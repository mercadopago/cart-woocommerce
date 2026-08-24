<?php

namespace MercadoPago\Woocommerce\Tests\Refund;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Refund\RefundHandler;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Exceptions\RefundException;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RefundHandlerTest extends TestCase
{
    use WoocommerceMock;

    private RefundHandler $refundHandler;
    private Requester $requester;
    private $order;
    private $mercadopagoMock;
    private $sellerConfig;
    private bool $userCanManageWoocommerce = true;

    protected function setUp(): void
    {
        // Mock WordPress sanitize_text_field function
        WP_Mock::userFunction('sanitize_text_field', [
            'return' => function ($text) {
                return $text;
            }
        ]);

        // Mock WordPress wp_is_mobile function
        WP_Mock::userFunction('wp_is_mobile', [
            'return' => false
        ]);

        // Mock WordPress current_user_can function. Defaults to allowed; a test can
        // set $this->userCanManageWoocommerce = false to exercise the permission gate
        // (re-declaring userFunction does not override this default reliably).
        $this->userCanManageWoocommerce = true;
        WP_Mock::userFunction('current_user_can', [
            'return' => function () {
                return $this->userCanManageWoocommerce;
            }
        ]);

        // Mock WordPress site_url function
        WP_Mock::userFunction('site_url', [
            'return' => 'https://test.com'
        ]);

        // Define MP_VERSION constant if not already defined
        if (!defined('MP_VERSION')) {
            define('MP_VERSION', '8.2.0');
        }

        // Define MP_PLATFORM_NAME constant if not already defined
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }

        if (!defined('MP_PRODUCT_ID_MOBILE')) {
            define('MP_PRODUCT_ID_MOBILE', 'BT7OFH09QS3001K5A0H0');
        }

        $this->requester = Mockery::mock(Requester::class);
        $this->order = Mockery::mock('WC_Order');

        // Add default expectation for _currency_ratio metadata
        $this->order->shouldReceive('get_meta')
            ->with('_currency_ratio')
            ->andReturn(null)
            ->byDefault();

        // Default: no checkout_type on the order (legacy orders). Tests that need a
        // specific product bucket (e.g. super_token) override this expectation.
        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn(null)
            ->byDefault();

        // Default: no prior refunds on the order (used by the idempotency key)
        $this->order->shouldReceive('get_total_refunded')
            ->andReturn(0.0)
            ->byDefault();
        $this->mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->sellerConfig = $this->mercadopagoMock->sellerConfig;

        $this->refundHandler = new RefundHandler($this->requester, $this->order, $this->mercadopagoMock);
    }

    /**
     * Helper method to mock Datadog with success expectation.
     * The 4th sendEvent argument carries the checkout_type (product bucket) so
     * refunds can be segmented in Datadog; null for legacy orders.
     *
     * Two events are emitted per processRefund call:
     * - woo_refund_success: once per individual payment (multi-payment = $times > 1)
     * - woo_refund_latency: always once per processRefund call (Rate + Duration signal)
     */
    private function mockDatadogSuccess(int $times = 1, ?string $checkoutType = null): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->times($times)
            ->with('woo_refund_success', 'refund_success', 'origin_woocommerce', $checkoutType);

        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('woo_refund_latency', Mockery::type('int'), null, $checkoutType);

        $this->injectDatadogMock($datadogMock);
    }

    /**
     * Helper method to mock Datadog with error expectation.
     * MP API failures (RefundException) emit mp_refund_error; unexpected
     * WooCommerce-side failures emit woo_refund_error. The 4th argument carries
     * the checkout_type (null for legacy orders).
     */
    private function mockDatadogError(string $errorCode = 'error', string $errorMessage = 'error message', string $metricName = 'mp_refund_error', ?string $checkoutType = null): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with($metricName, $errorCode, $errorMessage, $checkoutType);

        $this->injectDatadogMock($datadogMock);
    }

    /**
     * Helper method to inject Datadog mock
     */
    private function injectDatadogMock($datadogMock): void
    {
        $reflection = new \ReflectionClass($this->refundHandler);
        $datadogProperty = $reflection->getProperty('datadog');
        $datadogProperty->setAccessible(true);
        $datadogProperty->setValue($this->refundHandler, $datadogMock);
    }

    /**
     * Tests successful refund processing with amount and reason specified.
     *
     * Scenario:
     * - Valid payment ID exists in the order
     * - Valid access token is available
     * - API returns status 201 with approved refund data
     *
     * Expected result:
     * - Returns array with 'approved' status and refund data
     */
    public function testProcessRefundSuccess(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(1);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'id' => 'refund_123',
            'amount' => 100.00,
            'status' => 'approved'
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogSuccess();

        // Act
        $result = $this->refundHandler->processRefund($amount, $reason);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('approved', $result['status']);
        $this->assertEquals($responseData, $result['data']);
    }

    /**
     * Tests that a successful refund emits a latency metric with a non-negative integer
     * value and the order's checkout_type, following the same pattern as
     * CheckoutValidation::sendLatencyMetric (Rate + Duration signal — PSW-4309).
     */
    public function testProcessRefundSuccessEmitsLatencyMetric(): void
    {
        $paymentId   = '123456789';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')->andReturn(99);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund_lat_1', 'status' => 'approved']);

        $this->requester->shouldReceive('post')->once()->andReturn($response);

        $capturedLatency     = null;
        $capturedCheckoutType = null;

        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('woo_refund_success', 'refund_success', 'origin_woocommerce', null);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('woo_refund_latency', Mockery::on(function ($v) use (&$capturedLatency) {
                $capturedLatency = $v;
                return true;
            }), null, Mockery::on(function ($v) use (&$capturedCheckoutType) {
                $capturedCheckoutType = $v;
                return true;
            }));

        $reflection = new \ReflectionClass($this->refundHandler);
        $prop = $reflection->getProperty('datadog');
        $prop->setAccessible(true);
        $prop->setValue($this->refundHandler, $datadogMock);

        $this->refundHandler->processRefund(50.00, 'latency test');

        $this->assertIsInt($capturedLatency, 'Latency must be an integer (milliseconds)');
        $this->assertGreaterThanOrEqual(0, $capturedLatency, 'Latency must be non-negative');
        $this->assertNull($capturedCheckoutType, 'Legacy order has no checkout_type');
    }

    /**
     * Tests behavior when payment ID does not exist in the order.
     *
     * Scenario:
     * - Order meta '_Mercado_Pago_Payment_IDs' returns null
     * - No payment ID is available for refund processing
     *
     * Expected result:
     * - Throws RefundException with "Not Found" message
     */
    public function testProcessRefundWithoutPaymentId(): void
    {
        // Arrange

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn(null);

        $this->order->shouldReceive('get_id')
            ->andReturn(3);

        $this->mockDatadogError('404', 'Not Found: Payment ID not found in order metadata');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Not Found');

        // Act
        $this->refundHandler->processRefund(100.00);
    }

    /**
     * Tests that a Super Token order is no longer blocked from refunding.
     *
     * Scenario:
     * - The order is a Super Token checkout (checkout_type = 'super_token').
     * - The preemptive Super Token block was removed (PSW-4306).
     *
     * Expected result:
     * - No TYPE_SUPERTOKEN_NOT_SUPPORTED exception; the refund proceeds through the
     *   normal flow and succeeds, in parity with the other payment methods. The
     *   checkout_type stub makes this an intentional regression guard: if the block
     *   were reintroduced, it would read 'super_token' and throw, failing the success
     *   assertion (rather than an unexpected-call error).
     */
    public function testProcessRefundWithSuperTokenCheckout(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        // Build a Super Token order — the handler must treat it like any other method.
        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn('super_token');

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(2);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'id' => 'refund_123',
            'amount' => 100.00,
            'status' => 'approved'
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        // The success metric must carry checkout_type='super_token' so ST refunds
        // are segmentable in Datadog (PSW-4309).
        $this->mockDatadogSuccess(1, 'super_token');

        // Act
        $result = $this->refundHandler->processRefund($amount, $reason);

        // Assert — Super Token refund is no longer blocked; proceeds like any other method
        $this->assertIsArray($result);
        $this->assertEquals('approved', $result['status']);
        $this->assertEquals($responseData, $result['data']);
    }

    /**
     * Tests refund processing when API returns unauthorized error.
     *
     * Scenario:
     * - Valid payment ID but invalid/expired access token
     * - API returns status 401 (Unauthorized)
     *
     * Expected result:
     * - Throws RefundException with "Unauthorized" message
     */
    public function testProcessRefundWithUnauthorizedError(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(5);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            "code" => "unauthorized",
            "message" => "invalid access token"
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(401);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('401', 'Unauthorized: invalid access token');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Unauthorized');

        // Act
        $this->refundHandler->processRefund($amount, $reason);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(401, $responseData);
        $this->assertEquals('Unauthorized: Invalid credentials', $userMessage);
    }

    /**
     * Tests that an unexpected (non-MP) failure emits woo_refund_error.
     *
     * Scenario:
     * - Valid payment ID and access token
     * - The HTTP request itself throws a generic Exception (e.g. network failure),
     *   NOT a RefundException from an MP API response
     *
     * Expected result:
     * - The generic catch block emits woo_refund_error (not mp_refund_error)
     * - The original Exception is re-thrown for WooCommerce to handle
     */
    public function testProcessRefundUnexpectedExceptionEmitsWooRefundError(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(7);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        // The transport layer fails before any MP response is produced — this is a
        // WooCommerce-side/infra error, so it must be classified as woo_refund_error.
        $this->requester->shouldReceive('post')
            ->once()
            ->andThrow(new \Exception('Network failure', 0));

        $this->mockDatadogError('0', 'Network failure', 'woo_refund_error');

        // Assert Exceptions
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Network failure');

        // Act
        $this->refundHandler->processRefund($amount, $reason);
    }

    /**
     * Tests refund processing when API returns forbidden error.
     *
     * Scenario:
     * - Valid credentials but insufficient permissions
     * - API returns status 403 (Forbidden)
     *
     * Expected result:
     * - Throws RefundException with "Forbidden" message
     */
    public function testProcessRefundWithForbiddenError(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(6);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            "code" => "forbidden",
            "message" => "insufficient permissions"
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(403);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError(403, 'Forbidden: insufficient permissions');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Forbidden');

        // Act
        $this->refundHandler->processRefund($amount, $reason);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(403, $responseData);
        $this->assertEquals('Forbidden: Insufficient permissions', $userMessage);
    }

    /**
     * Tests refund processing when API returns internal server error.
     *
     * Scenario:
     * - Valid request but server experiences internal error
     * - API returns status 500 with error message
     *
     * Expected result:
     * - Throws RefundException with "Internal server error" message
     */
    public function testProcessRefundWithServerError(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(8);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            "code" => "internal_server_error",
            "message" => "internal server error"
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(500);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('500', 'Internal server error: internal server error');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Internal server error');

        // Act
        $this->refundHandler->processRefund($amount, $reason);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(500, $responseData);
        $this->assertEquals('Internal server error: A server error occurred while processing the refund', $userMessage);
    }

    /**
     * Tests refund processing when API returns object response data.
     *
     * Scenario:
     * - API returns successful status code (201)
     * - Response data is returned as object instead of array
     *
     * Expected result:
     * - Converts object to array and returns with 'approved' status
     */
    public function testProcessRefundWithObjectResponseData(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(10);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseObject = (object) [
            'id' => 'refund_789',
            'amount' => 100.00
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseObject);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogSuccess();

        // Act
        $result = $this->refundHandler->processRefund($amount, $reason);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('approved', $result['status']);
        $this->assertEquals(['id' => 'refund_789', 'amount' => 100.00], $result['data']);
    }

    /**
     * Tests refund processing when API returns a bad request error.
     *
     * Scenario:
     * - Valid payment ID and access token
     * - API returns status 400 with error message
     * - Request is malformed or contains invalid data
     *
     * Expected result:
     * - Throws RefundException with "Invalid Request" message
     */
    public function testProcessRefundWithBadRequestError(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(4);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            "code" => "bad_request",
            "message" => "The refund request contains invalid data"
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request: The refund request contains invalid data');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount, $reason);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: The refund request contains invalid data', $userMessage);
    }

    /**
     * Tests refund processing with validation errors from cause field - Invalid amount.
     *
     * Scenario:
     * - API returns status 400 with validation errors in 'cause' field
     * - Response contains structured error information
     *
     * Expected result:
     * - Throws RefundException with appropriate error message
     */
    public function testProcessRefundWithInvalidAmount(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $reason = 'Test refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(12);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'cause' => [
                'code' => 4040,
                'description' => 'Amount attribute must be positive'
            ]
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount, $reason);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: Amount attribute must be positive', $userMessage);
    }

    /**
     * Tests refund processing with validation errors from cause field - Payment too old to process refund.
     *
     * Scenario:
     * - API returns status 400 with error in 'cause' field
     * - Response contains error message in cause format
     *
     * Expected result:
     * - Throws RefundException with appropriate error message
     */
    public function testProcessRefundWithPaymentTooOld(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(13);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'cause' => [
                'code' => 2024,
                'description' => 'Payment too old to process refund'
            ]
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: Payment too old to process refund', $userMessage);
    }

    /**
     * Tests refund processing with validation errors from cause field - Invalid payment status.
     *
     * Scenario:
     * - API returns status 400 with error in 'cause' field
     * - Response contains error message in cause format
     *
     * Expected result:
     * - Throws RefundException with appropriate error message
     */
    public function testProcessRefundWithInvalidPaymentStatus(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(13);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'cause' => [
                'code' => 2063,
                'description' => 'The action requested is not valid for the current payment state'
            ]
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: he action requested is not valid for the current payment state', $userMessage);
    }

    /**
     * Tests refund processing with validation errors from cause field - Invalid maximumrefund amount.
     *
     * Scenario:
     * - API returns status 400 with error in 'cause' field
     * - Response contains error message in cause format
     *
     * Expected result:
     * - Throws RefundException with appropriate error message
     */
    public function testProcessRefundWithInvalidMaximumAmount(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(13);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'cause' => [
                'code' => 2017,
                'description' => 'Invalid refund amount'
            ]
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: Invalid refund amount', $userMessage);
    }

    /**
     * Tests refund processing with validation errors from original_message field - Payment not found.
     *
     * Scenario:
     * - API returns status 400 with error in 'original_message' field
     * - Response contains error message in original_message format
     *
     * Expected result:
     * - Throws RefundException with appropriate error message
     */
    public function testProcessRefundWithPaymentNotFound(): void
    {
        // Arrange
        $paymentId = '123456789';
        $amount = 100.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')
            ->andReturn(7);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = [
            'original_message' => '404 NOT_FOUND \"Payment not found\"'
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(400);
        $response->shouldReceive('getData')->andReturn([]);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogError('400', 'Invalid Request');

        // Assert Exceptions
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Invalid Request');

        // Act
        $this->refundHandler->processRefund($amount);

        // Assert User Message
        $userMessage = $this->refundHandler->refundStatusCodes->getUserMessage(400, $responseData);
        $this->assertEquals('Invalid Request: Payment not found', $userMessage);
    }

    /**
     * Tests successful refund processing with multiple payments.
     *
     * Scenario:
     * - Order has multiple payment IDs separated by comma
     * - Each payment has different paid/refunded amounts
     * - Refund amount needs to be distributed across payments
     *
     * Expected result:
     * - Returns merged array with all refund responses
     * - Processes payments in order until full amount is refunded
     */
    public function testProcessRefundWithMultiplePayments(): void
    {
        // Arrange
        $paymentIds = '123456789, 987654321, 555666777';
        $totalRefundAmount = 180.00;
        $reason = 'Multiple payments refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);

        $this->order->shouldReceive('get_id')
            ->andReturn(10);

        // Mock payment metadata for each payment
        $payment1Meta = '[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 0]';
        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method mastercard]/[Paid 100]/[Coupon 0]/[Refund 20]';
        $payment3Meta = '[Date 2024-01-15 11:30:00]/[Amount 50]/[Payment Type pix]/[Payment Method pix]/[Paid 50]/[Coupon 0]/[Refund 0]';

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 123456789')
            ->andReturn($payment1Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 987654321')
            ->andReturn($payment2Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 555666777')
            ->andReturn($payment3Meta);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->times(2)
            ->andReturn($accessToken);

        // Mock responses for each refund request
        $response1Data = ['id' => 'refund_1', 'amount' => 100.00, 'status' => 'approved'];
        $response2Data = ['id' => 'refund_2', 'amount' => 80.00, 'status' => 'approved'];

        $response1 = Mockery::mock(Response::class);
        $response1->shouldReceive('getStatus')->andReturn(201);
        $response1->shouldReceive('getData')->andReturn($response1Data);

        $response2 = Mockery::mock(Response::class);
        $response2->shouldReceive('getStatus')->andReturn(201);
        $response2->shouldReceive('getData')->andReturn($response2Data);

        // Expect two API calls: full amount for payment1, partial for payment2
        $this->requester->shouldReceive('post')
            ->twice()
            ->andReturn($response1, $response2);

        $this->mockDatadogSuccess(2);

        // Act
        $result = $this->refundHandler->processRefund($totalRefundAmount, $reason);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have 2 refunds

        // Check first refund
        $this->assertEquals('approved', $result[0]['status']);
        $this->assertArrayHasKey('data', $result[0]);

        // Check second refund
        $this->assertEquals('approved', $result[1]['status']);
        $this->assertArrayHasKey('data', $result[1]);
    }

    /**
     * Tests refund processing when some payments have no remaining amount.
     *
     * Scenario:
     * - Multiple payments where some are already fully refunded
     * - Only processes payments with remaining refundable amounts
     *
     * Expected result:
     * - Skips payments with zero remaining amount
     * - Processes only payments with available balance
     */
    public function testProcessRefundWithPartiallyRefundedPayments(): void
    {
        // Arrange
        $paymentIds = '111111111, 222222222, 333333333';
        $refundAmount = 50.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);

        $this->order->shouldReceive('get_id')
            ->andReturn(11);

        $payment1Meta = '[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 100]';
        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method mastercard]/[Paid 100]/[Coupon 0]/[Refund 30]';
        $payment3Meta = '[Date 2024-01-15 11:30:00]/[Amount 50]/[Payment Type pix]/[Payment Method pix]/[Paid 50]/[Coupon 0]/[Refund 0]';

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 111111111')
            ->andReturn($payment1Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 222222222')
            ->andReturn($payment2Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 333333333')
            ->andReturn($payment3Meta);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = ['id' => 'refund_partial', 'amount' => 50.00, 'status' => 'approved'];
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        // Should only call API once for payment2 (payment1 is fully refunded, payment3 not needed)
        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogSuccess();

        // Act
        $result = $this->refundHandler->processRefund($refundAmount);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result); // Should have 1 refund

        // Check single refund
        $this->assertEquals('approved', $result[0]['status']);
        $this->assertEquals($responseData, $result[0]['data']);
    }

    /**
     * Tests refund processing when exact amount matches remaining balances.
     *
     * Scenario:
     * - Multiple payments with exact refund amount available
     * - Refund process should stop exactly when target amount is reached
     *
     * Expected result:
     * - Processes exactly the needed payments
     * - Stops when refund amount is fully distributed
     */
    public function testProcessRefundWithExactAmountMatch(): void
    {
        // Arrange
        $paymentIds = '444444444, 555555555, 666666666';
        $refundAmount = 120.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);

        $this->order->shouldReceive('get_id')
            ->andReturn(12);

        $payment1Meta = '[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 0]';
        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method mastercard]/[Paid 100]/[Coupon 0]/[Refund 20]';
        $payment3Meta = '[Date 2024-01-15 11:30:00]/[Amount 50]/[Payment Type pix]/[Payment Method pix]/[Paid 50]/[Coupon 0]/[Refund 0]';

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 444444444')
            ->andReturn($payment1Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 555555555')
            ->andReturn($payment2Meta);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 666666666')
            ->never();

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->times(2)
            ->andReturn($accessToken);

        $response1Data = ['id' => 'refund_1', 'amount' => 100.00, 'status' => 'approved'];
        $response2Data = ['id' => 'refund_2', 'amount' => 20.00, 'status' => 'approved'];

        $response1 = Mockery::mock(Response::class);
        $response1->shouldReceive('getStatus')->andReturn(201);
        $response1->shouldReceive('getData')->andReturn($response1Data);

        $response2 = Mockery::mock(Response::class);
        $response2->shouldReceive('getStatus')->andReturn(201);
        $response2->shouldReceive('getData')->andReturn($response2Data);

        $this->requester->shouldReceive('post')
            ->twice()
            ->andReturn($response1, $response2);

        $this->mockDatadogSuccess(2);

        // Act
        $result = $this->refundHandler->processRefund($refundAmount);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have 2 refunds

        // Check first refund
        $this->assertEquals('approved', $result[0]['status']);
        $this->assertArrayHasKey('data', $result[0]);

        // Check second refund
        $this->assertEquals('approved', $result[1]['status']);
        $this->assertArrayHasKey('data', $result[1]);
    }

    /**
     * Tests refund processing when payment metadata is empty or invalid.
     *
     * Scenario:
     * - One of the payments has empty or malformed metadata
     * - Should handle gracefully and continue with other payments
     *
     * Expected result:
     * - Skips payment with invalid metadata
     * - Continues processing other valid payments
     */
    public function testProcessRefundWithInvalidPaymentMetadata(): void
    {
        // Arrange
        $paymentIds = '777777777, 888888888';
        $refundAmount = 50.00;
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);

        $this->order->shouldReceive('get_id')
            ->andReturn(13);

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 777777777')
            ->andReturn('');

        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 0]';
        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 888888888')
            ->andReturn($payment2Meta);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = ['id' => 'refund_valid', 'amount' => 50.00, 'status' => 'approved'];
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')
            ->once()
            ->andReturn($response);

        $this->mockDatadogSuccess();

        // Act
        $result = $this->refundHandler->processRefund($refundAmount);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result); // Should have 1 refund

        // Check single refund
        $this->assertEquals('approved', $result[0]['status']);
        $this->assertEquals($responseData, $result[0]['data']);
    }

    // -------------------------------------------------------------------------
    // Regression tests — WCS renewal order refunds
    // Verifies that RefundHandler reads _Mercado_Pago_Payment_IDs from the
    // renewal order passed to it, never from a parent order.
    // -------------------------------------------------------------------------

    public function testFullRefundOnRenewalUsesRenewalPaymentId(): void
    {
        $renewalPaymentId = 'RENEWAL-PAY-9001';
        $accessToken      = 'TEST-ACCESS-TOKEN';

        $this->order->shouldReceive('get_meta')->once()->with('_Mercado_Pago_Payment_IDs')->andReturn($renewalPaymentId);
        $this->order->shouldReceive('get_id')->andReturn(500);
        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->once()->andReturn($accessToken);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund-ok', 'status' => 'approved']);

        $this->requester
            ->shouldReceive('post')
            ->once()
            ->with(Mockery::pattern('/' . preg_quote($renewalPaymentId, '/') . '/'), Mockery::any(), Mockery::any())
            ->andReturn($response);

        $this->mockDatadogSuccess();

        $result = $this->refundHandler->processRefund(9.90, 'renewal refund');

        $this->assertEquals('approved', $result['status']);
    }

    public function testPartialRefundOnRenewalUsesRenewalPaymentId(): void
    {
        $renewalPaymentId = 'RENEWAL-PAY-9001';
        $accessToken      = 'TEST-ACCESS-TOKEN';

        $this->order->shouldReceive('get_meta')->once()->with('_Mercado_Pago_Payment_IDs')->andReturn($renewalPaymentId);
        $this->order->shouldReceive('get_id')->andReturn(500);
        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->once()->andReturn($accessToken);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund-partial', 'status' => 'approved']);

        $this->requester
            ->shouldReceive('post')
            ->once()
            ->with(
                Mockery::pattern('/' . preg_quote($renewalPaymentId, '/') . '/'),
                Mockery::any(),
                Mockery::on(fn($p) => isset($p['amount']) && $p['amount'] > 0)
            )
            ->andReturn($response);

        $this->mockDatadogSuccess();

        $result = $this->refundHandler->processRefund(4.95, 'partial renewal refund');

        $this->assertEquals('approved', $result['status']);
    }

    public function testRefundFailsWhenRenewalHasNoPaymentId(): void
    {
        $this->order->shouldReceive('get_meta')->once()->with('_Mercado_Pago_Payment_IDs')->andReturn('');
        $this->order->shouldReceive('get_id')->andReturn(500);

        $this->mockDatadogError('404', 'Not Found: Payment ID not found in order metadata');

        $this->expectException(RefundException::class);

        $this->refundHandler->processRefund(9.90);
    }

    public function testRenewalRefundNeverReadsParentPaymentId(): void
    {
        $renewalPaymentId = 'RENEWAL-PAY-9001';
        $parentPaymentId  = 'PARENT-PAY-1001';
        $accessToken      = 'TEST-ACCESS-TOKEN';

        $this->order->shouldReceive('get_meta')->once()->with('_Mercado_Pago_Payment_IDs')->andReturn($renewalPaymentId);
        $this->order->shouldReceive('get_id')->andReturn(500);
        $this->order->shouldNotReceive('get_parent_id');
        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->once()->andReturn($accessToken);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund-ok', 'status' => 'approved']);

        $this->requester
            ->shouldReceive('post')
            ->once()
            ->with(
                Mockery::not(Mockery::pattern('/' . preg_quote($parentPaymentId, '/') . '/')),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn($response);

        $this->mockDatadogSuccess();

        $result = $this->refundHandler->processRefund(9.90);

        $this->assertEquals('approved', $result['status']);
    }

    /**
     * The idempotency key is deterministic for the same inputs (same order state,
     * same payment_id and amount) — a re-submit of the same refund yields the same key.
     */
    public function testIdempotencyKeyIsDeterministic(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(7);
        $handler = new RefundHandler($this->requester, $order, $this->mercadopagoMock);

        $method = new \ReflectionMethod(RefundHandler::class, 'buildIdempotencyKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($handler, '123456789', 100.00, 0.0);
        $key2 = $method->invoke($handler, '123456789', 100.00, 0.0);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key1);
        $this->assertEquals($key1, $key2);
    }

    /**
     * Two sequential partial refunds (different amount already refunded before each)
     * produce distinct idempotency keys, even for the same payment_id and amount.
     */
    public function testIdempotencyKeyChangesWithPriorRefundedTotal(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(7);
        $handler = new RefundHandler($this->requester, $order, $this->mercadopagoMock);

        $method = new \ReflectionMethod(RefundHandler::class, 'buildIdempotencyKey');
        $method->setAccessible(true);

        // Same payment_id and amount, different "already refunded before" -> distinct keys.
        $keyA = $method->invoke($handler, '123456789', 20.00, 0.0);
        $keyB = $method->invoke($handler, '123456789', 20.00, 30.00);

        $this->assertNotEquals($keyA, $keyB, 'A different prior refunded total must change the key');
    }

    /**
     * When a currency_ratio is present, the "already refunded before" term of the key
     * is expressed in the same (converted) currency as the amount — both the amount
     * and the prior-refunded total are scaled by the ratio, so the two terms never
     * mix currencies. Under the previous implementation (store-currency total minus
     * converted amount) this key would differ.
     */
    public function testIdempotencyKeyAppliesCurrencyRatioToPriorRefunded(): void
    {
        $paymentId = '123456789';

        $this->order->shouldReceive('get_meta')
            ->with('_currency_ratio')
            ->andReturn(2.0);
        $this->order->shouldReceive('get_meta')
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(7);
        $this->order->shouldReceive('get_total_refunded')->andReturn(30.00);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->andReturn('APP-TOKEN');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund_1', 'status' => 'approved']);

        $capturedHeaders = null;
        $this->requester->shouldReceive('post')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($headers) use (&$capturedHeaders) {
                    $capturedHeaders = $headers;
                    return true;
                }),
                Mockery::any()
            )
            ->andReturn($response);

        $this->mockDatadogSuccess();

        $this->refundHandler->processRefund(10.00);

        // amount_mp = 10 * 2 = 20.00; before_mp = max(0, 30 * 2 - 20) = 40.00
        $expected = hash('sha256', $paymentId . '|20.00|7|40.00');
        $this->assertEquals($expected, $capturedHeaders['x-idempotency-key']);
    }

    /**
     * The refund request carries the x-idempotency-key header.
     */
    public function testRefundRequestSendsIdempotencyKeyHeader(): void
    {
        $paymentId = '123456789';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(1);
        $this->order->shouldReceive('get_total_refunded')->andReturn(100.00);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund_123', 'status' => 'approved']);

        $capturedHeaders = null;
        $this->requester->shouldReceive('post')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($headers) use (&$capturedHeaders) {
                    $capturedHeaders = $headers;
                    return true;
                }),
                Mockery::any()
            )
            ->andReturn($response);

        $this->mockDatadogSuccess();

        $this->refundHandler->processRefund(100.00, 'Test refund');

        $this->assertArrayHasKey('x-idempotency-key', $capturedHeaders);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $capturedHeaders['x-idempotency-key']);
    }

    /**
     * In a multi-payment order, each payment's idempotency key uses the SAME order-wide
     * "already refunded before" term, computed synchronously from
     * get_total_refunded(). The distinct per-iteration payment_id (and per-payment amount)
     * is what keeps the keys distinct within a single call.
     */
    public function testIdempotencyKeyUsesPerPaymentRefundedInMultiPayment(): void
    {
        $paymentIds = '123456789, 987654321';

        $this->order->shouldReceive('get_meta')
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);
        $this->order->shouldReceive('get_id')->andReturn(10);

        // No prior order-wide refunds -> order-wide before = max(0, 0 - 180) = 0 for both.
        // payment1: Paid 100, Refund 0 -> remaining 100 -> refund 100
        // payment2: Paid 100, Refund 20 -> remaining 80  -> refund 80
        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 123456789')
            ->andReturn('[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 0]');
        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 987654321')
            ->andReturn('[Date 2024-01-15 11:00:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method mastercard]/[Paid 100]/[Coupon 0]/[Refund 20]');

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->andReturn('APP-TOKEN');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund', 'status' => 'approved']);

        $capturedKeys = [];
        $this->requester->shouldReceive('post')
            ->twice()
            ->with(
                Mockery::any(),
                Mockery::on(function ($headers) use (&$capturedKeys) {
                    $capturedKeys[] = $headers['x-idempotency-key'];
                    return true;
                }),
                Mockery::any()
            )
            ->andReturn($response);

        $this->mockDatadogSuccess(2);

        // Refund 180 -> payment1 takes 100, payment2 takes 80; order-wide before = 0.00 for both.
        $this->refundHandler->processRefund(180.00);

        $expected1 = hash('sha256', '123456789|100.00|10|0.00');
        $expected2 = hash('sha256', '987654321|80.00|10|0.00');

        $this->assertCount(2, $capturedKeys);
        $this->assertEquals($expected1, $capturedKeys[0]);
        $this->assertEquals($expected2, $capturedKeys[1], 'Second payment key uses the order-wide "before" term (0.00) plus its own payment_id/amount');
        $this->assertNotEquals($capturedKeys[0], $capturedKeys[1]);
    }

    /**
     * Regression: a non-Super-Token payment method (e.g. credit card) refund is not
     * broken by the idempotency-key injection — it proceeds normally and carries the
     * x-idempotency-key header, in parity with Super Token. Makes the "non-ST regression"
     * acceptance criterion directly verifiable (the RefundHandler is checkout_type-agnostic).
     */
    public function testNonSuperTokenRefundIsNotBrokenByIdempotencyKey(): void
    {
        $paymentId = '555555555';

        // Non-ST order (credit card / custom checkout). The handler is checkout_type-agnostic;
        // the stub documents the scenario as an intentional non-ST regression guard.
        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn('custom');
        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(42);
        $this->order->shouldReceive('get_total_refunded')->andReturn(50.00);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn('APP-TOKEN');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund_card_1', 'status' => 'approved']);

        $capturedHeaders = null;
        $this->requester->shouldReceive('post')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($headers) use (&$capturedHeaders) {
                    $capturedHeaders = $headers;
                    return true;
                }),
                Mockery::any()
            )
            ->andReturn($response);

        // The success metric must carry checkout_type='custom' — the handler is
        // checkout_type-agnostic and forwards whatever the order records (PSW-4309).
        $this->mockDatadogSuccess(1, 'custom');

        $result = $this->refundHandler->processRefund(50.00, 'Card refund');

        $this->assertEquals('approved', $result['status']);
        $this->assertArrayHasKey('x-idempotency-key', $capturedHeaders);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $capturedHeaders['x-idempotency-key']);
    }

    /**
     * Super Token dedup: when the refund endpoint returns HTTP 200 with an empty body
     * (documented ST behaviour), no refund_id is available and addAppliedRefundId must
     * NOT be called. Deduplication for ST falls back to the value-based barrier
     * (refundAlreadyProcessed in OrderStatus::refundedFlow).
     */
    public function testSuperTokenRefundWithEmptyBodyDoesNotPersistRefundId(): void
    {
        $paymentId = '170349036297';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(43);
        $this->order->shouldReceive('get_total_refunded')->andReturn(0.0);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn('APP_USR-test');

        // ST endpoint returns HTTP 200 with no body.
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(null);

        $this->requester->shouldReceive('post')->once()->andReturn($response);

        // addAppliedRefundId must never be called when the body is empty.
        $this->mercadopagoMock->orderMetadata
            ->shouldNotReceive('addAppliedRefundId');

        $this->mockDatadogSuccess();

        $result = $this->refundHandler->processRefund(5.00, 'ST refund — empty body');

        $this->assertEquals('approved', $result['status']);
        $this->assertEmpty($result['data']);
    }

    /**
     * Dedup persistence: on a successful refund, the handler records the returned refund_id
     * via OrderMetadata::addAppliedRefundId so a later notification reflecting the same
     * refund is skipped.
     */
    public function testSuccessfulRefundPersistsAppliedRefundId(): void
    {
        $paymentId = '123456789';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(1);
        // Explicit: no prior refunds on the order (fix A1 reads this before the loop to
        // compute already-refunded-before; do not rely on the setUp default).
        $this->order->shouldReceive('get_total_refunded')->andReturn(0.0);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = ['id' => 'refund_123', 'amount' => 100.00, 'status' => 'approved'];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        $this->requester->shouldReceive('post')->once()->andReturn($response);

        // Assert the refund_id from the response is persisted for dedup.
        $this->mercadopagoMock->orderMetadata
            ->shouldReceive('addAppliedRefundId')
            ->once()
            ->with($this->order, 'refund_123');

        $this->mockDatadogSuccess();

        $result = $this->refundHandler->processRefund(100.00, 'Test refund');

        $this->assertEquals('approved', $result['status']);
    }

    /**
     * TC-02 — Partial refund of a Super Token order via the reused flow.
     *
     * Complements testProcessRefundWithSuperTokenCheckout (which covers the full
     * refund): a partial amount must reach the API body unchanged and the success
     * metric must carry checkout_type='super_token' (parity with other methods).
     */
    public function testPartialRefundWithSuperTokenCheckout(): void
    {
        $paymentId   = '123456789';
        $amount      = 50.00; // partial: less than the order total
        $reason      = 'Partial ST refund';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn('super_token');

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);

        $this->order->shouldReceive('get_id')->andReturn(21);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->once()
            ->andReturn($accessToken);

        $responseData = ['id' => 'refund_partial_st', 'amount' => 50.00, 'status' => 'approved'];
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn($responseData);

        // Capture the payload to assert the partial amount reached the API body.
        $capturedPayload = null;
        $this->requester->shouldReceive('post')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::on(function ($payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return true;
            }))
            ->andReturn($response);

        $this->mockDatadogSuccess(1, 'super_token');

        $result = $this->refundHandler->processRefund($amount, $reason);

        $this->assertEquals('approved', $result['status']);
        $this->assertEquals(50.00, $capturedPayload['amount'], 'Partial amount must reach the API body unchanged');
    }

    /**
     * TC-07 (payload) — currency_ratio scales the amount sent to the API.
     *
     * testIdempotencyKeyAppliesCurrencyRatioToPriorRefunded already checks the ratio
     * is applied to the idempotency key; this test closes the gap by asserting the
     * scaled amount is what actually reaches the refund request body.
     */
    public function testCurrencyRatioScalesRefundPayloadAmount(): void
    {
        $paymentId = '123456789';

        $this->order->shouldReceive('get_meta')
            ->with('_currency_ratio')
            ->andReturn(3.0);
        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentId);
        $this->order->shouldReceive('get_id')->andReturn(70);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->andReturn('APP-TOKEN');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getStatus')->andReturn(201);
        $response->shouldReceive('getData')->andReturn(['id' => 'refund_ratio', 'status' => 'approved']);

        $capturedPayload = null;
        $this->requester->shouldReceive('post')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::on(function ($payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return true;
            }))
            ->andReturn($response);

        $this->mockDatadogSuccess();

        // amount 10.00 * ratio 3.0 = 30.00 must be the value sent to the API.
        $this->refundHandler->processRefund(10.00);

        $this->assertEquals(30.00, $capturedPayload['amount'], 'currency_ratio must scale the amount sent to the API');
    }

    /**
     * TC-08 — requested amount exceeds the total remaining balance (multi-payment).
     *
     * Documents the actual RefundHandler behaviour: it does NOT block or throw here.
     * It distributes across every payment with a remaining balance and returns those
     * results; the excess simply has nowhere to go and triggers no extra request.
     *
     * Note: the "reject with an error and no API call" guarantee described in the
     * ticket lives upstream in WooCommerce core (WC_AJAX::refund_line_items validates
     * amount <= max_refund before ever calling process_refund), so it is not
     * reproducible at this unit level.
     */
    public function testRefundAmountExceedingTotalRemainingRefundsAllAvailable(): void
    {
        $paymentIds  = '123123123, 456456456';
        $accessToken = 'TEST-123456789';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);
        $this->order->shouldReceive('get_id')->andReturn(80);

        // Total remaining across both payments = 100 + 50 = 150.
        $payment1Meta = '[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 0]';
        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 50]/[Payment Type pix]/[Payment Method pix]/[Paid 50]/[Coupon 0]/[Refund 0]';

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 123123123')
            ->andReturn($payment1Meta);
        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 456456456')
            ->andReturn($payment2Meta);

        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')
            ->times(2)
            ->andReturn($accessToken);

        $response1 = Mockery::mock(Response::class);
        $response1->shouldReceive('getStatus')->andReturn(201);
        $response1->shouldReceive('getData')->andReturn(['id' => 'refund_1', 'amount' => 100.00, 'status' => 'approved']);

        $response2 = Mockery::mock(Response::class);
        $response2->shouldReceive('getStatus')->andReturn(201);
        $response2->shouldReceive('getData')->andReturn(['id' => 'refund_2', 'amount' => 50.00, 'status' => 'approved']);

        // Exactly two API calls — one per payment with balance; the excess (200 - 150)
        // triggers no additional request. Capture each payload to assert the amount
        // distributed per payment: a wrong split (e.g. 150/0, or sending the whole 200)
        // would still pass a count-only check, so this pins the core guarantee.
        $capturedAmounts = [];
        $this->requester->shouldReceive('post')
            ->twice()
            ->with(Mockery::any(), Mockery::any(), Mockery::on(function ($payload) use (&$capturedAmounts) {
                $capturedAmounts[] = $payload['amount'];
                return true;
            }))
            ->andReturn($response1, $response2);

        $this->mockDatadogSuccess(2);

        // Request 200 while only 150 is refundable across the two payments.
        $result = $this->refundHandler->processRefund(200.00);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('approved', $result[0]['status']);
        $this->assertEquals('approved', $result[1]['status']);
        // The available balance is distributed: 100 to payment 1, then 50 to payment 2.
        $this->assertSame([100.00, 50.00], $capturedAmounts, 'Should distribute 100 then 50 across the two payments');
    }

    /**
     * TC-09 — every payment already fully refunded (multi-payment).
     *
     * When paid == refund for all payments, the handler skips them all, makes no API
     * call, emits no success metric, and returns an empty array. Only the latency
     * metric fires (once per processRefund call).
     */
    public function testRefundWhenAllPaymentsFullyRefundedMakesNoApiCall(): void
    {
        $paymentIds = '111222333, 444555666';

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn($paymentIds);
        $this->order->shouldReceive('get_id')->andReturn(90);

        // Both payments fully refunded: paid == refund -> remaining 0.
        $payment1Meta = '[Date 2024-01-15 10:30:00]/[Amount 100]/[Payment Type credit_card]/[Payment Method visa]/[Paid 100]/[Coupon 0]/[Refund 100]';
        $payment2Meta = '[Date 2024-01-15 11:00:00]/[Amount 50]/[Payment Type pix]/[Payment Method pix]/[Paid 50]/[Coupon 0]/[Refund 50]';

        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 111222333')
            ->andReturn($payment1Meta);
        $this->order->shouldReceive('get_meta')
            ->with('Mercado Pago - Payment 444555666')
            ->andReturn($payment2Meta);

        // No API call and no access token are needed since nothing is refundable.
        $this->requester->shouldReceive('post')->never();
        $this->sellerConfig->shouldReceive('getCredentialsAccessToken')->never();

        // Only the latency metric fires; the success metric must never be emitted.
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('woo_refund_latency', Mockery::type('int'), null, null);
        $datadogMock->shouldReceive('sendEvent')
            ->with('woo_refund_success', Mockery::any(), Mockery::any(), Mockery::any())
            ->never();
        $this->injectDatadogMock($datadogMock);

        $result = $this->refundHandler->processRefund(75.00);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * TC-05 (ST parity) — Super Token order with no payment ID.
     *
     * Must fail exactly like any other method (RefundException, no API call), and the
     * error metric must carry checkout_type='super_token' so ST failures stay
     * segmentable in Datadog (PSW-4309).
     */
    public function testMissingPaymentIdEmitsSuperTokenCheckoutTypeInErrorMetric(): void
    {
        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn('super_token');

        $this->order->shouldReceive('get_meta')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs')
            ->andReturn(null);

        $this->order->shouldReceive('get_id')->andReturn(50);

        // No API call — the failure happens before any request is built.
        $this->requester->shouldReceive('post')->never();

        $this->mockDatadogError(
            '404',
            'Not Found: Payment ID not found in order metadata',
            'mp_refund_error',
            'super_token'
        );

        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Not Found');

        $this->refundHandler->processRefund(100.00);
    }

    /**
     * Refund is a privileged action: a user without the manage_woocommerce
     * capability must be blocked before anything else runs. The permission check
     * remains the first operation, but the denial is observed by the standard
     * WooCommerce refund error metric and log path.
     */
    public function testProcessRefundThrowsWhenUserLacksPermission(): void
    {
        // Deny the manage_woocommerce capability (setUp default is allowed).
        $this->userCanManageWoocommerce = false;

        // The permission gate runs before any request is built.
        $this->requester->shouldReceive('post')->never();

        $this->mockDatadogError(
            '0',
            RefundException::TYPE_NO_PERMISSION,
            'woo_refund_error'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(RefundException::TYPE_NO_PERMISSION);

        $this->refundHandler->processRefund(100.00);
    }
}

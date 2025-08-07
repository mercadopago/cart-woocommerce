<?php

namespace MercadoPago\Woocommerce\Tests\Refund;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Refund\RefundHandler;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
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

    protected function setUp(): void
    {
        $this->woocommerceSetUp();

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

        // Mock WordPress current_user_can function
        WP_Mock::userFunction('current_user_can', [
            'return' => true
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
        $this->mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->sellerConfig = $this->mercadopagoMock->sellerConfig;

        $this->refundHandler = new RefundHandler($this->requester, $this->order, $this->mercadopagoMock);
    }

    /**
     * Helper method to mock Datadog with success expectation
     */
    private function mockDatadogSuccess(int $times = 1): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->times($times)
            ->with('woo_refund_success', 'refund_success', 'origin_woocommerce');

        $this->injectDatadogMock($datadogMock);
    }

    /**
     * Helper method to mock Datadog with error expectation
     */
    private function mockDatadogError(string $errorCode = 'error', string $errorMessage = 'error message'): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('woo_refund_error', $errorCode, $errorMessage);

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
     * Tests refund processing when checkout type is super_token.
     *
     * Scenario:
     * - Order has checkout_type meta set to 'super_token'
     * - Refund is not supported for super_token checkouts
     *
     * Expected result:
     * - Throws Exception with RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED
     */
    public function testProcessRefundWithSuperTokenCheckout(): void
    {
        // Arrange
        $this->order->shouldReceive('get_meta')
            ->with('checkout_type')
            ->andReturn('super_token');

        $this->order->shouldReceive('get_id')
            ->andReturn(2);

        // Assert Exceptions
        $this->expectException(\Exception::class);

        // Act
        $this->refundHandler->processRefund(100.00);
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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
            ->with('checkout_type')
            ->andReturn("");

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
}

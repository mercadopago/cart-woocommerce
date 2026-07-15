<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Subscriptions;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration tests for the CIT (Customer Initiated Transaction) flow.
 *
 * Covers the first payment of a subscription: checkout -> AP v2 CIT -> metadata persistence.
 * Uses fixture JSON responses from ./fixtures/ to simulate the AP v2 API.
 *
 * Tests invoke process_subscription_initial_payment() directly (bypassing the routing
 * logic tested in CustomGatewaySubscriptionsTest) to avoid static method mocking issues.
 *
 * AC-3 Datadog Validation Strategy:
 * - The handler (gateway) does NOT call Datadog directly for CIT operations
 * - Datadog mp_api_error metrics are instrumented automatically by {@see Requester}
 * - This is validated in {@see RequesterTest} with 15+ scenarios covering success (no metric)
 *   and failure cases (mp_api_error with correct status codes)
 * - These integration tests verify that the handler correctly delegates to AutomaticPaymentsClient
 *   and does not emit duplicate metrics
 *
 * @spec feat-001 US-3, US-6, US-7 | DD-1, DD-3, DD-7
 * @covers \MercadoPago\Woocommerce\Gateways\CustomGateway::process_subscription_initial_payment
 * @covers \MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient::cit
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CitFlowTest extends TestCase
{
    /**
     * @var Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    /**
     * @var Mockery\MockInterface|Datadog
     */
    private $datadogMock;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        if (!function_exists('wcs_get_subscriptions_for_order')) {
            require_once __DIR__ . '/../../Mocks/WcsStubs.php';
        }

        $this->gateway = Mockery::mock(CustomGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        MercadoPagoMock::mockTranslations($this->gateway, ['storeTranslations', 'adminTranslations']);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')->byDefault()->andReturn('TEST-public-key');
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsAccessToken')->byDefault()->andReturn('TEST-access-token');
        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('getProductionMode')->byDefault()->andReturn('yes');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setIsProductionModeData')->byDefault();
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setUsedGatewayData')->byDefault();
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCurrencyRatioData')->byDefault();
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCustomMetadata')->byDefault();

        $this->datadogMock = Mockery::mock(Datadog::class);
        $this->gateway->datadog = $this->datadogMock;

        $this->gateway->settings = [
            'currency_conversion' => 'no',
            'enabled'             => 'yes',
            'title'               => 'Test Gateway',
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wcs_subscriptions'], $GLOBALS['__wcs_subs_for_order']);
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Loads a stub JSON file as an associative array.
     *
     * The handler casts Response::getData() to array at the top level, so nested
     * objects become arrays. We load as assoc array for consistency in assertions.
     */
    private function loadStubAsArray(string $name): array
    {
        $path = __DIR__ . "/fixtures/{$name}.json";
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Loads a stub JSON file as stdClass (for Response::setData()).
     *
     * Response::getData() returns what was set, then handler casts to array.
     * Using stdClass at top level with array children mimics real behavior.
     */
    private function loadStub(string $name): object
    {
        $path = __DIR__ . "/fixtures/{$name}.json";
        $data = json_decode(file_get_contents($path), true);
        return (object) $data;
    }

    /**
     * Creates a Response mock with the given status and data.
     */
    private function makeResponse(int $status, object $data): Response
    {
        $response = new Response();
        $response->setStatus($status);
        $response->setData($data);
        return $response;
    }

    /**
     * Creates a WC_Order mock with common expectations.
     */
    private function makeOrderMock(int $orderId): Mockery\MockInterface
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_total')->andReturn(49.90);
        $order->shouldReceive('update_status')->byDefault()->andReturnTrue();
        $order->shouldReceive('save')->byDefault();
        return $order;
    }

    /**
     * Creates a WC_Subscription mock.
     */
    private function makeSubscriptionMock(int $subId): Mockery\MockInterface
    {
        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn($subId);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');
        return $subscription;
    }

    /**
     * Invokes the private process_subscription_initial_payment() method via reflection.
     */
    private function invokeInitialPayment(\WC_Order $order): array
    {
        $reflection = new \ReflectionClass(CustomGateway::class);
        $method     = $reflection->getMethod('process_subscription_initial_payment');
        $method->setAccessible(true);
        return $method->invoke($this->gateway, $order);
    }

    /**
     * AC-1: CIT approved -> metadata persisted on subscription, payment_complete() called.
     * AC-3: Handler does NOT emit Datadog metrics directly (delegated to Requester layer).
     */
    public function testCitApprovedPersistsMetadataAndCompletesPayment(): void
    {
        $orderId = 101;
        $subId   = 789;
        $stubArr = $this->loadStubAsArray('cit-approve');
        $stub    = $this->loadStub('cit-approve');

        $order        = $this->makeOrderMock($orderId);
        $subscription = $this->makeSubscriptionMock($subId);

        $order->shouldReceive('get_checkout_order_received_url')
            ->andReturn('https://store.test/order-received');

        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->once()
            ->andReturn('TEST-preapproval-token');

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_subscription_id', 'CPP-WSUB-1001');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_customer_id', 'MOCK-CUST-2001');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_active_card_id', '9876543210');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_active_card_last_four', '6351');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_active_card_brand', 'master');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')
            ->once()->with($subscription, '_mp_subscription_created_at', Mockery::type('string'));

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('mapApiErrorToUserMessage')
            ->byDefault()
            ->andReturn('Erro genérico');

        $this->gateway->mercadopago->automaticPaymentsClient
            ->shouldReceive('cit')
            ->once()
            ->andReturn($this->makeResponse(201, $stub));

        $this->datadogMock->shouldNotReceive('sendEvent');

        // handleResponseStatus() approved path
        $this->gateway->mercadopago->helpers->cart->shouldReceive('emptyCart')->byDefault()->andReturnNull();
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->byDefault()->andReturn(false);
        $this->gateway->mercadopago->orderStatus->shouldReceive('getOrderStatusMessage')->byDefault()->andReturn('Aprovado');
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeApprovedStatusNotice')->byDefault()->andReturnNull();
        $this->gateway->mercadopago->orderStatus->shouldReceive('setOrderStatus')->byDefault()->andReturnNull();

        $this->gateway->shouldReceive('getCheckoutFormData')
            ->once()
            ->andReturn(['token' => 'tok_123', 'payment_method_id' => 'master', 'doc_number' => '12345678909']);

        $this->gateway->shouldReceive('buildCitPayload')
            ->once()
            ->andReturn(['token' => 'tok_123']);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCustomMetadata')
            ->once()
            ->with($order, Mockery::on(fn($data) => ($data['id'] ?? null) === $stubArr['payment']['id']));

        $result = $this->invokeInitialPayment($order);

        $this->assertSame('success', $result['result']);
        $this->assertSame('https://store.test/order-received', $result['redirect']);
    }

    /**
     * AC-2: CIT rejected -> order stays pending, user sees friendly message.
     * AC-3: Handler does NOT emit Datadog metrics directly (Requester emits mp_api_error
     *       automatically when it receives 4xx/5xx - tested in RequesterTest).
     */
    public function testCitRejectedKeepsOrderPendingWithFriendlyMessage(): void
    {
        $orderId = 102;
        $subId   = 790;
        $stubArr = $this->loadStubAsArray('cit-reject-422');

        $order        = $this->makeOrderMock($orderId);
        $subscription = $this->makeSubscriptionMock($subId);

        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_meta_data');
        $order->shouldNotReceive('save');

        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->once()
            ->andReturn('TEST-preapproval-token');

        $rejectedData = (object) [
            'payment' => [
                'id'            => null,
                'status'        => 'rejected',
                'status_detail' => 'cc_rejected_other_reason',
            ],
            'subscription' => ['id' => 'CPP-WSUB-XXX'],
            'error'        => $stubArr['error'],
        ];

        $this->gateway->mercadopago->automaticPaymentsClient
            ->shouldReceive('cit')
            ->once()
            ->andReturn($this->makeResponse(422, $rejectedData));

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('mapApiErrorToUserMessage')
            ->once()
            ->andReturn('Pagamento recusado pelo emissor do cartão.');

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->gateway->shouldReceive('getCheckoutFormData')
            ->once()
            ->andReturn(['token' => 'tok_456', 'payment_method_id' => 'visa', 'doc_number' => '98765432100']);

        $this->gateway->shouldReceive('buildCitPayload')
            ->once()
            ->andReturn(['token' => 'tok_456']);

        $result = $this->invokeInitialPayment($order);

        $this->assertSame('failure', $result['result']);
        $this->assertStringContainsString('recusado', $result['messages']);
    }

    /**
     * CIT orphan (no subscription.id in response) -> CRITICAL log, generic error to user.
     *
     * The orphan detection happens INSIDE AutomaticPaymentsClient::cit() — when the AP v2 API
     * returns 2xx but without subscription.id, the client logs an error and throws RuntimeException.
     * The handler catches this and returns failure to the user.
     *
     * AC-3: Handler does NOT emit Datadog metrics directly. For orphan detection (2xx without
     * subscription.id), no mp_api_error is emitted since HTTP was successful - the error is
     * logged at the application layer instead.
     */
    public function testCitOrphanLogsErrorAndAbortsWithGenericMessage(): void
    {
        $orderId = 103;
        $subId   = 791;

        $order        = $this->makeOrderMock($orderId);
        $subscription = $this->makeSubscriptionMock($subId);

        $order->shouldNotReceive('payment_complete');

        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->once()
            ->andReturn('TEST-preapproval-token');

        $this->gateway->mercadopago->automaticPaymentsClient
            ->shouldReceive('cit')
            ->once()
            ->andThrow(new \RuntimeException('Erro ao processar pagamento.'));

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->gateway->shouldReceive('getCheckoutFormData')
            ->once()
            ->andReturn(['token' => 'tok_789', 'payment_method_id' => 'amex', 'doc_number' => '11122233344']);

        $this->gateway->shouldReceive('buildCitPayload')
            ->once()
            ->andReturn(['token' => 'tok_789']);

        // RuntimeException from cit() propagates out of the private method; process_payment()'s
        // try-catch converts it to a failure result. When testing the private method directly
        // via reflection we assert the propagated exception instead.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Erro ao processar pagamento.');

        $this->invokeInitialPayment($order);
    }

    /**
     * When currency_conversion is active and getRatio() returns an invalid value (≤ 0),
     * _currency_ratio must NOT be saved AND a warning must be logged — both conditions
     * together prove the else branch was taken and remains observable.
     *
     * Uses a fresh File mock to avoid conflict with the base warning expectation
     * registered by MercadoPagoMock::setMocksForLogFunctions in setUp.
     */
    public function testCurrencyRatioSkipLogsWarningWhenRatioIsInvalid(): void
    {
        $this->gateway->settings['currency_conversion'] = 'yes';

        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->andReturn(0.0);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCurrencyRatioData')
            ->never();

        $logFile = Mockery::mock(File::class);
        $logFile->shouldReceive('error')->byDefault();
        $logFile->shouldReceive('info')->byDefault();
        $logFile->shouldReceive('debug')->byDefault();
        $logFile->shouldReceive('notice')->byDefault();
        $logFile->shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'currency_ratio_skipped'));
        $this->gateway->mercadopago->logs->file = $logFile;

        $order        = $this->makeOrderMock(998);
        $subscription = $this->makeSubscriptionMock(887);

        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('');

        // Access token is intentionally empty here — the currency ratio check (what this
        // test validates) runs before the token check. The subsequent token check now throws;
        // we catch it so the Mockery expectation on warning() can be verified in tearDown.
        try {
            $this->invokeInitialPayment($order);
        } catch (\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException $e) {
            // expected — token is empty; not what this test is asserting
        }

        $this->addToAssertionCount(1);
    }

    /**
     * When currency_conversion is active and getRatio() returns a positive value,
     * setCurrencyRatioData() must be called with that ratio — regression guard for the
     * fix that added this call to process_subscription_initial_payment().
     */
    public function testCurrencyRatioIsStoredWhenCurrencyConversionIsActive(): void
    {
        $this->gateway->settings['currency_conversion'] = 'yes';

        $ratio = 5.177;
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->andReturn($ratio);

        $order        = $this->makeOrderMock(999);
        $subscription = $this->makeSubscriptionMock(888);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCurrencyRatioData')
            ->once()
            ->with($order, $ratio);

        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('');
        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();

        // Access token is intentionally empty — currency ratio is set before the token check.
        // Catch the subsequent InvalidCheckoutDataException so Mockery can verify ->once() in tearDown.
        try {
            $this->invokeInitialPayment($order);
        } catch (\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException $e) {
            // expected — token is empty; not what this test is asserting
        }

        // Mockery::close() in tearDown enforces the ->once() expectation above.
        $this->addToAssertionCount(1);
    }

    /**
     * Edge case: AP v2 returns approved status but without an 'id' field in payment.
     * setCustomMetadata receives the partial payment array: extractPaymentId returns null,
     * _Mercado_Pago_Payment_IDs is not written (field left empty).
     * Probability is minimal (AP v2 always returns id on 2xx approved), but the test
     * documents and guards the known behaviour of null-safe extractPaymentId.
     */
    public function testCitWithMissingPaymentIdDoesNotSetPaymentIds(): void
    {
        $orderId      = 997;
        $subId        = 886;
        $stubArr      = $this->loadStubAsArray('cit-approve');
        $stub         = $this->loadStub('cit-approve');

        // Override payment to have status=approved but no id.
        $partialPayment = (object) array_merge(
            (array) $stub,
            ['payment' => ['status' => 'approved']] // no 'id'
        );

        $order        = $this->makeOrderMock($orderId);
        $subscription = $this->makeSubscriptionMock($subId);

        $order->shouldReceive('get_checkout_order_received_url')
            ->andReturn('https://store.test/order-received');
        $GLOBALS['__wcs_subscriptions'] = [$subscription];

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')->once()->andReturn('TEST-preapproval-token');
        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('setSubscriptionMeta')->byDefault();

        $this->gateway->mercadopago->automaticPaymentsClient
            ->shouldReceive('cit')->once()
            ->andReturn($this->makeResponse(200, $partialPayment));

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->gateway->mercadopago->helpers->cart->shouldReceive('emptyCart')->byDefault();
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->byDefault()->andReturn(false);
        $this->gateway->mercadopago->orderStatus->shouldReceive('getOrderStatusMessage')->byDefault()->andReturn('OK');
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeApprovedStatusNotice')->byDefault();
        $this->gateway->mercadopago->orderStatus->shouldReceive('setOrderStatus')->byDefault();

        $this->gateway->shouldReceive('getCheckoutFormData')->once()
            ->andReturn(['token' => 'tok_partial', 'payment_method_id' => 'visa', 'doc_number' => '12345678900']);
        $this->gateway->shouldReceive('buildCitPayload')->once()
            ->andReturn(['token' => 'tok_partial']);

        // setCustomMetadata called with partial data — extractPaymentId returns null
        // → _Mercado_Pago_Payment_IDs not written.
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCustomMetadata')
            ->once()
            ->with($order, ['status' => 'approved']);

        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $order->shouldReceive('update_status')->byDefault()->andReturnTrue();

        $result = $this->invokeInitialPayment($order);

        $this->assertSame('success', $result['result']);
    }
}

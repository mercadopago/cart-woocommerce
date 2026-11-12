<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixStatusEndpoint;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixStatusEndpointTest extends TestCase
{
    private const ORDER_ID = 321;

    private const ORDER_KEY = 'wc_order_SYNTHETIC123';

    private const NONCE = 'synthetic_nonce';

    private const ATTEMPT_KEY = 'attempt_fixture_query_success_001';

    private const ORDER_REFERENCE = 'ORDER_SYNTHETIC_001';

    /** @var OrderMetadata|\Mockery\MockInterface */
    private $metadata;

    /** @var SmartPixOrderClient|\Mockery\MockInterface */
    private $client;

    /** @var Seller|\Mockery\MockInterface */
    private $seller;

    /** @var SmartPixStatusLock|\Mockery\MockInterface */
    private $lock;

    /** @var SmartPixStatusLock|\Mockery\MockInterface */
    private $terminalLock;

    private const LEASE = ['name' => 'mp_smart_pix_status_lock_321', 'value' => 'lease-owner'];

    private const TERMINAL_LEASE = ['name' => 'mp_smart_pix_terminal_lock_321', 'value' => 'terminal-owner'];

    /** @var array<string> */
    private array $reportedReasons = [];

    /** @var array<array{string, string, string}> */
    private array $reportedMetrics = [];

    /** @var list<array<string, string>> Details of each reported metric, in the same order. */
    private array $reportedDetails = [];

    private string $attemptStart = '';

    private string $terminalReported = '';

    private string $thankyouResultReported = '';

    /**
     * @dataProvider attemptStartProvider
     * @param mixed $pendingSince
     */
    public function testTheAttemptAgeAcceptsAStoredIntegerOrDigitStringStartAndIgnoresAnythingElse($pendingSince, ?int $expected): void
    {
        $order = Mockery::mock(WC_Order::class);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn(
            is_callable($pendingSince) ? $pendingSince() : $pendingSince
        );

        $age = SmartPixStatusEndpoint::attemptAgeSeconds($metadata, $order);

        if ($expected === null) {
            $this->assertNull($age);
            return;
        }
        // time() may advance by one second between the stub and the call.
        $this->assertGreaterThanOrEqual($expected, $age);
        $this->assertLessThanOrEqual($expected + 1, $age);
    }

    public static function attemptStartProvider(): array
    {
        return [
            'integer start' => [static fn(): int => time() - 90, 90],
            'digit string start' => [static fn(): string => (string) (time() - 30), 30],
            'future start' => [static fn(): int => time() + 60, 0],
            'empty' => ['', null],
            'zero' => [0, null],
            'malformed' => ['12a', null],
            'missing' => [null, null],
        ];
    }

    public function testBrowserPollingFailureIsAuthenticatedAndDoesNotReadOrMutateOrders(): void
    {
        $order = $this->requestOrder('pending');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        WP_Mock::userFunction('get_transient', ['return' => false]);
        WP_Mock::userFunction('set_transient', ['times' => 1]);
        $this->client->shouldNotReceive('query');
        $order->shouldNotReceive('payment_complete', 'update_status');
        $request = $this->request() + ['polling_failure' => 'network',
            'polling_failure_message' => 'TypeError: Failed to fetch; token=SECRET_TOKEN; buyer@example.test'];
        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($request));
        $this->assertSame([['smart_pix_server_error', 'status_poll_client', 'network']], $this->reportedMetrics);
        $message = $this->reportedDetails[0]['diagnostic_message'];
        $this->assertStringContainsString('Failed to fetch', $message);
        $this->assertStringNotContainsString('SECRET_TOKEN', $message);
        $this->assertStringNotContainsString('buyer@example.test', $message);
    }

    public function testBrowserPollingFailureCannotBypassOrderAuthorization(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => false]);
        $this->client->shouldNotReceive('query');
        $request = $this->request() + ['polling_failure' => 'network', 'polling_failure_message' => 'Failed to fetch'];
        $this->assertSame(['rejection' => 'invalid_nonce', 'state' => null], $this->endpoint()->resolve($request));
        $this->assertSame([], $this->reportedMetrics);
    }

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('WC', ['return' => (object) ['session' => null]]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldReceive('getPpPaymentStatusDetail')->andReturn('')->byDefault();
        $this->client = Mockery::mock(SmartPixOrderClient::class);
        $this->client->shouldNotReceive('create', 'cancel', 'refund');
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getSiteId')->andReturn('MLB');
        $this->seller->shouldReceive('getCustIdFromAT')->andReturn('123456');
        $this->lock = Mockery::mock(SmartPixStatusLock::class);
        $this->lock->shouldReceive('acquire')->with(self::ORDER_ID)->andReturn(self::LEASE)->byDefault();
        $this->lock->shouldReceive('release')->with(self::LEASE)->andReturn(true)->byDefault();
        $this->terminalLock = Mockery::mock(SmartPixStatusLock::class);
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->andReturn(self::TERMINAL_LEASE)->byDefault();
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->andReturn(true)->byDefault();
        $this->reportedReasons = [];
        $this->reportedMetrics = [];
        $this->reportedDetails = [];
        $this->attemptStart = (string) (time() - 42);
        $this->terminalReported = '';
        $this->thankyouResultReported = '';
    }

    /** @dataProvider thankyouReceiptProvider */
    public function testThankyouDurationIsValidatedWithoutProviderReadsOrFinancialEffects(string $status, $milliseconds, ?string $duration): void
    {
        $order = $this->requestOrder($status);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');
        $this->lock->shouldNotReceive('acquire');
        if ($duration === null) {
            $this->terminalLock->shouldNotReceive('acquire');
        }

        $result = $this->endpoint()->resolve($this->request() + ['thankyou_result_time_ms' => $milliseconds]);

        $state = SmartPixStatusEndpoint::publicStateFor($order);
        $this->assertSame($state, $result['state']);
        $this->assertSame($duration === null ? [] : [[SmartPixMetricSchema::THANKYOU_RESULT_TIME, $state, $duration]], $this->reportedMetrics);
        $this->assertSame($duration === null ? '' : $state, $this->thankyouResultReported);
    }

    /** @dataProvider repeatedThankyouReceiptProvider */
    public function testRepeatedThankyouReceiptsWithOtherDurationsReportOnlyTheFirstSampleOfTheAttempt(string $status, string $state): void
    {
        $order = $this->requestOrder($status);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');
        $this->lock->shouldNotReceive('acquire');
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturn(self::TERMINAL_LEASE);
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);

        $results = [];
        foreach (['7250', '1000', '960000'] as $milliseconds) {
            $results[] = $this->endpoint()->resolve($this->request() + ['thankyou_result_time_ms' => $milliseconds]);
        }

        $this->assertSame(array_fill(0, 3, ['rejection' => null, 'state' => $state]), $results);
        $this->assertSame([[SmartPixMetricSchema::THANKYOU_RESULT_TIME, $state, '7.250s']], $this->reportedMetrics);
        $this->assertSame($state, $this->thankyouResultReported);
    }

    public static function repeatedThankyouReceiptProvider(): array
    {
        return [
            'approval' => ['processing', 'approved'],
            'rejection' => ['failed', 'rejected'],
        ];
    }

    public function testAThankyouReceiptFlaggedByAConcurrentRequestUnderTheLeaseIsNotReported(): void
    {
        $order = $this->requestOrder('processing');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $this->metadata->shouldNotReceive('setPpOrderThankyouResultReported');
        // The cached pre-check reads no flag; the forced reload under the lease sees the other receipt's flag.
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()
            ->andReturnUsing(function (): array {
                $this->thankyouResultReported = 'approved';
                return self::TERMINAL_LEASE;
            });
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);

        $result = $this->endpoint()->resolve($this->request() + ['thankyou_result_time_ms' => '2000']);

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $result);
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testAContendedThankyouLeaseDropsTheSampleWithoutChangingTheResult(): void
    {
        $order = $this->requestOrder('failed');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $this->metadata->shouldNotReceive('setPpOrderThankyouResultReported');
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturnNull();
        $this->terminalLock->shouldNotReceive('release');

        $result = $this->endpoint()->resolve($this->request() + ['thankyou_result_time_ms' => '2000']);

        $this->assertSame(['rejection' => null, 'state' => 'rejected'], $result);
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testAFailedThankyouFlagWriteDropsTheSampleInsteadOfRiskingADuplicate(): void
    {
        $order = $this->requestOrder('processing');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $this->metadata->shouldReceive('setPpOrderThankyouResultReported')->with($order, 'approved')->once()
            ->andThrow(new \RuntimeException('database down'));
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);

        $result = $this->endpoint()->resolve($this->request() + ['thankyou_result_time_ms' => '2000']);

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $result);
        $this->assertSame([], $this->reportedMetrics);
    }

    public static function thankyouReceiptProvider(): array
    {
        return [
            'approval' => ['processing', '12345', '12.345s'],
            'rejection' => ['failed', '120250', '120.250s'],
            'measured zero' => ['completed', '0', '0.000s'],
            'upper bound' => ['processing', '960000', '960.000s'],
            'too large' => ['processing', '960001', null],
            'huge number' => ['processing', '999999999999999999999', null],
            'pending is not a result' => ['pending', '12345', null],
            'cancelled' => ['cancelled', '12345', null],
            'refunded' => ['refunded', '12345', null],
            'negative' => ['processing', '-1', null],
            'non finite' => ['processing', 'Infinity', null],
            'decimal' => ['processing', '12.5', null],
            'array' => ['processing', [], null],
            'missing value' => ['processing', null, null],
        ];
    }

    /** @dataProvider failingThankyouTelemetryProvider */
    public function testFailingThankyouTelemetryPreservesTheConfirmedResultWithoutFinancialEffects(
        string $status,
        string $state,
        \Throwable $failure
    ): void {
        $order = $this->requestOrder($status);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');
        $this->lock->shouldNotReceive('acquire');
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);
        $reports = [];
        $endpoint = new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership()),
            $this->metadata,
            function (string $reason): void {
                $this->reportedReasons[] = $reason;
            },
            static function (string $name, string $value, string $duration, array $details) use ($failure, &$reports): void {
                $reports[] = [$name, $value, $duration, $details];
                throw $failure;
            },
            $this->lock,
            $this->terminalLock
        );

        $result = $endpoint->resolve($this->request() + ['thankyou_result_time_ms' => '12345']);

        $this->assertSame(['rejection' => null, 'state' => $state], $result);
        $this->assertSame([[SmartPixMetricSchema::THANKYOU_RESULT_TIME, $state, '12.345s', []]], $reports);
        $this->assertSame([], $this->reportedReasons);
        $this->assertSame($state, $this->thankyouResultReported);
    }

    public static function failingThankyouTelemetryProvider(): array
    {
        return [
            'approval with exception' => ['processing', 'approved', new \RuntimeException('telemetry down')],
            'rejection with exception' => ['failed', 'rejected', new \RuntimeException('telemetry down')],
            'approval with error' => ['completed', 'approved', new \Error('telemetry down')],
            'rejection with error' => ['failed', 'rejected', new \Error('telemetry down')],
        ];
    }

    public function testThankyouReceiptCannotBypassTheNonceOrOrderKey(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => false]);
        $this->client->shouldNotReceive('query');
        $this->assertSame('invalid_nonce', $this->endpoint()->resolve(
            $this->request() + ['thankyou_result_time_ms' => '2000']
        )['rejection']);
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testThankyouReceiptWithAnotherOrderKeyIsRejectedBeforeReporting(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('processing')]);
        $this->client->shouldNotReceive('query');
        $request = array_replace($this->request(), ['order_key' => 'wc_order_OTHER', 'thankyou_result_time_ms' => '2000']);
        $this->assertSame('invalid_order_key', $this->endpoint()->resolve($request)['rejection']);
        $this->assertSame([], $this->reportedMetrics);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testAnAuthorizedInFlightOrderWithoutAProviderIdWaitsWithoutCallingOrders(): void
    {
        $order = $this->requestOrder('pending');
        $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')->andReturn(str_repeat('a', 64));
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
    }

    /** @dataProvider unauthorizedRequestProvider */
    public function testGivenAnUnauthorizedRequestWhenResolvingThenItIsRejectedWithoutQueryingOrders(string $case, string $reason): void
    {
        $request = $this->request();
        $order = $this->requestOrder('pending');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => $case !== 'invalid_nonce']);
        WP_Mock::userFunction('wc_get_order', ['return' => $case === 'order_not_found' ? false : $order]);
        switch ($case) {
            case 'missing_nonce':
                unset($request['nonce']);
                break;
            case 'invalid_order_id':
                $request['order_id'] = '32a';
                break;
            case 'zero_order_id':
                $request['order_id'] = '0';
                break;
            case 'missing_order_key':
                unset($request['order_key']);
                break;
            case 'oversized_order_key':
                $request['order_key'] = str_repeat('k', 129);
                break;
            case 'invalid_order_key':
                $request['order_key'] = 'wc_order_OTHER';
                break;
            case 'gateway_mismatch':
                $order->shouldReceive('get_payment_method')->andReturn('woo-mercado-pago-custom');
                break;
            case 'contract_mismatch':
                $this->metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('payments_v1');
                break;
            case 'attempt_ended':
                $this->metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('');
                break;
            case 'order_not_linked':
                $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')->andReturn('');
                $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
                break;
        }
        $this->client->shouldNotReceive('query');

        $this->assertSame(['rejection' => $reason, 'state' => null], $this->endpoint()->resolve($request));
    }

    public static function unauthorizedRequestProvider(): array
    {
        return [
            'missing nonce'       => ['missing_nonce', 'invalid_nonce'],
            'invalid nonce'       => ['invalid_nonce', 'invalid_nonce'],
            'non numeric id'      => ['invalid_order_id', 'invalid_order_id'],
            'zero id'             => ['zero_order_id', 'invalid_order_id'],
            'order not found'     => ['order_not_found', 'order_not_found'],
            'missing order key'   => ['missing_order_key', 'missing_order_key'],
            'oversized order key' => ['oversized_order_key', 'missing_order_key'],
            'wrong order key'     => ['invalid_order_key', 'invalid_order_key'],
            'other gateway'       => ['gateway_mismatch', 'gateway_mismatch'],
            'other contract'      => ['contract_mismatch', 'contract_mismatch'],
            'attempt ended'       => ['attempt_ended', 'contract_mismatch'],
            'not linked'          => ['order_not_linked', 'order_not_linked'],
        ];
    }

    /** @dataProvider indistinguishableRejectionProvider */
    public function testGivenAnyRejectionWhenHandlingThenTheBuyerReceivesTheSameForbiddenResponse(string $orderKey, $foundOrder): void
    {
        $_POST = ['nonce' => self::NONCE, 'order_id' => (string) self::ORDER_ID, 'order_key' => $orderKey];
        $this->mockSanitization();
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $foundOrder === true ? $this->requestOrder('pending') : false]);
        WP_Mock::userFunction('wp_send_json_error', ['times' => 1, 'args' => [['message' => 'Forbidden'], 403]]);
        WP_Mock::userFunction('wp_send_json_success', ['times' => 0]);
        $this->client->shouldNotReceive('query');

        $this->endpoint()->handle();

        $this->assertCount(1, $this->reportedReasons);
    }

    public static function indistinguishableRejectionProvider(): array
    {
        return [
            'order not found'  => [self::ORDER_KEY, false],
            'wrong order key'  => ['wc_order_OTHER', true],
        ];
    }

    /** Defensive path: every public caller reports only after authorizing an order. */
    public function testGivenNoAuthorizedOrderWhenAServerErrorIsReportedThenItCarriesNoOrderDetails(): void
    {
        $this->metadata->shouldNotReceive('getPaymentContract', 'getPpOrderId', 'getIdempotencyKey');
        $endpoint = $this->endpoint();
        $serverError = new \ReflectionMethod($endpoint, 'serverError');
        $serverError->setAccessible(true);

        $serverError->invoke($endpoint, 'exception');

        $this->assertSame([['smart_pix_server_error', 'status_poll', 'exception']], $this->reportedMetrics);
        // Only the poll boundary diagnostic is attached; no order identifiers or payment details.
        $this->assertSame([['diagnostic_message' => 'thankyou.status_poll: exception']], $this->reportedDetails);
    }

    public function testGivenFailingTelemetryWhenRejectingThenTheForbiddenResponseIsStillSent(): void
    {
        $_POST = ['nonce' => self::NONCE];
        $this->mockSanitization();
        WP_Mock::userFunction('wp_verify_nonce', ['return' => false]);
        WP_Mock::userFunction('wp_send_json_error', ['times' => 1, 'args' => [['message' => 'Forbidden'], 403]]);

        $endpoint = new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership()),
            $this->metadata,
            static function (string $reason): void {
                throw new \RuntimeException('telemetry down');
            },
            static function (): void {
            },
            $this->lock,
            $this->terminalLock
        );
        $endpoint->handle();

        $this->addToAssertionCount(1);
    }

    public function testGivenAnAuthorizedRequestWhenHandlingThenOnlyThePublicStateIsReturned(): void
    {
        $_POST = $this->request();
        $this->mockSanitization();
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('processing')]);
        WP_Mock::userFunction('wp_send_json_success', [
            'times' => 1,
            'args'  => [['state' => 'approved', 'message' => 'Smart Pix order status']],
        ]);
        WP_Mock::userFunction('wp_send_json_error', ['times' => 0]);

        $this->endpoint()->handle();

        $this->assertSame([], $this->reportedReasons);
    }

    /** @dataProvider publicRejectionProvider */
    public function testOnlyStableRejectionCodesReachTheBuyer($detail, string $expected): void
    {
        $_POST = $this->request();
        $this->mockSanitization();
        $order = $this->requestOrder('failed');
        $this->metadata->shouldReceive('getPpPaymentStatusDetail')->with($order)->andReturn($detail);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        WP_Mock::userFunction('wp_send_json_success', ['times' => 1, 'args' => [[
            'state' => 'rejected', 'message' => 'Smart Pix order status', 'smart_pix_error' => $expected,
        ]]]);
        $this->client->shouldNotReceive('query');
        $this->endpoint()->handle();
        $this->assertSame($expected, SmartPixStatusEndpoint::publicErrorFor($order, $this->metadata));
    }

    public static function publicRejectionProvider(): array
    {
        return [
            'balance' => ['insufficient_amount', SmartPixBuyerError::INSUFFICIENT_BALANCE],
            'limit' => ['rejected_cap_exceeded', SmartPixBuyerError::LIMIT_EXCEEDED],
            'generic' => ['rejected_high_risk', SmartPixBuyerError::REJECTED],
            'missing' => [null, SmartPixBuyerError::REJECTED],
            'unknown' => ['private_provider_message', SmartPixBuyerError::REJECTED],
            'malformed' => [['insufficient_amount'], SmartPixBuyerError::REJECTED],
        ];
    }

    /** @dataProvider localTerminalStatusProvider */
    public function testGivenALocalTerminalOrderWhenResolvingThenItMapsWithoutQueryingOrders(string $status, string $state): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder($status)]);
        WP_Mock::userFunction('get_transient', ['times' => 0]);
        $this->lock->shouldNotReceive('acquire');
        $this->client->shouldNotReceive('query');

        $expected = ['rejection' => null, 'state' => $state];
        if ($state === 'rejected') {
            $expected['smart_pix_error'] = SmartPixBuyerError::REJECTED;
        }
        $this->assertSame($expected, $this->endpoint()->resolve($this->request()));
    }

    public function testGivenAStorePaidStatusWhenResolvingThenTheBuyerSeesApprovedWithoutQueryingOrders(): void
    {
        $order = $this->requestOrder('paid-by-store');
        $order->shouldReceive('is_paid')->andReturn(true);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $order]);
        WP_Mock::userFunction('get_transient', ['times' => 0]);
        $this->lock->shouldNotReceive('acquire');
        $this->client->shouldNotReceive('query');

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertSame(SmartPixStatusEndpoint::STATE_APPROVED, SmartPixStatusEndpoint::publicStateFor($order));
    }

    /** @dataProvider observedTerminalStatusProvider */
    public function testGivenAnOrderAnotherWriterMovedToTerminalWhenFirstPolledThenItsOutcomeIsReportedOnceWithoutQueryingOrders(string $status, string $state): void
    {
        $order = $this->requestOrder($status);
        $order->shouldNotReceive('payment_complete', 'update_status');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        $this->client->shouldNotReceive('query');

        $this->endpoint()->resolve($this->request());
        $this->endpoint()->resolve($this->request());

        $this->assertCount(1, $this->reportedMetrics);
        $this->assertTerminalMetric($state, $this->reportedMetrics[0]);
        $this->assertSame($state, $this->terminalReported);
        $this->assertNoSensitiveMetricData();
    }

    public static function observedTerminalStatusProvider(): array
    {
        return [
            'approved by a notification' => ['processing', 'approved'],
            'rejected'                   => ['failed', 'rejected'],
            'cancelled by the store'     => ['cancelled', 'cancelled'],
        ];
    }

    public function testGivenATerminalOutcomeTheRenderAlreadyReportedWhenPolledThenNothingIsReportedAgain(): void
    {
        $this->terminalReported = 'approved';
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('completed')]);
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenAConcurrentObserverHoldsTheTerminalLeaseWhenPolledThenTheStateIsReturnedWithoutReporting(): void
    {
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturn(null);
        $this->terminalLock->shouldNotReceive('release');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('processing')]);
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenATerminalOutcomeWhenPolledThenItIsReportedUnderTheTerminalLeaseAndTheLeaseIsReleased(): void
    {
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturn(self::TERMINAL_LEASE);
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('cancelled')]);

        $this->endpoint()->resolve($this->request());

        $this->assertCount(1, $this->reportedMetrics);
        $this->assertTerminalMetric('cancelled', $this->reportedMetrics[0]);
    }

    public function testGivenAPendingOrderWhenPolledThenNoTerminalOutcomeIsFlagged(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('custom-review')]);
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        $this->endpoint()->resolve($this->request());

        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenTheTerminalFlagCannotBeWrittenWhenPolledThenNothingIsReportedAndTheStateIsStillReturned(): void
    {
        $order = $this->requestOrder('failed');
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'rejected')->once()
            ->andThrow(new \RuntimeException('database down'));
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);

        $this->assertSame(['rejection' => null, 'state' => 'rejected', 'smart_pix_error' => SmartPixBuyerError::REJECTED], $this->endpoint()->resolve($this->request()));
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenFailingTelemetryWhenReportingTheTerminalOutcomeThenTheStateIsStillReturned(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('processing')]);
        $endpoint = new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership()),
            $this->metadata,
            static function (): void {
            },
            static function (): void {
                throw new \RuntimeException('telemetry down');
            },
            $this->lock,
            $this->terminalLock
        );

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $endpoint->resolve($this->request()));
    }

    public static function localTerminalStatusProvider(): array
    {
        return [
            'processing'     => ['processing', 'approved'],
            'completed'      => ['completed', 'approved'],
            'failed'         => ['failed', 'rejected'],
            'cancelled'      => ['cancelled', 'cancelled'],
            'refunded'       => ['refunded', 'cancelled'],
            'unknown status' => ['custom-review', 'pending'],
        ];
    }

    public function testGivenAQueryWithinTheRateLimitWindowWhenResolvingThenOrdersIsNotQueried(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', ['times' => 1, 'args' => ['mp_smart_pix_status_' . self::ORDER_ID], 'return' => 1]);
        WP_Mock::userFunction('set_transient', ['times' => 0]);
        $this->client->shouldNotReceive('query');

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
    }

    public function testGivenASecondPollAfterTheFirstReleasedTheLeaseWithinTheWindowWhenResolvingThenOrdersIsQueriedOnce(): void
    {
        $transients = [];
        $leaseHeld = false;
        $events = [];
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', ['return' => static function (string $name) use (&$transients, &$leaseHeld, &$events) {
            $events[] = $leaseHeld ? 'window_checked_under_lease' : 'window_checked_without_lease';
            return $transients[$name] ?? false;
        }]);
        WP_Mock::userFunction('set_transient', ['return' => static function (string $name, $value) use (&$transients): bool {
            $transients[$name] = $value;
            return true;
        }]);
        $this->lock->shouldReceive('acquire')->with(self::ORDER_ID)->twice()->andReturnUsing(static function () use (&$leaseHeld) {
            $leaseHeld = true;
            return self::LEASE;
        });
        $this->lock->shouldReceive('release')->with(self::LEASE)->twice()->andReturnUsing(static function () use (&$leaseHeld): bool {
            $leaseHeld = false;
            return true;
        });
        $this->client->shouldReceive('query')->once()->andReturn([
            'outcome' => 'success', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [], 'error' => null,
        ]);

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame(['window_checked_under_lease', 'window_checked_under_lease'], $events);
    }

    public function testGivenAnotherRequestHoldsTheOrderLeaseWhenResolvingThenOrdersIsNotQueriedNorApplied(): void
    {
        $order = $this->requestOrder('pending');
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $order]);
        WP_Mock::userFunction('get_transient', ['times' => 0]);
        WP_Mock::userFunction('set_transient', ['times' => 0]);
        $this->lock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturnNull();
        $this->lock->shouldNotReceive('release');
        $this->client->shouldNotReceive('query');

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([], $this->reportedMetrics);
    }

    /** @dataProvider nonTerminalQueryProvider */
    public function testGivenANonTerminalQueryWhenResolvingThenTheOrderStaysPending(array $response, array $expectedMetrics): void
    {
        $order = $this->requestOrder('on-hold');
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $order]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($response);

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame($expectedMetrics, $this->reportedMetrics);
        foreach ($this->reportedDetails as $details) {
            $this->assertArrayNotHasKey('source', $details, 'Only a terminal observation names its source.');
        }
        $this->assertNoSensitiveMetricData();
    }

    public static function nonTerminalQueryProvider(): array
    {
        $error = static fn(string $category, bool $retryable, string $state): array => [
            'outcome' => 'failure', 'state' => $state, 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => $category, 'code' => 'SMART_PIX_' . strtoupper($category), 'retryable' => $retryable],
        ];

        return [
            'still processing' => [[
                'outcome' => 'success', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
                'eligibility' => null, 'authorization' => null, 'payments' => [], 'error' => null,
            ], []],
            'rate limited'     => [$error('rate_limit', true, 'processing'), [['smart_pix_status_query_failed', 'get_rate_limit', 'orders_get']]],
            'unavailable'      => [$error('unavailable', true, 'processing'), [['smart_pix_status_query_failed', 'get_unavailable', 'orders_get']]],
            'timeout'          => [$error('timeout', true, 'processing'), [['smart_pix_status_query_failed', 'get_timeout', 'orders_get']]],
            'unknown status'   => [$error('unknown', false, 'error'), [['smart_pix_status_query_failed', 'get_unknown', 'orders_get']]],
        ];
    }

    /**
     * The per-order Orders window follows the page backoff (5 s, 15 s, 30 s) minus one second.
     * @dataProvider queryWindowByAttemptAgeProvider
     */
    public function testThePerOrderQueryWindowGrowsWithTheAttemptAge(?int $age, int $window): void
    {
        // Computed here: data providers run long before the test in a full suite.
        $this->attemptStart = $age === null ? '' : (string) (time() - $age);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder('pending')]);
        $this->openRateLimitWindow($window);
        $this->client->shouldReceive('query')->once()->andReturn([
            'outcome' => 'success', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [], 'error' => null,
        ]);

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
    }

    public static function queryWindowByAttemptAgeProvider(): array
    {
        return [
            'first minute' => [30, 4],
            'until five minutes' => [120, 14],
            'after five minutes' => [600, 29],
            'within the grace period' => [940, 29],
            'unknown start' => [null, 4],
        ];
    }

    /** Past the 15-minute window plus grace, a poll returns the persisted state without any Orders GET. */
    public function testGivenAnAttemptOlderThanThePollingWindowWhenPolledThenOrdersIsNotQueried(): void
    {
        $this->attemptStart = (string) (time() - SmartPixStatusEndpoint::POLLING_WINDOW_SECONDS - SmartPixStatusEndpoint::POLLING_GRACE_SECONDS - 1);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', ['times' => 0]);
        WP_Mock::userFunction('set_transient', ['times' => 0]);
        $this->lock->shouldNotReceive('acquire');
        $this->client->shouldNotReceive('query');

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenAnApprovedTerminalQueryWhenResolvingThenTheOrderIsCompletedAndReportedApproved(): void
    {
        $request = $this->requestOrder('pending');
        $pending = $this->applicatorSnapshot('pending', false);
        $persisted = $this->applicatorSnapshot('processing', true);
        $persisted->shouldReceive('get_date_created')->andReturn($request->get_date_created());
        $pending->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn(true);
        $persisted->shouldReceive('get_status')->withNoArgs()->andReturn('processing');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', [
            'times'           => 4,
            'args'            => [self::ORDER_ID],
            'return_in_order' => [$request, $pending, $persisted, $persisted],
        ]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($this->terminalResponse('approved'));
        $this->lock->shouldReceive('release')->with(self::LEASE)->once()->andReturn(true);

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertCount(1, $this->reportedMetrics);
        $this->assertTerminalMetric('approved', $this->reportedMetrics[0]);
        $this->assertSame('approved', $this->terminalReported);
        $this->assertNoSensitiveMetricData();
    }

    public function testAnApprovalAppliedByThePollIsAttributedToThePollAndCountedOnce(): void
    {
        $observed = [];
        $telemetry = new SmartPixTelemetry(null, null, null, static function (int $orderId, string $source) use (&$observed): void {
            $observed[] = [$orderId, $source];
        });
        $this->approveThroughThePoll($this->requestOrder('pending'));

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint($telemetry)->resolve($this->request()));

        // The applicator hands the shared terminal observer the polling origin, never `internal`.
        $this->assertSame([[self::ORDER_ID, 'pending_query']], $observed);
        $statusMetrics = array_keys(array_filter($this->reportedMetrics, static function (array $metric): bool {
            return $metric[0] === SmartPixStatusEndpoint::PAYMENT_STATUS_METRIC;
        }));
        $this->assertCount(1, $statusMetrics);
        $this->assertSame('poll', $this->reportedDetails[$statusMetrics[0]]['source']);
    }

    /** @dataProvider queriedRejectionProvider */
    public function testARejectionAppliedDuringPollingUsesTheReloadedReason(string $detail, string $code): void
    {
        $request = $this->requestOrder('pending');
        $pending = $this->applicatorSnapshot('pending', false);
        $persisted = $this->applicatorSnapshot('failed', false);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        $persisted->shouldReceive('get_status')->withNoArgs()->andReturn('failed');
        $this->metadata->shouldReceive('getPpPaymentStatusDetail')->with($persisted)->andReturn($detail);
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', [
            'times' => 4, 'args' => [self::ORDER_ID],
            'return_in_order' => [$request, $pending, $persisted, $persisted],
        ]);
        $this->openRateLimitWindow();
        $response = $this->terminalResponse('rejected');
        $response['error'] = ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_' . strtoupper($detail), 'retryable' => false];
        $this->client->shouldReceive('query')->once()->andReturn($response);

        $this->assertSame(['rejection' => null, 'state' => 'rejected', 'smart_pix_error' => $code], $this->endpoint()->resolve($this->request()));
        $this->assertSame($code, SmartPixStatusEndpoint::publicErrorFor($persisted, $this->metadata));
    }

    public static function queriedRejectionProvider(): array
    {
        return [
            ['insufficient_amount', SmartPixBuyerError::INSUFFICIENT_BALANCE],
            ['rejected_cap_exceeded', SmartPixBuyerError::LIMIT_EXCEEDED],
            ['rejected_new_reason', SmartPixBuyerError::REJECTED],
        ];
    }

    public function testGivenAPollAppliedTheApprovalWhenTheNextPollSeesItThenTheOutcomeIsNotReportedAgain(): void
    {
        $request = $this->requestOrder('pending');
        $pending = $this->applicatorSnapshot('pending', false);
        $persisted = $this->applicatorSnapshot('processing', true);
        $persisted->shouldReceive('get_date_created')->andReturn($request->get_date_created());
        $pending->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn(true);
        $persisted->shouldReceive('get_status')->withNoArgs()->andReturn('processing');
        $completed = $this->requestOrder('processing');
        $completed->shouldNotReceive('payment_complete', 'update_status');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', [
            'times'           => 5,
            'args'            => [self::ORDER_ID],
            'return_in_order' => [$request, $pending, $persisted, $persisted, $completed],
        ]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($this->terminalResponse('approved'));

        $this->endpoint()->resolve($this->request());
        $this->endpoint()->resolve($this->request());

        $this->assertCount(1, $this->reportedMetrics);
        $this->assertTerminalMetric('approved', $this->reportedMetrics[0]);
    }

    public function testGivenAnOlderOrderWhenApprovedThenTheDurationStartsAtOrderCreation(): void
    {
        $request = $this->requestOrder('pending');
        $request->shouldReceive('get_date_created')->andReturn(new \DateTime('-5 days'));
        $this->attemptStart = (string) (time() - 30);
        $this->approveThroughThePoll($request);

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertCount(1, $this->reportedMetrics);
        $this->assertSame(['smart_pix_payment_status_observed', 'approved'], array_slice($this->reportedMetrics[0], 0, 2));
        $this->assertContains($this->reportedMetrics[0][2], ['432000s', '432001s']);
    }

    public function testGivenAnOrderWithoutACreationDateWhenApprovedThenTheDurationIsUnknown(): void
    {
        $request = $this->requestOrder('pending');
        $this->attemptStart = '';
        $request->shouldReceive('get_date_created')->andReturn(null);
        $this->approveThroughThePoll($request);

        $this->assertSame(['rejection' => null, 'state' => 'approved'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([['smart_pix_payment_status_observed', 'approved', 'unknown']], $this->reportedMetrics);
    }

    public function testGivenATerminalQueryTheApplicatorRefusesWhenResolvingThenTheReloadedStateIsReturned(): void
    {
        $request = $this->requestOrder('pending');
        $pending = $this->applicatorSnapshot('pending', false);
        $pending->shouldReceive('get_total')->with('edit')->andReturn('999.00');
        $pending->shouldNotReceive('payment_complete', 'update_status');
        $reloaded = $this->requestOrder('pending');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', [
            'times'           => 3,
            'args'            => [self::ORDER_ID],
            'return_in_order' => [$request, $pending, $reloaded],
        ]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($this->terminalResponse('approved'));

        $events = [];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(static function (...$arguments) use (&$events): void {
            $events[] = $arguments;
        });

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint(new SmartPixTelemetry($sink))->resolve($this->request()));
        // The applicator reports the refused outcome once; the endpoint must not report it again.
        $this->assertSame([], $this->reportedMetrics);
        $errors = array_values(array_filter($events, static fn(array $event): bool => $event[0] === SmartPixMetricSchema::ERROR));
        $this->assertCount(1, $errors);
        $this->assertSame(['apply', 'pending_query', 'conflict'], [$errors[0][2]['operation'], $errors[0][2]['source'], $errors[0][2]['category']]);
        $this->assertSame('amount_mismatch', $errors[0][3]);
    }

    public function testGivenTheOrdersQueryThrowsWhenResolvingThenThePageStaysPendingAndTheFailureIsReported(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder('pending')]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andThrow(new \RuntimeException('transport exploded ' . self::ORDER_REFERENCE));
        $this->lock->shouldReceive('release')->with(self::LEASE)->once()->andReturn(true);

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([['smart_pix_server_error', 'status_poll', 'exception']], $this->reportedMetrics);
        $this->assertNoSensitiveMetricData();
    }

    public function testGivenTheAttemptKeyVanishesAfterATerminalQueryWhenResolvingThenItIsReportedAsAServerError(): void
    {
        $order = $this->requestOrder('pending');
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn(self::ATTEMPT_KEY, '');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $order]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($this->terminalResponse('approved'));

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($this->request()));
        $this->assertSame([['smart_pix_server_error', 'status_poll', 'attempt_key_missing']], $this->reportedMetrics);
        $this->assertNoSensitiveMetricData();
    }

    public function testGivenTheErrorTelemetryFailsWhenTheQueryThrowsThenThePageStaysPending(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['times' => 1, 'return' => $this->requestOrder('pending')]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andThrow(new \RuntimeException('transport exploded'));
        $endpoint = new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership()),
            $this->metadata,
            static function (string $reason): void {
            },
            static function (string $name, string $value, string $message): void {
                throw new \RuntimeException('telemetry down');
            },
            $this->lock,
            $this->terminalLock
        );

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $endpoint->resolve($this->request()));
    }

    public function testGivenThePageReportsPollingExpiredWhenResolvingThenTheMetricIsSentOncePerOrderWithoutQueryingOrders(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', [
            'args'            => ['mp_smart_pix_expired_' . self::ORDER_ID],
            'return_in_order' => [false, false, 1],
        ]);
        WP_Mock::userFunction('set_transient', [
            'times' => 1,
            'args'  => ['mp_smart_pix_expired_' . self::ORDER_ID, 1, SmartPixStatusEndpoint::POLLING_EXPIRED_REPORT_TTL_SECONDS],
        ]);
        $this->client->shouldNotReceive('query');
        $request = $this->request() + ['polling_expired' => '1'];

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($request));
        $this->assertSame(['rejection' => null, 'state' => 'pending'], $this->endpoint()->resolve($request));
        $this->assertSame([['smart_pix_polling_expired', 'expired', 'max_duration']], $this->reportedMetrics);
        $this->assertArrayNotHasKey('source', $this->reportedDetails[0], 'An expired observer is not a terminal observation.');
    }

    public function testGivenAnotherRequestHoldsTheTelemetryLeaseWhenThePageReportsPollingExpiredThenNothingIsReported(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', ['return' => false]);
        WP_Mock::userFunction('set_transient', ['times' => 0]);
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturn(null);
        $this->terminalLock->shouldNotReceive('release');
        $this->client->shouldNotReceive('query');

        $state = $this->endpoint()->resolve($this->request() + ['polling_expired' => '1']);

        $this->assertSame(['rejection' => null, 'state' => 'pending'], $state);
        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenAConcurrentRequestReportedExpirationWhileWaitingForTheLeaseWhenReportingThenTheMetricIsNotRepeated(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('pending')]);
        WP_Mock::userFunction('get_transient', ['return_in_order' => [false, 1]]);
        WP_Mock::userFunction('set_transient', ['times' => 0]);
        $this->terminalLock->shouldReceive('acquire')->with(self::ORDER_ID)->once()->andReturn(self::TERMINAL_LEASE);
        $this->terminalLock->shouldReceive('release')->with(self::TERMINAL_LEASE)->once()->andReturn(true);

        $this->endpoint()->resolve($this->request() + ['polling_expired' => '1']);

        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenATerminalOrderWhenThePageReportsPollingExpiredThenOnlyItsFirstTerminalObservationIsReported(): void
    {
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', ['return' => $this->requestOrder('processing')]);
        WP_Mock::userFunction('get_transient', ['times' => 0]);
        $this->client->shouldNotReceive('query');

        $this->endpoint()->resolve($this->request() + ['polling_expired' => '1']);

        $this->assertCount(1, $this->reportedMetrics);
        $this->assertTerminalMetric('approved', $this->reportedMetrics[0]);
    }

    /** @param array{string, string, string} $metric */
    private function assertTerminalMetric(string $state, array $metric): void
    {
        $this->assertSame(['smart_pix_payment_status_observed', $state], array_slice($metric, 0, 2));
        $this->assertContains($metric[2], ['42s', '43s']);
        // The status poll is the observer that first saw this terminal outcome.
        $index = array_search($metric, $this->reportedMetrics, true);
        $this->assertSame('poll', $this->reportedDetails[$index]['source'] ?? null);
    }

    private function approveThroughThePoll($request): void
    {
        $pending = $this->applicatorSnapshot('pending', false);
        $persisted = $this->applicatorSnapshot('processing', true);
        $persisted->shouldReceive('get_date_created')->andReturn($request->get_date_created());
        $pending->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn(true);
        $persisted->shouldReceive('get_status')->withNoArgs()->andReturn('processing');
        WP_Mock::userFunction('wp_verify_nonce', ['return' => true]);
        WP_Mock::userFunction('wc_get_order', [
            'times'           => 4,
            'args'            => [self::ORDER_ID],
            'return_in_order' => [$request, $pending, $persisted, $persisted],
        ]);
        $this->openRateLimitWindow();
        $this->client->shouldReceive('query')->once()->andReturn($this->terminalResponse('approved'));
    }

    private function assertNoSensitiveMetricData(): void
    {
        $serialized = json_encode($this->reportedMetrics);
        foreach ([self::ORDER_KEY, self::ORDER_REFERENCE, self::NONCE, self::ATTEMPT_KEY] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, (string) $serialized);
        }
    }

    private function ownership(): SmartPixEffectOwnershipStore
    {
        $store = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $store->shouldReceive('acquire')->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)]);
        $store->shouldReceive('start')->andReturn(true);
        $store->shouldReceive('complete')->andReturn(true);
        $store->shouldReceive('markUncertain')->andReturn(true);
        $store->shouldReceive('releaseReserved')->andReturn(true);
        return $store;
    }

    private function endpoint(?SmartPixTelemetry $telemetry = null): SmartPixStatusEndpoint
    {
        return new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership(), $telemetry),
            $this->metadata,
            function (string $reason): void {
                $this->reportedReasons[] = $reason;
            },
            function (string $name, string $value, string $message, array $details = []): void {
                $this->reportedMetrics[] = [$name, $value, $message];
                $this->reportedDetails[] = $details;
            },
            $this->lock,
            $this->terminalLock
        );
    }

    /** @return array<string, string> */
    private function request(): array
    {
        return ['nonce' => self::NONCE, 'order_id' => (string) self::ORDER_ID, 'order_key' => self::ORDER_KEY];
    }

    private function mockSanitization(): void
    {
        WP_Mock::userFunction('sanitize_post', ['return' => static fn($data) => $data]);
        WP_Mock::userFunction('map_deep', ['return' => static fn($data, $callback) => array_map($callback, $data)]);
        WP_Mock::userFunction('sanitize_text_field', ['return' => static fn($value) => $value]);
    }

    /** The attempt in these fixtures started 42 seconds ago: the first, 4-second window applies. */
    private function openRateLimitWindow(int $window = SmartPixStatusEndpoint::RATE_LIMIT_SECONDS): void
    {
        WP_Mock::userFunction('get_transient', ['times' => 1, 'return' => false]);
        WP_Mock::userFunction('set_transient', [
            'times' => 1,
            'args'  => ['mp_smart_pix_status_' . self::ORDER_ID, 1, $window],
        ]);
    }

    /** The order the buyer's request resolves to, as the endpoint and the query read it. */
    private function requestOrder(string $status)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
        $order->shouldReceive('get_order_key')->andReturn(self::ORDER_KEY);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID)->byDefault();
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_status')->withNoArgs()->andReturn($status);
        // WooCommerce default paid statuses; tests for store-configured ones override this.
        $order->shouldReceive('is_paid')->andReturn(in_array($status, ['processing', 'completed'], true))->byDefault();
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'))->byDefault();
        $order->shouldReceive('read_meta_data')->with(true);
        $this->metadata->shouldReceive('getPaymentContract')->with($order)
            ->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1)->byDefault();
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn(self::ATTEMPT_KEY)->byDefault();
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE)->byDefault();
        $this->persistedAttemptMetadata($order);

        return $order;
    }

    /** One order row behind every mock: the attempt start and the terminal flag are shared like real metadata. */
    private function persistedAttemptMetadata($order): void
    {
        $this->metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturnUsing(fn() => $this->attemptStart);
        $this->metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturnUsing(fn() => $this->terminalReported);
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($order, Mockery::type('string'))
            ->andReturnUsing(function ($order, string $state): void {
                $this->terminalReported = $state;
            })->byDefault();
        $this->metadata->shouldReceive('getPpOrderThankyouResultReported')->with($order)
            ->andReturnUsing(fn() => $this->thankyouResultReported)->byDefault();
        $this->metadata->shouldReceive('setPpOrderThankyouResultReported')->with($order, Mockery::type('string'))
            ->andReturnUsing(function ($order, string $state): void {
                $this->thankyouResultReported = $state;
            })->byDefault();
    }

    /** The authoritative reload the applicator performs before and after native effects. */
    private function applicatorSnapshot(string $status, bool $paid)
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
        $order->shouldReceive('get_order_key')->andReturn(self::ORDER_KEY);
        $order->shouldReceive('get_data_store')->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50')->byDefault();
        $order->shouldReceive('get_status')->with('edit')->andReturn($status);
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'))->byDefault();
        $order->shouldReceive('is_paid')->andReturn($paid);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($paid ? new \stdClass() : null);
        $order->shouldReceive('get_refunds')->andReturn([]);
        $order->shouldReceive('get_transaction_id')->with('edit')->andReturn('');
        $this->metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn(self::ATTEMPT_KEY);
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE);
        $this->persistedAttemptMetadata($order);

        return $order;
    }

    /** @return array<string, mixed> */
    private function terminalResponse(string $state): array
    {
        return [
            'outcome'         => $state === 'rejected' ? 'failure' : 'success',
            'state'           => $state === 'approved' ? 'terminal_success' : 'terminal_failure',
            'order_reference' => self::ORDER_REFERENCE,
            'eligibility'     => null,
            'authorization'   => null,
            'payments'        => [[
                'reference' => 'PAYMENT_SYNTHETIC_001',
                'state'     => $state,
                'amount'    => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error'           => $state === 'rejected'
                ? ['category' => 'payment_rejected', 'code' => 'SMART_PIX_PAYMENT_REJECTED', 'retryable' => false]
                : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTerminalObservation;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixTerminalObservationTest extends TestCase
{
    private const ORDER_ID = 321;

    /** @var OrderMetadata|\Mockery\MockInterface */
    private $metadata;

    private SmartPixStatusLockDatabase $database;

    /** @var array<array{string, string}> */
    private array $reportedMetrics = [];

    /** What a fresh database read returns; the cached copy is what the order held when loaded. */
    private string $persistedFlag = '';

    private string $cachedFlag = '';

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldReceive('getPpOrderPendingSince')->andReturn((string) (time() - 42));
        $this->database = new SmartPixStatusLockDatabase();
        $this->reportedMetrics = [];
        $this->persistedFlag = '';
        $this->cachedFlag = '';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testGivenAnUnreportedTerminalOrderWhenRecordingThenItIsFlaggedReportedOnceAndTheLeaseIsReleased(): void
    {
        $order = $this->terminalOrder();
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'rejected')->once()
            ->andReturnUsing(function ($order, string $state): void {
                $this->persistedFlag = $state;
                $this->cachedFlag = $state;
            });

        $this->observation()->record($order);
        $this->observation()->record($order);

        $this->assertCount(1, $this->reportedMetrics);
        $this->assertSame('rejected', $this->reportedMetrics[0][0]);
        $this->assertContains($this->reportedMetrics[0][1], ['42s', '43s']);
        $this->assertSame('rejected', $this->persistedFlag);
        $this->assertSame([], $this->database->rows);
    }

    public function testGivenAnotherRequestHoldsTheTerminalLeaseWhenRecordingThenNothingIsFlaggedOrReported(): void
    {
        $order = $this->terminalOrder();
        $held = (new SmartPixStatusLock($this->database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX))
            ->acquire(self::ORDER_ID);
        $order->shouldNotReceive('read_meta_data');
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        $this->observation()->record($order);

        $this->assertSame([], $this->reportedMetrics);
        $this->assertSame($held['value'], $this->database->rows[$held['name']]['option_value']);
    }

    public function testGivenAConcurrentRequestFlaggedTheOutcomeAfterThePreCheckWhenRecordingThenTheReloadPreventsASecondReport(): void
    {
        $order = $this->terminalOrder();
        $this->persistedFlag = 'rejected';
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        $this->observation()->record($order);

        $this->assertSame([], $this->reportedMetrics);
        $this->assertSame([], $this->database->rows);
    }

    public function testGivenTheFlagCannotBeWrittenWhenRecordingThenNothingIsReportedAndTheLeaseIsStillReleased(): void
    {
        $order = $this->terminalOrder();
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->once()->andThrow(new \RuntimeException('database down'));

        $this->observation()->record($order);

        $this->assertSame([], $this->reportedMetrics);
        $this->assertSame([], $this->database->rows);
    }

    public function testGivenFailingTelemetryWhenRecordingThenTheFailureIsSwallowedAndTheLeaseIsStillReleased(): void
    {
        $order = $this->terminalOrder();
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->once();
        $observation = new SmartPixTerminalObservation(
            $this->metadata,
            new SmartPixStatusLock($this->database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX),
            static function (): void {
                throw new \RuntimeException('telemetry down');
            }
        );

        $observation->record($order);

        $this->assertSame([], $this->database->rows);
    }

    public function testGivenAPendingOrderWhenRecordingThenTheLeaseIsNeverTaken(): void
    {
        $lock = Mockery::mock(SmartPixStatusLock::class);
        $lock->shouldNotReceive('acquire');
        $order = $this->orderWithStatus('on-hold');
        $this->metadata->shouldNotReceive('setPpOrderTerminalReported');

        (new SmartPixTerminalObservation($this->metadata, $lock, function (string $state, string $duration): void {
            $this->reportedMetrics[] = [$state, $duration];
        }))->record($order);

        $this->assertSame([], $this->reportedMetrics);
    }

    public function testGivenTheStoredPaymentDetailCannotBeReadWhenThePluginObserverReportsThenTheOutcomeIsSentWithoutIt(): void
    {
        $order = $this->terminalOrder();
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)->andReturn('');
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'rejected')->once();
        $this->metadata->shouldReceive('getPpPaymentStatusDetail')->with($order)->once()
            ->andThrow(new \RuntimeException('metadata unavailable'));
        $events = [];
        $datadog = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadog->shouldReceive('sendEvent')->once()->andReturnUsing(static function (...$arguments) use (&$events): void {
            $events[] = $arguments;
        });
        WP_Mock::userFunction('site_url', ['return' => 'https://store.example.test']);
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['return' => []]);
        $GLOBALS['wpdb'] = $this->database;
        $seller = Mockery::mock(\MercadoPago\Woocommerce\Configs\Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('123');
        $mercadopago = (object) [
            'orderMetadata' => $this->metadata,
            'sellerConfig' => $seller,
            'storeConfig' => Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class),
            'woocommerce' => (object) ['version' => '9.8.1'],
        ];

        try {
            SmartPixTerminalObservation::fromPlugin($mercadopago, 'pending_query', [], $datadog)->record($order);
        } finally {
            unset($GLOBALS['wpdb']);
        }

        $this->assertCount(1, $events);
        $this->assertSame('smart_pix_payment_status_observed', $events[0][0]);
        $details = $events[0][4];
        $this->assertSame('rejected', $details['payment_status']);
        $this->assertSame('poll', $details['source']);
        $this->assertArrayNotHasKey('payment_status_detail', $details);
        $this->assertArrayNotHasKey('reason', $details);
        $this->assertSame([], $this->database->rows);
    }

    public function testAttemptDurationAcceptsAnIntegerStoredAttemptStart(): void
    {
        $order = $this->orderWithStatus('on-hold');

        $this->assertContains($this->attemptObservation($order, time() - 42)->secondsSinceAttemptStart($order), ['42s', '43s']);
    }

    /** @dataProvider invalidAttemptStartProvider */
    public function testAttemptDurationIsUnknownWithoutAValidStoredAttemptStart($pendingSince): void
    {
        $order = $this->orderWithStatus('on-hold');

        $this->assertSame('unknown', $this->attemptObservation($order, $pendingSince)->secondsSinceAttemptStart($order));
    }

    public static function invalidAttemptStartProvider(): array
    {
        return ['non-numeric value' => ['yesterday'], 'zero timestamp' => ['0'], 'missing value' => [null]];
    }

    /** An observation whose order attempt started at $pendingSince. */
    private function attemptObservation($order, $pendingSince): SmartPixTerminalObservation
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn($pendingSince);

        return new SmartPixTerminalObservation(
            $metadata,
            new SmartPixStatusLock($this->database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX),
            static function (): void {
            }
        );
    }

    private function observation(): SmartPixTerminalObservation
    {
        return new SmartPixTerminalObservation(
            $this->metadata,
            new SmartPixStatusLock($this->database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX),
            function (string $state, string $duration): void {
                $this->reportedMetrics[] = [$state, $duration];
            }
        );
    }

    /** A failed order whose flag reads from its cached meta until `read_meta_data(true)` reloads it. */
    private function terminalOrder()
    {
        $order = $this->orderWithStatus('failed');
        $order->shouldReceive('read_meta_data')->with(true)->andReturnUsing(function (): void {
            $this->cachedFlag = $this->persistedFlag;
        })->byDefault();
        $this->metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturnUsing(fn() => $this->cachedFlag);

        return $order;
    }

    private function orderWithStatus(string $status)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
        $order->shouldReceive('get_status')->andReturn($status);
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'))->byDefault();

        return $order;
    }
}

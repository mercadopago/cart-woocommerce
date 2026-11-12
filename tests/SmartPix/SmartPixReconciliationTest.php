<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixReconciliation;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

/**
 * The query and applicator are final classes, so real instances are wired with mocked interfaces
 * and WooCommerce doubles, as in the notification receiver tests.
 */
class SmartPixReconciliationTest extends TestCase
{
    private const ATTEMPT_KEY = 'SYNTHETIC_ATTEMPT_KEY';

    private const ORDER_REFERENCE = 'PPORDSYNTHETIC001';

    /** @var list<array<int, mixed>> */
    private array $events = [];

    private $metadata;

    private $seller;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        $this->events = [];
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $this->metadata->shouldReceive('getIdempotencyKey')->andReturn(self::ATTEMPT_KEY);
        $this->metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $this->metadata->shouldReceive('getPpOrderReconcileState')->andReturn('');
        $this->metadata->shouldNotReceive('setPpOrderReconcileState');
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getSiteId')->andReturn('MLB');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testScheduledAttemptsWaitFiveMinutesDoublingUpToOneHour(): void
    {
        $scheduled = [];
        $reconciliation = $this->scheduling(
            static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
                $scheduled[] = [$timestamp, $hook, $args];
                return true;
            },
            static fn(): bool => false
        );

        foreach ([1, 2, 3, 4, 5, 6, SmartPixReconciliation::MAX_ATTEMPTS] as $attempt) {
            $reconciliation->schedule(321, $attempt);
        }

        $this->assertSame([
            [1300, SmartPixReconciliation::HOOK, [321, 1]],
            [1600, SmartPixReconciliation::HOOK, [321, 2]],
            [2200, SmartPixReconciliation::HOOK, [321, 3]],
            [3400, SmartPixReconciliation::HOOK, [321, 4]],
            [4600, SmartPixReconciliation::HOOK, [321, 5]],
            [4600, SmartPixReconciliation::HOOK, [321, 6]],
            [4600, SmartPixReconciliation::HOOK, [321, SmartPixReconciliation::MAX_ATTEMPTS]],
        ], $scheduled);
    }

    public function testAnAttemptAlreadyScheduledForTheOrderIsNotScheduledAgain(): void
    {
        $checked = [];
        $reconciliation = $this->scheduling(
            static function (): bool {
                throw new \LogicException('An already scheduled attempt must not be scheduled twice.');
            },
            static function (string $hook, array $args) use (&$checked): bool {
                $checked[] = [$hook, $args];
                return true;
            }
        );

        $reconciliation->schedule(321);

        $this->assertSame([[SmartPixReconciliation::HOOK, [321, 1]]], $checked);
    }

    /** @testWith [0, 1]
     *            [-1, 1]
     *            [321, 0]
     */
    public function testAnInvalidOrderOrAttemptIsNeverScheduled(int $orderId, int $attempt): void
    {
        $reconciliation = $this->scheduling(
            static function (): bool {
                throw new \LogicException('Invalid input must not be scheduled.');
            },
            static function (): bool {
                throw new \LogicException('Invalid input must not be looked up.');
            }
        );

        $reconciliation->schedule($orderId, $attempt);

        $this->addToAssertionCount(1);
    }

    public function testASchedulingFailureNeverEscapesToTheNotificationAnswer(): void
    {
        $reconciliation = $this->scheduling(
            static function (): bool {
                throw new \RuntimeException('SYNTHETIC_CRON_FAILURE');
            },
            static fn(): bool => false
        );

        $reconciliation->schedule(321);

        $this->addToAssertionCount(1);
    }

    public function testApprovedOrderFoundByTheCronReadCompletesThePaymentOnceWithoutRescheduling(): void
    {
        $open = $this->order('pending', false);
        $open->shouldReceive('get_payment_method')->withNoArgs()->andReturn(PixGateway::ID);
        $open->shouldReceive('get_status')->withNoArgs()->andReturn('pending');
        $open->shouldReceive('get_currency')->withNoArgs()->andReturn('BRL');
        $open->shouldReceive('read_meta_data')->with(true)->once();
        $pending = $this->snapshot('pending', false);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $persisted = $this->snapshot('processing', true);
        $persisted->shouldNotReceive('payment_complete');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 3, 'return_in_order' => [$open, $pending, $persisted]]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $client->shouldNotReceive('create', 'cancel', 'refund');
        $owner = str_repeat('a', 64);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $ownership->shouldReceive('start')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduler = static function (): bool {
            throw new \LogicException('A resolved order must not be rescheduled.');
        };

        (new SmartPixReconciliation(
            new SmartPixOrderQuery($client, $this->metadata, $this->seller, $telemetry),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $ownership, $telemetry),
            $this->metadata,
            $telemetry,
            $scheduler
        ))->run(321, 1);

        $this->assertContains([
            'count', SmartPixMetricSchema::RECONCILIATION, 'resolved',
            ['operation' => 'apply', 'source' => 'internal', 'surface' => 'server', 'payment_method' => 'smart_pix', 'site_id' => 'MLB'],
        ], $this->events);
        $this->assertNotContains('rescheduled', array_column($this->events, 2));
    }

    public function testPaidOrderWithAnUncertainEffectFoundByTheCronGoesToManualReviewWithoutReplayingIt(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')->andReturn(self::ATTEMPT_KEY);
        $metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getPpOrderReconcileState')->andReturn('');
        $paid = Mockery::mock(WC_Order::class);
        $paid->shouldReceive('get_id')->andReturn(321);
        $paid->shouldReceive('get_payment_method')->withNoArgs()->andReturn(PixGateway::ID);
        $paid->shouldReceive('get_status')->withNoArgs()->andReturn('processing');
        $paid->shouldReceive('is_paid')->andReturn(true);
        $paid->shouldReceive('get_currency')->withNoArgs()->andReturn('BRL');
        $paid->shouldReceive('read_meta_data')->with(true)->once();
        $paid->shouldNotReceive('payment_complete', 'update_status');
        $paid->shouldReceive('add_order_note')->once();
        $metadata->shouldReceive('setPpOrderReconcileState')->once()
            ->with($paid, OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $client->shouldNotReceive('create', 'cancel', 'refund');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'uncertain', 'owner' => null]);
        $ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduler = static function (): bool {
            throw new \LogicException('An escalated order must not be rescheduled.');
        };

        (new SmartPixReconciliation(
            new SmartPixOrderQuery($client, $metadata, $this->seller, $telemetry),
            new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $this->seller, $ownership, $telemetry),
            $metadata,
            $telemetry,
            $scheduler
        ))->run(321, 1);

        $this->assertContains([
            'count', SmartPixMetricSchema::RECONCILIATION, 'manual_review',
            ['operation' => 'apply', 'source' => 'internal', 'surface' => 'server', 'payment_method' => 'smart_pix', 'site_id' => 'MLB'],
        ], $this->events);
        $this->assertNotContains('resolved', array_column($this->events, 2));
    }

    public function testOpenOrderWithAnUncertainEffectGoesToManualReviewEvenWhenItIsAlreadySavedAsPaid(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')->andReturn(self::ATTEMPT_KEY);
        $metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getPpOrderReconcileState')->andReturn('');
        $open = $this->order('pending', false);
        $open->shouldReceive('get_payment_method')->withNoArgs()->andReturn(PixGateway::ID);
        $open->shouldReceive('get_status')->withNoArgs()->andReturn('pending');
        $open->shouldReceive('get_currency')->withNoArgs()->andReturn('BRL');
        $open->shouldReceive('read_meta_data')->with(true)->once();
        $paid = $this->closedPaidOrder(0);
        $paid->shouldReceive('add_order_note')->once();
        $metadata->shouldReceive('setPpOrderReconcileState')->once()
            ->with($paid, OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$open, $paid]]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $ownership = $this->ownershipAnswering('uncertain');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        $this->reconciliation($client, $metadata, $ownership, $telemetry)->run(321, 1);

        $this->assertContains('manual_review', array_column($this->events, 2));
        $this->assertNotContains('resolved', array_column($this->events, 2));
    }

    /** @dataProvider paidConflictProvider */
    public function testOnlyAProviderPaidStateConflictRequiresReview(string $outcome, string $reason, string $state, bool $expected): void
    {
        $this->assertSame($expected, SmartPixReconciliation::isPaidConflict(
            ['outcome' => $outcome, 'reason' => $reason, 'status' => 'cancelled'],
            ['state' => $state]
        ));
    }

    public static function paidConflictProvider(): array
    {
        return [
            'approved for a cancelled order' => ['conflict', 'terminal_state_conflict', 'terminal_success', true],
            'approved with another amount' => ['conflict', 'amount_mismatch', 'terminal_success', true],
            'approved in another currency' => ['conflict', 'currency_mismatch', 'terminal_success', true],
            'rejected for a paid order' => ['conflict', 'paid_state_conflict', 'terminal_failure', false],
            'ownership held by another attempt' => ['conflict', 'ownership_conflict', 'terminal_success', false],
            'order context mismatch' => ['conflict', 'order_context_mismatch', 'terminal_success', false],
            'already applied' => ['no_change', 'approval_already_applied', 'terminal_success', false],
        ];
    }

    public function testPaidOrderWithAConflictFoundByTheCronIsLeftUnchangedWithoutReview(): void
    {
        $paid = $this->closedPaidOrder(1);
        $paid->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $ownership = $this->ownershipAnswering('conflict');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        $this->reconciliation($client, $this->metadata, $ownership, $telemetry)->run(321, 1);

        $this->assertNotContains(SmartPixMetricSchema::RECONCILIATION, array_column($this->events, 1));
    }

    public function testPaidOrderWhoseCronReadFailsTransientlyIsRescheduledWithoutApplying(): void
    {
        $paid = $this->closedPaidOrder(1);
        $paid->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn([
            'outcome' => 'failure', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'timeout', 'code' => 'SMART_PIX_TIMEOUT', 'retryable' => true],
        ]);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduled = [];
        $scheduler = static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = [$timestamp, $hook, $args];
            return true;
        };

        $this->reconciliation($client, $this->metadata, $ownership, $telemetry, $scheduler)->run(321, 1);

        // The second attempt waits 600 s, so the uncertain ownership can still be surfaced later.
        $this->assertSame([[1800000600, SmartPixReconciliation::HOOK, [321, 2]]], $scheduled);
        $this->assertContains('rescheduled', array_column($this->events, 2));
        $this->assertNotContains('manual_review', array_column($this->events, 2));
    }

    public function testPaidOrderWhoseOrderIsStillProcessingIsRescheduledWithoutApplying(): void
    {
        $paid = $this->closedPaidOrder(1);
        $paid->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn(['outcome' => 'success', 'state' => 'processing']);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduled = [];
        $scheduler = static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = [$timestamp, $hook, $args];
            return true;
        };

        $this->reconciliation($client, $this->metadata, $ownership, $telemetry, $scheduler)->run(321, 1);

        $this->assertSame([[1800000600, SmartPixReconciliation::HOOK, [321, 2]]], $scheduled);
        $this->assertNotContains('manual_review', array_column($this->events, 2));
    }

    /** @dataProvider unresolvedOwnershipProvider */
    public function testPaidOrderWhoseOwnershipCannotAnswerYetIsRescheduledWithoutReview(string $mode): void
    {
        $paid = $this->closedPaidOrder(1);
        $paid->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        if ($mode === 'busy') {
            $ownership = $this->ownershipAnswering('busy');
        } else {
            $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
            $ownership->shouldReceive('acquire')->once()->andThrow(new \RuntimeException('SYNTHETIC storage failure'));
            $ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
            $logger = Mockery::mock();
            $logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_unavailable',
            ]);
            WP_Mock::userFunction('wc_get_logger', ['return' => $logger]);
        }
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduled = [];
        $scheduler = static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = [$timestamp, $hook, $args];
            return true;
        };

        $this->reconciliation($client, $this->metadata, $ownership, $telemetry, $scheduler)->run(321, 3);

        $this->assertSame([[1800002400, SmartPixReconciliation::HOOK, [321, 4]]], $scheduled);
        $this->assertNotContains('manual_review', array_column($this->events, 2));
    }

    public static function unresolvedOwnershipProvider(): array
    {
        return ['effect still executing' => ['busy'], 'ownership storage unavailable' => ['unavailable']];
    }

    public function testPaidOrderWhoseRetriesAreExhaustedGoesToManualReview(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getPpOrderReconcileState')->andReturn('');
        $paid = $this->closedPaidOrder(0);
        $paid->shouldReceive('add_order_note')->once();
        $metadata->shouldReceive('setPpOrderReconcileState')->once()
            ->with($paid, OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        $this->reconciliation($client, $metadata, $ownership, $telemetry)
            ->schedule(321, SmartPixReconciliation::MAX_ATTEMPTS + 1);

        $this->assertContains('manual_review', array_column($this->events, 2));
    }

    public function testWithoutInjectedCronFunctionsTheAttemptIsScheduledThroughWpCron(): void
    {
        WP_Mock::userFunction('wp_next_scheduled', [
            'args' => [SmartPixReconciliation::HOOK, [321, 1]], 'times' => 1, 'return' => false,
        ]);
        WP_Mock::userFunction('wp_schedule_single_event', [
            'args' => [1300, SmartPixReconciliation::HOOK, [321, 1]], 'times' => 1, 'return' => true,
        ]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'create', 'cancel', 'refund');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        (new SmartPixReconciliation(
            new SmartPixOrderQuery($client, $this->metadata, $this->seller, $telemetry),
            new SmartPixStateApplicator(
                $this->metadata,
                new SmartPixStateReducer(),
                $this->seller,
                Mockery::mock(SmartPixEffectOwnershipStore::class),
                $telemetry
            ),
            $this->metadata,
            $telemetry,
            null,
            null,
            static fn(): int => 1000
        ))->schedule(321);

        $this->assertSame([], $this->events);
    }

    public function testOpenOrderWhoseEffectIsStillExecutingIsRescheduledWithoutReview(): void
    {
        $open = $this->openOrder();
        $open->shouldReceive('read_meta_data')->with(true)->once();
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $open]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduled = [];
        $scheduler = static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = [$timestamp, $hook, $args];
            return true;
        };

        $this->reconciliation($client, $this->metadata, $this->ownershipAnswering('busy'), $telemetry, $scheduler)->run(321, 2);

        $this->assertSame([[1800001200, SmartPixReconciliation::HOOK, [321, 3]]], $scheduled);
        $this->assertContains('rescheduled', array_column($this->events, 2));
        $this->assertNotContains('manual_review', array_column($this->events, 2));
        $this->assertNotContains('resolved', array_column($this->events, 2));
    }

    public function testOpenOrderWithAConflictThatAnotherWriterAlreadyClosedIsResolvedWithoutReview(): void
    {
        $open = $this->openOrder();
        $open->shouldReceive('read_meta_data')->with(true)->once();
        $closed = $this->closedPaidOrder(0);
        $closed->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return_in_order' => [$open, $closed]]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($this->approved());
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        $this->reconciliation($client, $this->metadata, $this->ownershipAnswering('conflict'), $telemetry)->run(321, 1);

        $this->assertContains([
            'count', SmartPixMetricSchema::RECONCILIATION, 'resolved',
            ['operation' => 'apply', 'source' => 'internal', 'surface' => 'server', 'payment_method' => 'smart_pix', 'site_id' => 'MLB'],
        ], $this->events);
        $this->assertNotContains('manual_review', array_column($this->events, 2));
    }

    public function testAnUnexpectedFailureDuringTheRunIsRescheduledInsteadOfEscapingToWpCron(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getPaymentContract')->andThrow(new \RuntimeException('SYNTHETIC metadata failure'));
        $metadata->shouldNotReceive('setPpOrderReconcileState');
        $order = $this->openOrder();
        $order->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry($this->recordingSink());
        $scheduled = [];
        $scheduler = static function (int $timestamp, string $hook, array $args) use (&$scheduled): bool {
            $scheduled[] = [$timestamp, $hook, $args];
            return true;
        };

        $this->reconciliation($client, $metadata, $ownership, $telemetry, $scheduler)->run(321, 1);

        $this->assertSame([[1800000600, SmartPixReconciliation::HOOK, [321, 2]]], $scheduled);
        $this->assertContains('rescheduled', array_column($this->events, 2));
    }

    public function testOrderAlreadyInManualReviewIsNeitherQueriedNorEscalatedAgain(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getPpOrderReconcileState')->andReturn(OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        $metadata->shouldNotReceive('setPpOrderReconcileState');
        $paid = $this->closedPaidOrder(0);
        $paid->shouldNotReceive('add_order_note');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $paid]);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        $this->reconciliation($client, $metadata, $ownership, $telemetry)->run(321, 1);

        $this->assertSame([], $this->events);
    }

    /** A linked Smart Pix order already saved as paid, as the cron sees it before the query. */
    private function closedPaidOrder(int $metaReads)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->withNoArgs()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_status')->withNoArgs()->andReturn('processing');
        $order->shouldReceive('is_paid')->andReturn(true);
        $order->shouldReceive('get_currency')->withNoArgs()->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->with(true)->times($metaReads);
        $order->shouldNotReceive('payment_complete', 'update_status');
        return $order;
    }

    /** A linked, unpaid Smart Pix order still waiting for its terminal state. */
    private function openOrder()
    {
        $order = $this->order('pending', false);
        $order->shouldReceive('get_payment_method')->withNoArgs()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_status')->withNoArgs()->andReturn('pending');
        $order->shouldReceive('get_currency')->withNoArgs()->andReturn('BRL');
        $order->shouldNotReceive('payment_complete');
        return $order;
    }

    /** Ownership that denies the complete_payment effect before any native read. */
    private function ownershipAnswering(string $claim)
    {
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        return $ownership;
    }

    private function reconciliation(
        $client,
        $metadata,
        $ownership,
        SmartPixTelemetry $telemetry,
        ?callable $scheduler = null
    ): SmartPixReconciliation {
        $scheduler = $scheduler ?? static function (): bool {
            throw new \LogicException('This order must not be rescheduled.');
        };
        return new SmartPixReconciliation(
            new SmartPixOrderQuery($client, $metadata, $this->seller, $telemetry),
            new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $this->seller, $ownership, $telemetry),
            $metadata,
            $telemetry,
            $scheduler,
            static fn(): bool => false,
            static fn(): int => 1800000000
        );
    }

    /** @return array<string, mixed> */
    private function approved(): array
    {
        return [
            'outcome' => 'success', 'state' => 'terminal_success', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null,
            'payments' => [['reference' => 'PPPAYSYNTHETIC001', 'state' => 'approved', 'amount' => ['value' => '125.50', 'currency' => 'BRL']]],
            'error' => null,
        ];
    }

    private function order(string $status, bool $paid)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('is_paid')->andReturn($paid);
        $order->shouldNotReceive('update_status', 'add_order_note');
        return $order;
    }

    /** Fully refreshed native order read by the applicator. */
    private function snapshot(string $status, bool $paid)
    {
        $order = $this->order($status, $paid);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $order->shouldReceive('get_status')->with('edit')->andReturn($status);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($paid ? new \stdClass() : null);
        $order->shouldReceive('get_refunds')->andReturn([]);
        $order->shouldReceive('get_transaction_id')->with('edit')->andReturn('');
        return $order;
    }

    private function recordingSink(): SmartPixMetricSink
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(function (...$arguments): void {
            $this->events[] = array_merge(['count'], $arguments);
        });
        $sink->shouldReceive('distribution')->andReturnUsing(function (...$arguments): void {
            $this->events[] = array_merge(['distribution'], $arguments);
        });
        return $sink;
    }

    /** Reconciliation whose WP-Cron functions and clock are replaced; schedule() never reads Orders. */
    private function scheduling(callable $scheduler, callable $isScheduled): SmartPixReconciliation
    {
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'create', 'cancel', 'refund');
        $telemetry = new SmartPixTelemetry($this->recordingSink());

        return new SmartPixReconciliation(
            new SmartPixOrderQuery($client, $this->metadata, $this->seller, $telemetry),
            new SmartPixStateApplicator(
                $this->metadata,
                new SmartPixStateReducer(),
                $this->seller,
                Mockery::mock(SmartPixEffectOwnershipStore::class),
                $telemetry
            ),
            $this->metadata,
            $telemetry,
            $scheduler,
            $isScheduled,
            static fn(): int => 1000
        );
    }
}

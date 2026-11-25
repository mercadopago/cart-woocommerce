<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Exceptions\PixCompletionException;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use MercadoPago\Woocommerce\Order\FreshOrderReader;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PixOrderStatusLock;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

/**
 * Pix-specific behaviour of OrderStatus::processStatus(): lock, fresh read and completion checkpoint.
 * The lock/reader are doubles here; their real behaviour is covered by PixOrderStatusLockTest and by
 * the integration run against a real database.
 */
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class OrderStatusPixTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const LOCK = ['name' => 'mp_pix_status_x', 'connection_id' => 7];

    private $lock;
    private $reader;
    private OrderStatus $orderStatus;
    private array $orderStatusArgs = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Mocks/FakeWcOrder.php';
        WP_Mock::setUp();
        WP_Mock::onFilter('woocommerce_mercadopago_pix_status_lock_enabled')->with(true)->reply(true);

        $translations = Mockery::mock(StoreTranslations::class);
        $translations->orderStatus   = ['payment_approved' => 'Payment Approved', 'in_mediation' => 'In Mediation'];
        $translations->commonMessages = ['cho_default' => 'Default'];

        $logs       = Mockery::mock(Logs::class);
        $file       = Mockery::mock(File::class);
        $remote     = Mockery::mock(Remote::class);
        $file->shouldIgnoreMissing();
        $remote->shouldIgnoreMissing();
        $logs->file   = $file;
        $logs->remote = $remote;

        $this->lock   = Mockery::mock(PixOrderStatusLock::class);
        $this->reader = Mockery::mock(FreshOrderReader::class);
        $this->lock->shouldReceive('isHeld')->andReturn(false)->byDefault();

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(null); // legacy (non Smart Pix) contract

        $this->orderStatusArgs = [
            $translations,
            $metadata,
            Mockery::mock(Seller::class),
            Mockery::mock(Requester::class),
            $logs,
            $this->lock,
            $this->reader,
        ];
        $this->orderStatus = new OrderStatus(...$this->orderStatusArgs);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    private function order(array $state = []): WC_Order
    {
        return new WC_Order($state);
    }

    private function expectLockCycle(WC_Order $order): void
    {
        $this->lock->shouldReceive('acquire')->once()->with(28)->andReturn(self::LOCK);
        $this->lock->shouldReceive('assertOwned')->atLeast()->once()->with(self::LOCK);
        $this->lock->shouldReceive('release')->once()->with(self::LOCK)->andReturn(true);
        $this->reader->shouldReceive('refresh')->atLeast()->once();
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('processing');
    }

    public function testApprovedOnPendingOrderCompletesOnceAndStoresTheCheckpoint(): void
    {
        $order = $this->order();
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(1, $order->paymentCompleteCalls);
        $this->assertCount(2, $order->notes, 'one approval pair of MP notes');
        $this->assertSame('1', $order->state['flag']);
    }

    public function testSecondApprovedAfterCompletionDoesNotCompleteAgain(): void
    {
        $order = $this->order(['status' => 'processing', 'flag' => true, 'paid' => true]);
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(0, $order->paymentCompleteCalls);
        $this->assertSame([], $order->notes);
    }

    public function testStaleSnapshotBecomesPaidAfterFreshReadAndIsNotCompletedAgain(): void
    {
        // The caller loaded a pending copy before the HTTP call; another worker paid it meanwhile.
        $order = $this->order();
        $this->lock->shouldReceive('acquire')->once()->andReturn(self::LOCK);
        $this->lock->shouldReceive('assertOwned');
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->reader->shouldReceive('refresh')->atLeast()->once()->andReturnUsing(function (WC_Order $o) {
            $o->state['status'] = 'processing';
            $o->state['paid']   = true;
            $o->state['flag']   = true;
        });

        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');

        $this->assertSame(0, $order->paymentCompleteCalls);
        $this->assertSame([], $order->notes);
    }

    public function testPaidOrderWithoutFlagOnlyRepairsTheFlag(): void
    {
        $order = $this->order(['status' => 'completed', 'paid' => true]);
        $this->expectLockCycle($order);

        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');

        $this->assertSame(0, $order->paymentCompleteCalls);
        $this->assertSame([], $order->notes);
        $this->assertSame('1', $order->state['flag']);
    }

    public function testPaymentDateAloneOnAPendingOrderIsNotProofOfCompletion(): void
    {
        // Marked paid and then reverted by hand: pending with a leftover payment date. A real approval
        // must complete it (as it did before the lock), not be swallowed as "already applied".
        $order = $this->order(['status' => 'pending', 'flag' => true, 'paid' => true]);
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(1, $order->paymentCompleteCalls);
        $this->assertSame('processing', $order->state['status']);
    }

    public function testCheckpointFlagWithoutPaymentEvidenceCompletesTheOrder(): void
    {
        // Legacy orders: older versions stored the flag even when the completion failed, leaving the
        // order unpaid. The flag alone is not proof: complete it instead of answering success.
        $order = $this->order(['status' => 'pending', 'flag' => '1', 'paid' => false]);
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(1, $order->paymentCompleteCalls);
        $this->assertSame('processing', $order->state['status']);
        $this->assertSame('1', $order->state['flag']);
    }

    public function testCheckpointFlagWithoutPaymentThatStillFailsIsReportedForRetry(): void
    {
        $order = $this->order(['status' => 'pending', 'flag' => '1', 'block_update_status' => true]);
        $order->onPaymentComplete = function () {
            return false;
        };
        $this->expectLockCycle($order);

        $this->expectException(PixCompletionException::class);
        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
    }

    public function testReentrantCallFromAHookIsSkippedWithoutBreakingTheOuterCompletion(): void
    {
        $order = $this->order();
        $nestedResult = null;
        $order->onPaymentComplete = function (WC_Order $o) use (&$nestedResult) {
            // e.g. a third-party callback of payment_complete() calling back into the plugin.
            $nestedResult = $this->orderStatus->processStatus('approved', ['id' => 1], $o, 'gw');
            $o->state['status'] = 'processing';
            $o->state['paid']   = true;

            return true;
        };
        $this->lock->shouldReceive('isHeld')->with(28)->andReturn(false, true);
        $this->lock->shouldReceive('acquire')->once()->with(28)->andReturn(self::LOCK);
        $this->lock->shouldReceive('assertOwned')->atLeast()->once()->with(self::LOCK);
        $this->lock->shouldReceive('release')->once()->with(self::LOCK)->andReturn(true);
        $this->reader->shouldReceive('refresh')->atLeast()->once();
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('processing');

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertFalse($nestedResult, 'the nested call reports not applied and does not touch the order');
        $this->assertSame(1, $order->paymentCompleteCalls, 'payment_complete() ran once, not again from the nested call');
        $this->assertSame('1', $order->state['flag']);
    }

    public function testApprovalAfterMediationReleasesTheHoldWithoutCompletingAgain(): void
    {
        // Paid order held by in_mediation: it keeps its payment date and the checkpoint.
        $order = $this->order(['status' => 'on-hold', 'flag' => '1', 'paid' => true, 'meta' => ['_mp_pix_in_mediation' => '1']]);
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame('processing', $order->state['status'], 'the hold is released');
        $this->assertSame(0, $order->paymentCompleteCalls, 'no second completion / payment_complete hook');
    }

    public function testMediationReleaseHonorsThePaymentCompleteStatusFilter(): void
    {
        $order = $this->order(['status' => 'on-hold', 'flag' => '1', 'paid' => true, 'meta' => ['_mp_pix_in_mediation' => '1']]);
        $this->lock->shouldReceive('acquire')->once()->andReturn(self::LOCK);
        $this->lock->shouldReceive('assertOwned');
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->reader->shouldReceive('refresh')->atLeast()->once();
        // A store that remaps its paid status.
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('custom-paid');

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame('custom-paid', $order->state['status']);
        $this->assertSame(0, $order->paymentCompleteCalls);
    }

    public function testReleaseThatDoesNotPersistTheNewStatusIsRecoverableAndKeepsTheMarker(): void
    {
        $order = $this->order(['status' => 'on-hold', 'flag' => '1', 'paid' => true, 'meta' => ['_mp_pix_in_mediation' => '1'], 'block_update_status' => true]);
        $this->expectLockCycle($order);

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
            $this->fail('PixCompletionException expected');
        } catch (PixCompletionException $e) {
            $this->assertSame('on-hold', $order->state['status']);
            $this->assertSame('1', $order->state['meta']['_mp_pix_in_mediation'], 'a later approval can retry the release');
        }
    }

    private function orderStatusWithPersisted(?WC_Order $persisted): OrderStatus
    {
        $status = Mockery::mock(OrderStatus::class, $this->orderStatusArgs)->makePartial()->shouldAllowMockingProtectedMethods();
        $status->shouldReceive('loadPersistedOrder')->with(28)->andReturn($persisted);

        return $status;
    }

    public function testMarkerIsClearedWhenTheOrderLeavesOnHoldAndTheNewStatusIsPersisted(): void
    {
        $order = $this->order(['status' => 'processing', 'meta' => ['_mp_pix_in_mediation' => '1']]);
        $persisted = $this->order(['status' => 'processing']);

        $this->orderStatusWithPersisted($persisted)->clearPixMediationMarker(28, 'on-hold', 'processing', $order);

        $this->assertArrayNotHasKey('_mp_pix_in_mediation', $order->state['meta']);
    }

    public function testMarkerIsKeptWhenTheStatusWriteDidNotReachTheDatabase(): void
    {
        // WooCommerce emits the status-changed hook even when wp_update_post() returned 0.
        $order = $this->order(['status' => 'processing', 'meta' => ['_mp_pix_in_mediation' => '1']]);
        $stillOnHold = $this->order(['status' => 'on-hold']);

        $this->orderStatusWithPersisted($stillOnHold)->clearPixMediationMarker(28, 'on-hold', 'processing', $order);
        $this->assertSame('1', $order->state['meta']['_mp_pix_in_mediation']);

        // The persisted copy could not be loaded at all: keep it too.
        $this->orderStatusWithPersisted(null)->clearPixMediationMarker(28, 'on-hold', 'processing', $order);
        $this->assertSame('1', $order->state['meta']['_mp_pix_in_mediation']);
    }

    public function testMarkerIsClearedWhenAnotherCallbackAlreadyMovedTheOrderOnFromTheTargetStatus(): void
    {
        // The hook reports on-hold -> processing, but a callback already completed the order before the
        // hook finished: the persisted status is "completed", not $to, and the hold has indeed ended.
        $order     = $this->order(['status' => 'processing', 'meta' => ['_mp_pix_in_mediation' => '1']]);
        $persisted = $this->order(['status' => 'completed']);

        $this->orderStatusWithPersisted($persisted)->clearPixMediationMarker(28, 'on-hold', 'processing', $order);

        $this->assertArrayNotHasKey('_mp_pix_in_mediation', $order->state['meta']);
    }

    public function testPersistedOrderIsALoadedAndRefreshedCopy(): void
    {
        $copy = $this->order(['status' => 'processing']);
        WP_Mock::userFunction('wc_get_order')->once()->with(28)->andReturn($copy);
        WP_Mock::userFunction('wc_get_order')->once()->with(29)->andReturn(false);
        $this->reader->shouldReceive('refresh')->once()->with($copy);

        $method = new \ReflectionMethod(OrderStatus::class, 'loadPersistedOrder');
        $method->setAccessible(true);

        $this->assertSame($copy, $method->invoke($this->orderStatus, 28));
        $this->assertNull($method->invoke($this->orderStatus, 29), 'an order that cannot be loaded');
        $this->assertNull($method->invoke($this->orderStatus, 0), 'no order id');
    }

    public function testMarkerIsUntouchedByOtherTransitions(): void
    {
        $other = $this->order(['status' => 'on-hold', 'meta' => ['_mp_pix_in_mediation' => '1']]);

        $this->orderStatus->clearPixMediationMarker(28, 'pending', 'on-hold', $other);
        $this->orderStatus->clearPixMediationMarker(28, 'on-hold', 'on-hold', $other);
        $this->assertSame('1', $other->state['meta']['_mp_pix_in_mediation']);

        $without = $this->order(['status' => 'processing']);
        $this->orderStatus->clearPixMediationMarker(28, 'on-hold', 'processing', $without);
        $this->assertArrayNotHasKey('_mp_pix_in_mediation', $without->state['meta'] ?? []);
    }

    public function testReentrantCallIsSkippedEvenWhenTheDatabaseLockIsDisabled(): void
    {
        WP_Mock::onFilter('woocommerce_mercadopago_pix_status_lock_enabled')->with(true)->reply(false);
        $order = $this->order();
        $nestedResult = null;
        $order->onPaymentComplete = function (WC_Order $o) use (&$nestedResult) {
            // e.g. a woocommerce_pre_payment_complete callback calling back into the plugin.
            $nestedResult = $this->orderStatus->processStatus('approved', ['id' => 1], $o, 'gw');
            $o->state['status'] = 'processing';
            $o->state['paid']   = true;

            return true;
        };
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('processing');
        $this->lock->shouldReceive('acquire')->never();
        $this->reader->shouldReceive('refresh')->atLeast()->once();

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertFalse($nestedResult, 'the nested call is skipped by the in-memory guard');
        $this->assertSame(1, $order->paymentCompleteCalls, 'no recursion: payment_complete() ran once');
    }

    public function testDisabledLockFilterRunsTheFlowWithoutTheDatabaseLock(): void
    {
        WP_Mock::onFilter('woocommerce_mercadopago_pix_status_lock_enabled')->with(true)->reply(false);
        $order = $this->order();
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('processing');
        $this->lock->shouldReceive('acquire')->never();
        $this->lock->shouldReceive('assertOwned')->never();
        $this->lock->shouldReceive('release')->never();
        $this->reader->shouldReceive('refresh')->atLeast()->once();

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(1, $order->paymentCompleteCalls, 'the checkpoint still decides and completes once');
        $this->assertSame('1', $order->state['flag']);
    }

    public function testManualHoldOfAPaidOrderIsNotReleasedByAnApproval(): void
    {
        // Paid order the merchant put on hold by hand: no in_mediation marker, so it stays as left.
        $order = $this->order(['status' => 'on-hold', 'flag' => '1', 'paid' => true]);
        $this->expectLockCycle($order);

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame('on-hold', $order->state['status']);
        $this->assertSame(0, $order->paymentCompleteCalls);
    }

    public function testInMediationOfAPixOrderStoresTheMarkerThatLaterAllowsTheRelease(): void
    {
        $order = $this->order(['status' => 'processing', 'flag' => '1', 'paid' => true]);
        $this->expectLockCycle($order);

        $this->orderStatus->processStatus('in_mediation', ['id' => 1], $order, 'gw');

        $this->assertSame('1', $order->state['meta']['_mp_pix_in_mediation']);
        $this->assertSame('on-hold', $order->state['status']);
    }

    public function testOnHoldWithoutPaymentEvidenceStillCompletesNormally(): void
    {
        $order = $this->order(['status' => 'on-hold']);
        $this->expectLockCycle($order);

        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');

        $this->assertSame(1, $order->paymentCompleteCalls);
    }

    public function testPartiallyRefundedApprovalDoesNotComplete(): void
    {
        $order = $this->order();
        $this->expectLockCycle($order);

        $this->orderStatus->processStatus('approved', ['status_detail' => 'partially_refunded'], $order, 'gw');

        $this->assertSame(0, $order->paymentCompleteCalls);
    }

    public function testBusyLockPropagatesAndNeverEntersTheEffect(): void
    {
        $order = $this->order();
        $this->lock->shouldReceive('acquire')->once()->andThrow(new OrderStatusLockException('busy'));
        $this->lock->shouldReceive('release')->never();
        $this->reader->shouldReceive('refresh')->never();

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
            $this->fail('OrderStatusLockException expected');
        } catch (OrderStatusLockException $e) {
            $this->assertSame(0, $order->paymentCompleteCalls);
            $this->assertSame([], $order->notes);
        }
    }

    public function testLockIsReleasedWhenTheEffectFails(): void
    {
        $order = $this->order();
        $order->onPaymentComplete = function () {
            throw new \Exception('third-party callback exploded');
        };
        $this->expectLockCycle($order);

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
            $this->fail('Exception expected');
        } catch (\Exception $e) {
            $this->assertSame('third-party callback exploded', $e->getMessage());
        }
    }

    public function testCompletionNotPersistedIsReportedAndCheckpointIsNotStored(): void
    {
        $order = $this->order(['block_update_status' => true]);
        // payment_complete() swallowed an exception internally and returned false: still unpaid.
        $order->onPaymentComplete = function () {
            return false;
        };
        $this->expectLockCycle($order);

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
            $this->fail('PixCompletionException expected');
        } catch (PixCompletionException $e) {
            $this->assertSame('', $order->state['flag']);
        }
    }

    public function testSaveSucceededButCallbackFailedIsNotCompletedAgainOnRetry(): void
    {
        $order = $this->order();
        // The order was saved as paid, then a third-party callback exploded.
        $order->onPaymentComplete = function (WC_Order $o) {
            $o->state['status'] = 'processing';
            $o->state['paid']   = true;
            throw new \Exception('callback failed after save');
        };
        $this->expectLockCycle($order);

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
        } catch (\Exception $e) {
            // expected
        }

        // Retry on the persisted state: no second completion, only the flag is repaired.
        $this->lock->shouldReceive('acquire')->once()->andReturn(self::LOCK);
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');

        $this->assertSame(1, $order->paymentCompleteCalls);
        $this->assertSame('1', $order->state['flag']);
    }

    public function testLostLockAfterTheEffectIsReportedButStillReleased(): void
    {
        $order = $this->order(['status' => 'processing', 'flag' => true, 'paid' => true]);
        $this->lock->shouldReceive('acquire')->once()->andReturn(self::LOCK);
        // before the re-read, after the re-read, after the effect
        $this->lock->shouldReceive('assertOwned')->twice()->andReturnNull();
        $this->lock->shouldReceive('assertOwned')->once()->andThrow(new OrderStatusLockException('lost'));
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->reader->shouldReceive('refresh');

        $this->expectException(OrderStatusLockException::class);
        $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
    }

    public function testLockLostDuringTheFreshReadNeverRunsTheEffect(): void
    {
        // The re-read reconnects wpdb: the GET_LOCK of the old connection is gone before any effect.
        $order = $this->order();
        $this->lock->shouldReceive('acquire')->once()->andReturn(self::LOCK);
        $this->lock->shouldReceive('assertOwned')->once()->andReturnNull();
        $this->lock->shouldReceive('assertOwned')->once()->andThrow(new OrderStatusLockException('lost'));
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->reader->shouldReceive('refresh')->once();

        try {
            $this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw');
            $this->fail('OrderStatusLockException expected');
        } catch (OrderStatusLockException $e) {
            $this->assertSame(0, $order->paymentCompleteCalls, 'payment_complete() must not run without the lock');
            $this->assertSame([], $order->notes);
            $this->assertSame('', $order->state['flag']);
        }
    }

    public function testNonPixOrderKeepsTheExistingPathWithoutLockOrFlag(): void
    {
        $order = $this->order(['method' => 'woo-mercado-pago-basic']);
        $this->lock->shouldReceive('acquire')->never();
        $this->reader->shouldReceive('refresh')->never();
        WP_Mock::onFilter('woocommerce_payment_complete_order_status')->with('processing', 28, $order)->reply('processing');

        $this->assertTrue($this->orderStatus->processStatus('approved', ['id' => 1], $order, 'gw'));

        $this->assertSame(1, $order->paymentCompleteCalls);
        $this->assertSame('', $order->state['flag'], 'no Pix flag for other gateways');
    }
}

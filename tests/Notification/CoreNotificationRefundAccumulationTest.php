<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\FreshOrderReader;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PixOrderStatusLock;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

/**
 * MP-origin refund accumulation of CoreNotification::updatePaymentDetails(): read, sum and record of the
 * refund happen under a per-order lock over a fresh copy of the order, and the refund marker (which carries
 * the resulting total) is persisted before the payment's [Refund] total.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreNotificationRefundAccumulationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PAYMENT_KEY = 'Mercado Pago - Payment 999';
    private const MARKER_KEY  = '_mp_refund_applied_999_r-1';
    private const LOCK        = ['name' => 'mp_refund_acc_x', 'connection_id' => 7];

    /** Simulated database: what a fresh read of the order returns. */
    private array $db = [];

    private $lock;
    private $file;
    private $notification;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Mocks/FakeWcOrder.php';
        WP_Mock::setUp();
        WP_Mock::onFilter('woocommerce_mercadopago_pix_status_lock_enabled')->with(true)->reply(true);

        $this->file = Mockery::mock(File::class);
        $this->file->shouldIgnoreMissing();
        $logs       = Mockery::mock(Logs::class);
        $logs->file = $this->file;

        $this->lock = Mockery::mock(PixOrderStatusLock::class);
        $this->lock->shouldReceive('acquire')->andReturn(self::LOCK)->byDefault();
        $this->lock->shouldReceive('assertOwned')->byDefault();
        $this->lock->shouldReceive('release')->andReturn(true)->byDefault();

        $this->notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $this->notification->shouldReceive('refundLock')->andReturn($this->lock)->byDefault();
        $this->notification->shouldReceive('loadFreshOrder')->andReturnUsing(fn() => $this->dbOrder())->byDefault();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    /** A copy of the order as the database holds it; save_meta_data() writes back unless a key is "dropped". */
    private function dbOrder(): WC_Order
    {
        $db = &$this->db;

        return new class ($db) extends WC_Order {
            private array $database;

            public function __construct(array &$database)
            {
                $this->database = &$database;
                parent::__construct(['meta' => $database['meta'] ?? []]);
            }

            public function save_meta_data()
            {
                foreach ($this->state['meta'] as $key => $value) {
                    foreach ($this->database['dropped'] ?? [] as $prefix) {
                        if (strpos($key, $prefix) === 0) {
                            continue 2;
                        }
                    }
                    if (($this->database['meta'][$key] ?? null) !== $value) {
                        $this->database['writes'][] = $key;
                    }
                    $this->database['meta'][$key] = $value;
                }

                return true;
            }
        };
    }

    private function paymentRow(float $refund): string
    {
        return '[Date 2026-01-01 00:00:00]/[Amount 100]/[Payment Type account_money]/[Payment Method account_money]/[Paid 100]/[Coupon 0]/[Refund ' . $refund . ']';
    }

    private function data(string $refundId = 'r-1', $amount = 10): array
    {
        $payment = [
            'id'                => '999',
            'date'              => '2026-01-01 00:00:00',
            'total_amount'      => 100,
            'paid_amount'       => 100,
            'coupon_amount'     => 0,
            'payment_type_id'   => 'account_money',
            'payment_method_id' => 'account_money',
            'refunds'           => [$refundId => ['id' => $refundId]],
        ];

        return [
            'refunds_notifying' => [['id' => $refundId]],
            'current_refund'    => ['id' => $refundId, 'amount' => $amount],
            'payments_details'  => [$payment],
        ];
    }

    /** The order object of the request: loaded before the lock, so it can be stale. */
    private function requestOrder(array $meta = []): WC_Order
    {
        return new WC_Order(['meta' => $meta]);
    }

    /**
     * The order object of a request, backed by the same simulated database: save_meta_data() and save() write
     * only the keys this request changed, like WooCommerce does.
     */
    private function requestOrderOnDb(array $meta): WC_Order
    {
        $db = &$this->db;

        return new class ($db, $meta) extends WC_Order {
            private array $database;
            private array $changed = [];

            public function __construct(array &$database, array $meta)
            {
                $this->database = &$database;
                parent::__construct(['meta' => $meta]);
            }

            public function update_meta_data($key, $value, $meta_id = 0)
            {
                if (($this->state['meta'][$key] ?? null) !== $value) {
                    $this->changed[$key] = true;
                }
                parent::update_meta_data($key, $value, $meta_id);
            }

            public function save_meta_data()
            {
                foreach (array_keys($this->changed) as $key) {
                    $this->database['meta'][$key] = $this->state['meta'][$key];
                    $this->database['saved'][]    = $key;
                }
                $this->changed = [];

                return true;
            }

            public function save()
            {
                $this->save_meta_data();

                return parent::save();
            }
        };
    }

    public function testFirstDeliveryAddsTheRefundPersistingPendingMarkerThenTotalThenDone(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15)]];
        $order    = $this->requestOrder([self::PAYMENT_KEY => $this->paymentRow(15)]);
        $this->lock->shouldReceive('acquire')->once()->with(28, 3)->andReturn(self::LOCK);
        $this->lock->shouldReceive('release')->once()->with(self::LOCK)->andReturn(true);
        $this->lock->shouldReceive('assertOwned')->atLeast()->times(3)->with(self::LOCK);

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
        $this->assertStringContainsString('[Refund 25]', $this->db['meta'][self::PAYMENT_KEY]);
        $this->assertSame([self::MARKER_KEY, self::PAYMENT_KEY, self::MARKER_KEY], $this->db['writes'], 'pending marker, total, done');
        $this->assertStringContainsString('[Refund 25]', $order->state['meta'][self::PAYMENT_KEY]);
        $this->assertSame('done:10', $order->state['meta'][self::MARKER_KEY]);
    }

    public function testConcurrentRefundOfAnotherPaymentIsNotLostAndTheTotalComesFromTheFreshRead(): void
    {
        // The request loaded the order before another worker added refund r-0 (total 15): its copy says 0.
        $this->db = ['meta' => [
            self::PAYMENT_KEY            => $this->paymentRow(15),
            '_mp_refund_applied_999_r-0' => 'done:15',
        ]];
        $order = $this->requestOrder([self::PAYMENT_KEY => $this->paymentRow(0)]);

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertStringContainsString('[Refund 25]', $this->db['meta'][self::PAYMENT_KEY]);
        $this->assertSame('done:15', $this->db['meta']['_mp_refund_applied_999_r-0'], 'the other marker survives');
        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
    }

    public function testRedeliveryOfADoneRefundChangesNothing(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(25), self::MARKER_KEY => 'done:10']];
        $order    = $this->requestOrder([self::PAYMENT_KEY => $this->paymentRow(15)]);

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertArrayNotHasKey('writes', $this->db, 'nothing to write twice');
        $this->assertStringContainsString('[Refund 25]', $order->state['meta'][self::PAYMENT_KEY]);
    }

    public function testRedeliveryFinishesTheTotalWhenOnlyThePendingMarkerWasSaved(): void
    {
        // Marker stored but the [Refund] total write failed: still 0.
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0), self::MARKER_KEY => 'pending:0:10']];
        $order    = $this->requestOrder([self::PAYMENT_KEY => $this->paymentRow(0)]);

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertStringContainsString('[Refund 10]', $this->db['meta'][self::PAYMENT_KEY]);
        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
        $this->assertStringContainsString('[Refund 10]', $order->state['meta'][self::PAYMENT_KEY]);
    }

    public function testPendingMarkerWhoseTotalWasAlreadyWrittenIsOnlyMarkedDone(): void
    {
        // Total landed (0 + 10) but the marker was never finalized.
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(10), self::MARKER_KEY => 'pending:0:10']];

        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());

        $this->assertStringContainsString('[Refund 10]', $this->db['meta'][self::PAYMENT_KEY], 'not added twice');
        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
        $this->assertSame([self::MARKER_KEY], $this->db['writes']);
    }

    public function testAnotherRefundProcessedWhileAnOlderOneIsPendingDoesNotLoseEither(): void
    {
        // Refund r-1 (10) left a pending marker and no total. Refund r-2 (5) arrives before r-1 is redelivered.
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0), self::MARKER_KEY => 'pending:0:10']];
        $order    = $this->requestOrder([self::PAYMENT_KEY => $this->paymentRow(0)]);

        $this->notification->updatePaymentDetails($order, $this->data('r-2', 5));

        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY]);
        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
        $this->assertSame('done:5', $this->db['meta']['_mp_refund_applied_999_r-2']);

        // r-1 is redelivered afterwards: already part of the total.
        $this->db['writes'] = [];
        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data('r-1', 10));
        $this->assertSame([], $this->db['writes']);
        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY]);
    }

    public function testUnreadableMarkerIsReportedAndSkipped(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15), self::MARKER_KEY => 'done:10', '_mp_refund_applied_999_bad' => 'pending:x']];
        $this->file->shouldReceive('error')->once()->with('refund_marker_unreadable', Mockery::any(), Mockery::any());

        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());

        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY]);
    }

    public function testNothingIsAddedWhenTheMarkerCannotBeReadBack(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15)], 'dropped' => ['_mp_refund_applied_']];
        $order    = $this->requestOrder();
        $this->lock->shouldReceive('release')->once()->andReturn(true);

        try {
            $this->notification->updatePaymentDetails($order, $this->data());
            $this->fail('expected the notification to be retried');
        } catch (OrderStatusLockException $exception) {
            $this->assertSame(OrderStatusLockException::REASON_UNAVAILABLE, $exception->getReason());
        }

        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY], 'total untouched without a marker');
    }

    public function testBusyLockAbortsBeforeAnyReadOrWrite(): void
    {
        $this->db = ['meta' => []];
        $this->lock->shouldReceive('acquire')->once()
            ->andThrow(new OrderStatusLockException(OrderStatusLockException::REASON_BUSY));
        $this->lock->shouldReceive('release')->never();
        $this->notification->shouldReceive('loadFreshOrder')->never();

        $this->expectException(OrderStatusLockException::class);

        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());
    }

    public function testLostLockStopsTheUpdateAndStillReleases(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15)]];
        $this->lock->shouldReceive('assertOwned')->andThrow(new OrderStatusLockException(OrderStatusLockException::REASON_LOST));
        $this->lock->shouldReceive('release')->once()->andReturn(true);

        try {
            $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());
            $this->fail('expected the lost lock to abort');
        } catch (OrderStatusLockException $exception) {
            $this->assertSame(OrderStatusLockException::REASON_LOST, $exception->getReason());
        }

        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY], 'the total was not written');
    }

    public function testUnconfirmedReleaseIsLogged(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15)]];
        $this->lock->shouldReceive('release')->once()->andReturn(false);
        $this->file->shouldReceive('warning')->once()->with('refund_accumulation_lock_release_unconfirmed', Mockery::any(), Mockery::any());

        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());

        $this->assertStringContainsString('[Refund 25]', $this->db['meta'][self::PAYMENT_KEY]);
    }

    public function testOrderThatCannotBeReadIsRetried(): void
    {
        $logs       = Mockery::mock(Logs::class);
        $logs->file = $this->file;
        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $notification->shouldReceive('refundLock')->andReturn($this->lock);
        $notification->shouldReceive('loadFreshOrder')->once()->with(28)->andReturn(null);
        $this->lock->shouldReceive('release')->once()->andReturn(true);

        $this->expectException(OrderStatusLockException::class);

        $notification->updatePaymentDetails($this->requestOrder(), $this->data());
    }

    public function testDisabledLockFilterStillUsesAFreshReadAndTheMarker(): void
    {
        WP_Mock::onFilter('woocommerce_mercadopago_pix_status_lock_enabled')->with(true)->reply(false);
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(15)]];
        $this->lock->shouldReceive('acquire')->never();
        $this->lock->shouldReceive('assertOwned')->never();
        $this->lock->shouldReceive('release')->never();

        $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());

        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
    }

    public function testRefundWithoutAnOrderIdSkipsTheLockAndWorksOnTheOrderItself(): void
    {
        $order = new WC_Order(['id' => 0, 'meta' => [self::PAYMENT_KEY => $this->paymentRow(5)]]);
        $this->lock->shouldReceive('acquire')->never();
        $this->notification->shouldReceive('loadFreshOrder')->never();

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertStringContainsString('[Refund 15]', $order->state['meta'][self::PAYMENT_KEY]);
        $this->assertSame('done:10', $order->state['meta'][self::MARKER_KEY]);
    }

    public function testRefundIdsFromThePayloadNeverReachAMetaKeyUnsanitized(): void
    {
        $weird = "r 1\n'; DROP";
        $this->db = ['meta' => []];
        $order    = $this->requestOrder();

        $this->notification->updatePaymentDetails($order, $this->data($weird));

        $keys = array_filter(array_keys($this->db['meta']), fn($key) => strpos($key, '_mp_refund_applied_') === 0);
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression('/^_mp_refund_applied_999_[a-f0-9]{40}$/', reset($keys));
    }

    /** A notification whose protected seams are the real ones. */
    public function testPaymentTotalThatIsNotPersistedKeepsTheMarkerPending(): void
    {
        // The data store drops every write of the payment total but reports success, as a store that keeps the old value would.
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0)], 'dropped' => [self::PAYMENT_KEY]];
        $this->lock->shouldReceive('release')->once()->andReturn(true);
        $this->file->shouldReceive('error')->once()->with('refund_accumulation_total_not_persisted', Mockery::any(), Mockery::any());

        try {
            $this->notification->updatePaymentDetails($this->requestOrder(), $this->data());
            $this->fail('expected the notification to be retried');
        } catch (OrderStatusLockException $exception) {
            $this->assertSame(OrderStatusLockException::REASON_UNAVAILABLE, $exception->getReason());
        }

        $this->assertSame('pending:0:10', $this->db['meta'][self::MARKER_KEY], 'the marker must not be done while the total is unreadable');
        $this->assertStringContainsString('[Refund 0]', $this->db['meta'][self::PAYMENT_KEY]);
    }

    private function plainNotification(): CoreNotification
    {
        $logs       = Mockery::mock(Logs::class);
        $logs->file = $this->file;

        return new CoreNotification(
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class)
        );
    }

    private function callProtected(object $target, string $method, ...$args)
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($target, ...$args);
    }

    public function testRefundLockIsTheRefundNamespacedLockAndIsReused(): void
    {
        global $wpdb;
        $wpdb         = new \stdClass();
        $wpdb->prefix = 'wp_';
        $notification = $this->plainNotification();

        $lock = $this->callProtected($notification, 'refundLock');

        $this->assertInstanceOf(PixOrderStatusLock::class, $lock);
        $this->assertSame($lock, $this->callProtected($notification, 'refundLock'));
        $property = new \ReflectionProperty(PixOrderStatusLock::class, 'namePrefix');
        $property->setAccessible(true);
        $this->assertSame(PixOrderStatusLock::REFUND_NAME_PREFIX, $property->getValue($lock));
    }

    public function testFreshOrderIsALoadedAndRefreshedCopy(): void
    {
        $copy   = new WC_Order();
        $reader = Mockery::mock(FreshOrderReader::class);
        $reader->shouldReceive('refresh')->once()->with($copy);
        WP_Mock::userFunction('wc_get_order')->once()->with(28)->andReturn($copy);
        WP_Mock::userFunction('wc_get_order')->once()->with(29)->andReturn(false);
        $notification = $this->plainNotification();
        $property     = new \ReflectionProperty(CoreNotification::class, 'freshOrderReader');
        $property->setAccessible(true);
        $property->setValue($notification, $reader);

        $this->assertSame($copy, $this->callProtected($notification, 'loadFreshOrder', 28));
        $this->assertNull($this->callProtected($notification, 'loadFreshOrder', 29));
    }

    public function testSavingTheFirstRequestOrderLaterDoesNotOverwriteTheNewerTotal(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0)]];
        $orderA   = $this->requestOrderOnDb([self::PAYMENT_KEY => $this->paymentRow(0)]);
        $orderB   = $this->requestOrderOnDb([self::PAYMENT_KEY => $this->paymentRow(0)]);

        // Both requests loaded the order before any refund. A accumulates 10 and releases the lock...
        $this->notification->updatePaymentDetails($orderA, $this->data('r-1', 10));
        // ... B accumulates 5 on top and saves ...
        $this->notification->updatePaymentDetails($orderB, $this->data('r-2', 5));
        $orderB->save();
        // ... and only then A's request reaches its final save().
        $orderA->save();

        $this->assertStringContainsString('[Refund 15]', $this->db['meta'][self::PAYMENT_KEY], 'the older request must not write its total back');
        $this->assertSame('done:10', $this->db['meta'][self::MARKER_KEY]);
        $this->assertSame('done:5', $this->db['meta']['_mp_refund_applied_999_r-2']);
    }

    public function testPaymentTotalIsPersistedWhileTheLockIsStillHeld(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0)]];
        $order    = $this->requestOrderOnDb([self::PAYMENT_KEY => $this->paymentRow(0)]);
        $released = false;
        $this->lock->shouldReceive('release')->once()->andReturnUsing(function () use (&$released) {
            $released = true;

            return true;
        });

        $this->notification->updatePaymentDetails($order, $this->data());

        $this->assertTrue($released);
        $this->assertContains(self::PAYMENT_KEY, $this->db['saved'], 'the caller order stored the payment before the lock was released');
        $this->assertStringContainsString('[Refund 10]', $this->db['meta'][self::PAYMENT_KEY]);

        $order->save();

        $this->assertSame(1, count(array_keys($this->db['saved'], self::PAYMENT_KEY, true)), 'the final save has nothing left to write for the payment');
    }

    public function testLostLockAfterStoringTheTotalStillReleasesAndFails(): void
    {
        $this->db = ['meta' => [self::PAYMENT_KEY => $this->paymentRow(0)]];
        $order    = $this->requestOrderOnDb([self::PAYMENT_KEY => $this->paymentRow(0)]);
        // Ownership is checked after: pending marker, total, done marker (3), then the stored payment metadata (4th).
        $calls = 0;
        $this->lock->shouldReceive('assertOwned')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 4) {
                throw new OrderStatusLockException(OrderStatusLockException::REASON_LOST);
            }
        });
        $this->lock->shouldReceive('release')->once()->andReturn(true);

        $this->expectException(OrderStatusLockException::class);
        $this->notification->updatePaymentDetails($order, $this->data());
    }
}

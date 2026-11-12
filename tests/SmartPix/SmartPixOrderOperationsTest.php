<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderOperations;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixOrderOperationsTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider merchantPermissionProvider */
    public function testManualCancellationIsRestoredBeforeSaveWhenContextIsInconclusive(bool $mayOperate): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('cancel');
        $client->shouldNotReceive('query');
        $processor = new SmartPixMutationProcessor(
            $client,
            $metadata,
            Mockery::mock(Seller::class),
            Mockery::mock(SmartPixOperationRepository::class),
            static function (): void {
            },
            static function (): void {
            }
        );
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldReceive('get_id')->andReturn(321);
        $candidate->shouldReceive('get_status')->with('edit')->andReturn('cancelled');
        $candidate->shouldReceive('get_changes')->andReturn(['status' => 'cancelled']);
        $candidate->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $candidate->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $candidate->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $candidate->shouldReceive('read_meta_data')->with(true);
        $dataStore = Mockery::mock();
        $dataStore->shouldReceive('read')->once()->with($candidate);
        $candidate->shouldReceive('get_data_store')->once()->andReturn($dataStore);
        $candidate->shouldReceive('set_props')->once()->with([]);
        $candidate->shouldNotReceive('set_status');
        $candidate->shouldNotReceive('save');
        $candidate->shouldNotReceive('update_status');
        $stored = Mockery::mock(WC_Order::class);
        $stored->shouldReceive('get_status')->with('edit')->andReturn('pending');
        $stored->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $stored->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $stored->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $stored->shouldReceive('read_meta_data')->with(true)->andThrow(new \RuntimeException('READ_SENTINEL'));
        WP_Mock::userFunction('current_user_can', ['args' => ['manage_woocommerce'], 'return' => $mayOperate]);
        WP_Mock::userFunction('get_current_user_id', ['return' => 777]);
        WP_Mock::userFunction('wp_doing_cron', ['return' => false]);
        $service = new SmartPixOrderOperations(
            $metadata,
            $processor,
            new SmartPixRefundContext(),
            static function () use ($stored): WC_Order {
                return $stored;
            }
        );
        $service->beforeSave($candidate);
    }

    public function testMetadataReadFailureCannotReachProviderOrLeaveTheSaveUnprotected(): void
    {
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldReceive('get_id')->andReturn(321);
        $candidate->shouldReceive('get_status')->with('edit')->andReturn('cancelled');
        $candidate->shouldReceive('get_changes')->andReturn(['status' => 'cancelled']);
        $candidate->shouldReceive('read_meta_data')->with(true)->once()->andThrow(new \RuntimeException('SYNTHETIC_META_READ_FAILURE'));
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $client->shouldNotReceive('cancel');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPaymentContract');
        $processor = new SmartPixMutationProcessor(
            $client,
            $metadata,
            Mockery::mock(Seller::class),
            Mockery::mock(SmartPixOperationRepository::class),
            static function (): void {
            },
            static function (): void {
            }
        );
        $this->expectExceptionMessage('SYNTHETIC_META_READ_FAILURE');
        (new SmartPixOrderOperations($metadata, $processor, new SmartPixRefundContext()))->beforeSave($candidate);
    }

    public static function merchantPermissionProvider(): array
    {
        return [[true], [false]];
    }

    private const NOW = 1800000000;

    /** @dataProvider expiredUnlinkedCreationProvider */
    public function testMerchantCancelsAnExpiredUnlinkedCreationLocallyWithoutCallingOrders(array $facts): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->andReturn('');
        $stored = $this->unlinkedCreationOrder($facts);
        $stored->shouldNotReceive('update_status');
        // The cancellation is written on a fresh read, right after its facts are checked again.
        $current = $this->unlinkedCreationOrder($facts);
        $current->shouldReceive('read_meta_data')->once()->with(true);
        $current->shouldReceive('is_paid')->andReturn(false);
        $current->shouldReceive('update_status')->once()->with(
            'cancelled',
            'Mercado Pago: the merchant cancelled this order locally. The Smart Pix creation had no confirmed Order, so no cancellation was sent to Mercado Pago.'
        )->andReturn(true);
        $loaded = [$stored, $current];
        $this->merchantSession(777, true, false);

        $this->localCancelOperations($metadata, $loaded)->beforeSave($this->cancelledCandidate());

        $this->assertSame([], $loaded);
    }

    public static function expiredUnlinkedCreationProvider(): array
    {
        $limit = self::NOW - SmartPixOrderOperations::LOCAL_CANCEL_DELAY_SECONDS;

        return [
            'admission marker at the limit' => [['started_at' => (string) $limit]],
            'older admission without marker' => [['started_at' => '', 'created' => $limit - 600, 'modified' => $limit]],
            'on-hold order' => [['started_at' => (string) $limit, 'status' => 'on-hold']],
        ];
    }

    /** @dataProvider notLocallyCancellableProvider */
    public function testAnUnlinkedCreationIsNeverCancelledLocallyOutsideAnExpiredMerchantDecision(
        int $userId,
        bool $canManage,
        bool $doingCron,
        array $facts
    ): void {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->andReturn($facts['reference'] ?? '');
        $stored = $this->unlinkedCreationOrder($facts);
        // The Orders cancellation path stops at its first read, so the stored status is preserved.
        $stored->shouldReceive('read_meta_data')->with(true)->andThrow(new \RuntimeException('READ_SENTINEL'));
        $stored->shouldNotReceive('update_status');
        $loaded = [$stored];
        $this->merchantSession($userId, $canManage, $doingCron);

        $this->localCancelOperations($metadata, $loaded)->beforeSave($this->cancelledCandidate());

        // A local cancellation would read the order a second time before writing.
        $this->assertSame([], $loaded);
    }

    public static function notLocallyCancellableProvider(): array
    {
        $expired = (string) (self::NOW - SmartPixOrderOperations::LOCAL_CANCEL_DELAY_SECONDS);
        $recent = (string) (self::NOW - SmartPixOrderOperations::LOCAL_CANCEL_DELAY_SECONDS + 1);

        return [
            'background cancellation' => [0, false, false, ['started_at' => $expired]],
            'merchant without permission' => [777, false, false, ['started_at' => $expired]],
            'WP-Cron with a merchant session' => [777, true, true, ['started_at' => $expired]],
            'too early after the admission' => [777, true, false, ['started_at' => $recent]],
            'older admission modified too recently' => [777, true, false, [
                'started_at' => '', 'created' => self::NOW - 7200, 'modified' => (int) $recent,
            ]],
            'older admission without dates' => [777, true, false, ['started_at' => '', 'created' => null]],
            'Order already linked' => [777, true, false, ['started_at' => $expired, 'reference' => 'PPORDSYNTHETIC001']],
            'no Smart Pix creation started' => [777, true, false, ['started_at' => $expired, 'hash' => '']],
        ];
    }

    /**
     * @dataProvider changedBeforeLocalCancellationProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAnOrderThatChangedRightBeforeTheLocalCancellationIsPreserved(string $status, bool $paid, string $reference): void
    {
        $facts = ['started_at' => (string) (self::NOW - SmartPixOrderOperations::LOCAL_CANCEL_DELAY_SECONDS)];
        $stored = $this->unlinkedCreationOrder($facts);
        $current = $this->unlinkedCreationOrder($facts + ['status' => $status]);
        $current->shouldReceive('read_meta_data')->once()->with(true);
        $current->shouldReceive('is_paid')->andReturn($paid);
        $current->shouldNotReceive('update_status');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->with($stored)->andReturn('');
        $metadata->shouldReceive('getPpOrderId')->with($current)->andReturn($reference);
        $loaded = [$stored, $current];
        $this->merchantSession(777, true, false);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        Mockery::mock('alias:WC_Admin_Meta_Boxes')->shouldReceive('add_error')->once();

        $this->localCancelOperations($metadata, $loaded)->beforeSave($this->cancelledCandidate());

        $this->assertSame([], $loaded);
    }

    public static function changedBeforeLocalCancellationProvider(): array
    {
        return [
            'Order linked meanwhile' => ['pending', false, 'PPORDSYNTHETIC001'],
            'payment completed meanwhile' => ['processing', true, ''],
            'order cancelled meanwhile' => ['cancelled', false, ''],
            'paid in an open status' => ['on-hold', true, ''],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAFailedLocalStatusWriteIsReportedAsNotConfirmed(): void
    {
        $facts = ['started_at' => (string) (self::NOW - SmartPixOrderOperations::LOCAL_CANCEL_DELAY_SECONDS)];
        $stored = $this->unlinkedCreationOrder($facts);
        $current = $this->unlinkedCreationOrder($facts);
        $current->shouldReceive('read_meta_data')->once()->with(true);
        $current->shouldReceive('is_paid')->andReturn(false);
        $current->shouldReceive('update_status')->once()->andThrow(new \RuntimeException('WRITE_SENTINEL'));
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->andReturn('');
        $loaded = [$stored, $current];
        $this->merchantSession(777, true, false);
        Mockery::mock('alias:WC_Admin_Meta_Boxes')->shouldReceive('add_error')->once()
            ->with('Smart Pix cancellation could not be confirmed. The order status was preserved.');

        $this->localCancelOperations($metadata, $loaded)->beforeSave($this->cancelledCandidate());

        $this->assertSame([], $loaded);
    }

    /** @param array<string, mixed> $facts */
    private function unlinkedCreationOrder(array $facts): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_status')->with('edit')->andReturn($facts['status'] ?? 'pending');
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')
            ->andReturn($facts['hash'] ?? str_repeat('a', 64));
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::STARTED_AT_META_KEY, true, 'edit')
            ->andReturn($facts['started_at'] ?? '');
        $created = array_key_exists('created', $facts) ? $facts['created'] : self::NOW - 7200;
        $modified = $facts['modified'] ?? null;
        $order->shouldReceive('get_date_created')->with('edit')
            ->andReturn($created === null ? null : new \DateTimeImmutable('@' . $created));
        $order->shouldReceive('get_date_modified')->with('edit')
            ->andReturn($modified === null ? null : new \DateTimeImmutable('@' . $modified));

        return $order;
    }

    private function cancelledCandidate(): WC_Order
    {
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldReceive('get_id')->andReturn(321);
        $candidate->shouldReceive('get_status')->with('edit')->andReturn('cancelled');
        $candidate->shouldReceive('get_changes')->andReturn(['status' => 'cancelled']);
        $candidate->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $candidate->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $candidate->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $candidate->shouldReceive('read_meta_data')->with(true);
        $dataStore = Mockery::mock();
        $dataStore->shouldReceive('read')->once()->with($candidate);
        $candidate->shouldReceive('get_data_store')->once()->andReturn($dataStore);
        $candidate->shouldReceive('set_props')->once()->with([]);
        $candidate->shouldNotReceive('update_status', 'save');

        return $candidate;
    }

    private function merchantSession(int $userId, bool $canManage, bool $doingCron): void
    {
        WP_Mock::userFunction('current_user_can', ['args' => ['manage_woocommerce'], 'return' => $canManage]);
        WP_Mock::userFunction('get_current_user_id', ['return' => $userId]);
        WP_Mock::userFunction('wp_doing_cron', ['return' => $doingCron]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
    }

    /** @param list<WC_Order> $loaded */
    private function localCancelOperations(OrderMetadata $metadata, array &$loaded): SmartPixOrderOperations
    {
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('cancel', 'query');
        $repository = Mockery::mock(SmartPixOperationRepository::class);
        $repository->shouldNotReceive('reserve');
        $processor = new SmartPixMutationProcessor(
            $client,
            $metadata,
            Mockery::mock(Seller::class),
            $repository,
            static function (): void {
            },
            static function (): void {
            }
        );

        return new SmartPixOrderOperations(
            $metadata,
            $processor,
            new SmartPixRefundContext(),
            static function (int $id) use (&$loaded): WC_Order {
                self::assertSame(321, $id);
                return array_shift($loaded);
            },
            null,
            static fn(): int => self::NOW
        );
    }

    public function testSavedCancelledOrderDoesNotInvokeRemoteMutationOnMetadataSave(): void
    {
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldReceive('get_id')->andReturn(321);
        $candidate->shouldReceive('get_status')->with('edit')->andReturn('cancelled');
        $candidate->shouldReceive('get_changes')->andReturn([]);
        $candidate->shouldNotReceive('read_meta_data');
        $metadata = Mockery::mock(OrderMetadata::class);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $client->shouldNotReceive('cancel');
        $processor = new SmartPixMutationProcessor(
            $client,
            $metadata,
            Mockery::mock(Seller::class),
            Mockery::mock(SmartPixOperationRepository::class),
            static function (): void {
            },
            static function (): void {
            }
        );
        (new SmartPixOrderOperations($metadata, $processor, new SmartPixRefundContext()))->beforeSave($candidate);
    }

    public function testRefundWithoutTheMatchingNativeRefundIsRejectedBeforeReachingOrders(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId');
        $operations = Mockery::mock(SmartPixOperationRepository::class);
        $operations->shouldNotReceive('reserve');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'refund');
        $metrics = [];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $service = new SmartPixOrderOperations($metadata, $this->refundProcessor($client, $metadata, $operations, $metrics), new SmartPixRefundContext());

        try {
            $service->refund($order, 5.0);
            $this->fail('A refund without its native WooCommerce refund must not be reported as approved.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('unknown_error', $exception->getMessage());
        }
        $this->assertSame([['smart_pix_server_error', 'refund', 'refund_context_invalid']], $metrics);
    }

    /** @dataProvider refundReservationProvider */
    public function testRefundOutcomeFollowsTheDurableOperationReservation(string $status, bool $approved): void
    {
        $order = $this->refundableOrder();
        $metadata = $this->refundMetadata($order);
        $operations = Mockery::mock(SmartPixOperationRepository::class);
        $operations->shouldReceive('reserve')->once()->with($order, 'refund_155', Mockery::on(static function (array $context): bool {
            return $context['operation'] === 'refund' && $context['amount'] === '5.00' && $context['total'] === '20.00'
                && $context['order_reference'] === 'PPORDSYNTHETIC';
        }))->andReturn(['dispatch' => 'no', 'status' => $status]);
        $operations->shouldNotReceive('finish');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'refund');
        $metrics = [];
        $refundContext = new SmartPixRefundContext();
        $refundContext->capture($this->nativeRefund('-5.00'), ['refund_payment' => true]);
        $service = new SmartPixOrderOperations($metadata, $this->refundProcessor($client, $metadata, $operations, $metrics), $refundContext);

        if ($approved) {
            $this->assertSame(['status' => 'approved', 'data' => []], $service->refund($order, 5.0));
            $this->assertSame([], array_values(array_filter($metrics, static fn(array $metric): bool => $metric[0] === 'smart_pix_server_error')));
            return;
        }
        try {
            $service->refund($order, 5.0);
            $this->fail('An unresolved operation must not be reported as an approved refund.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('unknown_error', $exception->getMessage());
        }
        $this->assertContains(['smart_pix_server_error', 'refund', 'operation_unresolved'], $metrics);
    }

    public static function refundReservationProvider(): array
    {
        return ['already applied by another initiator' => ['succeeded', true], 'still unresolved' => ['dispatching', false]];
    }

    public function testCancellationSavedByTheNativeEffectItselfIsNotGuardedAgain(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPaymentContract');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'cancel');
        $metrics = [];
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldNotReceive('get_status', 'get_changes', 'read_meta_data', 'set_props');
        $nativeEffects = new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects();
        $service = new SmartPixOrderOperations(
            $metadata,
            $this->refundProcessor($client, $metadata, Mockery::mock(SmartPixOperationRepository::class), $metrics),
            new SmartPixRefundContext(),
            static function (): WC_Order {
                throw new \LogicException('The applied native object must not be reloaded.');
            },
            $nativeEffects
        );

        $this->assertTrue($nativeEffects->cancel($candidate, static function () use ($service, $candidate): bool {
            $service->beforeSave($candidate);
            return true;
        }));
        $this->assertSame([], $metrics);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUnconfirmedAdminCancellationTellsTheMerchantTheStatusWasPreserved(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'cancel');
        $metrics = [];
        $candidate = Mockery::mock(WC_Order::class);
        $candidate->shouldReceive('get_id')->andReturn(321);
        $candidate->shouldReceive('get_status')->with('edit')->andReturn('cancelled');
        $candidate->shouldReceive('get_changes')->andReturn(['status' => 'cancelled']);
        $candidate->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $candidate->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $candidate->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $candidate->shouldReceive('read_meta_data')->with(true);
        $dataStore = Mockery::mock();
        $dataStore->shouldReceive('read')->once()->with($candidate);
        $candidate->shouldReceive('get_data_store')->once()->andReturn($dataStore);
        $candidate->shouldReceive('set_props')->once()->with([]);
        $candidate->shouldNotReceive('save', 'update_status');
        $stored = Mockery::mock(WC_Order::class);
        $stored->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $stored->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $stored->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $stored->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $stored->shouldNotReceive('read_meta_data');
        WP_Mock::userFunction('get_current_user_id', ['return' => 0]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        Mockery::mock('alias:WC_Admin_Meta_Boxes')->shouldReceive('add_error')->once()
            ->with('Smart Pix cancellation could not be confirmed. The order status was preserved.');
        $service = new SmartPixOrderOperations(
            $metadata,
            $this->refundProcessor($client, $metadata, Mockery::mock(SmartPixOperationRepository::class), $metrics),
            new SmartPixRefundContext(),
            static function (int $id) use ($stored): WC_Order {
                return $stored;
            }
        );

        $service->beforeSave($candidate);
        $this->assertSame([], $metrics);
    }

    public function testTheRefundContextIsTheInstanceSharedWithTheRefundHook(): void
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metrics = [];
        $refundContext = new SmartPixRefundContext();
        $service = new SmartPixOrderOperations(
            $metadata,
            $this->refundProcessor(Mockery::mock(SmartPixOrderClient::class), $metadata, Mockery::mock(SmartPixOperationRepository::class), $metrics),
            $refundContext
        );

        $this->assertSame($refundContext, $service->getRefundContext());
    }

    /** @param list<array{string, mixed, string|null}> $metrics */
    private function refundProcessor(SmartPixOrderClient $client, OrderMetadata $metadata, SmartPixOperationRepository $operations, array &$metrics): SmartPixMutationProcessor
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCustIdFromAT')->andReturn('12345');
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        return new SmartPixMutationProcessor(
            $client,
            $metadata,
            $seller,
            $operations,
            static function (string $name, $value, ?string $message) use (&$metrics): void {
                $metrics[] = [$name, $value, $message];
            },
            static function (): void {
                throw new \LogicException('A refund reservation must not apply native terminal effects.');
            }
        );
    }

    /** A paid Smart Pix order with a native total of 20.00 BRL. */
    private function refundableOrder(): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('get_total')->with('edit')->andReturn('20.00');
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn('woo-mercado-pago-pix');
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('is_paid')->andReturn(true);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn(new \stdClass());
        $order->shouldNotReceive('payment_complete', 'update_status');
        return $order;
    }

    private function refundMetadata(WC_Order $order): OrderMetadata
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('PPORDSYNTHETIC');
        $metadata->shouldReceive('getSmartPixAttemptReference')->with($order)->andReturn('WC-321');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        return $metadata;
    }

    private function nativeRefund(string $total): \WC_Order_Refund
    {
        $refund = Mockery::mock(\WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(155);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $refund->shouldReceive('get_total')->with('edit')->andReturn($total);
        return $refund;
    }
}

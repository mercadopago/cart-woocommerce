<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixRefundReconciler;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;
use WC_Order_Refund;
use WP_Mock;

/** Given/When/Then regression of mixed WooCommerce/provider partial refunds. */
class SmartPixPartialRefundImportTest extends TestCase
{
    private string $journal = '';
    private string $native = '0.30';
    private string $status = 'processing';
    private array $refunds = [];
    private array $notes = [];
    private int $generation = 0;
    private WC_Order $order;
    private SmartPixOperationRepository $operations;
    private SmartPixRefundReconciler $reconciler;
    private SmartPixStateApplicator $applicator;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $this->order = $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_total')->with('edit')->andReturn('2.36');
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_status')->with('edit')->andReturnUsing(fn(): string => $this->status);
        $order->shouldReceive('is_paid')->andReturn(true);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn(new \stdClass());
        $order->shouldReceive('get_total_refunded')->andReturnUsing(fn(): float => (float) $this->native);
        $order->shouldReceive('get_refunds')->andReturnUsing(fn(): array => $this->refunds)->byDefault();
        $order->shouldReceive('read_meta_data')->with(true);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->byDefault();
        $order->shouldReceive('get_data_store')->andReturn($store);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(fn(): string => $this->journal);
        $order->shouldReceive('update_meta_data')->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(function ($field, string $value): void {
                $this->journal = $value;
            });
        $order->shouldReceive('save_meta_data');
        $order->shouldReceive('add_order_note')->andReturnUsing(function (string $note): int {
            $this->notes[] = $note;
            return count($this->notes);
        });
        $order->shouldNotReceive('save', 'payment_complete', 'update_status');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('PPORDSYNTHETIC');
        $metadata->shouldReceive('getPpPaymentId')->with($order)->andReturn('PPPAYSYNTHETIC');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('12345');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire', 'start', 'complete', 'markUncertain', 'releaseReserved');
        $this->operations = new SmartPixOperationRepository(new SmartPixStatusLock(new SmartPixStatusLockDatabase(), 'operation_'));
        $this->reconciler = new SmartPixRefundReconciler($metadata, $seller, $this->operations);
        $this->applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, null, null, null, $this->reconciler);
        $this->refunds = [$this->refund(191, '0.30', true)];
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => function () use ($order): WC_Order {
            $this->generation++;
            return $order;
        }]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** Given a local 0.30 refund, when Orders confirms 0.60, then import 0.30 once. */
    public function testMixedPartialRefundsAndRedeliveriesImportOnlyTheMissingThirtyCents(): void
    {
        $this->expectImport('0.30', '0.60');
        $response = $this->response();
        $this->assertSame('applied', $this->apply($response)['outcome']);
        $this->assertSame('no_change', $this->apply($response)['outcome']);
        $this->assertSame('no_change', $this->applicator->apply(321, 'SYNTHETIC_KEY', $response, 'internal')['outcome']);
        $this->assertSame('0.60', $this->native);
        $this->assertCount(2, $this->refunds);
        $this->assertCount(1, $this->notes);
        $records = json_decode($this->journal, true);
        $this->assertCount(1, $records);
        $this->assertSame('succeeded', reset($records)['status']);
        $this->assertSame('native_partial_refund', reset($records)['operation']);
    }

    /** Given no local refund, when the dashboard refunds, then import the provider total. */
    public function testFirstPartialRefundCanOriginateOutsideWooCommerce(): void
    {
        $this->native = '0.00';
        $this->refunds = [];
        $this->expectImport('0.60', '0.60');
        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $this->assertCount(1, $this->refunds);
    }

    /** Given a completed import, when the provider refunds again, then import only the new delta. */
    public function testLaterCumulativeBalanceImportsANewDeltaAndOldSnapshotsCannotDuplicateIt(): void
    {
        $this->expectImport('0.30', '0.60');
        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->expectImport('0.30', '0.90');
        $later = $this->response();
        $later['refund_context']['refunded_amount'] = '0.90';
        $this->assertSame('applied', $this->apply($later)['outcome']);
        $this->assertSame('no_change', $this->apply($later)['outcome']);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame('0.90', $this->native);
        $this->assertCount(3, $this->refunds);
        $this->assertCount(2, $this->notes);
    }

    /** Given an executing import, when notification/sync/admin race, then neither redispatches. */
    public function testConcurrentDeliveryAndFinancialRefundCannotEnterAnExecutingImport(): void
    {
        $nested = [];
        $this->expectImport('0.30', '0.60', function () use (&$nested): void {
            $nested = $this->apply($this->response());
            try {
                $this->operations->reserve($this->order, 'refund_999', $this->financialContext());
                $this->fail('An admin financial mutation must be blocked during an import.');
            } catch (RuntimeException $failure) {
                $this->assertSame('operation_unresolved', $failure->getMessage());
            }
        });
        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->assertSame('uncertain', $nested['outcome']);
        $this->assertCount(2, $this->refunds);
    }

    public function testNativeRefundListIsReloadedFromAFreshOrderAfterCreation(): void
    {
        $cached = [];
        $this->order->shouldReceive('get_refunds')->andReturnUsing(function () use (&$cached): array {
            if (!isset($cached[$this->generation])) {
                $cached[$this->generation] = $this->refunds;
            }
            return $cached[$this->generation];
        });
        $this->expectImport('0.30', '0.60');
        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->assertSame(3, $this->generation, 'Datastore reads alone retain WC_Order cached refunds.');
        $this->assertCount(1, $cached[2]);
        $this->assertCount(2, $cached[3]);
    }

    public function testFinancialOperationAlreadyInProgressPreventsAnImport(): void
    {
        $this->operations->reserve($this->order, 'refund_999', $this->financialContext());
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame('0.30', $this->native);
    }

    /** @dataProvider provisionalRefundProvider */
    public function testProvisionalNativeRefundCannotBeMistakenForAConfirmedBalance(bool $appearsAfterReservation): void
    {
        $provisional = $this->refund(999, '0.30', false, true);
        if ($appearsAfterReservation) {
            $reads = 0;
            $this->order->get_data_store()->shouldReceive('read')->with($this->order)->twice()->andReturnUsing(function () use ($provisional, &$reads): void {
                if (++$reads === 2) {
                    $this->refunds[] = $provisional;
                    $this->native = '0.60';
                }
            });
        } else {
            $this->refunds[] = $provisional;
            $this->native = '0.60';
        }
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertCount(0, $this->notes);
    }

    public static function provisionalRefundProvider(): array
    {
        return [[false], [true]];
    }

    /** Given a manual Woo refund (no gateway call), the import counts it instead of retrying forever. */
    public function testManualNativeRefundEqualToTheProviderBalanceIsAcknowledgedWithoutAnImport(): void
    {
        $this->refunds[] = $this->refund(998, '0.30', false);
        $this->native = '0.60';
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $this->assertCount(0, $this->notes);
        $this->assertSame('', $this->journal);
    }

    public function testManualNativeRefundBelowTheProviderBalanceImportsOnlyTheDifference(): void
    {
        $this->refunds[] = $this->refund(998, '0.10', false);
        $this->native = '0.40';
        $this->expectImport('0.20', '0.60');

        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $this->assertCount(1, $this->notes);
    }

    /** @dataProvider invalidContextProvider */
    public function testUntrustedOrInconsistentPartialSnapshotNeverCreatesANativeRefund(string $case): void
    {
        $response = $this->response();
        switch ($case) {
            case 'seller':
                $response['refund_context']['seller_reference'] = 'OTHER';
                break;
            case 'attempt':
                $response['refund_context']['attempt_reference'] = 'WC-999';
                break;
            case 'payment':
                $response['payments'][0]['reference'] = 'PPPAYOTHER';
                break;
            case 'order':
                $response['order_reference'] = 'PPORDOTHER';
                break;
            case 'full':
                $response['refund_context']['refunded_amount'] = '2.36';
                break;
            case 'zero':
                $response['refund_context']['refunded_amount'] = '0.00';
                break;
            case 'float':
                $response['refund_context']['refunded_amount'] = 0.60;
                break;
            case 'less_than_native':
                $response['refund_context']['refunded_amount'] = '0.20';
                break;
            case 'currency':
                $response['refund_context']['amount']['currency'] = 'USD';
                break;
        }
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertNotSame('applied', $this->apply($response)['outcome']);
        $this->assertSame('', $this->journal);
        $this->assertSame('0.30', $this->native);
    }

    public static function invalidContextProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['seller', 'attempt', 'payment', 'order', 'full', 'zero', 'float', 'less_than_native', 'currency']);
    }

    public function testFailedCreationCanBeRetriedWithoutReportingARefundOrNote(): void
    {
        if (!class_exists('WP_Error')) {
            Mockery::mock('alias:WP_Error');
        }
        WP_Mock::userFunction('wc_create_refund', ['times' => 1, 'return' => (new \ReflectionClass('WP_Error'))->newInstanceWithoutConstructor()]);
        $result = $this->apply($this->response());
        $this->assertSame('uncertain', $result['outcome']);
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($result));
        $records = json_decode($this->journal, true);
        $this->assertSame('read_failed', reset($records)['status']);
        $this->assertSame([], $this->notes);
        $this->expectImport('0.30', '0.60');
        $this->assertSame('applied', $this->apply($this->response())['outcome']);
    }

    public function testUnverifiedMarkerAfterCreationCannotCauseADuplicateNativeRefund(): void
    {
        WP_Mock::userFunction('wc_create_refund', [
            'times' => 1,
            'return' => function (): WC_Order_Refund {
                $refund = $this->refund(202, '0.30', false);
                $refund->shouldReceive('get_meta')->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, true, 'edit')->andReturn('');
                $this->refunds[] = $refund;
                $this->native = '0.60';
                return $refund;
            },
        ]);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        // The unmarked refund is not a gateway refund in flight, so the balance already matches the
        // provider: the redelivery creates nothing and the journal stays unresolved for review.
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $this->assertSame('0.60', $this->native);
        $this->assertCount(2, $this->refunds);
        $records = json_decode($this->journal, true);
        $this->assertSame('uncertain', reset($records)['status']);
        $this->assertSame([], $this->notes);
    }

    public function testNativeBalanceCompletedBeforeReservationIsAcknowledgedWithoutAnImport(): void
    {
        $reads = 0;
        $this->order->get_data_store()->shouldReceive('read')->with($this->order)->twice()->andReturnUsing(function () use (&$reads): void {
            if (++$reads === 2) {
                $this->refunds[] = $this->refund(192, '0.30', true);
                $this->native = '0.60';
            }
        });
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $records = json_decode($this->journal, true);
        $this->assertSame('succeeded', reset($records)['status']);
        $this->assertSame([], $this->notes);
    }

    /** A throw that left a native refund behind is ambiguous: never import it again automatically. */
    public function testAmbiguousNativeFailureStaysBlockedAndIsNeverRetriedAutomatically(): void
    {
        WP_Mock::userFunction('wc_create_refund', ['times' => 1, 'return' => function () {
            $this->refunds[] = $this->refund(202, '0.30', false);
            $this->native = '0.60';
            throw new RuntimeException('PRIVATE_SENTINEL');
        }]);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $records = json_decode($this->journal, true);
        $this->assertSame('uncertain', reset($records)['status']);
        $this->assertSame([], $this->notes);
        $context = $this->financialContext();
        $context['amount'] = $context['total'];
        $this->expectException(RuntimeException::class);
        $this->operations->reconcileConfirmedFullRefund($this->order, $context);
    }

    /** A throw that persisted no native refund is retried once by the next delivery. */
    public function testRolledBackCreationBecomesRetryableAndAppliesExactlyOnce(): void
    {
        $calls = 0;
        WP_Mock::userFunction('wc_create_refund', [
            'times' => 2,
            'args' => [['order_id' => 321, 'amount' => '0.30', 'reason' => 'Mercado Pago: payment refunded.',
                'refund_payment' => false, 'restock_items' => false]],
            'return' => function () use (&$calls): WC_Order_Refund {
                if (++$calls === 1) {
                    throw new RuntimeException('PRIVATE_SENTINEL');
                }
                $refund = $this->refund(202, '0.30', false);
                $this->refunds[] = $refund;
                $this->native = '0.60';
                return $refund;
            },
        ]);
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame(['uncertain'], array_column(json_decode($this->journal, true), 'status'));

        $this->assertSame('applied', $this->apply($this->response())['outcome']);
        $this->assertSame('no_change', $this->apply($this->response())['outcome']);

        $this->assertSame(2, $calls);
        $this->assertSame('0.60', $this->native);
        $this->assertCount(2, $this->refunds);
        $this->assertCount(1, $this->notes);
        $records = json_decode($this->journal, true);
        $this->assertCount(1, $records);
        $this->assertSame('succeeded', reset($records)['status']);
    }

    /** The marker saved on the created refund proves the import when only its verification failed. */
    public function testVerifiedMarkerRecoversAnImportInterruptedAfterCreationWithoutASecondRefund(): void
    {
        $reads = 0;
        $this->order->get_data_store()->shouldReceive('read')->with($this->order)->andReturnUsing(function () use (&$reads): void {
            // Applicator load, reload after reservation, then the reload after creation fails.
            if (++$reads === 3) {
                throw new RuntimeException('PRIVATE_SENTINEL');
            }
        });
        $this->expectImport('0.30', '0.60');
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame(['uncertain'], array_column(json_decode($this->journal, true), 'status'));

        $this->assertSame('no_change', $this->apply($this->response())['outcome']);

        $records = json_decode($this->journal, true);
        $this->assertSame('succeeded', reset($records)['status']);
        $this->assertCount(2, $this->refunds);
        $this->assertSame('0.60', $this->native);
        // The journal no longer blocks the next financial operation.
        $this->assertSame('yes', $this->operations->reserve($this->order, 'refund_999', $this->financialContext())['dispatch']);
    }

    /** Given an interrupted partial import, when Orders already reports the full refund, then import only the rest. */
    public function testInterruptedPartialImportIsRecoveredWhenTheNextReadIsAlreadyTheFullRefund(): void
    {
        $reads = 0;
        $this->order->get_data_store()->shouldReceive('read')->with($this->order)->andReturnUsing(function () use (&$reads): void {
            // Applicator load, reload after reservation, then the reload after creation fails.
            if (++$reads === 3) {
                throw new RuntimeException('PRIVATE_SENTINEL');
            }
        });
        $this->expectImport('0.30', '0.60');
        $this->assertSame('uncertain', $this->apply($this->response())['outcome']);
        $this->assertSame(['uncertain'], array_column(json_decode($this->journal, true), 'status'));
        WP_Mock::userFunction('wc_get_orders', ['return' => fn(): array => $this->refunds]);
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [['order_id' => 321, 'amount' => '1.76', 'reason' => 'Mercado Pago: payment refunded.',
                'refund_payment' => false, 'restock_items' => false]],
            'times' => 1,
            'return' => function (): WC_Order_Refund {
                $refund = $this->refund(202, '1.76', false);
                $this->refunds[] = $refund;
                $this->native = '2.36';
                $this->status = 'refunded';
                return $refund;
            },
        ]);
        $full = $this->response();
        $full['payments'][0]['state'] = 'refunded';
        $full['observed'] = ['order_status' => 'refunded', 'order_status_detail' => 'refunded',
            'payment_status' => 'refunded', 'payment_status_detail' => 'refunded'];
        $full['refund_context']['refunded_amount'] = '2.36';

        $result = $this->reconciler->apply($this->order, 'SYNTHETIC_KEY', $full, static fn(): bool => true);

        $this->assertSame('applied', $result['outcome']);
        // The marked 0.30 counts once: the full refund imports only the remaining 1.76.
        $this->assertSame(['succeeded', 'succeeded'], array_column(json_decode($this->journal, true), 'status'));
        $this->assertCount(3, $this->refunds);
        $this->assertSame('2.36', $this->native);
    }

    /** Recovery is best-effort: its failure keeps the existing acknowledgement of a settled import. */
    public function testUnreadableJournalDuringRecoveryDoesNotChangeAnAlreadySettledImport(): void
    {
        $imported = $this->refund(202, '0.30', false);
        $imported->update_meta_data(SmartPixRefundReconciler::PARTIAL_IMPORT_META, ['attempt' => hash('sha256', 'SYNTHETIC_KEY'), 'target' => '0.60']);
        $this->refunds[] = $imported;
        $this->native = '0.60';
        $this->journal = '{not json';
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame('no_change', $this->apply($this->response())['outcome']);
        $this->assertSame('{not json', $this->journal);
    }

    private function expectImport(string $amount, string $target, ?callable $during = null): void
    {
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [['order_id' => 321, 'amount' => $amount, 'reason' => 'Mercado Pago: payment refunded.',
                'refund_payment' => false, 'restock_items' => false]],
            'times' => 1,
            'return' => function () use ($amount, $target, $during): WC_Order_Refund {
                if ($during !== null) {
                    $during();
                }
                $refund = $this->refund(200 + count($this->refunds), $amount, false);
                $this->refunds[] = $refund;
                $this->native = $target;
                return $refund;
            },
        ]);
    }

    private function refund(int $id, string $amount, bool $paid, bool $inFlight = false): WC_Order_Refund
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn($id);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-' . $amount);
        $refund->shouldReceive('get_refunded_payment')->with('edit')->andReturn($paid);
        $refund->shouldReceive('get_meta')->with(SmartPixRefundContext::IN_FLIGHT_META_KEY, true, 'edit')
            ->andReturn($inFlight ? '1' : '');
        $marker = '';
        $refund->shouldReceive('get_meta')->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, true, 'edit')
            ->andReturnUsing(static function () use (&$marker) {
                return $marker;
            })->byDefault();
        $refund->shouldReceive('update_meta_data')->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, Mockery::type('array'))
            ->andReturnUsing(static function ($field, $value) use (&$marker): void {
                $marker = $value;
            });
        $refund->shouldReceive('save_meta_data', 'read_meta_data');
        return $refund;
    }

    private function apply(array $response): array
    {
        return $this->applicator->apply(321, 'SYNTHETIC_KEY', $response, 'notification');
    }

    private function financialContext(): array
    {
        return ['operation' => 'refund', 'attempt_key' => 'SYNTHETIC_KEY', 'order_reference' => 'PPORDSYNTHETIC',
            'attempt_reference' => 'WC-321', 'seller' => '12345', 'total' => '2.36', 'amount' => '0.30', 'currency' => 'BRL'];
    }

    private function response(): array
    {
        return ['outcome' => 'success', 'state' => 'terminal_success', 'order_reference' => 'PPORDSYNTHETIC',
            'eligibility' => null, 'authorization' => null, 'error' => null,
            'payments' => [['reference' => 'PPPAYSYNTHETIC', 'state' => 'approved', 'amount' => ['value' => '2.36', 'currency' => 'BRL']]],
            'observed' => ['order_status' => 'processed', 'order_status_detail' => 'partially_refunded',
                'payment_status' => 'processed', 'payment_status_detail' => 'partially_refunded'],
            'refund_context' => ['contract_version' => SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
                'seller_reference' => '12345', 'attempt_reference' => 'WC-321',
                'amount' => ['value' => '2.36', 'currency' => 'BRL'], 'refunded_amount' => '0.60']];
    }
}

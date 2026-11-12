<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRefundContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixRefundReconciler;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Refund;
use WP_Mock;

class SmartPixRefundReconcilerTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider nativeBalanceProvider */
    public function testProviderFullRefundCreatesOnlyTheMissingNativeAmountAndReplayDoesNothing(string $previous, string $status = 'processing'): void
    {
        [$reconciler, $order, $operations, $metadata, $seller] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn($status, $status, $status, 'refunded', 'refunded', 'refunded');
        // WooCommerce reports a store-configured paid status as paid.
        $order->shouldReceive('is_paid')->andReturn($status === 'paid-by-store');
        $order->shouldReceive('get_total_refunded')->andReturn((float) $previous, 20.0);
        $operations->shouldReceive('reserve')->once()->with($order, 'provider_full_refund', Mockery::on(static function ($context): bool {
            return $context['amount'] === '20.00' && $context['operation'] === 'refund'
                && $context['order_reference'] === 'PPORDSYNTHETIC';
        }))->andReturn(['dispatch' => 'yes', 'id' => 'SYNTHETIC_RESERVATION']);
        $operations->shouldReceive('finish')->once()->with($order, Mockery::type('array'), 'succeeded');
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(155);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $remaining = $previous === '0.00' ? '20.00' : '15.00';
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-' . $remaining);
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [[
                'order_id' => 321, 'amount' => $remaining,
                'reason' => 'Mercado Pago: payment refunded.', 'refund_payment' => false, 'restock_items' => false,
            ]], 'times' => 1, 'return' => $refund,
        ]);

        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $owner = str_repeat('a', 64);
        $ownership->shouldReceive('acquire')->twice()->with(321, 'SYNTHETIC_KEY', 'record_full_refund')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner], ['outcome' => 'completed', 'owner' => null]);
        $ownership->shouldReceive('start')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund', $owner)->andReturn(true);
        $ownership->shouldReceive('complete')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund', $owner)->andReturn(true);
        $ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 2, 'return' => $order]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, null, null, null, $reconciler);
        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_refund_verified', 'status' => 'refunded'],
            $applicator->apply(321, 'SYNTHETIC_KEY', $this->response(), 'notification'));
        $this->assertSame(['outcome' => 'no_change', 'reason' => 'refund_already_applied', 'status' => 'refunded'],
            $applicator->apply(321, 'SYNTHETIC_KEY', $this->response(), 'internal'));
    }

    public static function nativeBalanceProvider(): array
    {
        // A pending order whose approval never applied still closes as refunded: the provider Order
        // was paid and fully refunded, so the native totals must match it (no approval is recorded).
        return ['paid order' => ['0.00'], 'partially refunded order' => ['5.00'], 'approval never applied' => ['0.00', 'pending'],
            'store paid status' => ['0.00', 'paid-by-store']];
    }

    public function testFullRefundReadRecoversTheMissingSixtyEightCentsOnceWithoutAnotherProviderRefund(): void
    {
        [, $order, , $metadata, $seller] = $this->context();
        $order->shouldReceive('get_total')->with('edit')->andReturn('1.18');
        $nativeTotal = 0.50;
        $nativeStatus = 'processing';
        $order->shouldReceive('get_status')->with('edit')->andReturnUsing(static function () use (&$nativeStatus): string {
            return $nativeStatus;
        });
        $order->shouldReceive('get_total_refunded')->andReturnUsing(static function () use (&$nativeTotal): float {
            return $nativeTotal;
        });
        $partial = Mockery::mock(WC_Order_Refund::class);
        $partial->shouldReceive('get_id')->andReturn(154);
        $order->shouldReceive('get_refunds')->andReturn([$partial]);
        $base = ['operation' => 'refund', 'attempt_key' => 'SYNTHETIC_KEY', 'order_reference' => 'PPORDSYNTHETIC',
            'attempt_reference' => 'WC-321', 'seller' => '12345', 'total' => '1.18', 'currency' => 'BRL'];
        $firstId = hash('sha256', 'SYNTHETIC_KEY|refund_154');
        $uncertainId = hash('sha256', 'SYNTHETIC_KEY|refund_155');
        $encoded = json_encode([
            $firstId => $base + ['amount' => '0.50', 'id' => $firstId, 'key' => str_repeat('a', 64), 'status' => 'succeeded'],
            $uncertainId => $base + ['amount' => '0.68', 'id' => $uncertainId, 'key' => str_repeat('b', 64), 'status' => 'uncertain'],
        ]);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$encoded): string {
                return $encoded;
            });
        $order->shouldReceive('update_meta_data')->times(3)->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->times(3);
        $order->shouldNotReceive('save');
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(156);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-0.68');
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [['order_id' => 321, 'amount' => '0.68',
                'reason' => 'Mercado Pago: payment refunded.', 'refund_payment' => false, 'restock_items' => false]],
            'times' => 1,
            'return' => static function () use (&$nativeTotal, &$nativeStatus, $refund): WC_Order_Refund {
                $nativeTotal = 1.18;
                $nativeStatus = 'refunded';
                return $refund;
            },
        ]);
        $operations = new SmartPixOperationRepository(new SmartPixStatusLock(new SmartPixStatusLockDatabase(), 'operation_'));
        $reconciler = new SmartPixRefundReconciler($metadata, $seller, $operations);
        $response = $this->response();
        $response['payments'][0]['amount']['value'] = '1.18';
        $response['refund_context']['amount']['value'] = '1.18';
        $response['refund_context']['refunded_amount'] = '1.18';
        $effects = 0;
        $beforeEffect = static function () use (&$effects): bool {
            $effects++;
            return true;
        };

        $this->assertSame('applied', $reconciler->apply($order, 'SYNTHETIC_KEY', $response, $beforeEffect)['outcome']);
        $this->assertSame('no_change', $reconciler->apply($order, 'SYNTHETIC_KEY', $response, $beforeEffect)['outcome']);
        $records = json_decode($encoded, true);
        $this->assertSame(1, $effects);
        $this->assertSame(1.18, $nativeTotal);
        $this->assertSame('refunded', $nativeStatus);
        $this->assertCount(3, $records);
        $this->assertSame('succeeded', $records[$firstId]['status']);
        $this->assertSame('reconciled', $records[$uncertainId]['status']);
        $this->assertSame('succeeded', $records[hash('sha256', 'SYNTHETIC_KEY|provider_full_refund')]['status']);
    }

    /** An uncertain admin partial refund cannot block importing the provider's later full refund. */
    public function testProviderFullRefundImportsTheWholeTotalAfterAnUncertainAdminPartialOfAnotherAmount(): void
    {
        [, $order, , $metadata, $seller] = $this->context();
        $order->shouldReceive('get_total')->with('edit')->andReturn('1.18');
        $nativeTotal = 0.0;
        $nativeStatus = 'processing';
        $order->shouldReceive('get_status')->with('edit')->andReturnUsing(static function () use (&$nativeStatus): string {
            return $nativeStatus;
        });
        $order->shouldReceive('get_total_refunded')->andReturnUsing(static function () use (&$nativeTotal): float {
            return $nativeTotal;
        });
        // WooCommerce removed the provisional admin refund after its gateway call failed.
        $order->shouldReceive('get_refunds')->andReturn([]);
        $uncertainId = hash('sha256', 'SYNTHETIC_KEY|refund_155');
        $encoded = json_encode([$uncertainId => ['operation' => 'refund', 'attempt_key' => 'SYNTHETIC_KEY',
            'order_reference' => 'PPORDSYNTHETIC', 'attempt_reference' => 'WC-321', 'seller' => '12345',
            'total' => '1.18', 'currency' => 'BRL', 'amount' => '0.30', 'id' => $uncertainId,
            'key' => str_repeat('b', 64), 'status' => 'uncertain']]);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$encoded): string {
                return $encoded;
            });
        $order->shouldReceive('update_meta_data')->times(3)->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->times(3);
        $order->shouldNotReceive('save');
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(156);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-1.18');
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [['order_id' => 321, 'amount' => '1.18',
                'reason' => 'Mercado Pago: payment refunded.', 'refund_payment' => false, 'restock_items' => false]],
            'times' => 1,
            'return' => static function () use (&$nativeTotal, &$nativeStatus, $refund): WC_Order_Refund {
                $nativeTotal = 1.18;
                $nativeStatus = 'refunded';
                return $refund;
            },
        ]);
        $reconciler = new SmartPixRefundReconciler($metadata, $seller,
            new SmartPixOperationRepository(new SmartPixStatusLock(new SmartPixStatusLockDatabase(), 'operation_')));
        $response = $this->response();
        $response['payments'][0]['amount']['value'] = '1.18';
        $response['refund_context']['amount']['value'] = '1.18';
        $response['refund_context']['refunded_amount'] = '1.18';
        $beforeEffect = static fn(): bool => true;

        $this->assertSame('applied', $reconciler->apply($order, 'SYNTHETIC_KEY', $response, $beforeEffect)['outcome']);
        $this->assertSame('no_change', $reconciler->apply($order, 'SYNTHETIC_KEY', $response, $beforeEffect)['outcome']);
        $records = json_decode($encoded, true);
        $this->assertSame('reconciled', $records[$uncertainId]['status']);
        $this->assertSame('succeeded', $records[hash('sha256', 'SYNTHETIC_KEY|provider_full_refund')]['status']);
    }

    /** @dataProvider partialConfirmationProvider */
    public function testPartialRefundOnlyAcknowledgesMatchingPaidNativeFacts(string $case, string $expected): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn($case === 'unpaid_status' ? 'pending'
            : ($case === 'store_paid_status' ? 'paid-by-store' : 'processing'));
        $order->shouldReceive('is_paid')->andReturn($case !== 'unpaid');
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($case === 'missing_paid_date' ? null : new \stdClass());
        $order->shouldReceive('get_refunds')->andReturn($case === 'missing_native_refund' ? [] : [new \stdClass()]);
        $order->shouldReceive('get_total_refunded')->andReturn($case === 'native_balance' ? 4.0 : ($case === 'zero_balance' ? 0.0 : 5.0));
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $response = $this->response();
        $response['payments'][0]['state'] = 'approved';
        $response['refund_context']['refunded_amount'] = '5.00';
        $response['observed'] = ['order_status' => 'processed', 'payment_status' => 'processed',
            'order_status_detail' => 'partially_refunded', 'payment_status_detail' => 'partially_refunded'];
        switch ($case) {
            case 'seller': $response['refund_context']['seller_reference'] = 'OTHER'; break;
            case 'attempt': $response['refund_context']['attempt_reference'] = 'WC-999'; break;
            case 'order': $response['order_reference'] = 'PPORDOTHER'; break;
            case 'payment': $response['payments'][0]['reference'] = 'PPPAYOTHER'; break;
            case 'contract': $response['refund_context']['contract_version'] = 'unknown'; break;
            case 'amount': $response['payments'][0]['amount']['value'] = '10.00'; break;
            case 'currency': $response['refund_context']['amount']['currency'] = 'USD'; break;
            case 'full_refund': $response['refund_context']['refunded_amount'] = '20.00'; break;
            case 'state': $response['payments'][0]['state'] = 'refunded'; break;
            case 'detail': $response['observed']['payment_status_detail'] = 'accredited'; break;
            case 'failure': $response['outcome'] = 'failure'; break;
        }

        for ($delivery = 0; $delivery < 2; $delivery++) {
            $result = $reconciler->confirmPartial($order, 'SYNTHETIC_KEY', $response);
            $this->assertSame($expected, $result['outcome']);
            $this->assertSame($expected === 'no_change' ? 'refund_already_applied' : 'refund_reconciliation_unavailable', $result['reason']);
        }
    }

    public static function partialConfirmationProvider(): array
    {
        $cases = ['matching' => ['matching', 'no_change'], 'store_paid_status' => ['store_paid_status', 'no_change']];
        foreach (['seller', 'attempt', 'order', 'payment', 'contract', 'amount', 'currency', 'full_refund',
            'state', 'detail', 'failure', 'native_balance', 'zero_balance', 'unpaid_status', 'unpaid',
            'missing_paid_date', 'missing_native_refund'] as $case) {
            $cases[$case] = [$case, 'uncertain'];
        }
        return $cases;
    }

    /** @dataProvider invalidPartialEnvelopeProvider */
    public function testPartialRefundFactsCannotEnterTheApprovalPathWithoutTheirValidatedContext(string $case): void
    {
        [, , , $metadata, $seller] = $this->context();
        $response = $this->response();
        $response['payments'][0]['state'] = 'approved';
        $response['observed'] = ['order_status' => 'processed', 'payment_status' => 'processed',
            'order_status_detail' => 'partially_refunded', 'payment_status_detail' => 'partially_refunded'];
        if ($case === 'missing_context') {
            unset($response['refund_context']);
        } elseif ($case === 'invalid_context') {
            $response['refund_context'] = 'invalid';
        } else {
            $response['observed']['payment_status_detail'] = 'accredited';
        }
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire', 'start', 'complete');
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);

        $this->assertSame('unsupported', $applicator->apply(321, 'SYNTHETIC_KEY', $response, 'notification')['outcome']);
    }

    public static function invalidPartialEnvelopeProvider(): array
    {
        return [['missing_context'], ['invalid_context'], ['mixed_detail']];
    }

    public function testAGatewayRefundStillInFlightBlocksTheImportBeforeReservation(): void
    {
        [$reconciler, $order, $operations] = $this->context([$this->nativeRefund('1', false)]);
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(5.0);
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame(['outcome' => 'uncertain', 'reason' => 'refund_reconciliation_unavailable', 'status' => null],
            $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static function (): bool {
                throw new \RuntimeException('A provisional refund must not authorize a native effect.');
            }));
    }

    /** @dataProvider unreadableRefundListProvider */
    public function testAnUnreadableRefundListBlocksTheImportBeforeReservation(array $refunds): void
    {
        [$reconciler, $order, $operations] = $this->context($refunds);
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0);
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame('uncertain', $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true)['outcome']);
    }

    public static function unreadableRefundListProvider(): array
    {
        return ['not a refund object' => [[new \stdClass()]]];
    }

    public function testAFailedRefundQueryBlocksTheImportBeforeReservation(): void
    {
        [$reconciler, $order, $operations] = $this->context(null);
        WP_Mock::userFunction('wc_get_orders', ['return' => false]);
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0);
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame('uncertain', $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true)['outcome']);
    }

    public function testARefundThatStartsAfterTheReservationIsRetryableAndCreatesNothing(): void
    {
        [$reconciler, $order, $operations] = $this->context(null);
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0, 5.0);
        // The admin refund appears between the first query and the post-reservation reload.
        WP_Mock::userFunction('wc_get_orders', [
            'args' => [['type' => 'shop_order_refund', 'parent' => 321, 'limit' => -1]],
            'return_in_order' => [[], [$this->nativeRefund('1', false)]],
        ]);
        $operations->shouldReceive('reserve')->once()->andReturn(['dispatch' => 'yes']);
        $operations->shouldReceive('finish')->once()->with($order, ['dispatch' => 'yes'], 'read_failed');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $this->assertSame(['outcome' => 'uncertain', 'reason' => 'refund_reconciliation_unavailable', 'status' => null],
            $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true));
    }

    /** @dataProvider settledRefundProvider */
    public function testSettledOrUnstampedRefundsDoNotBlockTheMissingAmount(string $inFlight, bool $refundedPayment): void
    {
        [$reconciler, $order, $operations] = $this->context([$this->nativeRefund($inFlight, $refundedPayment)]);
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing', 'processing', 'processing', 'refunded');
        $order->shouldReceive('get_total_refunded')->andReturn(5.0, 20.0);
        $operations->shouldReceive('reserve')->once()->andReturn(['dispatch' => 'yes']);
        $operations->shouldReceive('finish')->once()->with($order, ['dispatch' => 'yes'], 'succeeded');
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(155);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(321);
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-15.00');
        WP_Mock::userFunction('wc_create_refund', ['times' => 1, 'return' => $refund]);

        $this->assertSame(['outcome' => 'applied', 'reason' => 'native_refund_verified', 'status' => 'refunded'],
            $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true));
    }

    public static function settledRefundProvider(): array
    {
        return ['gateway refund confirmed' => ['1', true], 'manual bookkeeping refund' => ['', false]];
    }

    public function testExistingNativeFullRefundNeverReservesOrCreatesAnotherRefund(): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('refunded');
        $order->shouldReceive('get_total_refunded')->andReturn(20.0);
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('no_change', $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static function (): bool {
            throw new \RuntimeException('No native effect may start on a replay.');
        })['outcome']);
    }

    /** @dataProvider invalidContextProvider */
    public function testUncorrelatedOrIncompleteRefundCannotReachNativeEffects(string $case): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $response = $this->response();
        switch ($case) {
            case 'seller': $response['refund_context']['seller_reference'] = 'OTHER'; break;
            case 'order': $response['order_reference'] = 'PPORDOTHER'; break;
            case 'payment': $response['payments'][0]['reference'] = 'PPPAYOTHER'; break;
            case 'attempt': $response['refund_context']['attempt_reference'] = 'WC-999'; break;
            case 'partial': $response['refund_context']['refunded_amount'] = '5.00'; break;
            case 'amount': $response['payments'][0]['amount']['value'] = '10.00'; break;
            case 'currency': $response['refund_context']['amount']['currency'] = 'USD'; break;
            case 'contract': $response['refund_context']['contract_version'] = 'unknown'; break;
            case 'state': $response['payments'][0]['state'] = 'approved'; break;
        }
        $operations->shouldNotReceive('reserve', 'finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('uncertain', $reconciler->apply($order, 'SYNTHETIC_KEY', $response, static function (): bool {
            throw new \RuntimeException('Unverified data must not authorize a refund.');
        })['outcome']);
    }

    public static function invalidContextProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['seller', 'order', 'payment', 'attempt', 'partial', 'amount', 'currency', 'contract', 'state']);
    }

    public function testAnotherFinancialOperationBlocksTheReconciliation(): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $operations->shouldReceive('reserve')->once()->andThrow(new \RuntimeException('operation_unresolved'));
        $operations->shouldNotReceive('finish');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('uncertain', $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true)['outcome']);
    }

    public function testOwnershipFailureBeforeNativeExecutionRemainsSafeToReadAgain(): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0);
        $operations->shouldReceive('reserve')->once()->andReturn(['dispatch' => 'yes']);
        $operations->shouldReceive('finish')->once()->with($order, ['dispatch' => 'yes'], 'read_failed');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $this->assertSame('uncertain', $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => false)['outcome']);
    }

    public function testFailedNativeRefundRemainsUncertainAndDoesNotClaimSuccess(): void
    {
        [$reconciler, $order, $operations] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0);
        $operations->shouldReceive('reserve')->once()->andReturn(['dispatch' => 'yes']);
        $operations->shouldReceive('finish')->once()->with($order, ['dispatch' => 'yes'], 'uncertain');
        WP_Mock::userFunction('wc_create_refund', ['times' => 1, 'return' => static function () { throw new \RuntimeException('PRIVATE_SENTINEL'); }]);
        $result = $reconciler->apply($order, 'SYNTHETIC_KEY', $this->response(), static fn(): bool => true);
        $this->assertSame('uncertain', $result['outcome']);
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($result));
    }

    public function testUnreadablePartialRefundContextIsUncertainWithoutOwnershipOrNativeEffects(): void
    {
        [, , , $metadata, $seller] = $this->context();
        $response = $this->response();
        $response['payments'][0]['state'] = 'approved';
        $response['refund_context']['refunded_amount'] = '5.00';
        $response['observed'] = ['order_status' => 'processed', 'payment_status' => 'processed',
            'order_status_detail' => 'partially_refunded', 'payment_status_detail' => 'partially_refunded'];
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire', 'start', 'complete');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => static function () {
            throw new \RuntimeException('PRIVATE_SENTINEL');
        }]);
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership);

        $result = $applicator->apply(321, 'SYNTHETIC_KEY', $response, 'notification');

        $this->assertSame(['outcome' => 'uncertain', 'reason' => 'refund_reconciliation_unavailable', 'status' => null], $result);
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($result));
    }

    /** @dataProvider unstartedFullRefundOwnershipProvider */
    public function testFullRefundWithoutAVerifiedOwnershipStartNeverCreatesANativeRefund(string $claim): void
    {
        [$reconciler, $order, $operations, $metadata, $seller] = $this->context();
        $order->shouldReceive('get_status')->with('edit')->andReturn('processing');
        $order->shouldReceive('get_total_refunded')->andReturn(0.0);
        $operations->shouldReceive('reserve')->once()->andReturn(['dispatch' => 'yes']);
        $operations->shouldReceive('finish')->once()->with($order, ['dispatch' => 'yes'], 'read_failed');
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        $owner = str_repeat('a', 64);
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('complete', 'markUncertain');
        if ($claim === 'completed') {
            $ownership->shouldReceive('acquire')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund')
                ->andReturn(['outcome' => 'completed', 'owner' => null]);
            $ownership->shouldNotReceive('start', 'releaseReserved');
        } else {
            $ownership->shouldReceive('acquire')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund')
                ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
            $ownership->shouldReceive('start')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund', $owner)->andReturn(false);
            $ownership->shouldReceive('releaseReserved')->once()->with(321, 'SYNTHETIC_KEY', 'record_full_refund', $owner)->andReturn(true);
        }
        $applicator = new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, null, null, null, $reconciler);

        $this->assertSame(['outcome' => 'uncertain', 'reason' => 'refund_reconciliation_unavailable', 'status' => null],
            $applicator->apply(321, 'SYNTHETIC_KEY', $this->response(), 'notification'));
    }

    public static function unstartedFullRefundOwnershipProvider(): array
    {
        return ['start not verified' => ['acquired'], 'completed tombstone' => ['completed']];
    }

    /** @param list<mixed>|null $refunds Native refunds of the direct in-flight query; null lets the test stub it. */
    private function context(?array $refunds = []): array
    {
        if ($refunds !== null) {
            WP_Mock::userFunction('wc_get_orders', [
                'args' => [['type' => 'shop_order_refund', 'parent' => 321, 'limit' => -1]], 'return' => $refunds,
            ]);
        }
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_total')->with('edit')->andReturn('20.00')->byDefault();
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('read_meta_data')->with(true);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order);
        $order->shouldReceive('get_data_store')->andReturn($store);
        $order->shouldNotReceive('payment_complete', 'update_status');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('PPORDSYNTHETIC');
        $metadata->shouldReceive('getPpPaymentId')->with($order)->andReturn('PPPAYSYNTHETIC');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('12345');
        $operations = Mockery::mock(SmartPixOperationRepository::class);
        $operations->shouldReceive('reconcileConfirmedFullRefund')->byDefault();
        $operations->shouldReceive('reconcileUncertainPartialImport')->byDefault();
        return [new SmartPixRefundReconciler($metadata, $seller, $operations), $order, $operations, $metadata, $seller];
    }

    /** @return WC_Order_Refund&\Mockery\MockInterface */
    private function nativeRefund(string $inFlight, bool $refundedPayment): WC_Order_Refund
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_meta')->with(SmartPixRefundContext::IN_FLIGHT_META_KEY, true, 'edit')->andReturn($inFlight);
        $refund->shouldReceive('get_refunded_payment')->with('edit')->andReturn($refundedPayment);

        return $refund;
    }

    private function response(): array
    {
        return [
            'outcome' => 'success', 'state' => 'terminal_success', 'order_reference' => 'PPORDSYNTHETIC',
            'eligibility' => null, 'authorization' => null, 'error' => null,
            'payments' => [['reference' => 'PPPAYSYNTHETIC', 'state' => 'refunded', 'amount' => ['value' => '20.00', 'currency' => 'BRL']]],
            'refund_context' => [
                'contract_version' => SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
                'seller_reference' => '12345', 'attempt_reference' => 'WC-321',
                'amount' => ['value' => '20.00', 'currency' => 'BRL'], 'refunded_amount' => '20.00',
            ],
        ];
    }
}

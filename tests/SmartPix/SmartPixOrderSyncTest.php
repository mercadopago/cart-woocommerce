<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderSync;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;

class SmartPixOrderSyncTest extends TestCase
{
    public function testPendingSyncDoesNotConsumeTheCronErrorBudgetOrApplyATerminalEffect(): void
    {
        [$metadata, $order] = $this->context();
        $sync = new SmartPixOrderSync($metadata, function (): array {
            return $this->result('processing');
        },
            static function (): array {
                throw new RuntimeException('Pending is not a terminal effect.');
            });
        $sync->synchronize($order);
        $this->assertSame('pending', $sync->paymentData($order)[0]['status']);
    }

    public function testAnExternalFullRefundReachesTheSharedApplicatorEvenBeforeNativeRefundExists(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '0.00');
        $result = $this->result('refunded');
        $result['refund_context'] = $result['order_context'];
        unset($result['order_context']);
        $applied = 0;
        $sync = new SmartPixOrderSync(
            $metadata,
            static function () use ($result): array {
                return $result;
            },
            function ($id, $key, $response) use ($result, &$applied): array {
                $this->assertSame($result, $response);
                $this->assertSame(321, $id);
                $applied++;
                return ['outcome' => 'applied'];
            }
        );
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $sync->synchronize($order);
        $this->assertSame(1, $applied);
        $this->assertSame('refunded', $sync->paymentData($order)[0]['status']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testDisplayCacheNeverSuppliesTheFinancialSyncDecision(): void
    {
        [$metadata, $order] = $this->context();
        $reads = 0;
        $applies = 0;
        $approved = $this->result('approved');
        $sync = new SmartPixOrderSync($metadata, function () use (&$reads, $approved): array {
            return ++$reads === 1 ? $this->result('processing') : $approved;
        }, function ($id, $key, $result, $source, $surface) use (&$applies, $approved): array {
            $this->assertSame([321, 'SYNTHETIC_KEY', $approved, 'internal', 'server'], [$id, $key, $result, $source, $surface]);
            $applies++;
            return ['outcome' => 'applied'];
        });
        $this->assertSame('pending', $sync->paymentData($order)[0]['status']);
        $this->assertSame('pending', $sync->paymentData($order)[0]['status']);
        $this->assertSame(1, $reads);
        $this->assertSame(0, $applies);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);

        $sync->synchronize($order);

        $this->assertSame(2, $reads);
        $this->assertSame(1, $applies);
        $this->assertSame('approved', $sync->paymentData($order)[0]['status']);
    }

    /** @dataProvider statusProvider */
    public function testDisplayReusesTheExistingAdminStatusCards(string $state, string $status, string $detail): void
    {
        [$metadata, $order] = $this->context();
        if ($state === 'refunded') {
            $this->nativeRefund($metadata, $order);
        }
        $sync = new SmartPixOrderSync($metadata, function () use ($state): array {
            return $this->result($state);
        }, static function (): array {
            throw new RuntimeException('Display must not apply payment effects.');
        });
        $this->assertSame([[
            'id' => 'PPPAYSYNTHETIC001', 'payment_type_id' => 'bank_transfer',
            'status' => $status, 'status_detail' => $detail,
        ]], $sync->paymentData($order));
    }

    public function testConfirmedFullRefundRefreshesWithoutAnotherFinancialEffect(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order);
        $reads = 0;
        $sync = new SmartPixOrderSync($metadata, function () use (&$reads): array {
            $reads++;
            return $this->result('refunded');
        }, static function (): array {
            throw new RuntimeException('A confirmed native refund must not reach financial effects.');
        });
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->twice();
        $order->shouldReceive('get_data_store')->twice()->andReturn($store);

        $this->assertSame('refunded', $sync->paymentData($order)[0]['status']);
        $sync->synchronize($order);
        $sync->synchronize($order);

        $this->assertSame(3, $reads);
        $this->assertSame('refunded', $sync->paymentData($order)[0]['status']);
    }

    /** @dataProvider paidStatusProvider */
    public function testMatchingPartialRefundShowsTheAdminStatusAndSyncsWithoutAnotherFinancialEffect(string $status): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, $status, '5.00');
        // WooCommerce reports both native and store-configured paid statuses as paid.
        $order->shouldReceive('is_paid')->andReturn(true);
        $result = $this->partialRefundResult();
        $reads = 0;
        $sync = new SmartPixOrderSync($metadata, static function () use ($result, &$reads): array {
            $reads++;
            return $result;
        }, static function (): array {
            throw new RuntimeException('An existing partial refund must not reach financial effects.');
        });
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);

        $this->assertSame('partially_refunded', $sync->paymentData($order)[0]['status_detail']);
        $sync->synchronize($order);
        $this->assertSame('approved', $sync->paymentData($order)[0]['status']);
        $this->assertSame('partially_refunded', $sync->paymentData($order)[0]['status_detail']);
        $this->assertSame(2, $reads);
    }

    public static function paidStatusProvider(): array
    {
        return ['native paid status' => ['processing'], 'store paid status' => ['paid-by-store']];
    }

    /** Given an external partial, when displaying admin, then keep sync visible without effects. */
    public function testMissingExternalPartialStaysVisibleAndOnlyExplicitSyncImportsIt(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '0.30');
        $native = 0.30;
        $order->shouldReceive('get_total_refunded')->andReturnUsing(static function () use (&$native): float {
            return $native;
        });
        $result = $this->partialRefundResult();
        $result['order_context']['refunded_amount'] = '0.60';
        $applications = [];
        $sync = new SmartPixOrderSync(
            $metadata,
            static function () use ($result): array {
                return $result;
            },
            static function ($id, $key, $response, $source, $surface) use (&$native, &$applications): array {
                $applications[] = [$id, $key, $response, $source, $surface];
                $native = 0.60;
                return ['outcome' => 'applied'];
            }
        );
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->times(3);
        $order->shouldReceive('get_data_store')->times(3)->andReturn($store);
        $this->assertSame('partially_refunded', $sync->paymentData($order)[0]['status_detail']);
        $this->assertSame([], $applications);
        $sync->synchronize($order);
        $sync->synchronize($order);
        $this->assertCount(1, $applications);
        $this->assertSame([321, 'SYNTHETIC_KEY'], array_slice($applications[0], 0, 2));
        $this->assertSame($result['order_context'], $applications[0][2]['refund_context']);
        $this->assertArrayNotHasKey('order_context', $applications[0][2]);
        $this->assertSame(['internal', 'server'], array_slice($applications[0], 3));
        $this->assertSame('approved', $sync->paymentData($order)[0]['status']);
    }

    public function testAnAppliedImportThatLeavesTheNativeRefundBalanceBehindCannotBeReportedAsSuccessfulSync(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '0.30');
        $order->shouldReceive('get_total_refunded')->andReturn(0.30);
        $result = $this->partialRefundResult();
        $result['order_context']['refunded_amount'] = '0.60';
        $applications = 0;
        $sync = new SmartPixOrderSync(
            $metadata,
            static function () use ($result): array {
                return $result;
            },
            static function () use (&$applications): array {
                // The applicator claims success, but the native refund balance never reaches 0.60.
                ++$applications;
                return ['outcome' => 'applied'];
            }
        );
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->twice();
        $order->shouldReceive('get_data_store')->twice()->andReturn($store);

        try {
            $sync->synchronize($order);
            $this->fail('A native refund balance that disagrees with the provider cannot be a successful sync.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Smart Pix synchronization could not be confirmed.', $failure->getMessage());
        }
        $this->assertSame(1, $applications);
    }

    public function testFirstExternalPartialCanBeDisplayedWithOrdinaryRefundContext(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '0.00');
        $result = $this->partialRefundResult();
        $result['refund_context'] = $result['order_context'];
        unset($result['order_context']);
        $sync = new SmartPixOrderSync(
            $metadata,
            static function () use ($result): array {
                return $result;
            },
            static function (): array {
                throw new RuntimeException('Display must stay read-only.');
            }
        );
        $this->assertSame('partially_refunded', $sync->paymentData($order)[0]['status_detail']);
    }

    public function testUncertainImportCannotBeReportedAsSuccessfulSync(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '0.00');
        $result = $this->partialRefundResult();
        $sync = new SmartPixOrderSync(
            $metadata,
            static function () use ($result): array {
                return $result;
            },
            static function (): array {
                return ['outcome' => 'uncertain'];
            }
        );
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $this->expectException(RuntimeException::class);
        $sync->synchronize($order);
    }

    public function testAProviderFullRefundAfterANativePartialRefundReachesTheApplicator(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '5.00');
        $result = $this->result('refunded');
        $applied = [];
        $sync = new SmartPixOrderSync($metadata, static function () use ($result): array {
            return $result;
        }, static function ($id, $key, $response) use (&$applied): array {
            $applied[] = $response;
            return ['outcome' => 'applied'];
        });
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_data_store')->once()->andReturn($store);

        $sync->synchronize($order);

        $this->assertCount(1, $applied);
        $this->assertSame($result['order_context'], $applied[0]['refund_context']);
        $this->assertArrayNotHasKey('order_context', $applied[0]);
    }

    /** @dataProvider inconsistentPartialRefundProvider */
    public function testPartialRefundMismatchHidesTheStatusAndCannotSync(string $nativeAmount, string $providerAmount, string $paymentReference): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', $nativeAmount);
        $result = $this->partialRefundResult();
        $result['order_context']['refunded_amount'] = $providerAmount;
        $result['payments'][0]['reference'] = $paymentReference;
        $sync = new SmartPixOrderSync($metadata, static function () use ($result): array {
            return $result;
        }, static function (): array {
            throw new RuntimeException('A mismatched refund must not reach financial effects.');
        });
        try {
            $sync->paymentData($order);
            $this->fail('A mismatched refund must not be displayed as synchronized.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Smart Pix synchronization could not be confirmed.', $failure->getMessage());
        }
    }

    public static function inconsistentPartialRefundProvider(): array
    {
        return [
            ['5.00', '4.00', 'PPPAYSYNTHETIC001'],
            ['5.00', '20.00', 'PPPAYSYNTHETIC001'],
            ['5.00', '5.00', 'PPPAYOTHER001'],
        ];
    }

    public function testNativePartialRefundCannotDisplayAStaleAccreditedProviderRead(): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, 'processing', '5.00');
        $sync = new SmartPixOrderSync($metadata, function (): array {
            return $this->result('approved');
        }, static function (): array {
            throw new RuntimeException('Stale provider facts must not reach financial effects.');
        });

        $this->expectException(RuntimeException::class);
        $sync->paymentData($order);
    }

    /** @dataProvider inconsistentRefundProvider */
    public function testRefundMismatchCannotBeAcknowledged(string $nativeStatus, string $nativeAmount, string $providerAmount): void
    {
        [$metadata, $order] = $this->context();
        $this->nativeRefund($metadata, $order, $nativeStatus, $nativeAmount);
        $result = $this->result('refunded');
        $result['order_context']['refunded_amount'] = $providerAmount;
        $sync = new SmartPixOrderSync($metadata, static function () use ($result): array {
            return $result;
        }, static function (): array {
            throw new RuntimeException('An incomplete refund must not reach financial effects.');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Smart Pix synchronization could not be confirmed.');
        $sync->synchronize($order);
    }

    public static function inconsistentRefundProvider(): array
    {
        return [
            ['processing', '20.00', '20.00'], ['refunded', '10.00', '20.00'],
            ['refunded', '20.00', '10.00'], ['refunded', '0.00', '20.00'],
        ];
    }

    private function nativeRefund($metadata, $order, string $status = 'refunded', string $amount = '20.00'): void
    {
        $metadata->shouldReceive('getPpPaymentId')->with($order)->andReturn('PPPAYSYNTHETIC001');
        $order->shouldReceive('get_status')->with('edit')->andReturn($status);
        // WooCommerce default paid statuses.
        $order->shouldReceive('is_paid')->andReturn(in_array($status, ['processing', 'completed'], true))->byDefault();
        $order->shouldReceive('get_total_refunded')->andReturn((float) $amount)->byDefault();
    }

    public static function statusProvider(): array
    {
        return [
            ['processing', 'pending', 'pending_waiting_payment'], ['approved', 'approved', 'accredited'],
            ['rejected', 'rejected', 'generic'], ['cancelled', 'canceled', 'canceled'],
            ['refunded', 'refunded', 'refunded'],
        ];
    }

    /** @dataProvider invalidResultProvider */
    public function testUncorrelatedOrInvalidFactsCannotReachTheApplicator(string $case): void
    {
        [$metadata, $order] = $this->context();
        $result = $this->result('approved');
        switch ($case) {
            case 'other_order':
                $result['order_reference'] = 'PPORDOTHER001';
                break;
            case 'wrong_amount':
                $result['payments'][0]['amount']['value'] = '20.01';
                break;
            case 'wrong_currency':
                $result['payments'][0]['amount']['currency'] = 'USD';
                break;
            case 'multiple_payments':
                $result['payments'][] = $result['payments'][0];
                break;
            case 'missing_payments':
                $result['payments'] = [];
                break;
            case 'provider_order_as_payment':
                $result['payments'][0]['reference'] = 'ORDSYNTHETIC001';
                break;
            case 'unknown_state':
                $result['payments'][0]['state'] = 'unknown';
                break;
            case 'failed_read':
                $result['outcome'] = 'failure';
                $result['state'] = 'processing';
                break;
        }
        $applied = false;
        $sync = new SmartPixOrderSync($metadata, static function () use ($result): array {
            return $result;
        }, static function () use (&$applied): array {
            $applied = true;
            return ['outcome' => 'applied'];
        });
        try {
            $sync->synchronize($order);
            $this->fail('Invalid provider facts must not synchronize.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Smart Pix synchronization could not be confirmed.', $failure->getMessage());
        }
        $this->assertFalse($applied);
    }

    public static function invalidResultProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'other_order', 'wrong_amount', 'wrong_currency', 'multiple_payments', 'missing_payments',
            'provider_order_as_payment', 'unknown_state', 'failed_read',
        ]);
    }

    /** @dataProvider unconfirmedOutcomeProvider */
    public function testUnconfirmedNativeApplicationCannotReportSyncSuccess(string $outcome): void
    {
        [$metadata, $order] = $this->context();
        $order->shouldNotReceive('get_data_store');
        $sync = new SmartPixOrderSync($metadata, function (): array {
            return $this->result('approved');
        }, static function () use ($outcome): array {
            return ['outcome' => $outcome];
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Smart Pix synchronization could not be confirmed.');
        $sync->synchronize($order);
    }

    public static function unconfirmedOutcomeProvider(): array
    {
        return [['uncertain'], ['conflict'], ['busy'], ['unsupported']];
    }

    public function testReadErrorsDoNotExposeProviderOrDatabaseMessages(): void
    {
        [$metadata, $order] = $this->context();
        $sync = new SmartPixOrderSync($metadata, static function (): array {
            throw new \Error('SYNTHETIC_PRIVATE_PROVIDER_MESSAGE');
        }, static function (): array {
            throw new RuntimeException('Must not reach the applicator.');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Smart Pix synchronization could not be confirmed.');
        $sync->paymentData($order);
    }

    private function context(): array
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_total')->with('edit')->andReturn('20.00');
        $order->shouldReceive('get_total_refunded')->byDefault()->andReturn(0.0);
        $order->shouldNotReceive('payment_complete', 'update_status', 'save');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('PPORDSYNTHETIC001');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        return [$metadata, $order];
    }

    private function result(string $state): array
    {
        $result = [
            'outcome' => in_array($state, ['rejected', 'cancelled'], true) ? 'failure' : 'success',
            'state' => $state === 'processing' ? 'processing' : (in_array($state, ['rejected', 'cancelled'], true) ? 'terminal_failure' : 'terminal_success'),
            'order_reference' => 'PPORDSYNTHETIC001',
            'payments' => [['reference' => 'PPPAYSYNTHETIC001', 'state' => $state, 'amount' => ['value' => '20.00', 'currency' => 'BRL']]],
        ];
        if ($state === 'refunded') {
            $result['order_context'] = [
                'contract_version' => \MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
                'amount' => ['value' => '20.00', 'currency' => 'BRL'], 'refunded_amount' => '20.00',
            ];
        }
        return $result;
    }

    private function partialRefundResult(): array
    {
        $result = $this->result('approved');
        $result['observed'] = [
            'order_status' => 'processed', 'order_status_detail' => 'partially_refunded',
            'payment_status' => 'processed', 'payment_status_detail' => 'partially_refunded',
            'total_failed' => null,
        ];
        $result['order_context'] = [
            'contract_version' => \MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor::SNAPSHOT_CONTRACT,
            'amount' => ['value' => '20.00', 'currency' => 'BRL'], 'refunded_amount' => '5.00',
        ];
        return $result;
    }
}

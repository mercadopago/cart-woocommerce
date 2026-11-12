<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixRefundReconciler;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;
use WC_Order_Refund;

class SmartPixOperationRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPersistsAndVerifiesBeforeReturningDispatchOwnership(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $encoded = null;
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->once()->ordered()->andReturn('');
        // Capture the actual serialized write; this does not implement repository business rules.
        $order->shouldReceive('update_meta_data')->once()->ordered()->with(
            SmartPixOperationRepository::META_KEY,
            Mockery::on(function ($value) use (&$encoded): bool {
                $encoded = $value;
                return is_string($value);
            })
        );
        $order->shouldReceive('save_meta_data')->once()->ordered();
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->once()->ordered()->andReturnUsing(function () use (&$encoded) {
                return $encoded;
            });
        $order->shouldNotReceive('save');
        $database = new SmartPixStatusLockDatabase();
        $repository = new SmartPixOperationRepository(new SmartPixStatusLock($database, 'operation_'));
        $result = $repository->reserve($order, 'refund_42', $this->context());
        $this->assertSame('yes', $result['dispatch']);
        $this->assertSame('dispatching', $result['status']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['key']);
        $this->assertSame([], $database->rows);
    }

    public function testSameOperationKeepsItsKeyWithoutAnotherWrite(): void
    {
        $record = $this->record('succeeded');
        $order = $this->orderWith([$record['id'] => $record]);
        $order->shouldNotReceive('update_meta_data');
        $order->shouldNotReceive('save_meta_data');
        $result = $this->repository()->reserve($order, 'refund_42', $this->context());
        $this->assertSame('no', $result['dispatch']);
        $this->assertSame($record['key'], $result['key']);
    }

    public function testConfirmedFullRefundResolvesTheFinancialUncertaintyWithoutReopeningItsDispatch(): void
    {
        $record = $this->record('uncertain');
        $encoded = json_encode([$record['id'] => $record]);
        $order = $this->mutableJournalOrder($encoded);
        $order->shouldReceive('get_total_refunded')->once()->andReturn(100.0);
        $order->shouldReceive('get_refunds')->once()->andReturn([]);
        $order->shouldReceive('update_meta_data')->once()->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->once();
        $context = $this->context();
        $context['amount'] = $context['total'];
        $repository = $this->repository();

        $repository->reconcileConfirmedFullRefund($order, $context);
        $result = $repository->reserve($order, 'refund_42', $this->context());

        $this->assertSame('reconciled', $result['status']);
        $this->assertSame('no', $result['dispatch']);
        $this->assertSame($record['key'], $result['key']);
        $this->assertSame('reconciled', json_decode($encoded, true)[$record['id']]['status']);
    }

    /** An admin partial of 30 left uncertain must not block a provider full refund of 100 forever. */
    public function testConfirmedFullRefundResolvesAnUncertainRefundOfAnotherAmount(): void
    {
        $record = $this->record('uncertain');
        $record['amount'] = '30.00';
        $encoded = json_encode([$record['id'] => $record]);
        $order = $this->mutableJournalOrder($encoded);
        // WooCommerce deleted the failed admin refund: nothing native is refunded yet.
        $order->shouldReceive('get_total_refunded')->once()->andReturn(0.0);
        $order->shouldReceive('get_refunds')->once()->andReturn([]);
        $order->shouldReceive('update_meta_data')->once()->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->once();
        $context = $this->context();
        $context['amount'] = $context['total'];

        $this->repository()->reconcileConfirmedFullRefund($order, $context);

        $records = json_decode($encoded, true);
        $this->assertSame('reconciled', $records[$record['id']]['status']);
        $this->assertSame('30.00', $records[$record['id']]['amount']);
    }

    /** @dataProvider unsafeFullRefundRecoveryProvider */
    public function testFullRefundRecoveryCannotResolveUnrelatedOrExecutingOperations(string $case): void
    {
        $record = $this->record('uncertain');
        $context = $this->context();
        $context['amount'] = $context['total'];
        $refunds = [];
        $native = 100.0;
        switch ($case) {
            case 'dispatching':
                $record['status'] = 'dispatching';
                break;
            case 'cancel':
                $record['operation'] = 'cancel';
                break;
            case 'seller':
                $record['seller'] = 'OTHER';
                break;
            case 'attempt':
                $record['attempt_key'] = 'OTHER_ATTEMPT';
                break;
            case 'order':
                $record['order_reference'] = 'OTHER_ORDER';
                break;
            case 'currency':
                $record['currency'] = 'USD';
                break;
            case 'native effect':
                $record['id'] = hash('sha256', 'SYNTHETIC_ATTEMPT|provider_full_refund');
                $record['amount'] = '125.50';
                $native = 0.0;
                break;
            case 'provisional native refund':
                $refund = Mockery::mock(WC_Order_Refund::class);
                $refund->shouldReceive('get_id')->andReturn(42);
                $refunds = [$refund];
                break;
        }
        $records = [$record['id'] => $record];
        if ($case === 'multiple uncertain') {
            $second = $record;
            $second['id'] = hash('sha256', 'SYNTHETIC_ATTEMPT|refund_43');
            $records[$second['id']] = $second;
        }
        $encoded = json_encode($records);
        $before = $encoded;
        $order = $this->mutableJournalOrder($encoded);
        $order->shouldReceive('get_total_refunded')->andReturn($native);
        $order->shouldReceive('get_refunds')->andReturn($refunds);
        $order->shouldNotReceive('update_meta_data', 'save_meta_data');

        try {
            $this->repository()->reconcileConfirmedFullRefund($order, $context);
            $this->fail('Unverified operation must remain blocked.');
        } catch (RuntimeException $failure) {
            $this->assertContains($failure->getMessage(), ['operation_unresolved', 'operation_context_conflict']);
        }
        $this->assertSame($before, $encoded);
    }

    public static function unsafeFullRefundRecoveryProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'dispatching', 'cancel', 'seller', 'attempt', 'order', 'currency',
            'native effect', 'provisional native refund', 'multiple uncertain',
        ]);
    }

    public function testPartialProviderRefundCannotUseTheFullRefundRecovery(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data', 'update_meta_data');
        $this->expectExceptionMessage('operation_context_conflict');
        $this->repository()->reconcileConfirmedFullRefund($order, $this->context());
    }

    /** @dataProvider unresolvedProvider */
    public function testDifferentRefundCannotBypassUnresolvedOperation(string $status): void
    {
        $record = $this->record($status);
        $order = $this->orderWith([$record['id'] => $record]);
        $order->shouldNotReceive('update_meta_data');
        $this->expectExceptionMessage('operation_unresolved');
        $this->repository()->reserve($order, 'refund_43', $this->context());
    }

    public static function unresolvedProvider(): array
    {
        return [['dispatching'], ['uncertain']];
    }

    public function testChangedAmountCannotReuseACompletedOperationIdentity(): void
    {
        $record = $this->record('succeeded');
        $order = $this->orderWith([$record['id'] => $record]);
        $context = $this->context();
        $context['amount'] = '30.00';
        $this->expectExceptionMessage('operation_context_conflict');
        $this->repository()->reserve($order, 'refund_42', $context);
    }

    public function testPersistenceMismatchPreventsOutboundOwnership(): void
    {
        $order = $this->orderWith([]);
        $order->shouldReceive('update_meta_data')->once();
        $order->shouldReceive('save_meta_data')->once();
        $this->expectExceptionMessage('operation_persistence_failed');
        $this->repository()->reserve($order, 'refund_42', $this->context());
    }

    public function testCorruptedJournalFailsClosed(): void
    {
        $order = $this->orderWith(['invalid' => []]);
        $order->shouldNotReceive('save_meta_data');
        $this->expectExceptionMessage('operation_journal_invalid');
        $this->repository()->reserve($order, 'refund_42', $this->context());
    }

    public function testBusyShortLeaseDoesNotReadOrWriteJournal(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $lock = new SmartPixStatusLock($database, 'operation_');
        $lock->acquire(321);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldNotReceive('read_meta_data');
        $this->expectExceptionMessage('operation_busy');
        (new SmartPixOperationRepository($lock))->reserve($order, 'refund_42', $this->context());
    }

    public function testOnlyThePersistedOwnerCanFinish(): void
    {
        $record = $this->record('dispatching');
        $order = $this->orderWith([$record['id'] => $record]);
        $order->shouldNotReceive('update_meta_data');
        $reservation = $record;
        $reservation['key'] = str_repeat('c', 64);
        $this->expectExceptionMessage('operation_ownership_lost');
        $this->repository()->finish($order, $reservation, 'succeeded');
    }

    /** @dataProvider recoverableCancellationProvider */
    public function testCancellationRecoveryAcquiresANewOwnerWithoutReauthorizingAnUnknownDispatch(string $status, string $dispatch): void
    {
        $context = $this->context();
        $context['operation'] = 'cancel';
        $context['amount'] = '0.00';
        $id = hash('sha256', 'SYNTHETIC_ATTEMPT|cancel');
        $record = $context + ['id' => $id, 'key' => str_repeat('a', 64), 'status' => $status];
        $encoded = json_encode([$id => $record]);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$encoded): string {
                return $encoded;
            });
        $order->shouldReceive('update_meta_data')->once()->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->once();
        $order->shouldNotReceive('save');
        $repository = $this->repository();
        $result = $repository->reserve($order, 'cancel', $context);
        $this->assertSame($dispatch, $result['dispatch']);
        $this->assertSame('dispatching', $result['status']);
        $this->assertNotSame($record['key'], $result['key']);
        $this->assertCount(1, json_decode($encoded, true));
        // Then the old owner cannot finish the recovery or erase the new owner's reservation.
        $this->expectExceptionMessage('operation_ownership_lost');
        $repository->finish($order, $record, 'succeeded');
    }

    public static function recoverableCancellationProvider(): array
    {
        return [['read_failed', 'yes'], ['rejected', 'read_only']];
    }

    public function testRejectedRefundCannotAcquireReadOnlyCancellationRecovery(): void
    {
        $record = $this->record('rejected');
        $order = $this->orderWith([$record['id'] => $record]);
        $order->shouldNotReceive('update_meta_data', 'save_meta_data');
        $result = $this->repository()->reserve($order, 'refund_42', $this->context());
        $this->assertSame('no', $result['dispatch']);
        $this->assertSame($record['key'], $result['key']);
    }

    /** @dataProvider nonRetryableCancellationProvider */
    public function testAmbiguousOrDispatchedCancellationCannotReserveAgain(string $status): void
    {
        $context = $this->context();
        $context['operation'] = 'cancel';
        $context['amount'] = '0.00';
        $id = hash('sha256', 'SYNTHETIC_ATTEMPT|cancel');
        $record = $context + ['id' => $id, 'key' => str_repeat('a', 64), 'status' => $status];
        $order = $this->orderWith([$id => $record]);
        $order->shouldNotReceive('update_meta_data');
        $result = $this->repository()->reserve($order, 'cancel', $context);
        $this->assertSame('no', $result['dispatch']);
        $this->assertSame($record['key'], $result['key']);
    }

    public static function nonRetryableCancellationProvider(): array
    {
        return [['uncertain'], ['dispatching']];
    }

    /** @dataProvider partialImportRecoveryProvider */
    public function testUncertainPartialImportIsResolvedOnlyFromItsDurableMarker(string $case, ?string $expected): void
    {
        $record = $this->partialImportRecord();
        $marked = $this->importedRefund($this->partialMarker());
        $native = 0.60;
        $refunds = [$this->importedRefund(''), $marked];
        $second = null;
        switch ($case) {
            case 'rolled back creation':
                $native = 0.30;
                $refunds = [$this->importedRefund('')];
                break;
            case 'unmarked refund':
                $refunds = [$this->importedRefund(''), $this->importedRefund('')];
                break;
            case 'marked refund below the target':
                $native = 0.45;
                break;
            case 'marker of another target':
                $refunds = [$this->importedRefund(''), $this->importedRefund(['attempt' => hash('sha256', 'SYNTHETIC_ATTEMPT'), 'target' => '0.90'])];
                $native = 0.30;
                $expected = 'read_failed';
                break;
            case 'unreadable native balance':
                $native = -1.0;
                break;
            case 'unexpected refund object':
                $refunds = [Mockery::mock()];
                break;
            case 'another seller':
                $record['seller'] = 'OTHER';
                break;
            case 'resolved before the lock':
                $second = $record;
                $second['status'] = 'succeeded';
                break;
        }
        $encoded = json_encode([$record['id'] => $record]);
        $before = $encoded;
        if ($second === null) {
            $order = $this->mutableJournalOrder($encoded);
        } else {
            // A concurrent delivery resolves the record between the lock-free check and the lock.
            $order = Mockery::mock(WC_Order::class);
            $order->shouldReceive('get_id')->andReturn(321);
            $order->shouldReceive('read_meta_data')->with(true);
            $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
                ->twice()->andReturn($encoded, json_encode([$second['id'] => $second]));
        }
        $order->shouldReceive('get_total_refunded')->andReturn($native);
        $order->shouldReceive('get_refunds')->andReturn($refunds);
        $order->shouldReceive('update_meta_data')->times($expected === null ? 0 : 1)
            ->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->times($expected === null ? 0 : 1);

        $this->repository()->reconcileUncertainPartialImport($order, $this->partialImportContext());

        if ($expected === null) {
            $this->assertSame($before, $encoded);
        } else {
            $records = json_decode($encoded, true);
            $this->assertSame($expected, $records[$record['id']]['status']);
            $this->assertSame($record['key'], $records[$record['id']]['key']);
        }
    }

    public static function partialImportRecoveryProvider(): array
    {
        return [
            'marked refund holds the target' => ['marked refund holds the target', 'succeeded'],
            'rolled back creation' => ['rolled back creation', 'read_failed'],
            'marker of another target' => ['marker of another target', 'read_failed'],
            'unmarked refund' => ['unmarked refund', null],
            'marked refund below the target' => ['marked refund below the target', null],
            'unreadable native balance' => ['unreadable native balance', null],
            'unexpected refund object' => ['unexpected refund object', null],
            'another seller' => ['another seller', null],
            'resolved before the lock' => ['resolved before the lock', null],
        ];
    }

    public function testPartialImportRecoveryIgnoresJournalsWithoutASingleUncertainImport(): void
    {
        $import = $this->partialImportRecord();
        $import['status'] = 'succeeded';
        $refund = $this->record('uncertain');
        $order = $this->orderWith([$import['id'] => $import, $refund['id'] => $refund]);
        $order->shouldNotReceive('get_refunds', 'get_total_refunded', 'update_meta_data', 'save_meta_data');
        $database = new SmartPixStatusLockDatabase();

        (new SmartPixOperationRepository(new SmartPixStatusLock($database, 'operation_')))
            ->reconcileUncertainPartialImport($order, $this->partialImportContext());

        $this->assertSame([], $database->rows);
    }

    public function testUncertainPartialImportIsAlsoResolvedByAFullRefundReadOfTheSameAttempt(): void
    {
        // The provider reached the full refund before the interrupted partial import was redelivered.
        $record = $this->partialImportRecord();
        $encoded = json_encode([$record['id'] => $record]);
        $order = $this->mutableJournalOrder($encoded);
        $order->shouldReceive('get_total_refunded')->andReturn(0.60);
        $order->shouldReceive('get_refunds')->andReturn([$this->importedRefund(''), $this->importedRefund($this->partialMarker())]);
        $order->shouldReceive('update_meta_data')->once()->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $order->shouldReceive('save_meta_data')->once();
        $context = $this->context();
        $context['amount'] = $context['total'];

        $this->repository()->reconcileUncertainPartialImport($order, $context);

        $this->assertSame('succeeded', json_decode($encoded, true)[$record['id']]['status']);
    }

    public function testPartialImportRecoveryRefusesAnotherOperationContext(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data', 'update_meta_data');
        $this->expectExceptionMessage('operation_context_conflict');
        $this->repository()->reconcileUncertainPartialImport($order, ['operation' => 'cancel'] + $this->context());
    }

    private function partialImportContext(): array
    {
        return ['operation' => 'native_partial_refund', 'attempt_key' => 'SYNTHETIC_ATTEMPT',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'attempt_reference' => 'WC-321', 'seller' => '12345',
            'total' => '125.50', 'amount' => '0.60', 'currency' => 'BRL'];
    }

    private function partialImportRecord(): array
    {
        return $this->partialImportContext() + ['id' => hash('sha256', 'SYNTHETIC_ATTEMPT|provider_partial_refund_0.60'),
            'key' => str_repeat('c', 64), 'status' => 'uncertain'];
    }

    private function partialMarker(): array
    {
        return ['attempt' => hash('sha256', 'SYNTHETIC_ATTEMPT'), 'target' => '0.60'];
    }

    /** @param mixed $marker */
    private function importedRefund($marker): WC_Order_Refund
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_meta')->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, true, 'edit')->andReturn($marker);
        return $refund;
    }

    private function repository(): SmartPixOperationRepository
    {
        return new SmartPixOperationRepository(new SmartPixStatusLock(new SmartPixStatusLockDatabase(), 'operation_'));
    }

    private function orderWith(array $records): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturn($records === [] ? '' : json_encode($records));
        return $order;
    }

    private function mutableJournalOrder(string &$encoded): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$encoded): string {
                return $encoded;
            });
        $order->shouldNotReceive('save');
        return $order;
    }

    private function context(): array
    {
        return ['operation' => 'refund', 'attempt_key' => 'SYNTHETIC_ATTEMPT', 'amount' => '25.50',
            'order_reference' => 'ORDER_SYNTHETIC_001', 'attempt_reference' => 'WC-321',
            'seller' => '12345', 'total' => '125.50', 'currency' => 'BRL'];
    }

    private function record(string $status): array
    {
        return $this->context() + ['id' => hash('sha256', 'SYNTHETIC_ATTEMPT|refund_42'), 'key' => str_repeat('a', 64), 'status' => $status];
    }
}

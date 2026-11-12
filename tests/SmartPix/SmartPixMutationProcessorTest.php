<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;
use WP_Mock;

class SmartPixMutationProcessorTest extends TestCase
{
    /** @dataProvider blockedDiagnosticProvider */
    public function testBlockedRefundExplainsItsBoundaryWithoutSendingAMutation(bool $unresolved): void
    {
        $this->reserve($unresolved ? 'no' : 'yes', $unresolved ? 'uncertain' : 'dispatching');
        if ($unresolved) {
            $this->client->shouldNotReceive('query');
            $this->operations->shouldNotReceive('finish');
        } else {
            $query = $this->response('query');
            $query['order_context']['seller_reference'] = 'OTHER_SYNTHETIC_SELLER';
            $this->client->shouldReceive('query')->once()->andReturn($query);
            $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        }
        $this->client->shouldNotReceive('refund');
        $diagnostics = [];
        $sink = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(function ($name, $value, $details, $message = '') use (&$diagnostics): void {
            if ($name === 'smart_pix_server_error') {
                $diagnostics[] = $message;
            }
        });
        $sink->shouldReceive('distribution');
        $telemetry = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry($sink);
        $result = $this->processor(false, $telemetry)->refund($this->order, 'refund_42', '25.50');
        $this->assertSame(['success' => false, 'reason' => $unresolved ? 'operation_unresolved' : 'invalid_response'], $result);
        $this->assertCount(1, $diagnostics);
        $this->assertStringContainsString($unresolved ? 'refund.operation_reservation' : 'refund.preflight_read', $diagnostics[0]);
        $this->assertStringContainsString($unresolved ? 'an earlier operation is not confirmed' : 'snapshot seller does not match', substr($diagnostics[0], 0, 180));
    }

    public static function blockedDiagnosticProvider(): array
    {
        return [[true], [false]];
    }

    private WC_Order $order;
    private OrderMetadata $metadata;
    private Seller $seller;
    private SmartPixOrderClient $client;
    private SmartPixOperationRepository $operations;
    private array $metrics = [];
    private array $approvals = [];
    private array $application = ['outcome' => 'applied', 'status' => 'cancelled'];

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->order = Mockery::mock(WC_Order::class);
        $this->order->shouldReceive('read_meta_data')->with(true)->byDefault();
        $dataStore = Mockery::mock();
        $dataStore->shouldReceive('read')->with($this->order)->byDefault();
        $this->order->shouldReceive('get_data_store')->andReturn($dataStore)->byDefault();
        $this->order->shouldReceive('get_id')->andReturn(321)->byDefault();
        $this->order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID)->byDefault();
        $this->order->shouldReceive('get_currency')->with('edit')->andReturn('BRL')->byDefault();
        $this->order->shouldReceive('get_total')->with('edit')->andReturn('125.50')->byDefault();
        $this->order->shouldReceive('get_status')->with('edit')->andReturn('processing')->byDefault();
        $this->order->shouldReceive('is_paid')->andReturn(true)->byDefault();
        $this->order->shouldReceive('get_date_paid')->with('edit')->andReturn(new \stdClass())->byDefault();
        $this->order->shouldReceive('get_refunds')->andReturn([])->byDefault();
        $this->order->shouldNotReceive('payment_complete');
        $this->order->shouldNotReceive('update_status');
        $this->order->shouldNotReceive('add_order_note');
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1')->byDefault();
        $this->metadata->shouldReceive('getIdempotencyKey')->andReturn('SYNTHETIC_ATTEMPT')->byDefault();
        $this->metadata->shouldReceive('getSmartPixAttemptReference')->andReturn('WC-321')->byDefault();
        $this->metadata->shouldReceive('getPpOrderId')->andReturn('ORDER_SYNTHETIC_001')->byDefault();
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getSiteId')->andReturn('MLB')->byDefault();
        $this->seller->shouldReceive('getCustIdFromAT')->andReturn('12345')->byDefault();
        $this->client = Mockery::mock(SmartPixOrderClient::class);
        $this->client->shouldNotReceive('create');
        $this->operations = Mockery::mock(SmartPixOperationRepository::class);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $this->order]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider amountProvider */
    public function testRefundUsesOrdersAndItsOwnPersistedKey(string $amount): void
    {
        $this->reserve();
        $this->client->shouldReceive('query')->once()->with($this->request())->andReturn($this->response('query'));
        $response = $this->response('refund');
        $response['payments'][0]['amount']['value'] = $amount;
        $response['order_context']['refunded_amount'] = $amount;
        $request = $this->request();
        $request['refund_amount'] = ['value' => $amount, 'currency' => 'BRL'];
        $request['operation_context'] = $this->mutationContext();
        $this->client->shouldReceive('refund')->once()->with($request)->andReturn($response);
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');
        $this->assertTrue($this->processor()->refund($this->order, 'refund_42', $amount)['success']);
        $this->assertSame([], $this->approvals);
        $this->assertCount(1, $this->metrics);
        $this->assertSame('smart_pix_server_processing_time', $this->metrics[0][0]);
    }

    public static function amountProvider(): array
    {
        return [['25.50'], ['125.50']];
    }

    public function testSuccessfulRefundPersistsTheConfirmedProviderStatusWithoutChangingItsIdentifiers(): void
    {
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $response = $this->response('refund');
        $response['order_context']['refunded_amount'] = '125.50';
        $response['payments'][0]['amount']['value'] = '125.50';
        $response['observed'] = [
            'order_status' => 'refunded', 'order_status_detail' => 'refunded',
            'payment_status' => 'refunded', 'payment_status_detail' => 'refunded', 'total_failed' => null,
        ];
        $this->client->shouldReceive('refund')->once()->andReturn($response);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once()->with($this->order, $response['observed']);
        $this->metadata->shouldNotReceive('setPpOrderId', 'setPpPaymentId', 'setTransactionIntentId', 'setMpOrderId');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');
        $this->assertTrue($this->processor()->refund($this->order, 'refund_42', '125.50')['success']);
    }

    public function testKnownSuccessfulOperationDoesNotCallProviderAgain(): void
    {
        $this->reserve('no', 'succeeded');
        $this->client->shouldNotReceive('query');
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldNotReceive('finish');
        $this->assertTrue($this->processor()->refund($this->order, 'refund_42', '25.50')['success']);
    }

    /** @dataProvider conflictProvider */
    public function testRemoteContextConflictPreventsMutation(string $field, $value): void
    {
        $this->reserve();
        $query = $this->response('query');
        $query['order_context'][$field] = $value;
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertFalse($this->processor()->refund($this->order, 'refund_42', '25.50')['success']);
    }

    public static function conflictProvider(): array
    {
        return [
            ['seller_reference', '67890'], ['attempt_reference', 'WC-999'],
            ['amount', ['value' => '125.49', 'currency' => 'BRL']],
            ['amount', ['value' => '125.50', 'currency' => 'USD']],
            ['refunded_amount', '125.51'], ['refunded_amount', null],
        ];
    }

    public function testRefundOfAStorePaidStatusOrderUsesOrders(): void
    {
        // Given WooCommerce reports the store's own paid status as paid.
        $this->order->shouldReceive('get_status')->with('edit')->andReturn('paid-by-store');
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $response = $this->response('refund');
        $response['payments'][0]['amount']['value'] = '25.50';
        $response['order_context']['refunded_amount'] = '25.50';
        $this->client->shouldReceive('refund')->once()->andReturn($response);
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');
        $this->assertTrue($this->processor()->refund($this->order, 'refund_42', '25.50')['success']);
    }

    public function testRefundOfAnUnpaidLifecycleStatusReportedPaidNeverReachesOrders(): void
    {
        // A paid fact on a native unpaid status is contradictory local state.
        $this->order->shouldReceive('get_status')->with('edit')->andReturn('on-hold');
        $this->operations->shouldNotReceive('reserve');
        $this->client->shouldNotReceive('query', 'refund');
        $this->assertFalse($this->processor()->refund($this->order, 'refund_42', '25.50')['success']);
    }

    public function testRefundAboveRemoteBalanceIsRejectedBeforePost(): void
    {
        $this->reserve();
        $query = $this->response('query');
        $query['order_context']['refunded_amount'] = '125.00';
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertSame('amount_conflict', $this->processor()->refund($this->order, 'refund_42', '25.50')['reason']);
    }

    public function testTimeoutRetainsUncertaintyAndDoesNotLeakException(): void
    {
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $this->client->shouldReceive('refund')->once()->andThrow(new RuntimeException('TOKEN_SENTINEL_ACCOUNT_PAYLOAD'));
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'uncertain');
        $result = $this->processor()->refund($this->order, 'refund_42', '25.50');
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('SENTINEL', json_encode([$result, $this->metrics]));
    }

    public function testPendingCancellationMustBeConfirmedAndCountsExpiration(): void
    {
        $this->pending();
        $this->reserve();
        $query = $this->response('query');
        $query['state'] = 'processing';
        $query['payments'][0]['state'] = 'processing';
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldReceive('cancel')->once()->with($this->request() + ['operation_context' => $this->mutationContext()])
            ->andReturn($this->response('cancel'));
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');
        $this->assertTrue($this->processor()->cancel($this->order, true)['success']);
        $this->assertSame(['smart_pix_pending_expiration', 'cancel_requested', null], $this->metrics[0]);
    }

    public function testApprovalDuringCancellationBlocksLocalCancellationAndUsesSharedApplicator(): void
    {
        $this->application = ['outcome' => 'applied', 'reason' => 'native_paid_state_verified', 'status' => 'processing'];
        $this->pending();
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertSame('already_approved', $this->processor()->cancel($this->order)['reason']);
        $this->assertCount(1, $this->approvals);
        $this->assertArrayNotHasKey('order_context', $this->approvals[0][2]);
    }

    public function testApprovalAlreadyAppliedOnAStorePaidStatusBlocksLocalCancellation(): void
    {
        $this->application = ['outcome' => 'no_change', 'reason' => 'approval_already_applied', 'status' => 'paid-by-store'];
        $this->pending();
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertSame('already_approved', $this->processor()->cancel($this->order)['reason']);
        $this->assertCount(1, $this->approvals);
    }

    public function testApprovalWithoutAVerifiedPaidStateIsNotConfirmed(): void
    {
        // A paid-looking status alone is not proof: only the applicator's verified reasons confirm approval.
        $this->application = ['outcome' => 'no_change', 'reason' => 'paid_state_preserved', 'status' => 'processing'];
        $this->pending();
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('query'));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'uncertain');
        $this->assertSame('native_effect_not_confirmed', $this->processor()->cancel($this->order)['reason']);
    }

    /** @dataProvider rejectionRecoveryProvider */
    public function testConfirmedRejectionUpdatesThePendingOrderWithoutSendingCancellation(string $dispatch, string $detail): void
    {
        // Given an unpaid order, including a legacy cancellation whose dispatch is unknown.
        $this->pending();
        $this->reserve($dispatch);
        $this->application['status'] = 'failed';
        $this->client->shouldReceive('query')->once()->andReturn($this->rejection($detail));
        $this->client->shouldNotReceive('cancel', 'refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');

        // When Orders confirms a canonical rejection, then apply the failure through the shared applicator.
        $result = $this->processor()->cancel($this->order);
        $this->assertSame(['success' => true, 'reason' => 'remote_terminal_confirmed'], $result);
        $this->assertCount(1, $this->approvals);
        $this->assertSame('rejected', $this->approvals[0][2]['payments'][0]['state']);
        $this->assertArrayNotHasKey('order_context', $this->approvals[0][2]);
    }

    public static function rejectionRecoveryProvider(): array
    {
        return [['yes', 'processing_error'], ['read_only', 'processing_error'],
            ['yes', 'insufficient_amount'], ['read_only', 'insufficient_amount']];
    }

    public function testRejectionBetweenPreflightAndCancellationIsAppliedAsFailure(): void
    {
        $this->pending();
        $this->reserve();
        $this->application['status'] = 'failed';
        $query = $this->response('query');
        $query['state'] = $query['payments'][0]['state'] = 'processing';
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldReceive('cancel')->once()->andReturn($this->rejection('insufficient_amount'));
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded');

        $this->assertTrue($this->processor()->cancel($this->order)['success']);
        $this->assertCount(1, $this->approvals);
        $this->assertSame('SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', $this->approvals[0][2]['error']['code']);
    }

    /** @dataProvider unsafeRejectionProvider */
    public function testInvalidRejectionEvidenceCannotChangeTheOrder(array $error): void
    {
        $this->pending();
        $this->reserve();
        $response = $this->rejection('processing_error');
        $response['error'] = $error;
        $this->client->shouldReceive('query')->once()->andReturn($response);
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'read_failed');
        $this->assertSame('invalid_response', $this->processor()->cancel($this->order)['reason']);
        $this->assertSame([], $this->approvals);
    }

    public static function unsafeRejectionProvider(): array
    {
        return [
            [['category' => 'unknown', 'code' => 'SMART_PIX_REJECTED_PROCESSING_ERROR', 'retryable' => false]],
            [['category' => 'rejected', 'code' => 'SMART_PIX_INVALID_RESPONSE', 'retryable' => false]],
            [['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_PROCESSING_ERROR', 'retryable' => true]],
            [['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_', 'retryable' => false]],
        ];
    }

    /** @dataProvider readOnlyRecoveryFailureProvider */
    public function testLegacyRecoveryNeverAuthorizesAnotherPostOrBecomesADispatchableReadFailure(string $failure): void
    {
        $this->pending();
        $this->reserve('read_only');
        if ($failure === 'pending') {
            $response = $this->response('query');
            $response['state'] = $response['payments'][0]['state'] = 'processing';
            $this->client->shouldReceive('query')->once()->andReturn($response);
        } else {
            $this->client->shouldReceive('query')->once()->andThrow(new RuntimeException($failure));
        }
        $this->client->shouldNotReceive('cancel', 'refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertSame($failure === 'pending' ? 'operation_unresolved' : $failure, $this->processor()->cancel($this->order)['reason']);
        $this->assertSame([], $this->approvals);
    }

    public static function readOnlyRecoveryFailureProvider(): array
    {
        return [['pending'], ['invalid_response'], ['timeout']];
    }

    public function testMultiplePaymentsAreRejectedInsteadOfSelectingFirst(): void
    {
        $this->reserve();
        $query = $this->response('query');
        $query['payments'][] = $query['payments'][0];
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertFalse($this->processor()->refund($this->order, 'refund_42', '25.50')['success']);
    }

    public function testTelemetryFailureDoesNotChangeSuccessfulRefund(): void
    {
        $this->reserve('no', 'succeeded');
        $this->client->shouldNotReceive('query');
        $this->assertTrue($this->processor(true)->refund($this->order, 'refund_42', '25.50')['success']);
    }

    public function testLegacyNormalizedQueryWithoutSellerEvidenceCannotAuthorizeMutation(): void
    {
        $this->reserve();
        $query = $this->response('query');
        unset($query['order_context']);
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $this->assertSame('invalid_response', $this->processor()->refund($this->order, 'refund_42', '25.50')['reason']);
    }

    public function testUnknownSensitiveSnapshotFieldIsRejectedWithoutTelemetryLeak(): void
    {
        $this->reserve();
        $query = $this->response('query');
        $query['order_context']['token'] = 'AUTHORIZATION_SENTINEL';
        $this->client->shouldReceive('query')->once()->andReturn($query);
        $this->client->shouldNotReceive('refund');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'rejected');
        $result = $this->processor()->refund($this->order, 'refund_42', '25.50');
        $this->assertSame('invalid_response', $result['reason']);
        $this->assertStringNotContainsString('SENTINEL', json_encode([$result, $this->metrics]));
    }

    public function testUnconfirmedRemoteCancellationPreservesTheNativePendingOrder(): void
    {
        $this->pending();
        $this->reserve();
        $response = $this->response('query');
        $response['state'] = 'processing';
        $response['payments'][0]['state'] = 'processing';
        $this->client->shouldReceive('query')->once()->andReturn($response);
        $this->client->shouldReceive('cancel')->once()->andReturn($response);
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'uncertain');
        $this->assertSame('operation_not_confirmed', $this->processor()->cancel($this->order)['reason']);
        $this->assertSame([], $this->approvals);
    }

    public function testUnknownRefundContextEmitsOnlyFixedDiagnostics(): void
    {
        $this->client->shouldNotReceive('query');
        $this->client->shouldNotReceive('refund');
        $this->processor()->rejectRefundContext();
        $this->assertSame([['smart_pix_server_error', 'refund', 'refund_context_invalid']], $this->metrics);
    }

    public function testBusyOperationDoesNotCallProvider(): void
    {
        $this->operations->shouldReceive('reserve')->once()->andThrow(new RuntimeException('operation_busy'));
        $this->client->shouldNotReceive('query');
        $this->client->shouldNotReceive('refund');
        $this->assertSame('operation_busy', $this->processor()->refund($this->order, 'refund_42', '25.50')['reason']);
    }

    /** @dataProvider transientReadProvider */
    public function testTransientInitialReadFailureReleasesOnlyAnUnsentCancellation(string $reason): void
    {
        $this->pending();
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andThrow(new RuntimeException($reason));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'read_failed');
        $this->assertSame($reason, $this->processor()->cancel($this->order)['reason']);
        $this->assertSame([], $this->approvals);
    }

    public static function transientReadProvider(): array
    {
        return [['timeout'], ['unavailable'], ['rate_limit'], ['invalid_response']];
    }

    /** @dataProvider unverifiedNativeProvider */
    public function testUnverifiedNativeCancellationKeepsTheReservationUncertain(array $application): void
    {
        $this->pending();
        $this->reserve();
        $this->application = $application;
        $this->client->shouldReceive('query')->once()->andReturn($this->response('cancel'));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'uncertain');
        $this->assertSame('native_effect_not_confirmed', $this->processor()->cancel($this->order)['reason']);
        $this->assertCount(1, $this->approvals);
    }

    public static function unverifiedNativeProvider(): array
    {
        return [[['outcome' => 'uncertain', 'status' => 'cancelled']],
            [['outcome' => 'conflict', 'status' => 'pending']],
            [['outcome' => 'applied', 'status' => 'processing']]];
    }

    public function testReservationIsFinishedOnlyAfterNativeCancellationVerification(): void
    {
        $this->pending();
        $this->reserve();
        $this->client->shouldReceive('query')->once()->andReturn($this->response('cancel'));
        $this->client->shouldNotReceive('cancel');
        $this->operations->shouldReceive('finish')->once()->with($this->order, Mockery::type('array'), 'succeeded')
            ->andReturnUsing(function (): void {
                $this->assertCount(1, $this->approvals);
            });
        $this->assertTrue($this->processor()->cancel($this->order)['success']);
    }

    private function reserve(string $dispatch = 'yes', string $status = 'dispatching'): void
    {
        $this->operations->shouldReceive('reserve')->once()->with($this->order, Mockery::type('string'), Mockery::type('array'))
            ->andReturn(['id' => str_repeat('b', 64), 'key' => str_repeat('a', 64), 'dispatch' => $dispatch, 'status' => $status, 'operation' => 'cancel']);
    }

    private function pending(): void
    {
        $this->order->shouldReceive('get_status')->with('edit')->andReturn('pending');
        $this->order->shouldReceive('is_paid')->andReturn(false);
        $this->order->shouldReceive('get_date_paid')->with('edit')->andReturn(null);
    }

    private function processor(bool $brokenTelemetry = false, ?\MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry $telemetry = null): SmartPixMutationProcessor
    {
        return new SmartPixMutationProcessor(
            $this->client,
            $this->metadata,
            $this->seller,
            $this->operations,
            function ($event, $value, $message) use ($brokenTelemetry): void {
                if ($brokenTelemetry) {
                    throw new RuntimeException('TELEMETRY_SENTINEL');
                }
                $this->metrics[] = [$event, $value, $message];
            },
            function ($id, $key, $response): array {
                $this->approvals[] = [$id, $key, $response];
                return $this->application;
            },
            $telemetry
        );
    }

    private function response(string $operation): array
    {
        $names = ['query' => 'orders-query-terminal-success', 'cancel' => 'orders-cancel-terminal-success', 'refund' => 'orders-refund-terminal-success'];
        return json_decode(file_get_contents(__DIR__ . '/mutations/' . $names[$operation] . '.json'), true)['response'];
    }

    private function request(): array
    {
        return ['surface' => 'server', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321', 'order_reference' => 'ORDER_SYNTHETIC_001', 'amount' => null, 'refund_amount' => null];
    }

    private function rejection(string $detail): array
    {
        $response = $this->response('query');
        $response['outcome'] = 'failure';
        $response['state'] = 'terminal_failure';
        $response['payments'][0]['state'] = 'rejected';
        $response['error'] = ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_' . strtoupper($detail), 'retryable' => false];
        return $response;
    }

    private function mutationContext(): array
    {
        return ['contract_version' => 'smart-pix-mutations/v1', 'idempotency_key' => str_repeat('a', 64), 'seller_reference' => '12345'];
    }
}

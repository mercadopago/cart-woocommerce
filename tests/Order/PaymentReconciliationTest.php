<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Api\Http\HttpResponse as Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PaymentReconciliation;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;

class PaymentReconciliationTest extends TestCase
{
    private OrderMetadata $orderMetadata;

    private OrderStatus $orderStatus;

    private Requester $requester;

    private Logs $logs;

    private File $logFile;

    private PaymentReconciliation $reconciliation;

    private WC_Order $order;

    protected function setUp(): void
    {
        $this->orderMetadata = Mockery::mock(OrderMetadata::class);
        $this->orderStatus = Mockery::mock(OrderStatus::class);
        $this->requester = Mockery::mock(Requester::class);
        $this->logs = Mockery::mock(Logs::class);
        $this->logFile = Mockery::mock(File::class);
        $this->logFile->shouldIgnoreMissing();
        $this->logs->file = $this->logFile;
        $this->order = Mockery::mock(WC_Order::class);

        $this->reconciliation = new PaymentReconciliation(
            $this->orderMetadata,
            $this->orderStatus,
            $this->requester,
            $this->logs
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPreservesOrderWhenNoLocalIdentifierExists(): void
    {
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($this->order)->andReturn([]);
        $this->orderMetadata->shouldReceive('getPreferenceIds')->with($this->order)->andReturn([]);
        $this->requester->shouldNotReceive('get');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testApprovedLocalPaymentUpdatesOrderAndPreventsCancellation(): void
    {
        $payment = $this->approvedPayment('100', 'WC-75');
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $payment));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldReceive('updateOrderCustomFieldsAfterReconciliation')
            ->once()
            ->with($this->order, [$payment])
            ->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $payment, $this->order, 'woo-mercado-pago-basic');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreferenceRecoversPaymentWithoutLocalPaymentId(): void
    {
        $pending = $this->payment('200', 'WC-75', 'pending');
        $cancelled = $this->payment('200', 'WC-75', 'cancelled');
        $this->expectIdentifiers([], ['123456789-preference']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-preference', $this->headers())
            ->andReturn($this->merchantOrderResponse('123456789-preference', 'WC-75', ['200']));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/200', $this->headers())
            ->andReturn($this->response(200, $pending));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/200', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $cancelled));
        $this->orderMetadata->shouldReceive('replacePaymentIds')
            ->once()
            ->with($this->order, ['200'])
            ->andReturn(true);
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testApprovedPaymentDiscoveredOnlyByPreferenceUpdatesOrder(): void
    {
        $approved = $this->approvedPayment('200', 'WC-75');
        $this->expectIdentifiers([], ['123456789-preference']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-preference', $this->headers())
            ->andReturn($this->merchantOrderResponse('123456789-preference', 'WC-75', ['200']));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/200', $this->headers())
            ->andReturn($this->response(200, $approved));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldReceive('updateOrderCustomFieldsAfterReconciliation')
            ->once()
            ->with($this->order, [$approved])
            ->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $approved, $this->order, 'woo-mercado-pago-basic');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testApprovedPaymentWinsOverRejectedPreviousAttempt(): void
    {
        $rejected = $this->payment('100', 'WC-75', 'rejected');
        $approved = $this->approvedPayment('200', 'WC-75');
        $this->expectIdentifiers(['100'], ['123456789-preference']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-preference', $this->headers())
            ->andReturn($this->merchantOrderResponse('123456789-preference', 'WC-75', ['200']));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $rejected));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/200', $this->headers())
            ->andReturn($this->response(200, $approved));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldReceive('updateOrderCustomFieldsAfterReconciliation')
            ->once()
            ->with($this->order, [$rejected, $approved])
            ->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $approved, $this->order, 'woo-mercado-pago-basic');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testEarlierApprovedDirectAttemptIsRecoveredFromPersistedHistory(): void
    {
        $storedMetadata = [];
        $order = $this->inMemoryOrder($storedMetadata);
        $orderMetadata = new OrderMetadata(new OrderMeta(), $this->logs);
        $reconciliation = new PaymentReconciliation(
            $orderMetadata,
            $this->orderStatus,
            $this->requester,
            $this->logs
        );

        $approved = array_merge($this->approvedPayment('100', 'WC-75'), [
            'date_created' => '2026-10-05T10:00:00.000-03:00',
        ]);
        $pending = array_merge($this->payment('200', 'WC-75', 'pending'), [
            'date_created' => '2026-10-05T10:05:00.000-03:00',
        ]);

        $orderMetadata->setCustomMetadata($order, $approved);
        $orderMetadata->setCustomMetadata($order, $pending);

        $this->assertSame('200', $storedMetadata['_Mercado_Pago_Payment_IDs']);

        $this->expectAccessTokenFor($order);
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/200', $this->headers())
            ->andReturn($this->response(200, $pending));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $approved));
        $this->requester->shouldNotReceive('put');
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $approved, $order, 'woo-mercado-pago-custom');

        $this->assertFalse(
            $reconciliation->canCancelOrder($order, 'WC-75', 'woo-mercado-pago-custom')
        );
        $this->assertSame('200, 100', $storedMetadata['_Mercado_Pago_Payment_IDs']);
    }

    /**
     * @dataProvider terminalNonApprovedStatusProvider
     */
    public function testAllowsCancellationWhenPaymentIsTerminalAndUnpaid(string $status): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', $status)));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function terminalNonApprovedStatusProvider(): array
    {
        return [
            'rejected' => ['rejected'],
            'cancelled' => ['cancelled'],
            'refunded' => ['refunded'],
            'charged back' => ['charged_back'],
        ];
    }

    public function testUnusedPreferenceDoesNotBlockCancellationAfterRejectedLocalAttempt(): void
    {
        $rejected = $this->payment('100', 'WC-75', 'rejected');
        $this->expectIdentifiers(['100'], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => null, 'total' => 0]));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $rejected));
        $this->requester->shouldNotReceive('put');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testAllowsCancellationWhenPersistedPreferenceHasNoPayment(): void
    {
        $this->expectIdentifiers([], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => null, 'total' => 0]));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenEmptyPreferenceSearchIsIncomplete(): void
    {
        $this->expectIdentifiers([], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => [], 'total' => 1]));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenPreferenceSearchTotalIsFractional(): void
    {
        $this->expectIdentifiers([], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => [], 'total' => 0.5]));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenNullPreferenceElementsClaimExistingResults(): void
    {
        $this->expectIdentifiers([], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => null, 'total' => 1]));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testEmptyPreferenceSearchDoesNotHideKnownCancelablePayment(): void
    {
        $inProcess = $this->payment('100', 'WC-75', 'in_process');
        $cancelled = $this->payment('100', 'WC-75', 'cancelled');
        $this->expectIdentifiers(['100'], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => null, 'total' => 0]));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $inProcess));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $cancelled));
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testApprovedLocalPaymentWinsEvenWhenNewPreferenceHasNoPayment(): void
    {
        $approved = $this->approvedPayment('100', 'WC-75');
        $this->expectIdentifiers(['100'], ['123456789-unused']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/merchant_orders/search?preference_id=123456789-unused', $this->headers())
            ->andReturn($this->response(200, ['elements' => [], 'total' => 0]));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $approved));
        $this->requester->shouldNotReceive('put');
        $this->orderMetadata->shouldReceive('updateOrderCustomFieldsAfterReconciliation')
            ->once()
            ->with($this->order, [$approved])
            ->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $approved, $this->order, 'woo-mercado-pago-basic');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testRejectsMerchantOrderWithDifferentPreferenceId(): void
    {
        $this->expectIdentifiers([], ['123456789-preference']);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->andReturn($this->merchantOrderResponse('different-preference', 'WC-75', ['200']));
        $this->requester->shouldNotReceive('get')->with('/v1/payments/200', Mockery::any());
        $this->orderMetadata->shouldNotReceive('replacePaymentIds');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testRejectsPaymentWithDifferentExternalReference(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'OTHER-75', 'approved')));
        $this->orderMetadata->shouldNotReceive('updateOrderCustomFieldsAfterReconciliation');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderAndDoesNotLogRemoteErrorDetailsWhenApiFails(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->andThrow(new RuntimeException('response contains secret data'));
        $this->logFile->shouldReceive('error')
            ->once()
            ->with(
                'Mercado Pago: Payment reconciliation before cancellation was inconclusive: RuntimeException',
                PaymentReconciliation::class
            );

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    /**
     * @dataProvider cancelablePaymentStatusProvider
     */
    public function testCancelsDocumentedCancelablePaymentBeforeAllowingOrderCancellation(string $status): void
    {
        $activePayment = $this->payment('100', 'WC-75', $status);
        $cancelledPayment = $this->payment('100', 'WC-75', 'cancelled');
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $activePayment));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $cancelledPayment));
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function cancelablePaymentStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'in process' => ['in_process'],
            'authorized' => ['authorized'],
        ];
    }

    public function testPreservesOrderWithoutTryingToCancelPaymentInMediation(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'in_mediation')));
        $this->requester->shouldNotReceive('put');
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenPaymentStatusIsUnknown(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'unknown_status')));
        $this->requester->shouldNotReceive('put');
        $this->orderStatus->shouldNotReceive('processStatus');
        $this->logFile->shouldReceive('error')
            ->once()
            ->with(
                'Mercado Pago: Payment reconciliation before cancellation was inconclusive: RuntimeException',
                PaymentReconciliation::class
            );

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenOnlyPartOfThePaymentsCanBeCancelled(): void
    {
        $this->expectIdentifiers(['100', '200'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'pending')));
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/200', $this->headers())
            ->andReturn($this->response(200, $this->payment('200', 'WC-75', 'in_process')));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'cancelled')));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/200', $this->headers(), ['status' => 'cancelled'])
            ->andThrow(new RuntimeException('second cancellation failed'));
        $this->orderStatus->shouldNotReceive('processStatus');
        $this->logFile->shouldReceive('error')
            ->once()
            ->with(
                'Mercado Pago: Payment reconciliation before cancellation was inconclusive: RuntimeException',
                PaymentReconciliation::class
            );

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    /**
     * @dataProvider unconfirmedCancellationProvider
     */
    public function testPreservesOrderWhenRemoteCancellationIsNotExactlyConfirmed(
        int $responseStatus,
        array $responsePayment
    ): void {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'pending')));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response($responseStatus, $responsePayment));
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function unconfirmedCancellationProvider(): array
    {
        return [
            'different payment id' => [200, $this->payment('999', 'WC-75', 'cancelled')],
            'different external reference' => [200, $this->payment('100', 'OTHER-75', 'cancelled')],
            'payment remains active' => [200, $this->payment('100', 'WC-75', 'pending')],
            'http error' => [500, $this->payment('100', 'WC-75', 'cancelled')],
        ];
    }

    public function testAllowsOrderCancellationWhenRemoteCancellationFindsTerminalNonApprovedStatus(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'pending')));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'rejected')));
        $this->orderStatus->shouldNotReceive('processStatus');

        $this->assertTrue(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testApprovedCancellationResponseUpdatesOrderAndPreventsCancellation(): void
    {
        $pending = $this->payment('100', 'WC-75', 'pending');
        $approved = $this->approvedPayment('100', 'WC-75');
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $pending));
        $this->requester->shouldReceive('put')
            ->once()
            ->with('/v1/payments/100', $this->headers(), ['status' => 'cancelled'])
            ->andReturn($this->response(200, $approved));
        $this->orderMetadata->shouldReceive('updateOrderCustomFieldsAfterReconciliation')
            ->once()
            ->with($this->order, [$approved])
            ->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')
            ->once()
            ->with('approved', $approved, $this->order, 'woo-mercado-pago-basic');

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    public function testPreservesOrderWhenRemoteCancellationFailsWithoutLoggingResponseDetails(): void
    {
        $this->expectIdentifiers(['100'], []);
        $this->expectAccessToken();
        $this->requester->shouldReceive('get')
            ->once()
            ->with('/v1/payments/100', $this->headers())
            ->andReturn($this->response(200, $this->payment('100', 'WC-75', 'pending')));
        $this->requester->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('response contains secret data'));
        $this->logFile->shouldReceive('error')
            ->once()
            ->with(
                'Mercado Pago: Payment reconciliation before cancellation was inconclusive: RuntimeException',
                PaymentReconciliation::class
            );

        $this->assertFalse(
            $this->reconciliation->canCancelOrder($this->order, 'WC-75', 'woo-mercado-pago-basic')
        );
    }

    private function expectIdentifiers(array $paymentIds, array $preferenceIds): void
    {
        $this->orderMetadata->shouldReceive('getPaymentIds')
            ->once()
            ->with($this->order)
            ->andReturn($paymentIds);
        $this->orderMetadata->shouldReceive('getPreferenceIds')
            ->once()
            ->with($this->order)
            ->andReturn($preferenceIds);
    }

    private function expectAccessToken(): void
    {
        $this->expectAccessTokenFor($this->order);
    }

    private function expectAccessTokenFor(WC_Order $order): void
    {
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')
            ->once()
            ->with($order)
            ->andReturn('test-access-token');
    }

    private function inMemoryOrder(array &$storedMetadata): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')
            ->andReturnUsing(static function (string $key, bool $single = true) use (&$storedMetadata) {
                if (!array_key_exists($key, $storedMetadata)) {
                    return $single ? '' : [];
                }

                if ($single) {
                    return $storedMetadata[$key];
                }

                return [new \WC_Meta_Data([
                    'key' => $key,
                    'value' => $storedMetadata[$key],
                ])];
            });
        $order->shouldReceive('add_meta_data')
            ->andReturnUsing(static function (string $key, $value) use (&$storedMetadata): void {
                if (!array_key_exists($key, $storedMetadata)) {
                    $storedMetadata[$key] = $value;
                }
            });
        $order->shouldReceive('update_meta_data')
            ->andReturnUsing(static function (string $key, $value) use (&$storedMetadata): void {
                $storedMetadata[$key] = $value;
            });
        $order->shouldReceive('save')->andReturn(true);

        return $order;
    }

    private function headers(): array
    {
        return ['Authorization: Bearer test-access-token'];
    }

    private function response(int $status, array $data): Response
    {
        return new Response($status, [], '', $data);
    }

    private function merchantOrderResponse(
        string $preferenceId,
        string $externalReference,
        array $paymentIds
    ): Response {
        return $this->response(200, [
            'elements' => [[
                'preference_id' => $preferenceId,
                'external_reference' => $externalReference,
                'payments' => array_map(static function (string $paymentId): array {
                    return ['id' => $paymentId];
                }, $paymentIds),
            ]],
            'total' => 1,
        ]);
    }

    private function payment(string $id, string $externalReference, string $status): array
    {
        return [
            'id' => $id,
            'external_reference' => $externalReference,
            'status' => $status,
        ];
    }

    private function approvedPayment(string $id, string $externalReference): array
    {
        return array_merge($this->payment($id, $externalReference, 'approved'), [
            'transaction_amount' => 100.0,
            'payment_type_id' => 'credit_card',
            'payment_method_id' => 'visa',
            'coupon_amount' => 0,
            'transaction_details' => [
                'total_paid_amount' => 100.0,
            ],
            'transaction_amount_refunded' => 0,
        ]);
    }
}

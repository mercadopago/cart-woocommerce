<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PixStatusRetry;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PixStatusRetryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $orderStatus;
    private $orderMetadata;
    private $requester;
    private $datadog;
    private $logs;
    private PixStatusRetry $retry;

    protected function setUp(): void
    {
        WP_Mock::setUp();

        $logs       = Mockery::mock(Logs::class);
        $file       = Mockery::mock(File::class);
        $file->shouldIgnoreMissing();
        $logs->file = $file;
        $this->logs = $logs;

        $this->orderStatus   = Mockery::mock(OrderStatus::class);
        $this->orderMetadata = Mockery::mock(OrderMetadata::class);
        $this->requester     = Mockery::mock(Requester::class);
        $this->datadog       = Mockery::mock(Datadog::class)->shouldIgnoreMissing();
        $this->retry         = new PixStatusRetry($this->orderStatus, $this->orderMetadata, $this->requester, $logs, $this->datadog);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    private function pixOrder()
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_id')->andReturn(28);
        return $order;
    }

    private function paymentResponse(array $data, int $status = 200)
    {
        $response = Mockery::mock(HttpResponse::class);
        $response->shouldReceive('getStatus')->andReturn($status);
        $response->shouldReceive('getData')->andReturn($data);
        return $response;
    }

    public function testEnqueuePersistsOnlyMinimalReferencesInTheSharedGroup(): void
    {
        WP_Mock::userFunction('get_transient')->andReturn(false);
        WP_Mock::userFunction('as_enqueue_async_action')
            ->once()
            ->with('mp_retry_pix_status', [28, 'polling', 'pay-123', 1], 'mercadopago-pix-status')
            ->andReturn(41);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 41, 3900);

        $this->assertSame(41, $this->retry->enqueue(28, 'polling', 'pay-123'));
    }

    public function testEnqueueSanitizesTheReferenceAndRejectsUnknownSources(): void
    {
        WP_Mock::userFunction('get_transient')->andReturn(false);
        WP_Mock::userFunction('as_enqueue_async_action')
            ->once()
            ->with('mp_retry_pix_status', [28, 'webhook', 'abcscript123', 1], 'mercadopago-pix-status')
            ->andReturn(9);

        $this->assertSame(9, $this->retry->enqueue(28, 'webhook', "abc<script>123\n"));
        $this->assertSame(0, $this->retry->enqueue(28, 'not-a-channel', '1'));
        $this->assertSame(0, $this->retry->enqueue(0, 'polling', '1'));
    }

    public function testEnqueueReusesTheLiveRetryOfTheSameOrderInsteadOfPilingUp(): void
    {
        // The guard transient names the action of the live chain (any channel/reference/attempt).
        WP_Mock::userFunction('get_transient')->twice()->with('mp_pix_retry_28')->andReturn(55); // lookup + re-check
        WP_Mock::userFunction('as_enqueue_async_action')->never();
        // The new trigger is not dropped: it leaves a signal for a worker that is already running.
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_signal_28', 1, 3900);
        // The live action is handed back only after the store is read to hold the signal.
        WP_Mock::userFunction('get_transient')->once()->with('mp_pix_retry_signal_28')->andReturn(1);

        $this->assertSame(55, $this->retry->enqueue(28, 'polling', 'pay-123'));
    }

    public function testEnqueueOpensANewChainWhenTheLiveOneEndedWhileTheSignalWasWritten(): void
    {
        // Lookup sees the running action; before the signal is stored the worker clears the guard and reads
        // an empty signal. The re-check after the write finds no guard: do not return the dead action id.
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_28')->andReturn(55, false);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_signal_28', 1, 3900);
        WP_Mock::userFunction('get_transient')->once()->with('mp_pix_retry_signal_28')->andReturn(1);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 62, 3900);
        WP_Mock::userFunction('delete_transient')->once()->with('mp_pix_retry_signal_28');
        WP_Mock::userFunction('as_enqueue_async_action')->once()->andReturn(62);

        $this->assertSame(62, $this->retry->enqueue(28, 'polling', 'pay-B'));
    }

    public function testSuccessorThatCannotBePersistedReschedulesInsteadOfFinishingAsProcessed(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'pending']));
        $this->orderStatus->shouldReceive('processStatus')->once()->andReturn(true);

        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_signal_28')->andReturn(false, 1);
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_28')->andReturn(false);
        WP_Mock::userFunction('delete_transient');
        WP_Mock::userFunction('as_enqueue_async_action')->once()->andReturn(0); // successor failed
        // The consumed trigger is not lost: the run is rescheduled (it re-reads every attempt).
        WP_Mock::userFunction('as_schedule_single_action')
            ->once()
            ->with(Mockery::type('int'), 'mp_retry_pix_status', [28, 'polling', 'pay-1', 2], 'mercadopago-pix-status')
            ->andReturn(90);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 90, 360);

        $this->retry->run(28, 'polling', 'pay-1', 1);
    }

    public function testEnqueueIgnoresAGuardWhoseActionIsNoLongerRunnable(): void
    {
        $retry = Mockery::mock(PixStatusRetry::class, [$this->orderStatus, $this->orderMetadata, $this->requester, $this->logs, $this->datadog])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $retry->shouldReceive('actionStatus')->with(55)->andReturn('failed'); // fatal error / deleted action

        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_28')->andReturn(55);
        WP_Mock::userFunction('delete_transient')->once()->with('mp_pix_retry_28');
        WP_Mock::userFunction('as_enqueue_async_action')->once()->andReturn(61);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 61, 3900);

        $this->assertSame(61, $retry->enqueue(28, 'polling', 'pay-123'));
    }

    public function testApprovalSignalledWhileTheWorkerRunsIsHandedToASuccessor(): void
    {
        // The worker read A (rejected) before B was approved; B's trigger only recorded a signal.
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['pay-B', 'pay-A']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-A', Mockery::type('array'))->andReturn($this->paymentResponse(['id' => 'pay-A', 'status' => 'rejected']));
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-B', Mockery::type('array'))->andReturn($this->paymentResponse(['id' => 'pay-B', 'status' => 'pending']));
        $this->orderStatus->shouldReceive('processStatus')->once()->andReturn(true);

        // get_transient: 1st call = consume at start (nothing), 2nd = at the end (signal arrived), then the
        // guard lookup of the successor's enqueue() (guard already cleared).
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_signal_28')->andReturn(false, 1);
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_28')->andReturn(false);
        WP_Mock::userFunction('delete_transient')->with('mp_pix_retry_28');
        WP_Mock::userFunction('delete_transient')->once()->with('mp_pix_retry_signal_28');
        WP_Mock::userFunction('as_enqueue_async_action')
            ->once()
            ->with('mp_retry_pix_status', [28, 'polling', '', 1], 'mercadopago-pix-status')
            ->andReturn(88);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 88, 3900);

        $this->retry->run(28, 'polling', 'pay-A', 1);
    }

    public function testAnErrorRaisedByTheStatusFlowStillReschedulesInsteadOfLeavingAStaleGuard(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        // A third-party hook raises a TypeError (an Error, not an Exception).
        $this->orderStatus->shouldReceive('processStatus')->andThrow(new \TypeError('hook exploded'));
        WP_Mock::userFunction('as_schedule_single_action')
            ->once()
            ->with(Mockery::type('int'), 'mp_retry_pix_status', [28, 'polling', 'pay-1', 2], 'mercadopago-pix-status')
            ->andReturn(70);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 70, 360);

        $this->retry->run(28, 'polling', 'pay-1', 1);
    }

    public function testAnUnreadableAttemptDoesNotHideAnApprovalOfAnotherOne(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['pay-B', 'pay-A']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $approvedB = ['id' => 'pay-B', 'status' => 'approved'];
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-B', Mockery::type('array'))->andReturn($this->paymentResponse($approvedB));
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-A', Mockery::type('array'))->andReturn($this->paymentResponse([], 500));
        $this->orderStatus->shouldReceive('processStatus')->once()->with('approved', $approvedB, $order, PixGateway::WEBHOOK_API_NAME)->andReturn(true);

        $this->retry->run(28, 'polling', 'pay-B', 1);
    }

    public function testWithoutAnApprovalAnUnreadableAttemptMakesTheRunRetryInsteadOfApplyingAnOlderOne(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        // The current attempt (pay-B) cannot be read; the old one is rejected.
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['pay-B', 'pay-A']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-B', Mockery::type('array'))->andReturn($this->paymentResponse([], 500));
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-A', Mockery::type('array'))->andReturn($this->paymentResponse(['id' => 'pay-A', 'status' => 'rejected']));
        $this->orderStatus->shouldReceive('processStatus')->never();
        WP_Mock::userFunction('as_schedule_single_action')->once()->andReturn(71);
        WP_Mock::userFunction('set_transient')->once();

        $this->retry->run(28, 'polling', 'pay-B', 1);
    }

    public function testEnqueueOfAnotherOrderIsNotBlockedByTheGuardOfThisOne(): void
    {
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_28')->andReturn(55);
        WP_Mock::userFunction('get_transient')->with('mp_pix_retry_99')->andReturn(false);
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_99', 60, 3900);
        WP_Mock::userFunction('as_enqueue_async_action')
            ->once()
            ->with('mp_retry_pix_status', [99, 'polling', 'pay-9', 1], 'mercadopago-pix-status')
            ->andReturn(60);

        $this->assertSame(60, $this->retry->enqueue(99, 'polling', 'pay-9'));
    }

    public function testEnqueueFailureIsReportedAsZero(): void
    {
        WP_Mock::userFunction('get_transient')->andReturn(false);
        WP_Mock::userFunction('set_transient')->never();
        WP_Mock::userFunction('as_enqueue_async_action')->once()->andReturn(0);

        $this->assertSame(0, $this->retry->enqueue(28, 'core', '1'));
    }

    public function testRunReappliesTheApprovedStatusThroughTheProtectedEntrypoint(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['pay-123']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->with($order)->andReturn('token');
        $payment = ['id' => 'pay-123', 'status' => 'approved'];
        $this->requester->shouldReceive('get')
            ->once()->with('/v1/payments/pay-123', ['Authorization: Bearer token'])
            ->andReturn($this->paymentResponse($payment));
        $this->orderStatus->shouldReceive('processStatus')
            ->once()->with('approved', $payment, $order, PixGateway::WEBHOOK_API_NAME)->andReturn(true);
        // The chain is over: the dedupe guard goes away so a later contention can queue a new retry.
        WP_Mock::userFunction('delete_transient')->once()->with('mp_pix_retry_28');

        $this->retry->run(28, 'polling', 'pay-123', 1);
    }

    public function testRunNeverQueriesAPaymentThatDoesNotBelongToTheOrder(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['own-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')
            ->once()->with('/v1/payments/own-1', Mockery::type('array'))
            ->andReturn($this->paymentResponse(['id' => 'own-1', 'status' => 'pending']));
        $this->orderStatus->shouldReceive('processStatus')->once()->andReturn(true);

        $this->retry->run(28, 'ipn', 'foreign-999', 1);
    }

    public function testRunSkipsOrdersThatAreNotPix(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_payment_method')->andReturn('woo-mercado-pago-basic');
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderStatus->shouldReceive('processStatus')->never();

        $this->retry->run(28, 'polling', '1', 1);
    }

    public function testRunDoesNotReplayRefundsWithoutNotificationContext(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'refunded']));
        $this->orderStatus->shouldReceive('processStatus')->never();

        $this->retry->run(28, 'core', '1', 1);
    }

    public function testContentionPersistsTheNextAttemptWithBackoffBeforeFinishing(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        $this->orderStatus->shouldReceive('processStatus')->andThrow(new OrderStatusLockException('busy'));

        WP_Mock::userFunction('as_schedule_single_action')
            ->once()
            ->with(Mockery::on(function ($when) {
                return $when >= time() + 119 && $when <= time() + 121; // attempt 2 => 120 s
            }), 'mp_retry_pix_status', [28, 'polling', 'pay-1', 3], 'mercadopago-pix-status')
            ->andReturn(77);
        // The guard now points at the rescheduled action and lasts delay (120 s) + 300 s.
        WP_Mock::userFunction('set_transient')->once()->with('mp_pix_retry_28', 77, 420);

        $this->retry->run(28, 'polling', 'pay-1', 2);
    }

    public function testFailureToPersistTheNextAttemptFailsTheActionInsteadOfDroppingIt(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        $this->orderStatus->shouldReceive('processStatus')->andThrow(new OrderStatusLockException('busy'));
        WP_Mock::userFunction('as_schedule_single_action')->once()->andReturn(0);

        $this->expectException(\RuntimeException::class);
        $this->retry->run(28, 'polling', 'pay-1', 1);
    }

    public function testExhaustedRetriesFailTheActionAndStayVisible(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        $this->orderStatus->shouldReceive('processStatus')->andThrow(new OrderStatusLockException('busy'));
        WP_Mock::userFunction('as_schedule_single_action')->never();

        $this->expectException(\RuntimeException::class);
        $this->retry->run(28, 'polling', 'pay-1', PixStatusRetry::MAX_ATTEMPTS);
    }

    public function testWithoutAPaymentReferenceAnApprovedLaterAttemptIsAppliedOverARejectedFirstOne(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['new-2', 'old-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $rejected = ['id' => 'old-1', 'status' => 'rejected'];
        $approved = ['id' => 'new-2', 'status' => 'approved'];
        $this->requester->shouldReceive('get')->with('/v1/payments/old-1', Mockery::type('array'))->andReturn($this->paymentResponse($rejected));
        $this->requester->shouldReceive('get')->with('/v1/payments/new-2', Mockery::type('array'))->andReturn($this->paymentResponse($approved));
        $this->orderStatus->shouldReceive('processStatus')->once()->with('approved', $approved, $order, PixGateway::WEBHOOK_API_NAME)->andReturn(true);

        $this->retry->run(28, 'sync', '28', 1);
    }

    public function testWithoutAPaymentReferenceAndNoApprovalTheCurrentAttemptIsUsed(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        // getPaymentIds() lists the current attempt first and the historical ones after it.
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['current-3', 'old-2', 'old-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $current = ['id' => 'current-3', 'status' => 'pending'];
        $this->requester->shouldReceive('get')->with('/v1/payments/current-3', Mockery::type('array'))->andReturn($this->paymentResponse($current));
        $this->requester->shouldReceive('get')->with('/v1/payments/old-2', Mockery::type('array'))->andReturn($this->paymentResponse(['id' => 'old-2', 'status' => 'rejected']));
        $this->requester->shouldReceive('get')->with('/v1/payments/old-1', Mockery::type('array'))->andReturn($this->paymentResponse(['id' => 'old-1', 'status' => 'cancelled']));
        $this->orderStatus->shouldReceive('processStatus')->once()->with('pending', $current, $order, PixGateway::WEBHOOK_API_NAME)->andReturn(true);

        $this->retry->run(28, 'reconciliation', '28', 1);
    }

    public function testSharedRetryReconcilesAnApprovalOfAnotherAttemptThanTheStoredReference(): void
    {
        // enqueue(B) reused the chain created for A and dropped B: the worker must still find B.
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->with($order)->andReturn(['pay-B', 'pay-A']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $rejectedA = ['id' => 'pay-A', 'status' => 'rejected'];
        $approvedB = ['id' => 'pay-B', 'status' => 'approved'];
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-A', Mockery::type('array'))->andReturn($this->paymentResponse($rejectedA));
        $this->requester->shouldReceive('get')->with('/v1/payments/pay-B', Mockery::type('array'))->andReturn($this->paymentResponse($approvedB));
        $this->orderStatus->shouldReceive('processStatus')->once()->with('approved', $approvedB, $order, PixGateway::WEBHOOK_API_NAME)->andReturn(true);
        $this->orderStatus->shouldReceive('processStatus')->with('rejected', Mockery::any(), Mockery::any(), Mockery::any())->never();

        $this->retry->run(28, 'polling', 'pay-A', 1);
    }

    public function testEveryExecutionRecordsTheMetricWithItsOutcome(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        $this->orderStatus->shouldReceive('processStatus')->once()->andReturn(true);

        $this->datadog = Mockery::mock(Datadog::class);
        $this->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_retry_executed', 'processed', null, 'pix', ['source' => 'polling', 'attempt' => 2]);
        $retry = new PixStatusRetry($this->orderStatus, $this->orderMetadata, $this->requester, $this->logsMock(), $this->datadog);

        $retry->run(28, 'polling', 'pay-1', 2);
    }

    public function testMetricReportsRescheduledAndFailedOutcomesAndNeverBreaksTheRetry(): void
    {
        $order = $this->pixOrder();
        WP_Mock::userFunction('wc_get_order')->with(28)->andReturn($order);
        $this->orderMetadata->shouldReceive('getPaymentIds')->andReturn(['pay-1']);
        $this->orderStatus->shouldReceive('getAccessTokenForOrder')->andReturn('token');
        $this->requester->shouldReceive('get')->andReturn($this->paymentResponse(['id' => 'pay-1', 'status' => 'approved']));
        $this->orderStatus->shouldReceive('processStatus')->andThrow(new OrderStatusLockException('busy'));
        WP_Mock::userFunction('as_schedule_single_action')->once()->andReturn(5);

        $this->datadog = Mockery::mock(Datadog::class);
        // Telemetry throwing must not alter the outcome.
        $this->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_retry_executed', 'rescheduled', null, 'pix', ['source' => 'sync', 'attempt' => 1])
            ->andThrow(new \Error('telemetry down'));
        $retry = new PixStatusRetry($this->orderStatus, $this->orderMetadata, $this->requester, $this->logsMock(), $this->datadog);
        $retry->run(28, 'sync', '28', 1);

        $this->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_retry_executed', 'failed', null, 'pix', ['source' => 'sync', 'attempt' => PixStatusRetry::MAX_ATTEMPTS]);
        $this->expectException(\RuntimeException::class);
        $retry->run(28, 'sync', '28', PixStatusRetry::MAX_ATTEMPTS);
    }

    private function logsMock()
    {
        $logs = Mockery::mock(Logs::class);
        $file = Mockery::mock(File::class);
        $file->shouldIgnoreMissing();
        $logs->file = $file;

        return $logs;
    }
}

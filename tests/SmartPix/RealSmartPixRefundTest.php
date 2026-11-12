<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use WC_Order;

/** Synthetic wire responses follow the deployed Core P&P models; no live money in unit tests. */
class RealSmartPixRefundTest extends TestCase
{
    /** @dataProvider refundContractStageProvider */
    public function testRefundDiagnosticsIdentifyTheFailingReadReceiptOrConfirmation(string $stage, string $expected): void
    {
        $message = '';
        $sink = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $diagnostic) use (&$message): bool {
            $message = $diagnostic;
            $this->assertSame('smart_pix_server_error', $name);
            $this->assertSame('refund', $details['operation']);
            $this->assertSame('invalid_contract', $details['category']);
            return true;
        });
        [$client, $http] = $this->client(true, true, new SmartPixTelemetry($sink));
        $before = $this->order();
        if ($stage === 'preflight_read') {
            $before['payments'][0]['paid_amount'] = '0';
        }
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $before));
        if ($stage !== 'preflight_read') {
            $receipt = $this->receipt('20.00');
            if ($stage === 'refund_request') {
                $receipt['payments'][0]['pp_payment_id'] = 'OTHER_SYNTHETIC_PAYMENT';
            }
            $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
                ->andReturn(new HttpResponse(200, [], '', $receipt));
            if ($stage === 'refund_confirmation_read') {
                $after = $this->order('20.00', null, '0.00', 'OTHER_SYNTHETIC_REFUND');
                $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                    ->andReturn(new HttpResponse(200, [], '', $after));
            }
        }
        $result = $client->refund($this->request());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertStringContainsString('refund.' . $stage, $message);
        $this->assertStringContainsString($expected, substr($message, 0, 180));
        $this->assertStringNotContainsString($expected, json_encode($result));
    }

    public static function refundContractStageProvider(): array
    {
        return [['preflight_read', 'payment.paid_amount conflicts'],
            ['refund_request', 'refund receipt.pp_payment_id does not match'],
            ['refund_confirmation_read', 'was not found in the refreshed refund history']];
    }

    /** @dataProvider diagnosticMutationProvider */
    public function testMutationFailuresPreserveOriginalDiagnosticWithoutChangingThePublicResult(string $operation, string $amount, bool $transport): void
    {
        $sink = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $message) use ($operation, $transport): bool {
            $this->assertSame('smart_pix_server_error', $name);
            $this->assertSame('true', $value);
            $this->assertSame($operation, $details['operation']);
            $this->assertSame($transport ? 'timeout' : 'http_5xx', $details['category']);
            $this->assertStringContainsString('Original provider failure', $message);
            $this->assertStringNotContainsString('SYNTHETIC_CREDENTIAL', $message);
            $this->assertStringNotContainsString('buyer@example.test', $message);
            return true;
        });
        [$client, $http] = $this->client(true, true, new SmartPixTelemetry($sink));
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $operation === 'cancel' ? $this->pendingOrder() : $this->order()));
        $write = $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), Mockery::any());
        if ($transport) {
            $write->andThrow(new TransportException('timeout', 'Original provider failure; token=SYNTHETIC_CREDENTIAL; buyer@example.test'));
        } else {
            $write->andReturn(new HttpResponse(503, ['x-request-id' => 'mutation-error-request'], '', [
                'message' => 'Original provider failure; token=SYNTHETIC_CREDENTIAL; buyer@example.test',
                'cause' => [['code' => 'provider_dependency_failed', 'description' => 'Original cause']],
            ]));
        }
        $request = $this->request($amount);
        if ($operation === 'cancel') {
            $request['refund_amount'] = null;
        }
        $result = $client->$operation($request);
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertStringNotContainsString('Original provider failure', json_encode($result));
        $this->assertSame($transport ? null : 'mutation-error-request', $client->requestDetails()['request_id'] ?? null);
    }

    public static function diagnosticMutationProvider(): array
    {
        return [['cancel', '20.00', false], ['refund', '20.00', false], ['refund', '5.00', false],
            ['cancel', '20.00', true], ['refund', '20.00', true], ['refund', '5.00', true]];
    }

    /** @dataProvider sequentialRefundProvider */
    public function testSequentialRefundsShareThePaymentIdButHaveDistinctRefundOrderIds(string $total, string $movement, string $previous): void
    {
        [$client, $http] = $this->client();
        $raw = $this->order($total, $movement, $previous);
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('success', $result['outcome']);
        $this->assertSame($total, $result['refund_context']['refunded_amount']);
        $this->assertSame($total === '20.00' ? 'refunded' : 'approved', $result['payments'][0]['state']);
    }

    public static function sequentialRefundProvider(): array
    {
        return ['two partial refunds' => ['10.00', '5.00', '5.00'], 'partial then remaining refund' => ['20.00', '15.00', '5.00']];
    }

    /** @dataProvider invalidRefundHistoryProvider */
    public function testInvalidRefundHistoryCannotBeAcceptedEvenWhenItsTotalsMatch(string $case): void
    {
        [$client, $http] = $this->client();
        $raw = $this->order('20.00', '15.00', '5.00');
        switch ($case) {
            case 'duplicate reference':
                $raw['payments'][0]['refunds'][1]['references'] = $raw['payments'][0]['refunds'][0]['references'];
                break;
            case 'missing reference':
                unset($raw['payments'][0]['refunds'][1]['references']);
                break;
            case 'invalid reference':
                $raw['payments'][0]['refunds'][1]['references']['refund_order_id'] = [];
                break;
            case 'different payment':
                $raw['payments'][0]['refunds'][1]['transaction_id'] = 'PAYMENT_OTHER';
                break;
            case 'unprocessed refund':
                $raw['payments'][0]['refunds'][1]['status'] = 'processing';
                break;
            case 'excessive total':
                $raw['payments'][0]['refunds'][1]['amount'] = '16.00';
                break;
        }
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertArrayNotHasKey('refund_context', $result);
    }

    public static function invalidRefundHistoryProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'duplicate reference', 'missing reference', 'invalid reference', 'different payment',
            'unprocessed refund', 'excessive total',
        ]);
    }

    public function testAnUnpaidLegacyEnvelopeCannotAuthorizeAnOrdersCancellation(): void
    {
        [$client, $http] = $this->client();
        $raw = $this->pendingOrder();
        unset($raw['site_id']);
        $raw['country_code'] = 'BRA';
        $raw['total_amount'] = $raw['payments'][0]['amount'] = '20.00';
        $raw['total_paid_amount'] = '0.00';
        $raw['transactions'] = ['payments' => $raw['payments']];
        unset($raw['payments']);
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        $request['refund_amount'] = null;
        $dispatched = false;
        $result = $client->cancel($request, static function () use (&$dispatched): void {
            $dispatched = true;
        });
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertFalse($dispatched);
    }

    public function testRefundResultAndDurationCorrelateTheOriginatingSdkAndTheLastAuthoritativeRequest(): void
    {
        $context = new SmartPixMetricContext();
        $telemetry = new SmartPixTelemetry(new SmartPixCoreMonitorMetricSink('homol', '9.8.1', MP_VERSION, $context), null, $context);
        [$client, $http, $seller] = $this->client(true, true, $telemetry);
        [$processor, $native] = $this->mutationProcessor($client, $seller, 'refund', $telemetry);
        $native->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)->andReturn('sdk-original-123');
        $http->shouldReceive('request')->twice()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, ['x-request-id' => 'read-before'], '', $this->order()));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, ['x-request-id' => 'refund-write'], '', $this->receipt('20.00')));
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, ['x-request-id' => 'read-confirmation'], '', $this->order('20.00')));
        WP_Mock::userFunction('wp_json_encode', ['return' => static function ($value) {
            return json_encode($value);
        }]);
        $metrics = [];
        WP_Mock::userFunction('wp_remote_post', ['times' => 2, 'return' => function ($url, $args) use (&$metrics) {
            $payload = json_decode($args['body'], true);
            $this->assertSame('smart_transfer', $payload['details']['payment_method']);
            $this->assertSame('sdk-original-123', $payload['details']['sdk_instance_id']);
            $this->assertSame('read-confirmation', $payload['details']['request_id']);
            $this->assertSame('GET /plugins-platforms/v1/orders/{id}', $payload['details']['api_route']);
            $this->assertStringNotContainsString('SYNTHETIC_CREDENTIAL', $args['body']);
            $metrics[] = $payload;
            return [];
        }]);
        $this->assertTrue($processor->refund($native, 'refund_42', '20.00')['success']);
        $this->assertSame('1', $metrics[0]['value']);
        $this->assertSame('refund_succeeded', $metrics[0]['details']['event']);
        $this->assertGreaterThan(0, $metrics[1]['value']);
        $this->assertGreaterThan(0, $metrics[1]['details']['request_time']);
    }

    public function testWaitingTransferCanBeCancelledWithoutDiscardingItsMutationContext(): void
    {
        [$client, $http] = $this->client(true, true);
        $pending = $this->pendingOrder();
        $pending['status'] = $pending['payments'][0]['status'] = 'action_required';
        $pending['status_detail'] = $pending['payments'][0]['status_detail'] = 'waiting_transfer';
        $cancelled = $pending;
        $cancelled['status'] = $cancelled['payments'][0]['status'] = 'canceled';
        $cancelled['status_detail'] = $cancelled['payments'][0]['status_detail'] = 'canceled';
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $pending));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $cancelled));
        $request = $this->request();
        $request['refund_amount'] = null;
        $result = $client->cancel($request);
        $this->assertSame('cancelled', $result['payments'][0]['state']);
        $this->assertSame('canceled', $result['observed']['payment_status']);
        $this->assertSame('0.00', $result['order_context']['refunded_amount']);
    }

    /** @dataProvider externalLifecycleProvider */
    public function testOrdinaryNotificationQueryRecognizesProviderLifecycleAndPreservesStatusFacts(string $state, string $status): void
    {
        [$client, $http] = $this->client();
        $raw = $state === 'refunded' ? $this->order('20.00') : $this->pendingOrder();
        if ($state === 'cancelled') {
            $raw['status'] = $raw['payments'][0]['status'] = $status;
            $raw['status_detail'] = $raw['payments'][0]['status_detail'] = $status;
        }
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;
        $result = $client->query($request);
        $this->assertSame($state, $result['payments'][0]['state']);
        $this->assertSame($state === 'cancelled' ? 'terminal_failure' : 'terminal_success', $result['state']);
        $this->assertNull($result['error']);
        $this->assertSame($raw['status'], $result['observed']['order_status']);
        $this->assertSame($raw['status_detail'], $result['observed']['payment_status_detail']);
        $this->assertArrayNotHasKey('order_context', $result);
        if ($state === 'refunded') {
            $this->assertSame('20.00', $result['refund_context']['refunded_amount']);
        }
    }

    public static function externalLifecycleProvider(): array
    {
        return [['cancelled', 'canceled'], ['cancelled', 'expired'], ['refunded', 'refunded']];
    }

    /** @dataProvider externalLifecycleProvider */
    public function testLifecycleReadNamesThePaymentSoANotificationCompletesTheReferencesOfAnUnreferencedLink(
        string $state,
        string $status
    ): void {
        [$client, $http] = $this->client();
        $raw = $state === 'refunded' ? $this->order('20.00') : $this->pendingOrder();
        if ($state === 'cancelled') {
            $raw['status'] = $raw['payments'][0]['status'] = $status;
            $raw['status_detail'] = $raw['payments'][0]['status_detail'] = $status;
        }
        $raw['payments'][0]['references'] = ['source' => 'mp_order', 'order_id' => 'ORDSYNTHETICPROVIDER001'];
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('PAYMENT_SYNTHETIC', $result['observed']['payment_id']);
        $this->assertSame('ORDSYNTHETICPROVIDER001', $result['observed']['payment_reference']);
        // The creation linked only the PPORD: the notification completes it from this read.
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('persistSmartPixReferences')->once()->with($order, [
            'order_reference' => 'ORDER_SYNTHETIC', 'payment_reference' => 'PAYMENT_SYNTHETIC',
            'provider_order_reference' => 'ORDSYNTHETICPROVIDER001',
        ]);
        $metadata->shouldReceive('getMpOrderId')->once()->with($order)->andReturn('ORDSYNTHETICPROVIDER001');
        $this->assertTrue((new SmartPixNotificationLinkage($metadata, Mockery::mock(Seller::class)))
            ->completeReferences($order, 'ORDER_SYNTHETIC', 'ORDSYNTHETICPROVIDER001', $result));
    }

    public function testConfirmedExpirationUsesTheUnpaidPaymentWhilePreservingProviderFacts(): void
    {
        [$client, $http] = $this->client();
        $raw = $this->pendingOrder();
        $raw['status'] = $raw['payments'][0]['status'] = 'expired';
        $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'expired';
        $raw['total_paid_amount'] = $raw['total_amount'];
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('terminal_failure', $result['state']);
        $this->assertSame('cancelled', $result['payments'][0]['state']);
        $this->assertSame('expired', $result['observed']['order_status']);
        $this->assertSame('expired', $result['observed']['payment_status']);
        $this->assertNull($result['error']);
    }

    /** @dataProvider invalidExpirationProvider */
    public function testExpirationCannotCancelAnUncorrelatedPaidOrInconsistentOrder(string $case): void
    {
        [$client, $http] = $this->client();
        $raw = $this->pendingOrder();
        $raw['status'] = $raw['payments'][0]['status'] = 'expired';
        $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'expired';
        switch ($case) {
            case 'detail':
                $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'accredited';
                break;
            case 'paid':
                $raw['total_paid_amount'] = $raw['payments'][0]['paid_amount'] = '20';
                break;
            case 'aggregate_partial_mismatch':
                $raw['total_paid_amount'] = '5';
                break;
            case 'partially_paid':
                $raw['total_paid_amount'] = '20';
                $raw['payments'][0]['paid_amount'] = '5';
                break;
            case 'canceled_aggregate_mismatch':
                $raw['status'] = $raw['payments'][0]['status'] = 'canceled';
                $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'canceled';
                $raw['total_paid_amount'] = '20';
                break;
            case 'refunded':
                $raw['payments'][0]['refunds'] = [['transaction_id' => 'REFUND_SYNTHETIC', 'amount' => '5', 'status' => 'processed']];
                break;
            case 'payment_status':
                $raw['payments'][0]['status'] = 'processing';
                break;
            case 'payment_detail':
                $raw['payments'][0]['status_detail'] = 'in_process';
                break;
            case 'reference':
                $raw['id'] = 'OTHER_ORDER';
                break;
            case 'attempt':
                $raw['external_reference'] = 'WC-999';
                break;
            case 'site':
                $raw['site_id'] = 'MLA';
                break;
            case 'legacy':
                unset($raw['site_id']);
                break;
        }
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame($case === 'legacy' ? 'unknown' : 'invalid_response', $result['error']['category']);
        $this->assertEmpty($result['payments']);
    }

    public static function invalidExpirationProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'detail', 'paid', 'aggregate_partial_mismatch', 'partially_paid', 'canceled_aggregate_mismatch',
            'refunded', 'payment_status', 'payment_detail', 'reference', 'attempt', 'site', 'legacy',
        ]);
    }

    public function testRefundSnapshotReadKeepsValidatedPartialHistoryForAdminComparison(): void
    {
        [$client, $http] = $this->client(true, true);
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('5.00')));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('approved', $result['payments'][0]['state']);
        $this->assertSame('partially_refunded', $result['observed']['payment_status_detail']);
        $this->assertSame('5.00', $result['order_context']['refunded_amount']);
        $this->assertArrayNotHasKey('refund_context', $result);
    }

    public function testOrdinaryNotificationReadPreservesPartialRefundContextInsteadOfRejectingTheState(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('5.00')));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('approved', $result['payments'][0]['state']);
        $this->assertSame('partially_refunded', $result['observed']['payment_status_detail']);
        $this->assertSame('5.00', $result['refund_context']['refunded_amount']);
        $this->assertArrayNotHasKey('order_context', $result);
    }

    public function testPartialRefundWithoutConfirmedHistoryCannotBecomeAnApproval(): void
    {
        [$client, $http] = $this->client();
        $raw = $this->order('5.00');
        $raw['payments'][0]['refunds'] = [];
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $raw));
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;

        $result = $client->query($request);

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertArrayNotHasKey('refund_context', $result);
    }

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider refundProvider */
    public function testNativeRefundUsesTheAuthenticatedPaymentAndConfirmsTheBalance(string $amount, string $previous, bool $test): void
    {
        [$client, $http] = $this->client($test);
        $path = '/plugins-platforms/v1/orders/ORDER_SYNTHETIC';
        $http->shouldReceive('request')->once()->ordered()->with('GET', $path, Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order($previous)));
        $http->shouldReceive('request')->once()->ordered()->withArgs(function ($method, $url, $headers, $body) use ($amount, $previous, $path): bool {
            $this->assertSame('POST', $method);
            $this->assertSame($path . '/refund', $url);
            $this->assertSame('Bearer SYNTHETIC_CREDENTIAL', $headers['Authorization']);
            $this->assertSame(MP_PLATFORM_ID, $headers['X-Platform-Id']);
            $this->assertSame(MP_PRODUCT_ID_DESKTOP, $headers['X-Product-Id']);
            $this->assertSame(str_repeat('a', 64), $headers['X-Idempotency-Key']);
            if (($previous === '0.00' && $amount === '20.00') || ($previous === '5.00' && $amount === '15.00')) {
                $this->assertNull($body);
            } else {
                $this->assertSame(['payments' => [['id' => 'PAYMENT_SYNTHETIC', 'amount' => $amount]]], json_decode($body, true));
            }
            return true;
        })->andReturn(new HttpResponse(200, [], '', $this->receipt($amount)));
        $cumulative = $amount === '20.00' || $amount === '15.00' ? '20.00' : '5.00';
        $http->shouldReceive('request')->once()->ordered()->with('GET', $path, Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order($cumulative, $amount, $previous)));
        $result = $client->refund($this->request($amount));
        $this->assertSame('success', $result['outcome']);
        $this->assertSame('terminal_success', $result['state']);
        $this->assertSame('refunded', $result['payments'][0]['state']);
        $this->assertSame($amount, $result['payments'][0]['amount']['value']);
        $this->assertSame($cumulative, $result['order_context']['refunded_amount']);
        $this->assertSame('12345', $result['order_context']['seller_reference']);
    }

    public static function refundProvider(): array
    {
        return [['20.00', '0.00', true], ['5.00', '0.00', true], ['15.00', '5.00', true], ['20.00', '0.00', false]];
    }

    /** @dataProvider invalidSnapshotProvider */
    public function testInvalidRemoteFactsStopBeforePosting(string $case): void
    {
        [$client, $http] = $this->client();
        $order = $this->order();
        switch ($case) {
            case 'reference':
                $order['id'] = 'OTHER_ORDER';
                break;
            case 'correlation':
                $order['external_reference'] = 'WC-999';
                break;
            case 'currency':
                $order['currency'] = 'USD';
                break;
            case 'site':
                $order['site_id'] = 'MLA';
                break;
            case 'amount':
                $order['payments'][0]['amount'] = '19';
                break;
            case 'float':
                $order['total_amount'] = 20.0;
                break;
            case 'method':
                $order['payments'][0]['payment_method']['id'] = 'pix';
                break;
            case 'multiple':
                $order['payments'][] = $order['payments'][0];
                break;
            case 'pending_refund':
                $order['payments'][0]['refunds'] = [['transaction_id' => 'REFUND_SYNTHETIC', 'amount' => '5', 'status' => 'processing']];
                break;
            case 'duplicate_refund':
                $order = $this->order('5.00');
                $order['payments'][0]['refunds'][] = $order['payments'][0]['refunds'][0];
                break;
        }
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $order));
        $result = $client->refund($this->request());
        $this->assertSame('failure', $result['outcome']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function invalidSnapshotProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['reference', 'correlation', 'currency', 'site', 'amount', 'float', 'method', 'multiple', 'pending_refund', 'duplicate_refund']);
    }

    /** @dataProvider failureProvider */
    public function testProviderErrorsArePrivateAndNeverRetried(int $status, string $category): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order()));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse($status, [], 'SYNTHETIC_PRIVATE', ['message' => 'SYNTHETIC_PRIVATE']));
        $result = $client->refund($this->request());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($result));
    }

    public static function failureProvider(): array
    {
        return [[400, 'validation'], [401, 'authentication'], [403, 'authentication'], [409, 'conflict'], [422, 'validation'], [429, 'rate_limit'], [500, 'unavailable'], [503, 'unavailable']];
    }

    /** @dataProvider incompleteRefundProvider */
    public function testHttpSuccessAloneCannotConfirmARefund(string $case): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order()));
        $receipt = $this->receipt('20.00');
        if ($case === 'wrong_order') {
            $receipt['pp_order_id'] = 'OTHER_ORDER';
        }
        if ($case === 'wrong_payment') {
            $receipt['payments'][0]['pp_payment_id'] = 'OTHER_PAYMENT';
        }
        if ($case === 'wrong_amount') {
            $receipt['total_amount'] = '19';
        }
        if ($case === 'pending') {
            $receipt['payments'][0]['status'] = 'processing';
        }
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $case === 'malformed' ? null : $receipt));
        if (in_array($case, ['stale_balance', 'query_unavailable', 'different_refund'], true)) {
            $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                ->andReturn(new HttpResponse($case === 'query_unavailable' ? 503 : 200, [], '', $case === 'different_refund' ? $this->order('20.00', '20.00', '0.00', 'OTHER_REFUND') : $this->order()));
        }
        $result = $client->refund($this->request());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function incompleteRefundProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['wrong_order', 'wrong_payment', 'wrong_amount', 'pending', 'malformed', 'stale_balance', 'query_unavailable', 'different_refund']);
    }

    public function testTimeoutPreservesUncertaintyWithoutRepeatingPost(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order()));
        $http->shouldReceive('request')->once()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andThrow(new TransportException('timeout'));
        $result = $client->refund($this->request());
        $this->assertSame('timeout', $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    /** @dataProvider invalidIntentProvider */
    public function testInvalidIntentStopsBeforeHttp(string $case): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        if ($case === 'seller') {
            $request['operation_context']['seller_reference'] = '99999';
        }
        if ($case === 'key') {
            $request['operation_context']['idempotency_key'] = 'CREATION_KEY';
        }
        if ($case === 'float') {
            $request['refund_amount']['value'] = 20.0;
        }
        if ($case === 'currency') {
            $request['refund_amount']['currency'] = 'USD';
        }
        if ($case === 'zero') {
            $request['refund_amount']['value'] = '0.00';
        }
        if ($case === 'extra') {
            $request['url'] = 'https://example.test';
        }
        $http->shouldNotReceive('request');
        $this->assertSame('validation', $client->refund($request)['error']['category']);
    }

    public static function invalidIntentProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], ['seller', 'key', 'float', 'currency', 'zero', 'extra']);
    }

    /** @dataProvider cancellationProvider */
    public function testCancellationPreservesApprovalRacesAndRequiresRemoteTerminalState(string $readState, string $postState): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $before = $this->order();
        if ($readState !== 'processed') {
            $before['status'] = $before['payments'][0]['status'] = $readState;
            $before['status_detail'] = $before['payments'][0]['status_detail'] = $readState === 'expired' ? 'expired' : 'in_process';
            $before['total_paid_amount'] = $before['payments'][0]['paid_amount'] = '0';
        }
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $before));
        if ($readState === 'processing') {
            $after = $this->order();
            if ($postState !== 'processed') {
                $after['status'] = $after['payments'][0]['status'] = $postState;
                $after['status_detail'] = $after['payments'][0]['status_detail'] = $postState === 'expired' ? 'expired' : 'in_process';
                $after['total_paid_amount'] = $after['payments'][0]['paid_amount'] = '0';
            }
            $http->shouldReceive('request')->once()->ordered()->withArgs(function ($method, $path, $headers, $body): bool {
                $this->assertSame('POST', $method);
                $this->assertSame('/plugins-platforms/v1/orders/ORDER_SYNTHETIC/cancel', $path);
                $this->assertSame(str_repeat('a', 64), $headers['X-Idempotency-Key']);
                $this->assertNull($body);
                return true;
            })->andReturn(new HttpResponse(200, [], '', $after));
        }
        $result = $client->cancel($request);
        $expected = $readState === 'processed' || $postState === 'processed' ? 'approved' : 'cancelled';
        $this->assertSame($expected, $result['payments'][0]['state']);
        $this->assertSame($expected === 'approved' ? 'terminal_success' : 'terminal_failure', $result['state']);
    }

    public static function cancellationProvider(): array
    {
        return [
            ['processing', 'canceled'], ['processed', 'processed'], ['processing', 'processed'], ['canceled', 'canceled'],
            ['processing', 'expired'], ['expired', 'expired'],
        ];
    }

    public function testAmountAboveRemainingBalanceCannotSendAPost(): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('5.00')));
        $this->assertSame('validation', $client->refund($this->request('20.00'))['error']['category']);
    }

    /** @dataProvider failureProvider */
    public function testCancellationFailuresNeverAuthorizeATerminalNativeEffect(int $status, string $category): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $before = $this->order();
        $before['status'] = $before['payments'][0]['status'] = 'processing';
        $before['status_detail'] = $before['payments'][0]['status_detail'] = 'in_process';
        $before['total_paid_amount'] = $before['payments'][0]['paid_amount'] = '0';
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $before));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse($status, [], 'SYNTHETIC_PRIVATE', ['message' => 'SYNTHETIC_PRIVATE']));
        $result = $client->cancel($request);
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', json_encode($result));
    }

    /** @dataProvider queryEnvelopeProvider */
    public function testOrdinaryQueryRetainsTheCoreEnvelopeWhileOperationsOptIntoSnapshot(bool $snapshot): void
    {
        [$client, $http] = $this->client(true, $snapshot);
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->order()));
        $result = $client->query($request);
        $this->assertSame('approved', $result['payments'][0]['state']);
        $this->assertSame($snapshot, array_key_exists('order_context', $result));
    }

    public static function queryEnvelopeProvider(): array
    {
        return [[true], [false]];
    }

    /** @dataProvider queryEnvelopeProvider */
    public function testRejectedQueryUsesTheCanonicalContractAndIncludesMutationEvidenceOnlyWhenRequested(bool $snapshot): void
    {
        [$client, $http] = $this->client(true, $snapshot);
        $request = $this->request();
        unset($request['operation_context']);
        $request['refund_amount'] = null;
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->failedOrder()));
        $result = $client->query($request);
        $this->assertSame('terminal_failure', $result['state']);
        $this->assertSame('rejected', $result['payments'][0]['state']);
        $this->assertSame(['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', 'retryable' => false], $result['error']);
        $this->assertSame($snapshot, array_key_exists('order_context', $result));
        if ($snapshot) {
            $this->assertSame('12345', $result['order_context']['seller_reference']);
            $this->assertSame(['value' => '20.00', 'currency' => 'BRL'], $result['order_context']['amount']);
            $this->assertSame('0.00', $result['order_context']['refunded_amount']);
        }
    }

    /** @dataProvider invalidRejectionEvidenceProvider */
    public function testUnverifiedFailedOrderCannotAuthorizeACancellation(string $case): void
    {
        [$client, $http] = $this->client();
        $order = $this->failedOrder();
        switch ($case) {
            case 'aggregate':
                $order['total_paid_amount'] = '20';
                break;
            case 'payment paid':
                $order['payments'][0]['paid_amount'] = '20';
                break;
            case 'attempt':
                $order['external_reference'] = 'WC-OTHER';
                break;
            case 'order':
                $order['id'] = 'ORDER_OTHER';
                break;
            case 'multiple payments':
                $order['payments'][] = $order['payments'][0];
                break;
            case 'different state':
                $order['payments'][0]['status'] = 'processing';
                break;
            case 'method':
                $order['payments'][0]['payment_method']['id'] = 'pix';
                break;
            case 'refunded':
                $order['payments'][0]['refunds'] = $this->order('5.00')['payments'][0]['refunds'];
                break;
        }
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $order));
        $request = $this->request();
        $request['refund_amount'] = null;
        $result = $client->cancel($request);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertArrayNotHasKey('order_context', $result);
    }

    public static function invalidRejectionEvidenceProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'aggregate', 'payment paid', 'attempt', 'order', 'multiple payments', 'different state', 'method', 'refunded',
        ]);
    }

    /** @dataProvider rejectionReadRaceProvider */
    public function testRejectionAtAnyCancellationBoundaryIsAppliedOnlyOnce(int $pendingReads): void
    {
        [$client, $http, $seller] = $this->client(true, true);
        $applied = [];
        [$processor, $native, $journal] = $this->mutationProcessor(
            $client,
            $seller,
            'cancel',
            null,
            [],
            static function ($id, $key, $response) use (&$applied): array {
                $applied[] = $response;
                return ['outcome' => 'applied', 'status' => 'failed'];
            }
        );
        if ($pendingReads > 0) {
            $http->shouldReceive('request')->times($pendingReads)->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                ->andReturn(new HttpResponse(200, [], '', $this->pendingOrder()));
        }
        $response = new HttpResponse(200, [], '', $this->failedOrder());
        if ($pendingReads === 2) {
            $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
                ->andReturn($response);
        } else {
            $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                ->andReturn($response);
        }

        $this->assertTrue($processor->cancel($native)['success']);
        $this->assertTrue($processor->cancel($native)['success']);
        $this->assertCount(1, $applied);
        $this->assertSame('rejected', $applied[0]['payments'][0]['state']);
        $this->assertSame('succeeded', array_values($journal())[0]['status']);
    }

    public static function rejectionReadRaceProvider(): array
    {
        return [[0], [1], [2]];
    }

    /** @dataProvider legacyCancellationRecoveryProvider */
    public function testCorrectedFailedOrderCanBeReconciledAfterAnInvalidReadWithoutRepeatingCancellation(bool $legacy): void
    {
        // Given either an old rejected reservation or a fresh read with contradictory paid totals.
        [$client, $http, $seller] = $this->client(true, true);
        $id = hash('sha256', 'SYNTHETIC_ATTEMPT|cancel');
        $records = $legacy ? [$id => [
            'id' => $id, 'key' => str_repeat('a', 64), 'status' => 'rejected', 'operation' => 'cancel',
            'attempt_key' => 'SYNTHETIC_ATTEMPT', 'amount' => '0.00', 'order_reference' => 'ORDER_SYNTHETIC',
            'attempt_reference' => 'WC-321', 'seller' => '12345', 'total' => '20.00', 'currency' => 'BRL',
        ]] : [];
        $applied = [];
        [$processor, $native, $journal] = $this->mutationProcessor(
            $client,
            $seller,
            'cancel',
            null,
            $records,
            static function ($id, $key, $response) use (&$applied): array {
                $applied[] = $response;
                return ['outcome' => 'applied', 'status' => 'failed'];
            }
        );
        $invalid = $this->failedOrder();
        $invalid['total_paid_amount'] = '20';
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $invalid));
        $this->assertSame('invalid_response', $processor->cancel($native)['reason']);
        $this->assertSame($legacy ? 'rejected' : 'read_failed', $journal()[$id]['status']);
        $this->assertSame([], $applied);

        // When the authenticated provider read becomes coherent, then reconcile once, without a POST.
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->failedOrder()));
        $this->assertTrue($processor->cancel($native)['success']);
        $this->assertTrue($processor->cancel($native)['success']);
        $this->assertCount(1, $applied);
        $this->assertCount(1, $journal());
        $this->assertSame('succeeded', $journal()[$id]['status']);
    }

    public static function legacyCancellationRecoveryProvider(): array
    {
        return [[false], [true]];
    }

    /** @dataProvider transportFailureProvider */
    public function testCancellationTransportFailureNeverRetriesTheWrite(string $message, string $category): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->pendingOrder()));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andThrow(new TransportException($message));
        $result = $client->cancel($request);
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    /** @dataProvider transportFailureProvider */
    public function testLostRefundConfirmationRemainsUncertainWithoutRepeatingPost(string $message, string $category): void
    {
        [$client, $http] = $this->client();
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order()));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $this->receipt('20.00')));
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andThrow(new TransportException($message));
        $result = $client->refund($this->request());
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame($category, $result['error']['category']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function transportFailureProvider(): array
    {
        return [['timeout', 'timeout'], ['connection_unavailable', 'unavailable']];
    }

    public function testUnusableCancellationReadNeverSendsAPost(): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(503, [], 'PRIVATE_SENTINEL', null));
        $result = $client->cancel($request);
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('unavailable', $result['error']['category']);
        $this->assertTrue($result['error']['retryable']);
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($result));
    }

    public function testRefundedOrderCannotBeCancelled(): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $http->shouldReceive('request')->once()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('20.00')));
        $this->assertSame('conflict', $client->cancel($request)['error']['category']);
    }

    /** @dataProvider cancellationMissingCredentialProvider */
    public function testMutationsWithoutACredentialCannotUseHttp(string $operation): void
    {
        [$client, $http, $seller] = $this->client();
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('');
        $http->shouldNotReceive('request');
        $request = $this->request();
        if ($operation === 'cancel') {
            $request['refund_amount'] = null;
        }
        $this->assertSame('authentication', $client->{$operation}($request)['error']['category']);
    }

    public static function cancellationMissingCredentialProvider(): array
    {
        return [['refund'], ['cancel']];
    }

    /** @dataProvider uncorrelatedCancellationProvider */
    public function testSuccessfulHttpCancellationRequiresTerminalCorrelatedFacts(string $case): void
    {
        [$client, $http] = $this->client();
        $request = $this->request();
        $request['refund_amount'] = null;
        $after = $this->pendingOrder();
        if ($case !== 'pending') {
            $after['status'] = $after['payments'][0]['status'] = 'canceled';
            $after['status_detail'] = $after['payments'][0]['status_detail'] = 'canceled';
        }
        if ($case === 'payment') {
            $after['payments'][0]['id'] = 'OTHER_PAYMENT';
        }
        if ($case === 'amount') {
            $after['total_amount'] = $after['payments'][0]['amount'] = '21';
        }
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->pendingOrder()));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $after));
        $result = $client->cancel($request);
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertFalse($result['error']['retryable']);
    }

    public static function uncorrelatedCancellationProvider(): array
    {
        return [['pending'], ['payment'], ['amount']];
    }

    /** @dataProvider preDispatchReadProvider */
    public function testSecondReadFailureAllowsAnExplicitRetryWithoutAnyInitialPost(string $operation, string $failure, string $reason): void
    {
        [$client, $http, $seller] = $this->client(true, true);
        [$processor, $native, $journal] = $this->mutationProcessor($client, $seller, $operation);
        $before = $operation === 'refund' ? $this->order() : $this->pendingOrder();
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $before));
        $read = $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'));
        if (in_array($failure, ['timeout', 'connection_unavailable'], true)) {
            $read->andThrow(new TransportException($failure));
        } else {
            $read->andReturn(new HttpResponse((int) $failure, [], 'PRIVATE_SENTINEL', null));
        }
        $first = $operation === 'refund' ? $processor->refund($native, 'refund_42', '20.00') : $processor->cancel($native);
        $this->assertSame(['success' => false, 'reason' => $reason], $first);
        $record = array_values($journal())[0];
        $this->assertSame('read_failed', $record['status']);

        // Only the next explicit caller starts another intent; the failed attempt sent zero POSTs.
        $http->shouldReceive('request')->twice()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $before));
        $after = $this->pendingOrder();
        $after['status'] = $after['payments'][0]['status'] = 'canceled';
        $after['status_detail'] = $after['payments'][0]['status_detail'] = 'canceled';
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $operation === 'refund' ? $this->receipt('20.00') : $after));
        if ($operation === 'refund') {
            $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                ->andReturn(new HttpResponse(200, [], '', $this->order('20.00')));
        }
        $retry = $operation === 'refund' ? $processor->refund($native, 'refund_42', '20.00') : $processor->cancel($native);
        $this->assertTrue($retry['success']);
        $this->assertCount(1, $journal());
        $retried = array_values($journal())[0];
        $this->assertSame('succeeded', $retried['status']);
        $this->assertNotSame($record['key'], $retried['key']);
    }

    public static function preDispatchReadProvider(): array
    {
        $cases = [];
        foreach (['refund', 'cancel'] as $operation) {
            foreach ([['503', 'unavailable'], ['429', 'rate_limit'], ['timeout', 'timeout'], ['connection_unavailable', 'unavailable']] as $failure) {
                $cases[] = array_merge([$operation], $failure);
            }
        }
        return $cases;
    }

    public function testBalanceChangingBetweenReadsRejectsWithoutPostAndAllowsACorrectedRefund(): void
    {
        [$client, $http, $seller] = $this->client(true, true);
        [$processor, $native, $journal] = $this->mutationProcessor($client, $seller, 'refund');
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order()));
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('5.00')));
        $this->assertSame('validation', $processor->refund($native, 'refund_42', '20.00')['reason']);
        $record = array_values($journal())[0];
        $this->assertSame('rejected', $record['status']);

        $http->shouldReceive('request')->twice()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('5.00')));
        $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null)
            ->andReturn(new HttpResponse(200, [], '', $this->receipt('15.00')));
        $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $this->order('20.00', '15.00', '5.00')));
        $this->assertTrue($processor->refund($native, 'refund_43', '15.00')['success']);
        $records = array_values($journal());
        $this->assertCount(2, $records);
        $this->assertSame('rejected', $records[0]['status']);
        $this->assertSame('succeeded', $records[1]['status']);
        $this->assertNotSame($records[0]['key'], $records[1]['key']);
    }

    /** @dataProvider uncertainMutationProvider */
    public function testPossiblePostDispatchBlocksBothTheSameAndAnotherIntent(string $operation, bool $lostConfirmation): void
    {
        [$client, $http, $seller] = $this->client(true, true);
        [$processor, $native, $journal] = $this->mutationProcessor($client, $seller, $operation);
        $http->shouldReceive('request')->twice()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
            ->andReturn(new HttpResponse(200, [], '', $operation === 'refund' ? $this->order() : $this->pendingOrder()));
        $post = $http->shouldReceive('request')->once()->ordered()->with('POST', Mockery::type('string'), Mockery::type('array'), null);
        if ($lostConfirmation) {
            $post->andReturn(new HttpResponse(200, [], '', $this->receipt('20.00')));
            $http->shouldReceive('request')->once()->ordered()->with('GET', Mockery::type('string'), Mockery::type('array'))
                ->andThrow(new TransportException('timeout'));
        } else {
            $post->andThrow(new TransportException('timeout'));
        }
        $result = $operation === 'refund' ? $processor->refund($native, 'refund_42', '20.00') : $processor->cancel($native);
        $this->assertSame('timeout', $result['reason']);
        $record = array_values($journal())[0];
        $this->assertSame('uncertain', $record['status']);
        foreach (['refund_42', 'refund_43'] as $identity) {
            $retry = $operation === 'refund' ? $processor->refund($native, $identity, '20.00') : $processor->cancel($native);
            $this->assertSame('operation_unresolved', $retry['reason']);
        }
        $this->assertSame([$record['id'] => $record], $journal());
    }

    public static function uncertainMutationProvider(): array
    {
        return [['refund', false], ['cancel', false], ['refund', true]];
    }

    /** Real processor, adapter and repository; only WooCommerce persistence and HTTP are mocked. */
    private function mutationProcessor(RealSmartPixOrderClient $client, Seller $seller, string $operation, ?SmartPixTelemetry $telemetry = null, array $records = [], ?callable $apply = null): array
    {
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $native = Mockery::mock(WC_Order::class);
        $native->shouldReceive('get_id')->andReturn(321);
        $native->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $native->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $native->shouldReceive('get_total')->with('edit')->andReturn('20.00');
        $native->shouldReceive('get_status')->with('edit')->andReturn($operation === 'refund' ? 'processing' : 'pending');
        $native->shouldReceive('is_paid')->andReturn($operation === 'refund');
        $native->shouldReceive('get_date_paid')->with('edit')->andReturn($operation === 'refund' ? new \stdClass() : null);
        $native->shouldReceive('get_refunds')->andReturn([]);
        $native->shouldReceive('read_meta_data')->with(true);
        $datastore = Mockery::mock();
        $datastore->shouldReceive('read')->with($native);
        $native->shouldReceive('get_data_store')->andReturn($datastore);
        $native->shouldNotReceive('save');
        $native->shouldNotReceive('payment_complete');
        $native->shouldNotReceive('update_status');
        $encoded = $records === [] ? '' : json_encode($records);
        $native->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$encoded): string {
                return $encoded;
            });
        $native->shouldReceive('update_meta_data')->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))
            ->andReturnUsing(static function ($field, $value) use (&$encoded): void {
                $encoded = $value;
            });
        $native->shouldReceive('save_meta_data');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $native]);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->andReturn('SYNTHETIC_ATTEMPT');
        $metadata->shouldReceive('getPpOrderId')->andReturn('ORDER_SYNTHETIC');
        $metadata->shouldReceive('getSmartPixAttemptReference')->andReturn('WC-321');
        $repository = new SmartPixOperationRepository(new SmartPixStatusLock(new SmartPixStatusLockDatabase(), 'operation_'));
        $processor = new SmartPixMutationProcessor($client, $metadata, $seller, $repository, static function (): void {
        }, $apply ?? static function (): array {
            return ['outcome' => 'applied', 'status' => 'cancelled'];
        }, $telemetry);
        return [$processor, $native, static function () use (&$encoded): array {
            return $encoded === '' ? [] : json_decode($encoded, true);
        }];
    }

    private function pendingOrder(): array
    {
        $order = $this->order();
        $order['status'] = $order['payments'][0]['status'] = 'processing';
        $order['status_detail'] = $order['payments'][0]['status_detail'] = 'in_process';
        $order['total_paid_amount'] = $order['payments'][0]['paid_amount'] = '0';
        return $order;
    }

    private function failedOrder(): array
    {
        $order = $this->pendingOrder();
        $order['status'] = $order['payments'][0]['status'] = 'failed';
        $order['status_detail'] = $order['payments'][0]['status_detail'] = 'insufficient_amount';
        return $order;
    }

    private function client(bool $test = true, bool $snapshot = false, ?SmartPixTelemetry $telemetry = null): array
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_CREDENTIAL')->byDefault();
        $seller->shouldReceive('getCustIdFromAT')->andReturn('12345');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn($test);
        return [new RealSmartPixOrderClient($http, $seller, $store, $telemetry, 'pending_query', null, $snapshot), $http, $seller];
    }

    private function request(string $amount = '20.00'): array
    {
        return [
            'surface' => 'server', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321', 'order_reference' => 'ORDER_SYNTHETIC', 'amount' => null,
            'refund_amount' => ['value' => $amount, 'currency' => 'BRL'],
            'operation_context' => ['contract_version' => 'smart-pix-mutations/v1', 'idempotency_key' => str_repeat('a', 64), 'seller_reference' => '12345'],
        ];
    }

    private function order(string $refunded = '0.00', ?string $movement = null, string $previous = '0.00', string $reference = 'REFUND_SYNTHETIC'): array
    {
        $status = $refunded === '20.00' ? 'refunded' : 'processed';
        $detail = $refunded === '0.00' ? 'accredited' : ($refunded === '20.00' ? 'refunded' : 'partially_refunded');
        return [
            'id' => 'ORDER_SYNTHETIC', 'external_reference' => 'WC-321', 'site_id' => 'MLB', 'currency' => 'BRL',
            'type' => 'online', 'status' => $status, 'status_detail' => $detail, 'total_amount' => '20', 'total_paid_amount' => '20',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '20', 'paid_amount' => '20', 'status' => $status, 'status_detail' => $detail,
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
                'refunds' => $refunded === '0.00' ? [] : array_merge(
                    $previous === '0.00' ? [] : [['transaction_id' => 'PAYMENT_SYNTHETIC', 'amount' => $previous, 'status' => 'processed', 'references' => ['refund_order_id' => 'REFUND_PREVIOUS']]],
                    [['transaction_id' => 'PAYMENT_SYNTHETIC', 'amount' => $movement ?? $refunded, 'status' => 'processed', 'references' => ['refund_order_id' => $reference]]]
                ),
            ]],
        ];
    }

    private function receipt(string $amount): array
    {
        return ['pp_order_id' => 'ORDER_SYNTHETIC', 'total_amount' => $amount, 'payments' => [[
            'pp_payment_id' => 'PAYMENT_SYNTHETIC', 'status' => 'processed', 'amount' => $amount,
            'reference' => ['refund_order_id' => 'REFUND_SYNTHETIC'],
        ]]];
    }
}

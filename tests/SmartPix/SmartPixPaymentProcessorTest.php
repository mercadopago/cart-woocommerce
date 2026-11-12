<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixPaymentProcessor;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixValidator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationTiming;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixPaymentProcessorTest extends TestCase
{
    use SmartPixOrderDetailsFixture;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        WP_Mock::userFunction('get_site_url', ['return' => 'https://store.example.test']);
        WP_Mock::userFunction('home_url', ['return' => 'https://store.example.test']);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider dispatchedTransportFailureProvider */
    public function testFailureAfterDispatchNeverRequestsSuccessAndCannotCreateAgain(bool $throws): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::FAILURE_META_KEY, true, 'edit')->andReturn('');
        $started = '';
        $events = [];
        $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$started): string { return $started; });
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->with($order, 'CANDIDATE_KEY')->twice()->andReturn([
            'payment_contract' => 'orders_smart_pix_v1', 'idempotency_key' => 'STORED_KEY',
        ]);
        $creation = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::class);
        $creation->shouldReceive('begin')->with($order, 'STORED_KEY')->once()->andReturnUsing(static function () use (&$started, &$events): bool {
            $started = str_repeat('a', 64);
            $events[] = 'persisted';
            return true;
        });
        if ($throws) {
            $creation->shouldNotReceive('responseReceived');
        } else {
            $creation->shouldReceive('responseReceived')->with($order)->once();
        }
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldReceive('create')->once()->andReturnUsing(static function ($request, $payment, $onDispatch) use (&$events, $throws): array {
            $onDispatch();
            $events[] = 'http';
            if ($throws) {
                throw new \RuntimeException('synthetic_transport_failure');
            }
            return ['outcome' => 'failure', 'state' => 'processing', 'order_reference' => null,
                'payments' => [], 'error' => ['category' => 'timeout', 'retryable' => true]];
        });
        $processor = new SmartPixPaymentProcessor($validator, $repository, $client, $metadata, null, $creation,
            static function () use (&$events): void { $events[] = 'session_saved'; });
        $first = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $second = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('failure', $first['outcome']);
        $this->assertSame($throws ? 'unknown' : 'timeout', $first['error']['category']);
        $this->assertSame('failure', $second['outcome']);
        $this->assertSame('conflict', $second['error']['category']);
        $this->assertArrayNotHasKey('creation_pending', $first);
        $this->assertArrayNotHasKey('creation_pending', $second);
        $this->assertSame(['persisted', 'session_saved', 'http'], $events);
    }

    public static function dispatchedTransportFailureProvider(): array
    {
        return ['timeout response' => [false], 'exception after dispatch' => [true]];
    }

    /** @dataProvider dispatchedHttpFailureProvider */
    public function testDispatchedHttpFailuresKeepTheirCheckoutOutcomeWithoutAnotherPost(
        int $status,
        string $surface,
        ?string $expectedFailure,
        bool $persistFailure = true
    ): void {
        [$validator, $order, $seller] = $this->context();
        $data = [];
        $order->shouldReceive('get_meta')->andReturnUsing(static function (string $key) use (&$data) {
            return $data[$key] ?? '';
        });
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->twice()->andReturn([
            'payment_contract' => 'orders_smart_pix_v1', 'idempotency_key' => 'STORED_KEY',
        ]);
        $creation = Mockery::mock(SmartPixCreationStore::class);
        $creation->shouldReceive('begin')->once()->andReturnUsing(static function () use (&$data): bool {
            $data[SmartPixCreationStore::META_KEY] = str_repeat('a', 64);
            return true;
        });
        $creation->shouldReceive('responseReceived')->once();
        if (!$persistFailure) {
            $creation->shouldReceive('recordFailure')->once()->andThrow(new \RuntimeException('synthetic_write_failure'));
        } elseif ($expectedFailure !== null) {
            $creation->shouldReceive('recordFailure')->with($order, $expectedFailure)->once()
                ->andReturnUsing(static function () use (&$data, $expectedFailure): void {
                    $data[SmartPixCreationStore::FAILURE_META_KEY] = str_repeat('a', 64) . ':' . $expectedFailure;
                });
        } else {
            $creation->shouldNotReceive('recordFailure');
        }
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->withArgs(static function (string $method): bool {
            return $method === 'POST';
        })->andReturn(new HttpResponse($status, [], '', ['code' => 'CPP_OA_0000004']));
        $processor = new SmartPixPaymentProcessor(
            $validator,
            $repository,
            new RealSmartPixOrderClient($http, $seller, $this->pixStore()),
            $metadata,
            null,
            $creation
        );
        foreach ([1, 2] as $attempt) {
            $result = $processor->process($order, 321, $surface, $this->proof(), 'CANDIDATE_KEY', 'SERVER_ORDER_KEY');
            $category = $expectedFailure ?? [409 => 'conflict', 429 => 'rate_limit',
                500 => 'unavailable', 201 => 'invalid_response'][$status];
            if ($attempt === 2) {
                $category = $persistFailure && $expectedFailure !== null ? $expectedFailure : 'conflict';
            }
            $this->assertSame('failure', $result['outcome']);
            $this->assertSame($category, $result['error']['category']);
            $this->assertArrayNotHasKey('creation_pending', $result);
        }
    }

    public static function dispatchedHttpFailureProvider(): array
    {
        $cases = [];
        foreach (['classic', 'blocks', 'order_pay'] as $surface) {
            $statuses = [400 => 'validation', 401 => 'authentication', 403 => 'authentication',
                422 => 'validation', 409 => null, 429 => null, 500 => null, 201 => null];
            foreach ($statuses as $status => $category) {
                $cases[$surface . '_' . $status] = [$status, $surface, $category];
            }
        }
        $cases['failure_not_persisted'] = [422, 'classic', 'validation', false];
        return $cases;
    }

    public function testAnObservationWriteFailureCannotDiscardTheAuthenticatedCreationReference(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::FAILURE_META_KEY, true, 'edit')->andReturn('');
        $started = '';
        $reference = '';
        $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$started): string { return $started; });
        $metadata = $this->metricLinkedMetadata($order, $reference);
        $creation = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::class);
        $creation->shouldReceive('begin')->once()->andReturnUsing(static function () use (&$started): bool {
            $started = str_repeat('a', 64);
            return true;
        });
        $creation->shouldReceive('responseReceived')->once()->andThrow(new \RuntimeException('synthetic_probe_failure'));
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $response = $this->pendingResult();
        $client->shouldReceive('create')->once()->andReturnUsing(static function ($request, $payment, $onDispatch) use ($response): array {
            $onDispatch();
            return $response;
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, null, $creation);
        $this->assertSame($response, $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY'));
        $this->assertSame('ORDER_SYNTHETIC_001', $reference);
    }

    public function testLocalValidationBeforeDispatchLeavesTheAttemptRetryable(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')->andReturn('');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->twice()->andReturn([
            'payment_contract' => 'orders_smart_pix_v1', 'idempotency_key' => 'STORED_KEY',
        ]);
        $creation = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::class);
        $creation->shouldNotReceive('begin');
        $creation->shouldNotReceive('responseReceived');
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldReceive('create')->twice()->andReturn([
            'outcome' => 'failure', 'state' => 'error', 'order_reference' => null,
            'payments' => [], 'error' => ['category' => 'validation', 'retryable' => false],
        ]);
        $processor = new SmartPixPaymentProcessor($validator, $repository, $client, $metadata, null, $creation);
        foreach ([1, 2] as $attempt) {
            $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
            $this->assertArrayNotHasKey('creation_pending', $result);
            $this->assertSame('validation', $result['error']['category']);
        }
    }

    public function testPendingCreatePreservesLinkageWithoutEmittingDuplicateTiming(): void
    {
        [$validator, $order] = $this->context();
        $now = 10.0;
        $reference = '';
        $response = $this->pendingResult();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturnUsing(function (array $request, SmartPixAuthorizedPayment $payment) use (&$now, $response): array {
            $this->assertSame(['value' => '4.00', 'currency' => 'BRL'], $request['amount']);
            $this->assertSame('STORED_KEY', $payment->getIdempotencyKey());
            $now += 0.25;
            return $response;
        });
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturnUsing(static function () use (&$reference): string {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()
            ->globally()->ordered()->andReturnUsing(static function ($order, string $value) use (&$reference, &$now): void {
                $reference = $value;
                $now += 100.0;
            });
        $details = ['operation' => 'create', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        // Creation is measured only by its duration; the creation result has no counter.
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $telemetry);
        $this->assertSame($response, $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY'));
        $this->assertSame('ORDER_SYNTHETIC_001', $reference);
    }

    public function testProcessorLeavesCreationTimingToTheGateway(): void
    {
        [$validator, $order] = $this->context();
        $now = 10.0;
        $response = $this->pendingResult();
        $client = Mockery::mock(SmartPixOrderClient::class . ', ' . SmartPixCreationTiming::class);
        $client->shouldReceive('create')->once()->andReturnUsing(static function () use (&$now, $response): array {
            $now += 0.25;
            // Model observation performed inside the adapter after its transport has returned.
            $now += 100.0;
            return $response;
        });
        $client->shouldNotReceive('creationDurationSeconds');
        $reference = '';
        $metadata = $this->metricLinkedMetadata($order, $reference);
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $telemetry);
        $this->assertSame($response, $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY'));
    }

    public function testRealAdapterHttpDurationExcludesItsSlowErrorTelemetryAndReportsThatErrorOnce(): void
    {
        [$validator, $order, $seller] = $this->context();
        $now = 10.0;
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function (string $method, string $path, array $headers, string $body) use (&$now): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('/plugins-platforms/v1/orders', $path);
            $this->assertSame('4.00', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['transaction_amount']);
            $now += 0.25;
            return new HttpResponse(500, [], '', ['message' => 'SYNTHETIC_PRIVATE_PROVIDER_MESSAGE']);
        });
        $counts = [];
        $samples = [];
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(static function (string $name, string $value, array $details) use (&$now, &$counts): void {
            $counts[] = [$name, $value, $details];
            if ($name === SmartPixMetricSchema::ERROR) {
                $now += 100.0;
            }
        });
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        $store = $this->pixStore();
        $client = new RealSmartPixOrderClient($http, $seller, $store, $telemetry);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $telemetry);
        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $details = $this->metricDetails();
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame([
            [SmartPixMetricSchema::ERROR, 'true', $details + ['category' => 'http_5xx']],
        ], $counts);
        $this->assertSame([], $samples);
        $this->assertStringNotContainsString('PRIVATE', json_encode([$result, $counts, $samples]));
    }

    /** @dataProvider accreditedCreateProvider */
    public function testAccreditedCreatePreservesSuccessAndLinksTheOrderWithoutNativeEffects(string $surface, int $httpStatus): void
    {
        [$validator, $order, $seller] = $this->context();
        $reference = '';
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(4)->andReturnUsing(function () use (&$reference) {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'), [
                'order_reference' => 'ORDER_SYNTHETIC_001', 'payment_reference' => 'PAYMENT_SYNTHETIC_001',
            ])->once()
            ->andReturnUsing(function ($order, string $value) use (&$reference): void {
                $reference = $value;
            });
        $metadata->shouldReceive('getPpPaymentId')->with($order)->once()->andReturn('PAYMENT_SYNTHETIC_001');
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $body): bool {
            $this->assertSame('POST', $method);
            $this->assertSame('/plugins-platforms/v1/orders', $path);
            $this->assertSame('STORED_KEY', $headers['X-Idempotency-Key']);
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('WC-321', $decoded['external_reference']);
            $this->assertSame('4.00', $decoded['transaction_amount']);
            return true;
        })->andReturn(new HttpResponse($httpStatus, [], '', $this->ppResponse(true)));
        $store = $this->pixStore();
        $client = new RealSmartPixOrderClient($http, $seller, $store);
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->with($order, 'CANDIDATE_KEY')->twice()->andReturn([
            'payment_contract' => 'orders_smart_pix_v1', 'idempotency_key' => 'STORED_KEY',
        ]);
        $processor = new SmartPixPaymentProcessor($validator, $repository, $client, $metadata);
        $orderKey = $surface === 'order_pay' ? 'SERVER_ORDER_KEY' : null;
        $result = $processor->process($order, 321, $surface, $this->proof(), 'CANDIDATE_KEY', $orderKey);

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('terminal_success', $result['state']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertSame($result['order_reference'], $reference);
        $this->assertSame('approved', $result['payments'][0]['state']);
        $this->assertSame(['value' => '4.00', 'currency' => 'BRL'], $result['payments'][0]['amount']);
        $this->assertNull($result['error']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode($result));

        $replayed = $processor->process($order, 321, $surface, $this->proof(), 'CANDIDATE_KEY', $orderKey);
        $this->assertSame('failure', $replayed['outcome']);
        $this->assertSame('conflict', $replayed['error']['category']);
        $this->assertSame($reference, $replayed['order_reference']);
        $this->assertSame([], $replayed['payments']);
    }

    public function testAccreditedCreateWithRefundsKeepsKnownOrderWithoutApproval(): void
    {
        [$validator, $order, $seller] = $this->context();
        $reference = '';
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturnUsing(function () use (&$reference) {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()
            ->andReturnUsing(function ($order, string $value) use (&$reference): void {
                $reference = $value;
            });
        $response = $this->ppResponse(true);
        $response['payments'][0]['refunds'] = [[
            'transaction_id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '4.00', 'status' => 'processed',
            'references' => ['refund_payment_id' => 'SYNTHETIC_PRIVATE_REFUND'],
        ]];
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, [], '', $response));
        $store = $this->pixStore();
        $processor = new SmartPixPaymentProcessor(
            $validator,
            $this->storedAttempt($order),
            new RealSmartPixOrderClient($http, $seller, $store),
            $metadata
        );

        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('error', $result['state']);
        $this->assertSame('unknown', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertSame('ORDER_SYNTHETIC_001', $reference);
        $this->assertSame([], $result['payments']);
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE_REFUND', json_encode($result));
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode($result));
    }

    public static function accreditedCreateProvider(): array
    {
        return [
            ['classic', 201], ['classic', 200], ['blocks', 201], ['blocks', 200],
            ['order_pay', 201], ['order_pay', 200],
        ];
    }

    /** @dataProvider linkedQueryScenarioProvider */
    public function testPendingPostLinksTheOrderForAnAccreditedGetWithoutNativeEffects(string $scenario, ?string $category): void
    {
        [$validator, $order, $seller] = $this->context();
        $reference = '';
        $order->shouldReceive('read_meta_data')->with(true)->times($category === null ? 3 : 4);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturnUsing(function () use (&$reference) {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'), [
                'order_reference' => 'ORDER_SYNTHETIC_001', 'payment_reference' => 'PAYMENT_SYNTHETIC_001',
            ])->once()
            ->andReturnUsing(function ($order, string $value) use (&$reference): void {
                $reference = $value;
            });
        $metadata->shouldReceive('getPaymentContract')->with($order)->times($category === null ? 1 : 2)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->times($category === null ? 1 : 2)->andReturn('STORED_KEY');
        $metadata->shouldReceive('getPpPaymentId')->with($order)->once()->andReturn('PAYMENT_SYNTHETIC_001');
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->withArgs(function (string $method, string $path, array $headers, string $body): bool {
            $this->assertSame('POST', $method);
            $this->assertSame('/plugins-platforms/v1/orders', $path);
            $this->assertSame('STORED_KEY', $headers['X-Idempotency-Key']);
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('WC-321', $decoded['external_reference']);
            $this->assertSame('4.00', $decoded['transaction_amount']);
            $this->assertSame('4.00', $decoded['payments'][0]['amount']);
            return true;
        })->andReturn(new HttpResponse(201, [], '', $this->ppResponse(false)));
        $get = $http->shouldReceive('request')->times($category === null ? 1 : 2)->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC_001', [
            'Authorization' => 'Bearer SYNTHETIC_CREDENTIAL', 'Accept' => 'application/json', 'X-Platform-Id' => MP_PLATFORM_ID,
        ]);
        $approved = $this->ppResponse(true);
        $invalid = $approved;
        if ($scenario === 'amount') {
            $invalid['payments'][0]['paid_amount'] = '3.99';
        } elseif ($scenario === 'attempt') {
            $invalid['external_reference'] = 'WC-999';
        } elseif ($scenario === 'state') {
            $invalid['status_detail'] = 'unknown';
        } elseif ($scenario === 'mixed') {
            $invalid['transactions'] = ['payments' => $invalid['payments']];
        }
        if ($category !== null) {
            $get->andReturn(new HttpResponse(200, [], '', $invalid), new HttpResponse(200, [], '', $approved));
        } else {
            $get->andReturn(new HttpResponse(200, [], '', $approved));
        }
        $store = $this->pixStore();
        $client = new RealSmartPixOrderClient($http, $seller, $store);
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata);
        $created = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('success', $created['outcome']);
        $this->assertSame('processing', $created['state']);
        $this->assertSame('ORDER_SYNTHETIC_001', $reference);
        $query = new SmartPixOrderQuery($client, $metadata, $seller);
        if ($category !== null) {
            $rejected = $query->query($order, 'server');
            $this->assertSame('failure', $rejected['outcome']);
            $this->assertSame($category, $rejected['error']['category']);
            $this->assertSame('ORDER_SYNTHETIC_001', $rejected['order_reference']);
            $this->assertSame('ORDER_SYNTHETIC_001', $reference);
            $this->assertSame([], $rejected['payments']);
        }
        $queried = $query->query($order, 'server');
        $this->assertSame('terminal_success', $queried['state']);
        $this->assertSame(['value' => '4.00', 'currency' => 'BRL'], $queried['payments'][0]['amount']);
        $decision = (new SmartPixStateReducer())->reduce('pending', false, '0.00', $queried, ['value' => '4.00', 'currency' => 'BRL']);
        $this->assertSame('propose_effects', $decision['decision']);
        $this->assertSame(['complete_payment'], $decision['effects']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode([$created, $queried, $decision]));
    }

    public static function linkedQueryScenarioProvider(): array
    {
        return [
            ['valid', null], ['amount', 'invalid_response'], ['attempt', 'linkage_mismatch'],
            ['state', 'unknown'], ['mixed', 'invalid_response'],
        ];
    }

    public function testValidatedOrderPersistsBeforeCreateAndLinksPendingResponseWithoutPaymentEffects(): void
    {
        $events = [];
        $contract = '';
        $key = '';
        $reference = '';
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->twice()->andReturn(321);
        $order->shouldReceive('get_payment_method')->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->once()->andReturn(false);
        $order->shouldReceive('needs_payment')->once()->andReturn(true);
        $order->shouldReceive('get_currency')->once()->andReturn('BRL');
        $order->shouldReceive('get_total')->once()->andReturn('4.00');
        $this->stubOrderDetails($order);
        $order->shouldReceive('read_meta_data')->with(true)->times(4);
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $order->shouldNotReceive('add_order_note');

        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $seller->shouldNotReceive('getCollectorId');
        $seller->shouldReceive('getCredentialsAccessToken')->once()->andReturn('SYNTHETIC_CREDENTIAL');

        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->twice()->andReturnUsing(function () use (&$contract) {
            return $contract;
        });
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->twice()->andReturnUsing(function () use (&$key) {
            return $key;
        });
        $metadata->shouldReceive('persistSmartPixAttempt')->with($order, 'SYNTHETIC_KEY')->once()->andReturnUsing(function () use (&$contract, &$key, &$events) {
            $contract = 'orders_smart_pix_v1';
            $key = 'SYNTHETIC_KEY';
            $events[] = 'persist_attempt';
        });
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturnUsing(function () use (&$reference) {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()->andReturnUsing(function ($order, $value) use (&$reference, &$events) {
            $reference = $value;
            $events[] = 'link_reference';
        });

        $capturedRequest = null;
        $capturedPayment = null;
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $client->shouldNotReceive('cancel');
        $client->shouldNotReceive('refund');
        $client->shouldReceive('create')->once()->andReturnUsing(
            function (array $request, SmartPixAuthorizedPayment $payment) use (&$events, &$key, &$contract, &$capturedRequest, &$capturedPayment): array {
                $this->assertSame('SYNTHETIC_KEY', $key);
                $this->assertSame('orders_smart_pix_v1', $contract);
                $events[] = 'create';
                $capturedRequest = $request;
                $capturedPayment = $payment;
                return $this->pendingResult();
            }
        );

        $processor = new SmartPixPaymentProcessor(
            new SmartPixValidator($seller),
            new SmartPixAttemptRepository($metadata),
            $client,
            $metadata
        );
        $result = $processor->process($order, 321, 'classic', [
            'token' => 'SYNTHETIC_PROOF',
            'openFinanceAccountId' => '00000000-0000-4000-8000-000000000000',
        ], 'SYNTHETIC_KEY');

        $this->assertSame(['persist_attempt', 'create', 'link_reference'], $events);
        $this->assertSame([
            'surface' => 'classic',
            'country' => 'MLB',
            'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321',
            'order_reference' => null,
            'amount' => ['value' => '4.00', 'currency' => 'BRL'],
            'refund_amount' => null,
            'order_details' => $this->expectedOrderDetails(),
        ], $capturedRequest);
        $this->assertInstanceOf(SmartPixAuthorizedPayment::class, $capturedPayment);
        $this->assertSame('SYNTHETIC_PROOF', $capturedPayment->getAuthorizedPseudotoken());
        $this->assertSame('00000000-0000-4000-8000-000000000000', $capturedPayment->getOpenFinanceAccountId());
        $this->assertSame('SYNTHETIC_KEY', $capturedPayment->getIdempotencyKey());
        $this->assertSame('processing', $result['state']);
        $this->assertSame('success', $result['outcome']);
        $this->assertSame('ORDER_SYNTHETIC_001', $reference);
        $this->assertSame('4.00', $result['payments'][0]['amount']['value']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode($result));
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testValidationFailureStopsBeforePersistenceAndOrders(string $scenario): void
    {
        [$validator, $order] = $this->context($scenario === 'context' ? 999 : 321);
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldNotReceive('createOrRead');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId');
        $proof = $scenario === 'proof' ? [] : $this->proof();
        $result = (new SmartPixPaymentProcessor($validator, $repository, $client, $metadata))->process(
            $scenario === 'missing' ? null : $order,
            321,
            $scenario === 'order_pay' ? 'order_pay' : 'classic',
            $proof,
            'CANDIDATE_KEY'
        );

        $this->assertSame('validation', $result['error']['category']);
        $this->assertSame('error', $result['state']);
    }

    public static function invalidInputProvider(): array
    {
        return [['missing'], ['context'], ['proof'], ['order_pay']];
    }

    public function testPersistenceFailureStopsBeforeOrdersWithoutExposingException(): void
    {
        [$validator, $order] = $this->context();
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->once()->andThrow(new \RuntimeException('SYNTHETIC_SECRET_IN_EXCEPTION'));
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId');
        $result = (new SmartPixPaymentProcessor($validator, $repository, $client, $metadata))->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('persistence', $result['error']['category']);
        $this->assertStringNotContainsString('SYNTHETIC_SECRET_IN_EXCEPTION', json_encode($result));
    }

    /** @dataProvider linkedReferenceProvider */
    public function testExistingOrCorruptedReferencePreventsAnotherPost($reference, string $category): void
    {
        [$validator, $order] = $this->context();
        $repository = $this->storedAttempt($order);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $client->shouldNotReceive('query');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn($reference);
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $result = (new SmartPixPaymentProcessor($validator, $repository, $client, $metadata))->process($order, 321, 'blocks', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame($category, $result['error']['category']);
    }

    public static function linkedReferenceProvider(): array
    {
        return [
            ['LINKED_ORDER', 'conflict'],
            [['corrupted'], 'persistence'],
            ['../invalid', 'persistence'],
        ];
    }

    public function testReusesStoredKeyAndPreservesAdapterFailureWithoutLinking(): void
    {
        [$validator, $order] = $this->context();
        $repository = $this->storedAttempt($order);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $failure = $this->pendingResult();
        $failure['outcome'] = 'failure';
        $failure['payments'] = [];
        $failure['order_reference'] = null;
        $failure['error'] = ['category' => 'timeout', 'code' => 'SMART_PIX_TIMEOUT', 'retryable' => true];
        $client->shouldReceive('create')->once()->withArgs(function (array $request, SmartPixAuthorizedPayment $payment): bool {
            return $payment->getIdempotencyKey() === 'STORED_KEY'
                && $payment->getAuthorizedPseudotoken() === 'SYNTHETIC_PROOF'
                && $request['amount'] === ['value' => '4.00', 'currency' => 'BRL'];
        })->andReturn($failure);
        $client->shouldNotReceive('query');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $result = (new SmartPixPaymentProcessor($validator, $repository, $client, $metadata))->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame($failure, $result);
    }

    /** @dataProvider knownFailureReferenceProvider */
    public function testKnownOrderIsLinkedForQueryEvenWhenItsStateIsUnsupported(string $category, string $existingReference): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $result = $this->pendingResult();
        $result['outcome'] = 'failure';
        $result['state'] = 'error';
        $result['payments'] = [];
        $result['error'] = ['category' => $category, 'code' => 'SMART_PIX_' . strtoupper($category), 'retryable' => false];
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($result);
        $client->shouldNotReceive('query');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturn('', $existingReference, 'ORDER_SYNTHETIC_001');
        if ($existingReference === '') {
            $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once();
        } else {
            $metadata->shouldNotReceive('linkSmartPixOrder');
        }
        $this->assertSame($result, (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY'));
    }

    public static function knownFailureReferenceProvider(): array
    {
        return [['unknown', ''], ['invalid_response', ''], ['unknown', 'ORDER_SYNTHETIC_001']];
    }

    /** @dataProvider linkageFailureProvider */
    public function testPostLinkageMustSurviveReloadWithoutOverwritingAnotherOrder(string $scenario, string $category, bool $upstreamFailure, bool $accredited = false): void
    {
        [$validator, $order] = $this->context();
        $repository = $this->storedAttempt($order);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $response = $this->pendingResult();
        if ($upstreamFailure) {
            $response['outcome'] = 'failure';
            $response['state'] = 'error';
            $response['payments'] = [];
            $response['error'] = ['category' => 'unknown', 'code' => 'SMART_PIX_UNKNOWN', 'retryable' => false];
        } elseif ($accredited) {
            $response = $this->accreditedResult();
        }
        $client->shouldReceive('create')->once()->andReturn($response);
        $metadata = Mockery::mock(OrderMetadata::class);
        $order->shouldReceive('read_meta_data')->with(true)->times($scenario === 'conflict' || $scenario === 'write_failure' ? 1 : 2);
        if ($scenario === 'conflict') {
            $metadata->shouldReceive('getPpOrderId')->with($order)->twice()->andReturn('', 'ANOTHER_ORDER');
            $metadata->shouldNotReceive('linkSmartPixOrder');
        } else {
            $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('', '', '');
            $write = $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once();
            if ($scenario === 'write_failure') {
                $write->andThrow(new \RuntimeException('SYNTHETIC_WRITE_EXCEPTION'));
            }
        }
        $result = (new SmartPixPaymentProcessor($validator, $repository, $client, $metadata))->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame($category, $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertStringNotContainsString('SYNTHETIC_WRITE_EXCEPTION', json_encode($result));
    }

    public static function linkageFailureProvider(): array
    {
        return [
            ['conflict', 'linkage_mismatch', false], ['write_failure', 'persistence', false], ['reload_failure', 'persistence', false],
            ['conflict', 'linkage_mismatch', true], ['write_failure', 'persistence', true], ['reload_failure', 'persistence', true],
            ['conflict', 'linkage_mismatch', false, true], ['write_failure', 'persistence', false, true], ['reload_failure', 'persistence', false, true],
        ];
    }

    /** @dataProvider invalidCreatedReferenceProvider */
    public function testInvalidCreatedReferenceCannotBePersistedOrReturned(bool $upstreamFailure): void
    {
        [$validator, $order] = $this->context();
        $order->shouldNotReceive('read_meta_data');
        $result = $this->pendingResult();
        $result['order_reference'] = '../invalid';
        if ($upstreamFailure) {
            $result['outcome'] = 'failure';
        }
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($result);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $response = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('invalid_response', $response['error']['category']);
        $this->assertNull($response['order_reference']);
    }

    public static function invalidCreatedReferenceProvider(): array
    {
        return [[false], [true]];
    }

    public function testUnevidencedTerminalResponseDoesNotLinkOrCompletePayment(): void
    {
        [$validator, $order] = $this->context();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $response = $this->pendingResult();
        $response['state'] = 'terminal_success';
        $client->shouldReceive('create')->once()->andReturn($response);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('invalid_response', $result['error']['category']);
    }

    /** @dataProvider invalidAccreditedResultProvider */
    public function testInvalidAccreditedCreateDoesNotLinkOrCompletePayment(string $scenario): void
    {
        [$validator, $order] = $this->context();
        $order->shouldNotReceive('read_meta_data');
        $response = $this->accreditedResult();
        switch ($scenario) {
            case 'amount_mismatch':
                $response['payments'][0]['amount']['value'] = '3.99';
                break;
            case 'float_amount':
                $response['payments'][0]['amount']['value'] = 4.0;
                break;
            case 'noncanonical_amount':
                $response['payments'][0]['amount']['value'] = '4.000';
                break;
            case 'currency_mismatch':
                $response['payments'][0]['amount']['currency'] = 'USD';
                break;
            case 'invalid_reference':
                $response['payments'][0]['reference'] = '../invalid';
                break;
            case 'missing_reference':
                unset($response['payments'][0]['reference']);
                break;
            case 'missing_amount':
                unset($response['payments'][0]['amount']);
                break;
            case 'scalar_amount':
                $response['payments'][0]['amount'] = '4.00';
                break;
            case 'missing_error':
                unset($response['error']);
                break;
            case 'error_with_success':
                $response['error'] = ['category' => 'unknown'];
                break;
            case 'missing_payments':
                unset($response['payments']);
                break;
            case 'empty_payments':
                $response['payments'] = [];
                break;
            case 'scalar_payments':
                $response['payments'] = 'approved';
                break;
            case 'scalar_payment':
                $response['payments'] = ['approved'];
                break;
            case 'associative_payments':
                $response['payments'] = ['payment' => $response['payments'][0]];
                break;
            case 'multiple_payments':
                $second = $response['payments'][0];
                $second['reference'] = 'PAYMENT_SYNTHETIC_002';
                $second['amount']['value'] = '2.00';
                $response['payments'][0]['amount']['value'] = '2.00';
                $response['payments'][] = $second;
                break;
            case 'rejected_payment':
                $response['payments'][0]['state'] = 'rejected';
                break;
            case 'wrong_terminal_state':
                $response['state'] = 'terminal_failure';
                break;
        }
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($response);
        $client->shouldNotReceive('query');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertNull($result['order_reference']);
        $this->assertSame([], $result['payments']);
    }

    public static function invalidAccreditedResultProvider(): array
    {
        return [
            ['amount_mismatch'], ['float_amount'], ['noncanonical_amount'], ['currency_mismatch'],
            ['invalid_reference'], ['missing_reference'], ['missing_amount'], ['scalar_amount'],
            ['missing_error'], ['error_with_success'], ['missing_payments'], ['empty_payments'],
            ['scalar_payments'], ['scalar_payment'], ['associative_payments'], ['multiple_payments'],
            ['rejected_payment'], ['wrong_terminal_state'],
        ];
    }

    /** @dataProvider measuredCreateResultProvider */
    public function testCreateDurationUsesTheFinalNormalizedResultWithoutACreationCounter(string $case): void
    {
        [$validator, $order] = $this->context();
        $response = $case === 'approved' ? $this->accreditedResult() : $this->pendingResult();
        if ($case !== 'approved') {
            $response['outcome'] = 'failure';
            $response['state'] = $case === 'rejected' ? 'terminal_failure' : ($case === 'timeout' ? 'processing' : 'error');
            $response['order_reference'] = null;
            $response['payments'] = [];
            $response['error'] = ['category' => $case === 'timeout' ? 'timeout' : 'unknown', 'code' => 'SYNTHETIC_PROVIDER_CODE', 'retryable' => false];
        }
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($response);
        $reference = '';
        if ($case === 'approved') {
            $metadata = $this->metricLinkedMetadata($order, $reference);
        } else {
            $metadata = Mockery::mock(OrderMetadata::class);
            $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
            $metadata->shouldNotReceive('linkSmartPixOrder');
        }
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $details = $this->metricDetails();
        $counts = [];
        $sink->shouldReceive('count')->andReturnUsing(static function (string $name, string $value, array $details) use (&$counts): void {
            $counts[] = [$name, $value, $details];
        });
        $sink->shouldNotReceive('distribution');
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $this->fixedTelemetry($sink));
        $this->assertSame($response, $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY'));
        // The adapter owns its errors and the processor emits no creation counter.
        $this->assertSame([], $counts);
    }

    public static function measuredCreateResultProvider(): array
    {
        return [['approved'], ['rejected'], ['timeout'], ['invalid_response']];
    }

    public function testAccreditedAmountConflictIsReportedAsFailedWithoutChangingMoneyOrLinkage(): void
    {
        [$validator, $order] = $this->context();
        $response = $this->accreditedResult();
        $response['payments'][0]['amount']['value'] = '3.99';
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($response);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $order->shouldNotReceive('read_meta_data');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $details = $this->metricDetails();
        $sink->shouldReceive('count')->with(SmartPixMetricSchema::ERROR, 'true', $details + ['category' => 'invalid_contract'], 'create.response: result must contain a valid order_reference and processing or fully accredited payment')->once();
        $sink->shouldNotReceive('distribution');
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $this->fixedTelemetry($sink));
        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertNull($result['order_reference']);
        $this->assertSame([], $result['payments']);
    }

    /** @dataProvider measuredLinkageFailureProvider */
    public function testUnverifiedPostWriteCannotProduceAnApprovedCreationMetric(string $case, string $category): void
    {
        [$validator, $order] = $this->context();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($this->accreditedResult());
        $metadata = Mockery::mock(OrderMetadata::class);
        if ($case === 'conflict') {
            $order->shouldReceive('read_meta_data')->with(true)->once();
            $metadata->shouldReceive('getPpOrderId')->with($order)->twice()->andReturn('', 'ANOTHER_ORDER');
            $metadata->shouldNotReceive('linkSmartPixOrder');
        } else {
            $order->shouldReceive('read_meta_data')->with(true)->times($case === 'write_failure' ? 1 : 2);
            $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('');
            $write = $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once();
            if ($case === 'write_failure') {
                $write->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_WRITE_FAILURE'));
            }
        }
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $details = $this->metricDetails();
        $sink->shouldReceive('count')->with(
            SmartPixMetricSchema::ERROR,
            'true',
            $details + ['category' => $category === 'linkage_mismatch' ? 'invalid_contract' : 'persistence'],
            [
                'conflict' => 'create.link_reference: response order_reference differs from the persisted order_reference',
                'write_failure' => 'create.persist_references: RuntimeException [0]: SYNTHETIC_PRIVATE_WRITE_FAILURE',
                'reload_failure' => 'create.persist_references: saved order/payment references did not survive a fresh metadata read',
            ][$case]
        )->once();
        $sink->shouldNotReceive('distribution');
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $this->fixedTelemetry($sink));
        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($result));
    }

    public static function measuredLinkageFailureProvider(): array
    {
        return [['conflict', 'linkage_mismatch'], ['write_failure', 'persistence'], ['reload_failure', 'persistence']];
    }

    public function testLinkedReferenceReplayEmitsNoCreationCounterOrDuration(): void
    {
        [$validator, $order] = $this->context();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('ORDER_ALREADY_LINKED');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $clockCalls = 0;
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$clockCalls): float {
            ++$clockCalls;
            return 10.0;
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $telemetry);
        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('conflict', $result['error']['category']);
        $this->assertSame('ORDER_ALREADY_LINKED', $result['order_reference']);
        $this->assertSame(0, $clockCalls);
    }

    /** @dataProvider localFailureBeforeCreateProvider */
    public function testLocalFailureBeforeCreateEmitsOnlyItsOwnedError(string $case, string $operation, string $category): void
    {
        [$validator, $order] = $this->context();
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        if ($case === 'missing') {
            $repository->shouldNotReceive('createOrRead');
        } else {
            $failure = $case === 'validation' ? new \InvalidArgumentException('SYNTHETIC_PRIVATE_ATTEMPT')
                : new \RuntimeException('SYNTHETIC_PRIVATE_PERSISTENCE');
            $repository->shouldReceive('createOrRead')->once()->andThrow($failure);
        }
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $expected = array_replace($this->metricDetails('blocks'), ['operation' => $operation, 'category' => $category]);
        $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $message = '') use ($expected): bool {
            $this->assertSame(SmartPixMetricSchema::ERROR, $name);
            $this->assertSame('true', $value);
            $this->assertSame($expected, $details);
            return true;
        });
        $sink->shouldNotReceive('distribution');
        $clockCalls = 0;
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$clockCalls): float {
            ++$clockCalls;
            return 10.0;
        });
        $processor = new SmartPixPaymentProcessor($validator, $repository, $client, $metadata, $telemetry);
        $result = $processor->process($case === 'missing' ? null : $order, 321, 'blocks', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame($category, $result['error']['category']);
        $this->assertSame(0, $clockCalls);
        $this->assertStringNotContainsString('PRIVATE', json_encode($result));
    }

    public static function localFailureBeforeCreateProvider(): array
    {
        return [['missing', 'validation', 'validation'], ['validation', 'validation', 'validation'], ['persistence', 'create', 'persistence']];
    }

    public function testValidatorFailureEmitsItsOriginalServerDiagnosticOnce(): void
    {
        [$validator, $order] = $this->context();
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldNotReceive('createOrRead');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->with(SmartPixMetricSchema::ERROR, 'true',
            array_replace($this->metricDetails(), ['operation' => 'validation', 'category' => 'validation']), Mockery::type('string'));
        $sink->shouldNotReceive('distribution');
        $processor = new SmartPixPaymentProcessor($validator, $repository, $client, $metadata, $this->fixedTelemetry($sink));
        $result = $processor->process($order, 321, 'classic', [], 'CANDIDATE_KEY');
        $this->assertSame('validation', $result['error']['category']);
    }

    public function testGivenAnExistingPixWhenSmartPixIsSubmittedThenTheSpecificFailurePrecedesAnyAttemptWriteOrProviderCall(): void
    {
        [, $order, $seller] = $this->context();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('hasConventionalPixPayment')->once()->with($order)->andReturn(true);
        $metadata->shouldNotReceive('getPpOrderId', 'linkSmartPixOrder');
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldNotReceive('createOrRead');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $creation = Mockery::mock(SmartPixCreationStore::class);
        $creation->shouldNotReceive('begin');

        $processor = new SmartPixPaymentProcessor(
            new SmartPixValidator($seller, null, null, $metadata), $repository, $client, $metadata, null, $creation
        );
        $result = $processor->process($order, 321, 'order_pay', $this->proof(), 'CANDIDATE_KEY', 'SERVER_ORDER_KEY');

        $this->assertSame('failure', $result['outcome']);
        $this->assertNull($result['order_reference']);
        $this->assertSame([
            'category' => 'validation', 'code' => 'SMART_PIX_EXISTING_PIX', 'retryable' => false,
        ], $result['error']);
        $this->assertStringNotContainsString('SYNTHETIC_PROOF', json_encode($result));
    }

    /** @dataProvider unexpectedClientThrowableProvider */
    public function testUnexpectedClientThrowableIsPreservedWithOnlySanitizedMetrics(\Throwable $failure): void
    {
        [$validator, $order] = $this->context();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $now = 10.0;
        $client->shouldReceive('create')->once()->andReturnUsing(static function () use (&$now, $failure): void {
            $now += 0.5;
            throw $failure;
        });
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $details = $this->metricDetails();
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $telemetry = new SmartPixTelemetry($sink, static function () use (&$now): float {
            return $now;
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, $telemetry);
        $caught = null;
        try {
            $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        } catch (\Throwable $exception) {
            $caught = $exception;
        }
        $this->assertSame($failure, $caught);
    }

    public static function unexpectedClientThrowableProvider(): array
    {
        return [[new \RuntimeException('SYNTHETIC_PRIVATE_PROVIDER_ERROR')], [new \Error('SYNTHETIC_PRIVATE_PROVIDER_ERROR')]];
    }

    /** @dataProvider creationReferencePersistenceProvider */
    public function testRealCreationPersistsAllReferencesAndDetectsAPartialSave(bool $dropIntent): void
    {
        [$validator, $order, $seller] = $this->context();
        $persisted = ['_mp_payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1];
        $working = $persisted;
        $order->shouldReceive('get_meta')->andReturnUsing(static function ($key) use (&$working) {
            return $working[$key] ?? '';
        });
        $order->shouldReceive('update_meta_data')->andReturnUsing(static function ($key, $value) use (&$working): void {
            $working[$key] = $value;
        });
        $order->shouldReceive('save')->once()->andReturnUsing(static function () use (&$working, &$persisted, $dropIntent): void {
            $persisted = $working;
            if ($dropIntent) {
                unset($persisted['_mp_transaction_intent_id']);
            }
        });
        $order->shouldReceive('read_meta_data')->with(true)->twice()->andReturnUsing(static function () use (&$working, &$persisted): void {
            $working = $persisted;
        });
        $response = $this->ppResponse(false);
        $response['payments'][0]['references'] = [
            'order_id' => 'ORDSYNTHETIC001', 'transaction_intent_id' => 'syntheticintent001',
            'token' => 'SYNTHETIC_PRIVATE_PROOF',
        ];
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(201, [], '', $response));
        $metadata = new OrderMetadata(new \MercadoPago\Woocommerce\Hooks\OrderMeta(), Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Logs::class));
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), new RealSmartPixOrderClient($http, $seller, $this->pixStore()), $metadata);

        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');
        $this->assertSame('ORDER_SYNTHETIC_001', $persisted['_mp_pp_order_id']);
        $this->assertSame('PAYMENT_SYNTHETIC_001', $persisted['_mp_pp_payment_id']);
        $this->assertSame('ORDSYNTHETIC001', $persisted['_mp_order_id']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($persisted));
        if ($dropIntent) {
            $this->assertSame('persistence', $result['error']['category']);
            $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        } else {
            $this->assertSame('success', $result['outcome']);
            $this->assertSame('syntheticintent001', $persisted['_mp_transaction_intent_id']);
        }
    }

    public static function creationReferencePersistenceProvider(): array
    {
        return ['complete save' => [false], 'partial save' => [true]];
    }

    public function testACreationStoreWithoutADispatchCapableClientFailsBeforeAnyCreate(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')->andReturn('');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $creation = Mockery::mock(SmartPixCreationStore::class);
        $creation->shouldNotReceive('begin');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');

        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, null, $creation))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('validation', $result['error']['category']);
        $this->assertNull($result['order_reference']);
    }

    public function testAConcurrentSubmissionThatLostTheAdmissionIsAConflictWithoutAnotherPost(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')->andReturn('');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $creation = Mockery::mock(SmartPixCreationStore::class);
        $creation->shouldReceive('begin')->with($order, 'STORED_KEY')->once()->andReturn(false);
        $creation->shouldNotReceive('responseReceived', 'recordFailure');
        $events = [];
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldReceive('create')->once()->andReturnUsing(static function ($request, $payment, $onDispatch) use (&$events): array {
            $onDispatch();
            $events[] = 'http';
            return [];
        });
        $processor = new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata, null, $creation,
            static function (WC_Order $dispatched) use (&$events, $order): void {
                $events[] = $dispatched === $order ? 'session_saved' : 'other_order';
            });

        $result = $processor->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('conflict', $result['error']['category']);
        $this->assertSame(['session_saved'], $events);
    }

    public function testCreationReferencesFromAnotherOrderAreAMismatchWithoutLinking(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $client = Mockery::mock(SmartPixOrderClient::class . ', ' . \MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationReferences::class);
        $client->shouldReceive('create')->once()->andReturn($this->pendingResult());
        $client->shouldReceive('creationReferences')->once()->andReturn([
            'order_reference' => 'ORDER_SYNTHETIC_OTHER', 'payment_reference' => 'PAYMENT_SYNTHETIC_001',
        ]);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->twice()->andReturn('');
        $metadata->shouldNotReceive('linkSmartPixOrder', 'persistSmartPixReferences');

        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('linkage_mismatch', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
    }

    public function testAnOrderLinkedConcurrentlyOnlyReceivesTheMissingCreationReferences(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $references = ['order_reference' => 'ORDER_SYNTHETIC_001', 'payment_reference' => 'PAYMENT_SYNTHETIC_001'];
        $client = Mockery::mock(SmartPixOrderClient::class . ', ' . \MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationReferences::class);
        $client->shouldReceive('create')->once()->andReturn($this->pendingResult());
        $client->shouldReceive('creationReferences')->once()->andReturn($references);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturn('', 'ORDER_SYNTHETIC_001', 'ORDER_SYNTHETIC_001');
        $metadata->shouldNotReceive('linkSmartPixOrder');
        $metadata->shouldReceive('persistSmartPixReferences')->with($order, $references)->once();
        $metadata->shouldReceive('getPpPaymentId')->with($order)->once()->andReturn('PAYMENT_SYNTHETIC_001');

        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame($this->pendingResult(), $result);
    }

    public function testAnInvalidLinkageWriteIsAnInvalidResponseThatKeepsTheKnownReference(): void
    {
        [$validator, $order] = $this->context();
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('create')->once()->andReturn($this->pendingResult());
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->twice()->andReturn('');
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()
            ->andThrow(new \InvalidArgumentException('SYNTHETIC_INVALID_LINK'));

        $result = (new SmartPixPaymentProcessor($validator, $this->storedAttempt($order), $client, $metadata))
            ->process($order, 321, 'classic', $this->proof(), 'CANDIDATE_KEY');

        $this->assertSame('invalid_response', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC_001', $result['order_reference']);
        $this->assertStringNotContainsString('SYNTHETIC_INVALID_LINK', json_encode($result));
    }

    /** @return array<string, string> */
    private function metricDetails(string $surface = 'classic'): array
    {
        return ['operation' => 'create', 'source' => 'sync_response', 'surface' => $surface,
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
    }

    private function metricLinkedMetadata(WC_Order $order, string &$reference): OrderMetadata
    {
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPpOrderId')->with($order)->times(3)->andReturnUsing(static function () use (&$reference): string {
            return $reference;
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()
            ->andReturnUsing(static function ($order, string $value) use (&$reference): void {
                $reference = $value;
            });
        return $metadata;
    }

    private function fixedTelemetry(SmartPixMetricSink $sink): SmartPixTelemetry
    {
        $calls = 0;
        return new SmartPixTelemetry($sink, static function () use (&$calls): float {
            return ++$calls === 1 ? 10.0 : 10.5;
        });
    }

    /** @return array{SmartPixValidator, WC_Order, Seller} */
    private function context(int $id = 321): array
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_order_key')->andReturn('SERVER_ORDER_KEY');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn('4.00');
        $this->stubOrderDetails($order);
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $order->shouldNotReceive('add_order_note');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCollectorId')->andReturn('123456');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('123456');
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_CREDENTIAL');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreId')->andReturn('WC-');

        return [new SmartPixValidator($seller), $order, $seller];
    }

    private function storedAttempt(WC_Order $order): SmartPixAttemptRepository
    {
        $repository = Mockery::mock(SmartPixAttemptRepository::class);
        $repository->shouldReceive('createOrRead')->with($order, 'CANDIDATE_KEY')->once()->andReturn([
            'payment_contract' => 'orders_smart_pix_v1',
            'idempotency_key' => 'STORED_KEY',
        ]);
        return $repository;
    }

    /** @return array<string, string> */
    private function proof(): array
    {
        return ['token' => 'SYNTHETIC_PROOF', 'openFinanceAccountId' => '00000000-0000-4000-8000-000000000000'];
    }

    /** A public custom domain keeps the notification URL independent from WC(). */
    private function pixStore(): Store
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn(true);
        $store->shouldReceive('getCustomDomain')->andReturn('https://store.example.test');
        $store->shouldReceive('getCustomDomainOptions')->andReturn('yes');
        $store->shouldReceive('getStoreId')->andReturn('WC-');
        return $store;
    }

    /** @return array<string, mixed> */
    private function pendingResult(): array
    {
        return [
            'outcome' => 'success',
            'state' => 'processing',
            'order_reference' => 'ORDER_SYNTHETIC_001',
            'eligibility' => null,
            'authorization' => null,
            'payments' => [['reference' => 'PAYMENT_SYNTHETIC_001', 'state' => 'processing', 'amount' => ['value' => '4.00', 'currency' => 'BRL']]],
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function accreditedResult(): array
    {
        $result = $this->pendingResult();
        $result['state'] = 'terminal_success';
        $result['payments'][0]['state'] = 'approved';
        return $result;
    }

    /** @return array<string, mixed> */
    private function ppResponse(bool $approved): array
    {
        return [
            'id' => 'ORDER_SYNTHETIC_001', 'external_reference' => 'WC-321', 'type' => 'online',
            'site_id' => 'MLB', 'currency' => 'BRL', 'total_amount' => '4.00', 'total_paid_amount' => $approved ? '4.00' : '0.00',
            'status' => $approved ? 'processed' : 'action_required', 'status_detail' => $approved ? 'accredited' : 'waiting_transfer',
            'payments' => [[
                'id' => 'PAYMENT_SYNTHETIC_001', 'amount' => '4.00', 'paid_amount' => $approved ? '4.00' : '0.00',
                'status' => $approved ? 'processed' : 'action_required', 'status_detail' => $approved ? 'accredited' : 'waiting_transfer',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]],
        ];
    }
}

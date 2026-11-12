<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Configs\Store;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixOrderQueryTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider querySurfaceProvider */
    public function testQueryUsesPersistedReferenceWithoutCreatingOrUpdatingAnOrder(int $orderId, string $surface): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_payment_method')->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->once()->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('ORDER_SYNTHETIC_001');
        $metadata->shouldNotReceive('persistSmartPixAttempt');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $expected = ['outcome' => 'success', 'state' => 'processing'];
        $client->shouldReceive('query')->once()->with([
            'surface' => $surface, 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-' . $orderId, 'order_reference' => 'ORDER_SYNTHETIC_001',
            'amount' => null, 'refund_amount' => null,
        ])->andReturn($expected);

        $this->assertSame($expected, (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, $surface));
    }

    public function testQueryReadsACandidateThatTheCheckoutPersistedMeanwhileAsTheLinkedOrder(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->once()->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $order->shouldNotReceive('save');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->once()->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->once()->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->once()->andReturn('PPORDSYNTHETIC001');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $expected = ['outcome' => 'success', 'state' => 'processing'];
        $client->shouldReceive('query')->once()->with([
            'surface' => 'server', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321', 'order_reference' => 'PPORDSYNTHETIC001',
            'amount' => null, 'refund_amount' => null,
        ])->andReturn($expected);

        $result = (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, 'server', 'PPORDSYNTHETIC001');

        $this->assertSame($expected, $result);
    }

    public function testQueryRejectsACandidateWhenAnotherOrderIsPersisted(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $order->shouldNotReceive('save');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->andReturn('PPORDSYNTHETIC999');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'create');

        $result = (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, 'server', 'PPORDSYNTHETIC001');

        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('validation', $result['error']['category']);
    }

    public static function querySurfaceProvider(): array
    {
        return [[1, 'classic'], [321, 'blocks'], [PHP_INT_MAX, 'order_pay'], [321, 'server']];
    }

    /** @dataProvider incompatibleLinkProvider */
    public function testQueryRejectsAnIncompleteOrInvalidPersistedLink($contract, $key, $reference): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        $order->shouldNotReceive('save');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn($contract);
        $metadata->shouldReceive('getIdempotencyKey')->andReturn($key);
        $metadata->shouldReceive('getPpOrderId')->andReturn($reference);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $client->shouldNotReceive('create');

        $result = (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, 'server');
        $this->assertSame('failure', $result['outcome']);
        $this->assertSame('validation', $result['error']['category']);
    }

    public function incompatibleLinkProvider(): array
    {
        return [
            'Payments contract' => ['payments_v1', 'SYNTHETIC_KEY', 'ORDER_SYNTHETIC_001'],
            'missing attempt key' => ['orders_smart_pix_v1', '', 'ORDER_SYNTHETIC_001'],
            'corrupt attempt key' => ['orders_smart_pix_v1', [], 'ORDER_SYNTHETIC_001'],
            'missing reference' => ['orders_smart_pix_v1', 'SYNTHETIC_KEY', ''],
            'corrupt reference' => ['orders_smart_pix_v1', 'SYNTHETIC_KEY', []],
            'path traversal' => ['orders_smart_pix_v1', 'SYNTHETIC_KEY', '../ORDER_SYNTHETIC_001'],
            'external URL' => ['orders_smart_pix_v1', 'SYNTHETIC_KEY', 'https://example.test/order'],
        ];
    }

    public function testQueryRejectsAnUnknownSurfaceBeforeReadingOrCallingOrders(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $result = (new SmartPixOrderQuery(
            $client,
            Mockery::mock(OrderMetadata::class),
            Mockery::mock(Seller::class)
        ))->query($order, 'untrusted');
        $this->assertSame('validation', $result['error']['category']);
    }
    /** @dataProvider queryTelemetrySourceProvider */
    public function testLocalLinkFailureReportsOnceWithoutAnOrdersCall(string $source, string $operation): void
    {
        $sink = $this->queryErrorSink('validation', $source, $operation);
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $client->shouldNotReceive('create');
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry($sink), $source);
        $metadata->shouldReceive('getPpOrderId')->andReturn('');
        $this->assertSame('validation', $query->query($order, 'server')['error']['category']);
    }

    public static function queryTelemetrySourceProvider(): array
    {
        return [['pending_query', 'get'], ['notification', 'notification']];
    }

    public function testQueryUsesTheStoreModeTokenRegardlessOfTheOrderProductionModeMeta(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', [
            'Authorization' => 'Bearer SYNTHETIC_STORE_MODE_CREDENTIAL', 'Accept' => 'application/json', 'X-Platform-Id' => MP_PLATFORM_ID,
        ])->andReturn(new HttpResponse(200, [], '', [
            'id' => 'ORDER_SYNTHETIC', 'external_reference' => 'WC-321', 'status' => 'processing',
            'status_detail' => 'in_process', 'type' => 'online', 'country_code' => 'BRA', 'currency' => 'BRL',
            'total_amount' => '125.50', 'total_paid_amount' => '0.00', 'transactions' => ['payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.50', 'status' => 'processing', 'status_detail' => 'in_process',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]]],
        ]));
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_STORE_MODE_CREDENTIAL');
        $seller->shouldNotReceive('getCredentialsAccessTokenProd', 'getCredentialsAccessTokenTest');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn(true);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldNotReceive('payment_complete', 'update_status');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('ORDER_SYNTHETIC');
        // The order was created in production while the store is now in test mode.
        $metadata->shouldReceive('getIsProductionModeData')->with($order)->zeroOrMoreTimes()->andReturn('yes');
        $metadata->shouldReceive('persistSmartPixObservation')->zeroOrMoreTimes();
        $query = new SmartPixOrderQuery(
            new RealSmartPixOrderClient($http, $seller, $store),
            $metadata,
            $seller,
            null,
            'pending_query'
        );

        $result = $query->query($order, 'server');

        $this->assertSame('success', $result['outcome']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame('ORDER_SYNTHETIC', $result['order_reference']);
    }

    public function testApiFailureIsReportedByRealClientOnly(): void
    {
        $sink = $this->queryErrorSink('http_5xx');
        $telemetry = new SmartPixTelemetry($sink);
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/orders/ORDER_SYNTHETIC', Mockery::type('array'))
            ->andReturn(new HttpResponse(503, [], '', ['message' => 'SYNTHETIC_PRIVATE']));
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_CREDENTIAL');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn(true);
        $client = new RealSmartPixOrderClient($http, $seller, $store, $telemetry);
        [$query, $order] = $this->linkedQuery($client, $telemetry);
        $result = $query->query($order, 'server');
        $this->assertSame('unavailable', $result['error']['category']);
        $this->assertSame('ORDER_SYNTHETIC', $result['order_reference']);
    }

    public function testUnexpectedGetThrowableIsReportedOnceAndRethrownUnchanged(): void
    {
        $sink = $this->queryErrorSink('internal_error');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $original = new \Error('SYNTHETIC_PRIVATE_QUERY');
        $client->shouldReceive('query')->once()->andThrow($original);
        $client->shouldNotReceive('create');
        [$query, $order] = $this->linkedQuery($client, new SmartPixTelemetry($sink));
        try {
            $query->query($order, 'server');
            $this->fail('Expected the original query failure.');
        } catch (\Error $failure) {
            $this->assertSame($original, $failure);
        }
    }

    public function testUnknownSurfaceIsBoundedWithoutReadingOrSendingHttp(): void
    {
        $sink = $this->queryErrorSink('validation');
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('read_meta_data');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query');
        $query = new SmartPixOrderQuery($client, Mockery::mock(OrderMetadata::class), Mockery::mock(Seller::class), new SmartPixTelemetry($sink));
        $this->assertSame('validation', $query->query($order, 'SYNTHETIC_PRIVATE_SURFACE')['error']['category']);
    }

    public function testSupersededQueryReadsTheNotifiedOrderOfTheAttemptAsAServerReadWithoutRecordingIt(): void
    {
        $order = $this->supersededOrder(321, 'BRL');
        $order->shouldNotReceive('read_meta_data', 'save', 'payment_complete', 'update_status');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('getPpOrderId', 'persistSmartPixObservation', 'persistSmartPixAttempt');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('create');
        $expected = ['outcome' => 'success', 'state' => 'terminal_success', 'order_reference' => 'PPORDSYNTHETIC001'];
        $client->shouldReceive('query')->once()->with([
            'surface' => 'server', 'country' => 'MLB', 'payment_contract' => 'orders_smart_pix_v1',
            'attempt_reference' => 'WC-321', 'order_reference' => 'PPORDSYNTHETIC001',
            'amount' => null, 'refund_amount' => null,
        ])->andReturn($expected);
        $query = new SmartPixOrderQuery($client, $metadata, $this->mlbSeller(), null, 'notification');

        $this->assertSame($expected, $query->querySuperseded($order, 'PPORDSYNTHETIC001', 'WC-321'));
    }

    /** @dataProvider invalidSupersededQueryProvider */
    public function testSupersededQueryRejectsAnInvalidContextOnceWithoutAnOrdersCall(
        int $orderId,
        string $currency,
        string $siteId,
        string $candidate,
        string $attempt
    ): void {
        $sink = $this->queryErrorSink('validation', 'notification', 'notification');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldNotReceive('query', 'create');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn($siteId);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('persistSmartPixObservation');
        $query = new SmartPixOrderQuery($client, $metadata, $seller, new SmartPixTelemetry($sink), 'notification');

        $result = $query->querySuperseded($this->supersededOrder($orderId, $currency), $candidate, $attempt);

        $this->assertSame('failure', $result['outcome']);
        $this->assertNull($result['order_reference']);
        $this->assertSame('validation', $result['error']['category']);
    }

    public static function invalidSupersededQueryProvider(): array
    {
        return [
            'unsaved order' => [0, 'BRL', 'MLB', 'PPORDSYNTHETIC001', 'WC-321'],
            'another currency' => [321, 'USD', 'MLB', 'PPORDSYNTHETIC001', 'WC-321'],
            'another site' => [321, 'BRL', 'MLA', 'PPORDSYNTHETIC001', 'WC-321'],
            'malformed candidate' => [321, 'BRL', 'MLB', 'PPORD/SYNTHETIC', 'WC-321'],
            'malformed attempt reference' => [321, 'BRL', 'MLB', 'PPORDSYNTHETIC001', ''],
        ];
    }

    public function testUnexpectedSupersededReadThrowableIsReportedOnceAndRethrownUnchanged(): void
    {
        $sink = $this->queryErrorSink('internal_error', 'notification', 'notification');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $original = new \Error('SYNTHETIC_PRIVATE_SUPERSEDED_QUERY');
        $client->shouldReceive('query')->once()->andThrow($original);
        $query = new SmartPixOrderQuery(
            $client,
            Mockery::mock(OrderMetadata::class),
            $this->mlbSeller(),
            new SmartPixTelemetry($sink),
            'notification'
        );
        try {
            $query->querySuperseded($this->supersededOrder(321, 'BRL'), 'PPORDSYNTHETIC001', 'WC-321');
            $this->fail('Expected the original superseded query failure.');
        } catch (\Error $failure) {
            $this->assertSame($original, $failure);
        }
    }

    /** @return mixed */
    private function supersededOrder(int $orderId, string $currency)
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_currency')->andReturn($currency);
        return $order;
    }

    private function mlbSeller(): Seller
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        return $seller;
    }

    private function queryErrorSink(string $category, string $source = 'pending_query', string $operation = 'get'): SmartPixMetricSink
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $expected = ['operation' => $operation, 'source' => $source, 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'category' => $category];
        $sink->shouldReceive('count')->once()->withArgs(function ($name, $value, $details, $message = '') use ($expected): bool {
            $this->assertSame(SmartPixMetricSchema::ERROR, $name);
            $this->assertSame('true', $value);
            $this->assertSame($expected, $details);
            return true;
        });
        $sink->shouldNotReceive('distribution');
        return $sink;
    }

    /** @return array{SmartPixOrderQuery, mixed, mixed} */
    private function linkedQuery(SmartPixOrderClient $client, SmartPixTelemetry $telemetry, string $source = 'pending_query'): array
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->with(true)->once();
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->andReturn('ORDER_SYNTHETIC')->byDefault();
        return [new SmartPixOrderQuery($client, $metadata, $seller, $telemetry, $source), $order, $metadata];
    }

    public function testFailedOrderObservationIsRecordedInHiddenMetaWithOneSaveAndReturnedUnchanged(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/fixtures/orders/orders-query-terminal-failure.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $response = $fixture['response'];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        foreach (
            [
                '_mp_pp_order_status' => 'failed', '_mp_pp_order_status_detail' => 'high_risk',
                '_mp_pp_payment_status' => 'failed', '_mp_pp_payment_status_detail' => 'high_risk',
                '_mp_pp_order_total_failed' => '125.50',
            ] as $key => $value
        ) {
            $order->shouldReceive('update_meta_data')->once()->with($key, $value);
        }
        $order->shouldReceive('save')->once();
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        $metadata = Mockery::mock(OrderMetadata::class, [new OrderMeta(), Mockery::mock(Logs::class)])->makePartial();
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('order_fixture_query_failure_001');

        $this->assertSame($response, (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, 'server'));
    }

    public function testMutationObservationWithoutPaymentIdsIsStillRecorded(): void
    {
        $response = $this->terminalFailureResponse();
        // Mutation snapshots never project payment ids; their status facts must still be kept.
        unset($response['observed']['payment_id'], $response['observed']['payment_reference']);
        $order = Mockery::mock(WC_Order::class);
        $order->shouldNotReceive('save', 'payment_complete', 'update_status');
        $seller = Mockery::mock(Seller::class);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldReceive('persistSmartPixObservation')->once()->with($order, $response['observed']);

        (new SmartPixOrderQuery(Mockery::mock(SmartPixOrderClient::class), $metadata, $seller))
            ->recordObservation($order, 'ORDER_SYNTHETIC', $response);

        $this->addToAssertionCount(1);
    }

    public function testOrderReadObservationWithAMalformedPaymentIdIsNotRecorded(): void
    {
        $response = $this->terminalFailureResponse();
        $response['observed']['payment_id'] = '../payment';
        $order = Mockery::mock(WC_Order::class);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('persistSmartPixObservation');

        (new SmartPixOrderQuery(Mockery::mock(SmartPixOrderClient::class), $metadata, Mockery::mock(Seller::class)))
            ->recordObservation($order, 'ORDER_SYNTHETIC', $response);

        $this->addToAssertionCount(1);
    }

    public function testObservationAboutAnotherOrderIsNotRecordedAndTheResultIsUnchanged(): void
    {
        $response = $this->terminalFailureResponse();
        $response['order_reference'] = 'ORDER_SYNTHETIC_999';
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry());
        $metadata->shouldNotReceive('persistSmartPixObservation');

        $this->assertSame($response, $query->query($order, 'server'));
    }

    /**
     * @dataProvider malformedObservationProvider
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    public function testMissingOrMalformedObservationIsNotRecordedAndTheResultIsUnchanged(callable $change): void
    {
        $response = $change($this->terminalFailureResponse());
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry());
        $metadata->shouldNotReceive('persistSmartPixObservation');

        $this->assertSame($response, $query->query($order, 'server'));
    }

    public static function malformedObservationProvider(): array
    {
        $set = static function (string $key, $value): callable {
            return static function (array $response) use ($key, $value): array {
                $response['observed'][$key] = $value;
                return $response;
            };
        };
        $replace = static function ($observed): callable {
            return static function (array $response) use ($observed): array {
                $response['observed'] = $observed;
                return $response;
            };
        };
        return [
            'missing' => [static function (array $response): array {
                unset($response['observed']);
                return $response;
            }],
            'null' => [$replace(null)],
            'string' => [$replace('failed')],
            'list' => [$replace(['failed', 'high_risk', 'failed', 'high_risk', '125.50'])],
            'missing key' => [static function (array $response): array {
                unset($response['observed']['payment_status_detail']);
                return $response;
            }],
            'extra key' => [$set('raw_status', 'failed')],
            'uppercase status' => [$set('order_status', 'FAILED')],
            'symbol in detail' => [$set('payment_status_detail', 'high-risk')],
            'empty detail' => [$set('order_status_detail', '')],
            'too long detail' => [$set('payment_status_detail', str_repeat('a', 65))],
            'numeric status' => [$set('payment_status', 1)],
            'float failed total' => [$set('total_failed', 125.5)],
            'short failed total' => [$set('total_failed', '125.5')],
            'subcent failed total' => [$set('total_failed', '125.500')],
            'negative failed total' => [$set('total_failed', '-125.50')],
            'too long failed total' => [$set('total_failed', str_repeat('1', 62) . '.00')],
        ];
    }

    public function testObservationWriteFailureLogsAFixedReasonAndKeepsTheResult(): void
    {
        $response = $this->terminalFailureResponse();
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry());
        $metadata->shouldReceive('persistSmartPixObservation')->once()->with($order, $response['observed'])
            ->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_WRITE'));
        $logger = Mockery::mock();
        $logger->shouldReceive('warning')->once()->with('Smart Pix observation was not recorded.', [
            'source' => 'mercadopago-smart-pix', 'reason' => 'observation_write_failed',
        ]);
        WP_Mock::userFunction('wc_get_logger', ['times' => 1, 'return' => $logger]);

        $this->assertSame($response, $query->query($order, 'server'));
    }

    public function testObservationWriteAndLoggingFailuresNeverChangeTheResultOrReportAnError(): void
    {
        $response = $this->terminalFailureResponse();
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldNotReceive('count');
        $sink->shouldNotReceive('distribution');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry($sink));
        $metadata->shouldReceive('persistSmartPixObservation')->once()->andThrow(new \Error('SYNTHETIC_PRIVATE_WRITE'));
        WP_Mock::userFunction('wc_get_logger', ['times' => 1, 'return' => static function (): void {
            throw new \Error('SYNTHETIC_PRIVATE_LOGGER');
        }]);

        $this->assertSame($response, $query->query($order, 'server'));
    }

    public function testObservationWithoutAFailedTotalClearsTheStoredFailedTotal(): void
    {
        $response = $this->terminalFailureResponse();
        $response['observed']['total_failed'] = null;
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('read_meta_data')->once()->with(true);
        foreach (
            [
                '_mp_pp_order_status' => 'failed', '_mp_pp_order_status_detail' => 'high_risk',
                '_mp_pp_payment_status' => 'failed', '_mp_pp_payment_status_detail' => 'high_risk',
                '_mp_pp_order_total_failed' => '',
            ] as $key => $value
        ) {
            $order->shouldReceive('update_meta_data')->once()->with($key, $value);
        }
        $order->shouldReceive('save')->once();
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        $metadata = Mockery::mock(OrderMetadata::class, [new OrderMeta(), Mockery::mock(Logs::class)])->makePartial();
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('ORDER_SYNTHETIC');

        $this->assertSame($response, (new SmartPixOrderQuery($client, $metadata, $seller))->query($order, 'server'));
    }

    public function testProcessingObservationIsRecordedWithoutAFailedTotal(): void
    {
        $response = [
            'outcome' => 'success', 'state' => 'processing', 'order_reference' => 'ORDER_SYNTHETIC',
            'eligibility' => null, 'authorization' => null,
            'payments' => [['reference' => 'PAYMENT_SYNTHETIC', 'state' => 'processing', 'amount' => ['value' => '125.50', 'currency' => 'BRL']]],
            'error' => null,
            'observed' => [
                'order_status' => 'processing', 'order_status_detail' => 'in_process',
                'payment_status' => 'processing', 'payment_status_detail' => 'in_process', 'total_failed' => null,
                'payment_id' => 'PAYMENT_SYNTHETIC', 'payment_reference' => null,
            ],
        ];
        $client = Mockery::mock(SmartPixOrderClient::class);
        $client->shouldReceive('query')->once()->andReturn($response);
        [$query, $order, $metadata] = $this->linkedQuery($client, new SmartPixTelemetry());
        $metadata->shouldReceive('persistSmartPixObservation')->once()->with($order, $response['observed']);

        $this->assertSame($response, $query->query($order, 'server'));
    }

    /** @return array<string, mixed> */
    private function terminalFailureResponse(): array
    {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__ . '/fixtures/orders/orders-query-terminal-failure.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $response = $fixture['response'];
        $response['order_reference'] = 'ORDER_SYNTHETIC';
        return $response;
    }
}

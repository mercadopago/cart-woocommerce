<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationHandler;
use MercadoPago\Woocommerce\SmartPix\Composition\SmartPixCoreNotificationHandlerFactory;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use Mockery;
use PHPUnit\Framework\TestCase;
use Throwable;
use WC_Order;
use WP_Mock;

/**
 * The factory composes the real Core P&P chain; only HTTP, configuration, order metadata,
 * the database and WooCommerce functions are doubles.
 */
class SmartPixCoreNotificationHandlerFactoryTest extends TestCase
{
    private const NOTIFICATION_ID = 'PPORDSYNTHETIC001';

    private const ORDER_REFERENCE = self::NOTIFICATION_ID;

    private const PROVIDER_ORDER_REFERENCE = 'ORDSYNTHETIC001';

    /** Id of the layer behind Core, carried only in the envelope references. */
    private const LAYER_REFERENCE = self::PROVIDER_ORDER_REFERENCE;

    private const ACCESS_TOKEN = 'SYNTHETIC_ACCESS_TOKEN';

    /** @var array<string, mixed> */
    private array $globals = [];

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $logger = Mockery::mock();
        $logger->shouldNotReceive('warning');
        WP_Mock::userFunction('wc_get_logger', ['return' => $logger]);
        foreach (['mercadopago', 'wpdb'] as $name) {
            $this->globals[$name] = $GLOBALS[$name] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->globals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testCreatedHandlerFetchesLinksAndQueriesTheOrderThroughTheGivenHttpClient(): void
    {
        [$seller, $store, $metadata] = $this->configuration();
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->ordered()
            ->with('GET', '/plugins-platforms/v1/notification/order/' . self::NOTIFICATION_ID, ['Authorization' => 'Bearer ' . self::ACCESS_TOKEN], null)
            ->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $http->shouldReceive('request')->once()->ordered()
            ->with('GET', '/plugins-platforms/v1/orders/' . self::ORDER_REFERENCE, [
                'Authorization' => 'Bearer ' . self::ACCESS_TOKEN,
                'Accept' => 'application/json',
                'X-Platform-Id' => MP_PLATFORM_ID,
            ])
            ->andReturn(new HttpResponse(200, [], '', $this->pendingOrder()));
        $database = $this->database();
        $database->shouldNotReceive('query', 'insert', 'update', 'delete', 'get_row', 'get_var');
        $order = $this->linkedOrder($metadata);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => 'WC-']);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $handler = SmartPixCoreNotificationHandlerFactory::create($http, $seller, $store, $metadata, $database);
        $result = $handler->handle(self::NOTIFICATION_ID, new CoreRequestContext(self::ACCESS_TOKEN));

        // A non-terminal Orders answer is acknowledged without effects.
        $this->assertSame(['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], $result);
    }

    public function testFromPluginComposesTheHandlerFromThePluginContainer(): void
    {
        [$seller, $store, $metadata] = $this->configuration();
        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $seller,
            'storeConfig' => $store,
            'orderMetadata' => $metadata,
            'orderStatus' => Mockery::mock(\MercadoPago\Woocommerce\Order\OrderStatus::class),
            'smartPixNativeEffects' => new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects(),
        ];
        $GLOBALS['wpdb'] = $this->database();

        $this->assertInstanceOf(SmartPixCoreNotificationHandler::class, SmartPixCoreNotificationHandlerFactory::fromPlugin());
    }

    /** @dataProvider storeModeProvider */
    public function testFromPluginSendsProductionOrdersTelemetryInBothStoreModes(bool $testMode, string $environment): void
    {
        [$seller, $store, $metadata] = $this->configuration($testMode);
        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $seller,
            'storeConfig' => $store,
            'orderMetadata' => $metadata,
            'orderStatus' => Mockery::mock(\MercadoPago\Woocommerce\Order\OrderStatus::class),
            'smartPixNativeEffects' => new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects(),
            'woocommerce' => (object) ['version' => '9.8.1'],
        ];
        $GLOBALS['wpdb'] = $this->database();
        $sent = [];
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post')->andReturnUsing(function ($url, $arguments) use (&$sent) {
            $sent[$url] = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            return [];
        });
        WP_Mock::userFunction('wp_remote_request', ['times' => 0]);

        // An id that is not a Smart Pix notification is rejected before any fetch.
        $result = SmartPixCoreNotificationHandlerFactory::fromPlugin()
            ->handle('NOT_A_SMART_PIX_ID', new CoreRequestContext(self::ACCESS_TOKEN));

        $this->assertSame(['status' => 400, 'message' => SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD], $result);
        $url = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/' . SmartPixMetricSchema::NOTIFICATION_REJECTED;
        $this->assertArrayHasKey($url, $sent);
        $this->assertSame([
            'value' => '1', 'message' => 'server', 'plugin_version' => MP_VERSION,
            'platform' => ['name' => 'woocommerce', 'version' => '9.8.1'],
            'details' => ['payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'notification_rejected',
                'reason' => 'invalid_payload', 'environment' => $environment, 'missing_fields' => 'sdk_instance_id'],
        ], $sent[$url]);
    }

    /** @return array<string, array{0: bool, 1: string}> */
    public static function storeModeProvider(): array
    {
        // Orders and Core Monitor stay on production; only the environment tag follows the store mode.
        return ['test mode' => [true, 'homol'], 'production mode' => [false, 'prod']];
    }

    public function testFromPluginFailsWhenThePluginContainerIsNotLoaded(): void
    {
        $GLOBALS['mercadopago'] = null;
        $GLOBALS['wpdb'] = $this->database();

        try {
            SmartPixCoreNotificationHandlerFactory::fromPlugin();
            $this->fail('The handler cannot be composed without the plugin container.');
        } catch (Throwable $failure) {
            // CoreNotification turns this into the fixed server error.
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $failure);
        }
    }

    public function testReconciliationFromPluginReadsTheOrderThroughThePluginHttpAndReschedulesAPendingAnswer(): void
    {
        [$seller, $store, $metadata] = $this->configuration();
        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $seller,
            'storeConfig' => $store,
            'orderMetadata' => $metadata,
            'orderStatus' => Mockery::mock(\MercadoPago\Woocommerce\Order\OrderStatus::class),
            'smartPixNativeEffects' => new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects(),
            'woocommerce' => (object) ['version' => '9.8.1'],
        ];
        $database = $this->database();
        $database->shouldNotReceive('query', 'insert', 'update', 'delete', 'get_row', 'get_var');
        $GLOBALS['wpdb'] = $database;
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_status')->andReturn('pending');
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldNotReceive('save', 'payment_complete', 'update_status', 'add_order_note');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_ATTEMPT_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getMpOrderId')->with($order)->andReturn(self::PROVIDER_ORDER_REFERENCE);
        $metadata->shouldReceive('getPpOrderReconcileState')->with($order)->andReturn('');
        $metadata->shouldNotReceive('setPpOrderReconcileState');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $order]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        $requested = [];
        WP_Mock::userFunction('wp_remote_request')->once()->andReturnUsing(function ($url, $args) use (&$requested) {
            $requested = [$url, $args['method'], $args['headers']['Authorization'] ?? null];
            return ['synthetic' => 'response'];
        });
        WP_Mock::userFunction('is_wp_error', ['return' => false]);
        WP_Mock::userFunction('wp_remote_retrieve_body', ['return' => json_encode($this->pendingOrder())]);
        WP_Mock::userFunction('wp_remote_retrieve_response_code', ['return' => 200]);
        WP_Mock::userFunction('wp_remote_retrieve_headers', ['return' => []]);
        WP_Mock::userFunction('wp_next_scheduled', ['return' => false]);
        // A non-terminal answer schedules the next attempt five minutes later.
        WP_Mock::userFunction('wp_schedule_single_event')->once()
            ->with(Mockery::type('int'), 'mp_smart_pix_reconcile_order', [321, 2])->andReturn(true);
        $sent = [];
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post')->andReturnUsing(function ($url, $arguments) use (&$sent) {
            $sent[$url] = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            return [];
        });

        SmartPixCoreNotificationHandlerFactory::reconciliationFromPlugin()->run(321, 1);

        $this->assertSame([
            'https://api.mercadopago.com/plugins-platforms/v1/orders/' . self::ORDER_REFERENCE,
            'GET',
            'Bearer ' . self::ACCESS_TOKEN,
        ], $requested);
        $url = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/' . SmartPixMetricSchema::RECONCILIATION;
        $this->assertArrayHasKey($url, $sent);
        // The reschedule is counted on the production Monitor, correlated with the Orders read,
        // and tagged as the test environment of this test-mode store.
        $this->assertSame('1', $sent[$url]['value']);
        $this->assertSame('server', $sent[$url]['message']);
        $this->assertSame('homol', $sent[$url]['details']['environment']);
        $this->assertSame('GET /plugins-platforms/v1/orders/{id}', $sent[$url]['details']['api_route']);
    }

    private function database()
    {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';

        return $database;
    }

    /** @return array{0: Seller, 1: Store, 2: OrderMetadata} */
    private function configuration(bool $testMode = true): array
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn(self::ACCESS_TOKEN);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('isTestMode')->andReturn($testMode);
        $metadata = Mockery::mock(OrderMetadata::class);
        $metadata->shouldNotReceive('persistSmartPixAttempt');
        $metadata->shouldReceive('persistSmartPixObservation');

        return [$seller, $store, $metadata];
    }

    /** The order loaded by linkage and re-read by the query. */
    private function linkedOrder($metadata)
    {
        $order = Mockery::mock(WC_Order::class);
        $dataStore = Mockery::mock();
        $dataStore->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_data_store')->once()->andReturn($dataStore);
        $order->shouldReceive('read_meta_data')->with(true)->twice();
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldNotReceive('save', 'payment_complete', 'update_status');
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('SYNTHETIC_ATTEMPT_KEY');
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE);
        $metadata->shouldReceive('getMpOrderId')->with($order)->andReturn(self::PROVIDER_ORDER_REFERENCE)->byDefault();

        return $order;
    }

    /** @return array<string, mixed> */
    private function fetchedNotification(): array
    {
        return [
            'notification_id' => self::NOTIFICATION_ID,
            'transaction_type' => 'pp_order',
            'external_reference' => 'WC-321',
            'status' => 'processed',
            'payments_details' => [[
                'id' => 'PPPAYSYNTHETIC001',
                'references' => ['order_id' => self::PROVIDER_ORDER_REFERENCE, 'source' => 'mp_order'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function pendingOrder(): array
    {
        return [
            'id' => self::ORDER_REFERENCE, 'external_reference' => 'WC-321', 'status' => 'processing',
            'status_detail' => 'in_process', 'type' => 'online', 'country_code' => 'BRA', 'currency' => 'BRL',
            'total_amount' => '125.50', 'total_paid_amount' => '0.00', 'transactions' => ['payments' => [[
                'id' => 'PAYMENT_SYNTHETIC', 'amount' => '125.50', 'status' => 'processing', 'status_detail' => 'in_process',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer'],
            ]]],
        ];
    }
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Api\Http\WordPressHttpClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixEffectOwnership;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixOperationRepository;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixRefundReconciler;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Refund;
use WP_Mock;

/**
 * Core P&P notification through the production composition: CoreNotification without an
 * injected handler, the plugin container, the WordPress HTTP client and the Core Monitor sink.
 * Only WordPress functions, configuration, order metadata, orders and the options table are
 * doubles. Every request input carries unique sentinels that must never reach a metric or a log.
 */
class SmartPixNotificationTelemetrySentinelTest extends TestCase
{
    public function testProviderFullRefundIsFetchedAndAcknowledgedWithoutRepeatingAnExistingNativeRefund(): void
    {
        $raw = $this->approvedOrder();
        $raw['status'] = $raw['payments'][0]['status'] = 'refunded';
        $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'refunded';
        $raw['payments'][0]['refunds'] = [['transaction_id' => $raw['payments'][0]['id'], 'amount' => '125.50', 'status' => 'processed', 'references' => ['refund_order_id' => 'REFUND_SYNTHETIC']]];
        $this->loadPlugin(200, $raw);
        $native = $this->orderSnapshot('refunded', false, 1);
        $native->shouldReceive('get_total_refunded')->andReturn(125.50);
        $this->metadata->shouldReceive('getPpPaymentId')->with($native)->andReturn(self::PAYMENT_REFERENCE);
        $this->forbidEffects($native);
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 3, 'return' => self::STORE_PREFIX]);
        // Refund confirmations do not emit a fresh approval.
        WP_Mock::userFunction('wc_get_order', [
            'args' => [self::ORDER_ID],
            'times' => 2,
            'return_in_order' => [$this->linkedOrder(), $native],
        ]);
        $this->notification()->handleReceivedNotification($this->query());
        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSame([SmartPixMetricSchema::EFFECT, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame('effect_no_change', $this->metrics[0]['body']['details']['event']);
        $this->assertCount(2, $this->requests);
        $this->assertSentinelsAbsent();
    }

    public function testPartialRefundNotificationAndRedeliveryConfirmTheNativeRefundWithoutFinancialEffects(): void
    {
        $this->loadPlugin(200, $this->partiallyRefundedOrder());
        $orders = [];
        for ($delivery = 0; $delivery < 2; $delivery++) {
            $orders[] = $this->linkedOrder();
            $native = $this->orderSnapshot('processing', true, 1, [$this->settledNativeRefund()]);
            $native->shouldReceive('get_total_refunded')->andReturn(10.0);
            $this->metadata->shouldReceive('getPpPaymentId')->with($native)->andReturn(self::PAYMENT_REFERENCE);
            $this->forbidEffects($native);
            $orders[] = $native;
            // A partial refund must not emit a fresh terminal payment result.
        }
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 6, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 4, 'return_in_order' => $orders]);

        $this->notification()->handleReceivedNotification($this->query());
        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame(array_fill(0, 2, [['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]), $this->responses);
        $this->assertSame([SmartPixMetricSchema::EFFECT, SmartPixMetricSchema::TIME,
            SmartPixMetricSchema::EFFECT, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame('effect_no_change', $this->metrics[0]['body']['details']['event']);
        $this->assertSame('effect_no_change', $this->metrics[2]['body']['details']['event']);
        $this->assertSame([], $this->options, 'A partial observation must not consume payment effect ownership.');
        $this->assertCount(4, $this->requests);
        $this->assertSentinelsAbsent(4);
    }

    public function testPartialRefundNotificationRequestsRedeliveryWhileANativeRefundIsUnsettled(): void
    {
        $this->loadPlugin(200, $this->partiallyRefundedOrder());
        $native = $this->orderSnapshot('processing', true, 1, [new \stdClass()]);
        $native->shouldReceive('get_total_refunded')->andReturn(5.0);
        $this->metadata->shouldReceive('getPpPaymentId')->with($native)->andReturn(self::PAYMENT_REFERENCE);
        $this->forbidEffects($native);
        WP_Mock::userFunction('wc_create_refund', ['times' => 0]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 3, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 2, 'return_in_order' => [$this->linkedOrder(), $native]]);

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500]], $this->responses);
        $diagnostic = array_shift($this->metrics);
        $this->assertSame('apply_processing_failed', $diagnostic['body']['details']['event']);
        $this->assertNotEmpty($diagnostic['body']['message']);
        $this->assertSame('effect_uncertain', $this->metrics[0]['body']['details']['event']);
        $this->assertSame('notification_processing_failed', $this->metrics[1]['body']['details']['event']);
        $this->assertSame([], $this->options);
        $this->assertCount(2, $this->requests);
        $this->assertSentinelsAbsent();
    }

    /** Given a panel partial followed by an external partial, when notifications repeat,
     * then fetch authentic Orders history and import only its native remainder, never a financial POST.
     */
    public function testExternalPartialNotificationImportsTheMissingAmountAndAcknowledgesRedelivery(): void
    {
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $this->loadPlugin(200, $this->partiallyRefundedOrder());
        $native = $this->orderSnapshot('processing', true, 8, [], 4);
        $nativeTotal = 5.0;
        $refunds = [$this->settledNativeRefund('5.00')];
        $native->shouldReceive('get_total_refunded')->andReturnUsing(static function () use (&$nativeTotal): float {
            return $nativeTotal;
        });
        $native->shouldReceive('get_refunds')->andReturnUsing(static function () use (&$refunds): array {
            return $refunds;
        });
        $this->metadata->shouldReceive('getPpPaymentId')->with($native)->andReturn(self::PAYMENT_REFERENCE);
        $this->forbidEffects($native);
        $journal = '';
        $native->shouldReceive('get_meta')->with(SmartPixOperationRepository::META_KEY, true, 'edit')
            ->andReturnUsing(static function () use (&$journal): string {
                return $journal;
            });
        $native->shouldReceive('update_meta_data')->with(SmartPixOperationRepository::META_KEY, Mockery::type('string'))->twice()
            ->andReturnUsing(static function ($key, string $value) use (&$journal): void {
                $journal = $value;
            });
        $native->shouldReceive('save_meta_data')->twice();
        $native->shouldReceive('add_order_note')->once()->with('Mercado Pago: partial refund of 5.00 BRL.')->andReturn(88);
        $import = Mockery::mock(WC_Order_Refund::class);
        $import->shouldReceive('get_id')->andReturn(192);
        $import->shouldReceive('get_parent_id')->with('edit')->andReturn(self::ORDER_ID);
        $import->shouldReceive('get_total')->with('edit')->andReturn('-5.00');
        $import->shouldReceive('get_refunded_payment')->with('edit')->andReturn(false);
        $marker = ['attempt' => hash('sha256', self::ATTEMPT_KEY), 'target' => '10.00'];
        $import->shouldReceive('update_meta_data')->once()->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, $marker);
        $import->shouldReceive('save_meta_data')->once();
        $import->shouldReceive('read_meta_data')->once()->with(true);
        $import->shouldReceive('get_meta')->with(SmartPixRefundReconciler::PARTIAL_IMPORT_META, true, 'edit')->andReturn($marker);
        WP_Mock::userFunction('wc_create_refund', [
            'args' => [['order_id' => self::ORDER_ID, 'amount' => '5.00', 'reason' => 'Mercado Pago: payment refunded.',
                'refund_payment' => false, 'restock_items' => false]], 'times' => 1,
            'return' => static function () use (&$nativeTotal, &$refunds, $import): WC_Order_Refund {
                $nativeTotal = 10.0;
                $refunds[] = $import;
                return $import;
            },
        ]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 6,
            'return_in_order' => [$this->linkedOrder(), $native, $native, $native, $this->linkedOrder(), $native]]);

        $this->notification()->handleReceivedNotification($this->query());
        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame(array_fill(0, 2, [['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]), $this->responses);
        $this->assertSame([SmartPixMetricSchema::EFFECT, SmartPixMetricSchema::TIME,
            SmartPixMetricSchema::EFFECT, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame('effect_applied', $this->metrics[0]['body']['details']['event']);
        $this->assertSame('effect_no_change', $this->metrics[2]['body']['details']['event']);
        $this->assertSame(10.0, $nativeTotal);
        $this->assertCount(2, $refunds);
        $this->assertSame([], $this->options, 'Imports must preserve approval ownership.');
        $this->assertCount(4, $this->requests, 'Only notification/Orders GETs may be sent, never a provider refund POST.');
        $records = json_decode($journal, true);
        $this->assertSame('succeeded', reset($records)['status']);
        $this->assertSentinelsAbsent(4);
    }

    private function settledNativeRefund(string $amount = '10.00'): WC_Order_Refund
    {
        $refund = Mockery::mock(WC_Order_Refund::class);
        $refund->shouldReceive('get_id')->andReturn(191);
        $refund->shouldReceive('get_parent_id')->with('edit')->andReturn(self::ORDER_ID);
        $refund->shouldReceive('get_total')->with('edit')->andReturn('-' . $amount);
        $refund->shouldReceive('get_refunded_payment')->with('edit')->andReturn(true);
        return $refund;
    }

    private const NOTIFICATION_ID = 'PPORDSENTINELNOTIF91';

    private const ORDER_REFERENCE = self::NOTIFICATION_ID;

    private const PROVIDER_ORDER_REFERENCE = 'ORDSENTINELORDER91';

    /** Id of the layer behind Core, carried only in the envelope references. */
    private const LAYER_REFERENCE = self::PROVIDER_ORDER_REFERENCE;

    private const PAYMENT_REFERENCE = 'PAYSENTINELPAYMENT91';

    private const STORE_PREFIX = 'SENTINELSTORE-';

    private const ATTEMPT_KEY = 'SENTINEL_ATTEMPT_KEY_91';

    private const ACCESS_TOKEN = 'APP_USR-SENTINEL-SELLER-TOKEN-91';

    private const CPF = '12345678909';

    private const EMAIL = 'sentinel.buyer.91@example.test';

    private const BUYER_NAME = 'SENTINEL_BUYER_NAME_91';

    private const POSTED_ONLY = 'SENTINEL_POSTED_ONLY_91';

    private const ENVELOPE_ONLY = 'SENTINEL_ENVELOPE_ONLY_91';

    private const PROVIDER_ONLY = 'SENTINEL_PROVIDER_ONLY_91';

    private const OTHER_NOTIFICATION_ID = 'PPORDSENTINELOTHER91';

    private const OTHER_REFERENCE = 'ORDSENTINELOTHER91';

    private const PROVIDER_DETAIL = 'sentinel_detail_91';

    private const ORDER_ID = 321;

    private const NOTIFICATION_DETAILS = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'environment' => 'prod'];

    private const APPLY_DETAILS = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'environment' => 'prod'];

    /** @var array<string, mixed> */
    private array $globals = [];

    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $metrics = [];

    /** @var list<array<int, mixed>> */
    private array $logs = [];

    /** @var list<string> */
    private array $requests = [];

    /** @var list<array<int, mixed>> */
    private array $responses = [];

    /** @var array<string, string> */
    private array $options = [];

    /** Terminal observation leases, kept apart from the payment effect ownership in $options. */
    private array $locks = [];

    private $metadata;

    /** Metric transport behavior: deliver, throw or answer a WordPress error. */
    private string $transport = 'deliver';

    protected function setUp(): void
    {
        WP_Mock::setUp();
        foreach (['mercadopago', 'wpdb'] as $name) {
            $this->globals[$name] = $GLOBALS[$name] ?? null;
        }
        $this->metrics = $this->logs = $this->requests = $this->responses = $this->options = $this->locks = [];
        $this->transport = 'deliver';
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldNotReceive('persistSmartPixAttempt');
        $this->metadata->shouldReceive('persistSmartPixObservation');
        $this->interceptWordPress();
    }

    protected function tearDown(): void
    {
        foreach ($this->globals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testApprovedNotificationEmitsBoundedMetricsWithoutSensitiveValues(): void
    {
        $this->loadPlugin(200, $this->approvedOrder());
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', [
            'args' => [self::ORDER_ID],
            'times' => 4,
            'return_in_order' => [$this->linkedOrder(), $pending, $persisted, $this->observedOrder('processing')],
        ]);

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSame([
            SmartPixMetricSchema::EFFECT,
            SmartPixMetricSchema::TIME,
        ], $this->metricNames());
        $this->assertSame('1', $this->metrics[0]['body']['value']);
        $this->assertSame('effect_applied', $this->metrics[0]['body']['details']['event']);
        $this->assertSame('12345', $this->metrics[0]['body']['details']['cust_id']);
        $this->assertSame('notification_processing_succeeded', $this->metrics[1]['body']['details']['event']);
        $this->assertSentinelsAbsent();
    }

    /**
     * A buyer who left the page is counted by the notification, through the production event emitter.
     * Separate process: the plugin platform constant and `site_url` exist only in WordPress.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFirstTerminalObservationByTheNotificationIsCountedOnceWithoutSensitiveValues(): void
    {
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }
        WP_Mock::userFunction('site_url', ['return' => 'https://store.example.test']);
        $this->loadPlugin(200, $this->approvedOrder());
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $observed = $this->observedOrder('processing', '');
        $observed->shouldReceive('read_meta_data')->with(true)->once();
        $this->metadata->shouldReceive('getPpOrderPendingSince')->with($observed)->andReturn((string) (time() - 42));
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($observed, 'approved')->once();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', [
            'args' => [self::ORDER_ID],
            'times' => 4,
            'return_in_order' => [$this->linkedOrder(), $pending, $persisted, $observed],
        ]);

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSame([
            SmartPixMetricSchema::EFFECT,
            'smart_pix_payment_status_observed',
            SmartPixMetricSchema::PAYMENT_APPROVAL_TIME,
            SmartPixMetricSchema::TIME,
        ], $this->metricNames());
        $event = $this->metrics[1]['body'];
        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/smart_pix_payment_status_observed', $this->metrics[1]['url']);
        $this->assertSame('https://store.example.test', $event['platform']['url']);
        $this->assertContains($event['details']['pooling_time'], ['42000', '43000']);
        unset($event['details']['pooling_time']);
        $this->assertSame([
            'payment_method' => 'smart_transfer', 'event' => 'payment_status_observed', 'payment_status' => 'approved',
            'source' => 'notification', 'payment_status_detail' => 'accredited', 'site_id' => 'MLB', 'environment' => 'prod',
            'cust_id' => '12345', 'api_route' => 'GET /plugins-platforms/v1/orders/{id}', 'missing_fields' => 'sdk_instance_id',
        ], $event['details']);
        $approval = $this->metrics[2]['body'];
        $this->assertContains($approval['value'], [42, 43]);
        $this->assertSame('https://store.example.test', $approval['platform']['url']);
        $this->assertSame('12345', $approval['details']['cust_id']);
        $this->assertSame('payment_status_observed', $approval['details']['event']);
        // The lease that serializes the first report was released; payment ownership is separate.
        $this->assertSame([], $this->locks);
        $this->assertSentinelsAbsent();
    }

    public function testOrdersReadFailureEmitsTheServerErrorWithoutSensitiveValues(): void
    {
        // The provider error echoes request material; none of it may be forwarded.
        $this->loadPlugin(500, [
            'message' => self::ACCESS_TOKEN . ' ' . self::CPF . ' ' . self::EMAIL,
            'error' => 'provider_unavailable',
            'cause' => [['code' => 'DEPENDENCY_FAILURE', 'description' => 'The provider could not process the Order.']],
        ]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 1, 'return' => $this->linkedOrder()]);

        $this->notification()->handleReceivedNotification($this->query());

        // A retryable read failure asks Core to redeliver; the order is untouched.
        $this->assertSame([[['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500]], $this->responses);
        $this->assertSame([SmartPixMetricSchema::ERROR, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame(
            ['event' => 'notification_processing_failed', 'reason' => 'http_5xx'],
            array_intersect_key($this->metrics[0]['body']['details'], ['event' => true, 'reason' => true])
        );
        $this->assertSame('notification_processing_failed', $this->metrics[1]['body']['details']['event']);
        $this->assertSame([], $this->options);
        $this->assertSentinelsAbsent();
    }

    /** @dataProvider rawRejectionProvider */
    public function testRawReceiverRejectionsEmitOnlyTheClosedCategoryWithoutSensitiveValues(
        ?string $body,
        bool $authenticated,
        int $status,
        string $message,
        string $category,
        string $reason,
        int $authCalls
    ): void {
        $this->loadPlugin(200, $this->approvedOrder());
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $authenticator = $this->authenticator($authenticated);
        $raw = $body === 'envelope' ? (string) json_encode($this->fetchedNotification()) : $body;
        if ($body === 'oversized') {
            $raw = (string) json_encode(['note' => str_repeat(self::ENVELOPE_ONLY, 1000)] + $this->fetchedNotification());
        }

        $result = $this->rawReceiver($authenticator, $raw)->handle($this->rawHeaders(), null);
        $this->responses[] = [$result];

        $this->assertSame(['status' => $status, 'message' => $message], $result);
        $this->assertCount($authCalls, $authenticator->calls);
        if ($authCalls > 0) {
            // The sender's credentials and the posted personal data reached the authenticator.
            $this->assertSame([[$this->rawHeaders(), $raw]], $authenticator->calls);
            $this->assertStringContainsString(self::CPF, $raw);
        }
        $this->assertRejectionMetrics($category, $reason);
        $this->assertSame([], $this->options);
        $this->assertSentinelsAbsent(0);
    }

    /** @return array<string, array{0: string|null, 1: bool, 2: int, 3: string, 4: string, 5: string, 6: int}> */
    public static function rawRejectionProvider(): array
    {
        $invalid = SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD;
        return [
            'not authenticated' => ['envelope', false, 401, SmartPixNotificationReceiver::MESSAGE_UNAUTHENTICATED, 'invalid_auth', '', 1],
            'body above the limit' => ['oversized', true, 413, SmartPixNotificationReceiver::MESSAGE_TOO_LARGE, 'body_too_large', '', 0],
            'unreadable body' => [null, true, 400, $invalid, 'invalid_payload', 'unreadable_body', 0],
        ];
    }

    public function testRawReceiverUnlinkedNotificationIsIgnoredWithOnlyTheClosedCategory(): void
    {
        $this->loadPlugin(200, $this->approvedOrder());
        $order = $this->orderSnapshot('pending', false, 1);
        $this->forbidEffects($order);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 1, 'return' => $order]);
        $envelope = $this->fetchedNotification();
        // The notification names another Core Order than the one persisted for the attempt.
        $envelope['notification_id'] = self::OTHER_NOTIFICATION_ID;
        $raw = (string) json_encode($envelope);
        $authenticator = $this->authenticator(true);

        $result = $this->rawReceiver($authenticator, $raw)->handle($this->rawHeaders(), (string) strlen($raw));
        $this->responses[] = [$result];

        $this->assertSame(['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_IGNORED], $result);
        $this->assertSame([[$this->rawHeaders(), $raw]], $authenticator->calls);
        $this->assertRejectionMetrics('invalid_linkage', '');
        $this->assertSame([], $this->options);
        $this->assertSentinelsAbsent(0);
    }

    /**
     * @dataProvider failingTransportProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFailingMetricTransportChangesNeitherTheResponseNorTheOrderEffect(string $transport): void
    {
        if ($transport === 'wp_error' && !class_exists('WP_Error')) {
            Mockery::mock('alias:WP_Error');
        }
        $this->transport = $transport;
        $this->expectApprovedEffect();

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        // Every metric was attempted once, without retries.
        $this->assertSame([
            SmartPixMetricSchema::EFFECT,
            SmartPixMetricSchema::TIME,
        ], $this->metricNames());
        $this->assertSentinelsAbsent();
    }

    /** @return array<string, array{0: string}> */
    public static function failingTransportProvider(): array
    {
        return ['transport throws' => ['throw'], 'transport answers a WordPress error' => ['wp_error']];
    }

    /**
     * @dataProvider unusableWooCommerceVersionProvider
     * @param array<string, mixed> $container
     */
    public function testUnusableWooCommerceVersionDisablesEmissionWithoutAffectingProcessing(array $container): void
    {
        $this->expectApprovedEffect();
        $plugin = $GLOBALS['mercadopago'];
        unset($plugin->woocommerce);
        foreach ($container as $name => $value) {
            $plugin->{$name} = $value;
        }

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSentinelsAbsent(2, false);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function unusableWooCommerceVersionProvider(): array
    {
        return [
            'container without WooCommerce' => [[]],
            'WooCommerce without version' => [['woocommerce' => (object) []]],
            'non-string version' => [['woocommerce' => (object) ['version' => 981]]],
            'incomplete version' => [['woocommerce' => (object) ['version' => '9.8']]],
            'version carrying a sentinel' => [['woocommerce' => (object) ['version' => '9.8.1-SENTINEL_WC_91']]],
        ];
    }

    public function testFetchedNotificationIdMismatchEmitsOnlyTheInvalidPayloadRejection(): void
    {
        $this->loadPlugin(200, $this->approvedOrder(), ['notification_id' => self::OTHER_NOTIFICATION_ID] + $this->fetchedNotification());
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[
            ['status' => 400, 'message' => SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD],
            400,
        ]], $this->responses);
        $this->assertRejectionMetrics('invalid_payload', '', 'GET /plugins-platforms/v1/notification/order/{id}', '12345');
        $this->assertSame([], $this->options);
        $this->assertSentinelsAbsent(1);
    }

    public function testOrdersAnswerAboutAnotherOrderEmitsTheInvalidContractErrorWithoutEffects(): void
    {
        $this->loadPlugin(200, ['id' => self::OTHER_REFERENCE] + $this->approvedOrder());
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', ['args' => [self::ORDER_ID], 'times' => 1, 'return' => $this->linkedOrder()]);

        $this->notification()->handleReceivedNotification($this->query());

        // A non-retryable answer is acknowledged; the order is untouched.
        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSame([SmartPixMetricSchema::ERROR, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame('notification_processing_failed', $this->metrics[0]['body']['details']['event']);
        $this->assertSame('invalid_contract', $this->metrics[0]['body']['details']['reason']);
        $this->assertSame('notification_processing_succeeded', $this->metrics[1]['body']['details']['event']);
        $this->assertSame([], $this->options);
        $this->assertSentinelsAbsent();
    }

    /**
     * A terminal rejection is reported by the backend even with no buyer page open.
     * @dataProvider rejectionDetailProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTerminalRejectionPreservesTheOriginalReasonWithoutABrowser(string $detail): void
    {
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }
        WP_Mock::userFunction('site_url', ['return' => 'https://store.example.test']);
        $this->loadPlugin(200, $this->rejectedOrder($detail));
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        $pending->shouldNotReceive('payment_complete');
        $persisted = $this->orderSnapshot('failed', false, 1);
        $this->forbidEffects($persisted);
        $observed = $this->observedOrder('failed', '');
        $observed->shouldReceive('read_meta_data')->with(true)->once();
        $this->metadata->shouldReceive('setPpOrderTerminalReported')->with($observed, 'rejected')->once();
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', [
            'args' => [self::ORDER_ID], 'times' => 4,
            'return_in_order' => [$this->linkedOrder(), $pending, $persisted, $observed],
        ]);

        $this->notification()->handleReceivedNotification($this->query());

        $this->assertSame([[['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200]], $this->responses);
        $this->assertSame([
            SmartPixMetricSchema::EFFECT, 'smart_pix_payment_status_observed',
            SmartPixMetricSchema::PAYMENT_REJECTION_TIME, SmartPixMetricSchema::TIME,
        ], $this->metricNames());
        $event = $this->metrics[1]['body'];
        $this->assertSame('rejected', $event['details']['payment_status']);
        $this->assertSame('notification', $event['details']['source']);
        $this->assertSame($detail, $event['details']['reason']);
        $this->assertSame($detail, $event['details']['payment_status_detail']);
        $this->assertContains($event['details']['pooling_time'], ['42000', '43000']);
        $this->assertArrayNotHasKey('duration_bucket', $event['details']);
        $this->assertContains($this->metrics[2]['body']['value'], [42, 43]);
        $this->assertSentinelsAbsent();
    }

    public static function rejectionDetailProvider(): array
    {
        return [['high_risk'], ['insufficient_amount'], ['rejected_by_regulations']];
    }

    private function expectApprovedEffect(): void
    {
        $this->loadPlugin(200, $this->approvedOrder());
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => self::STORE_PREFIX]);
        WP_Mock::userFunction('wc_get_order', [
            'args' => [self::ORDER_ID],
            'times' => 4,
            'return_in_order' => [$this->linkedOrder(), $pending, $persisted, $this->observedOrder('processing')],
        ]);
    }

    /**
     * Notification context with extra dimensions in emission order, before the sink's environment.
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function notificationDetails(array $extra): array
    {
        $details = self::NOTIFICATION_DETAILS;
        unset($details['environment']);
        return $details + $extra + ['environment' => 'prod'];
    }

    /** @param string|null $custId Seller account added by the plugin sink; the raw receiver sink has none. */
    private function assertRejectionMetrics(string $category, string $reason, ?string $route = null, ?string $custId = null): void
    {
        $this->assertSame([SmartPixMetricSchema::NOTIFICATION_REJECTED, SmartPixMetricSchema::TIME], $this->metricNames());
        $this->assertSame(
            ['value' => '1', 'message' => 'server', 'details' => [
                'payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'notification_rejected',
                'reason' => $reason !== '' ? $reason : $category, 'environment' => 'prod']
                + ($custId === null ? [] : ['cust_id' => $custId])
                + ($route === null ? [] : ['api_route' => $route]) + ['missing_fields' => 'sdk_instance_id']],
            array_intersect_key($this->metrics[0]['body'], ['value' => true, 'message' => true, 'details' => true])
        );
        $this->assertSame('notification_processing_failed', $this->metrics[1]['body']['details']['event']);
        $this->assertGreaterThan(0, $this->metrics[1]['body']['details']['request_time']);
    }

    private function assertSentinelsAbsent(int $requests = 2, bool $emits = true): void
    {
        // The sentinels did travel through the HTTP layer, so their absence below is meaningful.
        $this->assertCount($requests, $this->requests);
        if ($requests > 0) {
            $this->assertStringContainsString(self::NOTIFICATION_ID, $this->requests[0]);
        }
        if ($requests > 1) {
            $this->assertStringContainsString(self::ORDER_REFERENCE, $this->requests[1]);
        }
        $emits ? $this->assertNotSame([], $this->metrics) : $this->assertSame([], $this->metrics);

        $observed = (string) json_encode(['metrics' => $this->metrics, 'logs' => $this->logs, 'responses' => $this->responses]);
        $sentinels = [
            self::NOTIFICATION_ID, self::ORDER_REFERENCE, self::LAYER_REFERENCE, self::PAYMENT_REFERENCE, self::STORE_PREFIX,
            self::ATTEMPT_KEY, self::ACCESS_TOKEN, self::CPF, self::EMAIL, self::BUYER_NAME,
            self::POSTED_ONLY, self::ENVELOPE_ONLY, self::PROVIDER_ONLY, self::OTHER_NOTIFICATION_ID,
            self::OTHER_REFERENCE, self::PROVIDER_DETAIL, 'Bearer', 'Authorization', 'SENTINEL',
        ];
        foreach ($sentinels as $sentinel) {
            $this->assertStringNotContainsStringIgnoringCase($sentinel, $observed, 'Sensitive value leaked: ' . $sentinel);
        }
        foreach ($this->metrics as $metric) {
            $this->assertSame(['value', 'plugin_version', 'platform', 'details'], array_values(array_diff(
                array_keys($metric['body']),
                ['message']
            )));
            $this->assertSame('prod', $metric['body']['details']['environment']);
        }
    }

    /** @return list<string> */
    private function metricNames(): array
    {
        return array_map(static function (array $metric): string {
            return (string) substr($metric['url'], (int) strrpos($metric['url'], '/') + 1);
        }, $this->metrics);
    }

    /**
     * @param array<string, mixed> $orderBody
     * @param array<string, mixed>|null $fetched Fetched notification; defaults to the requested one.
     */
    private function loadPlugin(int $orderStatus, array $orderBody, ?array $fetched = null): void
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn(self::ACCESS_TOKEN);
        $seller->shouldReceive('getCustIdFromAT')->andReturn('12345');
        $store = Mockery::mock(Store::class);
        // A production-mode store: a test-mode store would tag every metric `homol`.
        $store->shouldReceive('isTestMode')->andReturn(false);
        $store->shouldReceive('getIntegratorId')->andReturn('');
        $noteWriter = Mockery::mock(OrderStatus::class);
        $noteWriter->shouldReceive('addSmartPixStatusNotes')->andReturnNull();
        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $seller,
            'storeConfig' => $store,
            'orderMetadata' => $this->metadata,
            'orderStatus' => $noteWriter,
            'smartPixNativeEffects' => new \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects(),
            'woocommerce' => (object) ['version' => '9.8.1'],
        ];
        $GLOBALS['wpdb'] = $this->optionsTable();

        $answers = [
            '/plugins-platforms/v1/notification/order/' => [200, $fetched ?? $this->fetchedNotification()],
            '/v1/orders/' => [$orderStatus, $orderBody],
        ];
        WP_Mock::userFunction('wp_remote_request')->andReturnUsing(function (string $url) use ($answers): array {
            $this->requests[] = $url;
            foreach ($answers as $path => [$status, $body]) {
                if (strpos($url, $path) !== false) {
                    return ['code' => $status, 'body' => (string) json_encode($body)];
                }
            }
            $this->fail('Unexpected request.');
        });
    }

    private function interceptWordPress(): void
    {
        $logger = new class ($this->logs) {
            /** @var list<array<int, mixed>> */
            private array $calls;

            /** @param list<array<int, mixed>> $calls */
            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            /** @param array<int, mixed> $arguments */
            public function __call(string $method, array $arguments): void
            {
                $this->calls[] = array_merge(['wc_logger', $method], $arguments);
            }
        };
        WP_Mock::userFunction('wc_get_logger', ['return' => $logger]);
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('is_wp_error', ['return' => false]);
        WP_Mock::userFunction('wp_remote_retrieve_body')->andReturnUsing(static fn(array $response): string => $response['body']);
        WP_Mock::userFunction('wp_remote_retrieve_response_code')->andReturnUsing(static fn(array $response): int => $response['code']);
        WP_Mock::userFunction('wp_remote_retrieve_headers', ['return' => []]);
        WP_Mock::userFunction('wp_cache_delete', ['return' => true]);
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post')->andReturnUsing(function (string $url, array $arguments) {
            $this->metrics[] = ['url' => $url, 'body' => json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR)];
            if ($this->transport === 'throw') {
                throw new \Error(self::PROVIDER_ONLY . ' Authorization: Bearer ' . self::ACCESS_TOKEN);
            }
            return $this->transport === 'wp_error' ? new \WP_Error() : [];
        });
        WP_Mock::userFunction('wp_send_json')->andReturnUsing(function (...$arguments): void {
            $this->responses[] = $arguments;
        });
    }

    private function notification(): CoreNotification
    {
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info', 'error', 'warning', 'debug', 'notice')->andReturnUsing(function (...$arguments): void {
            $this->logs[] = array_merge(['file'], $arguments);
        });
        $logs = Mockery::mock(Logs::class);
        $logs->file = $file;
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus->shouldNotReceive('processStatus');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $plugin = $GLOBALS['mercadopago'];

        // No handler is injected, so the route composes the productive one from the plugin.
        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            $orderStatus,
            $plugin->sellerConfig,
            $plugin->storeConfig,
            $legacyClient,
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $notification->shouldReceive('getInput')->andReturn((string) json_encode([
            'notification_id' => self::NOTIFICATION_ID,
            'external_reference' => self::STORE_PREFIX . '999',
            'payer' => ['email' => self::EMAIL, 'identification' => ['number' => self::CPF]],
            'note' => self::POSTED_ONLY,
        ]));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        return $notification;
    }

    /**
     * The factory composition with an injected body source and authenticator: the plugin wires
     * the deny-all authenticator, and the Core flow never reaches the raw-body entry.
     */
    private function rawReceiver(SmartPixNotificationAuthenticator $authenticator, ?string $body): SmartPixNotificationReceiver
    {
        $plugin = $GLOBALS['mercadopago'];
        $seller = $plugin->sellerConfig;
        $source = SmartPixNotificationReceiver::SOURCE;
        $telemetry = new SmartPixTelemetry(new SmartPixCoreMonitorMetricSink('prod', '9.8.1', MP_VERSION));
        $client = new RealSmartPixOrderClient(
            new WordPressHttpClient(Requester::BASEURL_MP),
            $seller,
            $plugin->storeConfig,
            $telemetry,
            $source
        );
        $opener = static function () use ($body) {
            if ($body === null) {
                return false;
            }
            $stream = fopen('php://memory', 'r+b');
            fwrite($stream, $body);
            rewind($stream);
            return $stream;
        };

        return new SmartPixNotificationReceiver(
            new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $opener),
            $authenticator,
            new SmartPixCoreNotificationIdentifiers(),
            new SmartPixNotificationLinkage($this->metadata, $seller),
            new SmartPixOrderQuery($client, $this->metadata, $seller, $telemetry, $source),
            new SmartPixStateApplicator(
                $this->metadata,
                new SmartPixStateReducer(),
                $seller,
                new SmartPixEffectOwnership($GLOBALS['wpdb']),
                $telemetry
            ),
            $telemetry
        );
    }

    private function authenticator(bool $result): SmartPixNotificationAuthenticator
    {
        return new class ($result) implements SmartPixNotificationAuthenticator {
            /** @var list<array{array<string, string>, string}> */
            public array $calls = [];

            private bool $result;

            public function __construct(bool $result)
            {
                $this->result = $result;
            }

            /** @param array<string, string> $headers */
            public function authenticate(array $headers, string $rawBody): bool
            {
                $this->calls[] = [$headers, $rawBody];
                return $this->result;
            }
        };
    }

    /** @return array<string, string> */
    private function rawHeaders(): array
    {
        return ['authorization' => 'Bearer ' . self::ACCESS_TOKEN, 'x-signature' => self::POSTED_ONLY];
    }

    /** @return array<string, string> */
    private function query(): array
    {
        return ['wc-api' => 'WC_WooMercadoPago_Pix_Gateway', 'source_news' => 'webhooks'];
    }

    /** In-memory options table honoring the ownership store's conditional statements. */
    private function optionsTable()
    {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $database->shouldReceive('suppress_errors')->andReturn(false);
        $database->shouldReceive('prepare')->andReturnUsing(static function (string $sql, ...$values): string {
            return (string) json_encode([strtok($sql, ' '), $values]);
        });
        $database->shouldReceive('query')->andReturnUsing(function (string $statement): int {
            [$verb, $values] = json_decode($statement, true);
            if ($verb === 'INSERT') {
                if (isset($this->options[$values[0]])) {
                    return 0;
                }
                $this->options[$values[0]] = $values[1];
                return 1;
            }
            if ($verb === 'UPDATE' && ($this->options[$values[2]] ?? null) === $values[3]) {
                $this->options[$values[2]] = $values[0];
                return 1;
            }
            if ($verb === 'DELETE' && ($this->options[$values[0]] ?? null) === $values[1]) {
                unset($this->options[$values[0]]);
                return 1;
            }
            return 0;
        });
        $database->shouldReceive('get_var')->andReturnUsing(function (string $statement): ?string {
            [, $values] = json_decode($statement, true);
            return $this->options[$values[0]] ?? $this->locks[$values[0]] ?? null;
        });
        // Only the terminal observation lease uses the insert/delete helpers.
        $database->shouldReceive('insert')->andReturnUsing(function (string $table, array $row): int {
            $this->assertTrue(strpos($row['option_name'], SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX) === 0
                || strpos($row['option_name'], 'mp_smart_pix_operation_lock_') === 0);
            if (isset($this->locks[$row['option_name']])) {
                return 0;
            }
            $this->locks[$row['option_name']] = $row['option_value'];
            return 1;
        });
        $database->shouldReceive('delete')->andReturnUsing(function (string $table, array $where): int {
            if (($this->locks[$where['option_name']] ?? null) !== $where['option_value']) {
                return 0;
            }
            unset($this->locks[$where['option_name']]);
            return 1;
        });

        return $database;
    }

    /** @return array<string, mixed> */
    private function fetchedNotification(): array
    {
        return [
            'notification_id' => self::NOTIFICATION_ID,
            'site_id' => 'MLB',
            'status' => 'processed',
            'transaction_type' => 'pp_order',
            'external_reference' => self::STORE_PREFIX . self::ORDER_ID,
            'transaction_amount' => 125.5,
            'payer' => ['email' => self::EMAIL, 'identification' => ['type' => 'CPF', 'number' => self::CPF]],
            'payments_details' => [[
                'id' => 'PPPAYSENTINEL91',
                'payment_method_id' => 'smart_transfer',
                'status' => 'processed',
                'references' => [
                    'order_id' => self::PROVIDER_ORDER_REFERENCE,
                    'order_transaction_id' => self::PAYMENT_REFERENCE,
                    'source' => 'mp_order',
                ],
            ]],
            'note' => self::ENVELOPE_ONLY,
        ];
    }

    /** @return array<string, mixed> */
    private function approvedOrder(): array
    {
        return [
            'id' => self::ORDER_REFERENCE, 'type' => 'online',
            'external_reference' => self::STORE_PREFIX . self::ORDER_ID, 'site_id' => 'MLB', 'currency' => 'BRL',
            'total_amount' => '125.50', 'total_paid_amount' => '125.50',
            'status' => 'processed', 'status_detail' => 'accredited',
            'payer' => [
                'email' => self::EMAIL,
                'first_name' => self::BUYER_NAME,
                'identification' => ['type' => 'CPF', 'number' => self::CPF],
            ],
            'payments' => [[
                'id' => self::PAYMENT_REFERENCE, 'status' => 'processed', 'status_detail' => 'accredited',
                'amount' => '125.50', 'paid_amount' => '125.50',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer', 'token' => self::PROVIDER_ONLY],
                'references' => ['source' => 'mp_order', 'payment_id' => self::PROVIDER_ONLY],
            ]],
        ];
    }

    /** A failed Order whose only payment mirrors the provider rejection detail. */
    private function rejectedOrder(string $detail): array
    {
        return [
            'id' => self::ORDER_REFERENCE, 'type' => 'online',
            'external_reference' => self::STORE_PREFIX . self::ORDER_ID, 'site_id' => 'MLB', 'currency' => 'BRL',
            'total_amount' => '125.50', 'total_paid_amount' => '0.00',
            'status' => 'failed', 'status_detail' => $detail,
            'payer' => ['email' => self::EMAIL, 'identification' => ['type' => 'CPF', 'number' => self::CPF]],
            'payments' => [[
                'id' => self::PAYMENT_REFERENCE, 'status' => 'failed', 'status_detail' => $detail, 'amount' => '125.50',
                'payment_method' => ['id' => 'smart_transfer', 'type' => 'smart_transfer', 'token' => self::PROVIDER_ONLY],
            ]],
        ];
    }

    private function partiallyRefundedOrder(): array
    {
        $raw = $this->approvedOrder();
        $raw['status_detail'] = $raw['payments'][0]['status_detail'] = 'partially_refunded';
        $raw['payments'][0]['refunds'] = [['transaction_id' => $raw['payments'][0]['id'], 'amount' => '10.00', 'status' => 'processed', 'references' => ['refund_order_id' => 'REFUND_SYNTHETIC']]];
        return $raw;
    }

    /** The order loaded by linkage and re-read by the query without the edit context. */
    private function linkedOrder()
    {
        $order = $this->orderSnapshot('pending', false, 2);
        $order->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($order);
        return $order;
    }

    private function orderSnapshot(string $status, bool $paid, int $metaReads, array $refunds = [], int $nativeReads = 1)
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->times($nativeReads);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
        $order->shouldReceive('get_data_store')->times($nativeReads)->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->times($metaReads);
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $order->shouldReceive('get_status')->with('edit')->andReturn($status);
        $order->shouldReceive('is_paid')->andReturn($paid);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($paid ? new \stdClass() : null);
        $order->shouldReceive('get_refunds')->andReturn($refunds)->byDefault();
        $order->shouldReceive('get_transaction_id')->with('edit')->andReturn('');
        $order->shouldNotReceive('save');
        $this->metadata->shouldReceive('getPaymentContract')->with($order)
            ->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn(self::ATTEMPT_KEY);
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE);
        $this->metadata->shouldReceive('getMpOrderId')->with($order)->andReturn(self::PROVIDER_ORDER_REFERENCE)->byDefault();
        return $order;
    }

    /**
     * The order re-read by the notification's terminal observation. By default a poll or page
     * render already reported this attempt, so the observation reports nothing again.
     */
    private function observedOrder(string $status, string $reported = 'approved')
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
        $order->shouldReceive('get_status')->withNoArgs()->andReturn($status);
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'));
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)->andReturn('');
        $order->shouldNotReceive('save', 'payment_complete', 'update_status');
        $this->metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturn($reported);
        if ($reported !== '') {
            $this->metadata->shouldNotReceive('setPpOrderTerminalReported')->with($order);
        }
        return $order;
    }

    private function forbidEffects($order): void
    {
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
    }
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\AbstractNotification;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\DenyAllSmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationHandler;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;
use WP_Mock;

/**
 * Core P&P route: CoreNotification receives a PPORD id and the Smart Pix pipeline runs with
 * real collaborators, mocked interfaces and WooCommerce doubles, as in the receiver tests.
 */
class SmartPixCoreNotificationHandlerTest extends TestCase
{
    use AssertsApplicationDiagnostics;

    private const NOTIFICATION_ID = 'PPORDSYNTHETIC001';

    private const ORDER_REFERENCE = self::NOTIFICATION_ID;

    private const PROVIDER_ORDER_REFERENCE = 'ORDSYNTHETIC001';

    /** Id of the layer behind Core, carried only in the envelope references. */
    private const LAYER_REFERENCE = self::PROVIDER_ORDER_REFERENCE;

    private const ATTEMPT_KEY = 'SYNTHETIC_ATTEMPT_KEY';

    private const ACCESS_TOKEN = 'SYNTHETIC_ACCESS_TOKEN';

    private const SECRET = 'SYNTHETIC_SECRET_VALUE';

    private const APPLY = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    private const NOTIFICATION = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    /** @var list<array<int, mixed>> */
    private array $events = [];

    private $metadata;

    private $seller;

    private $orderClient;

    private $ownership;

    private $http;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $logger = Mockery::mock();
        $logger->shouldNotReceive('warning');
        WP_Mock::userFunction('wc_get_logger', ['return' => $logger]);
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        $this->events = [];
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldNotReceive('persistSmartPixAttempt');
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getSiteId')->andReturn('MLB');
        $this->seller->shouldReceive('getCredentialsAccessToken')->andReturn(self::ACCESS_TOKEN);
        $this->orderClient = Mockery::mock(SmartPixOrderClient::class);
        $this->orderClient->shouldNotReceive('create', 'cancel', 'refund');
        $this->ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $this->http = Mockery::mock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testCoreNotificationWithSmartPixIdFetchesTheNotificationAndAppliesTheOrdersStateOnce(): void
    {
        // The posted body names the notification only; its other fields must be ignored.
        $posted = (string) json_encode([
            'notification_id' => self::NOTIFICATION_ID,
            'external_reference' => 'WC-999',
            'status' => 'processed',
            'payments_details' => [['references' => ['order_id' => 'ORDPOSTEDONLY']]],
        ]);
        $this->http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/notification/order/' . self::NOTIFICATION_ID, [
            'Authorization' => 'Bearer ' . self::ACCESS_TOKEN,
            'x-platform-id' => MP_PLATFORM_ID,
            'x-product-id' => MP_PRODUCT_ID_DESKTOP,
        ], null)->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));

        $linked = $this->linkedOrder();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2, 'return' => 'WC-']);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 3, 'return_in_order' => [$linked, $pending, $persisted]]);
        $this->orderClient->shouldReceive('query')->once()->with([
            'surface' => 'server',
            'country' => 'MLB',
            'payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
            'attempt_reference' => 'WC-321',
            'order_reference' => self::ORDER_REFERENCE,
            'amount' => null,
            'refund_amount' => null,
        ])->andReturn($this->approvedQuery());
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('start')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_PROCESSED], 200],
            'times' => 1,
        ]);
        $query = ['wc-api' => 'WC_WooMercadoPago_Pix_Gateway', 'source_news' => 'webhooks'];
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, $query);
        $file->shouldNotReceive('error');
        $notification = $this->coreNotification($file, $posted);

        $notification->handleReceivedNotification($query);

        $this->assertSame([
            ['count', SmartPixMetricSchema::EFFECT, 'applied',
                self::APPLY + ['reason_class' => 'native_verification', 'native_status' => 'processing']],
            ['distribution', SmartPixMetricSchema::TIME, 0.25, self::NOTIFICATION + ['outcome' => 'success']],
        ], $this->events);
    }

    /** @dataProvider fetchFailureProvider */
    public function testFetchFailuresAnswerAFixedServerErrorWithoutLinkageQueryOrEffects(callable $answer, string $diagnostic): void
    {
        // Fetched once with the given current-mode context: no failure is retried with another token.
        $this->http->shouldReceive('request')->once()
            ->with('GET', '/plugins-platforms/v1/notification/order/' . self::NOTIFICATION_ID, Mockery::type('array'), null)
            ->andReturnUsing($answer);
        $this->forbidLinkageAndEffects();

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertSame([$this->error('unavailable', $diagnostic), $this->time('failure')], $this->events);
    }

    public static function fetchFailureProvider(): array
    {
        $status = static function (int $status): callable {
            return static function () use ($status): HttpResponse {
                return new HttpResponse($status, [], '', ['message' => self::SECRET, 'status' => $status]);
            };
        };
        $cases = [
            'unauthorized' => [$status(401)],
            'not found' => [$status(404)],
            'internal server error' => [$status(500)],
            'service unavailable' => [$status(503)],
            'transport error' => [static function (): HttpResponse {
                throw new TransportException(self::SECRET);
            }],
            'unexpected throwable' => [static function (): HttpResponse {
                throw new RuntimeException(self::SECRET . ' ' . self::NOTIFICATION_ID);
            }],
            'non-object answer' => [static function (): HttpResponse {
                return new HttpResponse(200, [], '', self::NOTIFICATION_ID);
            }],
            'empty answer' => [static function (): HttpResponse {
                return new HttpResponse(200, [], '', null);
            }],
        ];
        $diagnostics = [];
        foreach (['unauthorized' => 401, 'not found' => 404, 'internal server error' => 500, 'service unavailable' => 503] as $name => $status) {
            $diagnostics[$name] = 'notification.authentication: MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException [' . $status . ']: ' . self::SECRET;
        }
        $diagnostics['transport error'] = 'notification.authentication: MercadoPago\Woocommerce\Api\Http\TransportException [' . self::SECRET . ']: Unable to connect to Mercado Pago API.';
        $diagnostics['unexpected throwable'] = 'notification.authentication: RuntimeException [0]: ' . self::SECRET . ' ' . self::NOTIFICATION_ID;
        $diagnostics['non-object answer'] = $diagnostics['empty answer'] = 'notification.authentication: UnexpectedValueException [0]: Core notification response must be a JSON object.';
        foreach ($cases as $name => &$case) {
            $case[] = $diagnostics[$name];
        }
        return $cases;
    }

    public function testCoreNotificationFetchFailureSendsTheFixedServerErrorAndLogsNothingFromTheNotification(): void
    {
        $posted = (string) json_encode(['notification_id' => self::NOTIFICATION_ID]);
        $this->http->shouldReceive('request')->once()
            ->andReturn(new HttpResponse(500, [], '', ['message' => self::SECRET, 'status' => 500]));
        $this->forbidLinkageAndEffects();
        WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500],
            'times' => 1,
        ]);
        $query = ['source_news' => 'webhooks'];
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, $query);
        $file->shouldNotReceive('error', 'warning', 'debug');
        $notification = $this->coreNotification($file, $posted);

        $notification->handleReceivedNotification($query);

        $this->assertSame([$this->error('unavailable', 'notification.authentication: MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException [500]: ' . self::SECRET), $this->time('failure')], $this->events);
    }

    /** @dataProvider foreignFetchedIdProvider */
    public function testFetchedNotificationThatIsNotTheRequestedOneIsAnInvalidPayload(array $fetched): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $fetched));
        $this->forbidLinkageAndEffects();

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(400, SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD, $result);
        $this->assertSame([$this->rejected('invalid_payload'), $this->time('failure')], $this->events);
    }

    public function foreignFetchedIdProvider(): array
    {
        $fetched = $this->fetchedNotification();
        $missing = $fetched;
        unset($missing['notification_id']);
        return [
            'another Smart Pix id' => [array_merge($fetched, ['notification_id' => 'PPORDSYNTHETIC002'])],
            'same id in another case' => [array_merge($fetched, ['notification_id' => strtolower(self::NOTIFICATION_ID)])],
            'legacy payment id' => [array_merge($fetched, ['notification_id' => 'P-123'])],
            'non-string id' => [array_merge($fetched, ['notification_id' => ['PPORDSYNTHETIC001']])],
            'missing id' => [$missing],
        ];
    }

    /** @dataProvider rejectedEnvelopeProvider */
    public function testFetchedEnvelopeRejectionsAreInvalidPayloadsBeforeLinkage(array $fetched): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $fetched));
        $this->forbidLinkageAndEffects();

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(400, SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD, $result);
        $this->assertSame([$this->rejected('invalid_payload'), $this->time('failure')], $this->events);
    }

    public function rejectedEnvelopeProvider(): array
    {
        $fetched = $this->fetchedNotification();
        $detail = $fetched['payments_details'][0];
        $withDetail = static function (array $changes) use ($fetched, $detail): array {
            return array_merge($fetched, ['payments_details' => [array_merge($detail, $changes)]]);
        };
        $withoutReferences = $detail;
        unset($withoutReferences['references']);
        $withoutOrderId = $detail;
        unset($withoutOrderId['references']['order_id']);
        $withoutTransactionType = $fetched;
        unset($withoutTransactionType['transaction_type']);
        $withoutExternalReference = $fetched;
        unset($withoutExternalReference['external_reference']);
        return [
            'payment transaction type' => [array_merge($fetched, ['transaction_type' => 'payment'])],
            'missing transaction type' => [$withoutTransactionType],
            'missing external reference' => [$withoutExternalReference],
            'numeric external reference' => [array_merge($fetched, ['external_reference' => 321])],
            'empty external reference' => [array_merge($fetched, ['external_reference' => ''])],
            'no payments details' => [array_merge($fetched, ['payments_details' => []])],
            'null payments details' => [array_merge($fetched, ['payments_details' => null])],
            'two payments details' => [array_merge($fetched, ['payments_details' => [$detail, $detail]])],
            'keyed payments detail' => [array_merge($fetched, ['payments_details' => ['first' => $detail]])],
            'scalar payments detail' => [array_merge($fetched, ['payments_details' => [self::LAYER_REFERENCE]])],
            'missing references' => [array_merge($fetched, ['payments_details' => [$withoutReferences]])],
            'scalar references' => [$withDetail(['references' => self::LAYER_REFERENCE])],
            'missing order id' => [array_merge($fetched, ['payments_details' => [$withoutOrderId]])],
            'payment reference as order id' => [$withDetail(['references' => ['order_id' => 'PAYSYNTHETIC001']])],
            'lowercase order id' => [$withDetail(['references' => ['order_id' => strtolower(self::LAYER_REFERENCE)]])],
            'hyphenated order id' => [$withDetail(['references' => ['order_id' => 'ORD-SYNTHETIC001']])],
            'order id with trailing newline' => [$withDetail(['references' => ['order_id' => self::LAYER_REFERENCE . "\n"]])],
            'bare order prefix' => [$withDetail(['references' => ['order_id' => 'ORD']])],
            'non-string order id' => [$withDetail(['references' => ['order_id' => 123]])],
        ];
    }

    /** @dataProvider foreignEnvelopeIdProvider */
    public function testExtractorRejectsAnEnvelopeWhoseOwnIdIsNotASmartPixId($notificationId): void
    {
        $fetched = array_merge($this->fetchedNotification(), ['notification_id' => $notificationId]);

        $this->assertNull((new SmartPixCoreNotificationIdentifiers())->extract($fetched));
    }

    public static function foreignEnvelopeIdProvider(): array
    {
        return [
            'legacy payment id' => ['P-123'],
            'lowercase prefix' => ['ppordsynthetic001'],
            'hyphenated' => ['PPORD-SYNTHETIC001'],
            'trailing newline' => [self::NOTIFICATION_ID . "\n"],
            'bare prefix' => ['PPORD'],
            'non-string' => [123],
            'null' => [null],
        ];
    }

    public function testExtractorTakesOnlyTheReferencesFromTheFetchedEnvelope(): void
    {
        $this->assertSame(
            ['attempt_reference' => 'WC-321', 'order_reference' => self::ORDER_REFERENCE, 'provider_order_reference' => self::PROVIDER_ORDER_REFERENCE],
            (new SmartPixCoreNotificationIdentifiers())->extract($this->fetchedNotification())
        );
    }

    /** Masked real envelope: the test environment prefixes the layer id with ORDBTA. */
    public function testExtractorLinksByTheCoreOrderIdWhateverTheLayerReferencePrefix(): void
    {
        $fetched = $this->fetchedNotification();
        $fetched['payments_details'][0]['references']['order_id'] = 'ORDBTASYNTHETIC001';

        $this->assertSame(
            [
                'attempt_reference' => 'WC-321',
                'order_reference' => self::NOTIFICATION_ID,
                'provider_order_reference' => 'ORDBTASYNTHETIC001',
            ],
            (new SmartPixCoreNotificationIdentifiers())->extract($fetched)
        );
    }

    public function testNonSmartPixIdGivenToTheHandlerIsAnInvalidPayloadWithoutFetching(): void
    {
        $this->http->shouldNotReceive('request');
        $this->forbidLinkageAndEffects();

        $result = $this->handler()->handle('P-123', $this->context());

        $this->assertFixedResponse(400, SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD, $result);
        $this->assertSame([$this->rejected('invalid_payload'), $this->time('failure')], $this->events);
    }

    /** @dataProvider unlinkedProvider */
    public function testFetchedNotificationForAnOrderWeDoNotOwnIsAcknowledgedAsIgnored(array $changes, bool $loadsOrder): void
    {
        $this->http->shouldReceive('request')->once()
            ->andReturn(new HttpResponse(200, [], '', array_merge($this->fetchedNotification(), $changes)));
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => 'WC-']);
        if ($loadsOrder) {
            $order = $this->orderSnapshot('pending', false, 1);
            $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('ORDSYNTHETIC999');
            $this->forbidEffects($order);
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        } else {
            WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        }
        $this->orderClient->shouldNotReceive('query');
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(200, SmartPixNotificationReceiver::MESSAGE_IGNORED, $result);
        $this->assertSame([$this->rejected('invalid_linkage'), $this->time('failure')], $this->events);
    }

    public static function unlinkedProvider(): array
    {
        return [
            'foreign store prefix' => [['external_reference' => 'XX-321'], false],
            'zero padded order id' => [['external_reference' => 'WC-0321'], false],
            'persisted order reference differs' => [[], true],
        ];
    }

    /** @dataProvider unappliedQueryProvider */
    public function testAuthoritativeAnswersThatCannotBeAppliedHaveNoEffect(array $answer, bool $mismatch): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->orderClient->shouldReceive('query')->once()->andReturn($answer);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        // An answer about another Order is a contract conflict; the others are acknowledged.
        if ($mismatch) {
            $this->assertFixedResponse(409, SmartPixNotificationReceiver::MESSAGE_CONFLICT, $result);
            $this->assertSame([$this->error('invalid_contract'), $this->time('failure')], $this->events);
            return;
        }
        $this->assertFixedResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    public function unappliedQueryProvider(): array
    {
        $approved = $this->approvedQuery();
        $processing = array_merge($approved, ['state' => 'processing', 'payments' => []]);
        $failure = [
            'outcome' => 'failure', 'state' => 'error', 'order_reference' => null,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'validation', 'code' => 'SMART_PIX_VALIDATION', 'retryable' => false],
        ];
        return [
            'non-terminal answer' => [$processing, false],
            'returned non-retryable query failure' => [$failure, false],
            'terminal answer for another order' => [array_merge($approved, ['order_reference' => 'ORDSYNTHETIC999']), true],
            'non-terminal answer for another order' => [array_merge($processing, ['order_reference' => 'ORDSYNTHETIC999']), true],
            'terminal answer without reference' => [array_merge($approved, ['order_reference' => null]), true],
        ];
    }

    public function testCoreNotificationWithRetryableQueryFailureSendsTheFixedServerErrorSoCoreRedelivers(): void
    {
        $posted = (string) json_encode(['notification_id' => self::NOTIFICATION_ID]);
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->orderClient->shouldReceive('query')->once()->andReturn([
            'outcome' => 'failure', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'timeout', 'code' => 'SMART_PIX_TIMEOUT', 'retryable' => true],
        ]);
        $this->ownership->shouldNotReceive('acquire');
        WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500],
            'times' => 1,
        ]);
        $query = ['source_news' => 'webhooks'];
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, $query);
        $file->shouldNotReceive('error', 'warning', 'debug');
        $notification = $this->coreNotification($file, $posted);

        $notification->handleReceivedNotification($query);

        // The client owns the error metric for its returned failure; the receiver adds none.
        $this->assertSame([$this->time('failure')], $this->events);
    }

    public function testQueryThrowIsASanitizedServerErrorWithoutEffects(): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->orderClient->shouldReceive('query')->once()->andThrow(new RuntimeException('Provider read failed; token=' . self::SECRET));
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        // Reported once by the query itself before it rethrows.
        $expected = $this->error('internal_error');
        $expected[] = 'RuntimeException [0]: Provider read failed; token=[REDACTED]';
        $this->assertSame([$expected, $this->time('failure')], $this->events);
    }

    /** @dataProvider ownershipDenialProvider */
    public function testOwnershipDenialHasNoEffectAndAnswersByOutcome(string $claim, int $status, string $message, string $category): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->orderClient->shouldReceive('query')->once()->andReturn($this->approvedQuery());
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        // A conflict cannot change on redelivery; busy and uncertain effects ask the sender to redeliver.
        $this->assertFixedResponse($status, $message, $result);
        if ($claim !== 'busy') {
            $this->assertApplicationDiagnostic($this->events, true, $claim === 'conflict' ? 'conflict' : 'persistence');
        }
        $this->assertSame([
            $this->effect($claim, 'ownership', 'unknown'),
            $this->rejected($category),
            $this->time('failure'),
        ], $this->events);
    }

    public static function ownershipDenialProvider(): array
    {
        return [
            'busy' => ['busy', 500, SmartPixNotificationReceiver::MESSAGE_FAILED, 'effect_unresolved'],
            'conflict' => ['conflict', 409, SmartPixNotificationReceiver::MESSAGE_CONFLICT, 'conflict'],
            'uncertain' => ['uncertain', 500, SmartPixNotificationReceiver::MESSAGE_FAILED, 'effect_unresolved'],
        ];
    }

    public function testUnavailableOwnershipClaimAnswersAServerErrorSoCoreRedelivers(): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->orderClient->shouldReceive('query')->once()->andReturn($this->approvedQuery());
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'unavailable', 'owner' => null]);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        // The applicator already reports the effect; the receiver adds no error metric.
        $this->assertApplicationDiagnostic($this->events);
        $this->assertSame([
            $this->effect('uncertain', 'ownership', 'unknown'),
            $this->time('failure'),
        ], $this->events);
    }

    public function testReplayedNotificationAfterCompletionFetchesAgainWithoutASecondEffect(): void
    {
        $this->http->shouldReceive('request')->twice()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $replayed = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->forbidEffects($replayed);
        $this->expectOrders(2, [$this->linkedOrder(), $pending, $persisted, $this->linkedOrder('processing', true), $replayed]);
        $this->orderClient->shouldReceive('query')->twice()->andReturn($this->approvedQuery());
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->twice()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner], ['outcome' => 'completed', 'owner' => null]);
        $this->ownership->shouldReceive('start')->once()->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $handler = $this->handler();

        $first = $handler->handle(self::NOTIFICATION_ID, $this->context());
        $second = $handler->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $first);
        $this->assertFixedResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $second);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
            $this->effect('no_change', 'state_decision', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    public function testAuthoritativeRejectionMarksTheOrderFailedOnce(): void
    {
        $this->http->shouldReceive('request')->once()->andReturn(new HttpResponse(200, [], '', $this->fetchedNotification()));
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        $pending->shouldNotReceive('payment_complete');
        $persisted = $this->orderSnapshot('failed', false, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$this->linkedOrder(), $pending, $persisted]);
        $this->orderClient->shouldReceive('query')->once()->andReturn($this->rejectedQuery());
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'mark_failed')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('start')->once()->with(321, self::ATTEMPT_KEY, 'mark_failed', $owner)->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'mark_failed', $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');

        $result = $this->handler()->handle(self::NOTIFICATION_ID, $this->context());

        $this->assertFixedResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'failed'),
            $this->time('success'),
        ], $this->events);
    }

    private function coreNotification(File $file, string $posted)
    {
        $logs = Mockery::mock(Logs::class);
        $logs->file = $file;
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getIntegratorId')->andReturn('');
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus->shouldNotReceive('processStatus', 'isOwnedBySmartPixOrders');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            $orderStatus,
            $this->seller,
            $store,
            $legacyClient,
            $this->handler(),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $notification->shouldReceive('getInput')->andReturn($posted);
        $notification->shouldNotReceive('handleSuccessfulRequest');

        return $notification;
    }

    private function handler(): SmartPixCoreNotificationHandler
    {
        $telemetry = new SmartPixTelemetry($this->recordingSink(), $this->clock());
        $receiver = new SmartPixNotificationReceiver(
            new SmartPixNotificationBody(),
            new DenyAllSmartPixNotificationAuthenticator(),
            new SmartPixCoreNotificationIdentifiers(),
            new SmartPixNotificationLinkage($this->metadata, $this->seller),
            new SmartPixOrderQuery($this->orderClient, $this->metadata, $this->seller, $telemetry, 'notification'),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership, $telemetry),
            $telemetry
        );

        return new SmartPixCoreNotificationHandler(new NotificationsClient(new CoreApiClient($this->http)), $receiver);
    }

    /** Core P&P envelope as fetched; its status fields are deliberately not the applied state. */
    private function fetchedNotification(): array
    {
        return [
            'notification_id' => self::NOTIFICATION_ID,
            'site_id' => 'MLB',
            'status' => 'failed',
            'status_detail' => 'high_risk',
            'transaction_type' => 'pp_order',
            'external_reference' => 'WC-321',
            'transaction_amount' => 10,
            'total_paid' => 0,
            'payments_details' => [[
                'id' => 'PPPAYSYNTHETIC001',
                'payment_method_id' => 'smart_transfer',
                'payment_type_id' => 'smart_transfer',
                'status' => 'failed',
                'refunds' => [],
                'references' => [
                    'order_id' => self::PROVIDER_ORDER_REFERENCE,
                    'order_transaction_id' => 'PAYSYNTHETIC001',
                    'source' => 'mp_order',
                ],
            ]],
            'refunds_notifying' => null,
            'force_renotify' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function approvedQuery(): array
    {
        return [
            'outcome' => 'success',
            'state' => 'terminal_success',
            'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null,
            'authorization' => null,
            'payments' => [[
                'reference' => 'synthetic_terminal_payment',
                'state' => 'approved',
                'amount' => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function rejectedQuery(): array
    {
        return [
            'outcome' => 'failure',
            'state' => 'terminal_failure',
            'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null,
            'authorization' => null,
            'payments' => [[
                'reference' => 'synthetic_terminal_payment',
                'state' => 'rejected',
                'amount' => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error' => ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK', 'retryable' => false],
        ];
    }

    private function context(): CoreRequestContext
    {
        return new CoreRequestContext(self::ACCESS_TOKEN, MP_PLATFORM_ID, MP_PRODUCT_ID_DESKTOP, '');
    }

    private function forbidLinkageAndEffects(): void
    {
        WP_Mock::userFunction('get_option', ['times' => 0]);
        WP_Mock::userFunction('wc_get_order', ['times' => 0]);
        $this->metadata->shouldNotReceive('getIdempotencyKey', 'getPpOrderId', 'getPaymentContract');
        $this->orderClient->shouldNotReceive('query');
        $this->ownership->shouldNotReceive('acquire');
    }

    /** @param list<mixed> $orders */
    private function expectOrders(int $requests, array $orders): void
    {
        // Linkage and query each read the store identificator once per request.
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2 * $requests, 'return' => 'WC-']);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => count($orders), 'return_in_order' => $orders]);
    }

    /** @param array<string, mixed> $result */
    private function assertFixedResponse(int $status, string $message, array $result): void
    {
        $this->assertSame(['status' => $status, 'message' => $message], $result);
        $encoded = (string) json_encode($result);
        $forbidden = [self::SECRET, self::NOTIFICATION_ID, self::ORDER_REFERENCE, self::LAYER_REFERENCE, self::ATTEMPT_KEY, self::ACCESS_TOKEN, 'WC-321'];
        foreach ($forbidden as $value) {
            $this->assertStringNotContainsString($value, $encoded);
        }
    }

    /** @return array<int, mixed> */
    private function rejected(string $category): array
    {
        return ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, $category, self::NOTIFICATION];
    }

    /** @return array<int, mixed> */
    private function error(string $category, string $diagnostic = ''): array
    {
        $event = ['count', SmartPixMetricSchema::ERROR, 'true', self::NOTIFICATION + ['category' => $category]];
        if ($category === 'invalid_contract') {
            $event[] = 'notification.order_read: authoritative order_reference differs from the linked or candidate order';
        } elseif ($diagnostic !== '') {
            $event[] = $diagnostic;
        }
        return $event;
    }

    /** @return array<int, mixed> */
    private function time(string $outcome): array
    {
        return ['distribution', SmartPixMetricSchema::TIME, 0.25, self::NOTIFICATION + ['outcome' => $outcome]];
    }

    /** @return array<int, mixed> */
    private function effect(string $outcome, string $reasonClass, string $status): array
    {
        return ['count', SmartPixMetricSchema::EFFECT, $outcome,
            self::APPLY + ['reason_class' => $reasonClass, 'native_status' => $status]];
    }

    private function recordingSink(): SmartPixMetricSink
    {
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->andReturnUsing(function (...$arguments): void {
            $this->events[] = array_merge(['count'], $arguments);
        });
        $sink->shouldReceive('distribution')->andReturnUsing(function (...$arguments): void {
            $this->events[] = array_merge(['distribution'], $arguments);
        });
        return $sink;
    }

    private function clock(): callable
    {
        $tick = 0;
        return static function () use (&$tick): float {
            return 10.0 + 0.25 * $tick++;
        };
    }

    /** The order loaded by linkage and re-read by the query without the edit context. */
    private function linkedOrder(string $status = 'pending', bool $paid = false)
    {
        $order = $this->orderSnapshot($status, $paid, 2);
        $order->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($order);
        return $order;
    }

    private function orderSnapshot(string $status, bool $paid, int $metaReads)
    {
        $order = Mockery::mock(WC_Order::class);
        $store = Mockery::mock();
        $store->shouldReceive('read')->with($order)->once();
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_data_store')->once()->andReturn($store);
        $order->shouldReceive('read_meta_data')->with(true)->times($metaReads);
        $order->shouldReceive('get_payment_method')->with('edit')->andReturn(PixGateway::ID);
        $order->shouldReceive('get_currency')->with('edit')->andReturn('BRL');
        $order->shouldReceive('get_total')->with('edit')->andReturn('125.50');
        $order->shouldReceive('get_status')->with('edit')->andReturn($status);
        $order->shouldReceive('is_paid')->andReturn($paid);
        $order->shouldReceive('get_date_paid')->with('edit')->andReturn($paid ? new \stdClass() : null);
        $order->shouldReceive('get_refunds')->andReturn([]);
        $order->shouldReceive('get_transaction_id')->with('edit')->andReturn('');
        $order->shouldNotReceive('save');
        $this->metadata->shouldReceive('getPaymentContract')->with($order)
            ->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1)->byDefault();
        $this->metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn(self::ATTEMPT_KEY)->byDefault();
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::ORDER_REFERENCE)->byDefault();
        $this->metadata->shouldReceive('getMpOrderId')->with($order)->andReturn(self::PROVIDER_ORDER_REFERENCE)->byDefault();
        return $order;
    }

    private function forbidEffects($order): void
    {
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
    }
}

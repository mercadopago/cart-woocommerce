<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
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
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

/**
 * Smart Pix notification handling driven by the synthetic fixtures in fixtures/notifications:
 * posted Core bodies, fetched Core P&P envelopes and normalized Orders query answers.
 *
 * Covers authenticity (fetch-back), linkage, the body limit of the raw receiver entry,
 * replay of the same notification and out-of-order delivery against local order state.
 */
class SmartPixNotificationHandlerTest extends TestCase
{
    use AssertsApplicationDiagnostics;

    private const FIXTURES = __DIR__ . '/fixtures/notifications';

    private const NOTIFICATION_ID = 'PPORDSYNTHETICNTF001';

    private const ORDER_REFERENCE = self::NOTIFICATION_ID;

    private const PROVIDER_ORDER_REFERENCE = 'ORDSYNTHETICNTF001';

    private const ATTEMPT_REFERENCE = 'WC-321';

    private const ORDER_ID = 321;

    private const ATTEMPT_KEY = 'SYNTHETIC_ATTEMPT_KEY';

    private const ACCESS_TOKEN = 'SYNTHETIC_ACCESS_TOKEN';

    private const QUERY = ['wc-api' => 'WC_WooMercadoPago_Pix_Gateway', 'source_news' => 'webhooks'];

    private const APPLY = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    private const NOTIFICATION = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    private const TIME_SUCCESS = ['distribution', SmartPixMetricSchema::TIME, 0.25, self::NOTIFICATION + ['outcome' => 'success']];

    private const TIME_FAILURE = ['distribution', SmartPixMetricSchema::TIME, 0.25, self::NOTIFICATION + ['outcome' => 'failure']];

    /** @var list<array<int, mixed>> */
    private array $events = [];

    private $metadata;

    private $seller;

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
        $this->ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $this->http = Mockery::mock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        // PHPUnit keeps every TestCase instance until the run ends; drop the doubles so their
        // expectation graphs are not retained by the remaining suite.
        $this->events = [];
        $this->metadata = null;
        $this->seller = null;
        $this->ownership = null;
        $this->http = null;
    }

    /**
     * Only the posted notification id is read; the decision comes from the fetched envelope
     * references and the fresh Orders query, never from posted or fetched status fields.
     *
     * @dataProvider completedPaymentProvider
     */
    public function testPostedSmartPixNotificationIsFetchedQueriedAndCompletesThePaymentOnce(string $posted, string $fetched): void
    {
        $this->expectFetch($fetched);
        $orders = $this->orders('notification-query-terminal-success');

        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        // Linkage and query each read the store identificator once.
        $this->expectStorePrefixReads(2);
        $this->expectOrderLoads([$this->linkedOrder('pending', false), $pending, $persisted]);
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('start')->once()
            ->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()
            ->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $this->expectResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED);

        $this->coreNotification($this->receivedFile(), self::rawFixture($posted), $orders)->handleReceivedNotification(self::QUERY);

        // One fresh Orders read for the references taken from the fetched envelope only.
        $this->assertSame([$this->expectedQueryCall()], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::EFFECT, 'applied',
                self::APPLY + ['reason_class' => 'native_verification', 'native_status' => 'processing']],
            self::TIME_SUCCESS,
        ], $this->events);
    }

    public static function completedPaymentProvider(): array
    {
        $approved = 'fetched/pp-order-approved-processed.json';
        return [
            'core object with the notification id' => ['posted/core-posted-notification-id.json', $approved],
            'raw string with the notification id' => ['posted/core-posted-raw-string.json', $approved],
            // Forged status, amounts and references point elsewhere; only the posted id is used.
            'forged posted financial fields' => ['posted/core-posted-forged-financial-fields.json', $approved],
            // The fetched envelope status is not a decision input; the Orders query is.
            'fetched envelope failed but Orders query approved' => [
                'posted/core-posted-notification-id.json',
                'fetched/pp-order-failed-status-detail.json',
            ],
        ];
    }

    /** @dataProvider fetchFailureProvider */
    public function testFetchFailureAnswersFixedServerErrorWithoutLinkageQueryOrEffect(?int $status): void
    {
        $request = $this->http->shouldReceive('request')->once()
            ->with('GET', '/plugins-platforms/v1/notification/order/' . self::NOTIFICATION_ID, Mockery::type('array'), null);
        if ($status === null) {
            $request->andThrow(new TransportException('http_request_failed'));
        } else {
            $request->andReturn(new HttpResponse($status, [], '', $this->fixture('fetched/core-error-response.json')));
        }
        $orders = $this->orders('notification-query-terminal-success');
        $this->expectStorePrefixReads(0);
        $this->expectOrderLoads([]);
        $this->forbidApply();
        // Fixed message: the Core error body or transport text is never echoed.
        $this->expectResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::ERROR, 'true', self::NOTIFICATION + ['category' => 'unavailable'],
                $status === null
                    ? 'notification.authentication: MercadoPago\Woocommerce\Api\Http\TransportException [http_request_failed]: Unable to connect to Mercado Pago API.'
                    : 'notification.authentication: MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException [' . $status . ']: Synthetic Core failure detail that must never be echoed.'],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public static function fetchFailureProvider(): array
    {
        return [
            'unauthorized' => [401],
            'not found' => [404],
            'internal server error' => [500],
            'service unavailable' => [503],
            'transport failure' => [null],
        ];
    }

    /** @dataProvider invalidFetchedEnvelopeProvider */
    public function testInvalidFetchedEnvelopeIsRejectedWithoutLinkageOrQuery(string $fetched): void
    {
        $this->expectFetch($fetched);
        $orders = $this->orders('notification-query-terminal-success');
        $this->expectStorePrefixReads(0);
        $this->expectOrderLoads([]);
        $this->forbidApply();
        $this->expectResponse(400, SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, 'invalid_payload', self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public static function invalidFetchedEnvelopeProvider(): array
    {
        return [
            'fetched id differs from the requested id' => ['fetched/pp-order-mismatched-id.json'],
            'fetched transaction type is not pp_order' => ['fetched/pp-order-wrong-transaction-type.json'],
        ];
    }

    /** @dataProvider legacyNotificationIdProvider */
    public function testNonSmartPixPostedIdKeepsTheLegacyRejectionWithoutSmartPixFetch(string $posted): void
    {
        $this->http->shouldNotReceive('request');
        $orders = $this->orders('notification-query-terminal-success');
        $this->forbidApply();
        $this->expectResponse(400, 'Invalid notification id');
        $file = $this->receivedFile();
        $file->shouldReceive('error')->once()->with('Invalid notification id', CoreNotification::class, self::QUERY);

        $this->coreNotification($file, self::rawFixture($posted), $orders)->handleReceivedNotification(self::QUERY);

        $this->assertSame([], $orders->getCalls());
        $this->assertSame([], $this->events);
    }

    public static function legacyNotificationIdProvider(): array
    {
        return [
            'hyphenated PPORD id' => ['posted/core-posted-hyphenated-id.json'],
            'unknown id prefix' => ['posted/core-posted-unknown-id.json'],
        ];
    }

    public function testExternalReferenceOfAnotherStoreIsAcknowledgedAsInvalidLinkageWithoutQuery(): void
    {
        $this->expectFetch('fetched/pp-order-other-store-reference.json');
        $orders = $this->orders('notification-query-terminal-success');
        $this->expectStorePrefixReads(1);
        $this->expectOrderLoads([]);
        $this->forbidApply();
        $this->expectResponse(200, SmartPixNotificationReceiver::MESSAGE_IGNORED);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, 'invalid_linkage', self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public function testPersistedOrdersReferenceDifferentFromTheFetchedOneIsAcknowledgedWithoutQuery(): void
    {
        $this->expectFetch('fetched/pp-order-approved-processed.json');
        $orders = $this->orders('notification-query-terminal-success');
        $order = $this->orderSnapshot('pending', false, 1);
        $this->forbidEffects($order);
        $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn('ORDSYNTHETICOTHER001');
        $this->expectStorePrefixReads(1);
        $this->expectOrderLoads([$order]);
        $this->forbidApply();
        $this->expectResponse(200, SmartPixNotificationReceiver::MESSAGE_IGNORED);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, 'invalid_linkage', self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    /**
     * The body is generated inside the test (null bytes means a small JSON object) so oversized
     * strings are not retained by the data provider for the rest of the suite.
     *
     * @dataProvider oversizedOrMalformedBodyProvider
     */
    public function testRawBodyLimitIsEnforcedBeforeAuthentication(
        ?string $contentLength,
        ?int $bodyBytes,
        int $expectedOpens,
        int $status,
        string $message,
        string $category
    ): void {
        $opens = 0;
        $authenticator = $this->spyAuthenticator();
        $identifiers = $this->spyIdentifiers();
        $this->forbidApply();
        $body = $bodyBytes === null ? '{}' : str_repeat('a', $bodyBytes);
        $receiver = $this->receiver($this->bodyReader($body, $opens), $authenticator, $identifiers);
        unset($body);

        $result = $receiver->handle(['content-type' => 'application/json'], $contentLength);

        $this->assertSame(['status' => $status, 'message' => $message], $result);
        $this->assertSame($expectedOpens, $opens);
        $this->assertSame(0, $authenticator->calls);
        $this->assertSame(0, $identifiers->calls);
        $this->assertSame([
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, $category, self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public static function oversizedOrMalformedBodyProvider(): array
    {
        $oversized = SmartPixNotificationBody::DEFAULT_MAX_BYTES + 1;
        $tooLarge = [413, SmartPixNotificationReceiver::MESSAGE_TOO_LARGE, SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE];
        return [
            'declared length above the limit' => array_merge([(string) $oversized, $oversized, 0], $tooLarge),
            'real body above the limit without a declared length' => array_merge([null, $oversized, 1], $tooLarge),
            'real body above an understated declared length' => array_merge(['10', $oversized, 1], $tooLarge),
            'malformed declared length' => [
                '12abc',
                null,
                0,
                400,
                SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD,
                SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD,
            ],
        ];
    }

    /**
     * Either a posted fixture or a generated non-JSON body of the given size.
     *
     * @dataProvider bodyWithinLimitProvider
     */
    public function testBodyWithinLimitIsRejectedByTheDenyAllAuthenticatorWithoutDecoding(?string $fixture, int $bodyBytes): void
    {
        $opens = 0;
        $identifiers = $this->spyIdentifiers();
        $this->forbidApply();
        $body = $fixture !== null ? self::rawFixture($fixture) : str_repeat('a', $bodyBytes);
        $length = (string) strlen($body);
        $receiver = $this->receiver($this->bodyReader($body, $opens), new DenyAllSmartPixNotificationAuthenticator(), $identifiers);
        unset($body);

        $result = $receiver->handle(['content-type' => 'application/json'], $length);

        // A non-JSON body would answer 400 if decoded; 401 proves authentication ran first.
        $this->assertSame(['status' => 401, 'message' => SmartPixNotificationReceiver::MESSAGE_UNAUTHENTICATED], $result);
        $this->assertSame(1, $opens);
        $this->assertSame(0, $identifiers->calls);
        $this->assertSame([
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, 'invalid_auth', self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public static function bodyWithinLimitProvider(): array
    {
        return [
            'forged financial body' => ['posted/core-posted-forged-financial-fields.json', 0],
            'non-JSON body exactly at the limit' => [null, SmartPixNotificationBody::DEFAULT_MAX_BYTES],
        ];
    }

    public function testReplayedNotificationIsFetchedAgainButAppliesTheEffectOnce(): void
    {
        $this->expectFetch('fetched/pp-order-approved-processed.json', 2);
        $orders = $this->orders('notification-query-terminal-success');

        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $replayed = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($replayed);
        $this->expectStorePrefixReads(4);
        $this->expectOrderLoads([
            $this->linkedOrder('pending', false),
            $pending,
            $persisted,
            $this->linkedOrder('processing', true),
            $replayed,
        ]);
        $owner = str_repeat('b', 64);
        $this->ownership->shouldReceive('acquire')->twice()->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner], ['outcome' => 'completed', 'owner' => null]);
        $this->ownership->shouldReceive('start')->once()
            ->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()
            ->with(self::ORDER_ID, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $this->expectResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, 2);
        $notification = $this->coreNotification(
            $this->receivedFile(2),
            self::rawFixture('posted/core-posted-notification-id.json'),
            $orders
        );

        $notification->handleReceivedNotification(self::QUERY);
        $notification->handleReceivedNotification(self::QUERY);

        $this->assertSame([$this->expectedQueryCall(), $this->expectedQueryCall()], $orders->getCalls());
        $this->assertSame([
            ['count', SmartPixMetricSchema::EFFECT, 'applied',
                self::APPLY + ['reason_class' => 'native_verification', 'native_status' => 'processing']],
            self::TIME_SUCCESS,
            ['count', SmartPixMetricSchema::EFFECT, 'no_change',
                self::APPLY + ['reason_class' => 'state_decision', 'native_status' => 'processing']],
            self::TIME_SUCCESS,
        ], $this->events);
    }

    /** @dataProvider processingDeliveryProvider */
    public function testProcessingFetchAndQueryAreAcknowledgedWithoutEffect(string $posted, string $status, bool $paid): void
    {
        $this->expectFetch('fetched/pp-order-processing.json');
        $orders = $this->orders('notification-query-processing');
        $this->expectStorePrefixReads(2);
        $this->expectOrderLoads([$this->linkedOrder($status, $paid)]);
        $this->forbidApply();
        $this->expectResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED);

        $this->coreNotification($this->receivedFile(), self::rawFixture($posted), $orders)->handleReceivedNotification(self::QUERY);

        $this->assertSame([$this->expectedQueryCall()], $orders->getCalls());
        $this->assertSame([self::TIME_SUCCESS], $this->events);
    }

    public static function processingDeliveryProvider(): array
    {
        return [
            'pending order with a forged processed posted body' => ['posted/core-posted-forged-financial-fields.json', 'pending', false],
            'late delivery on an already paid order' => ['posted/core-posted-notification-id.json', 'processing', true],
        ];
    }

    public function testTerminalFailureAfterApprovalIsAConflictWithoutStatusUpdate(): void
    {
        $this->expectFetch('fetched/pp-order-failed-status-detail.json');
        $orders = $this->orders('notification-query-terminal-failure');
        $paid = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($paid);
        $this->expectStorePrefixReads(2);
        $this->expectOrderLoads([$this->linkedOrder('processing', true), $paid]);
        $owner = str_repeat('c', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(self::ORDER_ID, self::ATTEMPT_KEY, 'mark_failed')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('releaseReserved')->once()
            ->with(self::ORDER_ID, self::ATTEMPT_KEY, 'mark_failed', $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain');
        $this->expectResponse(409, SmartPixNotificationReceiver::MESSAGE_CONFLICT);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([$this->expectedQueryCall()], $orders->getCalls());
        $this->assertApplicationDiagnostic($this->events, true, 'conflict');
        $this->assertSame([
            ['count', SmartPixMetricSchema::EFFECT, 'conflict',
                self::APPLY + ['reason_class' => 'state_decision', 'native_status' => 'processing']],
            ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, 'conflict', self::NOTIFICATION],
            self::TIME_FAILURE,
        ], $this->events);
    }

    public function testRetryableOrdersQueryFailureAnswersServerErrorSoTheSenderRedelivers(): void
    {
        $this->expectFetch('fetched/pp-order-approved-processed.json');
        $orders = $this->orders('notification-query-retryable-failure');
        $this->expectStorePrefixReads(2);
        $this->expectOrderLoads([$this->linkedOrder('pending', false)]);
        $this->forbidApply();
        $this->expectResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED);

        $this->coreNotification($this->receivedFile(), self::rawFixture('posted/core-posted-notification-id.json'), $orders)
            ->handleReceivedNotification(self::QUERY);

        $this->assertSame([$this->expectedQueryCall()], $orders->getCalls());
        $this->assertSame([self::TIME_FAILURE], $this->events);
    }

    private static function rawFixture(string $name): string
    {
        $raw = file_get_contents(self::FIXTURES . '/' . $name);
        if (!is_string($raw)) {
            throw new \RuntimeException('The Smart Pix notification fixture could not be read.');
        }
        return $raw;
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $decoded = json_decode(self::rawFixture($name), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    private function orders(string $scenario): MockSmartPixOrderClient
    {
        return new MockSmartPixOrderClient(self::FIXTURES, $scenario);
    }

    /** @return array{operation: string, request: array<string, mixed>} */
    private function expectedQueryCall(): array
    {
        return [
            'operation' => 'query',
            'request' => [
                'surface' => 'server',
                'country' => 'MLB',
                'payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
                'attempt_reference' => self::ATTEMPT_REFERENCE,
                'order_reference' => self::ORDER_REFERENCE,
                'amount' => null,
                'refund_amount' => null,
            ],
        ];
    }

    private function expectFetch(string $fetched, int $times = 1): void
    {
        $this->http->shouldReceive('request')->times($times)->with('GET', '/plugins-platforms/v1/notification/order/' . self::NOTIFICATION_ID, [
            'Authorization' => 'Bearer ' . self::ACCESS_TOKEN,
            'x-platform-id' => MP_PLATFORM_ID,
            'x-product-id' => MP_PRODUCT_ID_DESKTOP,
        ], null)->andReturn(new HttpResponse(200, [], '', $this->fixture($fetched)));
    }

    private function expectStorePrefixReads(int $times): void
    {
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => $times, 'return' => 'WC-']);
    }

    /** @param list<mixed> $orders */
    private function expectOrderLoads(array $orders): void
    {
        $definition = ['args' => [self::ORDER_ID], 'times' => count($orders)];
        if ($orders !== []) {
            $definition['return_in_order'] = $orders;
        }
        WP_Mock::userFunction('wc_get_order', $definition);
    }

    private function expectResponse(int $status, string $message, int $times = 1): void
    {
        WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => $status, 'message' => $message], $status],
            'times' => $times,
        ]);
    }

    private function forbidApply(): void
    {
        $this->ownership->shouldNotReceive('acquire', 'start', 'complete', 'markUncertain', 'releaseReserved');
    }

    private function receivedFile(int $times = 1)
    {
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->times($times)->with('Received data content', AbstractNotification::class, self::QUERY);
        $file->shouldNotReceive('warning', 'debug');
        $file->shouldNotReceive('error')->byDefault();
        return $file;
    }

    private function bodyReader(string $body, int &$opens): SmartPixNotificationBody
    {
        return new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, static function () use ($body, &$opens) {
            $opens++;
            $stream = fopen('php://memory', 'w+b');
            fwrite($stream, $body);
            rewind($stream);
            return $stream;
        });
    }

    /**
     * Counting doubles instead of Mockery mocks: these interfaces are mocked nowhere else, so a
     * generated Mockery class would stay loaded for the rest of the suite.
     */
    private function spyAuthenticator(): SmartPixNotificationAuthenticator
    {
        return new class () implements SmartPixNotificationAuthenticator {
            public int $calls = 0;

            public function authenticate(array $headers, string $rawBody): bool
            {
                $this->calls++;
                return false;
            }
        };
    }

    private function spyIdentifiers(): SmartPixNotificationIdentifiers
    {
        return new class () implements SmartPixNotificationIdentifiers {
            public int $calls = 0;

            public function extract(array $payload): ?array
            {
                $this->calls++;
                return null;
            }
        };
    }

    private function coreNotification(File $file, string $posted, MockSmartPixOrderClient $orders)
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
            $this->handler($orders),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $notification->shouldReceive('getInput')->andReturn($posted);
        $notification->shouldNotReceive('handleSuccessfulRequest');

        return $notification;
    }

    private function handler(MockSmartPixOrderClient $orders): SmartPixCoreNotificationHandler
    {
        $receiver = $this->receiver(
            new SmartPixNotificationBody(),
            new DenyAllSmartPixNotificationAuthenticator(),
            new SmartPixCoreNotificationIdentifiers(),
            $orders
        );

        return new SmartPixCoreNotificationHandler(new NotificationsClient(new CoreApiClient($this->http)), $receiver);
    }

    private function receiver(
        SmartPixNotificationBody $body,
        SmartPixNotificationAuthenticator $authenticator,
        SmartPixNotificationIdentifiers $identifiers,
        ?MockSmartPixOrderClient $orders = null
    ): SmartPixNotificationReceiver {
        $telemetry = new SmartPixTelemetry($this->recordingSink(), $this->clock());
        return new SmartPixNotificationReceiver(
            $body,
            $authenticator,
            $identifiers,
            new SmartPixNotificationLinkage($this->metadata, $this->seller),
            new SmartPixOrderQuery(
                $orders ?? $this->orders('notification-query-terminal-success'),
                $this->metadata,
                $this->seller,
                $telemetry,
                'notification'
            ),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership, $telemetry),
            $telemetry
        );
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
    private function linkedOrder(string $status, bool $paid)
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
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);
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

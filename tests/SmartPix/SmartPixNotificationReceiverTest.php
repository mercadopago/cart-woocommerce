<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixNotificationIdentifiers;
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
 * The receiver collaborators are final classes, which Mockery cannot mock. As in the
 * sibling tests, real instances are wired with mocked interfaces and WooCommerce doubles,
 * so the whole server chain (body, linkage, query, applicator, telemetry) is exercised.
 *
 * Telemetry swallows sink failures, so every sink event is recorded and asserted exactly.
 */
class SmartPixNotificationReceiverTest extends TestCase
{
    use AssertsApplicationDiagnostics;

    /** @dataProvider unsupportedAuthoritativeStateProvider */
    public function testUnsupportedAuthoritativeStatesRequestRedeliveryInsteadOfClaimingProcessing(string $category): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $failure = $this->retryableFailure($category, self::ORDER_REFERENCE);
        $failure['error']['retryable'] = false;
        $this->expectQuery($failure);
        $this->ownership->shouldNotReceive('acquire');
        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);
        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    public static function unsupportedAuthoritativeStateProvider(): array
    {
        return [['unknown'], ['invalid_response']];
    }

    /** @dataProvider remoteReadFailureProvider */
    public function testOrdersRefusingTheReadOfALinkedOrderRequestsRedeliveryAndReconciliation(string $category): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $failure = $this->retryableFailure($category, self::ORDER_REFERENCE);
        $failure['state'] = 'error';
        $failure['error']['retryable'] = false;
        $this->expectQuery($failure);
        $this->ownership->shouldNotReceive('acquire');
        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers('echo'),
            self::BODY,
            null,
            null,
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertSame([321], $scheduled);
    }

    public static function remoteReadFailureProvider(): array
    {
        return [['authentication'], ['validation'], ['conflict']];
    }

    public function testPartialRefundWithoutItsValidatedContextRequestsRedeliveryWithoutApproval(): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $response = $this->envelope('approved');
        $response['observed'] = ['order_status' => 'processed', 'payment_status' => 'processed',
            'order_status_detail' => 'partially_refunded', 'payment_status_detail' => 'partially_refunded'];
        $this->expectQuery($response);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    public function testAnUnresolvedPartialRefundAlreadyInManualReviewIsAcknowledged(): void
    {
        $linked = $this->linkedOrder();
        $this->expectOrders(1, [$linked]);
        $response = $this->envelope('approved');
        $response['observed'] = ['order_status' => 'processed', 'payment_status' => 'processed',
            'order_status_detail' => 'partially_refunded', 'payment_status_detail' => 'partially_refunded'];
        $this->expectQuery($response);
        $this->ownership->shouldNotReceive('acquire');
        $this->metadata->shouldReceive('getPpOrderReconcileState')->with($linked)
            ->andReturn(OrderMetadata::PP_ORDER_RECONCILE_STATE_MANUAL_REVIEW);
        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers('echo'),
            self::BODY,
            null,
            null,
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([], $scheduled);
    }

    /** @dataProvider ownershipDenialProvider */
    public function testUnresolvedFullRefundIsNotAcknowledgedAsProcessed(string $claim): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $response = $this->envelope('approved');
        $response['payments'][0]['state'] = 'refunded';
        $response['refund_context'] = [];
        $this->expectQuery($response);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'record_full_refund')
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);
        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    private const ATTEMPT_KEY = 'SYNTHETIC_ATTEMPT_KEY';

    private const ORDER_REFERENCE = 'ORDER_SYNTHETIC_001';

    private const CANDIDATE_PAYMENT = 'PPPAYSYNTHETIC001';

    private const CANDIDATE_PROVIDER_ORDER = 'ORDSYNTHETICPROVIDER001';

    private const OTHER_REFERENCE = 'ORDER_SYNTHETIC_999';

    private const SECRET = 'SYNTHETIC_SECRET_VALUE';

    private const BODY = '{"synthetic_attempt":"WC-321","synthetic_order":"ORDER_SYNTHETIC_001"}';

    private const HEADERS = ['x-synthetic-signature' => 'SYNTHETIC_SIGNATURE'];

    private const NOTIFICATION = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    private const APPLY = ['operation' => 'apply', 'source' => 'notification', 'surface' => 'server',
        'payment_method' => 'smart_pix', 'site_id' => 'MLB'];

    /** @var list<array<int, mixed>> */
    private array $events = [];

    private $metadata;

    private $seller;

    private $client;

    private $ownership;

    private $logger;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->logger = Mockery::mock();
        $this->logger->shouldReceive('warning')->never()->byDefault();
        WP_Mock::userFunction('wc_get_logger', ['return' => $this->logger]);
        $this->events = [];
        $this->metadata = Mockery::mock(OrderMetadata::class);
        $this->metadata->shouldNotReceive('persistSmartPixAttempt');
        $this->seller = Mockery::mock(Seller::class);
        $this->seller->shouldReceive('getSiteId')->andReturn('MLB');
        $this->client = Mockery::mock(SmartPixOrderClient::class);
        $this->client->shouldNotReceive('create', 'cancel', 'refund');
        $this->ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testAuthenticatedLinkedNotificationAppliesTheAuthoritativeQueryResponse(): void
    {
        $linked = $this->linkedOrder();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$linked, $pending, $persisted]);
        $this->expectQuery($this->envelope('approved'));
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $authenticator = $this->authenticator(true);
        $identifiers = $this->identifiers('echo');

        $result = $this->receiver($authenticator, $identifiers)->handle(self::HEADERS, (string) strlen(self::BODY));

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([[self::HEADERS, self::BODY]], $authenticator->calls);
        $this->assertSame([['synthetic_attempt' => 'WC-321', 'synthetic_order' => self::ORDER_REFERENCE]], $identifiers->payloads);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    public function testUnlinkedAttemptAdoptsTheNotifiedOrderOnceTheQueryConfirmsItAndAppliesIt(): void
    {
        // The checkout has not persisted the Orders reference yet: the notification id is the candidate.
        $unlinked = $this->orderSnapshot('pending', false, 4);
        $unlinked->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $unlinked->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($unlinked);
        $this->metadata->shouldReceive('getPpOrderId')->with($unlinked)->andReturn('', '', '', self::ORDER_REFERENCE);
        $this->metadata->shouldReceive('linkSmartPixOrder')->once()
            ->with($unlinked, self::ORDER_REFERENCE, Mockery::type('int'), [
                'order_reference' => self::ORDER_REFERENCE,
                'payment_reference' => self::CANDIDATE_PAYMENT,
                'provider_order_reference' => self::CANDIDATE_PROVIDER_ORDER,
            ]);
        $this->metadata->shouldReceive('getMpOrderId')->with($unlinked)->andReturn(self::CANDIDATE_PROVIDER_ORDER);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$unlinked, $pending, $persisted]);
        $this->expectQuery($this->candidateEnvelope());
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    public function testUnlinkedAttemptLinkedByTheCheckoutBeforeTheQueryIsStillQueriedAndApplied(): void
    {
        // Linkage saw no reference; the checkout persisted the same Order before the query re-read it.
        $unlinked = $this->orderSnapshot('pending', false, 3);
        $unlinked->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $unlinked->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($unlinked);
        $this->metadata->shouldReceive('getPpOrderId')->with($unlinked)->andReturn('', self::ORDER_REFERENCE);
        $this->metadata->shouldNotReceive('linkSmartPixOrder');
        // The checkout persisted the provider Order with the same creation references.
        $this->metadata->shouldReceive('getMpOrderId')->with($unlinked)->andReturn(self::CANDIDATE_PROVIDER_ORDER);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$unlinked, $pending, $persisted]);
        $this->expectQuery($this->candidateEnvelope());
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    /** @dataProvider unconfirmedCandidateProvider */
    public function testUnlinkedAttemptIsNotAdoptedWhenTheReadDoesNotNameTheNotifiedProviderOrder(?string $paymentReference): void
    {
        $unlinked = $this->orderSnapshot('pending', false, 2);
        $unlinked->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $unlinked->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($unlinked);
        $this->metadata->shouldReceive('getPpOrderId')->with($unlinked)->andReturn('');
        $this->metadata->shouldNotReceive('linkSmartPixOrder');
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $this->expectOrders(1, [$unlinked]);
        $envelope = $this->candidateEnvelope();
        $envelope['observed']['payment_reference'] = $paymentReference;
        $this->expectQuery($envelope);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))
            ->handle(self::HEADERS, null);

        // Nothing was confirmed and nothing was written; redelivery cannot change a non-retryable answer.
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    /** @dataProvider mismatchedCandidatePaymentsProvider */
    public function testUnlinkedAttemptIsNotAdoptedWhenTheReadDoesNotMatchTheOrderTotal(array $payments): void
    {
        $unlinked = $this->orderSnapshot('pending', false, 2);
        $unlinked->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $unlinked->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($unlinked);
        $this->metadata->shouldReceive('getPpOrderId')->with($unlinked)->andReturn('');
        $this->metadata->shouldNotReceive('linkSmartPixOrder');
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $this->expectOrders(1, [$unlinked]);
        $envelope = $this->candidateEnvelope();
        $envelope['payments'] = $payments;
        $this->expectQuery($envelope);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))
            ->handle(self::HEADERS, null);

        // The Order of another purchase is never linked, even before any effect would refuse it.
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    public static function mismatchedCandidatePaymentsProvider(): array
    {
        $payment = static fn(string $value, string $currency = 'BRL'): array => [
            'reference' => 'synthetic_terminal_payment', 'state' => 'approved',
            'amount' => ['value' => $value, 'currency' => $currency],
        ];
        return [
            'another amount' => [[$payment('99.90')]],
            'another currency' => [[$payment('125.50', 'USD')]],
            'partial payments' => [[$payment('100.00'), $payment('20.00')]],
            'no payments' => [[]],
        ];
    }

    public static function unconfirmedCandidateProvider(): array
    {
        return [
            'another provider Order' => ['ORDSYNTHETICOTHER001'],
            'no provider Order in the read' => [null],
        ];
    }

    /** @return array<string, string> */
    private function candidateIdentifiers(): array
    {
        return [
            'attempt_reference' => 'WC-321',
            'order_reference' => self::ORDER_REFERENCE,
            'provider_order_reference' => self::CANDIDATE_PROVIDER_ORDER,
        ];
    }

    /** Authenticated read of the candidate Order, naming its first payment and provider Order. */
    private function candidateEnvelope(): array
    {
        return $this->envelope('approved') + ['observed' => [
            'order_status' => 'processed', 'order_status_detail' => 'accredited',
            'payment_status' => 'processed', 'payment_status_detail' => 'accredited', 'total_failed' => null,
            'payment_id' => self::CANDIDATE_PAYMENT, 'payment_reference' => self::CANDIDATE_PROVIDER_ORDER,
        ]];
    }

    /** Order paid (or only switched) to another method after its Smart Pix create never answered. */
    private function supersededOrder(bool $paid = true)
    {
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => 'WC-']);
        $order = $this->orderSnapshot($paid ? 'processing' : 'pending', $paid, 1);
        $order->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($order);
        $this->metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('');
        $this->metadata->shouldReceive('getSupersededSmartPixAttempt')->with($order)
            ->andReturn(['attempt_key' => self::ATTEMPT_KEY, 'attempt_reference' => 'WC-321']);
        $this->metadata->shouldNotReceive('linkSmartPixOrder', 'persistSmartPixObservation');
        $this->ownership->shouldNotReceive('acquire');
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        return $order;
    }

    /** @dataProvider supersededReviewNoteProvider */
    public function testALateApprovedOrderOfASupersededAttemptIsRecordedForReviewWithoutPaymentEffects(
        bool $paid,
        string $note
    ): void {
        $order = $this->supersededOrder($paid);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        $this->expectQuery($this->candidateEnvelope());
        $this->metadata->shouldReceive('isSupersededSmartPixSettled')->once()->with($order, self::ORDER_REFERENCE)->andReturn(false);
        $order->shouldReceive('add_order_note')->once()->with(sprintf($note, self::ORDER_REFERENCE))->ordered();
        // Manual review is recorded with the marker, whether or not the replacing method was paid.
        $this->metadata->shouldReceive('settleSupersededSmartPixReview')->once()->with($order, self::ORDER_REFERENCE)->ordered();

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([
            ['count', SmartPixMetricSchema::RECONCILIATION, 'manual_review',
                ['operation' => 'apply', 'source' => 'internal', 'surface' => 'server',
                    'payment_method' => 'smart_pix', 'site_id' => 'MLB'], 'superseded_payment'],
            $this->time('success'),
        ], $this->events);
    }

    public static function supersededReviewNoteProvider(): array
    {
        return [
            // Only a paid order can hold a duplicate payment to refund.
            'order paid by the other method' => [true, 'Mercado Pago: a Smart Pix payment (Order %s) was approved after'
                . ' this order was paid with another method. Review it in the Mercado Pago panel and refund it if it is a duplicate.'],
            'other method not paid' => [false, 'Mercado Pago: a Smart Pix payment (Order %s) was approved after another'
                . ' payment method was selected for this order. Review it in the Mercado Pago panel before changing the order.'],
        ];
    }

    public function testARedeliveredLateOrderOfASupersededAttemptIsNotRecordedTwice(): void
    {
        $order = $this->supersededOrder();
        $this->expectQuery($this->candidateEnvelope());
        $this->metadata->shouldReceive('isSupersededSmartPixSettled')->once()->with($order, self::ORDER_REFERENCE)->andReturn(true);
        $this->metadata->shouldNotReceive('settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $order->shouldNotReceive('add_order_note');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    /** @dataProvider unconfirmedSupersededReadProvider */
    public function testAnUnconfirmedLateOrderOfASupersededAttemptIsAcknowledgedWithoutReview(array $response): void
    {
        $order = $this->supersededOrder();
        $this->expectQuery($response);
        $this->metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $order->shouldNotReceive('add_order_note');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
    }

    public function unconfirmedSupersededReadProvider(): array
    {
        $rejected = $this->envelope('rejected');
        $otherProvider = $this->candidateEnvelope();
        $otherProvider['observed']['payment_reference'] = 'ORDSYNTHETICOTHER001';
        return ['not approved' => [$rejected], 'another provider Order' => [$otherProvider]];
    }

    public function testATransientReadOfASupersededAttemptRequestsRedelivery(): void
    {
        $order = $this->supersededOrder();
        $this->expectQuery($this->retryableFailure('unavailable', null));
        $this->metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview');
        $order->shouldNotReceive('add_order_note');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    /** @dataProvider refusedReadOfALinkedOrderProvider */
    public function testARefusedReadOfASupersededAttemptRequestsRedeliveryWithoutReview(string $category, string $state): void
    {
        // No reconciliation tracks a superseded attempt: only redelivery can still reach a late paid Order.
        $order = $this->supersededOrder();
        $failure = $this->retryableFailure($category, self::ORDER_REFERENCE);
        $failure['state'] = $state;
        $failure['error']['retryable'] = false;
        $this->expectQuery($failure);
        $this->metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $order->shouldNotReceive('add_order_note');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    public function testALocallyInvalidReadOfASupersededAttemptIsAcknowledgedWithoutRedelivery(): void
    {
        // A local validation failure names no Order: redelivery would only repeat it.
        $order = $this->supersededOrder();
        $failure = $this->retryableFailure('validation', null);
        $failure['state'] = 'error';
        $failure['error']['retryable'] = false;
        $this->expectQuery($failure);
        $this->metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $order->shouldNotReceive('add_order_note');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
    }

    public function testAuthenticatedRejectionAppliesTheNativeFailedStatus(): void
    {
        $linked = $this->linkedOrder();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
        $pending->shouldNotReceive('payment_complete');
        $persisted = $this->orderSnapshot('failed', false, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$linked, $pending, $persisted]);
        $this->expectQuery($this->envelope('rejected'));
        $owner = $this->expectAcquired('mark_failed');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'mark_failed', $owner)->andReturn(true);

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'failed'),
            $this->time('success'),
        ], $this->events);
    }

    /** @dataProvider earlyRejectionProvider */
    public function testSenderRejectionsStopBeforeLinkageWithOneRejectionMetric(
        ?string $body,
        ?string $contentLength,
        bool $authenticated,
        string $extraction,
        int $status,
        string $message,
        string $category,
        string $reason,
        int $authCalls,
        int $extractCalls
    ): void {
        $this->forbidLinkageAndEffects();
        $authenticator = $this->authenticator($authenticated);
        $identifiers = $this->identifiers($extraction);

        $result = $this->receiver($authenticator, $identifiers, $body)->handle(self::HEADERS, $contentLength);

        $this->assertResponse($status, $message, $result);
        $this->assertCount($authCalls, $authenticator->calls);
        $this->assertCount($extractCalls, $identifiers->payloads);
        $this->assertSame([$this->rejected($category, $reason), $this->time('failure')], $this->events);
    }

    public static function earlyRejectionProvider(): array
    {
        $tooLarge = str_repeat('a', SmartPixNotificationBody::DEFAULT_MAX_BYTES + 1);
        $invalid = SmartPixNotificationReceiver::MESSAGE_INVALID_PAYLOAD;
        $large = SmartPixNotificationReceiver::MESSAGE_TOO_LARGE;
        return [
            'declared length above the limit' => [self::BODY, '16385', true, 'echo', 413, $large, 'body_too_large', '', 0, 0],
            'oversized declared length' => [self::BODY, str_repeat('9', 40), true, 'echo', 413, $large, 'body_too_large', '', 0, 0],
            'actual body above the limit' => [$tooLarge, null, true, 'echo', 413, $large, 'body_too_large', '', 0, 0],
            'malformed declared length' => [self::BODY, '12abc', true, 'echo', 400, $invalid, 'invalid_payload', '', 0, 0],
            'unreadable body' => [null, null, true, 'echo', 400, $invalid, 'invalid_payload', 'unreadable_body', 0, 0],
            'not authenticated' => [self::BODY, null, false, 'echo', 401,
                SmartPixNotificationReceiver::MESSAGE_UNAUTHENTICATED, 'invalid_auth', '', 1, 0],
            'invalid json' => ['{"synthetic_attempt":', null, true, 'echo', 400, $invalid, 'invalid_payload', '', 1, 0],
            'json list' => ['["WC-321"]', null, true, 'echo', 400, $invalid, 'invalid_payload', '', 1, 0],
            'json scalar' => ['"WC-321"', null, true, 'echo', 400, $invalid, 'invalid_payload', '', 1, 0],
            'extractor returns null' => [self::BODY, null, true, 'null', 400, $invalid, 'invalid_payload', '', 1, 1],
            'extractor misses a key' => [self::BODY, null, true, 'missing', 400, $invalid, 'invalid_payload', '', 1, 1],
            'extractor returns non-strings' => [self::BODY, null, true, 'non_string', 400, $invalid, 'invalid_payload', '', 1, 1],
        ];
    }

    public function testContentLengthMismatchWithinTheLimitIsAccepted(): void
    {
        $this->forbidLinkageAndEffects();
        $authenticator = $this->authenticator(false);

        $result = $this->receiver($authenticator, $this->identifiers('echo'))->handle(self::HEADERS, '3');

        $this->assertResponse(401, SmartPixNotificationReceiver::MESSAGE_UNAUTHENTICATED, $result);
        $this->assertSame([[self::HEADERS, self::BODY]], $authenticator->calls);
        $this->assertSame([$this->rejected('invalid_auth'), $this->time('failure')], $this->events);
    }

    /** @dataProvider unlinkedProvider */
    public function testUnlinkedNotificationIsAcknowledgedWithoutQueryOrEffects(string $attemptReference, bool $loadsOrder): void
    {
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => 'WC-']);
        if ($loadsOrder) {
            $order = $this->orderSnapshot('pending', false, 1);
            $this->metadata->shouldReceive('getPpOrderId')->with($order)->andReturn(self::OTHER_REFERENCE);
            $this->forbidEffects($order);
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        }
        $this->client->shouldNotReceive('query');
        $this->ownership->shouldNotReceive('acquire');
        $identifiers = $this->identifiers(['attempt_reference' => $attemptReference, 'order_reference' => self::ORDER_REFERENCE]);

        $result = $this->receiver($this->authenticator(true), $identifiers)->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_IGNORED, $result);
        $this->assertSame([$this->rejected('invalid_linkage'), $this->time('failure')], $this->events);
    }

    public static function unlinkedProvider(): array
    {
        return [
            'foreign attempt reference' => ['XX-321', false],
            'persisted reference differs' => ['WC-321', true],
        ];
    }

    public function testAPaidOrderThatTheNativeStateRefusesIsSentToTheReconciliation(): void
    {
        $linked = $this->linkedOrder('cancelled');
        $cancelled = $this->orderSnapshot('cancelled', false, 1);
        $this->forbidEffects($cancelled);
        $this->expectOrders(1, [$linked, $cancelled]);
        $this->expectQuery($this->envelope('approved'));
        $this->expectConflictingClaim();
        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers('echo'),
            self::BODY,
            null,
            null,
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(409, SmartPixNotificationReceiver::MESSAGE_CONFLICT, $result);
        $this->assertSame([321], $scheduled);
    }

    public function testALinkedOrderWithoutItsProviderReferenceCompletesItFromTheReadAndApplies(): void
    {
        $linked = $this->orderSnapshot('pending', false, 3);
        $linked->shouldReceive('get_payment_method')->withNoArgs()->once()->andReturn(PixGateway::ID);
        $linked->shouldReceive('get_currency')->withNoArgs()->once()->andReturn('BRL');
        $this->forbidEffects($linked);
        $this->metadata->shouldReceive('getMpOrderId')->with($linked)->andReturn('', self::CANDIDATE_PROVIDER_ORDER);
        $this->metadata->shouldReceive('persistSmartPixReferences')->once()->with($linked, [
            'order_reference' => self::ORDER_REFERENCE,
            'payment_reference' => self::CANDIDATE_PAYMENT,
            'provider_order_reference' => self::CANDIDATE_PROVIDER_ORDER,
        ]);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$linked, $pending, $persisted]);
        $this->expectQuery($this->candidateEnvelope());
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
    }

    public function testALinkedOrderWithoutItsProviderReferenceIgnoresAReadNamingAnotherProviderOrder(): void
    {
        $linked = $this->linkedOrder();
        $this->metadata->shouldReceive('getMpOrderId')->with($linked)->andReturn('');
        $this->metadata->shouldNotReceive('persistSmartPixReferences');
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $this->expectOrders(1, [$linked]);
        $other = $this->candidateEnvelope();
        $other['observed']['payment_reference'] = 'ORDSYNTHETICOTHER001';
        $this->expectQuery($other);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers($this->candidateIdentifiers()))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_IGNORED, $result);
    }

    public function testALinkedOrderWithoutItsProviderReferenceRequestsRedeliveryWhenTheReadIsTransient(): void
    {
        $linked = $this->linkedOrder();
        $this->metadata->shouldReceive('getMpOrderId')->with($linked)->andReturn('');
        // Nothing is completed from a read that did not answer: the provider reference stays missing.
        $this->metadata->shouldNotReceive('persistSmartPixReferences', 'persistSmartPixObservation');
        $this->expectOrders(1, [$linked]);
        $this->expectQuery($this->retryableFailure('unavailable', self::ORDER_REFERENCE));
        $this->ownership->shouldNotReceive('acquire');
        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers($this->candidateIdentifiers()),
            self::BODY,
            null,
            null,
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertSame([321], $scheduled);
        $this->assertSame([$this->time('failure')], $this->events);
    }

    /** @dataProvider refusedReadOfALinkedOrderProvider */
    public function testALinkedOrderWithoutItsProviderReferenceRequestsReconciliationWhenOrdersRefusesTheRead(
        string $category,
        string $state
    ): void {
        $linked = $this->linkedOrder();
        $this->metadata->shouldReceive('getMpOrderId')->with($linked)->andReturn('');
        // A refused read names no provider Order, so it is neither completed nor ignored as foreign.
        $this->metadata->shouldNotReceive('persistSmartPixReferences', 'persistSmartPixObservation');
        $this->expectOrders(1, [$linked]);
        $failure = $this->retryableFailure($category, self::ORDER_REFERENCE);
        $failure['state'] = $state;
        $failure['error']['retryable'] = false;
        $this->expectQuery($failure);
        $this->ownership->shouldNotReceive('acquire');
        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers($this->candidateIdentifiers()),
            self::BODY,
            null,
            null,
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertSame([321], $scheduled);
        $this->assertSame([$this->time('failure')], $this->events);
    }

    public static function refusedReadOfALinkedOrderProvider(): array
    {
        return [
            'authentication' => ['authentication', 'error'],
            'validation' => ['validation', 'error'],
            'conflict' => ['conflict', 'error'],
            'unknown' => ['unknown', 'processing'],
            'invalid response' => ['invalid_response', 'error'],
        ];
    }

    public function testASupersededLinkWithoutAWooCommerceOrderIsIgnoredWithoutAnyRead(): void
    {
        // Defensive guard: the linkage always names the loaded order, so only a direct call reaches it.
        $this->client->shouldNotReceive('query');
        $this->metadata->shouldNotReceive('isSupersededSmartPixSettled', 'settleSupersededSmartPixReview', 'setPpOrderReconcileState');
        $review = new \ReflectionMethod(SmartPixNotificationReceiver::class, 'reviewSuperseded');
        $review->setAccessible(true);
        $stage = 'internal';
        $arguments = [null, $this->candidateIdentifiers(), &$stage];

        $result = $review->invokeArgs($this->receiver($this->authenticator(true), $this->identifiers('echo')), $arguments);

        $this->assertSame(['status' => 200, 'message' => SmartPixNotificationReceiver::MESSAGE_IGNORED, 'success' => false], $result);
        $this->assertSame('internal', $stage);
        $this->assertSame([$this->rejected('invalid_linkage')], $this->events);
    }

    /** @dataProvider unappliedQueryProvider */
    public function testAuthoritativeAnswersThatCannotBeAppliedHaveNoEffect(array $envelope, bool $mismatch): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($envelope);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        // An answer about another Order is a contract conflict; the others are acknowledged.
        if ($mismatch) {
            $this->assertResponse(409, SmartPixNotificationReceiver::MESSAGE_CONFLICT, $result);
            $this->assertSame([$this->error('invalid_contract'), $this->time('failure')], $this->events);
            return;
        }
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    public function unappliedQueryProvider(): array
    {
        $failure = [
            'outcome' => 'failure', 'state' => 'error', 'order_reference' => null,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => 'validation', 'code' => 'SMART_PIX_VALIDATION', 'retryable' => false],
        ];
        $processing = array_merge($this->envelope('approved'), ['state' => 'processing', 'payments' => []]);
        return [
            'returned non-retryable query failure' => [$failure, false],
            'non-terminal answer' => [$processing, false],
            'terminal answer for another order' => [array_merge($this->envelope('approved'), ['order_reference' => self::OTHER_REFERENCE]), true],
            'non-terminal answer for another order' => [array_merge($processing, ['order_reference' => self::OTHER_REFERENCE]), true],
            'terminal answer without reference' => [array_merge($this->envelope('approved'), ['order_reference' => null]), true],
            'retryable failure for another order' => [$this->retryableFailure('unavailable', self::OTHER_REFERENCE), true],
        ];
    }

    /** @dataProvider retryableQueryFailureProvider */
    public function testRetryableQueryFailureAnswersAServerErrorSoTheSenderRedelivers(string $category, ?string $reference): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($this->retryableFailure($category, $reference));
        $this->ownership->shouldNotReceive('acquire');

        $scheduled = [];

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, null, $this->reconciliationRecorder($scheduled))
            ->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        // The client owns the error metric for its returned failure; the receiver adds none.
        $this->assertSame([$this->time('failure')], $this->events);
        // The WP-Cron reconciliation backs up the redelivery of a linked order.
        $this->assertSame([321], $scheduled);
    }

    public function testFailingReconciliationSchedulingKeepsTheServerErrorAnswer(): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($this->retryableFailure('unavailable', self::ORDER_REFERENCE));
        $this->ownership->shouldNotReceive('acquire');
        $failing = static function (): void {
            throw new \RuntimeException('SYNTHETIC_CRON_FAILURE');
        };

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, null, $failing)
            ->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    /** Shapes returned by the Orders client for transient reads (5xx, 418, 422 and 424 map to unavailable). */
    public static function retryableQueryFailureProvider(): array
    {
        return [
            'timeout' => ['timeout', self::ORDER_REFERENCE],
            'unavailable (5xx, 418, 422, 424)' => ['unavailable', self::ORDER_REFERENCE],
            'rate limit' => ['rate_limit', self::ORDER_REFERENCE],
            'retryable failure without reference' => ['unavailable', null],
        ];
    }

    /** @dataProvider unavailableOwnershipProvider */
    public function testUnavailableOwnershipAnswersAServerErrorSoTheSenderRedelivers(string $mode): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($this->envelope('approved'));
        $this->expectUnavailableOwnership($mode);
        $observed = [];

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $this->observer($observed))
            ->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertSame([], $observed, 'An unconfirmed effect is not a terminal observation.');
        // The applicator already reports the effect; the receiver adds no error metric.
        $this->assertApplicationDiagnostic($this->events);
        $this->assertSame([
            $this->effect('uncertain', 'ownership', 'unknown'),
            $this->time('failure'),
        ], $this->events);
    }

    public static function unavailableOwnershipProvider(): array
    {
        return [
            'claim unavailable' => ['unavailable'],
            'acquire throws' => ['throws'],
            'malformed owner' => ['malformed_owner'],
        ];
    }

    public function testTerminalFailureMarkedRetryableIsLeftToTheApplicatorAndAnsweredUnsupported(): void
    {
        $envelope = $this->envelope('rejected');
        $envelope['error']['retryable'] = true;
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($envelope);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        // The applicator refuses this shape without effects; redelivery cannot change it.
        $this->assertResponse(422, SmartPixNotificationReceiver::MESSAGE_UNSUPPORTED, $result);
        $this->assertApplicationDiagnostic($this->events, true, 'invalid_contract');
        $this->assertSame([
            $this->effect('unsupported', 'invalid_context', 'unknown'),
            $this->rejected('unsupported'),
            $this->time('failure'),
        ], $this->events);
    }

    public function testRedeliveryAfterARetryableFailureAppliesThePaymentExactlyOnce(): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(2, [$this->linkedOrder(), $this->linkedOrder(), $pending, $persisted]);
        $this->client->shouldReceive('query')->twice()
            ->andReturn($this->retryableFailure('unavailable', self::ORDER_REFERENCE), $this->envelope('approved'));
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $receiver = $this->receiver($this->authenticator(true), $this->identifiers('echo'));

        $first = $receiver->handle(self::HEADERS, null);
        $second = $receiver->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $first);
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $second);
        $this->assertSame([
            $this->time('failure'),
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    /** @dataProvider processingObservationProvider */
    public function testProcessingQueryResultIsRecordedWithoutApplyingOrReportingAnUnknownState(string $status, string $detail): void
    {
        $envelope = [
            'outcome' => 'success', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null,
            'payments' => [[
                'reference' => 'synthetic_processing_payment',
                'state' => 'processing',
                'amount' => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error' => null,
            'observed' => [
                'order_status' => $status, 'order_status_detail' => $detail,
                'payment_status' => $status, 'payment_status_detail' => $detail, 'total_failed' => null,
                'payment_id' => 'synthetic_processing_payment', 'payment_reference' => null,
            ],
        ];
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($envelope);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once()->with(Mockery::type(WC_Order::class), $envelope['observed']);
        $this->ownership->shouldNotReceive('acquire');

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([$this->time('success')], $this->events);
    }

    public static function processingObservationProvider(): array
    {
        return [
            'in process' => ['processing', 'in_process'],
            'waiting transfer' => ['action_required', 'waiting_transfer'],
        ];
    }

    /** @dataProvider ownershipDenialProvider */
    public function testOwnershipDenialHasNoEffectAndAnswersByOutcome(string $claim, int $status, string $message, string $category): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($this->envelope('approved'));
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'))->handle(self::HEADERS, null);

        // A conflict cannot change on redelivery; busy and uncertain effects ask the sender to redeliver.
        $this->assertResponse($status, $message, $result);
        if ($claim !== 'busy') { $this->assertApplicationDiagnostic($this->events, true, $claim === 'conflict' ? 'conflict' : 'persistence'); }
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

    public function testReplayedNotificationAfterCompletionDoesNotCompletePaymentTwice(): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $pending->shouldNotReceive('update_status');
        $persisted = $this->orderSnapshot('processing', true, 1);
        $replayed = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->forbidEffects($replayed);
        $this->expectOrders(2, [$this->linkedOrder(), $pending, $persisted, $this->linkedOrder('processing', true), $replayed]);
        $this->client->shouldReceive('query')->twice()->andReturn($this->envelope('approved'));
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->twice()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner], ['outcome' => 'completed', 'owner' => null]);
        $this->ownership->shouldReceive('start')->once()->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $receiver = $this->receiver($this->authenticator(true), $this->identifiers('echo'));

        $first = $receiver->handle(self::HEADERS, null);
        $second = $receiver->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $first);
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $second);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
            $this->effect('no_change', 'state_decision', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    /** @dataProvider throwableProvider */
    public function testThrowablesAreSanitizedServerErrorsWithoutRejectionMetrics(string $stage, string $category): void
    {
        $failure = new RuntimeException('Provider read failed; token=' . self::SECRET);
        $authenticator = $this->authenticator($stage === 'authentication' ? $failure : true);
        $identifiers = $this->identifiers($stage === 'extraction' ? $failure : 'echo');
        $this->ownership->shouldNotReceive('acquire');
        if ($stage === 'linkage') {
            WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 1, 'return' => 'WC-']);
            WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => static function () use ($failure) {
                throw $failure;
            }]);
            $this->client->shouldNotReceive('query');
        } elseif ($stage === 'query') {
            $this->expectOrders(1, [$this->linkedOrder()]);
            $this->client->shouldReceive('query')->once()->andThrow($failure);
        } else {
            $this->forbidLinkageAndEffects();
        }

        $result = $this->receiver($authenticator, $identifiers)->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $expected = $this->error($category);
        $expected[] = ($stage === 'query' ? '' : 'notification.' . ($stage === 'authentication' ? 'authentication' : 'internal') . ': ')
            . 'RuntimeException [0]: Provider read failed; token=[REDACTED]';
        $this->assertSame([$expected, $this->time('failure')], $this->events);
    }

    public static function throwableProvider(): array
    {
        return [
            'authenticator throws' => ['authentication', 'unavailable'],
            'extractor throws' => ['extraction', 'internal_error'],
            'linkage storage throws' => ['linkage', 'internal_error'],
            // Reported once by the query itself before it rethrows.
            'query throws' => ['query', 'internal_error'],
        ];
    }

    public function testMissingClockSkipsProcessingTimeWithoutChangingTheResponse(): void
    {
        $this->forbidLinkageAndEffects();
        $telemetry = new SmartPixTelemetry($this->recordingSink(), static function () {
            return null;
        });

        $result = $this->receiver($this->authenticator(false), $this->identifiers('echo'), self::BODY, $telemetry)
            ->handle(self::HEADERS, null);

        $this->assertResponse(401, SmartPixNotificationReceiver::MESSAGE_UNAUTHENTICATED, $result);
        $this->assertSame([$this->rejected('invalid_auth')], $this->events);
    }

    public function testFailingSinkNeverChangesTheResponseOrTheEffect(): void
    {
        $linked = $this->linkedOrder();
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$linked, $pending, $persisted]);
        $this->expectQuery($this->envelope('approved'));
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->andThrow(new RuntimeException(self::SECRET));
        $sink->shouldReceive('distribution')->once()->andThrow(new RuntimeException(self::SECRET));
        $telemetry = new SmartPixTelemetry($sink, $this->clock());

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, $telemetry)
            ->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
    }

    /** @dataProvider appliedTerminalProvider */
    public function testAppliedTerminalStateIsObservedOnceWithTheOrderId(string $state, string $effect, string $persistedStatus): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        if ($state === 'approved') {
            $pending->shouldReceive('payment_complete')->once()->andReturn(true);
            $pending->shouldNotReceive('update_status');
        } else {
            $pending->shouldReceive('update_status')->with('failed')->once()->andReturn(true);
            $pending->shouldNotReceive('payment_complete');
        }
        $persisted = $this->orderSnapshot($persistedStatus, $state === 'approved', 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$this->linkedOrder(), $pending, $persisted]);
        $this->expectQuery($this->envelope($state));
        $owner = $this->expectAcquired($effect);
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, $effect, $owner)->andReturn(true);
        $observed = [];

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $this->observer($observed))
            ->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([321], $observed);
    }

    public static function appliedTerminalProvider(): array
    {
        return [
            'approval' => ['approved', 'complete_payment', 'processing'],
            'rejection' => ['rejected', 'mark_failed', 'failed'],
        ];
    }

    public function testReplayedTerminalStateWithoutChangeIsObservedAgainForTheIdempotentObserver(): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $persisted = $this->orderSnapshot('processing', true, 1);
        $replayed = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->forbidEffects($replayed);
        $this->expectOrders(2, [$this->linkedOrder(), $pending, $persisted, $this->linkedOrder('processing', true), $replayed]);
        $this->client->shouldReceive('query')->twice()->andReturn($this->envelope('approved'));
        $this->ownership->shouldReceive('acquire')->twice()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => str_repeat('a', 64)], ['outcome' => 'completed', 'owner' => null]);
        $this->ownership->shouldReceive('start')->once()->andReturn(true);
        $this->ownership->shouldReceive('complete')->once()->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        $observed = [];
        $receiver = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $this->observer($observed));

        $first = $receiver->handle(self::HEADERS, null);
        $observedAfterFirst = $observed;
        $second = $receiver->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $first);
        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $second);
        $this->assertSame([321], $observedAfterFirst);
        // The no_change delivery is observed once too; the observer itself counts the outcome only once.
        $this->assertSame([321, 321], $observed);
        $this->assertSame('no_change', $this->events[2][2]);
    }

    /** @dataProvider ownershipDenialProvider */
    public function testDeniedTerminalOutcomesAreNotObserved(string $claim, int $status, string $message): void
    {
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($this->envelope('approved'));
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => $claim, 'owner' => null]);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
        $observed = [];

        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers('echo'),
            self::BODY,
            null,
            $this->observer($observed),
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse($status, $message, $result);
        // Only a redelivery request (500) is backed by the WP-Cron reconciliation; 409 is final.
        $this->assertSame($status === 500 ? [321] : [], $scheduled);
        if ($claim !== 'busy') { $this->assertApplicationDiagnostic($this->events, true, $claim === 'conflict' ? 'conflict' : 'persistence'); }
        $this->assertSame($claim, $this->events[0][2]);
        $this->assertSame([], $observed);
    }

    public function testUncertainNativeEffectIsNotObserved(): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(false);
        $persisted = $this->orderSnapshot('pending', false, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$this->linkedOrder(), $pending, $persisted]);
        $this->expectQuery($this->envelope('approved'));
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('start')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $this->ownership->shouldReceive('markUncertain')->once()->andReturn(true);
        $this->ownership->shouldNotReceive('complete', 'releaseReserved');
        $observed = [];

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $this->observer($observed))
            ->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
        $this->assertApplicationDiagnostic($this->events);
        $this->assertSame('uncertain', $this->events[0][2]);
        $this->assertSame([], $observed);
    }

    public function testUnsupportedTerminalShapeIsNotObserved(): void
    {
        $envelope = $this->envelope('rejected');
        $envelope['error']['retryable'] = true;
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($envelope);
        $this->ownership->shouldNotReceive('acquire');
        $observed = [];

        $scheduled = [];

        $result = $this->receiver(
            $this->authenticator(true),
            $this->identifiers('echo'),
            self::BODY,
            null,
            $this->observer($observed),
            $this->reconciliationRecorder($scheduled)
        )->handle(self::HEADERS, null);

        $this->assertResponse(422, SmartPixNotificationReceiver::MESSAGE_UNSUPPORTED, $result);
        $this->assertSame([], $scheduled);
        $this->assertApplicationDiagnostic($this->events, true, 'invalid_contract');
        $this->assertSame('unsupported', $this->events[0][2]);
        $this->assertSame([], $observed);
    }

    public function testNonTerminalQueryResultIsNotObserved(): void
    {
        $envelope = [
            'outcome' => 'success', 'state' => 'processing', 'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null, 'authorization' => null, 'payments' => [], 'error' => null,
            'observed' => ['order_status' => 'processing', 'order_status_detail' => 'in_process',
                'payment_status' => 'processing', 'payment_status_detail' => 'in_process', 'total_failed' => null],
        ];
        $this->expectOrders(1, [$this->linkedOrder()]);
        $this->expectQuery($envelope);
        $this->metadata->shouldReceive('persistSmartPixObservation')->once();
        $this->ownership->shouldNotReceive('acquire');
        $observed = [];

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $this->observer($observed))
            ->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame([], $observed);
    }

    /** @dataProvider throwingObserverProvider */
    public function testThrowingTerminalObserverNeverChangesTheResponseOrTheMetrics(\Throwable $failure): void
    {
        $pending = $this->orderSnapshot('pending', false, 1);
        $pending->shouldReceive('payment_complete')->once()->andReturn(true);
        $persisted = $this->orderSnapshot('processing', true, 1);
        $this->forbidEffects($persisted);
        $this->expectOrders(1, [$this->linkedOrder(), $pending, $persisted]);
        $this->expectQuery($this->envelope('approved'));
        $owner = $this->expectAcquired('complete_payment');
        $this->ownership->shouldReceive('complete')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)->andReturn(true);
        $calls = 0;
        $observer = static function (int $orderId) use (&$calls, $failure): void {
            ++$calls;
            throw $failure;
        };

        $result = $this->receiver($this->authenticator(true), $this->identifiers('echo'), self::BODY, null, $observer)
            ->handle(self::HEADERS, null);

        $this->assertResponse(200, SmartPixNotificationReceiver::MESSAGE_PROCESSED, $result);
        $this->assertSame(1, $calls);
        $this->assertSame([
            $this->effect('applied', 'native_verification', 'processing'),
            $this->time('success'),
        ], $this->events);
    }

    public static function throwingObserverProvider(): array
    {
        return [
            'exception' => [new RuntimeException(self::SECRET)],
            'error' => [new \Error(self::SECRET)],
            'type error' => [new \TypeError(self::SECRET)],
        ];
    }

    /**
     * @param list<int> $observed
     * @return \Closure(int): void
     */
    private function observer(array &$observed): \Closure
    {
        return static function (int $orderId) use (&$observed): void {
            $observed[] = $orderId;
        };
    }

    public function testFailingSinkNeverChangesASanitizedServerError(): void
    {
        $this->forbidLinkageAndEffects();
        $sink = Mockery::mock(SmartPixMetricSink::class);
        $sink->shouldReceive('count')->once()->andThrow(new RuntimeException(self::SECRET));
        $sink->shouldReceive('distribution')->once()->andThrow(new RuntimeException(self::SECRET));
        $telemetry = new SmartPixTelemetry($sink, $this->clock());
        $authenticator = $this->authenticator(new RuntimeException(self::SECRET));

        $result = $this->receiver($authenticator, $this->identifiers('echo'), self::BODY, $telemetry)->handle(self::HEADERS, null);

        $this->assertResponse(500, SmartPixNotificationReceiver::MESSAGE_FAILED, $result);
    }

    /** @param bool|\Throwable $result */
    private function authenticator($result): SmartPixNotificationAuthenticator
    {
        return new class ($result) implements SmartPixNotificationAuthenticator {
            /** @var list<array{array<string, string>, string}> */
            public array $calls = [];

            /** @var bool|\Throwable */
            private $result;

            /** @param bool|\Throwable $result */
            public function __construct($result)
            {
                $this->result = $result;
            }

            public function authenticate(array $headers, string $rawBody): bool
            {
                $this->calls[] = [$headers, $rawBody];
                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }
                return $this->result;
            }
        };
    }

    /** @param string|array<string, mixed>|\Throwable $mode 'echo' maps the synthetic payload fields. */
    private function identifiers($mode): SmartPixNotificationIdentifiers
    {
        return new class ($mode) implements SmartPixNotificationIdentifiers {
            /** @var list<array<string, mixed>> */
            public array $payloads = [];

            /** @var string|array<string, mixed>|\Throwable */
            private $mode;

            /** @param string|array<string, mixed>|\Throwable $mode */
            public function __construct($mode)
            {
                $this->mode = $mode;
            }

            public function extract(array $payload): ?array
            {
                $this->payloads[] = $payload;
                if ($this->mode instanceof \Throwable) {
                    throw $this->mode;
                }
                if (is_array($this->mode)) {
                    return $this->mode;
                }
                $map = [
                    'echo' => ['attempt_reference' => $payload['synthetic_attempt'], 'order_reference' => $payload['synthetic_order']],
                    'null' => null,
                    'missing' => ['attempt_reference' => $payload['synthetic_attempt']],
                    'non_string' => ['attempt_reference' => 321, 'order_reference' => ['ORDER_SYNTHETIC_001']],
                ];
                return $map[$this->mode];
            }
        };
    }

    private function receiver(
        SmartPixNotificationAuthenticator $authenticator,
        SmartPixNotificationIdentifiers $identifiers,
        ?string $body = self::BODY,
        ?SmartPixTelemetry $telemetry = null,
        ?\Closure $terminalObserved = null,
        ?callable $scheduleReconciliation = null
    ): SmartPixNotificationReceiver {
        $telemetry = $telemetry ?? new SmartPixTelemetry($this->recordingSink(), $this->clock());
        return new SmartPixNotificationReceiver(
            new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->opener($body)),
            $authenticator,
            $identifiers,
            new SmartPixNotificationLinkage($this->metadata, $this->seller),
            new SmartPixOrderQuery($this->client, $this->metadata, $this->seller, $telemetry, 'notification'),
            new SmartPixStateApplicator($this->metadata, new SmartPixStateReducer(), $this->seller, $this->ownership, $telemetry),
            $telemetry,
            $terminalObserved,
            $scheduleReconciliation
        );
    }

    /** Records each order id handed to the WP-Cron reconciliation. */
    private function reconciliationRecorder(array &$scheduled): callable
    {
        return static function (int $orderId) use (&$scheduled): void {
            $scheduled[] = $orderId;
        };
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

    /** Every request measures exactly 0.25 seconds. */
    private function clock(): callable
    {
        $tick = 0;
        return static function () use (&$tick): float {
            return 10.0 + 0.25 * $tick++;
        };
    }

    /** @return callable(): mixed */
    private function opener(?string $body): callable
    {
        return static function () use ($body) {
            if ($body === null) {
                return false;
            }
            $stream = fopen('php://memory', 'w+b');
            fwrite($stream, $body);
            rewind($stream);
            return $stream;
        };
    }

    private function forbidLinkageAndEffects(): void
    {
        $this->metadata->shouldNotReceive('getIdempotencyKey', 'getPpOrderId', 'getPaymentContract');
        $this->client->shouldNotReceive('query');
        $this->ownership->shouldNotReceive('acquire');
    }

    /** @param list<mixed> $orders */
    private function expectOrders(int $requests, array $orders): void
    {
        // Linkage and query each read the store identificator once per request.
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'times' => 2 * $requests, 'return' => 'WC-']);
        WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => count($orders), 'return_in_order' => $orders]);
    }

    /** @param array<string, mixed> $envelope */
    private function expectQuery(array $envelope): void
    {
        $this->client->shouldReceive('query')->once()->with([
            'surface' => 'server',
            'country' => 'MLB',
            'payment_contract' => OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1,
            'attempt_reference' => 'WC-321',
            'order_reference' => self::ORDER_REFERENCE,
            'amount' => null,
            'refund_amount' => null,
        ])->andReturn($envelope);
    }

    /** Ownership storage that cannot admit the complete_payment effect. */
    private function expectUnavailableOwnership(string $mode): void
    {
        $acquire = $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment');
        if ($mode === 'throws') {
            $acquire->andThrow(new RuntimeException(self::SECRET));
            $this->logger->shouldReceive('warning')->once()->with('Smart Pix state application failed.', [
                'source' => 'mercadopago-smart-pix', 'reason' => 'ownership_unavailable',
            ]);
        } else {
            $acquire->andReturn(['outcome' => $mode === 'unavailable' ? 'unavailable' : 'acquired',
                'owner' => $mode === 'unavailable' ? null : 'not-a-valid-owner']);
        }
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain', 'releaseReserved');
    }

    /** @return array<string, mixed> */
    private function retryableFailure(string $category, ?string $reference): array
    {
        return [
            'outcome' => 'failure', 'state' => 'processing', 'order_reference' => $reference,
            'eligibility' => null, 'authorization' => null, 'payments' => [],
            'error' => ['category' => $category, 'code' => 'SMART_PIX_' . strtoupper($category), 'retryable' => true],
        ];
    }

    /** An approval claim that the reducer refuses, so the reservation is released without effects. */
    private function expectConflictingClaim(): void
    {
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment')
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('releaseReserved')->once()->with(321, self::ATTEMPT_KEY, 'complete_payment', $owner)
            ->andReturn(true);
        $this->ownership->shouldNotReceive('start', 'complete', 'markUncertain');
    }

    private function expectAcquired(string $effect): string
    {
        $owner = str_repeat('a', 64);
        $this->ownership->shouldReceive('acquire')->once()->with(321, self::ATTEMPT_KEY, $effect)
            ->andReturn(['outcome' => 'acquired', 'owner' => $owner]);
        $this->ownership->shouldReceive('start')->once()->with(321, self::ATTEMPT_KEY, $effect, $owner)->andReturn(true);
        $this->ownership->shouldNotReceive('markUncertain', 'releaseReserved');
        return $owner;
    }

    /** @return array<string, mixed> */
    private function envelope(string $state): array
    {
        return [
            'outcome' => $state === 'approved' ? 'success' : 'failure',
            'state' => $state === 'approved' ? 'terminal_success' : 'terminal_failure',
            'order_reference' => self::ORDER_REFERENCE,
            'eligibility' => null,
            'authorization' => null,
            'payments' => [[
                'reference' => 'synthetic_terminal_payment',
                'state' => $state,
                'amount' => ['value' => '125.50', 'currency' => 'BRL'],
            ]],
            'error' => $state === 'approved'
                ? null
                : ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_HIGH_RISK', 'retryable' => false],
        ];
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
        return $order;
    }

    private function forbidEffects($order): void
    {
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
    }

    /** @param array<string, mixed> $result */
    private function assertResponse(int $status, string $message, array $result): void
    {
        $this->assertSame(['status' => $status, 'message' => $message], $result);
        $encoded = (string) json_encode($result);
        foreach ([self::SECRET, self::ORDER_REFERENCE, self::ATTEMPT_KEY, 'WC-321', 'synthetic', 'SYNTHETIC'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    /** @return array<int, mixed> */
    private function rejected(string $category, string $reason = ''): array
    {
        $event = ['count', SmartPixMetricSchema::NOTIFICATION_REJECTED, $category, self::NOTIFICATION];
        if ($reason !== '') {
            $event[] = $reason;
        }
        return $event;
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
}

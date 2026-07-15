<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Subscriptions;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers as WCHelpers;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Hooks\Subscriptions;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration tests for the MIT (Merchant Initiated Transaction) flow.
 *
 * Covers subscription renewal: WCS hook -> AP v2 MIT -> payment status handling.
 * Uses inline Response mocks to simulate the AP v2 API.
 *
 * @spec feat-001 US-4, US-6 | DD-1, DD-11, DD-14
 * @covers \MercadoPago\Woocommerce\Hooks\Subscriptions::processSubscriptionRenewal
 * @covers \MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient::mit
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MitFlowTest extends TestCase
{
    private Subscriptions $hook;

    private Mockery\MockInterface $apClient;

    private Mockery\MockInterface $subscriptionsHelper;

    private Mockery\MockInterface $store;

    private Mockery\MockInterface $logs;

    private Mockery\MockInterface $orderMetadataMock;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->apClient = Mockery::mock(AutomaticPaymentsClient::class);
        $this->subscriptionsHelper = Mockery::mock(SubscriptionsHelper::class);
        $this->store = Mockery::mock(Store::class);
        $this->store->shouldReceive('getStoreName')->byDefault()->andReturn('Test Store');
        // buildMitPayload always builds the notification URL (custom-domain branch is
        // WC()-independent); no custom domain configured in these tests.
        $this->store->shouldReceive('getCustomDomain')->byDefault()->andReturn('');
        $this->store->shouldReceive('getCustomDomainOptions')->byDefault()->andReturn('no');

        $logFile = Mockery::mock(File::class);
        $logFile->shouldReceive('info')->byDefault();
        $logFile->shouldReceive('error')->byDefault();
        $logFile->shouldReceive('warning')->byDefault();

        $this->logs = Mockery::mock(Logs::class);
        $this->logs->file = $logFile;

        // Prevent add_action from being called
        WP_Mock::userFunction('is_admin')->andReturn(false);
        WP_Mock::userFunction('add_action')->byDefault();

        // Mock WP functions used by admin notices and buildMitPayload
        WP_Mock::userFunction('get_option')->byDefault()->andReturn([]);
        WP_Mock::userFunction('get_option')->with('_mp_store_identificator', 'WC-')->andReturn('WC-')->byDefault();
        WP_Mock::userFunction('update_option')->byDefault()->andReturnTrue();
        WP_Mock::userFunction('__')->byDefault()->andReturnUsing(fn($s) => $s);
        WP_Mock::userFunction('get_bloginfo')->byDefault()->andReturn('Test Store');
        WP_Mock::userFunction('get_site_url')->byDefault()->andReturn('https://store.example');
        WP_Mock::userFunction('WC')->byDefault()->andReturn(null);

        $countryMock = Mockery::mock(Country::class);
        $countryMock->shouldReceive('getCountryConfigs')->byDefault()->andReturn(['currency' => 'BRL']);
        $helpersMock = Mockery::mock(WCHelpers::class);
        $helpersMock->country = $countryMock;
        $helpersMock->currency = Mockery::mock(Currency::class);

        $this->orderMetadataMock = Mockery::mock(\MercadoPago\Woocommerce\Order\OrderMetadata::class);
        $this->orderMetadataMock->shouldReceive('setCustomMetadata')->byDefault();

        $this->hook = new Subscriptions(
            $this->apClient,
            $this->subscriptionsHelper,
            $this->store,
            $this->logs,
            $helpersMock,
            $this->orderMetadataMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function makeRenewalOrderMock(int $orderId): Mockery\MockInterface
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('update_status')->byDefault()->andReturnTrue();
        $order->shouldReceive('update_meta_data')->byDefault();
        $order->shouldReceive('save')->byDefault();
        $order->shouldReceive('payment_complete')->byDefault();
        return $order;
    }

    private function makeSubscriptionMock(int $subId): Mockery\MockInterface
    {
        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn($subId);
        return $subscription;
    }

    /**
     * AC-1: MIT approved -> payment_complete() called, metadata persisted.
     */
    public function testMitApprovedCompletesRenewalPayment(): void
    {
        $renewalOrderId  = 201;
        $subId           = 789;
        $mpSubscriptionId = 'CPP-WSUB-1001';
        $paymentId       = 'MOCK-PAY-MIT-2002';

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);
        $subscription = $this->makeSubscriptionMock($subId);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscription, '_mp_subscription_id', '')
            ->once()
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->once()
            ->andReturn('TEST-preapproval-token');

        $this->subscriptionsHelper
            ->shouldReceive('buildMitSeed')
            ->once()
            ->andReturn('seed-201');

        $this->subscriptionsHelper
            ->shouldReceive('generateIdempotencyKey')
            ->once()
            ->andReturn('idem-key-201');

        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->andReturn([
                'status'             => 201,
                'credential_revoked' => false,
                'data'               => [
                    'payment' => [
                        'id'     => $paymentId,
                        'status' => 'approved',
                    ],
                ],
            ]);

        $renewalOrder->shouldReceive('payment_complete')
            ->once()
            ->with($paymentId);

        // setCustomMetadata must be called once with the payment data before payment_complete,
        // so RefundHandler and the Sync button can read _Mercado_Pago_Payment_IDs immediately.
        $this->orderMetadataMock
            ->shouldReceive('setCustomMetadata')
            ->once()
            ->with($renewalOrder, Mockery::on(fn($data) => ($data['id'] ?? null) === $paymentId));

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->addToAssertionCount(1);
    }

    /**
     * When notification_url cannot be built (e.g. WC() not initialized in WP-Cron),
     * a warning is logged so a silently stuck renewal is diagnosable.
     */
    public function testMitLogsWarningWhenNotificationUrlCannotBeBuilt(): void
    {
        $renewalOrder = $this->makeRenewalOrderMock(205);
        $subscription = $this->makeSubscriptionMock(793);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscription, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-1005');
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')->andReturn('TEST-token');
        $this->subscriptionsHelper->shouldReceive('buildMitSeed')->andReturn('seed-205');
        $this->subscriptionsHelper->shouldReceive('generateIdempotencyKey')->andReturn('idem-205');

        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->andReturn([
                'status'             => 201,
                'credential_revoked' => false,
                'data'               => ['payment' => ['id' => 'PAY-205', 'status' => 'approved']],
            ]);

        // WC() is null by default here → notification_url cannot be built → warning expected.
        $this->logs->file
            ->shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/op=mit notification_url_missing.*wc_not_initialized/'), Mockery::any());

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->addToAssertionCount(1);
    }

    /**
     * P2 regression: external_reference must use the store-configured order-reference
     * prefix, not a hardcoded 'WC-'. A store with a custom _mp_store_identificator
     * (e.g. 'STORE-') must produce external_reference = 'STORE-' . renewalOrderId.
     */
    public function testMitPayloadUsesConfiguredExternalReferencePrefix(): void
    {
        $renewalOrderId = 210;

        WP_Mock::userFunction('get_option')
            ->with('_mp_store_identificator', 'WC-')
            ->andReturn('STORE-');

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);
        $subscription = $this->makeSubscriptionMock(794);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-210');
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')->andReturn('TEST-token');
        $this->subscriptionsHelper->shouldReceive('buildMitSeed')->andReturn('seed-210');
        $this->subscriptionsHelper->shouldReceive('generateIdempotencyKey')->andReturn('idem-210');

        $captured = null;
        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->with(Mockery::any(), Mockery::on(function ($payload) use (&$captured) {
                $captured = $payload;
                return true;
            }), Mockery::any())
            ->andReturn([
                'status'             => 201,
                'credential_revoked' => false,
                'data'               => ['payment' => ['id' => 'PAY-210', 'status' => 'approved']],
            ]);

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->assertSame('STORE-' . $renewalOrderId, $captured['transaction']['external_reference']);
    }

    /**
     * P1 happy-path: with a custom domain configured, the MIT notification_url carries
     * source_news=webhooks so the Core P&P can deliver the renewal webhook.
     */
    public function testMitPayloadNotificationUrlCarriesSourceNews(): void
    {
        $this->store->shouldReceive('getCustomDomain')->andReturn('https://webhook.example');
        $this->store->shouldReceive('getCustomDomainOptions')->andReturn('yes');

        $renewalOrder = $this->makeRenewalOrderMock(211);
        $subscription = $this->makeSubscriptionMock(795);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-211');
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')->andReturn('TEST-token');
        $this->subscriptionsHelper->shouldReceive('buildMitSeed')->andReturn('seed-211');
        $this->subscriptionsHelper->shouldReceive('generateIdempotencyKey')->andReturn('idem-211');

        $captured = null;
        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->with(Mockery::any(), Mockery::on(function ($payload) use (&$captured) {
                $captured = $payload;
                return true;
            }), Mockery::any())
            ->andReturn([
                'status'             => 201,
                'credential_revoked' => false,
                'data'               => ['payment' => ['id' => 'PAY-211', 'status' => 'approved']],
            ]);

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->assertArrayHasKey('notification_url', $captured);
        $this->assertStringContainsString('source_news=webhooks', $captured['notification_url']);
    }

    /**
     * AC-2: MIT rejected -> order status failed, WCS handles retry.
     */
    public function testMitRejectedFailsRenewalOrder(): void
    {
        $renewalOrderId   = 202;
        $subId            = 790;
        $mpSubscriptionId = 'CPP-WSUB-1002';

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);
        $subscription = $this->makeSubscriptionMock($subId);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('TEST-preapproval-token');

        $this->subscriptionsHelper
            ->shouldReceive('buildMitSeed')
            ->andReturn('seed-202');

        $this->subscriptionsHelper
            ->shouldReceive('generateIdempotencyKey')
            ->andReturn('idem-key-202');

        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->andReturn([
                'status'             => 422,
                'credential_revoked' => false,
                'data'               => [
                    'payment' => [
                        'id'     => null,
                        'status' => 'rejected',
                    ],
                    'error' => 'PaymentRejected',
                ],
            ]);

        $renewalOrder->shouldNotReceive('payment_complete');
        $renewalOrder->shouldReceive('update_status')
            ->once()
            ->with('failed', Mockery::any());

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Non-2xx error payload without data.payment.status -> failure note carries the
     * HTTP status and AP v2 error code (not an empty "status=").
     */
    public function testMitNon2xxFailureNoteCarriesHttpStatusAndError(): void
    {
        $renewalOrder = $this->makeRenewalOrderMock(206);
        $subscription = $this->makeSubscriptionMock(796);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-1006');
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')->andReturn('TEST-token');
        $this->subscriptionsHelper->shouldReceive('buildMitSeed')->andReturn('seed-206');
        $this->subscriptionsHelper->shouldReceive('generateIdempotencyKey')->andReturn('idem-key-206');

        // AP v2 error payload: no data.payment.status, only HTTP status + error code.
        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->andReturn([
                'status'             => 503,
                'credential_revoked' => false,
                'data'               => ['error' => 'UpstreamUnavailable'],
            ]);

        $renewalOrder->shouldNotReceive('payment_complete');
        $renewalOrder->shouldReceive('update_status')
            ->once()
            ->with('failed', Mockery::pattern('/http_status=503 error=UpstreamUnavailable/'));

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-3: MIT 401/403 -> credential_revoked notice queued.
     */
    public function testMitCredentialRevokedQueuesAdminNotice(): void
    {
        $renewalOrderId   = 203;
        $subId            = 791;
        $mpSubscriptionId = 'CPP-WSUB-1003';

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);
        $subscription = $this->makeSubscriptionMock($subId);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn($mpSubscriptionId);

        $this->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('TEST-preapproval-token');

        $this->subscriptionsHelper
            ->shouldReceive('buildMitSeed')
            ->andReturn('seed-203');

        $this->subscriptionsHelper
            ->shouldReceive('generateIdempotencyKey')
            ->andReturn('idem-key-203');

        $this->apClient
            ->shouldReceive('mit')
            ->once()
            ->andReturn([
                'status'             => 401,
                'credential_revoked' => true,
                'data'               => [],
            ]);

        $renewalOrder->shouldNotReceive('payment_complete');
        $renewalOrder->shouldReceive('update_status')
            ->once()
            ->with('failed', Mockery::any());

        // Verify that update_option is called with credential_revoked flag
        WP_Mock::userFunction('update_option')
            ->once()
            ->with(
                Subscriptions::OPTION_NOTICES,
                Mockery::on(fn($v) => isset($v['credential_revoked']) && $v['credential_revoked'] === true),
                false
            )
            ->andReturnTrue();

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->expectNotToPerformAssertions();
    }

    /**
     * AC-4: MIT with missing _mp_subscription_id -> orphan notice queued, renewal fails.
     */
    public function testMitMissingSubscriptionIdQueuesOrphanNotice(): void
    {
        $renewalOrderId = 204;
        $subId          = 792;

        $renewalOrder = $this->makeRenewalOrderMock($renewalOrderId);
        $subscription = $this->makeSubscriptionMock($subId);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscription],
        ]);

        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscription, '_mp_subscription_id', '')
            ->once()
            ->andReturn('');

        $this->apClient->shouldNotReceive('mit');

        $renewalOrder->shouldNotReceive('payment_complete');
        $renewalOrder->shouldReceive('update_status')
            ->once()
            ->with('failed', Mockery::any());

        // Verify that update_option is called with orphan_orders
        WP_Mock::userFunction('update_option')
            ->once()
            ->with(
                Subscriptions::OPTION_NOTICES,
                Mockery::on(fn($v) => isset($v['orphan_orders'][$renewalOrderId])),
                false
            )
            ->andReturnTrue();

        $this->hook->processSubscriptionRenewal(49.90, $renewalOrder);

        $this->expectNotToPerformAssertions();
    }
}

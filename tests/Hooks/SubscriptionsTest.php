<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers as WCHelpers;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Tests\Mocks\TestableSubscriptionsHook;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;

class SubscriptionsTest extends TestCase
{
    /**
     * @var Mockery\MockInterface|AutomaticPaymentsClient
     */
    private $apClientMock;

    /**
     * @var Mockery\MockInterface|SubscriptionsHelper
     */
    private $subscriptionsHelperMock;

    /**
     * @var Mockery\MockInterface|Store
     */
    private $storeMock;

    /**
     * @var Mockery\MockInterface|Logs
     */
    private $logsMock;

    /**
     * @var Mockery\MockInterface|File
     */
    private $fileTransportMock;

    /**
     * @var Mockery\MockInterface|WCHelpers
     */
    private $helpersMock;

    /**
     * @var Mockery\MockInterface|OrderMetadata
     */
    private $orderMetadataMock;

    public function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('is_admin', ['return' => false]);
        // buildMitPayload() reads the configured order-reference prefix and always builds
        // the notification URL (custom-domain branch is WC()-independent).
        WP_Mock::userFunction('get_option')->with('_mp_store_identificator', 'WC-')->andReturn('WC-');
        WP_Mock::userFunction('get_site_url')->byDefault()->andReturn('https://store.example');

        $this->apClientMock            = Mockery::mock(AutomaticPaymentsClient::class);
        $this->subscriptionsHelperMock = Mockery::mock(SubscriptionsHelper::class);
        $this->storeMock               = Mockery::mock(Store::class);
        $this->storeMock->shouldReceive('getStoreName')->byDefault()->andReturn('My Store');
        $this->storeMock->shouldReceive('getCustomDomain')->byDefault()->andReturn('');
        $this->storeMock->shouldReceive('getCustomDomainOptions')->byDefault()->andReturn('no');
        $this->fileTransportMock       = Mockery::mock(File::class);

        $this->logsMock         = Mockery::mock(Logs::class);
        $this->logsMock->file   = $this->fileTransportMock;
        $this->logsMock->remote = Mockery::mock(Remote::class);

        // MIT logs a warning when notification_url cannot be built (WC() null in unit tests).
        $this->fileTransportMock->shouldReceive('warning')->byDefault();

        $countryMock = Mockery::mock(Country::class);
        $countryMock->shouldReceive('getCountryConfigs')->byDefault()->andReturn(['currency' => 'BRL']);

        $this->helpersMock          = Mockery::mock(WCHelpers::class);
        $this->helpersMock->country = $countryMock;
        $this->helpersMock->currency = Mockery::mock(Currency::class);

        $this->orderMetadataMock = Mockery::mock(OrderMetadata::class);
        $this->orderMetadataMock->shouldReceive('setCustomMetadata')->byDefault();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    private function buildHook(): TestableSubscriptionsHook
    {
        // WC_Subscriptions is not defined in the test bootstrap, so
        // SubscriptionsHelper::isWcsActive() returns false and registerHooks()
        // is never called — no add_action expectations needed in behaviour tests.
        return new TestableSubscriptionsHook(
            $this->apClientMock,
            $this->subscriptionsHelperMock,
            $this->storeMock,
            $this->logsMock,
            $this->helpersMock,
            $this->orderMetadataMock
        );
    }

    /* ───────── hooks registration ───────── */

    public function testHooksNotRegisteredWhenWcsIsAbsent(): void
    {
        // WC_Subscriptions is not defined → SubscriptionsHelper::isWcsActive()
        // returns false → registerHooks() is never called → no add_action expected.
        $hook = $this->buildHook();

        $this->assertInstanceOf(TestableSubscriptionsHook::class, $hook);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBothHooksRegisteredWhenWcsIsActive(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order, $type = "any") { return true; }');
        }

        WP_Mock::expectActionAdded(
            'woocommerce_scheduled_subscription_payment_woo-mercado-pago-custom',
            [new \WP_Mock\Matcher\AnyInstance(TestableSubscriptionsHook::class), 'processSubscriptionRenewal'],
            10,
            2
        );
        WP_Mock::expectActionAdded(
            'woocommerce_subscription_cancelled_woo-mercado-pago-custom',
            [new \WP_Mock\Matcher\AnyInstance(TestableSubscriptionsHook::class), 'onSubscriptionCancelled'],
            10,
            1
        );

        $hook = new TestableSubscriptionsHook(
            $this->apClientMock,
            $this->subscriptionsHelperMock,
            $this->storeMock,
            $this->logsMock,
            $this->helpersMock,
            $this->orderMetadataMock
        );

        $this->assertInstanceOf(TestableSubscriptionsHook::class, $hook);
    }

    /* ─── processSubscriptionRenewal — orphan (missing subscription_id) ─── */

    public function testRenewalFailsWithOrphanNoticeWhenSubscriptionIdMissing(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(42);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', [
            'return' => [$subscriptionMock],
        ]);
        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', [
            'args'   => [
                TestableSubscriptionsHook::OPTION_NOTICES,
                Mockery::on(fn($val) => isset($val['orphan_orders'][42])),
                false,
            ],
            'return' => true,
        ]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/subscription_id_missing/'), AutomaticPaymentsClient::LOG_SOURCE);

        $hook->processSubscriptionRenewal(10.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── processSubscriptionRenewal — empty access token → abort ─── */

    public function testRenewalAbortsWithErrorWhenAccessTokenNotConfigured(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(46);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-abc');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/missing_access_token/'), AutomaticPaymentsClient::LOG_SOURCE);

        // mit() must NOT be called when the token is missing.
        $this->apClientMock->shouldNotReceive('mit');

        $hook->processSubscriptionRenewal(10.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── processSubscriptionRenewal — 401/403 → credential revoked ─── */

    public function testRenewalFailsWithCredentialNoticeOn401(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(43);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));
        $renewalOrderMock->shouldReceive('get_total')->andReturn('15.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(7);
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-abc');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:43:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-abc');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', ['return' => true]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->apClientMock->shouldReceive('mit')->once()
            ->andReturn(['status' => 401, 'credential_revoked' => true, 'data' => []]);

        $hook->processSubscriptionRenewal(15.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── processSubscriptionRenewal — approved MIT ─── */

    public function testApprovedMitCallsPaymentComplete(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(45);
        $renewalOrderMock->shouldReceive('get_total')->andReturn('25.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_meta_data')->byDefault();
        $renewalOrderMock->shouldReceive('payment_complete')->once()->with('99001122');

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(9);
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-ghi');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:45:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-ghi');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->apClientMock->shouldReceive('mit')->once()->andReturn([
            'status'             => 201,
            'credential_revoked' => false,
            'data'               => ['payment' => ['id' => '99001122', 'status' => 'approved']],
        ]);

        $hook->processSubscriptionRenewal(25.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /**
     * Renewal retry edge case: when _Mercado_Pago_Payment_IDs already exists (e.g. from a
     * previous attempt), the overwrite must replace it with the new payment ID so
     * RefundHandler always operates on the correct charge.
     */
    public function testApprovedMitOverwritesStalePaymentIdOnRetry(): void
    {
        $hook     = $this->buildHook();
        $newPayId = 'NEW-PAY-99';

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(46);
        $renewalOrderMock->shouldReceive('get_total')->andReturn('30.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('payment_complete')->once()->with($newPayId);

        // Assert overwrite: update_meta_data must be called with the new payment ID.
        $renewalOrderMock->shouldReceive('update_meta_data')
            ->once()
            ->with('_Mercado_Pago_Payment_IDs', $newPayId);

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(10);
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-retry');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:46:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-retry');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->apClientMock->shouldReceive('mit')->once()->andReturn([
            'status'             => 201,
            'credential_revoked' => false,
            'data'               => ['payment' => ['id' => $newPayId, 'status' => 'approved']],
        ]);

        $hook->processSubscriptionRenewal(30.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── AC 4: cancel with 404 → silent ok (WCS not blocked) ─── */

    public function testCancelWithNotFoundIsOkSilencioso(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-xyz');

        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        $this->apClientMock
            ->shouldReceive('deleteSubscription')
            ->once()
            ->with('TOKEN', 'CPP-WSUB-xyz')
            ->andReturn(['status' => 404, 'success' => false, 'not_found' => true]);

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── AC 4: cancel with 5xx → log + continue (WCS not blocked) ─── */

    public function testCancelWith5xxLogsAndContinues(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('CPP-WSUB-xyz');

        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        $this->apClientMock
            ->shouldReceive('deleteSubscription')
            ->once()
            ->andReturn(['status' => 500, 'success' => false, 'not_found' => false]);

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── AC 4: cancel with missing subscription_id → warn + return ─── */

    public function testCancelWithMissingSubscriptionIdLogsWarningAndReturns(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->with($subscriptionMock, '_mp_subscription_id', '')
            ->andReturn('');

        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/subscription_id_missing/'), AutomaticPaymentsClient::LOG_SOURCE);

        $this->apClientMock->shouldNotReceive('deleteSubscription');

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── processSubscriptionRenewal — transport exception → fail renewal ─── */

    public function testRenewalFailsGracefullyOnTransportException(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(50);
        $renewalOrderMock->shouldReceive('get_total')->andReturn('10.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(10);
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-exc');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:50:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-exc');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->apClientMock->shouldReceive('mit')->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/transport_error/'), AutomaticPaymentsClient::LOG_SOURCE);

        $hook->processSubscriptionRenewal(10.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── processSubscriptionRenewal — rejected payment → fail renewal ─── */

    public function testRenewalFailsWhenPaymentIsRejected(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(51);
        $renewalOrderMock->shouldReceive('get_total')->andReturn('10.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(11);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-rej');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:51:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-rej');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        // 422 — payment rejected, not a credential issue.
        $this->apClientMock->shouldReceive('mit')->once()->andReturn([
            'status'             => 422,
            'credential_revoked' => false,
            'data'               => ['payment' => ['status' => 'rejected']],
        ]);

        $hook->processSubscriptionRenewal(10.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── onSubscriptionCancelled — transport exception → log + continue ─── */

    public function testCancelContinuesOnTransportException(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-exc');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        $this->apClientMock->shouldReceive('deleteSubscription')->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/transport_error/'), AutomaticPaymentsClient::LOG_SOURCE);

        // WCS cancellation must not be blocked — no exception propagated.
        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── onSubscriptionCancelled — 401 → credential revoked notice ─── */

    public function testCancelWith401QueuesCredentialNotice(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-xyz');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', ['return' => true]);

        $this->apClientMock->shouldReceive('deleteSubscription')->once()
            ->andReturn(['status' => 401, 'success' => false, 'not_found' => false]);

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── G-08: processSubscriptionRenewal — 403 → credential revoked ─── */

    public function testRenewalFailsWithCredentialNoticeOn403(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(44);
        $renewalOrderMock->shouldReceive('get_total')->andReturn('20.00');
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(8);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-def');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:44:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-403');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', ['return' => true]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->apClientMock->shouldReceive('mit')->once()
            ->andReturn(['status' => 403, 'credential_revoked' => true, 'data' => []]);

        $hook->processSubscriptionRenewal(20.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── G-11: processSubscriptionRenewal — empty subscriptions array → orphan ─── */

    public function testRenewalFailsWithOrphanNoticeWhenSubscriptionsEmpty(): void
    {
        $hook = $this->buildHook();

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(47);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => []]);
        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', [
            'args'   => [
                TestableSubscriptionsHook::OPTION_NOTICES,
                Mockery::on(fn($val) => isset($val['orphan_orders'][47])),
                false,
            ],
            'return' => true,
        ]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        $this->fileTransportMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/subscription_id_missing/'), AutomaticPaymentsClient::LOG_SOURCE);

        $this->apClientMock->shouldNotReceive('mit');

        $hook->processSubscriptionRenewal(10.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }

    /* ─── G-09: onSubscriptionCancelled — 204 success ─── */

    public function testCancelWith204Succeeds(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-ok');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        $this->apClientMock
            ->shouldReceive('deleteSubscription')
            ->once()
            ->with('TOKEN', 'CPP-WSUB-ok')
            ->andReturn(['status' => 204, 'success' => true, 'not_found' => false]);

        // No error or warning logged on success.
        $this->fileTransportMock->shouldNotReceive('error');
        $this->fileTransportMock->shouldNotReceive('warning');

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── G-10: onSubscriptionCancelled — 403 → credential revoked notice ─── */

    public function testCancelWith403QueuesCredentialNotice(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-xyz');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');

        WP_Mock::userFunction('get_option', ['return' => []]);
        WP_Mock::userFunction('update_option', ['return' => true]);

        $this->apClientMock->shouldReceive('deleteSubscription')->once()
            ->andReturn(['status' => 403, 'success' => false, 'not_found' => false]);

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── G-01: onSubscriptionCancelled — empty access token → abort ─── */

    public function testCancelAbortsWhenAccessTokenNotConfigured(): void
    {
        $hook = $this->buildHook();

        $subscriptionMock = Mockery::mock('WC_Subscription');
        // onSubscriptionCancelled only proceeds when the subscription is truly cancelled.
        $subscriptionMock->shouldReceive('get_status')->andReturn('cancelled');
        $this->subscriptionsHelperMock
            ->shouldReceive('getSubscriptionMeta')
            ->andReturn('CPP-WSUB-abc');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('');

        $this->fileTransportMock
            ->shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/missing_access_token/'), AutomaticPaymentsClient::LOG_SOURCE);

        // deleteSubscription must NOT be called when token is missing.
        $this->apClientMock->shouldNotReceive('deleteSubscription');

        $hook->onSubscriptionCancelled($subscriptionMock);

        $this->addToAssertionCount(1);
    }

    /* ─── displayAdminNotices shows credential-revoked notice ─── */

    public function testDisplayAdminNoticesShowsCredentialRevokedNotice(): void
    {
        $hook = $this->buildHook();

        WP_Mock::userFunction('get_option', [
            'args'   => [TestableSubscriptionsHook::OPTION_NOTICES, []],
            'return' => ['credential_revoked' => true],
        ]);
        WP_Mock::userFunction('esc_html', ['return_arg' => 0]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        ob_start();
        $hook->displayAdminNotices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString('Pre-approval', $output);
    }

    /* ─── displayAdminNotices shows orphan count ─── */

    public function testDisplayAdminNoticesShowsOrphanCountNotice(): void
    {
        $hook = $this->buildHook();

        WP_Mock::userFunction('get_option', [
            'args'   => [TestableSubscriptionsHook::OPTION_NOTICES, []],
            'return' => ['orphan_orders' => [101 => true, 102 => true, 103 => true]],
        ]);
        WP_Mock::userFunction('esc_html', ['return_arg' => 0]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        ob_start();
        $hook->displayAdminNotices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString('3', $output);
    }

    /* ─── clearCredentialRevokedNotice removes the flag ─── */

    public function testClearCredentialRevokedNoticeRemovesFlag(): void
    {
        WP_Mock::userFunction('get_option', [
            'args'   => [TestableSubscriptionsHook::OPTION_NOTICES, []],
            'return' => ['credential_revoked' => true, 'orphan_orders' => [101 => true, 102 => true]],
        ]);
        WP_Mock::userFunction('update_option', [
            'args'   => [
                TestableSubscriptionsHook::OPTION_NOTICES,
                Mockery::on(function ($val) {
                    return !isset($val['credential_revoked']) && count($val['orphan_orders'] ?? []) === 2;
                }),
                false,
            ],
            'return' => true,
        ]);

        \MercadoPago\Woocommerce\Hooks\Subscriptions::clearCredentialRevokedNotice();

        $this->addToAssertionCount(1);
    }

    /* ─── queueCredentialRevokedNotice sets the flag ─── */

    public function testQueueCredentialRevokedNoticeSetsFlag(): void
    {
        WP_Mock::userFunction('get_option', [
            'args'   => [TestableSubscriptionsHook::OPTION_NOTICES, []],
            'return' => [],
        ]);
        WP_Mock::userFunction('update_option', [
            'args'   => [
                TestableSubscriptionsHook::OPTION_NOTICES,
                Mockery::on(fn($val) => ($val['credential_revoked'] ?? false) === true),
                false,
            ],
            'return' => true,
        ]);

        \MercadoPago\Woocommerce\Hooks\Subscriptions::queueCredentialRevokedNotice();

        $this->addToAssertionCount(1);
    }

    /* ─── queueOrphanNotice tracks unique renewal orders (Set semantics) ─── */

    public function testQueueOrphanNoticeTracksUniqueRenewalOrders(): void
    {
        // Pre-existing orphan for order 101. Adding order 102 should produce 2 entries.
        WP_Mock::userFunction('get_option', [
            'args'   => [TestableSubscriptionsHook::OPTION_NOTICES, []],
            'return' => ['orphan_orders' => [101 => true]],
        ]);
        WP_Mock::userFunction('update_option', [
            'args'   => [
                TestableSubscriptionsHook::OPTION_NOTICES,
                Mockery::on(fn($val) => count($val['orphan_orders'] ?? []) === 2 && isset($val['orphan_orders'][102])),
                false,
            ],
            'return' => true,
        ]);

        \MercadoPago\Woocommerce\Hooks\Subscriptions::queueOrphanNotice(102);

        $this->addToAssertionCount(1);
    }

    /* ─── PSW-4208: MIT payload uses MP account currency with ratio ─── */

    public function testMitPayloadUsesConvertedAmountWhenCurrenciesDiffer(): void
    {
        $capturedPayload = null;

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(60);
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('USD');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_meta_data')->byDefault();
        $renewalOrderMock->shouldReceive('payment_complete')->once()->with('PAY-60');

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(15);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-usd');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:60:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-usd');

        $this->helpersMock->country->shouldReceive('getCountryConfigs')->andReturn(['currency' => 'BRL']);
        $this->helpersMock->currency->shouldReceive('getCurrentRatio')->once()->andReturn(5.0);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->apClientMock->shouldReceive('mit')->once()
            ->andReturnUsing(function ($token, $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return [
                    'status'             => 201,
                    'credential_revoked' => false,
                    'data'               => ['payment' => ['id' => 'PAY-60', 'status' => 'approved']],
                ];
            });

        $hook = $this->buildHook();
        $hook->processSubscriptionRenewal(100.0, $renewalOrderMock);

        $this->assertSame('BRL', $capturedPayload['transaction']['currency']);
        $this->assertSame(500.0, $capturedPayload['transaction']['amount']);
    }

    public function testMitPayloadNormalizesAmountDecimalsWhenCurrenciesDiffer(): void
    {
        $capturedPayload = null;

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(63);
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('USD');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_meta_data')->byDefault();
        $renewalOrderMock->shouldReceive('payment_complete')->once()->with('PAY-63');

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(18);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-dec');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:63:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-dec');

        $this->helpersMock->country->shouldReceive('getCountryConfigs')->andReturn(['currency' => 'BRL']);
        // 29.90 * 0.211 = 6.3089 → calculateByCurrency devolve 6.3089 (4 casas).
        // Deve ser normalizado para 6.31 (2 casas), igual ao CIT — não enviar o valor cru.
        $this->helpersMock->currency->shouldReceive('getCurrentRatio')->once()->andReturn(0.211);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->apClientMock->shouldReceive('mit')->once()
            ->andReturnUsing(function ($token, $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return [
                    'status'             => 201,
                    'credential_revoked' => false,
                    'data'               => ['payment' => ['id' => 'PAY-63', 'status' => 'approved']],
                ];
            });

        $hook = $this->buildHook();
        $hook->processSubscriptionRenewal(29.90, $renewalOrderMock);

        $this->assertSame('BRL', $capturedPayload['transaction']['currency']);
        $this->assertSame(6.31, $capturedPayload['transaction']['amount']);
    }

    public function testMitPayloadKeepsAmountUnchangedWhenCurrenciesMatch(): void
    {
        $capturedPayload = null;

        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(61);
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('BRL');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_meta_data')->byDefault();
        $renewalOrderMock->shouldReceive('payment_complete')->once()->with('PAY-61');

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(16);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-brl');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:61:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-brl');

        $this->helpersMock->currency->shouldNotReceive('getCurrentRatio');

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('get_bloginfo', ['return' => 'My Store']);
        WP_Mock::userFunction('__', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['return' => null]);

        $this->apClientMock->shouldReceive('mit')->once()
            ->andReturnUsing(function ($token, $payload) use (&$capturedPayload) {
                $capturedPayload = $payload;
                return [
                    'status'             => 201,
                    'credential_revoked' => false,
                    'data'               => ['payment' => ['id' => 'PAY-61', 'status' => 'approved']],
                ];
            });

        $hook = $this->buildHook();
        $hook->processSubscriptionRenewal(200.0, $renewalOrderMock);

        $this->assertSame('BRL', $capturedPayload['transaction']['currency']);
        $this->assertSame(200.0, $capturedPayload['transaction']['amount']);
    }

    public function testMitFailsRenewalWhenRatioApiFails(): void
    {
        $renewalOrderMock = Mockery::mock('WC_Order');
        $renewalOrderMock->shouldReceive('get_id')->andReturn(62);
        $renewalOrderMock->shouldReceive('get_currency')->andReturn('USD');
        $renewalOrderMock->shouldReceive('get_items')->andReturn([]);
        $renewalOrderMock->shouldReceive('update_status')->once()->with('failed', Mockery::type('string'));

        $subscriptionMock = Mockery::mock('WC_Subscription');
        $subscriptionMock->shouldReceive('get_id')->andReturn(17);
        $this->subscriptionsHelperMock->shouldReceive('getSubscriptionMeta')->andReturn('CPP-WSUB-fail');
        $this->subscriptionsHelperMock->shouldReceive('resolveAccessToken')->andReturn('TOKEN');
        $this->subscriptionsHelperMock->shouldReceive('buildMitSeed')->andReturn('mit:62:0');
        $this->subscriptionsHelperMock->shouldReceive('generateIdempotencyKey')->andReturn('idem-fail');

        $this->helpersMock->country->shouldReceive('getCountryConfigs')->andReturn(['currency' => 'BRL']);
        $this->helpersMock->currency->shouldReceive('getCurrentRatio')->once()
            ->andThrow(new \Exception('ratio API unavailable'));

        $this->fileTransportMock->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/ratio_fetch_failed/'), AutomaticPaymentsClient::LOG_SOURCE);

        WP_Mock::userFunction('wcs_get_subscriptions_for_renewal_order', ['return' => [$subscriptionMock]]);
        WP_Mock::userFunction('__', ['return_arg' => 0]);

        // mit() must NOT be called — renewal is aborted before reaching the AP v2 call
        $this->apClientMock->shouldNotReceive('mit');

        $hook = $this->buildHook();
        $hook->processSubscriptionRenewal(100.0, $renewalOrderMock);

        $this->addToAssertionCount(1);
    }
}

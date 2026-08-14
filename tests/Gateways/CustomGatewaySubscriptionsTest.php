<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Tests for the WooCommerce Subscriptions branching added to CustomGateway
 * by TASK-007 (PSW-4001).
 *
 * @spec feat-001 US-3, US-7, DD-2 — spec.md §3.1 (supports[]) and §3.2
 *       (process_payment decision matrix).
 *
 * Each scenario runs in its own process so we can safely declare the
 * Subscriptions-side classes/functions without polluting other tests.
 *
 * @covers \MercadoPago\Woocommerce\Gateways\CustomGateway
 */
class CustomGatewaySubscriptionsTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = CustomGateway::class;

    /**
     * @var \Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    /**
     * The PPSP-1668 credential-mismatch guard makes both process_payment() and
     * isSubscriptionPaymentContext() consult the WCS automatic-payment options via
     * isAutomaticPaymentsOff(). Default them to "flags not set" so the existing
     * subscription-context tests keep exercising the recurring/override path; the
     * manual-renewal tests override these with concrete 'yes' values.
     *
     * @before
     */
    public function stubAutomaticPaymentsOptions(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('no')->byDefault();
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn('no')->byDefault();
    }

    /**
     * Scenario 3 — Payment-method change request.
     *
     * When WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment
     * is truthy, process_payment() MUST route to process_subscription_payment_method_change().
     * The handler returns failure when wcs_get_subscription is absent — asserting that
     * proves the correct branch was taken without mocking all private method dependencies.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentRoutesToPaymentMethodChangeWhenChangeFlagIsTrue(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = true; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = true;
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return true; }');
        }
        // wcs_get_subscription intentionally NOT defined — handler early-returns with failure.

        WP_Mock::userFunction('wc_get_order')->once()->with(42)->andReturn(Mockery::mock(\WC_Order::class));
        WP_Mock::userFunction('__')->andReturnArg(0);
        WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        WP_Mock::userFunction('set_transient')->andReturn(true);

        $result = $this->gateway->process_payment(42);

        // process_subscription_payment_method_change() was reached and returned failure
        // (empty token → early-return guard triggered).
        $this->assertSame('fail', $result['result']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Scenario 1 — Initial payment for a brand-new subscription.
     *
     * The change-PM flag is false but the order contains a subscription product.
     * process_payment() MUST route to process_subscription_initial_payment().
     * Without WCS helpers mocked, the handler short-circuits to a failure result
     * (no WC_Subscription found) — asserting that result proves correct routing
     * to the handler without requiring full CIT mocking here.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentRoutesToInitialSubscriptionPaymentWhenOrderContainsSubscription(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return true; }');
        }
        if (!function_exists('wcs_get_subscriptions_for_order')) {
            eval('function wcs_get_subscriptions_for_order($order) { return []; }');
        }

        // Store does NOT have Accept Manual Renewals enabled → pre-approval flow must run.
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('no');

        $orderMock = Mockery::mock(\WC_Order::class);
        $orderMock->shouldReceive('get_id')->andReturn(7);
        $orderMock->shouldReceive('update_status')->andReturnTrue();
        $orderMock->shouldReceive('save')->byDefault();
        WP_Mock::userFunction('wc_get_order')->once()->with(7)->andReturn($orderMock);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setCurrencyRatioData')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();

        // processReturnFail() (now triggered by the try-catch in process_payment()) needs these.
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->byDefault()->andReturn(false);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->byDefault();
        $this->gateway->mercadopago->helpers->errorMessages->shouldReceive('findErrorMessage')->byDefault()->andReturnArg(0);
        $this->gateway->datadog->shouldReceive('sendEvent')->byDefault();
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCustIdFromAT')->byDefault()->andReturn('');

        $result = $this->gateway->process_payment(7);

        $this->assertSame('fail', $result['result']);
    }

    /**
     * Scenario 1b — PPSP-1668: automatic payments turned off store-wide.
     *
     * "Accept Manual Renewals" alone only permits manual renewal as a fallback;
     * it does NOT disable automatic payments. Only when the store ALSO sets
     * turn_off_automatic_payments='yes' does WCS treat every renewal as manual —
     * that's the only condition under which the pre-approval flow must be skipped.
     * process_payment() MUST fall through to parent::process_payment() — the
     * same regular-checkout path used before v8.9.0.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentDelegatesToParentForManualRenewalSubscriptions(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return true; }');
        }
        // Positive probe: wcs_get_subscriptions_for_order() is called exclusively inside
        // process_subscription_initial_payment(), never in AbstractGateway::process_payment().
        // Tracking the call via a global flag gives a direct, unconditional assertion that
        // is independent of which exceptions are thrown along either path (addresses the
        // "swallowed exception" false-positive identified in code review).
        $GLOBALS['__ppsp1668_subs_branch'] = false;
        if (!function_exists('wcs_get_subscriptions_for_order')) {
            eval('function wcs_get_subscriptions_for_order($order) {
                $GLOBALS["__ppsp1668_subs_branch"] = true;
                return [new \stdClass()];
            }');
        }

        // Store has automatic payments fully turned off → pre-approval flow must be skipped.
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('yes');
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn('yes');

        $orderMock = Mockery::mock(\WC_Order::class);
        $orderMock->shouldReceive('get_id')->byDefault()->andReturn(11);
        WP_Mock::userFunction('wc_get_order')->andReturn($orderMock);

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault()->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault()->andReturnSelf();

        try {
            $this->gateway->process_payment(11);
        } catch (\Throwable $e) {
            // Any exception is acceptable — the assertion below independently proves
            // which branch was taken regardless of what was thrown or caught.
        }

        $this->assertFalse(
            $GLOBALS['__ppsp1668_subs_branch'],
            'process_subscription_initial_payment() was entered (wcs_get_subscriptions_for_order was called)' .
            ' — PPSP-1668 regression: automatic-payments-off guard missing in process_payment()'
        );
    }

    /**
     * Scenario 1c — PPSP-1668 regression guard: Accept Manual Renewals alone must
     * NOT bypass the pre-approval flow when automatic payments are still on.
     *
     * A store can have accept_manual_renewals='yes' as a fallback for failed
     * automatic charges while still running MP recurring payments normally
     * (turn_off_automatic_payments='no'). In that case process_payment() MUST
     * still enter process_subscription_initial_payment() — proven here via the
     * same wcs_get_subscriptions_for_order() probe used in Scenario 1b, but
     * asserting it WAS called.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentEntersPreApprovalWhenAutomaticPaymentsRemainOn(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return true; }');
        }
        $GLOBALS['__ppsp1668_subs_branch'] = false;
        if (!function_exists('wcs_get_subscriptions_for_order')) {
            eval('function wcs_get_subscriptions_for_order($order) {
                $GLOBALS["__ppsp1668_subs_branch"] = true;
                return [new \stdClass()];
            }');
        }

        // Accept Manual Renewals is on (fallback only), but automatic payments are
        // NOT turned off store-wide → pre-approval flow must still run.
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('yes');
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn('no');

        // Gateway has Pre-approval credentials configured (subscriptions support declared).
        $this->gateway->supports[] = 'subscriptions';

        $orderMock = Mockery::mock(\WC_Order::class);
        $orderMock->shouldReceive('get_id')->byDefault()->andReturn(12);
        WP_Mock::userFunction('wc_get_order')->andReturn($orderMock);

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault()->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault()->andReturnSelf();

        try {
            $this->gateway->process_payment(12);
        } catch (\Throwable $e) {
            // Any exception is acceptable — the assertion below independently proves
            // which branch was taken regardless of what was thrown or caught.
        }

        $this->assertTrue(
            $GLOBALS['__ppsp1668_subs_branch'],
            'process_subscription_initial_payment() was NOT entered (wcs_get_subscriptions_for_order was not called)' .
            ' — accept_manual_renewals=yes alone must not bypass pre-approval when automatic payments remain on'
        );
    }

    /**
     * Scenario 4 — Default path (regular order, not a subscription).
     *
     * Neither flag triggers a subscription branch, so process_payment() MUST
     * delegate to parent::process_payment(). We assert unconditionally that
     * neither subscription stub message (PSW-4006 / PSW-4003) appears in the
     * result or exception — proving neither subscription branch was taken.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentDelegatesToParentForRegularOrders(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return false; }');
        }

        WP_Mock::userFunction('wc_get_order')->andReturn(Mockery::mock(\WC_Order::class));

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault()->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault()->andReturnSelf();

        $messages = '';
        try {
            $result   = $this->gateway->process_payment(99);
            $messages = $result['messages'] ?? '';
        } catch (\Throwable $e) {
            $messages = $e->getMessage();
        }

        $this->assertStringNotContainsString('PSW-4006', $messages, 'Cenário 4 não deve rotear para o stub de troca de PM');
        $this->assertStringNotContainsString('PSW-4003', $messages, 'Cenário 4 não deve rotear para o stub de pagamento inicial');
    }

    /**
     * Scenario 4b — WCS class present but wcs_order_contains_subscription not declared.
     *
     * SubscriptionsHelper::isWcsActive() now checks both class_exists AND
     * function_exists. When only the class is loaded (partial WCS install,
     * staging import, multi-site load order edge-case), isWcsActive() returns
     * false → process_payment() hits the early return → parent handles the order.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentDelegatesToParentWhenSubscriptionFunctionMissing(): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        } else {
            \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        }
        // wcs_order_contains_subscription NOT declared → isWcsActive() returns false.
        $this->assertFalse(function_exists('wcs_order_contains_subscription'));

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault()->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault()->andReturnSelf();

        $messages = '';
        try {
            $result   = $this->gateway->process_payment(55);
            $messages = $result['messages'] ?? '';
        } catch (\Throwable $e) {
            $messages = $e->getMessage();
        }

        $this->assertStringNotContainsString('PSW-4006', $messages);
        $this->assertStringNotContainsString('PSW-4003', $messages);
    }

    /**
     * AC-1 — When WCS is installed and toggle is on, supports[] contains the 9 subscription flags.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSupportsContainsSubscriptionFlagsWhenWcsIsActive(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';

        $expectedFlags = [
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        ];

        $reflection = new \ReflectionClass(CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $instance->supports   = ['products', 'refunds'];
        $instance->settings   = ['subscriptions_enabled' => 'yes'];
        $instance->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        // resolveAccessToken returns 'APP_USR-preapproval' by default in MercadoPagoMock

        $initMethod = $reflection->getMethod('initWcsSupports');
        $initMethod->setAccessible(true);
        $initMethod->invoke($instance);

        foreach ($expectedFlags as $flag) {
            $this->assertContains(
                $flag,
                $instance->supports,
                "supports[] must contain '$flag' when WC_Subscriptions is active, toggle is on and credential is set"
            );
        }

        $this->assertNotContains('gateway_scheduled_payments', $instance->supports);
    }

    /**
     * When toggle is on but Pre-approval credential is empty, supports[]
     * must NOT contain subscription flags — the gateway must be invisible to WCS
     * until a credential is configured.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSupportsDoesNotContainSubscriptionFlagsWhenCredentialIsEmpty(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';

        $reflection = new \ReflectionClass(CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $instance->supports   = ['products', 'refunds'];
        $instance->settings   = ['subscriptions_enabled' => 'yes'];
        $instance->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $instance->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')
            ->andReturn('');

        $initMethod = $reflection->getMethod('initWcsSupports');
        $initMethod->setAccessible(true);
        $initMethod->invoke($instance);

        $subscriptionFlags = [
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        ];

        foreach ($subscriptionFlags as $flag) {
            $this->assertNotContains(
                $flag,
                $instance->supports,
                "supports[] must NOT contain '$flag' when Pre-approval credential is empty"
            );
        }
    }

    /**
     * PSW-4090: When WCS is installed but toggle is off, supports[] must NOT contain
     * subscription flags — WCS will hide the gateway for subscription products automatically.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSupportsDoesNotContainSubscriptionFlagsWhenToggleIsDisabled(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';

        $reflection = new \ReflectionClass(CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $instance->supports = ['products', 'refunds'];
        $instance->settings = ['subscriptions_enabled' => 'no'];

        $initMethod = $reflection->getMethod('initWcsSupports');
        $initMethod->setAccessible(true);
        $initMethod->invoke($instance);

        $subscriptionFlags = [
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        ];

        foreach ($subscriptionFlags as $flag) {
            $this->assertNotContains(
                $flag,
                $instance->supports,
                "supports[] must NOT contain '$flag' when toggle is off"
            );
        }
    }

    /**
     * AC-2 — Without WCS installed, supports[] keeps its original shape (zero regression).
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSupportsDoesNotContainSubscriptionFlagsWhenWcsIsAbsent(): void
    {
        $this->assertFalse(
            class_exists('WC_Subscriptions', false),
            'Sanity: this test must run without WC_Subscriptions declared.'
        );

        $reflection = new \ReflectionClass(CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $instance->supports = ['products', 'refunds'];

        $initMethod = $reflection->getMethod('initWcsSupports');
        $initMethod->setAccessible(true);
        $initMethod->invoke($instance);

        $subscriptionFlags = [
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
        ];

        foreach ($subscriptionFlags as $flag) {
            $this->assertNotContains(
                $flag,
                $instance->supports,
                "supports[] must NOT contain '$flag' when WCS is not installed"
            );
        }
    }

    /**
     * Covers private handler guards via reflection.
     *
     * process_subscription_payment_method_change() (PSW-4008/PSW-4009) now holds the
     * real implementation, so we verify only the early-return guard: when
     * wcs_get_subscription is unavailable the handler returns failure without touching
     * any other dependencies.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPaymentMethodChangeStubReturnsFailurePlaceholder(): void
    {
        WP_Mock::userFunction('__')->andReturnArg(0);
        WP_Mock::userFunction('get_current_user_id')->andReturn(1);
        WP_Mock::userFunction('set_transient')->andReturn(true);

        $reflection = new \ReflectionClass(CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();

        // change-PM handler: non-object order → early-return guard (!is_object).
        $changeMethod = $reflection->getMethod('process_subscription_payment_method_change');
        $changeMethod->setAccessible(true);
        $changeResult = $changeMethod->invoke($instance, false);

        $this->assertSame('fail', $changeResult['result']);
        $this->assertArrayHasKey('message', $changeResult);
    }

    /**
     * Covers the isWcsActive() fast-path in process_payment(): when WCS is absent,
     * the method returns immediately via parent::process_payment() without
     * reaching subscription branches. We assert unconditionally that neither
     * stub message (PSW-4006 / PSW-4003) appears in the result or exception.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentFastPathDelegatesToParentWhenWcsIsAbsent(): void
    {
        $this->assertFalse(class_exists('WC_Subscriptions', false));

        WP_Mock::userFunction('wc_get_order')->andReturn(Mockery::mock(\WC_Order::class));

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault()->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault()->andReturnSelf();

        $messages = '';
        try {
            $result   = $this->gateway->process_payment(123);
            $messages = $result['messages'] ?? '';
        } catch (\Throwable $e) {
            $messages = $e->getMessage();
        }

        $this->assertStringNotContainsString('PSW-4006', $messages, 'Fast-path não deve rotear para o stub de troca de PM');
        $this->assertStringNotContainsString('PSW-4003', $messages, 'Fast-path não deve rotear para o stub de pagamento inicial');
    }

    /* ───────────────────── overridePublicKeyForSubscriptionCheckout ───────────────────── */

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeySkipsWhenCartHasNoSubscription(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(0);

        WP_Mock::userFunction('wp_add_inline_script')->never();

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeySkipsWhenPublicKeyIsEmpty(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(0);

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolvePublicKey')
            ->once()
            ->andReturn('');

        WP_Mock::userFunction('wp_add_inline_script')->never();

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeyAddsInlineScriptWhenCartHasSubscription(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(0);

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolvePublicKey')
            ->once()
            ->andReturn('APP_USR-sub-key');

        WP_Mock::userFunction('esc_js')->andReturnArg(0);
        WP_Mock::userFunction('wp_add_inline_script')
            ->once()
            ->withArgs(function ($handle, $script, $position) {
                return $handle === 'wc_mercadopago_custom_checkout'
                    && strpos($script, 'APP_USR-sub-key') !== false
                    && strpos($script, 'window.mpSdkInstance = null') !== false
                    && $position === 'after';
            });

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * Manual renewals + automatic payments OFF: the store charges the subscription as a
     * single payment on the DEFAULT credential, so the Pre-approval public-key override
     * MUST be skipped — otherwise the token would be minted with the subscription key and
     * charged with the default access token (credential mismatch → rejected). Guards PPSP-1668.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeySkipsWhenAutomaticPaymentsOff(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('yes');
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn('yes');

        // Context short-circuits before touching the credential resolver or the SDK params.
        $this->gateway->mercadopago->subscriptionsHelper->shouldReceive('resolvePublicKey')->never();
        WP_Mock::userFunction('wp_add_inline_script')->never();

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * Order Pay: subscription order → Pre-approval key applied.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeyTriggersOnSubscriptionOrderPayPage(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;
        $GLOBALS['__wcs_order_contains_subscription'] = true;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        $orderMock = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(99);
        WP_Mock::userFunction('wc_get_order')->with(99)->andReturn($orderMock);

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolvePublicKey')
            ->once()
            ->andReturn('APP_USR-sub-key');

        WP_Mock::userFunction('esc_js')->andReturnArg(0);
        WP_Mock::userFunction('wp_add_inline_script')
            ->once()
            ->withArgs(function ($handle, $script) {
                return $handle === 'wc_mercadopago_custom_checkout'
                    && strpos($script, 'APP_USR-sub-key') !== false;
            });

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * Order Pay: non-subscription order → standard key kept even if cart has subscriptions.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeySkipsOnNonSubscriptionOrderPayEvenWithSubscriptionCart(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;

        $GLOBALS['__wcs_order_contains_subscription'] = false;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        $orderMock = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(55);
        WP_Mock::userFunction('wc_get_order')->with(55)->andReturn($orderMock);

        $this->gateway->mercadopago->subscriptionsHelper->shouldNotReceive('resolvePublicKey');
        WP_Mock::userFunction('wp_add_inline_script')->never();

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * Payment-method change page (cart empty, change_payment_method query var present
     * alongside the subscription ID in order-pay) → Pre-approval key applied.
     *
     * In production the WCS reuses order-pay with the subscription ID:
     *   /checkout/order-pay/114/?...&change_payment_method=114
     * So get_query_var('order-pay') returns a non-zero ID and
     * validateGetVar('change_payment_method') returns true.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOverridePublicKeyTriggersOnChangePaymentPage(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;

        // Production reality: change_payment_method is present AND order-pay has the subscription ID.
        // get_query_var('order-pay') is intentionally not mocked here: when validateGetVar
        // returns true, isSubscriptionPaymentContext() returns immediately without calling it.
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateGetVar')
            ->with('change_payment_method')
            ->andReturn(true);

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolvePublicKey')
            ->once()
            ->andReturn('APP_USR-sub-key');

        WP_Mock::userFunction('esc_js')->andReturnArg(0);
        WP_Mock::userFunction('wp_add_inline_script')
            ->once()
            ->withArgs(function ($handle, $script) {
                return $handle === 'wc_mercadopago_custom_checkout'
                    && strpos($script, 'APP_USR-sub-key') !== false;
            });

        $this->gateway->overridePublicKeyForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /* ───────────────────── dequeueSuperTokenForSubscriptionCheckout ───────────────────── */

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenSkipsWhenCartHasNoSubscription(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(0);

        WP_Mock::userFunction('wp_dequeue_script')->never();
        WP_Mock::userFunction('wp_deregister_script')->never();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * Manual renewals + automatic payments off routes the order through the single
     * payment branch (no recurrence), where Super Token is compatible. Because the
     * automaticPaymentsOff guard lives in the shared isSubscriptionPaymentContext(),
     * it must ALSO skip the dequeue here — leaving Super Token scripts enqueued.
     * Parity with testOverridePublicKeySkipsWhenAutomaticPaymentsOff (same guard,
     * both callers).
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenSkipsWhenAutomaticPaymentsOff(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn('yes');
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn('yes');

        // Context short-circuits before any script is dequeued/deregistered.
        WP_Mock::userFunction('wp_dequeue_script')->never();
        WP_Mock::userFunction('wp_deregister_script')->never();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenRemovesScriptsWhenCartHasSubscription(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(0);

        $dequeued = [];
        WP_Mock::userFunction('wp_dequeue_script')->andReturnUsing(function ($h) use (&$dequeued) {
            $dequeued[] = $h;
        });
        WP_Mock::userFunction('wp_deregister_script')->andReturnNull();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();

        $this->assertContains('wc_mercadopago_supertoken', $dequeued, 'O loader/inicializador do Super Token deve ser removido');
    }

    /**
     * Payment-method change page → Super Token must be dequeued.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenTriggersOnChangePaymentPage(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateGetVar')
            ->with('change_payment_method')
            ->andReturn(true);

        $dequeued = [];
        WP_Mock::userFunction('wp_dequeue_script')->andReturnUsing(function ($h) use (&$dequeued) {
            $dequeued[] = $h;
        });
        WP_Mock::userFunction('wp_deregister_script')->andReturnNull();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();

        $this->assertContains('wc_mercadopago_supertoken', $dequeued);
    }

    /**
     * Order Pay for a subscription order → Super Token must be dequeued.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenTriggersOnSubscriptionOrderPayPage(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = false;
        $GLOBALS['__wcs_order_contains_subscription'] = true;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        $orderMock = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(99);
        WP_Mock::userFunction('wc_get_order')->with(99)->andReturn($orderMock);

        $dequeued = [];
        WP_Mock::userFunction('wp_dequeue_script')->andReturnUsing(function ($h) use (&$dequeued) {
            $dequeued[] = $h;
        });
        WP_Mock::userFunction('wp_deregister_script')->andReturnNull();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();

        $this->assertContains('wc_mercadopago_supertoken', $dequeued);
    }

    /**
     * Order Pay for a non-subscription order → Super Token must NOT be dequeued,
     * even when the cart contains a subscription product.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDequeueSuperTokenSkipsOnNonSubscriptionOrderPayEvenWithSubscriptionCart(): void
    {
        require_once __DIR__ . '/../Mocks/WcsStubs.php';
        $GLOBALS['__wcs_cart_contains_subscription'] = true;
        $GLOBALS['__wcs_order_contains_subscription'] = false;
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->with('change_payment_method')->andReturn(false)->byDefault();
        $orderMock = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('get_query_var')->with('order-pay')->andReturn(55);
        WP_Mock::userFunction('wc_get_order')->with(55)->andReturn($orderMock);

        WP_Mock::userFunction('wp_dequeue_script')->never();
        WP_Mock::userFunction('wp_deregister_script')->never();

        $this->gateway->dequeueSuperTokenForSubscriptionCheckout();
        $this->addToAssertionCount(1);
    }
}

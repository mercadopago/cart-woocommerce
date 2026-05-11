<?php

// Namespace override: intercepts add_action calls from Checkout.php for direct closure invocation in tests.
// Unified with GatewayTest's override for backward compatibility with ScriptsTest's capture mechanism.
namespace MercadoPago\Woocommerce\Hooks;

if (!function_exists('MercadoPago\\Woocommerce\\Hooks\\add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        // ScriptsTest backward compat: capture wp_enqueue_scripts p.20 closure when trigger is set
        if (isset($GLOBALS['__test_capture_wp_enqueue_scripts']) && $tag === 'wp_enqueue_scripts' && $priority === 20) {
            $GLOBALS['__test_captured_wp_enqueue_closure'] = $callback;
        }
        // New hook tests: capture all actions indexed by tag
        $GLOBALS['__captured_actions'][$tag][] = ['callback' => $callback, 'priority' => $priority];

        \WP_Mock::onActionAdded($tag)->react($callback, $priority, $acceptedArgs);
        return true;
    }
}

namespace MercadoPago\Woocommerce\Tests\Hooks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Hooks\Checkout;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CheckoutTest extends TestCase
{
    private Checkout $checkout;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $GLOBALS['__captured_actions'] = [];
        $this->checkout = new Checkout();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
        unset($GLOBALS['__captured_actions']);
    }

    // -------------------------------------------------------------------------
    // registerBeforePay — classic hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforePayAddsClassicHook(): void
    {
        $callback = function () {
        };

        WP_Mock::expectActionAdded('before_woocommerce_pay', $callback);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerBeforePay($callback);

        $this->assertSame($callback, $GLOBALS['__captured_actions']['before_woocommerce_pay'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforePay — block theme wp hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforePayAddsWpHookForBlockThemes(): void
    {
        WP_Mock::expectActionAdded('before_woocommerce_pay', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerBeforePay(function () {
        });

        $this->assertIsCallable($GLOBALS['__captured_actions']['wp'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforePay — static flag deduplication
    // -------------------------------------------------------------------------

    public function testRegisterBeforePayStaticFlagPreventsSecondRegistration(): void
    {
        WP_Mock::expectActionAdded('before_woocommerce_pay', Mockery::type('callable'), 10, 1);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'), 10, 1);

        $this->checkout->registerBeforePay(function () {
        });
        $this->checkout->registerBeforePay(function () {
        }); // must be no-op

        $this->assertCount(1, $GLOBALS['__captured_actions']['before_woocommerce_pay']);
        $this->assertCount(1, $GLOBALS['__captured_actions']['wp']);
    }

    // -------------------------------------------------------------------------
    // registerBeforePay — block theme wp callback inner logic
    // -------------------------------------------------------------------------

    public function testRegisterBeforePayBlockCallbackSkipsWhenNotPayPage(): void
    {
        WP_Mock::expectActionAdded('before_woocommerce_pay', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerBeforePay(function () {
        });

        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testRegisterBeforePayBlockCallbackRegistersEnqueueAtPriority20AndInvokesCallback(): void
    {
        $invoked  = false;
        $callback = function () use (&$invoked) {
            $this->assertCount(0, func_get_args(), 'registerBeforePay callback must receive no arguments');
            $invoked = true;
        };

        WP_Mock::expectActionAdded('before_woocommerce_pay', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('callable'), 20, 1);

        $this->checkout->registerBeforePay($callback);

        WP_Mock::userFunction('is_checkout_pay_page', ['return' => true]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertSame(20, $GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['priority']);

        ($GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['callback'])();

        $this->assertTrue($invoked);
    }

    // -------------------------------------------------------------------------
    // registerPayOrderBeforeSubmit — classic hook
    // -------------------------------------------------------------------------

    public function testRegisterPayOrderBeforeSubmitAddsClassicHook(): void
    {
        $callback = function () {
        };

        WP_Mock::expectActionAdded('woocommerce_pay_order_before_submit', $callback);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerPayOrderBeforeSubmit($callback);

        $this->assertSame($callback, $GLOBALS['__captured_actions']['woocommerce_pay_order_before_submit'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerPayOrderBeforeSubmit — static flag deduplication
    // -------------------------------------------------------------------------

    public function testRegisterPayOrderBeforeSubmitStaticFlagPreventsSecondRegistration(): void
    {
        WP_Mock::expectActionAdded('woocommerce_pay_order_before_submit', Mockery::type('callable'), 10, 1);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'), 10, 1);

        $this->checkout->registerPayOrderBeforeSubmit(function () {
        });
        $this->checkout->registerPayOrderBeforeSubmit(function () {
        }); // must be no-op

        $this->assertCount(1, $GLOBALS['__captured_actions']['woocommerce_pay_order_before_submit']);
        $this->assertCount(1, $GLOBALS['__captured_actions']['wp']);
    }

    // -------------------------------------------------------------------------
    // registerPayOrderBeforeSubmit — block theme wp callback inner logic
    // -------------------------------------------------------------------------

    public function testRegisterPayOrderBeforeSubmitBlockCallbackSkipsWhenNotPayPage(): void
    {
        WP_Mock::expectActionAdded('woocommerce_pay_order_before_submit', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerPayOrderBeforeSubmit(function () {
        });

        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    /**
     * Form::sanitizedGetData uses filter_input_array(INPUT_GET) which reads original PHP input —
     * not injectable via $_GET. Uses Form alias mock to control the return value for this test.
     */
    public function testRegisterPayOrderBeforeSubmitBlockCallbackSkipsWhenPayForOrderAbsent(): void
    {
        Mockery::mock('alias:MercadoPago\Woocommerce\Helpers\Form')
            ->shouldReceive('sanitizedGetData')
            ->with('pay_for_order')
            ->andReturn('');

        WP_Mock::expectActionAdded('woocommerce_pay_order_before_submit', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->checkout->registerPayOrderBeforeSubmit(function () {
        });

        WP_Mock::userFunction('is_checkout_pay_page', ['return' => true]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testRegisterPayOrderBeforeSubmitBlockCallbackRegistersEnqueueAtPriority21WhenPayForOrderPresent(): void
    {
        Mockery::mock('alias:MercadoPago\Woocommerce\Helpers\Form')
            ->shouldReceive('sanitizedGetData')
            ->with('pay_for_order')
            ->andReturn('true');

        $invoked  = false;
        $callback = function () use (&$invoked) {
            $invoked = true;
        };

        WP_Mock::expectActionAdded('woocommerce_pay_order_before_submit', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('callable'), 21, 1);

        $this->checkout->registerPayOrderBeforeSubmit($callback);

        WP_Mock::userFunction('is_checkout_pay_page', ['return' => true]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertSame(21, $GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['priority']);

        ($GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['callback'])();

        $this->assertTrue($invoked);
    }
}

<?php

// Namespace override: PHP resolves unqualified add_action() in the same namespace as Gateway.php first.
// This unified version is backward-compatible with ScriptsTest's capture mechanism so that whichever
// file is autoloaded first by PHPUnit, ScriptsTest's expectations continue to work.
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
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Template;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Funnel\Funnel;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GatewayTest extends TestCase
{
    private Gateway $gateway;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $GLOBALS['__captured_actions'] = [];

        $this->gateway = new Gateway(
            Mockery::mock(Options::class),
            Mockery::mock(Template::class),
            Mockery::mock(Store::class),
            Mockery::mock(Checkout::class),
            Mockery::mock(StoreTranslations::class),
            Mockery::mock(Url::class),
            Mockery::mock(Funnel::class)
        );
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
        unset($GLOBALS['__captured_actions']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — classic hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouAddsClassicHook(): void
    {
        $callback = function ($orderId) {
        };

        WP_Mock::expectActionAdded('woocommerce_before_thankyou', $callback);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou($callback);

        $this->assertSame($callback, $GLOBALS['__captured_actions']['woocommerce_before_thankyou'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — block theme wp hook
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouAddsWpHookForBlockThemes(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        $this->assertIsCallable($GLOBALS['__captured_actions']['wp'][0]['callback']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — static flag deduplication
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouStaticFlagPreventsSecondRegistration(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'), 10, 1);
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'), 10, 1);

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });
        $this->gateway->registerBeforeThankYou(function ($orderId) {
        }); // must be no-op

        $this->assertCount(1, $GLOBALS['__captured_actions']['woocommerce_before_thankyou']);
        $this->assertCount(1, $GLOBALS['__captured_actions']['wp']);
    }

    // -------------------------------------------------------------------------
    // registerBeforeThankYou — block theme wp callback inner logic
    // -------------------------------------------------------------------------

    public function testRegisterBeforeThankYouBlockCallbackSkipsWhenNotOrderReceivedEndpoint(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => false]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testRegisterBeforeThankYouBlockCallbackSkipsWhenOrderIdIsZero(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));

        $this->gateway->registerBeforeThankYou(function ($orderId) {
        });

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => true]);
        WP_Mock::userFunction('get_query_var', ['args' => ['order-received'], 'return' => '0']);
        WP_Mock::userFunction('absint', ['args' => ['0'], 'return' => 0]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertArrayNotHasKey('wp_enqueue_scripts', $GLOBALS['__captured_actions']);
    }

    public function testRegisterBeforeThankYouBlockCallbackRegistersEnqueueAtPriority20AndInvokesCallback(): void
    {
        $invoked  = false;
        $callback = function ($orderId) use (&$invoked) {
            $this->assertSame(99, $orderId);
            $invoked = true;
        };

        WP_Mock::expectActionAdded('woocommerce_before_thankyou', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp', Mockery::type('callable'));
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('callable'), 20, 1);

        $this->gateway->registerBeforeThankYou($callback);

        WP_Mock::userFunction('is_wc_endpoint_url', ['args' => ['order-received'], 'return' => true]);
        WP_Mock::userFunction('get_query_var', ['args' => ['order-received'], 'return' => '99']);
        WP_Mock::userFunction('absint', ['args' => ['99'], 'return' => 99]);

        ($GLOBALS['__captured_actions']['wp'][0]['callback'])();

        $this->assertSame(20, $GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['priority']);

        ($GLOBALS['__captured_actions']['wp_enqueue_scripts'][0]['callback'])();

        $this->assertTrue($invoked);
    }
}

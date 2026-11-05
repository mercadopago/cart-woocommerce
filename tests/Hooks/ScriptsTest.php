<?php

// WHY THIS NAMESPACE BLOCK EXISTS
//
// Scripts.php registers closures via add_action('wp_enqueue_scripts', $closure, 20).
// To test whether the closure body correctly excludes cart/view-order pages, we need
// to invoke that closure directly in PHPUnit.
//
// The problem: WP_Mock controls add_action globally via its hook-tracking system
// (WP_Mock/API/function-mocks.php). Calling WP_Mock::userFunction('add_action') would
// conflict with that system and throw "Unexpected use of add_action" in strict mode.
//
// The solution: PHP resolves unqualified function calls by looking in the *current
// namespace first*, then falling back to the global namespace. By defining add_action
// here in MercadoPago\Woocommerce\Hooks (the same namespace as Scripts.php), PHP picks
// up this version instead of the global WP_Mock stub whenever Scripts.php calls
// add_action(). This override delegates to \WP_Mock::onActionAdded() to keep all
// existing hook-tracking expectations working, and additionally stores the closure in
// $GLOBALS when the test sets __test_capture_wp_enqueue_scripts = true.
//
// DO NOT remove this block or move it below the test namespace declaration — it must
// be declared before the test class for PHP to resolve it for Scripts.php calls.
namespace MercadoPago\Woocommerce\Hooks;

if (!function_exists('MercadoPago\\Woocommerce\\Hooks\\add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        if (!empty($GLOBALS['__test_capture_payment_form_assets']) && $tag === 'wp_enqueue_scripts') {
            $GLOBALS['__test_payment_form_asset_callbacks'][] = $callback;
        }
        if (isset($GLOBALS['__test_capture_wp_enqueue_scripts']) && $tag === 'wp_enqueue_scripts' && $priority === 20) {
            $GLOBALS['__test_captured_wp_enqueue_closure'] = $callback;
        }
        // Opt-in capture stores the real callable; it does not execute or replace it.
        if (!empty($GLOBALS['__test_capture_script_actions'])) {
            $GLOBALS['__test_captured_script_actions'][] = [
                'tag' => $tag,
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $acceptedArgs,
            ];
        }
        \WP_Mock::onActionAdded($tag)->react($callback, $priority, $acceptedArgs);
        return true;
    }
}

namespace MercadoPago\Woocommerce\Tests\Hooks;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WP_Mock;

class ScriptsTest extends TestCase
{
    use WoocommerceMock;

    /**
     * Actual WordPress priorities passed to add_action().
     * registerCheckoutScript() uses the default (10) for ALL checkout scripts, including SDK.
     * prioritizeMelidataStoreScriptEarly() uses 20 so wp_script_is() can reliably detect
     * the SDK — which was already registered at priority 10 — before declaring it as a dependency.
     */
    private const WP_PRIORITY_MELIDATA = 20;

    private const PHASE_WP_ENQUEUE_P10 = 1;
    private const PHASE_WP_ENQUEUE_P20 = 2;
    private const PHASE_TEMPLATE        = 3;

    private const PHASE_LABELS = [
        self::PHASE_WP_ENQUEUE_P10 => 'wp_enqueue_scripts:priority_10',
        self::PHASE_WP_ENQUEUE_P20 => 'wp_enqueue_scripts:priority_20',
        self::PHASE_TEMPLATE        => 'template_rendering',
    ];

    /** @var Mockery\MockInterface|Url */
    private $urlMock;

    /** @var Mockery\MockInterface|Seller */
    private $sellerMock;

    /** @var Mockery\MockInterface|PaymentMethods */
    private $paymentMethodsMock;

    private ScriptHealthMonitor $scriptHealthMonitor;

    private Scripts $scripts;

    public function setUp(): void
    {
        $this->urlMock            = Mockery::mock(Url::class);
        $this->sellerMock         = Mockery::mock(Seller::class);
        $this->paymentMethodsMock = Mockery::mock(PaymentMethods::class);

        $sellerConfig = Mockery::mock(Seller::class);
        $sellerConfig->shouldReceive('getSiteId')->andReturn('MLB')->byDefault();
        $sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('cust-123')->byDefault();

        $storeConfig = Mockery::mock(Store::class);
        $storeConfig->shouldReceive('isTestMode')->andReturn(false)->byDefault();

        $mercadopagoMock = Mockery::mock(WoocommerceMercadoPago::class);
        $mercadopagoMock->sellerConfig = $sellerConfig;
        $mercadopagoMock->storeConfig  = $storeConfig;

        $GLOBALS['mercadopago'] = $mercadopagoMock;

        $this->scriptHealthMonitor = new ScriptHealthMonitor();

        $this->scripts = new Scripts(
            $this->urlMock,
            $this->sellerMock,
            $this->paymentMethodsMock,
            $this->scriptHealthMonitor
        );

        $GLOBALS['woocommerce'] = (object) ['version' => '8.0.0'];

        $this->urlMock->shouldReceive('getJsAsset')->with('melidata/melidata-client')->andReturn('path/to/melidata-client.js');
        $this->urlMock->shouldReceive('getJsAsset')->andReturnUsing(static fn(string $asset) => 'fixture/' . $asset . '.js')->byDefault();
        $this->urlMock->shouldReceive('getCssAsset')->andReturnUsing(static fn(string $asset) => 'fixture/' . $asset . '.css')->byDefault();
        $this->urlMock->shouldReceive('assetVersion')->andReturn('1.0.0')->byDefault();
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('MLA')->byDefault();
        $this->sellerMock->shouldReceive('getCredentialsPublicKey')->andReturn('public-fixture')->byDefault();
        $this->sellerMock->shouldReceive('getCustIdFromAT')->andReturn('cust-fixture')->byDefault();
        $this->sellerMock->shouldReceive('isTestUser')->andReturn(false)->byDefault();
        $this->paymentMethodsMock->shouldReceive('getEnabledPaymentMethods')->andReturn([])->byDefault();
    }

    public function tearDown(): void
    {
        unset(
            $GLOBALS['mercadopago'],
            $GLOBALS['__test_capture_script_actions'],
            $GLOBALS['__test_captured_script_actions'],
            $GLOBALS['__captured_actions'],
            $GLOBALS['__test_capture_wp_enqueue_scripts'],
            $GLOBALS['__test_captured_wp_enqueue_closure'],
            $GLOBALS['__test_capture_payment_form_assets'],
            $GLOBALS['__test_payment_form_asset_callbacks']
        );
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @dataProvider paymentFormContextProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPaymentFormAssetsUseTheResolvedPageContext(array $context, bool $expected): void
    {
        $GLOBALS['__test_capture_payment_form_assets'] = true;
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('Closure'));

        // Register before the query is resolved; the real callbacks must decide at enqueue time.
        foreach (['wc_mercadopago_supertoken', 'wc_mercadopago_supertoken_refactored'] as $handle) {
            $this->scripts->registerPaymentFormScript(
                $handle,
                $handle . '.js',
                ['site_id' => 'MLA'],
                ['wc_mercadopago_sdk'],
                'wc_mercadopago_supertoken_bundle_params'
            );
        }
        $this->scripts->registerPaymentFormStyle('supertoken-css', 'supertoken.css');

        $this->mockPageContext($context);
        $scripts = [];
        $styles = [];
        $localized = [];
        WP_Mock::userFunction('wp_enqueue_script', ['return' => function (...$args) use (&$scripts) {
            $scripts[] = $args;
        }]);
        WP_Mock::userFunction('wp_register_style', ['times' => $expected ? 1 : 0,
            'args' => ['supertoken-css', 'supertoken.css', [], '1.0.0']]);
        WP_Mock::userFunction('wp_enqueue_style', ['return' => function ($handle) use (&$styles) {
            $styles[] = $handle;
        }]);
        WP_Mock::userFunction('wp_localize_script', ['return' => function (...$args) use (&$localized) {
            $localized[] = $args;
        }]);

        $callbacks = $GLOBALS['__test_payment_form_asset_callbacks'] ?? [];
        $this->assertCount(3, $callbacks);
        foreach ($callbacks as $callback) {
            $callback();
        }

        $handles = ['wc_mercadopago_supertoken', 'wc_mercadopago_supertoken_refactored'];
        $this->assertSame($expected ? $handles : [], array_column($scripts, 0));
        $this->assertSame($expected ? ['supertoken-css'] : [], $styles);
        $this->assertSame($expected ? [
            [$handles[0], 'wc_mercadopago_supertoken_bundle_params', ['site_id' => 'MLA']],
            [$handles[1], 'wc_mercadopago_supertoken_bundle_params', ['site_id' => 'MLA']],
        ] : [], $localized);
        foreach ($scripts as $script) {
            $this->assertSame([$script[0] . '.js', ['wc_mercadopago_sdk'], '1.0.0', true], array_slice($script, 1));
        }

        $tracked = new \ReflectionProperty(ScriptHealthMonitor::class, 'trackedHandles');
        $tracked->setAccessible(true);
        $this->assertSame($expected ? $handles : [], $tracked->getValue($this->scriptHealthMonitor));
    }

    public static function paymentFormContextProvider(): array
    {
        return [
            'checkout' => [['is_checkout' => true], true],
            'cart only' => [['is_cart' => true], false],
            'combined checkout and cart' => [['is_checkout' => true, 'is_cart' => true], true],
            'view order' => [['is_view_order_page' => true], false],
            'view order despite checkout' => [['is_view_order_page' => true, 'is_checkout' => true], false],
            'order received also matches checkout' => [['is_order_received_page' => true, 'is_checkout' => true], false],
            'order pay helper' => [['is_checkout_pay_page' => true], true],
            'order pay query variable' => [['order-pay' => '123'], true],
            'order pay with cart' => [['is_checkout_pay_page' => true, 'is_cart' => true], true],
            'confirmation overrides order pay' => [['is_order_received_page' => true, 'order-pay' => '123'], false],
            'add payment method' => [['is_add_payment_method_page' => true], false],
            'unrelated page' => [[], false],
        ];
    }

    /**
     * @dataProvider sharedCheckoutContextProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSharedCheckoutAssetsKeepTheirExistingScope(array $context): void
    {
        $GLOBALS['__test_capture_payment_form_assets'] = true;
        WP_Mock::expectActionAdded('wp_enqueue_scripts', Mockery::type('Closure'));
        $this->scripts->registerCheckoutScript('shared', 'shared.js', ['shared' => true]);
        $this->scripts->registerCheckoutStyle('shared-css', 'shared.css');
        $this->mockPageContext($context);

        WP_Mock::userFunction('wp_enqueue_script', ['times' => 1,
            'args' => ['shared', 'shared.js', [], '1.0.0', true]]);
        WP_Mock::userFunction('wp_localize_script', ['times' => 1,
            'args' => ['shared', 'shared_params', ['shared' => true]]]);
        WP_Mock::userFunction('wp_register_style', ['times' => 1,
            'args' => ['shared-css', 'shared.css', [], '1.0.0']]);
        WP_Mock::userFunction('wp_enqueue_style', ['times' => 1, 'args' => ['shared-css']]);

        $callbacks = $GLOBALS['__test_payment_form_asset_callbacks'] ?? [];
        $this->assertCount(2, $callbacks);
        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    public static function sharedCheckoutContextProvider(): array
    {
        return [
            'cart' => [['is_cart' => true]],
            'view order' => [['is_view_order_page' => true]],
            'confirmation' => [['is_order_received_page' => true]],
            'add payment method' => [['is_add_payment_method_page' => true]],
            'checkout' => [['is_checkout' => true]],
            'order pay query' => [['order-pay' => '123']],
        ];
    }

    private function mockPageContext(array $context): void
    {
        foreach (['is_checkout', 'is_checkout_pay_page', 'is_cart', 'is_view_order_page',
            'is_order_received_page', 'is_add_payment_method_page'] as $function) {
            WP_Mock::userFunction($function, ['return' => $context[$function] ?? false]);
        }
        WP_Mock::userFunction('get_query_var', ['args' => ['order-pay'], 'return' => $context['order-pay'] ?? false]);
    }

    /**
     * Proves that prioritizeMelidataStoreScriptEarly loads melidata in an earlier WordPress
     * lifecycle phase than registerMelidataStoreScript.
     *
     * Assertion: melidataPhase_B (2) < melidataPhase_A (3)
     */
    public function testGivenCallToRegisterMelidataScriptWhenPrioritizeMelidataStoreScriptEarlyCallsShouldBeFasterThanRegisterMelidataScript(): void
    {
        $lifecycleLog  = [];
        $currentPhase  = 0;

        // -----------------------------------------------------------------
        // SCENARIO B setup — intercept add_action('wp_enqueue_scripts', closure, 20)
        //
        // WP_Mock's internal safe_offset() converts Mockery::type('callable') to '__CLOSURE__',
        // which matches any Closure passed to add_action. When the match is found, perform()
        // fires, recording PHASE_WP_ENQUEUE_P20 into the lifecycle log.
        //
        // This is the only WP_Mock-compatible way to intercept a closure-based add_action call:
        // Mockery::type('callable') is the sole matcher that produces '__CLOSURE__' internally.
        // -----------------------------------------------------------------
        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), self::WP_PRIORITY_MELIDATA, 1)
            ->perform(function () use (&$lifecycleLog) {
                // Fires when add_action('wp_enqueue_scripts', $closure, 20) is called.
                // Records the lifecycle phase the closure WILL run in when WordPress
                // dispatches wp_enqueue_scripts at priority 20 (before template rendering).
                $lifecycleLog[] = ['handle' => 'mercadopago_melidata', 'phase' => self::PHASE_WP_ENQUEUE_P20];
            });

        // wp_enqueue_script: records the lifecycle phase at the moment of each direct call
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function (string $handle) use (&$lifecycleLog, &$currentPhase) {
                $lifecycleLog[] = ['handle' => $handle, 'phase' => $currentPhase];
            },
        ]);

        WP_Mock::userFunction('wp_localize_script', ['return' => true]);
        WP_Mock::userFunction('wp_script_is', ['return' => false]);

        // -----------------------------------------------------------------
        // SCENARIO A — registerMelidataStoreScript (slow path)
        //
        // Simulates: woocommerce_before_checkout_form fires during template rendering.
        // registerMelidataStoreScript() calls wp_enqueue_script() DIRECTLY at this phase.
        // -----------------------------------------------------------------
        $currentPhase = self::PHASE_TEMPLATE;
        $this->scripts->registerMelidataStoreScript('/checkout');

        $melidataPhase_A = $this->findScriptPhase($lifecycleLog, 'mercadopago_melidata');

        // -----------------------------------------------------------------
        // SCENARIO B — prioritizeMelidataStoreScriptEarly (fast path)
        //
        // Simulates: gateway constructor calls prioritizeMelidataStoreScriptEarly.
        // The method calls add_action('wp_enqueue_scripts', $closure, 20).
        // WP_Mock intercepts this call, matches the processor set up above, and fires
        // perform(), which records PHASE_WP_ENQUEUE_P20 into the lifecycle log.
        // -----------------------------------------------------------------
        $this->scripts->prioritizeMelidataStoreScriptEarly('/checkout');

        $melidataPhase_B = $this->findScriptPhase($lifecycleLog, 'mercadopago_melidata', 1);

        // -----------------------------------------------------------------
        // ASSERTION
        // prioritizeMelidataStoreScriptEarly must produce an earlier lifecycle phase
        // -----------------------------------------------------------------
        $this->assertLessThan(
            $melidataPhase_A,
            $melidataPhase_B,
            sprintf(
                "prioritizeMelidataStoreScriptEarly: melidata enqueued at phase %d (%s)\n" .
                "registerMelidataStoreScript:        melidata enqueued at phase %d (%s)\n" .
                "Expected the fast path to produce a lower (earlier) lifecycle phase number.",
                $melidataPhase_B,
                self::PHASE_LABELS[$melidataPhase_B],
                $melidataPhase_A,
                self::PHASE_LABELS[$melidataPhase_A]
            )
        );
    }

    /** The actual checkout callback must enqueue and then update the injected monitor. */
    public function testRegisterCheckoutScriptTracksHandleInScriptHealthMonitor(): void
    {
        $effects = $this->recordWordPressEffects();
        $this->mockPaymentPage('checkout');
        $callback = $this->captureAction(function () {
            $this->scripts->registerCheckoutScript('wc_mercadopago_supertoken', 'path/to/supertoken.js', ['mode' => 'real'], ['sdk']);
        });

        $this->assertSame([], $this->trackedHandles());
        $callback();

        $this->assertSame([['wc_mercadopago_supertoken', 'path/to/supertoken.js', ['sdk'], '1.0.0', true]], $effects->scripts);
        $this->assertSame([['wc_mercadopago_supertoken', 'wc_mercadopago_supertoken_params', ['mode' => 'real']]], $effects->localized);
        $this->assertSame(['wc_mercadopago_supertoken'], $this->trackedHandles());
    }

    /**
     * Proves that the closure registered by prioritizeMelidataStoreScriptEarly does NOT call
     * wp_enqueue_script when is_cart() returns true.
     *
     * Guards the fix from PSW-3862: isPaymentsRelatedPage() includes is_cart(), so without
     * the explicit !is_cart() guard, melidata-client would be enqueued on the cart page.
     */
    public function testPrioritizeMelidataStoreScriptEarlyDoesNotEnqueueOnCartPage(): void
    {
        $GLOBALS['__test_capture_wp_enqueue_scripts'] = true;

        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 20, 1)
            ->perform(function () {
            });

        $this->scripts->prioritizeMelidataStoreScriptEarly('/checkout');

        $capturedClosure = $GLOBALS['__test_captured_wp_enqueue_closure'] ?? null;
        unset($GLOBALS['__test_capture_wp_enqueue_scripts'], $GLOBALS['__test_captured_wp_enqueue_closure']);

        $this->assertIsCallable($capturedClosure, 'prioritizeMelidataStoreScriptEarly must register a wp_enqueue_scripts closure at priority 20');

        // isPaymentsRelatedPage() = true (because is_cart() = true), but !is_cart() = false → condition short-circuits to false
        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => true]);

        $enqueueCalled = false;
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function () use (&$enqueueCalled) {
                $enqueueCalled = true;
            },
        ]);

        ($capturedClosure)();

        $this->assertFalse($enqueueCalled, 'wp_enqueue_script must not be called when is_cart() returns true');
    }

    /**
     * Proves that the closure registered by prioritizeMelidataStoreScriptEarly does NOT call
     * wp_enqueue_script when is_view_order_page() returns true.
     *
     * Guards the fix from PSW-3862: isPaymentsRelatedPage() includes is_view_order_page(), so
     * without the explicit !is_view_order_page() guard, melidata-client would be enqueued on
     * the order details page.
     */
    public function testPrioritizeMelidataStoreScriptEarlyDoesNotEnqueueOnViewOrderPage(): void
    {
        $GLOBALS['__test_capture_wp_enqueue_scripts'] = true;

        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 20, 1)
            ->perform(function () {
            });

        $this->scripts->prioritizeMelidataStoreScriptEarly('/checkout');

        $capturedClosure = $GLOBALS['__test_captured_wp_enqueue_closure'] ?? null;
        unset($GLOBALS['__test_capture_wp_enqueue_scripts'], $GLOBALS['__test_captured_wp_enqueue_closure']);

        $this->assertIsCallable($capturedClosure, 'prioritizeMelidataStoreScriptEarly must register a wp_enqueue_scripts closure at priority 20');

        // isPaymentsRelatedPage() = true (because is_view_order_page() = true), but !is_view_order_page() = false → condition fails
        WP_Mock::userFunction('is_view_order_page', ['return' => true]);
        WP_Mock::userFunction('is_cart', ['return' => false]);

        $enqueueCalled = false;
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function () use (&$enqueueCalled) {
                $enqueueCalled = true;
            },
        ]);

        ($capturedClosure)();

        $this->assertFalse($enqueueCalled, 'wp_enqueue_script must not be called when is_view_order_page() returns true');
    }

    /**
     * Proves that prioritizeMelidataStoreScriptEarly guards both deps with wp_script_is:
     * wc_mercadopago_sdk and wc-blocks-registry are each declared only when registered.
     *
     * Guards the fix from PSW-4150: melidata-client.js accesses wc.wcBlocksRegistry, so
     * wc-blocks-registry must be declared (load order) when present — but declaring a dep
     * on an unregistered handle makes WordPress skip the enqueue, which would silently drop
     * melidata on payment pages where the registry is absent (e.g. classic checkout). Both
     * registration paths therefore use the same guard.
     *
     * @dataProvider melidataDepsProvider
     */
    public function testPrioritizeMelidataStoreScriptEarlyGuardsBothDeps(bool $sdkRegistered, bool $registryRegistered): void
    {
        $GLOBALS['__test_capture_wp_enqueue_scripts'] = true;

        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 20, 1)
            ->perform(function () {
            });

        $this->scripts->prioritizeMelidataStoreScriptEarly('/checkout');

        $capturedClosure = $GLOBALS['__test_captured_wp_enqueue_closure'] ?? null;
        unset($GLOBALS['__test_capture_wp_enqueue_scripts'], $GLOBALS['__test_captured_wp_enqueue_closure']);

        $this->assertIsCallable($capturedClosure);

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        WP_Mock::userFunction('get_query_var', ['return' => false]);
        WP_Mock::userFunction('wp_script_is', [
            'return' => function (string $handle) use ($sdkRegistered, $registryRegistered) {
                if ($handle === 'wc_mercadopago_sdk') {
                    return $sdkRegistered;
                }
                if ($handle === 'wc-blocks-registry') {
                    return $registryRegistered;
                }
                return false;
            },
        ]);
        WP_Mock::userFunction('wp_localize_script', ['return' => true]);

        $capturedDeps = null;
        $enqueued = false;
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function (string $handle, string $src, array $deps) use (&$capturedDeps, &$enqueued) {
                if ($handle === 'mercadopago_melidata') {
                    $capturedDeps = $deps;
                    $enqueued = true;
                }
            },
        ]);

        ($capturedClosure)();

        // melidata must always be enqueued, regardless of which deps are present
        $this->assertTrue($enqueued, 'mercadopago_melidata must always be enqueued (never skipped)');
        $this->assertIsArray($capturedDeps);

        if ($sdkRegistered) {
            $this->assertContains('wc_mercadopago_sdk', $capturedDeps);
        } else {
            $this->assertNotContains('wc_mercadopago_sdk', $capturedDeps);
        }

        if ($registryRegistered) {
            $this->assertContains('wc-blocks-registry', $capturedDeps);
        } else {
            $this->assertNotContains('wc-blocks-registry', $capturedDeps);
        }
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function melidataDepsProvider(): array
    {
        return [
            'sdk + registry registered'   => [true, true],
            'sdk registered, no registry' => [true, false],
            'registry registered, no sdk' => [false, true],
            'neither registered'          => [false, false],
        ];
    }

    /**
     * Proves that registerMelidataStoreScript (the path used on /products, /pay_order,
     * /thankyou) declares wc-blocks-registry as a dependency when that script is
     * registered, and omits it otherwise so melidata still loads when the registry is
     * absent (declaring a dep on an unregistered handle makes WordPress skip the script).
     *
     * Keeps both melidata registration paths consistent (PSW-4150): melidata-client.js
     * reads wc.wcBlocksRegistry synchronously on every page it loads, so the handle must
     * declare the dependency wherever the registry is present.
     *
     * @dataProvider melidataRegistryRegisteredProvider
     */
    public function testRegisterMelidataStoreScriptDeclaresWcBlocksRegistryWhenRegistered(bool $registryRegistered): void
    {
        WP_Mock::userFunction('wp_script_is', [
            'return' => function (string $handle) use ($registryRegistered) {
                return $handle === 'wc-blocks-registry' ? $registryRegistered : false;
            },
        ]);
        WP_Mock::userFunction('wp_localize_script', ['return' => true]);

        $capturedDeps = null;
        WP_Mock::userFunction('wp_enqueue_script', [
            'return' => function (string $handle, string $src, array $deps) use (&$capturedDeps) {
                if ($handle === 'mercadopago_melidata') {
                    $capturedDeps = $deps;
                }
            },
        ]);

        $this->scripts->registerMelidataStoreScript('/pay_order');

        $this->assertNotNull($capturedDeps, 'wp_enqueue_script must be called for mercadopago_melidata');

        if ($registryRegistered) {
            $this->assertContains(
                'wc-blocks-registry',
                $capturedDeps,
                'wc-blocks-registry must be declared when the registry script is registered'
            );
        } else {
            $this->assertNotContains(
                'wc-blocks-registry',
                $capturedDeps,
                'wc-blocks-registry must be omitted when not registered, so melidata still loads'
            );
        }
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function melidataRegistryRegisteredProvider(): array
    {
        return [
            'registry registered'     => [true],
            'registry not registered' => [false],
        ];
    }

    public function testCheckoutAssetsDoNotEnqueueOrTrackOutsidePaymentPages(): void
    {
        $effects = $this->recordWordPressEffects();
        $this->mockPaymentPage('unrelated');
        $script = $this->captureAction(function () {
            $this->scripts->registerCheckoutScript('checkout', 'checkout.js', ['key' => 'value']);
        });
        $style = $this->captureAction(function () {
            $this->scripts->registerCheckoutStyle('checkout-style', 'checkout.css');
        });

        $script();
        $style();

        $this->assertSame([], $effects->scripts);
        $this->assertSame([], $effects->localized);
        $this->assertSame([], $effects->registeredStyles);
        $this->assertSame([], $this->trackedHandles());
    }

    public function testStyleRegistrationsExecuteRealStoreAdminAndCheckoutPaths(): void
    {
        $effects = $this->recordWordPressEffects();
        $this->mockPaymentPage('checkout');
        $admin = $this->captureAction(function () {
            $this->scripts->registerAdminStyle('admin-style', 'admin.css');
        }, 'admin_enqueue_scripts');
        $checkout = $this->captureAction(function () {
            $this->scripts->registerCheckoutStyle('checkout-style', 'checkout.css');
        });

        $this->scripts->registerStoreStyle('store-style', 'store.css');
        $admin();
        $checkout();

        $this->assertSame([
            ['store-style', 'store.css', [], '1.0.0'],
            ['admin-style', 'admin.css', [], '1.0.0'],
            ['checkout-style', 'checkout.css', [], '1.0.0'],
        ], $effects->registeredStyles);
        $this->assertSame([['store-style'], ['admin-style'], ['checkout-style']], $effects->styles);
    }

    public function testAdminScriptExecutesItsRealCallbackAndOmitsEmptyLocalization(): void
    {
        $effects = $this->recordWordPressEffects();
        $callback = $this->captureAction(function () {
            $this->scripts->registerAdminScript('admin-script', 'admin.js');
        }, 'admin_enqueue_scripts');

        $this->assertSame([], $effects->scripts);
        $callback();

        $this->assertSame([['admin-script', 'admin.js', [], '1.0.0', true]], $effects->scripts);
        $this->assertSame([], $effects->localized);
    }

    /** @dataProvider creditsSectionProvider */
    public function testCreditsAssetsAreLimitedToTheirAdminSection(bool $matchesSection): void
    {
        $effects = $this->recordWordPressEffects();
        $this->urlMock->shouldReceive('validateSection')->with('woo-mercado-pago-credits')->twice()->andReturn($matchesSection);

        if ($matchesSection) {
            $script = $this->captureAction(function () {
                $this->scripts->registerCreditsAdminScript('credits', 'credits.js', ['enabled' => true]);
            }, 'admin_enqueue_scripts');
            $style = $this->captureAction(function () {
                $this->scripts->registerCreditsAdminStyle('credits-style', 'credits.css');
            }, 'admin_enqueue_scripts');
            $script();
            $style();
            $this->assertSame([['credits', 'credits.js', [], '1.0.0', true]], $effects->scripts);
            $this->assertSame([['credits', 'credits_params', ['enabled' => true]]], $effects->localized);
            $this->assertSame([['credits-style', 'credits.css', [], '1.0.0']], $effects->registeredStyles);
            $this->assertSame([['credits-style']], $effects->styles);
        } else {
            // Unexpected hook registration fails WP_Mock's strict boundary too.
            $this->scripts->registerCreditsAdminScript('credits', 'credits.js');
            $this->scripts->registerCreditsAdminStyle('credits-style', 'credits.css');
            $this->assertSame([], $effects->scripts);
            $this->assertSame([], $effects->registeredStyles);
        }
    }

    public static function creditsSectionProvider(): array
    {
        return ['credits section' => [true], 'another section' => [false]];
    }

    /** @dataProvider adminClientProvider */
    public function testAdminClientsLocalizeTheirActualPayloads(string $client): void
    {
        $effects = $this->recordWordPressEffects();
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('');
        WP_Mock::userFunction('get_locale', ['return' => 'pt_BR']);
        $common = ['site_id' => 'MLA', 'plugin_version' => MP_VERSION, 'platform_version' => '8.0.0'];

        if ($client === 'notices') {
            $method = 'registerNoticesAdminScript';
            $handle = 'wc_mercadopago_notices';
            $file = 'fixture/notices/notices-client.js';
            $expected = array_merge($common, [
                'container' => '#wpbody-content', 'public_key' => 'public-fixture', 'platform_id' => MP_PLATFORM_ID,
            ]);
        } elseif ($client === 'caronte') {
            $method = 'registerCaronteAdminScript';
            $handle = 'wc_mercadopago';
            $file = 'fixture/caronte/caronte-client.js';
            $expected = array_merge($common, [
                'locale' => 'pt_BR', 'platform_id' => MP_PLATFORM_ID,
                'public_key_element_id' => 'mp-public-key-prod', 'reference_element_id' => 'reference',
            ]);
        } else {
            $method = 'registerMelidataAdminScript';
            $handle = 'mercadopago_melidata';
            $file = 'path/to/melidata-client.js';
            $expected = array_merge($common, ['type' => 'seller', 'location' => '/settings', 'payment_method' => '']);
        }
        $callback = $this->captureAction(function () use ($method) {
            $this->scripts->$method();
        }, 'admin_enqueue_scripts');
        $this->assertSame([], $effects->scripts);

        $callback();

        $this->assertSame([[$handle, $file, [], '1.0.0', true]], $effects->scripts);
        $this->assertCount(1, $effects->localized);
        $this->assertSame([$handle, $handle . '_params'], array_slice($effects->localized[0], 0, 2));
        $this->assertPayload($expected, $effects->localized[0][2]);
    }

    public static function adminClientProvider(): array
    {
        return ['notices' => ['notices'], 'caronte' => ['caronte'], 'melidata seller' => ['melidata']];
    }

    public function testBehaviorTrackingUsesActualCheckoutCallbackAndFallbackData(): void
    {
        $effects = $this->recordWordPressEffects();
        $this->mockPaymentPage('checkout');
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('');
        $this->sellerMock->shouldReceive('getCustIdFromAT')->andReturn('');
        WP_Mock::userFunction('get_stylesheet', ['return' => 'theme-fixture']);
        $callback = $this->captureAction(function () {
            $this->scripts->registerMpBehaviorTrackingScript();
        });

        $callback();

        $this->assertSame([['wc_mercadopago_woocommerce_scripts', 'fixture/scripts/mp-behavior-tracking.js', [], '1.0.0', true]], $effects->scripts);
        $this->assertPayload([
            'site_id' => 'MLA', 'cust_id' => '', 'theme' => 'theme-fixture',
            'plugin_version' => MP_VERSION, 'platform_version' => '8.0.0',
        ], $effects->localized[0][2]);
        $this->assertSame(['wc_mercadopago_woocommerce_scripts'], $this->trackedHandles());
    }

    /** @dataProvider healthPageProvider */
    public function testHealthMonitorUsesActualPageContext(string $page, bool $block, bool $classic, bool $enqueued, bool $isCheckout): void
    {
        $effects = $this->recordWordPressEffects();
        $this->mockPaymentPage($page, $classic);
        WP_Mock::userFunction('has_block', ['args' => ['woocommerce/checkout'], 'return' => $block]);
        WP_Mock::userFunction('get_stylesheet', ['return' => 'theme-fixture']);
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('');
        $this->sellerMock->shouldReceive('getCustIdFromAT')->andReturn('');
        $this->sellerMock->shouldReceive('isTestUser')->andReturn(true);
        $this->paymentMethodsMock->shouldReceive('getEnabledPaymentMethods')->andReturn(['pix', 'visa']);
        $callback = $this->captureAction(function () {
            $this->scripts->registerHealthMonitorCheckoutScript();
        });

        $callback();

        if (!$enqueued) {
            $this->assertSame([], $effects->scripts);
            $this->assertSame([], $effects->localized);
            $this->assertSame([], $this->trackedHandles());
            return;
        }
        $this->assertSame([['wc_mercadopago_health_monitor', 'fixture/health/mp-health-monitor.js', [], '1.0.0', true]], $effects->scripts);
        $this->assertSame(['wc_mercadopago_health_monitor', 'wc_mercadopago_health_monitor_params'], array_slice($effects->localized[0], 0, 2));
        $this->assertPayload([
            'site_id' => 'not_available', 'theme' => 'theme-fixture', 'plugin_version' => MP_VERSION,
            'platform_version' => '8.0.0', 'cust_id' => '', 'is_test' => true,
            'is_checkout' => $isCheckout, 'payment_methods' => ['pix', 'visa'],
        ], $effects->localized[0][2]);
        $this->assertSame(['wc_mercadopago_health_monitor'], $this->trackedHandles());
    }

    public static function healthPageProvider(): array
    {
        return [
            'Classic checkout' => ['checkout', false, true, true, true],
            'Blocks checkout' => ['checkout', true, true, true, true],
            'block-only payment context' => ['cart', true, false, true, true],
            'order pay' => ['pay', false, false, true, true],
            'thank you' => ['received', false, true, true, false],
            'cart without checkout' => ['cart', false, false, true, false],
            'unrelated page' => ['unrelated', false, false, false, false],
        ];
    }

    public function testPaymentBlockRegistrationPreservesVersionDependenciesAndOptionalData(): void
    {
        $effects = $this->recordWordPressEffects();

        $this->scripts->registerPaymentBlockScript('block-with-data', 'block.js', 'build-7', ['wc-blocks-registry'], ['enabled' => true]);
        $this->scripts->registerPaymentBlockScript('block-without-data', 'empty.js', 'build-8');

        $this->assertSame([
            ['block-with-data', 'block.js', ['wc-blocks-registry'], 'build-7', true],
            ['block-without-data', 'empty.js', [], 'build-8', true],
        ], $effects->registeredScripts);
        $this->assertSame([['block-with-data', 'block-with-data_params', ['enabled' => true]]], $effects->localized);
    }

    private function captureAction(callable $register, string $tag = 'wp_enqueue_scripts', int $priority = 10): callable
    {
        $GLOBALS['__test_capture_script_actions'] = true;
        $GLOBALS['__test_captured_script_actions'] = [];
        WP_Mock::onHookAdded($tag, 'action')->with(Mockery::type('callable'), $priority, 1)->perform(static function () {
        });
        try {
            $register();
            $captured = $GLOBALS['__test_captured_script_actions'];
        } finally {
            unset($GLOBALS['__test_capture_script_actions'], $GLOBALS['__test_captured_script_actions']);
        }
        $this->assertCount(1, $captured);
        $this->assertSame($tag, $captured[0]['tag']);
        $this->assertSame($priority, $captured[0]['priority']);
        $this->assertSame(1, $captured[0]['accepted_args']);
        $this->assertIsCallable($captured[0]['callback']);
        return $captured[0]['callback'];
    }

    private function recordWordPressEffects(): \stdClass
    {
        $effects = (object) ['scripts' => [], 'localized' => [], 'registeredStyles' => [], 'styles' => [], 'registeredScripts' => []];
        foreach (['wp_enqueue_script' => 'scripts', 'wp_localize_script' => 'localized', 'wp_register_style' => 'registeredStyles', 'wp_enqueue_style' => 'styles', 'wp_register_script' => 'registeredScripts'] as $function => $property) {
            WP_Mock::userFunction($function, ['return' => static function (...$args) use ($effects, $property) {
                $effects->$property[] = $args;
                return true;
            }]);
        }
        return $effects;
    }

    private function mockPaymentPage(string $page, ?bool $classic = null): void
    {
        foreach (['is_view_order_page' => 'view', 'is_cart' => 'cart', 'is_order_received_page' => 'received', 'is_checkout_pay_page' => 'pay', 'is_add_payment_method_page' => 'add', 'is_checkout' => 'checkout'] as $function => $matches) {
            WP_Mock::userFunction($function, ['return' => $function === 'is_checkout' && $classic !== null ? $classic : $page === $matches]);
        }
        WP_Mock::userFunction('get_query_var', ['args' => ['order-pay'], 'return' => false]);
    }

    private function trackedHandles(): array
    {
        $property = new \ReflectionProperty(ScriptHealthMonitor::class, 'trackedHandles');
        $property->setAccessible(true);
        return $property->getValue($this->scriptHealthMonitor);
    }

    private function assertPayload(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    /**
     * Returns the lifecycle phase of the nth occurrence of a given script handle in the log.
     *
     * @param array<array{handle: string, phase: int}> $log
     */
    private function findScriptPhase(array $log, string $handle, int $occurrenceIndex = 0): int
    {
        $found = 0;
        foreach ($log as $entry) {
            if ($entry['handle'] === $handle) {
                if ($found === $occurrenceIndex) {
                    return $entry['phase'];
                }
                $found++;
            }
        }

        $this->fail(sprintf(
            "Script '%s' (occurrence #%d) was not found in the lifecycle log.\n" .
            "Log contents: %s",
            $handle,
            $occurrenceIndex,
            json_encode($log)
        ));
    }
}

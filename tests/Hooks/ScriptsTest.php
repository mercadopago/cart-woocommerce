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
        if (!empty($GLOBALS['__test_capture_admin_notice_assets']) && $tag === 'admin_enqueue_scripts') {
            $GLOBALS['__test_admin_notice_asset_callbacks'][] = $callback;
        }
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
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\Strings;
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
            $GLOBALS['__test_capture_admin_notice_assets'],
            $GLOBALS['__test_admin_notice_asset_callbacks'],
            $GLOBALS['__test_capture_script_actions'],
            $GLOBALS['__test_captured_script_actions'],
            $GLOBALS['__captured_actions'],
            $GLOBALS['__test_capture_wp_enqueue_scripts'],
            $GLOBALS['__test_captured_wp_enqueue_closure'],
            $GLOBALS['__test_capture_payment_form_assets'],
            $GLOBALS['__test_payment_form_asset_callbacks']
        );
        unset($_GET['key']);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNativeNoticeAndRemoteAssetsUseSeparateConfigurationAndOrderedDependencies(): void
    {
        define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
        $GLOBALS['__test_capture_admin_notice_assets'] = true;
        $GLOBALS['__test_admin_notice_asset_callbacks'] = [];
        WP_Mock::expectActionAdded('admin_enqueue_scripts', Mockery::type('Closure'));
        $this->urlMock->shouldReceive('getCssAsset')->with('admin/mp-admin-notices-layout')->andReturn('/native.css');
        $this->urlMock->shouldReceive('getJsAsset')->with('admin/mp-admin-notices-layout')->andReturn('/native.js');
        $this->urlMock->shouldReceive('getJsAsset')->with('notices/notices-client')->andReturn('/remote-loader.js');
        $cssRevision = substr(hash_file('sha256', dirname(MP_PLUGIN_FILE) . '/assets/css/admin/mp-admin-notices-layout.min.css'), 0, 12);
        $jsRevision = substr(hash_file('sha256', dirname(MP_PLUGIN_FILE) . '/assets/js/admin/mp-admin-notices-layout.min.js'), 0, 12);
        $this->urlMock->shouldReceive('setQueryVar')->once()->with('mp_native_rev', $cssRevision, '/native.css')->andReturn('/native.css?mp_native_rev=' . $cssRevision);
        $this->urlMock->shouldReceive('setQueryVar')->once()->with('mp_native_rev', $jsRevision, '/native.js')->andReturn('/native.js?mp_native_rev=' . $jsRevision);
        $this->sellerMock->shouldReceive('getCredentialsPublicKey')->andReturn('synthetic-active-mode-public-key');
        $native = ['enabled' => true, 'ajax_url' => '/wp-admin/admin-ajax.php', 'nonce' => 'synthetic-nonce', 'screen' => 'dashboard'];
        $scripts = [];
        $localized = [];
        WP_Mock::userFunction('wp_register_style')->once()->with('wc_mercadopago_native_notices_css', '/native.css?mp_native_rev=' . $cssRevision, [], '1.0.0');
        WP_Mock::userFunction('wp_enqueue_style')->once()->with('wc_mercadopago_native_notices_css');
        WP_Mock::userFunction('wp_enqueue_script')->andReturnUsing(function ($name, $file, $deps, $version, $footer) use (&$scripts) {
            $scripts[$name] = compact('file', 'deps', 'version', 'footer');
        });
        WP_Mock::userFunction('wp_localize_script')->andReturnUsing(function ($name, $global, $params) use (&$localized) {
            $localized[$name] = compact('global', 'params');
        });

        $this->scripts->registerNativeNoticesAdminScript($native);
        $this->scripts->registerNoticesAdminScript();
        foreach ($GLOBALS['__test_admin_notice_asset_callbacks'] as $callback) {
            $callback();
        }

        $this->assertSame([], $scripts['wc_mercadopago_native_notices']['deps']);
        $this->assertSame('/native.js?mp_native_rev=' . $jsRevision, $scripts['wc_mercadopago_native_notices']['file']);
        $this->assertSame('/remote-loader.js', $scripts['wc_mercadopago_notices']['file']);
        $this->assertSame(['wc_mercadopago_native_notices'], $scripts['wc_mercadopago_notices']['deps']);
        $this->assertSame('wc_mercadopago_native_notices_params', $localized['wc_mercadopago_native_notices']['global']);
        $this->assertSame($native, $localized['wc_mercadopago_native_notices']['params']);
        $remote = $localized['wc_mercadopago_notices']['params'];
        $this->assertSame('#mp-jaiminho-notices-slot', $remote['container']);
        $this->assertSame('synthetic-active-mode-public-key', $remote['public_key']);
        $this->assertSame('8.0.0', $remote['platform_version']);
        $this->assertSame(MP_VERSION, $remote['plugin_version']);
        $this->assertSame(MP_PLATFORM_ID, $remote['platform_id']);
        $this->assertSame('https://http2.mlstatic.com/storage/v1/plugins/notices/woocommerce.css', $remote['css_url']);
        $this->assertArrayNotHasKey('public_key', $localized['wc_mercadopago_native_notices']['params']);
    }

    public function testLegacyNoticeContainerDoesNotDependOnNativeAssets(): void
    {
        $effects = $this->recordWordPressEffects();
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('MLB');
        $callback = $this->captureAction(function () {
            $this->scripts->registerNoticesAdminScript(false);
        }, 'admin_enqueue_scripts');

        $callback();

        $this->assertSame([
            ['wc_mercadopago_notices', 'fixture/notices/notices-client.js', [], '1.0.0', true],
        ], $effects->scripts);
        $this->assertCount(1, $effects->localized);
        $this->assertSame('wc_mercadopago_notices', $effects->localized[0][0]);
        $this->assertSame('#wpbody-content', $effects->localized[0][2]['container']);
        $this->assertSame('MLB', $effects->localized[0][2]['site_id']);
    }

    /**
     * @dataProvider nativeNoticeAssetRevisionProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNativeNoticeContentRevisionsAreLocalAndFailOpen(string $scenario): void
    {
        $root = sys_get_temp_dir() . '/mp-native-assets-' . uniqid('', true);
        $cssFile = $root . '/assets/css/admin/mp-admin-notices-layout.min.css';
        $jsFile = $root . '/assets/js/admin/mp-admin-notices-layout.min.js';
        mkdir(dirname($cssFile), 0700, true);
        mkdir(dirname($jsFile), 0700, true);
        define('MP_PLUGIN_FILE', $root . '/woocommerce-mercadopago.php');
        $css = 'native stylesheet';
        $js = 'native javascript';
        file_put_contents($cssFile, $css);
        file_put_contents($jsFile, $js);

        try {
            $this->scripts = new Scripts(new Url(new Strings()), $this->sellerMock, $this->paymentMethodsMock, $this->scriptHealthMonitor);
            $GLOBALS['__test_capture_admin_notice_assets'] = true;
            WP_Mock::expectActionAdded('admin_enqueue_scripts', Mockery::type('Closure'));
            WP_Mock::userFunction('plugins_url')->andReturnUsing(static fn($asset) => 'https://store.example/plugin/' . $asset . '?existing=kept');
            WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
            WP_Mock::userFunction('add_query_arg')->andReturnUsing(static fn($key, $value, $url) => $url . '&' . $key . '=' . $value);
            $registered = [];
            WP_Mock::userFunction('wp_register_style')->andReturnUsing(static function ($name, $file, $deps, $version) use (&$registered): void {
                $registered[$name] = compact('file', 'version');
            });
            WP_Mock::userFunction('wp_enqueue_style');
            WP_Mock::userFunction('wp_enqueue_script')->andReturnUsing(static function ($name, $file, $deps, $version) use (&$registered): void {
                $registered[$name] = compact('file', 'version');
            });
            $enqueue = function () use (&$registered): array {
                $GLOBALS['__test_admin_notice_asset_callbacks'] = [];
                $this->scripts->registerNativeNoticesAdminScript([]);
                $this->scripts->registerAdminStyle('other-style', '/other.css?existing=kept');
                $this->scripts->registerAdminScript('other-script', '/other.js?existing=kept');
                foreach ($GLOBALS['__test_admin_notice_asset_callbacks'] as $callback) {
                    $callback();
                }
                return $registered;
            };
            $before = $enqueue();

            if ($scenario === 'css_changed') {
                $css .= ' updated';
                file_put_contents($cssFile, $css);
            } elseif ($scenario === 'js_changed') {
                $js .= ' updated';
                file_put_contents($jsFile, $js);
            } elseif ($scenario === 'missing') {
                unlink($cssFile);
                unlink($jsFile);
            } elseif ($scenario === 'unreadable') {
                chmod($cssFile, 0000);
                chmod($jsFile, 0000);
                clearstatcache();
                if (is_readable($cssFile) || is_readable($jsFile)) {
                    $this->markTestSkipped('This runtime cannot enforce unreadable permissions on its own files.');
                }
            } elseif ($scenario === 'not_regular') {
                unlink($cssFile);
                unlink($jsFile);
                mkdir($cssFile);
                mkdir($jsFile);
                clearstatcache();
            }

            $after = $enqueue();
            foreach (['css' => $css, 'js' => $js] as $type => $content) {
                $handle = $type === 'css' ? 'wc_mercadopago_native_notices_css' : 'wc_mercadopago_native_notices';
                $originalUrl = 'https://store.example/plugin/assets/' . $type . '/admin/mp-admin-notices-layout.min.' . $type . '?existing=kept';
                $this->assertSame($originalUrl . '&mp_native_rev=' . substr(hash('sha256', $type === 'css' ? 'native stylesheet' : 'native javascript'), 0, 12), $before[$handle]['file']);
                $expectedUrl = in_array($scenario, ['missing', 'unreadable', 'not_regular'], true) ? $originalUrl : $originalUrl . '&mp_native_rev=' . substr(hash('sha256', $content), 0, 12);
                $this->assertSame($expectedUrl, $after[$handle]['file']);
                $this->assertSame(MP_VERSION, $after[$handle]['version']);
                if ($scenario === $type . '_changed') {
                    $this->assertNotSame($before[$handle]['file'], $after[$handle]['file']);
                }
            }
            $this->assertSame(['file' => '/other.css?existing=kept', 'version' => MP_VERSION], $after['other-style']);
            $this->assertSame(['file' => '/other.js?existing=kept', 'version' => MP_VERSION], $after['other-script']);
        } finally {
            foreach ([$cssFile, $jsFile] as $file) {
                if (is_dir($file)) {
                    rmdir($file);
                } elseif (file_exists($file)) {
                    chmod($file, 0600);
                    unlink($file);
                }
                rmdir(dirname($file));
                rmdir(dirname($file, 2));
            }
            rmdir($root . '/assets');
            rmdir($root);
        }
    }

    public function nativeNoticeAssetRevisionProvider(): array
    {
        return [['unchanged'], ['css_changed'], ['js_changed'], ['missing'], ['unreadable'], ['not_regular']];
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
     * @dataProvider smartPixAssetsProvider
     */
    public function testRegisterSmartPixCheckoutAssetsUsesOnlyAllowlistedServerConfiguration(
        string $environment,
        string $expectedScriptUrl,
        string $expectedStyleUrl
    ): void {
        $this->expectSmartPixBootstrapAssets();
        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 10, 1)
            ->perform(function () {
            });
        WP_Mock::onFilterAdded('style_loader_tag')
            ->with([$this->scripts, 'addSmartPixStyleCrossOrigin'], 10, 2)
            ->perform(function () {
            });

        $this->scripts->registerSmartPixCheckoutAssets($environment);

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        WP_Mock::userFunction('get_query_var', ['return' => false]);
        WP_Mock::userFunction('wp_enqueue_style')
            ->with(Scripts::SMART_PIX_STYLE_NAME, $expectedStyleUrl, [], 'v1')
            ->once();
        WP_Mock::userFunction('wp_enqueue_script')
            ->with(
                Scripts::SMART_PIX_SCRIPT_NAME,
                $expectedScriptUrl,
                ['wc_mercadopago_sdk'],
                'v1',
                true
            )
            ->once();
        WP_Mock::userFunction('wp_localize_script')
            ->with(Scripts::SMART_PIX_SCRIPT_NAME, 'wc_mercadopago_smart_pix_params', [
                'site_id' => Country::SITE_ID_MLB,
                'standalone_bridge' => 'enabled',
                'platform_id' => MP_PLATFORM_ID,
                'order_pay_context' => null,
            ])
            ->once();

        $this->scripts->enqueueSmartPixCheckoutAssets($environment);

        $this->assertContains(
            Scripts::SMART_PIX_SCRIPT_NAME,
            $this->getTrackedScriptHandles()
        );
    }

    public function testSmartPixUsesTheSharedBridgeWhenCustomGatewayIsEnabled(): void
    {
        $this->paymentMethodsMock->shouldReceive('getEnabledPaymentMethods')
            ->once()
            ->andReturn([CustomGateway::ID]);

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        WP_Mock::userFunction('get_query_var', ['return' => false]);
        WP_Mock::userFunction('wp_enqueue_style', ['return' => true]);
        WP_Mock::userFunction('wp_enqueue_script', ['return' => true]);
        WP_Mock::userFunction('wp_localize_script')
            ->with(Scripts::SMART_PIX_SCRIPT_NAME, 'wc_mercadopago_smart_pix_params', [
                'site_id' => Country::SITE_ID_MLB,
                'standalone_bridge' => 'disabled',
                'platform_id' => MP_PLATFORM_ID,
                'order_pay_context' => null,
            ])
            ->once();

        $this->scripts->enqueueSmartPixCheckoutAssets(Scripts::SMART_PIX_ENV_HOMOLOGATION);
        $this->assertContains(Scripts::SMART_PIX_SCRIPT_NAME, $this->getTrackedScriptHandles());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function smartPixAssetsProvider(): array
    {
        return [
            'homologation' => [
                Scripts::SMART_PIX_ENV_HOMOLOGATION,
                'https://http2.mlstatic.com/storage/homol/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.js',
                'https://http2.mlstatic.com/storage/homol/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.css',
            ],
            'production' => [
                Scripts::SMART_PIX_ENV_PRODUCTION,
                'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.js',
                'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.css',
            ],
        ];
    }

    public function testRegisterSmartPixCheckoutAssetsRejectsUnknownEnvironment(): void
    {
        $this->scripts->registerSmartPixCheckoutAssets('https://attacker.example/bundle.js');
        $this->scripts->enqueueSmartPixCheckoutAssets('https://attacker.example/bundle.js');

        $this->assertSame([], $this->getTrackedScriptHandles());
    }

    /** @dataProvider sharedFastPaymentsProvider */
    public function testCustomGatewayEnqueuesOneSuperTokenLoaderWithoutPixParameters(
        bool $customEnabled,
        bool $pixFirst,
        string $environment
    ): void {
        $registrations = 0;
        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 10, 1)
            ->perform(function () use (&$registrations) {
                $registrations++;
            });
        $this->scripts->registerFastPaymentsCheckoutAssets($environment);
        $this->scripts->registerFastPaymentsCheckoutAssets($environment);
        $this->assertSame(1, $registrations);

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        $this->urlMock->shouldReceive('getJsAsset')->with('checkouts/super-token-loader')->andReturn('loader.js');
        $this->sellerMock->shouldReceive('getSiteId')->andReturn('MLB');
        $this->paymentMethodsMock->shouldReceive('getEnabledPaymentMethods')
            ->andReturn($customEnabled ? [CustomGateway::ID] : []);
        WP_Mock::userFunction('wp_enqueue_script', [
            'times' => 1,
            'args' => [Scripts::FAST_PAYMENTS_SCRIPT_NAME, 'loader.js', ['wc_mercadopago_sdk'], '1.0.0', true],
        ]);
        $localizations = [];
        WP_Mock::userFunction('wp_localize_script', [
            'times' => 1,
            'return' => function (string $handle, string $name, array $params) use (&$localizations) {
                $this->assertSame(Scripts::FAST_PAYMENTS_SCRIPT_NAME, $handle);
                $localizations[$name] = $params;
                return true;
            },
        ]);
        $this->scripts->enqueueFastPaymentsCheckoutAssets();
        $this->assertSame(['wc_mercadopago_supertoken_params'], array_keys($localizations));
        $this->assertSame(['environment' => $environment], $localizations['wc_mercadopago_supertoken_params']);
        $this->assertArrayNotHasKey('wc_mercadopago_smart_pix_params', $localizations);

    }

    public static function sharedFastPaymentsProvider(): array
    {
        return [
            [true, false, Scripts::SMART_PIX_ENV_PRODUCTION],
            [true, true, Scripts::SMART_PIX_ENV_HOMOLOGATION],
            [false, false, Scripts::SMART_PIX_ENV_PRODUCTION],
            [false, true, Scripts::SMART_PIX_ENV_HOMOLOGATION],
        ];
    }

    public function testSharedFastPaymentsRejectsUnknownEnvironmentBeforeRegistration(): void
    {
        $this->scripts->registerFastPaymentsCheckoutAssets('https://attacker.example');
        $this->scripts->enqueueFastPaymentsCheckoutAssets();
        $this->assertSame([], $this->getTrackedScriptHandles());
    }

    public function testFastPaymentsLoaderKeepsTheFirstEnvironmentAndLocalizesItsBundleParams(): void
    {
        $callback = $this->captureAction(function () {
            $this->scripts->registerFastPaymentsCheckoutAssets(Scripts::SMART_PIX_ENV_PRODUCTION, ['site_id' => 'MLB']);
        });
        // A second gateway asking for another environment must not switch the loader or its params.
        $this->scripts->registerFastPaymentsCheckoutAssets(Scripts::SMART_PIX_ENV_HOMOLOGATION, ['site_id' => 'MLA']);

        $this->mockPageContext(['is_checkout' => true]);
        $this->urlMock->shouldReceive('getJsAsset')->with('checkouts/super-token-loader')->andReturn('loader.js');
        $effects = $this->recordWordPressEffects();

        $callback();

        $this->assertSame([
            [Scripts::FAST_PAYMENTS_SCRIPT_NAME, 'loader.js', ['wc_mercadopago_sdk'], '1.0.0', true],
        ], $effects->scripts);
        $this->assertSame([
            [Scripts::FAST_PAYMENTS_SCRIPT_NAME, 'wc_mercadopago_supertoken_params', ['environment' => Scripts::SMART_PIX_ENV_PRODUCTION]],
            [Scripts::FAST_PAYMENTS_SCRIPT_NAME, Scripts::FAST_PAYMENTS_BUNDLE_PARAMS_OBJECT, ['site_id' => 'MLB']],
        ], $effects->localized);
        $this->assertSame([Scripts::FAST_PAYMENTS_SCRIPT_NAME], $this->getTrackedScriptHandles());
    }

    public function testRegisteredSmartPixCallbackSkipsPagesWithoutPayments(): void
    {
        WP_Mock::onFilterAdded('style_loader_tag')
            ->with([$this->scripts, 'addSmartPixStyleCrossOrigin'], 10, 2)
            ->perform(function () {
            });
        $callback = $this->captureAction(function () {
            $this->scripts->registerSmartPixCheckoutAssets(Scripts::SMART_PIX_ENV_PRODUCTION);
        });

        $this->mockPageContext([]);
        $effects = $this->recordWordPressEffects();

        $callback();

        $this->assertSame([], $effects->scripts);
        $this->assertSame([], $effects->styles);
        $this->assertSame([], $this->getTrackedScriptHandles());
    }

    public function testRegisteredSmartPixLocalCallbackSkipsPagesWithoutPayments(): void
    {
        $callback = $this->captureAction(function () {
            $this->scripts->registerSmartPixLocalCheckoutAssets();
        });

        $this->mockPageContext([]);
        $this->urlMock->shouldNotReceive('getCssAsset')->with('checkouts/smart-pix/smart-pix.bundle');
        $effects = $this->recordWordPressEffects();

        $callback();

        $this->assertSame([], $effects->scripts);
        $this->assertSame([], $effects->styles);
        $this->assertSame([], $this->getTrackedScriptHandles());
    }

    public function testSmartPixLocalAssetsAreAvailableOnlyWhenBothBuiltFilesExist(): void
    {
        $pluginRoot = sys_get_temp_dir() . '/mp-smart-pix-local-' . uniqid('', true);
        $scriptFile = $pluginRoot . '/assets/js/checkouts/smart-pix/smart-pix.bundle.min.js';
        $styleFile  = $pluginRoot . '/assets/css/checkouts/smart-pix/smart-pix.bundle.min.css';
        mkdir(dirname($scriptFile), 0777, true);
        mkdir(dirname($styleFile), 0777, true);

        try {
            $this->assertFalse($this->scripts->hasSmartPixLocalCheckoutAssets($pluginRoot));

            file_put_contents($scriptFile, '/* built */');
            $this->assertFalse($this->scripts->hasSmartPixLocalCheckoutAssets($pluginRoot));

            file_put_contents($styleFile, '/* built */');
            $this->assertTrue($this->scripts->hasSmartPixLocalCheckoutAssets($pluginRoot));

            unlink($scriptFile);
            $this->assertFalse($this->scripts->hasSmartPixLocalCheckoutAssets($pluginRoot));
        } finally {
            @unlink($scriptFile);
            @unlink($styleFile);
            @rmdir(dirname($scriptFile));
            @rmdir(dirname($styleFile));
            @rmdir($pluginRoot . '/assets/js/checkouts');
            @rmdir($pluginRoot . '/assets/css/checkouts');
            @rmdir($pluginRoot . '/assets/js');
            @rmdir($pluginRoot . '/assets/css');
            @rmdir($pluginRoot . '/assets');
            @rmdir($pluginRoot);
        }
    }

    public function testSmartPixLocalAssetsDefaultToThePluginDirectory(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $expected = is_file($pluginRoot . '/assets/js/checkouts/smart-pix/smart-pix.bundle.min.js')
            && is_file($pluginRoot . '/assets/css/checkouts/smart-pix/smart-pix.bundle.min.css');

        $this->assertSame($expected, $this->scripts->hasSmartPixLocalCheckoutAssets());
    }

    public function testSmartPixRealLocalAssetsRequireSdkAndKeepOrderPayContext(): void
    {
        $this->expectSmartPixBootstrapAssets();
        WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
            ->with(Mockery::type('callable'), 10, 1)
            ->perform(function () {
            });
        $this->scripts->registerSmartPixLocalCheckoutAssets();

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        WP_Mock::userFunction('get_query_var', ['return' => false]);
        $this->urlMock->shouldReceive('getCssAsset')
            ->once()->with('checkouts/smart-pix/smart-pix.bundle')
            ->andReturn('https://store.test/plugin/smart-pix.bundle.min.css');
        $this->urlMock->shouldReceive('getJsAsset')
            ->once()->with('checkouts/smart-pix/smart-pix.bundle')
            ->andReturn('https://store.test/plugin/smart-pix.bundle.min.js');
        WP_Mock::userFunction('wp_enqueue_style')
            ->once()->with(Scripts::SMART_PIX_STYLE_NAME, 'https://store.test/plugin/smart-pix.bundle.min.css', [], '1.0.0');
        WP_Mock::userFunction('wp_enqueue_script')
            ->once()->with(Scripts::SMART_PIX_SCRIPT_NAME, 'https://store.test/plugin/smart-pix.bundle.min.js',
                ['wc_mercadopago_sdk'], '1.0.0', true);
        WP_Mock::userFunction('wp_localize_script')
            ->once()->with(Scripts::SMART_PIX_SCRIPT_NAME, 'wc_mercadopago_smart_pix_params', [
                'site_id' => Country::SITE_ID_MLB,
                'standalone_bridge' => 'enabled',
                'platform_id' => MP_PLATFORM_ID,
                'order_pay_context' => null,
            ]);

        $this->scripts->enqueueSmartPixLocalCheckoutAssets();
        $this->assertContains(Scripts::SMART_PIX_SCRIPT_NAME, $this->getTrackedScriptHandles());
    }

    private function expectSmartPixBootstrapAssets(): void
    {
        WP_Mock::userFunction('wp_enqueue_style')
            ->once()->with('wc_mercadopago_pix_bootstrap', 'fixture/checkouts/mp-pix-bootstrap.css', [], '1.0.0');
        WP_Mock::userFunction('wp_enqueue_script')
            ->once()->with('wc_mercadopago_pix_bootstrap', 'fixture/checkouts/pix/mp-pix-bootstrap.js', [], '1.0.0', false);
    }

    /**
     * @dataProvider smartPixOrderPayTotalProvider
     *
     * @param mixed $total
     */
    public function testSmartPixOrderPayContextCarriesOnlyACanonicalPositiveTotal($total, ?array $expectedContext): void
    {
        $_GET['key'] = 'wc_order_key';
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_order_key')->andReturn('wc_order_key');
        $order->shouldReceive('get_total')->andReturn($total);
        $order->shouldReceive('get_billing_email')->andReturn(' buyer@example.com ');
        $order->shouldReceive('get_id')->andReturn(42);

        WP_Mock::userFunction('is_view_order_page', ['return' => false]);
        WP_Mock::userFunction('is_cart', ['return' => false]);
        WP_Mock::userFunction('is_order_received_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => true]);
        WP_Mock::userFunction('is_add_payment_method_page', ['return' => false]);
        WP_Mock::userFunction('is_checkout', ['return' => true]);
        WP_Mock::userFunction('get_query_var', ['return' => '42']);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        WP_Mock::userFunction('wp_unslash', ['return' => static fn($value) => $value]);
        WP_Mock::userFunction('sanitize_text_field', ['return' => static fn($value) => $value]);
        $this->urlMock->shouldReceive('getCssAsset')->andReturn('https://store.test/smart-pix.bundle.min.css');
        $this->urlMock->shouldReceive('getJsAsset')->andReturn('https://store.test/smart-pix.bundle.min.js');
        WP_Mock::userFunction('wp_enqueue_style', ['return' => true]);
        WP_Mock::userFunction('wp_enqueue_script', ['return' => true]);
        $localized = null;
        WP_Mock::userFunction('wp_localize_script', [
            'times' => 1,
            'return' => static function (string $handle, string $name, array $params) use (&$localized): bool {
                $localized = [$handle, $name, $params];
                return true;
            },
        ]);

        $this->scripts->enqueueSmartPixLocalCheckoutAssets();

        $this->assertSame([
            Scripts::SMART_PIX_SCRIPT_NAME,
            'wc_mercadopago_smart_pix_params',
            [
                'site_id' => Country::SITE_ID_MLB,
                'standalone_bridge' => 'enabled',
                'platform_id' => MP_PLATFORM_ID,
                'order_pay_context' => $expectedContext,
            ],
        ], $localized);
    }

    /**
     * @return array<string, array{mixed, array|null}>
     */
    public static function smartPixOrderPayTotalProvider(): array
    {
        $context = static fn(string $amount): array => [
            'amount' => $amount,
            'buyerEmail' => 'buyer@example.com',
            'orderId' => 42,
        ];

        return [
            'two decimals' => ['150.00', $context('150.00')],
            'integer string' => ['150', $context('150.00')],
            'one decimal float' => [150.5, $context('150.50')],
            'zero total' => ['0.00', null],
            'negative total' => ['-1.00', null],
            'sub-cent total' => ['10.005', null],
            'malformed total' => ['abc', null],
        ];
    }

    public function testSmartPixStylesheetHasNoPinnedContentHash(): void
    {
        $tag = '<link rel="stylesheet" href="https://http2.mlstatic.com/bundle.css" />';
        $result = $this->scripts->addSmartPixStyleCrossOrigin($tag, Scripts::SMART_PIX_STYLE_NAME);

        $this->assertSame(
            '<link rel="stylesheet" crossorigin="anonymous" href="https://http2.mlstatic.com/bundle.css" />',
            $result
        );
        $this->assertStringNotContainsString('integrity=', $result);
    }

    public function testSmartPixStylesheetCrossOriginPreservesOtherHandlesAndExistingAttributes(): void
    {
        $tag = '<link rel="stylesheet" href="https://merchant.example/theme.css" />';
        $this->assertSame($tag, $this->scripts->addSmartPixStyleCrossOrigin($tag, 'merchant-theme'));
        $existing = '<link crossorigin="anonymous" href="https://http2.mlstatic.com/bundle.css" />';
        $this->assertSame($existing, $this->scripts->addSmartPixStyleCrossOrigin($existing, Scripts::SMART_PIX_STYLE_NAME));
    }

    /**
     * @return array<int, string>
     */
    private function getTrackedScriptHandles(): array
    {
        $reflection = new \ReflectionProperty(ScriptHealthMonitor::class, 'trackedHandles');
        $reflection->setAccessible(true);

        return $reflection->getValue($this->scriptHealthMonitor);
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
        $dependencies = [];

        if ($client === 'notices') {
            $method = 'registerNoticesAdminScript';
            $handle = 'wc_mercadopago_notices';
            $file = 'fixture/notices/notices-client.js';
            $dependencies = ['wc_mercadopago_native_notices'];
            $expected = array_merge($common, [
                'container' => '#mp-jaiminho-notices-slot', 'public_key' => 'public-fixture', 'platform_id' => MP_PLATFORM_ID,
                'css_url' => 'https://http2.mlstatic.com/storage/v1/plugins/notices/woocommerce.css',
                'js_url' => 'https://http2.mlstatic.com/storage/v1/plugins/notices/woocommerce.js',
                'metrics_url' => 'https://api.mercadopago.com/v1/plugins/notices/metrics',
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
            $expected = array_merge($common, ['type' => 'seller', 'location' => '/settings', 'payment_method' => '', 'client_url' => 'https://http2.mlstatic.com/storage/v1/plugins/melidata/woocommerce.min.js']);
        }
        $callback = $this->captureAction(function () use ($method) {
            $this->scripts->$method();
        }, 'admin_enqueue_scripts');
        $this->assertSame([], $effects->scripts);

        $callback();

        $this->assertSame([[$handle, $file, $dependencies, '1.0.0', true]], $effects->scripts);
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

    /** @param mixed $requestKey */
    private function orderPayContext($requestKey, ?\WC_Order $order, bool $payPage = true): ?array
    {
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => $payPage]);
        WP_Mock::userFunction('get_query_var', ['args' => ['order-pay'], 'return' => '321']);
        WP_Mock::userFunction('wc_get_order', ['return' => $order]);
        WP_Mock::userFunction('wp_unslash', ['return_arg' => 0]);
        WP_Mock::userFunction('sanitize_text_field', ['return_arg' => 0]);
        if ($requestKey === null) {
            unset($_GET['key']);
        } else {
            $_GET['key'] = $requestKey;
        }
        try {
            $context = (new \ReflectionClass(Scripts::class))->getMethod('getSmartPixOrderPayContext');
            $context->setAccessible(true);
            return $context->invoke($this->scripts);
        } finally {
            unset($_GET['key']);
        }
    }

    private function payableOrder(string $total = '125.50', string $email = 'buyer@example.test', int $id = 321): \WC_Order
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_order_key')->andReturn('wc_order_SERVERKEY');
        $order->shouldReceive('get_total')->andReturn($total);
        $order->shouldReceive('get_billing_email')->andReturn($email);
        $order->shouldReceive('get_id')->andReturn($id);
        return $order;
    }

    public function testOrderPayContextExposesTheBuyerOnlyWithTheMatchingOrderKey(): void
    {
        $this->assertSame(
            ['amount' => '125.50', 'buyerEmail' => 'buyer@example.test', 'orderId' => 321],
            $this->orderPayContext('wc_order_SERVERKEY', $this->payableOrder())
        );
    }

    /** @dataProvider refusedOrderPayContextProvider */
    public function testOrderPayContextIsRefusedOutsideItsProof(string $case): void
    {
        $order = $this->payableOrder();
        $key = 'wc_order_SERVERKEY';
        $payPage = true;
        switch ($case) {
            case 'not the pay page':
                $payPage = false;
                break;
            case 'missing key':
                $key = null;
                break;
            case 'different key':
                $key = 'wc_order_OTHERKEY';
                break;
            case 'array key':
                $key = ['wc_order_SERVERKEY'];
                break;
            case 'unknown order':
                $order = null;
                break;
            case 'invalid buyer e-mail':
                $order = $this->payableOrder('125.50', 'not-an-email');
                break;
            case 'zero total':
                $order = $this->payableOrder('0.00');
                break;
            case 'another order':
                $order = $this->payableOrder('125.50', 'buyer@example.test', 999);
                break;
        }

        $this->assertNull($this->orderPayContext($key, $order, $payPage));
    }

    public static function refusedOrderPayContextProvider(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'not the pay page' => 'not the pay page', 'missing key' => 'missing key', 'different key' => 'different key',
            'array key' => 'array key', 'unknown order' => 'unknown order', 'invalid buyer e-mail' => 'invalid buyer e-mail',
            'zero total' => 'zero total', 'another order' => 'another order',
        ]);
    }
}

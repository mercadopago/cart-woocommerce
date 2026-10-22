<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\AbstractBlock;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;
use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Helpers\Cart;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Paths;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks;
use MercadoPago\Woocommerce\Hooks\Blocks;
use MercadoPago\Woocommerce\Hooks\Cart as CartHook;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MP_PLUGIN_FILE')) {
    define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
}

/**
 * Real Block/Gateway/helper methods with doubles only at WordPress/WooCommerce boundaries.
 * Reflection configures containers and gateway state without their unrelated plugin bootstrap.
 * Every concrete Block is constructed normally; no production method is replaced.
 */
abstract class BlockTestCase extends TestCase
{
    use SetNotAccessibleProperty;

    protected string $blockClass;
    protected string $gatewayClass;
    protected string $siteId;
    protected AbstractBlock $block;
    protected AbstractGateway $gateway;
    protected WoocommerceMercadoPago $plugin;
    protected array $options = [];
    private array $registry = [];
    private array $registeredScripts = [];
    private array $renderedTemplates = [];
    private array $previousGlobals = [];

    protected function setUp(): void
    {
        WP_Mock::setUp();
        foreach (['mercadopago', 'woocommerce'] as $name) {
            $this->previousGlobals[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
        }

        $this->options = [
            '_site_id_v1' => $this->siteId,
            '_mp_public_key_prod' => 'synthetic-public-key',
            '_mp_access_token_prod' => 'synthetic-access-token',
            '_mp_public_key_test' => 'synthetic-test-public-key',
            '_mp_access_token_test' => 'synthetic-test-access-token',
            'checkbox_checkout_test_mode' => 'no',
            '_site_id_payment_methods' => [['id' => 'consumer_credits']],
            '_all_payment_methods_ticket' => [
                ['id' => 'bolbradesco', 'name' => 'Boleto', 'secure_thumbnail' => 'https://fixture.test/boleto.svg'],
            ],
            '_payment_methods_pse' => [
                ['id' => 'pse', 'financial_institutions' => [['id' => 'fixture-bank', 'description' => 'Fixture bank']]],
            ],
        ];
        WP_Mock::userFunction('get_option', ['return' => function ($key, $default = false) {
            return $this->options[$key] ?? $default;
        }]);
        WP_Mock::userFunction('update_option', ['return' => function ($key, $value) {
            $this->options[$key] = $value;
            return true;
        }]);
        WP_Mock::userFunction('plugins_url', ['return' => function ($path) {
            return 'https://fixture.test/plugin/' . $path;
        }]);
        WP_Mock::userFunction('admin_url', ['return' => 'https://fixture.test/wp-admin/']);
        WP_Mock::userFunction('home_url', ['return' => 'https://fixture.test/']);
        WP_Mock::userFunction('wp_get_environment_type', ['return' => 'production']);
        WP_Mock::userFunction('get_locale', ['return' => 'en_US']);
        WP_Mock::userFunction('__', ['return' => function ($text) { return $text; }]);
        WP_Mock::userFunction('_x', ['return' => function ($text) { return $text; }]);
        WP_Mock::onFilter('plugin_locale')->with('en_US', 'woocommerce-mercadopago')->reply('en_US');
        foreach (['esc_attr', 'esc_html', 'esc_textarea'] as $function) {
            WP_Mock::userFunction($function, ['return' => function ($text) {
                return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
            }]);
        }
        WP_Mock::userFunction('esc_url', ['return' => function ($url) { return $url; }]);
        WP_Mock::userFunction('esc_js', ['return' => function ($text) { return (string) $text; }]);
        WP_Mock::userFunction('wp_kses_post', ['return' => function ($text) { return $text; }]);
        WP_Mock::userFunction('wp_json_encode', ['return' => function ($value) { return json_encode($value); }]);
        WP_Mock::userFunction('wp_strip_all_tags', ['return' => function ($text) { return strip_tags($text); }]);
        WP_Mock::userFunction('wc_price', ['return' => function ($value) { return (string) $value; }]);
        WP_Mock::userFunction('get_stylesheet', ['return' => 'fixture-theme']);
        WP_Mock::userFunction('get_woocommerce_currency', ['return' => 'COP']);
        WP_Mock::userFunction('woocommerce_store_api_register_update_callback', ['return' => null]);
        foreach ([10, 20] as $priority) {
            WP_Mock::onHookAdded('wp_enqueue_scripts', 'action')
                ->with(Mockery::type('callable'), $priority, 1)->perform(function () {});
        }
        WP_Mock::onHookAdded('enqueue_block_assets', 'action')
            ->with(Mockery::type('callable'), 10, 1)->perform(function () {});
        foreach ([
            'woocommerce_cart_calculate_fees' => 'registerDiscountAndCommissionFeesOnCart',
            'woocommerce_blocks_enqueue_checkout_block_scripts_before' => 'resetCheckoutSession',
        ] as $hook => $method) {
            WP_Mock::onHookAdded($hook, 'action')
                ->with([new \WP_Mock\Matcher\AnyInstance($this->blockClass), $method], 10, 1)->perform(function () {});
        }
        WP_Mock::userFunction('wp_register_script', ['return' => function ($name, $url, $dependencies, $version, $footer) {
            $this->registeredScripts[$name] = compact('url', 'dependencies', 'version', 'footer');
            return true;
        }]);
        WP_Mock::userFunction('wc_get_template_html', ['return' => function ($template, $args, $path, $defaultPath) {
            $this->renderedTemplates[] = [$template, $path, $defaultPath];
            return self::renderWooCommerceTemplate($defaultPath . $template, $args);
        }]);
        WP_Mock::userFunction('wc_get_template', ['return' => function ($template, $args, $path, $defaultPath) {
            echo self::renderWooCommerceTemplate($defaultPath . $template, $args);
        }]);

        $woocommerce = Mockery::mock('WooCommerce');
        $woocommerce->version = 'fixture-wc-version';
        $woocommerce->cart = Mockery::mock('WC_Cart');
        $woocommerce->cart->shouldReceive('__get')->with('total')->andReturn(80.0);
        $woocommerce->cart->shouldReceive('get_cart_contents_total')->andReturn(70.0);
        $woocommerce->cart->shouldReceive('get_cart_contents_tax')->andReturn(10.0);
        $paymentGateways = Mockery::mock('WC_Payment_Gateways');
        $paymentGateways->shouldReceive('payment_gateways')->andReturnUsing(function () { return $this->registry; });
        $woocommerce->shouldReceive('payment_gateways')->andReturn($paymentGateways);
        WP_Mock::userFunction('WC', ['return' => $woocommerce]);
        $GLOBALS['woocommerce'] = $woocommerce;
        $user = Mockery::mock('WP_User');
        $user->ID = 0;
        $user->user_email = '';
        WP_Mock::userFunction('wp_get_current_user', ['return' => $user]);
        Mockery::mock('alias:WC_AJAX')->shouldReceive('get_endpoint')->andReturn('https://fixture.test/?wc-ajax=validation');

        $options = new Options();
        $store = new Store($options);
        $seller = self::withoutBootstrap(Seller::class);
        $this->setNotAccessibleProperty($seller, 'options', $options);
        $this->setNotAccessibleProperty($seller, 'store', $store);
        $country = new Country($seller);
        $url = new Url(new Strings());
        $links = new Links($country, $url);
        $translations = new StoreTranslations($links);
        $currency = self::withoutBootstrap(Currency::class);
        $session = new Session();
        $helpers = self::withoutBootstrap(Helpers::class);
        $helpers->country = $country;
        $helpers->url = $url;
        $helpers->links = $links;
        $helpers->currency = $currency;
        $helpers->cart = new Cart($country, $currency, $session, $translations);
        $helpers->currentUser = self::withoutBootstrap(CurrentUser::class);
        $helpers->paymentMethods = new PaymentMethods($url);

        $hooks = self::withoutBootstrap(Hooks::class);
        $hooks->options = $options;
        $hooks->cart = new CartHook();
        $hooks->blocks = new Blocks();
        $hooks->scripts = new Scripts($url, $seller, $helpers->paymentMethods, self::withoutBootstrap(ScriptHealthMonitor::class));
        $hooks->gateway = self::withoutBootstrap(Gateway::class);
        $this->setNotAccessibleProperty($hooks->gateway, 'translations', $translations);

        $this->plugin = self::withoutBootstrap(WoocommerceMercadoPago::class);
        $this->plugin->woocommerce = $woocommerce;
        $this->plugin->hooks = $hooks;
        $this->plugin->helpers = $helpers;
        $this->plugin->sellerConfig = $seller;
        $this->plugin->storeConfig = $store;
        $this->plugin->storeTranslations = $translations;
        $GLOBALS['mercadopago'] = $this->plugin;

        $this->gateway = $this->configuredGateway($this->gatewayClass);
        $decoyClass = $this->gatewayClass === BasicGateway::class ? PixGateway::class : BasicGateway::class;
        $decoy = $this->configuredGateway($decoyClass);
        $decoy->icon = 'https://fixture.test/decoy.svg';
        $this->registry = [$decoy->id => $decoy, $this->gateway->id => $this->gateway];
        $this->options['woocommerce_' . $this->gateway->id . '_settings'] = [
            'title' => 'Configured method title', 'description' => 'Configured method description',
        ];
        $this->block = new $this->blockClass();
        $this->block->initialize();
    }

    protected function tearDown(): void
    {
        try {
            try {
                WP_Mock::tearDown();
            } finally {
                Mockery::close();
            }
        } finally {
            foreach ($this->previousGlobals as $name => [$existed, $value]) {
                if ($existed) {
                    $GLOBALS[$name] = $value;
                } else {
                    unset($GLOBALS[$name]);
                }
            }
        }
    }

    protected function assertIdentityAndDataEnvelope(string $expectedName): array
    {
        $this->assertSame($expectedName, $this->block->get_name());
        $this->assertSame($this->gateway, $this->block->setGateway());
        $data = $this->block->get_payment_method_data();
        $this->assertSame('Configured method title', $data['title']);
        $this->assertSame('Configured method description', $data['description']);
        $this->assertSame(['products', 'refunds'], $data['supports']);
        $this->assertSame('', $data['params']['fee_title']);
        return $data['params'];
    }

    protected function assertScriptContract(string $expectedHandle, string $expectedFile, string $expectedAsset): void
    {
        $this->assertSame([$expectedHandle], $this->block->get_payment_method_script_handles());
        $this->assertSame('https://fixture.test/plugin/build/' . $expectedFile, $this->registeredScripts[$expectedHandle]['url']);
        $this->assertSame([
            'react', 'wc-blocks-checkout', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities',
        ], $this->registeredScripts[$expectedHandle]['dependencies']);
        $asset = require Paths::buildPath($expectedAsset);
        $this->assertSame($asset['version'], $this->registeredScripts[$expectedHandle]['version']);
        $this->assertNotSame('', $this->registeredScripts[$expectedHandle]['version']);
        $this->assertTrue($this->registeredScripts[$expectedHandle]['footer']);
    }

    protected function assertGatewayAbsenceDisablesBlock(): void
    {
        unset($this->registry[$this->gateway->id]);
        $block = new $this->blockClass();
        $block->initialize();
        $this->assertFalse($block->is_active());
        $this->assertSame([], $block->get_payment_method_script_handles());
        $this->assertSame([], $this->registeredScripts);
    }

    protected function assertMissingCredentialDisablesBlock(string $credentialOption, bool $testMode): void
    {
        $this->options['checkbox_checkout_test_mode'] = $testMode ? 'yes' : 'no';
        $this->options[$credentialOption] = '';
        $this->assertFalse($this->block->is_active());
    }

    public function credentialProvider(): array
    {
        return [
            'production public key' => ['_mp_public_key_prod', false],
            'production access token' => ['_mp_access_token_prod', false],
            'test public key' => ['_mp_public_key_test', true],
            'test access token' => ['_mp_access_token_test', true],
        ];
    }

    protected function assertTemplateUsed(string $expectedTemplate): void
    {
        $this->assertSame([[$expectedTemplate, '', Paths::templatesPath()]], $this->renderedTemplates);
    }

    protected function setSite(string $siteId): void
    {
        $this->plugin->sellerConfig->setSiteId($siteId);
    }

    private function configuredGateway(string $class): AbstractGateway
    {
        $gateway = self::withoutBootstrap($class);
        $gateway->id = $class::ID;
        $gateway->mercadopago = $this->plugin;
        $gateway->icon = 'https://fixture.test/method.svg';
        $gateway->iconAdmin = 'https://fixture.test/admin-method.svg';
        $gateway->supports = ['products', 'refunds'];
        $gateway->discount = 0.0;
        $gateway->commission = 0.0;
        $gateway->settings = ['method' => 'redirect', 'enabled' => 'yes'];
        $name = strtolower(str_replace(['MercadoPago\\Woocommerce\\Gateways\\', 'Gateway'], '', $class));
        $gateway->storeTranslations = $this->plugin->storeTranslations->{$name . 'Checkout'};
        $this->setNotAccessibleProperty($gateway, 'countryConfigs', $this->plugin->helpers->country->getCountryConfigs());
        $this->setNotAccessibleProperty($gateway, 'links', $this->plugin->helpers->links->getLinks());
        $this->plugin->helpers->currency->setRatio($gateway->id, 1.25);
        return $gateway;
    }

    private static function withoutBootstrap(string $class)
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    private static function renderWooCommerceTemplate(string $file, array $args): string
    {
        extract($args, EXTR_SKIP);
        ob_start();
        try {
            include $file;
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

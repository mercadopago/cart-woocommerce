<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Paths;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;

if (!defined('ABSPATH')) {
    exit;
}

class Scripts
{
    private const SUFFIX = '_params';

    private const MELIDATA_SCRIPT_NAME = 'mercadopago_melidata';

    private const MELIDATA_CLIENT_URL = 'https://http2.mlstatic.com/storage/v1/plugins/melidata/woocommerce.min.js';

    private const WOOCCOMMERCE_SCRIPTS_SCRIPT_NAME = 'wc_mercadopago_woocommerce_scripts';

    private const CARONTE_SCRIPT_NAME = 'wc_mercadopago';

    private const NOTICES_SCRIPT_NAME = 'wc_mercadopago_notices';

    private const NATIVE_NOTICES_SCRIPT_NAME = 'wc_mercadopago_native_notices';

    private const HEALTH_MONITOR_SCRIPT_NAME = 'wc_mercadopago_health_monitor';

    public const FAST_PAYMENTS_SCRIPT_NAME = 'wc_mercadopago_supertoken';

    public const FAST_PAYMENTS_BUNDLE_PARAMS_OBJECT = 'wc_mercadopago_supertoken_bundle_params';

    private ?string $fastPaymentsEnvironment = null;

    private bool $fastPaymentsRegistered = false;

    /** @var array<string, mixed> */
    private array $fastPaymentsBundleParams = [];

    public const SMART_PIX_ENV_HOMOLOGATION = 'homologation';

    public const SMART_PIX_ENV_PRODUCTION = 'production';

    public const SMART_PIX_SCRIPT_NAME = 'wc_mercadopago_smart_pix';

    public const SMART_PIX_STYLE_NAME = 'wc_mercadopago_smart_pix';

    private const SMART_PIX_ASSET_VERSION = 'v1';

    private const SMART_PIX_LOCAL_SCRIPT_PATH = 'checkouts/smart-pix/smart-pix.bundle';

    private const SMART_PIX_LOCAL_STYLE_PATH = 'checkouts/smart-pix/smart-pix.bundle';

    private const SMART_PIX_ASSETS = [
        self::SMART_PIX_ENV_HOMOLOGATION => [
            'script_url' => 'https://http2.mlstatic.com/storage/homol/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.js',
            'style_url' => 'https://http2.mlstatic.com/storage/homol/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.css',
        ],
        self::SMART_PIX_ENV_PRODUCTION => [
            'script_url' => 'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.js',
            'style_url' => 'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/smart-pix/v1/smart-pix.bundle.min.css',
        ],
    ];

    private Url $url;

    private Seller $seller;

    private PaymentMethods $paymentMethods;

    public ScriptHealthMonitor $scriptHealthMonitor;

    /**
     * Scripts constructor
     *
     * @param Url $url
     * @param Seller $seller
     * @param PaymentMethods $paymentMethods
     * @param ScriptHealthMonitor $scriptHealthMonitor
     */
    public function __construct(Url $url, Seller $seller, PaymentMethods $paymentMethods, ScriptHealthMonitor $scriptHealthMonitor)
    {
        $this->url    = $url;
        $this->seller = $seller;
        $this->paymentMethods = $paymentMethods;
        $this->scriptHealthMonitor = $scriptHealthMonitor;
    }

    /**
     * Register styles on admin
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    public function registerAdminStyle(string $name, string $file): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
    }

    /**
     * Register scripts on admin
     *
     * @param string $name
     * @param string $file
     * @param array $variables
     *
     * @return void
     */
    public function registerAdminScript(string $name, string $file, array $variables = [], array $deps = []): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file, $variables, $deps) {
            $this->registerScript($name, $file, $variables, $deps);
        });
    }

    /**
     * Register styles on checkout
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    public function registerCheckoutStyle(string $name, string $file): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file) {
            if ($this->isPaymentsRelatedPage()) {
                $this->registerStyle($name, $file);
            }
        });
    }

    /**
     * Register scripts on checkout
     *
     * @param string $name
     * @param string $file
     * @param array $variables
     *
     * @return void
     */
    public function registerCheckoutScript(string $name, string $file, array $variables = [], array $deps = []): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables, $deps) {
            if ($this->isPaymentsRelatedPage()) {
                $this->registerScript($name, $file, $variables, $deps);
                $this->scriptHealthMonitor->trackEnqueued($name);
            }
        });
    }

    /**
     * Register the Custom Gateway CDN loader once.
     *
     * @param string $environment
     * @param array<string, mixed> $bundleParams Super Token runtime data localized as
     *        wc_mercadopago_supertoken_bundle_params.
     * @return void
     */
    public function registerFastPaymentsCheckoutAssets(string $environment, array $bundleParams = []): void
    {
        if (!in_array($environment, [self::SMART_PIX_ENV_HOMOLOGATION, self::SMART_PIX_ENV_PRODUCTION], true)) {
            return;
        }

        if ($this->fastPaymentsEnvironment !== null && $this->fastPaymentsEnvironment !== $environment) {
            return;
        }

        $this->fastPaymentsEnvironment = $environment;
        $this->fastPaymentsBundleParams = $bundleParams;
        if ($this->fastPaymentsRegistered) {
            return;
        }

        $this->fastPaymentsRegistered = true;
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueFastPaymentsCheckoutAssets();
        });
    }

    /**
     * Localize capabilities before the loader runs. CDN destinations remain fixed in the loader.
     *
     * @return void
     */
    public function enqueueFastPaymentsCheckoutAssets(): void
    {
        if ($this->fastPaymentsEnvironment === null || !$this->isPaymentFormPage()) {
            return;
        }

        $this->registerScript(
            self::FAST_PAYMENTS_SCRIPT_NAME,
            $this->url->getJsAsset('checkouts/super-token-loader'),
            ['environment' => $this->fastPaymentsEnvironment],
            ['wc_mercadopago_sdk']
        );

        if ($this->fastPaymentsBundleParams) {
            wp_localize_script(
                self::FAST_PAYMENTS_SCRIPT_NAME,
                self::FAST_PAYMENTS_BUNDLE_PARAMS_OBJECT,
                $this->fastPaymentsBundleParams
            );
        }

        $this->scriptHealthMonitor->trackEnqueued(self::FAST_PAYMENTS_SCRIPT_NAME);
    }

    /**
     * Register Smart Pix assets selected exclusively by the server.
     *
     * Unknown environments fail closed: no remote URL is accepted from the browser or request.
     * The browser waits for a loaded stylesheet before replacing conventional Pix.
     *
     * @param string $environment
     *
     * @return void
     */
    public function registerSmartPixCheckoutAssets(string $environment): void
    {
        if (!isset(self::SMART_PIX_ASSETS[$environment])) {
            return;
        }

        add_filter('style_loader_tag', [$this, 'addSmartPixStyleCrossOrigin'], 10, 2);

        add_action('wp_enqueue_scripts', function () use ($environment) {
            $this->enqueueSmartPixCheckoutAssets($environment);
        });
    }

    /**
     * Register a script only where the checkout payment form can render.
     */
    public function registerPaymentFormScript(string $name, string $file, array $variables = [], array $deps = [], string $objectName = ''): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables, $deps, $objectName) {
            if ($this->isPaymentFormPage()) {
                $this->registerScript($name, $file, $variables, $deps, $objectName);
                $this->scriptHealthMonitor->trackEnqueued($name);
            }
        });
    }

    /**
     * Enqueue one allow-listed Smart Pix environment on payment-related pages.
     *
     * Kept separate from hook registration so the fixed URL/dependency contract is testable
     * without invoking a captured WordPress closure.
     *
     * @param string $environment
     *
     * @return void
     */
    public function enqueueSmartPixCheckoutAssets(string $environment): void
    {
        if (!isset(self::SMART_PIX_ASSETS[$environment]) || !$this->isPaymentsRelatedPage()) {
            return;
        }

        $this->enqueueSmartPixBootstrapAssets();

        $assets = self::SMART_PIX_ASSETS[$environment];

        wp_enqueue_style(
            self::SMART_PIX_STYLE_NAME,
            $assets['style_url'],
            [],
            self::SMART_PIX_ASSET_VERSION
        );
        wp_enqueue_script(
            self::SMART_PIX_SCRIPT_NAME,
            $assets['script_url'],
            ['wc_mercadopago_sdk'],
            self::SMART_PIX_ASSET_VERSION,
            true
        );
        wp_localize_script(self::SMART_PIX_SCRIPT_NAME, 'wc_mercadopago_smart_pix_params', [
            'site_id' => Country::SITE_ID_MLB,
            'standalone_bridge' => in_array(CustomGateway::ID, $this->paymentMethods->getEnabledPaymentMethods(), true)
                ? 'disabled' : 'enabled',
            'platform_id'      => MP_PLATFORM_ID,
            'order_pay_context' => $this->getSmartPixOrderPayContext(),
        ]);
        $this->scriptHealthMonitor->trackEnqueued(self::SMART_PIX_SCRIPT_NAME);
    }

    /**
     * The plugin-local Smart Pix build is git-ignored and never shipped in the release ZIP, so it
     * may be selected only when both built files exist on disk; otherwise its URLs would 404.
     *
     * @param string|null $pluginRoot Plugin directory; defaults to the one containing this file.
     */
    public function hasSmartPixLocalCheckoutAssets(?string $pluginRoot = null): bool
    {
        $pluginRoot ??= dirname(__DIR__, 2);

        return is_file($pluginRoot . '/assets/js/' . self::SMART_PIX_LOCAL_SCRIPT_PATH . '.min.js')
            && is_file($pluginRoot . '/assets/css/' . self::SMART_PIX_LOCAL_STYLE_PATH . '.min.css');
    }

    /**
     * Load the real, plugin-local Smart Pix build independently of the Orders environment.
     */
    public function registerSmartPixLocalCheckoutAssets(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueSmartPixLocalCheckoutAssets();
        });
    }

    public function enqueueSmartPixLocalCheckoutAssets(): void
    {
        if (!$this->isPaymentsRelatedPage()) {
            return;
        }

        $this->enqueueSmartPixBootstrapAssets();

        wp_enqueue_style(
            self::SMART_PIX_STYLE_NAME,
            $this->url->getCssAsset(self::SMART_PIX_LOCAL_STYLE_PATH),
            [],
            $this->url->assetVersion()
        );
        wp_enqueue_script(
            self::SMART_PIX_SCRIPT_NAME,
            $this->url->getJsAsset(self::SMART_PIX_LOCAL_SCRIPT_PATH),
            ['wc_mercadopago_sdk'],
            $this->url->assetVersion(),
            true
        );
        wp_localize_script(self::SMART_PIX_SCRIPT_NAME, 'wc_mercadopago_smart_pix_params', [
            'site_id' => Country::SITE_ID_MLB,
            'standalone_bridge' => in_array(CustomGateway::ID, $this->paymentMethods->getEnabledPaymentMethods(), true)
                ? 'disabled' : 'enabled',
            'platform_id' => MP_PLATFORM_ID,
            'order_pay_context' => $this->getSmartPixOrderPayContext(),
        ]);
        $this->scriptHealthMonitor->trackEnqueued(self::SMART_PIX_SCRIPT_NAME);
    }

    /** First-paint presentation must not wait for the SDK or the independently delivered bundle. */
    private function enqueueSmartPixBootstrapAssets(): void
    {
        wp_enqueue_style(
            'wc_mercadopago_pix_bootstrap',
            $this->url->getCssAsset('checkouts/mp-pix-bootstrap'),
            [],
            $this->url->assetVersion()
        );
        wp_enqueue_script(
            'wc_mercadopago_pix_bootstrap',
            $this->url->getJsAsset('checkouts/pix/mp-pix-bootstrap'),
            [],
            $this->url->assetVersion(),
            false
        );
    }

    /**
     * Read the minimum Order Pay context from the server-side WC_Order.
     *
     * @return array{amount: string, buyerEmail: string, orderId: int}|null
     */
    private function getSmartPixOrderPayContext(): ?array
    {
        if (!is_checkout_pay_page()) {
            return null;
        }

        $orderId = (int) get_query_var('order-pay');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($orderId <= 0 || !isset($_GET['key']) || !is_string($_GET['key'])) {
            return null;
        }

        $order = wc_get_order($orderId);

        if (!$order instanceof \WC_Order) {
            return null;
        }

        // The WooCommerce order key is the Order Pay authorization proof, not a CSRF nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requestOrderKey = sanitize_text_field(wp_unslash($_GET['key']));
        $storedOrderKey  = (string) $order->get_order_key();

        if (
            $requestOrderKey === ''
            || $storedOrderKey === ''
            || !hash_equals($storedOrderKey, $requestOrderKey)
        ) {
            return null;
        }

        // Exact canonical BRL total; sub-cent, negative or malformed totals yield no context.
        $amount     = SmartPixDecimal::canonical((string) $order->get_total());
        $buyerEmail = trim((string) $order->get_billing_email());
        $resolvedId = (int) $order->get_id();

        if (
            $amount === null
            || $amount === '0.00'
            || filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) === false
            || $resolvedId !== $orderId
        ) {
            return null;
        }

        return [
            'amount'     => $amount,
            'buyerEmail' => $buyerEmail,
            'orderId'    => $resolvedId,
        ];
    }

    /**
     * Keep the loaded stylesheet readable by the existing browser readiness check.
     * This adds no content hash and does not validate the downloaded bytes.
     */
    public function addSmartPixStyleCrossOrigin(string $tag, string $handle): string
    {
        if ($handle !== self::SMART_PIX_STYLE_NAME || preg_match('/\scrossorigin\s*=/i', $tag)) {
            return $tag;
        }

        return str_replace(' href=', ' crossorigin="anonymous" href=', $tag);
    }

    /**
     * Register a stylesheet only where the checkout payment form can render.
     */
    public function registerPaymentFormStyle(string $name, string $file): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file) {
            if ($this->isPaymentFormPage()) {
                $this->registerStyle($name, $file);
            }
        });
    }

    private function isPaymentFormPage(): bool
    {
        // A combined cart/checkout is valid; order confirmation also matches is_checkout().
        return !is_view_order_page() && !is_order_received_page() &&
            (is_checkout() || is_checkout_pay_page() || (bool) get_query_var('order-pay'));
    }

    /**
     * Returns if the user is on a payments related page
     *
     * @return bool
     */
    private function isPaymentsRelatedPage(): bool
    {
        return is_view_order_page() || is_cart() || is_order_received_page() || is_checkout_pay_page() ||
            is_add_payment_method_page() || is_checkout() || get_query_var('order-pay');
    }

    /**
     * Register styles on store
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    public function registerStoreStyle(string $name, string $file): void
    {
        $this->registerStyle($name, $file);
    }

    /**
     * Register scripts on store
     *
     * @param string $name
     * @param string $file
     * @param array $variables
     * @param array $deps
     *
     * @return void
     */
    public function registerStoreScript(string $name, string $file, array $variables = [], array $deps = []): void
    {
        $this->registerScript($name, $file, $variables, $deps);
    }

    /**
     * Register notices script on admin
     *
     * @return void
     */
    public function registerNoticesAdminScript(bool $useSharedRegion = true): void
    {
        global $woocommerce;

        $file      = $this->url->getJsAsset('notices/notices-client');
        $variables = [
            'site_id'          => $this->seller->getSiteId() ?: Country::SITE_ID_MLA,
            'container'        => $useSharedRegion ? '#mp-jaiminho-notices-slot' : '#wpbody-content',
            'public_key'       => $this->seller->getCredentialsPublicKey(),
            'plugin_version'   => MP_VERSION,
            'platform_id'      => MP_PLATFORM_ID,
            'platform_version' => $woocommerce->version,
            'css_url'          => 'https://http2.mlstatic.com/storage/v1/plugins/notices/woocommerce.css',
            'js_url'           => 'https://http2.mlstatic.com/storage/v1/plugins/notices/woocommerce.js',
            'metrics_url'      => 'https://api.mercadopago.com/v1/plugins/notices/metrics',
        ];

        $dependencies = $useSharedRegion ? [self::NATIVE_NOTICES_SCRIPT_NAME] : [];
        $this->registerAdminScript(self::NOTICES_SCRIPT_NAME, $file, $variables, $dependencies);
    }

    public function registerNativeNoticesAdminScript(array $variables): void
    {
        $this->registerAdminStyle(
            'wc_mercadopago_native_notices_css',
            $this->nativeNoticeAssetUrl(
                $this->url->getCssAsset('admin/mp-admin-notices-layout'),
                'assets/css/admin/mp-admin-notices-layout.min.css'
            )
        );
        $this->registerAdminScript(
            self::NATIVE_NOTICES_SCRIPT_NAME,
            $this->nativeNoticeAssetUrl(
                $this->url->getJsAsset('admin/mp-admin-notices-layout'),
                'assets/js/admin/mp-admin-notices-layout.min.js'
            ),
            $variables
        );
    }

    private function nativeNoticeAssetUrl(string $url, string $assetPath): string
    {
        $path = Paths::basePath($assetPath);
        if (!is_file($path) || !is_readable($path)) {
            return $url;
        }

        // The asset may disappear or become unreadable after the checks above.
        $revision = @hash_file('sha256', $path);
        return $revision === false ? $url : $this->url->setQueryVar('mp_native_rev', substr($revision, 0, 12), $url);
    }

    /**
     * Register credits script on admin
     *
     * @param string $name
     * @param string $file
     * @param array $variables
     *
     * @return void
     */
    public function registerCreditsAdminScript(string $name, string $file, array $variables = []): void
    {
        if ($this->url->validateSection('woo-mercado-pago-credits')) {
            $this->registerAdminScript($name, $file, $variables);
        }
    }

    /**
     * Register credits style on admin
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    public function registerCreditsAdminStyle(string $name, string $file): void
    {
        if ($this->url->validateSection('woo-mercado-pago-credits')) {
            $this->registerAdminStyle($name, $file);
        }
    }

    /**
     * Register caronte script on admin
     *
     * @return void
     */
    public function registerCaronteAdminScript(): void
    {
        global $woocommerce;

        $file      = $this->url->getJsAsset('caronte/caronte-client');
        $variables = [
            'locale'                => get_locale(),
            'site_id'               => $this->seller->getSiteId() ?: Country::SITE_ID_MLA,
            'plugin_version'        => MP_VERSION,
            'platform_id'           => MP_PLATFORM_ID,
            'platform_version'      => $woocommerce->version,
            'public_key_element_id' => 'mp-public-key-prod',
            'reference_element_id'  => 'reference'
        ];

        $this->registerAdminScript(self::CARONTE_SCRIPT_NAME, $file, $variables);
    }

    /**
     * Register melidata scripts on admin
     *
     * @return void
     */
    public function registerMelidataAdminScript(): void
    {
        $this->registerMelidataScript('seller', '/settings');
    }

    /**
     * Register melidata script on store
     *
     * @param string $location
     * @param string $paymentMethod
     *
     * @return void
     */
    public function registerMelidataStoreScript(string $location, string $paymentMethod = ''): void
    {
        $this->registerMelidataScript('buyer', $location, $paymentMethod);
    }

    public function prioritizeMelidataStoreScriptEarly(string $location = ''): void
    {
        $file = $this->url->getJsAsset('melidata/melidata-client');
        $clientUrl = $this->getMelidataClientUrl();

        add_action('wp_enqueue_scripts', function () use ($file, $location, $clientUrl) {
            if ($this->isPaymentsRelatedPage() && !is_cart() && !is_view_order_page()) {
                $deps = array_merge(
                    wp_script_is('wc_mercadopago_sdk', 'registered') ? ['wc_mercadopago_sdk'] : [],
                    wp_script_is('wc-blocks-registry', 'registered') ? ['wc-blocks-registry'] : []
                );
                wp_enqueue_script(
                    self::MELIDATA_SCRIPT_NAME,
                    $file,
                    $deps,
                    $this->url->assetVersion(),
                    true
                );

                if ($location) {
                    global $woocommerce;

                    $variables = [
                        'type'             => 'buyer',
                        'site_id'          => $this->seller->getSiteId() ?: "not_available",
                        'location'         => $location,
                        'payment_method'   => '',
                        'plugin_version'   => MP_VERSION,
                        'platform_version' => $woocommerce->version,
                        'payment_methods'  => $this->paymentMethods->getEnabledPaymentMethods(),
                        'client_url'       => $clientUrl,
                    ];

                    wp_localize_script(
                        self::MELIDATA_SCRIPT_NAME,
                        self::MELIDATA_SCRIPT_NAME . self::SUFFIX,
                        $variables
                    );
                }
            }
        }, 20);
    }

    /**
     * Register melidata scripts
     *
     * @param string $type
     * @param string $location
     * @param string $paymentMethod
     *
     * @return void
     */
    private function registerMelidataScript(string $type, string $location, string $paymentMethod = ''): void
    {
        global $woocommerce;

        $file      = $this->url->getJsAsset('melidata/melidata-client');

        $variables = [
            'type'             => $type,
            'site_id'          => $this->seller->getSiteId() ?: Country::SITE_ID_MLA,
            'location'         => $location,
            'payment_method'   => $paymentMethod,
            'plugin_version'   => MP_VERSION,
            'platform_version' => $woocommerce->version,
            'client_url'       => $this->getMelidataClientUrl(),
        ];

        if ($type == 'buyer') {
            /**
             * Params below used on melidata-client events, don't remove
            */
            $variables['payment_methods'] = $this->paymentMethods->getEnabledPaymentMethods();
        }

        if ($type == 'seller') {
            $this->registerAdminScript(self::MELIDATA_SCRIPT_NAME, $file, $variables);
            return;
        }

        $deps = wp_script_is('wc-blocks-registry', 'registered') ? ['wc-blocks-registry'] : [];
        $this->registerStoreScript(self::MELIDATA_SCRIPT_NAME, $file, $variables, $deps);
    }

    private function getMelidataClientUrl(): string
    {
        return self::MELIDATA_CLIENT_URL;
    }

    public function registerMpBehaviorTrackingScript(): void
    {
        global $woocommerce;

        $file = $this->url->getJsAsset('scripts/mp-behavior-tracking');

        $variables = [
            'site_id'          => $this->seller->getSiteId() ?: Country::SITE_ID_MLA,
            'cust_id'          => $this->seller->getCustIdFromAT() ?: '',
            'theme'            => get_stylesheet(),
            'plugin_version'   => MP_VERSION,
            'platform_version' => $woocommerce->version,
        ];

        $this->registerCheckoutScript(self::WOOCCOMMERCE_SCRIPTS_SCRIPT_NAME, $file, $variables);
    }

    /**
     * Register health monitor script on checkout pages.
     * Detects CSS conflicts and missing JS globals caused by third-party customizations.
     *
     * @return void
     */
    public function registerHealthMonitorCheckoutScript(): void
    {
        global $woocommerce;
        $file = $this->url->getJsAsset('health/mp-health-monitor');

        add_action('wp_enqueue_scripts', function () use ($woocommerce, $file) {
            if (!$this->isPaymentsRelatedPage()) {
                return;
            }

            $is_checkout_block   = has_block('woocommerce/checkout');
            $is_checkout_classic = is_checkout();
            $is_order_pay        = is_checkout_pay_page();

            $variables = [
                'site_id'          => $this->seller->getSiteId() ?: 'not_available',
                'theme'            => get_stylesheet(),
                'plugin_version'   => MP_VERSION,
                'platform_version' => $woocommerce->version,
                'cust_id'          => $this->seller->getCustIdFromAT() ?: '',
                'is_test'          => $this->seller->isTestUser(),
                'is_checkout'      => ($is_checkout_block || $is_checkout_classic || $is_order_pay) && !is_order_received_page(),
                'payment_methods'  => $this->paymentMethods->getEnabledPaymentMethods(),
            ];

            $this->registerScript(self::HEALTH_MONITOR_SCRIPT_NAME, $file, $variables);
            $this->scriptHealthMonitor->trackEnqueued(self::HEALTH_MONITOR_SCRIPT_NAME);
        });
    }

    /**
     * Register scripts for payment block
     *
     * @param string $name
     * @param string $file
     * @param string $version
     * @param array $deps
     * @param array $variables
     *
     * @return void
     */
    public function registerPaymentBlockScript(string $name, string $file, string $version, array $deps = [], array $variables = []): void
    {
        wp_register_script($name, $file, $deps, $version, true);
        if ($variables) {
            wp_localize_script($name, $name . self::SUFFIX, $variables);
        }
    }

    public function registerPaymentBlockStyle(string $name, string $file): void
    {
        add_action('enqueue_block_assets', fn() => $this->registerStyle($name, $file));
    }

    /**
     * Register styles
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    private function registerStyle(string $name, string $file): void
    {
        wp_register_style($name, $file, [], $this->url->assetVersion());
        wp_enqueue_style($name);
    }

    /**
     * Register scripts
     *
     * @param string $name
     * @param string $file
     * @param array $variables
     *
     * @return void
     */
    private function registerScript(string $name, string $file, array $variables = [], array $deps = [], string $objectName = ''): void
    {
        wp_enqueue_script($name, $file, $deps, $this->url->assetVersion(), true);

        if ($variables) {
            wp_localize_script($name, $objectName ?: $name . self::SUFFIX, $variables);
        }
    }
}

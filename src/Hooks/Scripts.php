<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\HealthMonitor\ScriptHealthMonitor;

if (!defined('ABSPATH')) {
    exit;
}

class Scripts
{
    private const SUFFIX = '_params';

    private const MELIDATA_SCRIPT_NAME = 'mercadopago_melidata';

    private const WOOCCOMMERCE_SCRIPTS_SCRIPT_NAME = 'wc_mercadopago_woocommerce_scripts';

    private const CARONTE_SCRIPT_NAME = 'wc_mercadopago';

    private const NOTICES_SCRIPT_NAME = 'wc_mercadopago_notices';

    private const HEALTH_MONITOR_SCRIPT_NAME = 'wc_mercadopago_health_monitor';

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
    public function registerAdminScript(string $name, string $file, array $variables = []): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
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
    public function registerCheckoutScript(string $name, string $file, array $variables = []): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables) {
            if ($this->isPaymentsRelatedPage()) {
                $this->registerScript($name, $file, $variables);
                $this->scriptHealthMonitor->trackEnqueued($name);
            }
        });
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
     *
     * @return void
     */
    public function registerStoreScript(string $name, string $file, array $variables = []): void
    {
        $this->registerScript($name, $file, $variables);
    }

    /**
     * Register notices script on admin
     *
     * @return void
     */
    public function registerNoticesAdminScript(): void
    {
        global $woocommerce;

        $file      = $this->url->getJsAsset('notices/notices-client');
        $variables = [
            'site_id'          => $this->seller->getSiteId() ?: Country::SITE_ID_MLA,
            'container'        => '#wpbody-content',
            'public_key'       => $this->seller->getCredentialsPublicKey(),
            'plugin_version'   => MP_VERSION,
            'platform_id'      => MP_PLATFORM_ID,
            'platform_version' => $woocommerce->version,
        ];

        $this->registerAdminScript(self::NOTICES_SCRIPT_NAME, $file, $variables);
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

        add_action('wp_enqueue_scripts', function () use ($file, $location) {
            if ($this->isPaymentsRelatedPage()) {
                wp_enqueue_script(
                    self::MELIDATA_SCRIPT_NAME,
                    $file,
                    wp_script_is('wc_mercadopago_sdk', 'registered')
                            ? ['wc_mercadopago_sdk']
                            : [],
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

        $this->registerStoreScript(self::MELIDATA_SCRIPT_NAME, $file, $variables);
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
    private function registerScript(string $name, string $file, array $variables = []): void
    {
        wp_enqueue_script($name, $file, [], $this->url->assetVersion(), true);

        if ($variables) {
            wp_localize_script($name, $name . self::SUFFIX, $variables);
        }
    }
}

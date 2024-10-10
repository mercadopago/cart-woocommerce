<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Environment;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Configs\Seller;

if (!defined('ABSPATH')) {
    exit;
}

class Scripts
{
    private const SUFFIX = '_params';

    private const MELIDATA_SCRIPT_NAME = 'mercadopago_melidata';

    private const CARONTE_SCRIPT_NAME = 'wc_mercadopago';

    private const NOTICES_SCRIPT_NAME = 'wc_mercadopago_notices';

    private Url $url;

    private Seller $seller;

    /**
     * Scripts constructor
     *
     * @param Url $url
     * @param Seller $seller
     */
    public function __construct(Url $url, Seller $seller)
    {
        $this->url    = $url;
        $this->seller = $seller;
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
            $this->registerStyle($name, $file);
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
            $this->registerScript($name, $file, $variables);
        });
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

        if ($type == 'seller') {
            $this->registerAdminScript(self::MELIDATA_SCRIPT_NAME, $file, $variables);
            return;
        }

        $this->registerStoreScript(self::MELIDATA_SCRIPT_NAME, $file, $variables);
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
        wp_register_style($name, $file, false, $this->assetVersion());
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
        wp_enqueue_script($name, $file, [], $this->assetVersion(), true);

        if ($variables) {
            wp_localize_script($name, $name . self::SUFFIX, $variables);
        }
    }

    /**
     * Determines the version value to be used on scripts / styles
     **/
    private function assetVersion(): string
    {
        return Arrays::filterJoin([MP_VERSION, Environment::isDevelopmentEnvironment() ? time() : false], '.');
    }
}

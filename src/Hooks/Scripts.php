<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Url;

if (!defined('ABSPATH')) {
    exit;
}

class Scripts
{
    /**
     * @const
     */
    private const SUFFIX = '_params';

    /**
     * @const
     */
    private const MELIDATA_SCRIPT_NAME = 'mercadopago_melidata';

    /**
     * @const
     */
    private const CARONTE_SCRIPT_NAME = 'wc_mercadopago';

    /**
     * @const
     */
    private const NOTICES_SCRIPT_NAME = 'wc_mercadopago_notices';

    /**
     * @var Scripts
     */
    private static $instance = null;

    /**
     * Get Scripts Hooks instance
     *
     * @return Scripts
     */
    public static function getInstance(): Scripts
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
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
     * Register styles on store
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    public function registerStoreStyle(string $name, string $file): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
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
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
        });
    }

    /**
     * Register notices script on admin
     *
     * @return void
     */
    public function registerNoticesAdminScript(): void
    {
        global $woocommerce;

        $file      = Url::getPluginFileUrl('assets/js/notices/notices-client', '.js');
        $variables = [
            'site_id'          => 'MLA',
            'container'        => '#wpbody-content',
            'public_key'       => '',
            'plugin_version'   => MP_VERSION,
            'platform_id'      => MP_PLATFORM_ID,
            'platform_version' => $woocommerce->version,
        ];

        $this->registerAdminScript(self::NOTICES_SCRIPT_NAME, $file, $variables);
    }

    /**
     * Register caronte script on admin
     *
     * @return void
     */
    public function registerCaronteAdminScript(): void
    {
        global $woocommerce;

        $file      = Url::getPluginFileUrl('assets/js/caronte/caronte-client', '.js');
        $variables = [
            'site_id'               => 'MLA',
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
     *
     * @return void
     */
    public function registerMelidataStoreScript(string $location): void
    {
        $this->registerMelidataScript('buyer', $location);
    }

    /**
     * Register melidata scripts
     *
     * @param string $type
     * @param string $location
     *
     * @return void
     */
    private function registerMelidataScript(string $type, string $location): void
    {
        global $woocommerce;

        $file      = Url::getPluginFileUrl('assets/js/melidata/melidata-client', '.js');
        $variables = [
            'type'             => $type,
            'site_id'          => 'MLA',
            'location'         => $location,
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
     * Register styles
     *
     * @param string $name
     * @param string $file
     *
     * @return void
     */
    private function registerStyle(string $name, string $file): void
    {
        wp_register_style($name, $file, false, MP_VERSION);
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
        wp_enqueue_script($name, $file, array(), MP_VERSION, true);

        if ($variables) {
            wp_localize_script($name, $name . self::SUFFIX, $variables);
        }
    }
}

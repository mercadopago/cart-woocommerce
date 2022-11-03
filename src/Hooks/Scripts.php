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
    const SUFFIX = '_params';

    /**
     * @const
     */
    const MELIDATA_SCRIPT_NAME = 'mercadopago_melidata';

    /**
     * @const
     */
    const CARONTE_SCRIPT_NAME = 'wc_mercadopago';

    /**
     * @const
     */
    const NOTICES_SCRIPT_NAME = 'wc_mercadopago_notices';

    /**
     * @var Scripts
     */
    private static $instance = null;

    public static function getInstance(): Scripts
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerAdminStyle(string $name, string $file): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
    }

    public function registerAdminScript(string $name, string $file, array $variables = []): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
        });
    }

    public function registerStoreStyle(string $name, string $file): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
    }

    public function registerStoreScript(string $name, string $file, array $variables = []): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
        });
    }

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

    public function registerMelidataAdminScript(): void
    {
        $this->registerMelidataScript('seller', '/settings');
    }

    public function registerMelidataStoreScript(string $location): void
    {
        $this->registerMelidataScript('buyer', $location);
    }

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

    private function registerStyle(string $name, string $file): void
    {
        wp_register_style($name, $file, false, MP_VERSION);
        wp_enqueue_style($name);
    }

    private function registerScript(string $name, string $file, array $variables = []): void
    {
        wp_enqueue_script($name, $file, array(), MP_VERSION, true);

        if ($variables) {
            wp_localize_script($name, $name . self::SUFFIX, $variables);
        }
    }
}

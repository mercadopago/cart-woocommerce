<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Url;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    /**
     * @var Settings
     */
    private static $instance;

    private function __construct()
    {
        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();
    }

    public static function getInstance(): Settings
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadMenu(): void
    {
        add_action('admin_menu', array($this, 'registerMercadoPagoInWoocommerceMenu'), PRIORITY_ON_MENU);
    }

    public function loadScriptsAndStyles(): void
    {
        add_action('admin_enqueue_scripts', array($this, 'loadStyles'));
        add_action('admin_enqueue_scripts', array($this, 'loadScripts'));
    }

    public function canLoadScriptsAndStyles(): bool
    {
        return is_admin() && (Url::validatePage('mercadopago-settings') || Url::validateSection('woo-mercado-pago'));
    }

    public function loadStyles(): void
    {
        if ($this->canLoadScriptsAndStyles()) {
            wp_register_style(
                'mercadopago_settings_admin_css',
                Url::getPluginFileUrl('assets/css/admin/mp-admin-settings', '.css'),
                false,
                MP_VERSION
            );
            wp_enqueue_style('mercadopago_settings_admin_css');
        }
    }

    public function loadScripts(): void
    {
        if ($this->canLoadScriptsAndStyles()) {
            wp_enqueue_script(
                'mercadopago_settings_javascript',
                Url::getPluginFileUrl('assets/js/admin/mp-admin-settings', '.js'),
                array(),
                MP_VERSION,
                true
            );
        }
    }

    public function registerAjaxEndpoints(): void
    {
        add_action('wp_ajax_mp_get_requirements', array($this, 'mercadopagoValidateRequirements'));
    }

    public function registerMercadoPagoInWoocommerceMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            'Mercado Pago Settings',
            'Mercado Pago',
            'manage_options',
            'mercadopago-settings',
            array($this, 'mercadoPagoSubmenuPageCallback')
        );
    }

    public function mercadoPagoSubmenuPageCallback(): void
    {
        $headerTranslations      = Translations::$headerSettings;
        $credentialsTranslations = Translations::$credentialsSettings;
        $storeTranslations       = Translations::$storeSettings;

        include dirname(__FILE__) . '/../../templates/admin/settings/settings.php';
    }

    public function mercadopagoValidateRequirements(): void
    {
        $hasCurl = in_array('curl', get_loaded_extensions(), true);
        $hasGD   = in_array('gd', get_loaded_extensions(), true);
        $hasSSL  = is_ssl();

        wp_send_json_success([
            'ssl'      => $hasSSL,
            'gd_ext'   => $hasGD,
            'curl_ext' => $hasCurl
        ]);
    }
}

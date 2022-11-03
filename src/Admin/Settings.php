<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\Link;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    /**
     * @var Translations
     */
    public Translations $translations;

    /**
     * @var ?Settings
     */
    private static ?Settings $instance = null;

    /**
     * Settings constructor
     */
    private function __construct()
    {
        $this->translations = Translations::getInstance();

        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();
    }

    /**
     * Get a Settings instance
     *
     * @return Settings
     */
    public static function getInstance(): Settings
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load admin menu
     *
     * @return void
     */
    public function loadMenu(): void
    {
        add_action('admin_menu', array($this, 'registerMercadoPagoInWoocommerceMenu'), WoocommerceMercadoPago::MP_PRIORITY_ON_MENU);
    }

    /**
     * Load scripts and styles
     *
     * @return void
     */
    public function loadScriptsAndStyles(): void
    {
        add_action('admin_enqueue_scripts', array($this, 'loadStyles'));
        add_action('admin_enqueue_scripts', array($this, 'loadScripts'));
    }

    /**
     * Check if scripts ans styles can be loaded
     *
     * @return bool
     */
    public function canLoadScriptsAndStyles(): bool
    {
        return is_admin() && (Url::validatePage('mercadopago-settings') || Url::validateSection('woo-mercado-pago'));
    }

    /**
     * Load styles
     *
     * @return void
     */
    public function loadStyles(): void
    {
        if ($this->canLoadScriptsAndStyles()) {
            wp_register_style(
                'mercadopago_settings_admin_css',
                Url::getPluginFileUrl('assets/css/admin/mp-admin-settings', '.css'),
                false,
                WoocommerceMercadoPago::MP_VERSION
            );
            wp_enqueue_style('mercadopago_settings_admin_css');
        }
    }

    /**
     * Load scripts
     *
     * @return void
     */
    public function loadScripts(): void
    {
        if ($this->canLoadScriptsAndStyles()) {
            wp_enqueue_script(
                'mercadopago_settings_javascript',
                Url::getPluginFileUrl('assets/js/admin/mp-admin-settings', '.js'),
                array(),
                WoocommerceMercadoPago::MP_VERSION,
                true
            );
        }
    }

    /**
     * Register ajax endpoints
     *
     * @return void
     */
    public function registerAjaxEndpoints(): void
    {
        add_action('wp_ajax_mp_get_requirements', array($this, 'mercadopagoValidateRequirements'));
    }

    /**
     * Add Mercado Pago submenu to Woocommerce menu
     *
     * @return void
     */
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

    /**
     * Show submenu page
     *
     * @return void
     */
    public function mercadoPagoSubmenuPageCallback(): void
    {
        $headerTranslations      = $this->translations->headerSettings;
        $credentialsTranslations = $this->translations->credentialsSettings;
        $storeTranslations       = $this->translations->storeSettings;
        $gatewaysTranslations    = $this->translations->gatewaysSettings;
        $testModeTranslations    = $this->translations->testModeSettings;

        include dirname(__FILE__) . '/../../templates/admin/settings/settings.php';
    }

    /**
     * Validate plugin requirements
     *
     * @return void
     */
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

    /**
     * Set plugin configuration links
     *
     * @param array $links
     *
     * @return array
     */
    public function setPluginSettingsLink(array $links): array
    {
        $pluginLinks   = array(
            '<a href="' . admin_url('admin.php?page=mercadopago-settings') . '">' . $this->translations->pluginSettings['set_plugin'] . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . $this->translations->pluginSettings['payment_method'] . '</a>',
            '<a target="_blank" href="' . Link::getLinks()['link_mp_developers'] . '">' . $this->translations->pluginSettings['plugin_manual'] . '</a>',
        );

        return array_merge($pluginLinks, $links);
    }
}

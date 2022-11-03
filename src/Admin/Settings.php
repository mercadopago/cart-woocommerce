<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Scripts;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    /**
     * @const
     */
    const PRIORITY_ON_MENU = 90;

    /**
     * @var Scripts
     */
    protected $scripts;

    /**
     * @var Settings
     */
    private static $instance = null;

    /**
     * Settings constructor
     */
    private function __construct()
    {
        $this->scripts = Scripts::getInstance();

        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();
    }

    /**
     * Get Settings instance
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
        add_action('admin_menu', array($this, 'registerMercadoPagoInWoocommerceMenu'), self::PRIORITY_ON_MENU);
    }

    /**
     * Load scripts and styles
     *
     * @return void
     */
    public function loadScriptsAndStyles(): void
    {
        if ($this->canLoadScriptsAndStyles()) {
            $this->scripts->registerAdminStyle(
                'mercadopago_settings_admin_css',
                Url::getPluginFileUrl('assets/css/admin/mp-admin-settings', '.css')
            );

            $this->scripts->registerAdminScript(
                'mercadopago_settings_admin_js',
                Url::getPluginFileUrl('assets/js/admin/mp-admin-settings', '.js')
            );

            $this->scripts->registerCaronteAdminScript();
            $this->scripts->registerNoticesAdminScript();
            $this->scripts->registerMelidataAdminScript();
        }
    }

    /**
     * Check if scripts and styles can be loaded
     *
     * @return bool
     */
    public function canLoadScriptsAndStyles(): bool
    {
        return is_admin() && (Url::validatePage('mercadopago-settings') || Url::validateSection('woo-mercado-pago'));
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
     * Show plugin configuration page
     *
     * @return void
     */
    public function mercadoPagoSubmenuPageCallback(): void
    {
        $headerTranslations      = Translations::$headerSettings;
        $credentialsTranslations = Translations::$credentialsSettings;
        $storeTranslations       = Translations::$storeSettings;
        $gatewaysTranslations    = Translations::$gatewaysSettings;
        $testModeTranslations    = Translations::$testModeSettings;

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
}

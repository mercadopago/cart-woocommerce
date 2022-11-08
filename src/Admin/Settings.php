<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Configs\Credentials;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Scripts;
use PHPUnit\Exception;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    /**
     * @const
     */
    private const PRIORITY_ON_MENU = 90;

    /**
     * @var Scripts
     */
    protected $scripts;

    /**
     * @var Translations
     */
    protected $translations;

    /**
     * @var Credentials
     */
    protected $credentialsConfigs;

    /**
     * @var Store
     */
    protected $storeConfigs;

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
        $this->translations = Translations::getInstance();
        $this->credentialsConfigs = Credentials::getInstance();
        $this->storeConfigs = Store::getInstance();

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
        add_action( 'wp_ajax_mp_validate_credentials', array($this, 'mercadopagoValidateCredentials'));
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
        $headerTranslations      = $this->translations->headerSettings;
        $credentialsTranslations = $this->translations->credentialsSettings;
        $storeTranslations       = $this->translations->storeSettings;
        $gatewaysTranslations    = $this->translations->gatewaysSettings;
        $testModeTranslations    = $this->translations->testModeSettings;

        $publicKeyProd   = $this->credentialsConfigs->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->credentialsConfigs->getCredentialsAccessTokenProd();
        $publicKeyTest   = $this->credentialsConfigs->getCredentialsPublicKeyTest();
        $accessTokenTest = $this->credentialsConfigs->getCredentialsAccessTokenTest();

        $storeId       = $this->storeConfigs->getStoreId();
        $storeName     = $this->storeConfigs->getStoreName();
        $storeCategory = $this->storeConfigs->getStoreCategory();
        $customDomain  = $this->storeConfigs->getCustomDomain();
        $integratorId  = $this->storeConfigs->getIntegratorId();
        $debugMode     = $this->storeConfigs->getDebugMode();

        $checkboxCheckoutTestMode       = $this->storeConfigs->getCheckboxCheckoutTestMode();
        $checkboxCheckoutProductionMode = $this->storeConfigs->getCheckboxCheckoutProductionMode();

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
     * Validate plugin credentials
     *
     * @return void
     */
    public function mercadopagoValidateCredentials(): void
    {
        $isTest      = Form::getSanitizeTextFromPost('is_test');
        $publicKey   = Form::getSanitizeTextFromPost('public_key');
        $accessToken = Form::getSanitizeTextFromPost('access_token');

        if ($publicKey) {
            // TODO: implement credentials wrapper API
            $validatePublicKey = true;
            if (!$validatePublicKey || $validatePublicKey['is_test'] !== $isTest) {
                wp_send_json_error('Invalid Public Key');
            } else {
                wp_send_json_success('Valid Public Key');
            }
        }

        if ($accessToken) {
            // TODO: implement credentials wrapper API
            $validateAccessToken = true;
            if (!$validateAccessToken || $validateAccessToken['is_test'] !== $isTest) {
                wp_send_json_error('Invalid Access Token');
            } else {
                wp_send_json_success('Valid Access Token');
            }
        }
    }
}

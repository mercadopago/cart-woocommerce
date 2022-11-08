<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Configs\Credentials;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Form;
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
    protected $credentials;

    /**
     * @var Seller
     */
    protected $seller;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @var Settings
     */
    private static $instance = null;

    /**
     * Settings constructor
     */
    private function __construct()
    {
        $this->scripts      = Scripts::getInstance();
        $this->translations = Translations::getInstance();
        $this->credentials  = Credentials::getInstance();
        $this->seller       = Seller::getInstance();
        $this->store        = Store::getInstance();

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
        add_action('wp_ajax_mp_validate_credentials', array($this, 'mercadopagoValidateCredentials'));
        add_action('wp_ajax_mp_update_public_key', array($this, 'mercadopagoValidatePublicKey'));
        add_action('wp_ajax_mp_update_access_token', array($this, 'mercadopagoValidateAccessToken'));
        add_action('wp_ajax_mp_update_option_credentials', array($this, 'mercadopagoUpdateOptionCredentials'));
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

        $publicKeyProd   = $this->credentials->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->credentials->getCredentialsAccessTokenProd();
        $publicKeyTest   = $this->credentials->getCredentialsPublicKeyTest();
        $accessTokenTest = $this->credentials->getCredentialsAccessTokenTest();

        $storeId       = $this->store->getStoreId();
        $storeName     = $this->store->getStoreName();
        $storeCategory = $this->store->getStoreCategory();
        $customDomain  = $this->store->getCustomDomain();
        $integratorId  = $this->store->getIntegratorId();
        $debugMode     = $this->store->getDebugMode();

        $checkboxCheckoutTestMode       = $this->store->getCheckboxCheckoutTestMode();
        $checkboxCheckoutProductionMode = $this->store->getCheckboxCheckoutProductionMode();

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
     * Validate public key
     *
     * @return void
     */
    public function mercadopagoValidatePublicKey(): void
    {
        $isTest    = Form::getSanitizeTextFromPost('is_test');
        $publicKey = Form::getSanitizeTextFromPost('public_key');

        $validateCredentialsResponse = $this->credentials->validatePublicKey($publicKey);

        $data   = $validateCredentialsResponse['data'];
        $status = $validateCredentialsResponse['status'];

        if ($status === 200 && json_encode($data['is_test']) === $isTest) {
            wp_send_json_success('Valid Public Key');
        }

        wp_send_json_error('Invalid Public Key');
    }

    /**
     * Validate access token
     *
     * @return void
     */
    public function mercadopagoValidateAccessToken(): void
    {
        $isTest      = Form::getSanitizeTextFromPost('is_test');
        $accessToken = Form::getSanitizeTextFromPost('access_token');

        $validateCredentialsResponse = $this->credentials->validateAccessToken($accessToken);

        $data   = $validateCredentialsResponse['data'];
        $status = $validateCredentialsResponse['status'];

        if ($status === 200 && json_encode($data['is_test']) === $isTest) {
            wp_send_json_success('Valid Access Token');
        }

        wp_send_json_error('Invalid Access Token');
    }

    /**
     * Save credentials, seller and store options
     *
     * @return void
     */
    public function mercadopagoUpdateOptionCredentials(): void
    {
        // TODO: update payment methods
        // TODO: add wp cache
        // TODO: add translations

        $publicKeyProd   = Form::getSanitizeTextFromPost('public_key_prod');
        $accessTokenProd = Form::getSanitizeTextFromPost('access_token_prod');
        $publicKeyTest   = Form::getSanitizeTextFromPost('public_key_test');
        $accessTokenTest = Form::getSanitizeTextFromPost('access_token_test');

        $validatePublicKeyProd   = $this->credentials->validatePublicKey($publicKeyProd);
        $validateAccessTokenProd = $this->credentials->validateAccessToken($accessTokenProd);
        $validatePublicKeyTest   = $this->credentials->validatePublicKey($publicKeyTest);
        $validateAccessTokenTest = $this->credentials->validateAccessToken($accessTokenTest);

        if (
            $validatePublicKeyProd['status'] === 200 &&
            $validateAccessTokenProd['status'] === 200 &&
            $validatePublicKeyProd['data']['is_test'] === false &&
            $validateAccessTokenProd['data']['is_test'] === false
        ) {
            $this->credentials->setCredentialsPublicKeyProd($publicKeyProd);
            $this->credentials->setCredentialsAccessTokenProd($accessTokenProd);

            $sellerInfo = $this->seller->getSellerInfo($accessTokenProd);
            if ($sellerInfo['status'] === 200) {
                $this->seller->setSiteId($sellerInfo['data']['site_id']);
                $this->store->setCheckoutCountry($sellerInfo['data']['site_id']);
            }

            if (
                (empty($publicKeyTest) && empty($accessTokenTest)) ||
                ($validatePublicKeyTest['status'] === 200 &&
                $validateAccessTokenTest['status'] === 200 &&
                $validatePublicKeyTest['data']['is_test'] === true &&
                $validateAccessTokenTest['data']['is_test'] === true)
            ) {
                $this->credentials->setCredentialsPublicKeyTest($publicKeyTest);
                $this->credentials->setCredentialsAccessTokenTest($publicKeyTest);

                if (empty($publicKeyTest) && empty($accessTokenTest) && $this->store->getCheckboxCheckoutTestMode() === 'yes') {
                    $this->store->setCheckboxCheckoutTestMode('no');
                    $response = [
                        'type'      => 'alert',
                        'message'   => 'Your store has exited Test Mode and is making real sales in Production Mode.',
                        'subtitle'  => 'To test the store, re-enter both test credentials.',
                        'test_mode' => 'no',
                    ];
                    wp_send_json_error($response);
                }
            }

            wp_send_json_success('Credentials were updated');
        }

        $response = [
            'type'      => 'error',
            'link'      => '#',
            'linkMsg'   => 'how to enter the credentials the right way.',
            'message'   => 'Invalid credentials',
            'subtitle'  => 'See our manual to learn ',
            'test_mode' => $this->store->getCheckboxCheckoutTestMode()
        ];

        wp_send_json_error($response);
    }
}

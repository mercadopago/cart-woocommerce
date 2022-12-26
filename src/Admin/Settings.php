<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Categories;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Plugin;
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
     * @const
     */
    private const NONCE_ID = 'mp_settings_nonce';

    /**
     * @var Admin
     */
    private $admin;

    /**
     * @var Endpoints
     */
    private $endpoints;

    /**
     * @var Links
     */
    private $links;

    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var Scripts
     */
    private $scripts;

    /**
     * @var Seller
     */
    private $seller;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Translations
     */
    private $translations;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var Nonce
     */
    private $nonce;

    /**
     * @var CurrentUser
     */
    private $currentUser;

    /**
     * Settings constructor
     */
    public function __construct(
        Admin $admin,
        Endpoints $endpoints,
        Links $links,
        Plugin $plugin,
        Scripts $scripts,
        Seller $seller,
        Store $store,
        Translations $translations,
        Url $url,
        Nonce $nonce,
        CurrentUser $currentUser
    ) {
        $this->admin        = $admin;
        $this->endpoints    = $endpoints;
        $this->links        = $links;
        $this->plugin       = $plugin;
        $this->scripts      = $scripts;
        $this->seller       = $seller;
        $this->store        = $store;
        $this->translations = $translations;
        $this->url          = $url;
        $this->nonce        = $nonce;
        $this->currentUser  = $currentUser;

        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();
    }

    /**
     * Load admin menu
     *
     * @return void
     */
    public function loadMenu(): void
    {
        $this->admin->registerOnMenu(self::PRIORITY_ON_MENU, [$this, 'registerMercadoPagoInWoocommerceMenu']);
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
                $this->url->getPluginFileUrl('assets/css/admin/mp-admin-settings', '.css')
            );

            $this->scripts->registerAdminScript(
                'mercadopago_settings_admin_js',
                $this->url->getPluginFileUrl('assets/js/admin/mp-admin-settings', '.js'),
                [
                    'nonce' => $this->nonce->generateNonce(self::NONCE_ID)
                ]
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
        return $this->admin->isAdmin() && (
            $this->url->validatePage('mercadopago-settings') ||
            $this->url->validateSection('woo-mercado-pago')
        );
    }

    /**
     * Register ajax endpoints
     *
     * @return void
     */
    public function registerAjaxEndpoints(): void
    {
        $this->endpoints->registerAjaxEndpoint('mp_update_test_mode', [$this, 'mercadopagoUpdateTestMode']);
        $this->endpoints->registerAjaxEndpoint('mp_update_store_information', [$this, 'mercadopagoUpdateStoreInfo']);
        $this->endpoints->registerAjaxEndpoint('mp_update_option_credentials', [$this, 'mercadopagoUpdateOptionCredentials']);
        $this->endpoints->registerAjaxEndpoint('mp_update_public_key', [$this, 'mercadopagoValidatePublicKey']);
        $this->endpoints->registerAjaxEndpoint('mp_update_access_token', [$this, 'mercadopagoValidateAccessToken']);
        $this->endpoints->registerAjaxEndpoint('mp_get_requirements', [$this, 'mercadopagoValidateRequirements']);
        $this->endpoints->registerAjaxEndpoint('mp_validate_store_tips', [$this, 'mercadopagoValidateStoreTips']);
        $this->endpoints->registerAjaxEndpoint('mp_validate_credentials_tips', [$this, 'mercadopagoValidateCredentialsTips']);
    }

    /**
     * Add Mercado Pago submenu to Woocommerce menu
     *
     * @return void
     */
    public function registerMercadoPagoInWoocommerceMenu(): void
    {
        $this->admin->registerSubmenuPage(
            'woocommerce',
            'Mercado Pago Settings',
            'Mercado Pago',
            'manage_options',
            'mercadopago-settings',
            [$this, 'mercadoPagoSubmenuPageCallback']
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

        $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();
        $publicKeyTest   = $this->seller->getCredentialsPublicKeyTest();
        $accessTokenTest = $this->seller->getCredentialsAccessTokenTest();

        $storeId       = $this->store->getStoreId();
        $storeName     = $this->store->getStoreName();
        $storeCategory = $this->store->getStoreCategory();
        $customDomain  = $this->store->getCustomDomain();
        $integratorId  = $this->store->getIntegratorId();
        $debugMode     = $this->store->getDebugMode();

        $checkboxCheckoutTestMode       = $this->store->getCheckboxCheckoutTestMode();
        $checkboxCheckoutProductionMode = $this->store->getCheckboxCheckoutProductionMode();

        $links      = $this->links->getLinks();
        $testMode   = ($checkboxCheckoutTestMode === 'yes');
        $categories = Categories::getCategories();

        include dirname(__FILE__) . '/../../templates/admin/settings/settings.php';
    }

    /**
     * Validate plugin requirements
     *
     * @return void
     */
    public function mercadopagoValidateRequirements(): void
    {
        $this->validateAjaxNonce();

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
     * Validate store tips
     *
     * @return void
     */
    public function mercadopagoValidateStoreTips(): void
    {
        $this->validateAjaxNonce();

        $storeId       = $this->store->getStoreId();
        $storeName     = $this->store->getStoreName();
        $storeCategory = $this->store->getStoreCategory();

        if ($storeId && $storeName && $storeCategory) {
            wp_send_json_success($this->translations->configurationTips['valid_store_tips']);
        }

        wp_send_json_error($this->translations->configurationTips['invalid_store_tips']);
    }

    /**
     * Validate credentials tips
     *
     * @return void
     */
    public function mercadopagoValidateCredentialsTips(): void
    {
        $this->validateAjaxNonce();

        $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();

        if ($publicKeyProd && $accessTokenProd) {
            wp_send_json_success($this->translations->configurationTips['valid_credentials_tips']);
        }

        wp_send_json_error($this->translations->configurationTips['invalid_credentials_tips']);
    }

    /**
     * Validate public key
     *
     * @return void
     */
    public function mercadopagoValidatePublicKey(): void
    {
        $this->validateAjaxNonce();

        $isTest    = Form::getSanitizeTextFromPost('is_test');
        $publicKey = Form::getSanitizeTextFromPost('public_key');

        $validateCredentialsResponse = $this->seller->validatePublicKey($publicKey);

        $data   = $validateCredentialsResponse['data'];
        $status = $validateCredentialsResponse['status'];

        if ($status === 200 && json_encode($data['is_test']) === $isTest) {
            wp_send_json_success($this->translations->validateCredentials['valid_public_key']);
        }

        wp_send_json_error($this->translations->validateCredentials['invalid_public_key']);
    }

    /**
     * Validate access token
     *
     * @return void
     */
    public function mercadopagoValidateAccessToken(): void
    {
        $this->validateAjaxNonce();

        $isTest      = Form::getSanitizeTextFromPost('is_test');
        $accessToken = Form::getSanitizeTextFromPost('access_token');

        $validateCredentialsResponse = $this->seller->validateAccessToken($accessToken);

        $data   = $validateCredentialsResponse['data'];
        $status = $validateCredentialsResponse['status'];

        if ($status === 200 && json_encode($data['is_test']) === $isTest) {
            wp_send_json_success($this->translations->validateCredentials['valid_access_token']);
        }

        wp_send_json_error($this->translations->validateCredentials['invalid_access_token']);
    }

    /**
     * Save credentials, seller and store options
     *
     * @return void
     */
    public function mercadopagoUpdateOptionCredentials(): void
    {
        $this->validateAjaxNonce();

        $publicKeyProd   = Form::getSanitizeTextFromPost('public_key_prod');
        $accessTokenProd = Form::getSanitizeTextFromPost('access_token_prod');
        $publicKeyTest   = Form::getSanitizeTextFromPost('public_key_test');
        $accessTokenTest = Form::getSanitizeTextFromPost('access_token_test');

        $validatePublicKeyProd   = $this->seller->validatePublicKey($publicKeyProd);
        $validateAccessTokenProd = $this->seller->validateAccessToken($accessTokenProd);
        $validatePublicKeyTest   = $this->seller->validatePublicKey($publicKeyTest);
        $validateAccessTokenTest = $this->seller->validateAccessToken($accessTokenTest);

        if (
            $validatePublicKeyProd['status'] === 200 &&
            $validateAccessTokenProd['status'] === 200 &&
            $validatePublicKeyProd['data']['is_test'] === false &&
            $validateAccessTokenProd['data']['is_test'] === false
        ) {
            $this->seller->setCredentialsPublicKeyProd($publicKeyProd);
            $this->seller->setCredentialsAccessTokenProd($accessTokenProd);
            $this->seller->setHomologValidate($validateAccessTokenProd['data']['homologated']);
            $this->seller->setClientId($validateAccessTokenProd['data']['client_id']);

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
                $this->seller->setCredentialsPublicKeyTest($publicKeyTest);
                $this->seller->setCredentialsAccessTokenTest($accessTokenTest);

                if (empty($publicKeyTest) && empty($accessTokenTest) && $this->store->getCheckboxCheckoutTestMode() === 'yes') {
                    $this->store->setCheckboxCheckoutTestMode('no');
                    $response = [
                        'type'      => 'alert',
                        'message'   => $this->translations->updateCredentials['no_test_mode_title'],
                        'subtitle'  => $this->translations->updateCredentials['no_test_mode_subtitle'],
                        'test_mode' => 'no',
                    ];
                    wp_send_json_error($response);
                }
            }

            do_action($this->plugin::UPDATE_CREDENTIALS_ACTION);

            wp_send_json_success($this->translations->updateCredentials['credentials_updated']);
        }

        $response = [
            'type'      => 'error',
            'message'   => $this->translations->updateCredentials['invalid_credentials_title'],
            'subtitle'  => $this->translations->updateCredentials['invalid_credentials_subtitle'],
            'linkMsg'   => $this->translations->updateCredentials['invalid_credentials_link_message'],
            'link'      => '#',
            'test_mode' => $this->store->getCheckboxCheckoutTestMode()
        ];

        wp_send_json_error($response);
    }

    /**
     * Save store info options
     *
     * @return void
     */
    public function mercadopagoUpdateStoreInfo(): void
    {
        $this->validateAjaxNonce();

        $storeId       = Form::getSanitizeTextFromPost('store_category_id');
        $storeName     = Form::getSanitizeTextFromPost('store_identificator');
        $storeCategory = Form::getSanitizeTextFromPost('store_categories');
        $customDomain  = Form::getSanitizeTextFromPost('store_url_ipn');
        $integratorId  = Form::getSanitizeTextFromPost('store_integrator_id');
        $debugMode     = Form::getSanitizeTextFromPost('store_debug_mode');

        $this->store->setStoreId($storeId);
        $this->store->setStoreName($storeName);
        $this->store->setStoreCategory($storeCategory);
        $this->store->setCustomDomain($customDomain);
        $this->store->setIntegratorId($integratorId);
        $this->store->setDebugMode($debugMode);

        do_action($this->plugin::UPDATE_STORE_INFO_ACTION);

        wp_send_json_success($this->translations->updateStore['valid_configuration']);
    }

    /**
     * Save test mode options
     *
     * @return void
     */
    public function mercadopagoUpdateTestMode(): void
    {
        $this->validateAjaxNonce();

        $checkoutTestMode    = Form::getSanitizeTextFromPost('input_mode_value');
        $verifyAlertTestMode = Form::getSanitizeTextFromPost('input_verify_alert_test_mode');

        $validateCheckoutTestMode = ($checkoutTestMode === 'yes');
        $withoutTestCredentials = (
            $this->seller->getCredentialsPublicKeyTest() === '' ||
            $this->seller->getCredentialsAccessTokenTest() === ''
        );

        if ($verifyAlertTestMode === 'yes' || ($validateCheckoutTestMode && $withoutTestCredentials)) {
            wp_send_json_error('Invalid credentials for test mode');
        }

        $this->store->setCheckboxCheckoutTestMode($checkoutTestMode);

        do_action($this->plugin::UPDATE_TEST_MODE_ACTION);

        if ($validateCheckoutTestMode) {
            wp_send_json_success('Mercado Pago\'s Payment Methods in Test Mode');
        }

        wp_send_json_success('Mercado Pago\'s Payment Methods in Production Mode');
    }

    /**
     * Validate ajax nonce
     *
     * @return void
     */
    private function validateAjaxNonce(): void
    {
        $this->currentUser->validateUserNeededPermissions();
        $this->nonce->validateNonce(self::NONCE_ID, Form::getSanitizeTextFromPost('nonce'));
    }
}
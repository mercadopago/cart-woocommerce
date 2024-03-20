<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Categories;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\IO\Downloader;

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
     * @var AdminTranslations
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
     * @var Session
     */
    private $session;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var Downloader
     */
    private $downloader;

    /**
     * Settings constructor
     *
     * @param Admin $admin
     * @param Endpoints $endpoints
     * @param Links $links
     * @param Plugin $plugin
     * @param Scripts $scripts
     * @param Seller $seller
     * @param Store $store
     * @param AdminTranslations $translations
     * @param Url $url
     * @param Nonce $nonce
     * @param CurrentUser $currentUser
     * @param Session $session
     * @param Logs $logs
     * @param Downloader $downloader
     */
    public function __construct(
        Admin $admin,
        Endpoints $endpoints,
        Links $links,
        Plugin $plugin,
        Scripts $scripts,
        Seller $seller,
        Store $store,
        AdminTranslations $translations,
        Url $url,
        Nonce $nonce,
        CurrentUser $currentUser,
        Session $session,
        Logs $logs,
        Downloader $downloader
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
        $this->session      = $session;
        $this->logs         = $logs;
        $this->downloader   = $downloader;

        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();

        $this->plugin->registerOnPluginCredentialsUpdate(function () {
            $this->seller->updatePaymentMethods();
            $this->seller->updatePaymentMethodsBySiteId();
        });

        $this->plugin->registerOnPluginTestModeUpdate(function () {
            $this->seller->updatePaymentMethods();
            $this->seller->updatePaymentMethodsBySiteId();
        });
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

            $this->scripts->registerAdminStyle(
                'mercadopago_admin_configs_css',
                $this->url->getPluginFileUrl('assets/css/admin/mp-admin-configs', '.css')
            );

            $this->scripts->registerAdminScript(
                'mercadopago_settings_admin_js',
                $this->url->getPluginFileUrl('assets/js/admin/mp-admin-settings', '.js'),
                [
                    'nonce'              => $this->nonce->generateNonce(self::NONCE_ID),
                    'show_advanced_text' => $this->translations->storeSettings['accordion_advanced_store_show'],
                    'hide_advanced_text' => $this->translations->storeSettings['accordion_advanced_store_hide'],
                ]
            );

            $this->scripts->registerCaronteAdminScript();
            $this->scripts->registerMelidataAdminScript();
        }

        if ($this->canLoadScriptsNoticesAdmin()) {
            $this->scripts->registerNoticesAdminScript();
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
     * Check if scripts notices can be loaded
     *
     * @return bool
     */
    public function canLoadScriptsNoticesAdmin(): bool
    {
        return $this->admin->isAdmin() && (
            $this->url->validateUrl('index') ||
            $this->url->validateUrl('plugins') ||
            $this->url->validatePage('wc-admin') ||
            $this->url->validatePage('wc-settings') ||
            $this->url->validatePage('mercadopago-settings')
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
        $this->endpoints->registerAjaxEndpoint('mp_get_payment_methods', [$this, 'mercadopagoPaymentMethods']);
        $this->endpoints->registerAjaxEndpoint('mp_validate_credentials_tips', [$this, 'mercadopagoValidateCredentialsTips']);
        $this->endpoints->registerAjaxEndpoint('mp_validate_store_tips', [$this, 'mercadopagoValidateStoreTips']);
        $this->endpoints->registerAjaxEndpoint('mp_validate_payment_tips', [$this, 'mercadopagoValidatePaymentTips']);
        $this->endpoints->registerAjaxEndpoint('mp_download_log', [$this, 'mercadopagoDownloadLog']);
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
        $supportTranslations     = $this->translations->supportSettings;

        $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();
        $publicKeyTest   = $this->seller->getCredentialsPublicKeyTest();
        $accessTokenTest = $this->seller->getCredentialsAccessTokenTest();

        $storeId             = $this->store->getStoreId();
        $storeName           = $this->store->getStoreName();
        $storeCategory       = $this->store->getStoreCategory('others');
        $customDomain        = $this->store->getCustomDomain();
        $customDomainOptions = $this->store->getCustomDomainOptions();
        $integratorId        = $this->store->getIntegratorId();
        $debugMode           = $this->store->getDebugMode();

        $checkboxCheckoutTestMode       = $this->store->getCheckboxCheckoutTestMode();
        $checkboxCheckoutProductionMode = $this->store->getCheckboxCheckoutProductionMode();

        $links      = $this->links->getLinks();
        $testMode   = ($checkboxCheckoutTestMode === 'yes');
        $categories = Categories::getCategories();
        $pluginLogs = $this->downloader->pluginLogs;

        $phpVersion = phpversion() ? phpversion() : "";
        $wpVersion = $GLOBALS['wp_version'] ? $GLOBALS['wp_version'] : "";
        $wcVersion = $GLOBALS['woocommerce']->version ? $GLOBALS['woocommerce']->version : "";
        $pluginVersion = MP_VERSION ? MP_VERSION : "";


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
     * Get available payment methods
     *
     * @return void
     */
    public function mercadopagoPaymentMethods(): void
    {
        try {
            $this->validateAjaxNonce();

            $paymentGateways            = $this->store->getAvailablePaymentGateways();
            $payment_gateway_properties = [];

            foreach ($paymentGateways as $paymentGateway) {
                $gateway = new $paymentGateway();

                $payment_gateway_properties[] = [
                    'id'               => $gateway->id,
                    'title_gateway'    => $gateway->title,
                    'description'      => $gateway->description,
                    'title'            => $gateway->title,
                    'enabled'          => !isset($gateway->settings['enabled']) ? false : $gateway->settings['enabled'],
                    'icon'             => $gateway->iconAdmin,
                    'link'             => admin_url('admin.php?page=wc-settings&tab=checkout&section=') . $gateway->id,
                    'badge_translator' => [
                        'yes' => $this->translations->gatewaysSettings['enabled'],
                        'no'  => $this->translations->gatewaysSettings['disabled'],
                    ],
                ];
            }

            wp_send_json_success($payment_gateway_properties);
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error in mercadopagoPaymentMethods: {$e->getMessage()}",
                __CLASS__
            );
            $response = [
                'message' => $e->getMessage()
            ];

            wp_send_json_error($response);
        }
    }

    /**
     * Validate store tips
     *
     * @return void
     */
    public function mercadopagoValidatePaymentTips(): void
    {
        $this->validateAjaxNonce();

        $paymentGateways = $this->store->getAvailablePaymentGateways();

        foreach ($paymentGateways as $gateway) {
            $gateway = new $gateway();

            if (isset($gateway->settings['enabled']) && 'yes' === $gateway->settings['enabled']) {
                wp_send_json_success($this->translations->configurationTips['valid_payment_tips']);
            }
        }

        wp_send_json_error($this->translations->configurationTips['invalid_payment_tips']);
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
        $storeCategory = $this->store->getStoreCategory();

        if ($storeId && $storeCategory) {
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

        $isTest    = Form::sanitizeTextFromPost('is_test');
        $publicKey = Form::sanitizeTextFromPost('public_key');

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

        $isTest      = Form::sanitizeTextFromPost('is_test');
        $accessToken = Form::sanitizeTextFromPost('access_token');

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
        try {
            $this->validateAjaxNonce();

            $publicKeyProd   = Form::sanitizeTextFromPost('public_key_prod');
            $accessTokenProd = Form::sanitizeTextFromPost('access_token_prod');
            $publicKeyTest   = Form::sanitizeTextFromPost('public_key_test');
            $accessTokenTest = Form::sanitizeTextFromPost('access_token_test');

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
                    $this->store->setCheckoutCountry($sellerInfo['data']['site_id']);
                    $this->seller->setSiteId($sellerInfo['data']['site_id']);
                    $this->seller->setTestUser(in_array('test_user', $sellerInfo['data']['tags'], true));
                }

                if (
                    (empty($publicKeyTest) && empty($accessTokenTest)) || (
                        $validatePublicKeyTest['status'] === 200 &&
                        $validateAccessTokenTest['status'] === 200 &&
                        $validatePublicKeyTest['data']['is_test'] === true &&
                        $validateAccessTokenTest['data']['is_test'] === true
                    )
                ) {
                    $this->seller->setCredentialsPublicKeyTest($publicKeyTest);
                    $this->seller->setCredentialsAccessTokenTest($accessTokenTest);

                    if (empty($publicKeyTest) && empty($accessTokenTest) && $this->store->getCheckboxCheckoutTestMode() === 'yes') {
                        $this->store->setCheckboxCheckoutTestMode('no');
                        $this->plugin->executeUpdateCredentialAction();

                        $response = [
                            'type'      => 'alert',
                            'message'   => $this->translations->updateCredentials['no_test_mode_title'],
                            'subtitle'  => $this->translations->updateCredentials['no_test_mode_subtitle'],
                            'test_mode' => 'no',
                        ];
                        wp_send_json_error($response);
                    } else {
                        $this->plugin->executeUpdateCredentialAction();
                        wp_send_json_success($this->translations->updateCredentials['credentials_updated']);
                    }
                }
            }

            $response = [
                'type'      => 'error',
                'message'   => $this->translations->updateCredentials['invalid_credentials_title'],
                'subtitle'  => $this->translations->updateCredentials['invalid_credentials_subtitle'] . ' ',
                'linkMsg'   => $this->translations->updateCredentials['invalid_credentials_link_message'],
                'link'      => $this->links->getLinks()['docs_integration_credentials'],
                'test_mode' => $this->store->getCheckboxCheckoutTestMode()
            ];

            wp_send_json_error($response);
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error in update option credentials: {$e->getMessage()}",
                __CLASS__
            );
        }
    }

    /**
     * Save store info options
     *
     * @return void
     */
    public function mercadopagoUpdateStoreInfo(): void
    {
        $this->validateAjaxNonce();

        $storeId              = Form::sanitizeTextFromPost('store_category_id');
        $storeName            = Form::sanitizeTextFromPost('store_identificator');
        $storeCategory        = Form::sanitizeTextFromPost('store_categories');
        $customDomain         = Form::sanitizeTextFromPost('store_url_ipn');
        $customDomainOptions  = Form::sanitizeTextFromPost('store_url_ipn_options');
        $integratorId         = Form::sanitizeTextFromPost('store_integrator_id');
        $debugMode            = Form::sanitizeTextFromPost('store_debug_mode');

        $this->store->setStoreId($storeId);
        $this->store->setStoreName($storeName);
        $this->store->setStoreCategory($storeCategory);
        $this->store->setCustomDomain($customDomain);
        $this->store->setCustomDomainOptions($customDomainOptions);
        $this->store->setIntegratorId($integratorId);
        $this->store->setDebugMode($debugMode);

        $this->plugin->executeUpdateStoreInfoAction();

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

        $checkoutTestMode    = Form::sanitizeTextFromPost('input_mode_value');
        $verifyAlertTestMode = Form::sanitizeTextFromPost('input_verify_alert_test_mode');

        $validateCheckoutTestMode = ($checkoutTestMode === 'yes');

        $withoutTestCredentials = (
            $this->seller->getCredentialsPublicKeyTest() === '' ||
            $this->seller->getCredentialsAccessTokenTest() === ''
        );

        if ($verifyAlertTestMode === 'yes' || ($validateCheckoutTestMode && $withoutTestCredentials)) {
            wp_send_json_error($this->translations->updateCredentials['invalid_credentials_title'] .
                $this->translations->updateCredentials['for_test_mode']);
        }

        $this->store->setCheckboxCheckoutTestMode($checkoutTestMode);

        $this->plugin->executeUpdateTestModeAction();

        if ($validateCheckoutTestMode) {
            wp_send_json_success($this->translations->testModeSettings['title_message_test']);
        }

        wp_send_json_success($this->translations->testModeSettings['title_message_prod']);
    }

    /**
     * Validate ajax nonce
     *
     * @return void
     */
    private function validateAjaxNonce(): void
    {
        $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizeTextFromPost('nonce'));
        $this->currentUser->validateUserNeededPermissions();
    }

    public function mercadopagoDownloadLog()
    {
        try {
            $this->downloader->downloadLog();
        } catch (\Exception $e) {
            $this->logs->file->error('Mercado pago gave error to download log files: ' . $e->getMessage(), __CLASS__);
            http_response_code(500);
            header("Location: " . admin_url("admin.php?page=wc-status&tab=logs"));
            exit;
        }
    }
}

<?php

namespace MercadoPago\Woocommerce\Admin;

use Exception;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Categories;
use MercadoPago\Woocommerce\Helpers\CredentialsStates;
use MercadoPago\Woocommerce\Helpers\Intervals;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\IO\Downloader;
use MercadoPago\Woocommerce\Funnel\Funnel;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    private const PRIORITY_ON_MENU = 90;

    private const NONCE_ID = 'mp_settings_nonce';

    private const ONBOARDING_ERROR = 'onboarding_error';

    private const NOT_LINKED_FAILED = 'not_linked_failed';

    private const DEFAULT = 'default';

    private const EXPIRED = 'expired';

    private const COULD_NOT_VALIDATE_LINK = 'could_not_validate_link';

    private const LINKED_NO_TEST_CREDENTIALS = 'linked_no_test_credentials';

    private const PREVIOUSLY_LINKED = 'previously_linked';

    private const RECENTLY_LINKED = 'recently_linked';

    private const LINKED_FAILED_TO_LOAD = 'linked_failed_to_load';

    private const STATUS = 'status';

    private const LINK_UPDATED = 'link_updated';

    private Admin $admin;

    private Endpoints $endpoints;

    private Links $links;

    private Order $order;

    private Plugin $plugin;

    private Scripts $scripts;

    private Seller $seller;

    private Store $store;

    private AdminTranslations $translations;

    private Url $url;

    private Nonce $nonce;

    private CurrentUser $currentUser;

    private Logs $logs;

    private Downloader $downloader;

    private Funnel $funnel;

    private Strings $strings;

    private CredentialsStates $credentialsStates;

    private Intervals $intervals;

    /**
     * Settings constructor
     *
     * @param Admin $admin
     * @param Endpoints $endpoints
     * @param Links $links
     * @param Order $order
     * @param Plugin $plugin
     * @param Scripts $scripts
     * @param Seller $seller
     * @param Store $store
     * @param AdminTranslations $translations
     * @param Url $url
     * @param Nonce $nonce
     * @param CurrentUser $currentUser
     * @param Logs $logs
     * @param Downloader $downloader
     * @param Funnel $funnel
     * @param Strings $strings
     * @param CredentialsStates $credentialsStates
     * @param Intervals $intervals
     */
    public function __construct(
        Admin $admin,
        Endpoints $endpoints,
        Links $links,
        Order $order,
        Plugin $plugin,
        Scripts $scripts,
        Seller $seller,
        Store $store,
        AdminTranslations $translations,
        Url $url,
        Nonce $nonce,
        CurrentUser $currentUser,
        Logs $logs,
        Downloader $downloader,
        Funnel $funnel,
        Strings $strings,
        CredentialsStates $credentialsStates,
        Intervals $intervals
    ) {
        $this->admin             = $admin;
        $this->endpoints         = $endpoints;
        $this->links             = $links;
        $this->order             = $order;
        $this->plugin            = $plugin;
        $this->scripts           = $scripts;
        $this->seller            = $seller;
        $this->store             = $store;
        $this->translations      = $translations;
        $this->url               = $url;
        $this->nonce             = $nonce;
        $this->currentUser       = $currentUser;
        $this->logs              = $logs;
        $this->downloader        = $downloader;
        $this->funnel            = $funnel;
        $this->strings           = $strings;
        $this->credentialsStates = $credentialsStates;
        $this->intervals         = $intervals;

        $this->loadMenu();
        $this->loadScriptsAndStyles();
        $this->registerAjaxEndpoints();

        $this->plugin->registerOnPluginCredentialsUpdate(function () {
            $this->seller->updatePaymentMethods();
            $this->seller->updatePaymentMethodsBySiteId();
            $this->funnel->updateStepCredentials();
        });

        $this->plugin->registerOnPluginTestModeUpdate(function () {
            $this->seller->updatePaymentMethods();
            $this->seller->updatePaymentMethodsBySiteId();
            $this->funnel->updateStepPluginMode();
        });

        $this->plugin->registerOnPluginStoreInfoUpdate(function () {
            $this->order->selectSyncPendingStatusOrdersCron($this->store->getCronSyncMode());
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
                'mercadopago_vars_css',
                $this->url->getCssAsset('public/mp-vars')
            );

            $this->scripts->registerAdminStyle(
                'mercadopago_settings_admin_css',
                $this->url->getCssAsset('admin/mp-admin-settings')
            );

            $this->scripts->registerAdminStyle(
                'mercadopago_admin_configs_css',
                $this->url->getCssAsset('admin/mp-admin-configs')
            );

            $this->scripts->registerAdminScript(
                'mercadopago_security_session',
                $this->url->getJsAsset('session')
            );

            $this->scripts->registerAdminScript(
                'mercadopago_settings_admin_js',
                $this->url->getJsAsset('admin/mp-admin-settings'),
                [
                    'nonce'              => $this->nonce->generateNonce(self::NONCE_ID),
                    'show_advanced_text' => $this->translations->storeSettings['accordion_advanced_store_show'],
                    'hide_advanced_text' => $this->translations->storeSettings['accordion_advanced_store_hide'],
                    'show_access_token'  => $this->translations->credentialsSettings['show_access_token'],
                    'hide_access_token'  => $this->translations->credentialsSettings['hide_access_token'],
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
        $this->endpoints->registerAjaxEndpoint('mp_integration_login', [$this, 'mercadopagoIntegrationLogin']);
        $this->endpoints->registerAjaxEndpoint('mp_update_test_mode', [$this, 'mercadopagoUpdateTestMode']);
        $this->endpoints->registerAjaxEndpoint('mp_update_store_information', [$this, 'mercadopagoUpdateStoreInfo']);
        $this->endpoints->registerAjaxEndpoint('mp_update_option_credentials', [$this, 'mercadopagoUpdateOptionCredentials']);
        $this->endpoints->registerAjaxEndpoint('mp_get_requirements', [$this, 'mercadopagoValidateRequirements']);
        $this->endpoints->registerAjaxEndpoint('mp_get_payment_methods', [$this, 'mercadopagoPaymentMethods']);
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
        $headerTranslations         = $this->translations->headerSettings;
        $credentialsTranslations    = $this->translations->credentialsSettings;
        $credentialsLinkComponents  = $this->translations->credentialsLinkComponents;
        $storeTranslations          = $this->translations->storeSettings;
        $gatewaysTranslations       = $this->translations->gatewaysSettings;
        $testModeTranslations       = $this->translations->testModeSettings;
        $supportTranslations        = $this->translations->supportSettings;
        $allowedHtmlTags            = $this->strings->getAllowedHtmlTags();

        $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();
        $publicKeyTest   = $this->seller->getCredentialsPublicKeyTest();
        $accessTokenTest = $this->seller->getCredentialsAccessTokenTest();

        $storeId             = $this->store->getStoreId();
        $storeName           = $this->store->getStoreName();
        $storeCategory       = $this->store->getStoreCategory('others');
        $cronSyncMode        = $this->store->getCronSyncMode();
        $customDomain        = $this->store->getCustomDomain();
        $customDomainOptions = $this->store->getCustomDomainOptions();
        $integratorId        = $this->store->getIntegratorId();
        $debugMode           = $this->store->getDebugMode();

        $checkboxCheckoutTestMode       = $this->store->getCheckboxCheckoutTestMode();
        $checkboxCheckoutProductionMode = $this->store->getCheckboxCheckoutProductionMode();

        $links      = $this->links->getLinks();
        $testMode   = ($checkboxCheckoutTestMode === 'yes');
        $categories = Categories::getCategories();
        $intervals  = $this->intervals->getIntervals();
        $pluginLogs = $this->downloader->pluginLogs;
        $phpVersion = phpversion() ?? "";
        $wpVersion  = $GLOBALS['wp_version'] ?? "";
        $wcVersion  = $GLOBALS['woocommerce']->version ?? "";
        $pluginVersion = MP_VERSION ??  "";

        $credentialsState = $this->credentialsStates->getCredentialsTemplate($this->getMercadoPagoCredentialsStatus());

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
        } catch (Exception $e) {
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

    private function getMercadoPagoCredentialsStatus(): string
    {
        try {
            if (Form::sanitizedGetData(self::ONBOARDING_ERROR) === 'true') {
                return self::NOT_LINKED_FAILED;
            }

            if (Form::sanitizedGetData(self::LINK_UPDATED) === 'true') {
                return self::LINK_UPDATED;
            }

            $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
            $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();
            $publicKeyTest   = $this->seller->getCredentialsPublicKeyTest();
            $accessTokenTest = $this->seller->getCredentialsAccessTokenTest();

            $linkState = self::DEFAULT;

            if (empty($publicKeyProd) || empty($accessTokenProd)) {
                return $linkState;
            }

            if ($this->seller->isExpiredPublicKey($publicKeyProd)) {
                return self::EXPIRED;
            }

            if (empty($publicKeyTest) || empty($accessTokenTest)) {
                return self::LINKED_NO_TEST_CREDENTIALS;
            }

            $testCredentialsValidation = $this->seller->validateCredentials($accessTokenTest, $publicKeyTest);

            if ($testCredentialsValidation[self::STATUS] == 401) {
                return CredentialsStates::UNAUTHORIZED_ACCESS_TOKEN;
            }

            if ($testCredentialsValidation[self::STATUS] !== 200) {
                return self::COULD_NOT_VALIDATE_LINK;
            }

            if ($this->store->getCodeChallenge() === '') {
                return self::PREVIOUSLY_LINKED;
            }

            $this->store->setCodeChallenge('');
            return self::RECENTLY_LINKED;
        } catch (Exception $e) {
            $this->logs->file->error(
                "An error has occurred to retrieve credentials state: {$e->getMessage()}",
                __CLASS__
            );
            return self::LINKED_FAILED_TO_LOAD;
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

        $storeId              = Form::sanitizedPostData('store_category_id');
        $storeName            = Form::sanitizedPostData('store_identificator');
        $storeCategory        = Form::sanitizedPostData('store_categories');
        $customDomain         = Form::sanitizedPostData('store_url_ipn');
        $customDomainOptions  = Form::sanitizedPostData('store_url_ipn_options');
        $integratorId         = Form::sanitizedPostData('store_integrator_id');
        $debugMode            = Form::sanitizedPostData('store_debug_mode');
        $cronSyncMode         = Form::sanitizedPostData('store_cron_config');

        $this->store->setStoreId($storeId);
        $this->store->setStoreName($storeName);
        $this->store->setStoreCategory($storeCategory);
        $this->store->setCustomDomain($customDomain);
        $this->store->setCustomDomainOptions($customDomainOptions);
        $this->store->setIntegratorId($integratorId);
        $this->store->setDebugMode($debugMode);
        $this->store->setCronSyncMode($cronSyncMode);

        $this->plugin->executeUpdateStoreInfoAction();

        wp_send_json_success($this->translations->updateStore['valid_configuration']);
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

            $publicKeyTest   = Form::sanitizedPostData('public_key_test');
            $accessTokenTest = Form::sanitizedPostData('access_token_test');

            $this->validateFormCredentials($accessTokenTest, $publicKeyTest);

            $this->seller->setCredentialsPublicKeyTest($publicKeyTest);
            $this->seller->setCredentialsAccessTokenTest($accessTokenTest);

            $this->plugin->executeUpdateCredentialAction();
            wp_send_json_success($this->translations->updateCredentials['credentials_updated']);
        } catch (Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error in update option credentials: {$e->getMessage()}",
                __CLASS__
            );
        }
    }

    /**
     * Validate inserted test credentials and send error if occurs
     *
     * @return void
     */
    private function validateFormCredentials($accessTokenTest, $publicKeyTest): void
    {
        $publicKeyProd   = $this->seller->getCredentialsPublicKeyProd();
        $accessTokenProd = $this->seller->getCredentialsAccessTokenProd();

        $validateProd    = $this->seller->validateCredentials($accessTokenProd, $publicKeyProd);
        $validateTest    = $this->seller->validateCredentials($accessTokenTest, $publicKeyTest);

        if (empty($publicKeyTest) && empty($accessTokenTest)) {
            if ($this->store->getCheckboxCheckoutTestMode() === 'yes') {
                $this->store->setCheckboxCheckoutTestMode('no');
            }
            $response = [
                'type'               => 'error',
                'message'   => $this->translations->updateCredentials['invalid_credentials_empty'],
            ];
            wp_send_json_error($response);
        }

        if ($validateTest['status'] !== 200) {
            $response = [
                'type'               => 'error',
                'message'   => $this->translations->updateCredentials['invalid_credentials'],
            ];
            wp_send_json_error($response);
        }

        if (!$validateTest['data']['is_test']) {
            $response = [
                'type'               => 'error',
                'message'   => $this->translations->updateCredentials['invalid_credentials_not_test'],
            ];
            wp_send_json_error($response);
        }

        if ($validateTest['data']['client_id'] !== $validateProd['data']['client_id']) {
            $response = [
                'type'               => 'error',
                'message'   => $this->translations->updateCredentials['invalid_credentials_not_same_client_id'],
            ];
            wp_send_json_error($response);
        }
    }

    /**
     * Save test mode options
     *
     * @return void
     */
    public function mercadopagoUpdateTestMode(): void
    {
        $this->validateAjaxNonce();

        $checkoutTestMode    = Form::sanitizedPostData('input_mode_value');
        $verifyAlertTestMode = Form::sanitizedPostData('input_verify_alert_test_mode');

        $validateCheckoutTestMode = ($checkoutTestMode === 'yes');
        $validateCheckoutProdMode = ($checkoutTestMode === 'no');

        $withoutTestCredentials = (
            $this->seller->getCredentialsPublicKeyTest() === '' ||
            $this->seller->getCredentialsAccessTokenTest() === ''
        );

        $withoutProdCredentials = (
            $this->seller->getCredentialsPublicKeyProd() === '' ||
            $this->seller->getCredentialsAccessTokenProd() === ''
        );

        if ($verifyAlertTestMode === 'yes' || ($validateCheckoutTestMode && $withoutTestCredentials)) {
            wp_send_json_error($this->translations->updateCredentials['invalid_credentials_title'] . ' ' .
                $this->translations->updateCredentials['for_test_mode']);
        }

        if ($verifyAlertTestMode === 'no' && $validateCheckoutProdMode && $withoutProdCredentials) {
            $this->store->setCheckboxCheckoutTestMode('yes');
            $this->plugin->executeUpdateTestModeAction();
            wp_send_json_success();
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
        $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizedPostData('nonce'));
        $this->currentUser->validateUserNeededPermissions();
    }

    public function mercadopagoDownloadLog()
    {
        try {
            $this->downloader->downloadLog();
        } catch (Exception $e) {
            $this->logs->file->error('Mercado pago gave error to download log files: ' . $e->getMessage(), __CLASS__);
            http_response_code(500);
            wp_safe_redirect(admin_url("admin.php?page=wc-status&tab=logs"));
            exit;
        }
    }

    public function mercadopagoIntegrationLogin()
    {
        $this->validateAjaxNonce();
        $siteId = Form::sanitizedPostData('site_id') ? Form::sanitizedPostData('site_id') : $this->seller->getSiteId();

        $this->seller->setDeviceFingerprint(Form::sanitizedPostData('device_fingerprint'));

        try {
            [$status, $data] = $this->seller->getIntegrationLoginUrl($siteId);
            if ($status == 200 && !empty($data)) {
                $this->store->setIntegrationId($data['integration_id']);
                wp_send_json_success($data);
            } else {
                throw new Exception(json_encode($data));
            }
        } catch (Exception $e) {
            $this->logs->file->error('Unable to make request to integration auth: ' . $e->getMessage(), __CLASS__);
            $response = [
                'message' => json_decode($e->getMessage(), true)
            ];
            wp_send_json_error($response);
            return;
        }
    }
}

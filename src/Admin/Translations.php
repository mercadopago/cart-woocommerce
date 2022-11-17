<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Links;

if (!defined('ABSPATH')) {
    exit;
}

class Translations
{
    /**
     * @const
     */
    private const DOMAIN = 'woocommerce-mercadopago';

    /**
     * @var array
     */
    public $notices = [];

    /**
     * @var array
     */
    public $plugin = [];

    /**
     * @var array
     */
    public $order = [];

    /**
     * @var array
     */
    public $headerSettings = [];

    /**
     * @var array
     */
    public $credentialsSettings = [];

    /**
     * @var array
     */
    public $storeSettings = [];

    /**
     * @var array
     */
    public $gatewaysSettings = [];

    /**
     * @var array
     */
    public $testModeSettings = [];

    /**
     * @var array
     */
    public $configurationTips = [];

    /**
     * @var array
     */
    public $validateCredentials = [];

    /**
     * @var array
     */
    public $updateCredentials = [];

    /**
     * @var array
     */
    public $updateStore = [];

    /**
     * @var array
     */
    protected $links = [];

    /**
     * @var Translations
     */
    private static $instance = null;

    /**
     * Translations constructor
     */
    public function __construct()
    {
        $this->links = Links::getLinks();

        $this->setNoticesTranslations();
        $this->setPluginSettingsTranslations();
        $this->setHeaderSettingsTranslations();
        $this->setCredentialsSettingsTranslations();
        $this->setStoreSettingsTranslations();
        $this->setOrderSettingsTranslations();
        $this->setGatewaysSettingsTranslations();
        $this->setTestModeSettingsTranslations();
        $this->setConfigurationTipsTranslations();
        $this->setUpdateCredentialsTranslations();
        $this->setValidateCredentialsTranslations();
        $this->setUpdateStoreTranslations();
    }

    /**
     * Get Translations instance
     *
     * @return Translations
     */
    public static function getInstance(): Translations
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set notices translations
     *
     * @return void
     */
    private function setNoticesTranslations(): void
    {
        $missWoocommerce = sprintf(
            __('The Mercado Pago module needs an active version of %s in order to work!', self::DOMAIN),
            '<a target="_blank" href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
        );

        $this->notices = [
            'php_wrong_version'     => __('Mercado Pago payments for WooCommerce requires PHP version 7.2 or later. Please update your PHP version.', self::DOMAIN),
            'missing_curl'          => __('Mercado Pago Error: PHP Extension CURL is not installed.', self::DOMAIN),
            'missing_gd_extensions' => __('Mercado Pago Error: PHP Extension GD is not installed. Installation of GD extension is required to send QR Code Pix by email.', self::DOMAIN),
            'activate_woocommerce'  => __('Activate WooCommerce', self::DOMAIN),
            'install_woocommerce'   => __('Install WooCommerce', self::DOMAIN),
            'see_woocommerce'       => __('See WooCommerce', self::DOMAIN),
            'miss_woocommerce'      => $missWoocommerce,
        ];
    }

    /**
     * Set plugin settings translations
     *
     * @return void
     */
    private function setPluginSettingsTranslations(): void
    {
        $this->plugin = [
            'set_plugin'     => __('Set plugin', self::DOMAIN),
            'payment_method' => __('Payment method', self::DOMAIN),
            'plugin_manual'  => __('Plugin manual', self::DOMAIN),
        ];
    }

    /**
     * Set order settings translations
     *
     * @return void
     */
    private function setOrderSettingsTranslations(): void
    {
        $this->order = [
            'cancel_order' => __('Cancel order', self::DOMAIN),
        ];
    }

    /**
     * Set headers settings translations
     *
     * @return void
     */
    private function setHeaderSettingsTranslations(): void
    {
        $titleHeader = sprintf(
            '%s <b>%s</b> %s <br/> %s <b>%s</b> %s',
            __('Accept', self::DOMAIN),
            __('payments on the spot', self::DOMAIN),
            __('with', self::DOMAIN),
            __('the', self::DOMAIN),
            __('security', self::DOMAIN),
            __('from Mercado Pago', self::DOMAIN)
        );

        $installmentsDescription = sprintf(
            '%s <b>%s</b> %s <b>%s</b> %s',
            __('Choose', self::DOMAIN),
            __('when you want to receive the money', self::DOMAIN),
            __('from your sales and if you want to offer', self::DOMAIN),
            __('interest-free installments', self::DOMAIN),
            __('to your clients.', self::DOMAIN)
        );

        $questionsDescription = sprintf(
            '%s <b>%s</b> %s',
            __('Review the step-by-step of', self::DOMAIN),
            __('how to integrate the Mercado Pago Plugin', self::DOMAIN),
            __('on our website for developers.', self::DOMAIN)
        );

        $this->headerSettings = [
            'ssl'                      => __('SSL', self::DOMAIN),
            'curl'                     => __('Curl', self::DOMAIN),
            'gd_extension'             => __('GD Extensions', self::DOMAIN),
            'title_header'             => $titleHeader,
            'title_requirements'       => __('Technical requirements', self::DOMAIN),
            'title_installments'       => __('Collections and installments', self::DOMAIN),
            'title_questions'          => __('Questions?', self::DOMAIN),
            'description_ssl'          => __('Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.', self::DOMAIN),
            'description_curl'         => __('It is an extension responsible for making payments via requests from the plugin to Mercado Pago.', self::DOMAIN),
            'description_gd_extension' => __('These extensions are responsible for the implementation and operation of Pix in your store.', self::DOMAIN),
            'description_installments' => $installmentsDescription,
            'description_questions'    => $questionsDescription,
            'button_installments'      => __('Set deadlines and fees', self::DOMAIN),
            'button_questions'         => __('Plugin manual', self::DOMAIN),
        ];
    }

    /**
     * Set credentials settings translations
     *
     * @return void
     */
    private function setCredentialsSettingsTranslations(): void
    {
        $subtitleCredentials = sprintf(
            '%s <b>%s</b>',
            __('To enable orders, you must create and activate production credentials in your Mercado Pago Account.', self::DOMAIN),
            __('Copy and paste the credentials below.', self::DOMAIN)
        );

        $this->credentialsSettings = [
            'public_key'                => __('Public Key', self::DOMAIN),
            'access_token'              => __('Access Token', self::DOMAIN),
            'title_credentials'         => __('1. Integrate your store with Mercado Pago', self::DOMAIN),
            'title_credentials_prod'    => __('Production credentials', self::DOMAIN),
            'title_credentials_test'    => __('Test credentials', self::DOMAIN),
            'subtitle_credentials'      => $subtitleCredentials,
            'subtitle_credentials_test' => __('Enable Mercado Pago checkouts for test purchases in the store.', self::DOMAIN),
            'subtitle_credentials_prod' => __('Enable Mercado Pago checkouts to receive real payments in the store.', self::DOMAIN),
            'placeholder_public_key'    => __('Paste your Public Key here', self::DOMAIN),
            'placeholder_access_token'  => __('Paste your Access Token here', self::DOMAIN),
            'button_link_credentials'   => __('Check credentials', self::DOMAIN),
            'button_credentials'        => __('Save and continue', self::DOMAIN),
        ];
    }

    /**
     * Set store settings translations
     *
     * @return void
     */
    private function setStoreSettingsTranslations(): void
    {
        $helperUrl = sprintf(
            '%s %s <a class="mp-settings-blue-text" target="_blank" href="%s">%s</a>.',
            __('Add the URL to receive payments notifications.', self::DOMAIN),
            __('Find out more information in the', self::DOMAIN),
            $this->links['docs_ipn_notification'],
            __('guides', self::DOMAIN)
        );

        $helperIntegrator = sprintf(
            '%s %s <a class="mp-settings-blue-text" target="_blank" href="%s">%s</a>.',
            __('If you are a Mercado Pago Certified Partner, make sure to add your integrator_id.', self::DOMAIN),
            __('If you do not have the code, please', self::DOMAIN),
            $this->links['docs_developers_program'],
            __('request it now', self::DOMAIN)
        );

        $this->storeSettings = [
            'title_store'                  => __('2. Customize your business', self::DOMAIN),
            'title_info_store'             => __('Your store information', self::DOMAIN),
            'title_advanced_store'         => __('Advanced integration options (optional)', self::DOMAIN),
            'title_debug'                  => __('Debug and Log Mode', self::DOMAIN),
            'subtitle_store'               => __('Fill out the following information to have a better experience and offer more information to your clients.', self::DOMAIN),
            'subtitle_name_store'          => __('Name of your store in your client\'s invoice', self::DOMAIN),
            'subtitle_activities_store'    => __('Identification in Activities of Mercado Pago', self::DOMAIN),
            'subtitle_advanced_store'      => __('For further integration of your store with Mercado Pago (IPN, Certified Partners, Debug Mode)', self::DOMAIN),
            'subtitle_category_store'      => __('Store category', self::DOMAIN),
            'subtitle_url'                 => __('URL for IPN', self::DOMAIN),
            'subtitle_integrator'          => __('Integrator ID', self::DOMAIN),
            'subtitle_debug'               => __('We record your store\'s actions in order to provide a better assistance.', self::DOMAIN),
            'placeholder_name_store'       => __('Ex: Mary\'s Store', self::DOMAIN),
            'placeholder_activities_store' => __('Ex: Mary Store', self::DOMAIN),
            'placeholder_category_store'   => __('Select', self::DOMAIN),
            'placeholder_url'              => __('Ex: https://examples.com/my-custom-ipn-url', self::DOMAIN),
            'placeholder_integrator'       => __('Ex: 14987126498', self::DOMAIN),
            'accordion_advanced_store'     => __('Show advanced options', self::DOMAIN),
            'button_store'                 => __('Save and continue', self::DOMAIN),
            'helper_name_store'            => __('If this field is empty, the purchase will be identified as Mercado Pago.', self::DOMAIN),
            'helper_activities_store'      => __('In Activities, you will view this term before the order number', self::DOMAIN),
            'helper_category_store'        => __('Select "Other categories" if you do not find the appropriate category.', self::DOMAIN),
            'helper_integrator_link'       => __('request it now.', self::DOMAIN),
            'helper_url'                   => $helperUrl,
            'helper_integrator'            => $helperIntegrator,
        ];
    }

    /**
     * Set gateway settings translations
     *
     * @return void
     */
    private function setGatewaysSettingsTranslations(): void
    {
        $this->gatewaysSettings = [
            'title_payments'    => __('3. Set payment methods', self::DOMAIN),
            'subtitle_payments' => __('To view more options, please select a payment method below', self::DOMAIN),
            'settings_payment'  => __('Settings', self::DOMAIN),
            'button_payment'    => __('Continue', self::DOMAIN),
        ];
    }

    /**
     * Set test mode settings translations
     *
     * @return void
     */
    private function setTestModeSettingsTranslations(): void
    {
        $testCredentialsHelper = sprintf(
            '%s, <a class="mp-settings-blue-text" id="mp-testmode-credentials-link" target="_blank" href="%s">%s</a> %s.',
            __('To enable test mode', self::DOMAIN),
            $this->links['mercadopago_credentials'],
            __('copy your test credentials', self::DOMAIN),
            __('and paste them above in section 1 of this page', self::DOMAIN)
        );

        $testSubtitleOne = sprintf(
            '1. %s <a class="mp-settings-blue-text" id="mp-testmode-testuser-link" target="_blank" href="%s">%s</a>, %s.',
            __('Create your', self::DOMAIN),
            $this->links['mercadopago_test_user'],
            __('test user', self::DOMAIN),
            __('(Optional. Can be used in Production Mode and Test Mode, to test payments)', self::DOMAIN)
        );

        $testSubtitleTwo = sprintf(
            '2. <a class="mp-settings-blue-text" id="mp-testmode-cardtest-link" target="_blank" href="%s">%s</a>, %s.',
            $this->links['docs_test_cards'],
            __('Use our test cards', self::DOMAIN),
            __('never use real cards', self::DOMAIN)
        );

        $testSubtitleThree = sprintf(
            '3. <a class="mp-settings-blue-text" id="mp-testmode-store-link" target="_blank" href="%s">%s</a> %s.',
            $this->links['store_visit'],
            __('Visit your store', self::DOMAIN),
            __('to test purchases', self::DOMAIN)
        );

        $this->testModeSettings = [
            'title_test_mode'         => __('4. Test your store before you sell', self::DOMAIN),
            'title_mode'              => __('Choose how you want to operate your store:', self::DOMAIN),
            'title_test'              => __('Test Mode', self::DOMAIN),
            'title_prod'              => __('Sale Mode (Production)', self::DOMAIN),
            'title_message_prod'      => __('Mercado Pago payment methods in Production Mode', self::DOMAIN),
            'title_message_test'      => __('Mercado Pago payment methods in Test Mode', self::DOMAIN),
            'title_alert_test'        => __('Enter test credentials', self::DOMAIN),
            'subtitle_test_mode'      => __('Test the experience in Test Mode and then enable the Sale Mode (Production) to sell.', self::DOMAIN),
            'subtitle_test'           => __('Mercado Pago Checkouts disabled for real collections.', self::DOMAIN),
            'subtitle_test_link'      => __('Test Mode rules.', self::DOMAIN),
            'subtitle_prod'           => __('Mercado Pago Checkouts enabled for real collections.', self::DOMAIN),
            'subtitle_message_prod'   => __('The clients can make real purchases in your store.', self::DOMAIN),
            'subtitle_test_one'       => $testSubtitleOne,
            'subtitle_test_two'       => $testSubtitleTwo,
            'subtitle_test_three'     => $testSubtitleThree,
            'test_credentials_helper' => $testCredentialsHelper,
            'badge_mode'              => __('Store in sale mode (Production)', self::DOMAIN),
            'badge_test'              => __('Store under test', self::DOMAIN),
            'button_test_mode'        => __('Save changes', self::DOMAIN),
        ];
    }

    public function setConfigurationTipsTranslations(): void
    {
        $this->configurationTips = [
            'valid_store_tips'         => __('Store business fields are valid', self::DOMAIN),
            'invalid_store_tips'       => __('Store business fields could not be validated', self::DOMAIN),
            'valid_credentials_tips'   => __('Credentials fields are valid', self::DOMAIN),
            'invalid_credentials_tips' => __('Credentials fields could not be validated', self::DOMAIN),
        ];
    }

    /**
     * Set validate credentials translations
     *
     * @return void
     */
    public function setValidateCredentialsTranslations(): void
    {
        $this->validateCredentials = [
            'valid_public_key'     => __('Valid Public Key', self::DOMAIN),
            'invalid_public_key'   => __('Invalid Public Key', self::DOMAIN),
            'valid_access_token'   => __('Valid Access Token', self::DOMAIN),
            'invalid_access_token' => __('Invalid Access Token', self::DOMAIN),
        ];
    }

    /**
     * Set update credentials translations
     *
     * @return void
     */
    public function setUpdateCredentialsTranslations(): void
    {
        $this->updateCredentials = [
            'credentials_updated'              => __('Credentials were updated', self::DOMAIN),
            'no_test_mode_title'               => __('Your store has exited Test Mode and is making real sales in Production Mode.', self::DOMAIN),
            'no_test_mode_subtitle'            => __('To test the store, re-enter both test credentials.', self::DOMAIN),
            'invalid_credentials_title'        => __('Invalid credentials', self::DOMAIN),
            'invalid_credentials_subtitle'     => __('See our manual to learn ', self::DOMAIN),
            'invalid_credentials_link_message' => __('how to enter the credentials the right way.', self::DOMAIN),
        ];
    }

    /**
     * Set update store translations
     *
     * @return void
     */
    public function setUpdateStoreTranslations(): void
    {
        $this->updateStore = [
            'valid_configuration' => __('Store information is valid', self::DOMAIN),
        ];
    }
}

<?php

namespace MercadoPago\Woocommerce\Translations;

use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\Links;

if (!defined('ABSPATH')) {
    exit;
}

class AdminTranslations
{
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
    public $pixSettings = [];

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
     * @var Links
     */
    private $links;

    /**
     * Translations constructor
     */
    public function __construct(Links $links)
    {
        $this->links    = $links->getLinks();

        $this->setNoticesTranslations();
        $this->setPluginSettingsTranslations();
        $this->setHeaderSettingsTranslations();
        $this->setCredentialsSettingsTranslations();
        $this->setStoreSettingsTranslations();
        $this->setOrderSettingsTranslations();
        $this->setGatewaysSettingsTranslations();
        $this->setPixSettingsTranslations();
        $this->setTestModeSettingsTranslations();
        $this->setConfigurationTipsTranslations();
        $this->setUpdateCredentialsTranslations();
        $this->setValidateCredentialsTranslations();
        $this->setUpdateStoreTranslations();
    }

    /**
     * Set notices translations
     *
     * @return void
     */
    private function setNoticesTranslations(): void
    {
        $missWoocommerce = sprintf(
            __('The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago'),
            '<a target="_blank" href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
        );

        $currencyConversion = sprintf(
            '<b>%s</b> %s',
            __('Attention:', 'woocommerce-mercadopago'),
            __('The currency settings you have in WooCommerce are not compatible with the currency you use in your Mercado Pago account. Please activate the currency conversion.', 'woocommerce-mercadopago')
        );

        $this->notices = [
            'php_wrong_version'     => __('Mercado Pago payments for WooCommerce requires PHP version 7.2 or later. Please update your PHP version.', 'woocommerce-mercadopago'),
            'missing_curl'          => __('Mercado Pago Error: PHP Extension CURL is not installed.', 'woocommerce-mercadopago'),
            'missing_gd_extensions' => __('Mercado Pago Error: PHP Extension GD is not installed. Installation of GD extension is required to send QR Code Pix by email.', 'woocommerce-mercadopago'),
            'activate_woocommerce'  => __('Activate WooCommerce', 'woocommerce-mercadopago'),
            'install_woocommerce'   => __('Install WooCommerce', 'woocommerce-mercadopago'),
            'see_woocommerce'       => __('See WooCommerce', 'woocommerce-mercadopago'),
            'miss_woocommerce'      => $missWoocommerce,
            'currency_enabled'      => __('Now we convert your currency', 'woocommerce-mercadopago'),
            'currency_disabled'     => __('We no longer convert your currency', 'woocommerce-mercadopago'),
            'currency_conversion'   => $currencyConversion,
            'miss_pix_text'         => __('Please note that to receive payments via Pix at our checkout, you must have a Pix key registered in your Mercado Pago account.', 'woocommerce-mercadopago'),
            'miss_pix_link'         => __('Register your Pix key at Mercado Pago.', 'woocommerce-mercadopago'),
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
            'set_plugin'     => __('Set plugin', 'woocommerce-mercadopago'),
            'payment_method' => __('Payment method', 'woocommerce-mercadopago'),
            'plugin_manual'  => __('Plugin manual', 'woocommerce-mercadopago'),
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
            'cancel_order' => __('Cancel order', 'woocommerce-mercadopago'),
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
            __('Accept', 'woocommerce-mercadopago'),
            __('payments on the spot', 'woocommerce-mercadopago'),
            __('with', 'woocommerce-mercadopago'),
            __('the', 'woocommerce-mercadopago'),
            __('security', 'woocommerce-mercadopago'),
            __('from Mercado Pago', 'woocommerce-mercadopago')
        );

        $installmentsDescription = sprintf(
            '%s <b>%s</b> %s <b>%s</b> %s',
            __('Choose', 'woocommerce-mercadopago'),
            __('when you want to receive the money', 'woocommerce-mercadopago'),
            __('from your sales and if you want to offer', 'woocommerce-mercadopago'),
            __('interest-free installments', 'woocommerce-mercadopago'),
            __('to your clients.', 'woocommerce-mercadopago')
        );

        $questionsDescription = sprintf(
            '%s <b>%s</b> %s',
            __('Review the step-by-step of', 'woocommerce-mercadopago'),
            __('how to integrate the Mercado Pago Plugin', 'woocommerce-mercadopago'),
            __('on our website for developers.', 'woocommerce-mercadopago')
        );

        $this->headerSettings = [
            'ssl'                      => __('SSL', 'woocommerce-mercadopago'),
            'curl'                     => __('Curl', 'woocommerce-mercadopago'),
            'gd_extension'             => __('GD Extensions', 'woocommerce-mercadopago'),
            'title_header'             => $titleHeader,
            'title_requirements'       => __('Technical requirements', 'woocommerce-mercadopago'),
            'title_installments'       => __('Collections and installments', 'woocommerce-mercadopago'),
            'title_questions'          => __('Questions?', 'woocommerce-mercadopago'),
            'description_ssl'          => __('Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.', 'woocommerce-mercadopago'),
            'description_curl'         => __('It is an extension responsible for making payments via requests from the plugin to Mercado Pago.', 'woocommerce-mercadopago'),
            'description_gd_extension' => __('These extensions are responsible for the implementation and operation of Pix in your store.', 'woocommerce-mercadopago'),
            'description_installments' => $installmentsDescription,
            'description_questions'    => $questionsDescription,
            'button_installments'      => __('Set deadlines and fees', 'woocommerce-mercadopago'),
            'button_questions'         => __('Plugin manual', 'woocommerce-mercadopago'),
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
            __('To enable orders, you must create and activate production credentials in your Mercado Pago Account.', 'woocommerce-mercadopago'),
            __('Copy and paste the credentials below.', 'woocommerce-mercadopago')
        );

        $cardInfoSubtitle = sprintf(
            '%s&nbsp;<b>%s</b>.',
            __('You must enter', 'woocommerce-mercadopago'),
            __('production credentials', 'woocommerce-mercadopago')
        );

        $this->credentialsSettings = [
            'public_key'                => __('Public Key', 'woocommerce-mercadopago'),
            'access_token'              => __('Access Token', 'woocommerce-mercadopago'),
            'title_credentials'         => __('1. Integrate your store with Mercado Pago', 'woocommerce-mercadopago'),
            'title_credentials_prod'    => __('Production credentials', 'woocommerce-mercadopago'),
            'title_credentials_test'    => __('Test credentials', 'woocommerce-mercadopago'),
            'subtitle_credentials'      => $subtitleCredentials,
            'subtitle_credentials_test' => __('Enable Mercado Pago checkouts for test purchases in the store.', 'woocommerce-mercadopago'),
            'subtitle_credentials_prod' => __('Enable Mercado Pago checkouts to receive real payments in the store.', 'woocommerce-mercadopago'),
            'placeholder_public_key'    => __('Paste your Public Key here', 'woocommerce-mercadopago'),
            'placeholder_access_token'  => __('Paste your Access Token here', 'woocommerce-mercadopago'),
            'button_link_credentials'   => __('Check credentials', 'woocommerce-mercadopago'),
            'button_credentials'        => __('Save and continue', 'woocommerce-mercadopago'),
            'card_info_title'           => __('Important! To sell you must enter your credentials.', 'woocommerce-mercadopago'),
            'card_info_subtitle'        => $cardInfoSubtitle,
            'card_info_button_text'     => __('Enter credentials', 'woocommerce-mercadopago'),
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
            __('Add the URL to receive payments notifications.', 'woocommerce-mercadopago'),
            __('Find out more information in the', 'woocommerce-mercadopago'),
            $this->links['docs_ipn_notification'],
            __('guides', 'woocommerce-mercadopago')
        );

        $helperIntegrator = sprintf(
            '%s %s <a class="mp-settings-blue-text" target="_blank" href="%s">%s</a>.',
            __('If you are a Mercado Pago Certified Partner, make sure to add your integrator_id.', 'woocommerce-mercadopago'),
            __('If you do not have the code, please', 'woocommerce-mercadopago'),
            $this->links['docs_developers_program'],
            __('request it now', 'woocommerce-mercadopago')
        );

        $this->storeSettings = [
            'title_store'                  => __('2. Customize your business', 'woocommerce-mercadopago'),
            'title_info_store'             => __('Your store information', 'woocommerce-mercadopago'),
            'title_advanced_store'         => __('Advanced integration options (optional)', 'woocommerce-mercadopago'),
            'title_debug'                  => __('Debug and Log Mode', 'woocommerce-mercadopago'),
            'subtitle_store'               => __('Fill out the following information to have a better experience and offer more information to your clients.', 'woocommerce-mercadopago'),
            'subtitle_name_store'          => __('Name of your store in your client\'s invoice', 'woocommerce-mercadopago'),
            'subtitle_activities_store'    => __('Identification in Activities of Mercado Pago', 'woocommerce-mercadopago'),
            'subtitle_advanced_store'      => __('For further integration of your store with Mercado Pago (IPN, Certified Partners, Debug Mode)', 'woocommerce-mercadopago'),
            'subtitle_category_store'      => __('Store category', 'woocommerce-mercadopago'),
            'subtitle_url'                 => __('URL for IPN', 'woocommerce-mercadopago'),
            'subtitle_integrator'          => __('Integrator ID', 'woocommerce-mercadopago'),
            'subtitle_debug'               => __('We record your store\'s actions in order to provide a better assistance.', 'woocommerce-mercadopago'),
            'placeholder_name_store'       => __('Ex: Mary\'s Store', 'woocommerce-mercadopago'),
            'placeholder_activities_store' => __('Ex: Mary Store', 'woocommerce-mercadopago'),
            'placeholder_category_store'   => __('Select', 'woocommerce-mercadopago'),
            'placeholder_url'              => __('Ex: https://examples.com/my-custom-ipn-url', 'woocommerce-mercadopago'),
            'placeholder_integrator'       => __('Ex: 14987126498', 'woocommerce-mercadopago'),
            'accordion_advanced_store'     => __('Show advanced options', 'woocommerce-mercadopago'),
            'button_store'                 => __('Save and continue', 'woocommerce-mercadopago'),
            'helper_name_store'            => __('If this field is empty, the purchase will be identified as Mercado Pago.', 'woocommerce-mercadopago'),
            'helper_activities_store'      => __('In Activities, you will view this term before the order number', 'woocommerce-mercadopago'),
            'helper_category_store'        => __('Select "Other categories" if you do not find the appropriate category.', 'woocommerce-mercadopago'),
            'helper_integrator_link'       => __('request it now.', 'woocommerce-mercadopago'),
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
            'title_payments'    => __('3. Set payment methods', 'woocommerce-mercadopago'),
            'subtitle_payments' => __('To view more options, please select a payment method below', 'woocommerce-mercadopago'),
            'settings_payment'  => __('Settings', 'woocommerce-mercadopago'),
            'button_payment'    => __('Continue', 'woocommerce-mercadopago'),
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
            __('To enable test mode', 'woocommerce-mercadopago'),
            $this->links['mercadopago_credentials'],
            __('copy your test credentials', 'woocommerce-mercadopago'),
            __('and paste them above in section 1 of this page', 'woocommerce-mercadopago')
        );

        $testSubtitleOne = sprintf(
            '1. %s <a class="mp-settings-blue-text" id="mp-testmode-testuser-link" target="_blank" href="%s">%s</a>, %s.',
            __('Create your', 'woocommerce-mercadopago'),
            $this->links['mercadopago_test_user'],
            __('test user', 'woocommerce-mercadopago'),
            __('(Optional. Can be used in Production Mode and Test Mode, to test payments)', 'woocommerce-mercadopago')
        );

        $testSubtitleTwo = sprintf(
            '2. <a class="mp-settings-blue-text" id="mp-testmode-cardtest-link" target="_blank" href="%s">%s</a>, %s.',
            $this->links['docs_test_cards'],
            __('Use our test cards', 'woocommerce-mercadopago'),
            __('never use real cards', 'woocommerce-mercadopago')
        );

        $testSubtitleThree = sprintf(
            '3. <a class="mp-settings-blue-text" id="mp-testmode-store-link" target="_blank" href="%s">%s</a> %s.',
            $this->links['store_visit'],
            __('Visit your store', 'woocommerce-mercadopago'),
            __('to test purchases', 'woocommerce-mercadopago')
        );

        $this->testModeSettings = [
            'title_test_mode'         => __('4. Test your store before you sell', 'woocommerce-mercadopago'),
            'title_mode'              => __('Choose how you want to operate your store:', 'woocommerce-mercadopago'),
            'title_test'              => __('Test Mode', 'woocommerce-mercadopago'),
            'title_prod'              => __('Sale Mode (Production)', 'woocommerce-mercadopago'),
            'title_message_prod'      => __('Mercado Pago payment methods in Production Mode', 'woocommerce-mercadopago'),
            'title_message_test'      => __('Mercado Pago payment methods in Test Mode', 'woocommerce-mercadopago'),
            'title_alert_test'        => __('Enter test credentials', 'woocommerce-mercadopago'),
            'subtitle_test_mode'      => __('Test the experience in Test Mode and then enable the Sale Mode (Production) to sell.', 'woocommerce-mercadopago'),
            'subtitle_test'           => __('Mercado Pago Checkouts disabled for real collections.', 'woocommerce-mercadopago'),
            'subtitle_test_link'      => __('Test Mode rules.', 'woocommerce-mercadopago'),
            'subtitle_prod'           => __('Mercado Pago Checkouts enabled for real collections.', 'woocommerce-mercadopago'),
            'subtitle_message_prod'   => __('The clients can make real purchases in your store.', 'woocommerce-mercadopago'),
            'subtitle_test_one'       => $testSubtitleOne,
            'subtitle_test_two'       => $testSubtitleTwo,
            'subtitle_test_three'     => $testSubtitleThree,
            'test_credentials_helper' => $testCredentialsHelper,
            'badge_mode'              => __('Store in sale mode (Production)', 'woocommerce-mercadopago'),
            'badge_test'              => __('Store under test', 'woocommerce-mercadopago'),
            'button_test_mode'        => __('Save changes', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set configuration tips translations
     *
     * @return void
     */
    private function setConfigurationTipsTranslations(): void
    {
        $this->configurationTips = [
            'valid_store_tips'         => __('Store business fields are valid', 'woocommerce-mercadopago'),
            'invalid_store_tips'       => __('Store business fields could not be validated', 'woocommerce-mercadopago'),
            'valid_credentials_tips'   => __('Credentials fields are valid', 'woocommerce-mercadopago'),
            'invalid_credentials_tips' => __('Credentials fields could not be validated', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set validate credentials translations
     *
     * @return void
     */
    private function setValidateCredentialsTranslations(): void
    {
        $this->validateCredentials = [
            'valid_public_key'     => __('Valid Public Key', 'woocommerce-mercadopago'),
            'invalid_public_key'   => __('Invalid Public Key', 'woocommerce-mercadopago'),
            'valid_access_token'   => __('Valid Access Token', 'woocommerce-mercadopago'),
            'invalid_access_token' => __('Invalid Access Token', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set update credentials translations
     *
     * @return void
     */
    private function setUpdateCredentialsTranslations(): void
    {
        $this->updateCredentials = [
            'credentials_updated'              => __('Credentials were updated', 'woocommerce-mercadopago'),
            'no_test_mode_title'               => __('Your store has exited Test Mode and is making real sales in Production Mode.', 'woocommerce-mercadopago'),
            'no_test_mode_subtitle'            => __('To test the store, re-enter both test credentials.', 'woocommerce-mercadopago'),
            'invalid_credentials_title'        => __('Invalid credentials', 'woocommerce-mercadopago'),
            'invalid_credentials_subtitle'     => __('See our manual to learn', 'woocommerce-mercadopago'),
            'invalid_credentials_link_message' => __('how to enter the credentials the right way.', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set update store translations
     *
     * @return void
     */
    private function setUpdateStoreTranslations(): void
    {
        $this->updateStore = [
            'valid_configuration' => __('Store information is valid', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set pix settings translations
     *
     * @return void
     */
    private function setPixSettingsTranslations(): void
    {
        $enabledDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('The transparent checkout for Pix payment is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $enabledDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('The transparent checkout for Pix payment is', 'woocommerce-mercadopago'),
            __('disabled', 'woocommerce-mercadopago')
        );

        $currencyConversionDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Currency conversion is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $currencyConversionDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Currency conversion is', 'woocommerce-mercadopago'),
            __('disabled', 'woocommerce-mercadopago')
        );

        $stepsStepTwoText = sprintf(
            '%s <b>%s</b> %s <b>%s</b>.',
            __('Go to the', 'woocommerce-mercadopago'),
            __('Your Profile', 'woocommerce-mercadopago'),
            __('area and choose the', 'woocommerce-mercadopago'),
            __('Your Pix Keys section', 'woocommerce-mercadopago')
        );

        $this->pixSettings = [
            'gateway_title'                             => __('Pix', 'woocommerce-mercadopago'),
            'gateway_description'                       => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'gateway_method_title'                      => __('Mercado pago - Customized Checkout', 'woocommerce-mercadopago'),
            'gateway_method_description'                => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'header_title'                              => __('Transparent Checkout | Pix', 'woocommerce-mercadopago'),
            'header_description'                        => __('With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago.', 'woocommerce-mercadopago'),
            'card_settings_title'                       => __('Mercado Pago plugin general settings', 'woocommerce-mercadopago'),
            'card_settings_subtitle'                    => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
            'card_settings_button_text'                 => __('Go to Settings', 'woocommerce-mercadopago'),
            'enabled_title'                             => __('Enable the checkout', 'woocommerce-mercadopago'),
            'enabled_subtitle'                          => __('By disabling it, you will disable all Pix payments from Mercado Pago Transparent Checkout.', 'woocommerce-mercadopago'),
            'enabled_descriptions_enabled'              => $enabledDescriptionsEnabled,
            'enabled_descriptions_disabled'             => $enabledDescriptionsDisabled,
            'title_title'                               => __('Title in the store Checkout', 'woocommerce-mercadopago'),
            'title_description'                         => __('Change the display text in Checkout, maximum characters: 85', 'woocommerce-mercadopago'),
            'title_default'                             => __('Pix', 'woocommerce-mercadopago'),
            'title_desc_tip'                            => __('The text inserted here will not be translated to other languages', 'woocommerce-mercadopago'),
            'expiration_date_title'                     => __('Expiration for payments via Pix', 'woocommerce-mercadopago'),
            'expiration_date_description'               => __('Set the limit in minutes for your clients to pay via Pix.', 'woocommerce-mercadopago'),
            'expiration_date_options_fifteen_minutes'   => __('15 minutes', 'woocommerce-mercadopago'),
            'expiration_date_options_thirty_minutes'    => __('30 minutes (recommended)', 'woocommerce-mercadopago'),
            'expiration_date_options_sixty_minutes'     => __('60 minutes', 'woocommerce-mercadopago'),
            'expiration_date_options_twelve_hours'      => __('12 hours', 'woocommerce-mercadopago'),
            'expiration_date_options_twenty_four_hours' => __('24 hours', 'woocommerce-mercadopago'),
            'expiration_date_options_two_days'          => __('2 days', 'woocommerce-mercadopago'),
            'expiration_date_options_three_days'        => __('3 days', 'woocommerce-mercadopago'),
            'expiration_date_options_four_days'         => __('4 days', 'woocommerce-mercadopago'),
            'expiration_date_options_five_days'         => __('5 days', 'woocommerce-mercadopago'),
            'expiration_date_options_six_days'          => __('6 days', 'woocommerce-mercadopago'),
            'expiration_date_options_seven_days'        => __('7 days', 'woocommerce-mercadopago'),
            'currency_conversion_title'                 => __('Convert Currency', 'woocommerce-mercadopago'),
            'currency_conversion_subtitle'              => __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago'),
            'currency_conversion_descriptions_enabled'  => $currencyConversionDescriptionsEnabled,
            'currency_conversion_descriptions_disabled' => $currencyConversionDescriptionsDisabled,
            'card_info_title'                           => __('Would you like to know how Pix works?', 'woocommerce-mercadopago'),
            'card_info_subtitle'                        => __('We have a dedicated page where we explain how it works and its advantages.', 'woocommerce-mercadopago'),
            'card_info_button_text'                     => __('Find out more about Pix', 'woocommerce-mercadopago'),
            'advanced_configuration_title'              => __('Advanced configuration of the Pix experience', 'woocommerce-mercadopago'),
            'advanced_configuration_subtitle'           => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'discount_title'                            => __('Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'discount_description'                      => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'discount_checkbox_label'                   => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'commission_title'                          => __('Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'commission_description'                    => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'commission_checkbox_label'                 => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'steps_title'                               => __('To activate Pix, you must have a key registered in Mercado Pago.', 'woocommerce-mercadopago'),
            'steps_step_one_text'                       => __('Download the Mercado Pago app on your cell phone.', 'woocommerce-mercadopago'),
            'steps_step_two_text'                       => $stepsStepTwoText,
            'steps_step_three_text'                     => __('Choose which data to register as Pix keys. After registering, you can set up Pix in your checkout.', 'woocommerce-mercadopago'),
            'steps_observation_one'                     => __('Remember that, for the time being, the Central Bank of Brazil is open Monday through Friday, from 9am to 6pm.', 'woocommerce-mercadopago'),
            'steps_observation_two'                     => __('If you requested your registration outside these hours, we will confirm it within the next business day.', 'woocommerce-mercadopago'),
            'steps_button_about_pix'                    => __('Learn more about Pix', 'woocommerce-mercadopago'),
            'steps_observation_three'                   => __('If you have already registered a Pix key at Mercado Pago and cannot activate Pix in the checkout, ', 'woocommerce-mercadopago'),
            'steps_link_title_one'                      => __('click here.', 'woocommerce-mercadopago'),
        ];
    }
}

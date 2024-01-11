<?php

namespace MercadoPago\Woocommerce\Translations;

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
    public $basicGatewaySettings = [];

    /**
     * @var array
     */
    public $creditsGatewaySettings = [];

    /**
     * @var array
     */
    public $customGatewaySettings = [];

    /**
     * @var array
     */
    public $ticketGatewaySettings = [];

    /**
     * @var array
     */
    public $pixGatewaySettings = [];

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
    public $currency = [];

    /**
     * @var array
     */
    public $statusSync = [];

    /**
     * @var array
     */
    private $links;

    /**
     * Translations constructor
     *
     * @param Links $links
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setNoticesTranslations();
        $this->setPluginSettingsTranslations();
        $this->setHeaderSettingsTranslations();
        $this->setCredentialsSettingsTranslations();
        $this->setStoreSettingsTranslations();
        $this->setOrderSettingsTranslations();
        $this->setGatewaysSettingsTranslations();
        $this->setBasicGatewaySettingsTranslations();
        $this->setCreditsGatewaySettingsTranslations();
        $this->setCustomGatewaySettingsTranslations();
        $this->setTicketGatewaySettingsTranslations();
        $this->setPixGatewaySettingsTranslations();
        $this->setTestModeSettingsTranslations();
        $this->setConfigurationTipsTranslations();
        $this->setUpdateCredentialsTranslations();
        $this->setValidateCredentialsTranslations();
        $this->setUpdateStoreTranslations();
        $this->setCurrencyTranslations();
        $this->setStatusSyncTranslations();
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

        $this->notices = [
            'miss_woocommerce'          => $missWoocommerce,
            'php_wrong_version'         => __('Mercado Pago payments for WooCommerce requires PHP version 7.4 or later. Please update your PHP version.', 'woocommerce-mercadopago'),
            'missing_curl'              => __('Mercado Pago Error: PHP Extension CURL is not installed.', 'woocommerce-mercadopago'),
            'missing_gd_extensions'     => __('Mercado Pago Error: PHP Extension GD is not installed. Installation of GD extension is required to send QR Code Pix by email.', 'woocommerce-mercadopago'),
            'activate_woocommerce'      => __('Activate WooCommerce', 'woocommerce-mercadopago'),
            'install_woocommerce'       => __('Install WooCommerce', 'woocommerce-mercadopago'),
            'see_woocommerce'           => __('See WooCommerce', 'woocommerce-mercadopago'),
            'miss_pix_text'             => __('Please note that to receive payments via Pix at our checkout, you must have a Pix key registered in your Mercado Pago account.', 'woocommerce-mercadopago'),
            'miss_pix_link'             => __('Register your Pix key at Mercado Pago.', 'woocommerce-mercadopago'),
            'dismissed_review_title'    => __('Do you have a minute to share your experience with our plugin?', 'woocommerce-mercadopago'),
            'dismissed_review_subtitle' => __('Your opinion is very important so that we can offer you the best possible payment solution and continue to improve.', 'woocommerce-mercadopago'),
            'dismissed_review_button'   => __('Rate the plugin', 'woocommerce-mercadopago'),
            'saved_cards_title'         => __('Enable payments via Mercado Pago account', 'woocommerce-mercadopago'),
            'saved_cards_subtitle'      => __('When you enable this function, your customers pay faster using their Mercado Pago accounts.</br>The approval rate of these payments in your store can be 25% higher compared to other payment methods.', 'woocommerce-mercadopago'),
            'saved_cards_button'        => __('Activate', 'woocommerce-mercadopago'),
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
            'payment_method' => __('Payment methods', 'woocommerce-mercadopago'),
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
            'cancel_order'                       => __('Cancel order', 'woocommerce-mercadopago'),
            'order_note_commission_title'        => __('Mercado Pago commission:', 'woocommerce-mercadopago'),
            'order_note_commission_tip'          => __('Represents the commission configured on plugin settings.', 'woocommerce-mercadopago'),
            'order_note_discount_title'          => __('Mercado Pago discount:', 'woocommerce-mercadopago'),
            'order_note_discount_tip'            => __('Represents the discount configured on plugin settings.', 'woocommerce-mercadopago'),
            'order_note_installments_fee_tip'    => __('Represents the installment fee charged by Mercado Pago.', 'woocommerce-mercadopago'),
            'order_note_installments_fee_title'  => __('Mercado Pago Installment Fee:', 'woocommerce-mercadopago'),
            'order_note_total_paid_amount_tip'   => __('Represents the total purchase plus the installment fee charged by Mercado Pago.', 'woocommerce-mercadopago'),
            'order_note_total_paid_amount_title' => __('Mercado Pago Total:', 'woocommerce-mercadopago'),
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
            'card_homolog_title'        => __('Activate your credentials to be able to sell', 'woocommerce-mercadopago'),
            'card_homolog_subtitle'     => __('Credentials are codes that you must enter to enable sales. Go below on Activate Credentials. On the next screen, use again the Activate Credentials button and fill in the fields with the requested information.', 'woocommerce-mercadopago'),
            'card_homolog_button_text'  => __('Activate credentials', 'woocommerce-mercadopago'),
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
            'title_store'                   => __('2. Customize your business', 'woocommerce-mercadopago'),
            'title_info_store'              => __('Your store information', 'woocommerce-mercadopago'),
            'title_advanced_store'          => __('Advanced integration options (optional)', 'woocommerce-mercadopago'),
            'title_debug'                   => __('Debug and Log Mode', 'woocommerce-mercadopago'),
            'subtitle_store'                => __('Fill out the following information to have a better experience and offer more information to your clients.', 'woocommerce-mercadopago'),
            'subtitle_name_store'           => __('Name of your store in your client\'s invoice', 'woocommerce-mercadopago'),
            'subtitle_activities_store'     => __('Identification in Activities of Mercado Pago', 'woocommerce-mercadopago'),
            'subtitle_advanced_store'       => __('For further integration of your store with Mercado Pago (IPN, Certified Partners, Debug Mode)', 'woocommerce-mercadopago'),
            'subtitle_category_store'       => __('Store category', 'woocommerce-mercadopago'),
            'subtitle_url'                  => __('URL for IPN', 'woocommerce-mercadopago'),
            'subtitle_integrator'           => __('Integrator ID', 'woocommerce-mercadopago'),
            'subtitle_debug'                => __('We record your store\'s actions in order to provide a better assistance.', 'woocommerce-mercadopago'),
            'placeholder_name_store'        => __('Ex: Mary\'s Store', 'woocommerce-mercadopago'),
            'placeholder_activities_store'  => __('Ex: Mary Store', 'woocommerce-mercadopago'),
            'placeholder_category_store'    => __('Select', 'woocommerce-mercadopago'),
            'placeholder_url'               => __('Ex: https://examples.com/my-custom-ipn-url', 'woocommerce-mercadopago'),
            'options_url'                   => __('Add plugin default params', 'woocommerce-mercadopago'),
            'placeholder_integrator'        => __('Ex: 14987126498', 'woocommerce-mercadopago'),
            'accordion_advanced_store_show' => __('Show advanced options', 'woocommerce-mercadopago'),
            'accordion_advanced_store_hide' => __('Hide advanced options', 'woocommerce-mercadopago'),
            'button_store'                  => __('Save and continue', 'woocommerce-mercadopago'),
            'helper_name_store'             => __('If this field is empty, the purchase will be identified as Mercado Pago.', 'woocommerce-mercadopago'),
            'helper_activities_store'       => __('In Activities, you will view this term before the order number', 'woocommerce-mercadopago'),
            'helper_category_store'         => __('Select "Other categories" if you do not find the appropriate category.', 'woocommerce-mercadopago'),
            'helper_integrator_link'        => __('request it now.', 'woocommerce-mercadopago'),
            'helper_url'                    => $helperUrl,
            'helper_integrator'             => $helperIntegrator
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
            'enabled'           => __('Enabled', 'woocommerce-mercadopago'),
            'disabled'          => __('Disabled', 'woocommerce-mercadopago'),
            'empty_credentials' => __('Configure your credentials to enable Mercado Pago payment methods.', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set basic settings translations
     *
     * @return void
     */
    private function setBasicGatewaySettingsTranslations(): void
    {
        $enabledDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('The checkout is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $enabledDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('The checkout is', 'woocommerce-mercadopago'),
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

        $autoReturnDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('The buyer', 'woocommerce-mercadopago'),
            __('will be automatically redirected to the store', 'woocommerce-mercadopago')
        );

        $autoReturnDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('The buyer', 'woocommerce-mercadopago'),
            __('will not be automatically redirected to the store', 'woocommerce-mercadopago')
        );


        $binaryModeDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Pending payments', 'woocommerce-mercadopago'),
            __('will be automatically declined', 'woocommerce-mercadopago')
        );

        $binaryModeDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Pending payments', 'woocommerce-mercadopago'),
            __('will not be automatically declined', 'woocommerce-mercadopago')
        );

        $this->basicGatewaySettings = [
            'gateway_title'                             => __('Your saved cards or money in Mercado Pago', 'woocommerce-mercadopago'),
            'gateway_description'                       => __('Debit, Credit and invoice in Mercado Pago environment', 'woocommerce-mercadopago'),
            'gateway_method_title'                      => __('Mercado Pago - Checkout Pro', 'woocommerce-mercadopago'),
            'gateway_method_description'                => __('Debit, Credit and invoice in Mercado Pago environment', 'woocommerce-mercadopago'),
            'header_title'                              => __('Checkout Pro', 'woocommerce-mercadopago'),
            'header_description'                        => __('With Checkout Pro you sell with all the safety inside Mercado Pago environment.', 'woocommerce-mercadopago'),
            'card_settings_title'                       => __('Mercado Pago plugin general settings', 'woocommerce-mercadopago'),
            'card_settings_subtitle'                    => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
            'card_settings_button_text'                 => __('Go to Settings', 'woocommerce-mercadopago'),
            'enabled_title'                             => __('Enable the checkout', 'woocommerce-mercadopago'),
            'enabled_subtitle'                          => __('By disabling it, you will disable all payments from Mercado Pago Checkout at Mercado Pago website by redirect.', 'woocommerce-mercadopago'),
            'enabled_descriptions_enabled'              => $enabledDescriptionsEnabled,
            'enabled_descriptions_disabled'             => $enabledDescriptionsDisabled,
            'title_title'                               => __('Title in the store Checkout', 'woocommerce-mercadopago'),
            'title_description'                         => __('Change the display text in Checkout, maximum characters: 85', 'woocommerce-mercadopago'),
            'title_default'                             => __('Your saved cards or money in Mercado Pago', 'woocommerce-mercadopago'),
            'title_desc_tip'                            => __('The text inserted here will not be translated to other languages', 'woocommerce-mercadopago'),
            'currency_conversion_title'                 => __('Convert Currency', 'woocommerce-mercadopago'),
            'currency_conversion_subtitle'              => __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago'),
            'currency_conversion_descriptions_enabled'  => $currencyConversionDescriptionsEnabled,
            'currency_conversion_descriptions_disabled' => $currencyConversionDescriptionsDisabled,
            'ex_payments_title'                         => __('Choose the payment methods you accept in your store', 'woocommerce-mercadopago'),
            'ex_payments_description'                   => __('Enable the payment methods available to your clients.', 'woocommerce-mercadopago'),
            'ex_payments_type_credit_card_label'        => __('Credit Cards', 'woocommerce-mercadopago'),
            'ex_payments_type_debit_card_label'         => __('Debit Cards', 'woocommerce-mercadopago'),
            'ex_payments_type_other_label'              => __('Other Payment Methods', 'woocommerce-mercadopago'),
            'installments_title'                        => __('Maximum number of installments', 'woocommerce-mercadopago'),
            'installments_description'                  => __('What is the maximum quota with which a customer can buy?', 'woocommerce-mercadopago'),
            'installments_options_1'                    => __('1 installment', 'woocommerce-mercadopago'),
            'installments_options_2'                    => __('2 installments', 'woocommerce-mercadopago'),
            'installments_options_3'                    => __('3 installments', 'woocommerce-mercadopago'),
            'installments_options_4'                    => __('4 installments', 'woocommerce-mercadopago'),
            'installments_options_5'                    => __('5 installments', 'woocommerce-mercadopago'),
            'installments_options_6'                    => __('6 installments', 'woocommerce-mercadopago'),
            'installments_options_10'                   => __('10 installments', 'woocommerce-mercadopago'),
            'installments_options_12'                   => __('12 installments', 'woocommerce-mercadopago'),
            'installments_options_15'                   => __('15 installments', 'woocommerce-mercadopago'),
            'installments_options_18'                   => __('18 installments', 'woocommerce-mercadopago'),
            'installments_options_24'                   => __('24 installments', 'woocommerce-mercadopago'),
            'advanced_configuration_title'              => __('Advanced settings', 'woocommerce-mercadopago'),
            'advanced_configuration_description'        => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'method_title'                              => __('Payment experience', 'woocommerce-mercadopago'),
            'method_description'                        => __('Define what payment experience your customers will have, whether inside or outside your store.', 'woocommerce-mercadopago'),
            'method_options_redirect'                   => __('Redirect', 'woocommerce-mercadopago'),
            'method_options_modal'                      => __('Modal', 'woocommerce-mercadopago'),
            'auto_return_title'                         => __('Return to the store', 'woocommerce-mercadopago'),
            'auto_return_subtitle'                      => __('Do you want your customer to automatically return to the store after payment?', 'woocommerce-mercadopago'),
            'auto_return_descriptions_enabled'          => $autoReturnDescriptionsEnabled,
            'auto_return_descriptions_disabled'         => $autoReturnDescriptionsDisabled,
            'success_url_title'                         => __('Success URL', 'woocommerce-mercadopago'),
            'success_url_description'                   => __('Choose the URL that we will show your customers when they finish their purchase.', 'woocommerce-mercadopago'),
            'failure_url_title'                         => __('Payment URL rejected', 'woocommerce-mercadopago'),
            'failure_url_description'                   => __('Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.', 'woocommerce-mercadopago'),
            'pending_url_title'                         => __('Payment URL pending', 'woocommerce-mercadopago'),
            'pending_url_description'                   => __('Choose the URL that we will show to your customers when they have a payment pending approval.', 'woocommerce-mercadopago'),
            'binary_mode_title'                         => __('Automatic decline of payments without instant approval', 'woocommerce-mercadopago'),
            'binary_mode_subtitle'                      => __('Enable it if you want to automatically decline payments that are not instantly approved by banks or other institutions.', 'woocommerce-mercadopago'),
            'binary_mode_default'                       => __('Debit, Credit and Invoice in Mercado Pago environment.', 'woocommerce-mercadopago'),
            'binary_mode_descriptions_enabled'          => $binaryModeDescriptionsEnabled,
            'binary_mode_descriptions_disabled'         => $binaryModeDescriptionsDisabled,
            'discount_title'                            => __('Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'discount_description'                      => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'discount_checkbox_label'                   => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'commission_title'                          => __('Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'commission_description'                    => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'commission_checkbox_label'                 => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'invalid_back_url'                          => __('This seems to be an invalid URL', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits settings translations
     *
     * @return void
     */
    private function setCreditsGatewaySettingsTranslations(): void
    {
        $enabledDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Payment in installments without card in the store checkout is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $enabledDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Payment in installments without card in the store checkout is', 'woocommerce-mercadopago'),
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

        $creditsBannerDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('The installments without card component is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $creditsBannerDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('The installments without card component is', 'woocommerce-mercadopago'),
            __('disabled', 'woocommerce-mercadopago')
        );

        $this->creditsGatewaySettings = [
            'gateway_title'                             => __('Installments without card', 'woocommerce-mercadopago'),
            'gateway_description'                       => __('Customers who buy on spot and pay later in up to 12 installments', 'woocommerce-mercadopago'),
            'gateway_method_title'                      => __('Mercado Pago - Installments without card', 'woocommerce-mercadopago'),
            'gateway_method_description'                => __('Customers who buy on spot and pay later in up to 12 installments', 'woocommerce-mercadopago'),
            'header_title'                              => __('Installments without card', 'woocommerce-mercadopago'),
            'header_description'                        => __('Reach millions of buyers by offering Mercado Credito as a payment method. Our flexible payment options give your customers the possibility to buy today whatever they want in up to 12 installments without the need to use a credit card. For your business, the approval of the purchase is immediate and guaranteed.', 'woocommerce-mercadopago'),
            'card_settings_title'                       => __('Mercado Pago plugin general settings', 'woocommerce-mercadopago'),
            'card_settings_subtitle'                    => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
            'card_settings_button_text'                 => __('Go to Settings', 'woocommerce-mercadopago'),
            'enabled_title'                             => __('Activate installments without card in your store checkout', 'woocommerce-mercadopago'),
            'enabled_subtitle'                          => __('Offer the option to pay in installments without card directly from your store\'s checkout.', 'woocommerce-mercadopago'),
            'enabled_descriptions_enabled'              => $enabledDescriptionsEnabled,
            'enabled_descriptions_disabled'             => $enabledDescriptionsDisabled,
            'enabled_toggle_title'                      => __('Checkout visualization', 'woocommerce-mercadopago'),
            'enabled_toggle_subtitle'                   => __('Check below how this feature will be displayed to your customers:', 'woocommerce-mercadopago'),
            'enabled_toggle_footer'                     => __('Checkout Preview', 'woocommerce-mercadopago'),
            'enabled_toggle_pill_text'                  => __('PREVIEW', 'woocommerce-mercadopago'),
            'title_title'                               => __('Title in the store Checkout', 'woocommerce-mercadopago'),
            'title_description'                         => __('It is possible to edit the title. Maximum of 85 characters.', 'woocommerce-mercadopago'),
            'title_default'                             => __('Checkout without card', 'woocommerce-mercadopago'),
            'title_desc_tip'                            => __('The text inserted here will not be translated to other languages', 'woocommerce-mercadopago'),
            'currency_conversion_title'                 => __('Convert Currency', 'woocommerce-mercadopago'),
            'currency_conversion_subtitle'              => __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago'),
            'currency_conversion_descriptions_enabled'  => $currencyConversionDescriptionsEnabled,
            'currency_conversion_descriptions_disabled' => $currencyConversionDescriptionsDisabled,
            'credits_banner_title'                      => __('Inform your customers about the option of paying in installments without card', 'woocommerce-mercadopago'),
            'credits_banner_subtitle'                   => __('By activating the installments without card component, you increase your chances of selling.', 'woocommerce-mercadopago'),
            'credits_banner_descriptions_enabled'       => $creditsBannerDescriptionsEnabled,
            'credits_banner_descriptions_disabled'      => $creditsBannerDescriptionsDisabled,
            'credits_banner_desktop'                    => __('Banner on the product page | Computer version', 'woocommerce-mercadopago'),
            'credits_banner_cellphone'                  => __('Banner on the product page | Cellphone version', 'woocommerce-mercadopago'),
            'credits_banner_toggle_computer'            => __('Computer', 'woocommerce-mercadopago'),
            'credits_banner_toggle_mobile'              => __('Mobile', 'woocommerce-mercadopago'),
            'credits_banner_toggle_title'               => __('Component visualization', 'woocommerce-mercadopago'),
            'credits_banner_toggle_subtitle'            => __('Check below how this feature will be displayed to your customers:', 'woocommerce-mercadopago'),
            'advanced_configuration_title'              => __('Advanced settings', 'woocommerce-mercadopago'),
            'advanced_configuration_description'        => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'discount_title'                            => __('Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'discount_description'                      => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'discount_checkbox_label'                   => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'commission_title'                          => __('Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'commission_description'                    => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'commission_checkbox_label'                 => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set custom settings translations
     *
     * @return void
     */
    private function setCustomGatewaySettingsTranslations(): void
    {
        $enabledDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Transparent Checkout for credit cards is', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $enabledDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Transparent Checkout for credit cards is', 'woocommerce-mercadopago'),
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

        $walletButtonDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Payments via Mercado Pago accounts are', 'woocommerce-mercadopago'),
            __('enabled', 'woocommerce-mercadopago')
        );

        $walletButtonDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Payments via Mercado Pago accounts are', 'woocommerce-mercadopago'),
            __('disabled', 'woocommerce-mercadopago')
        );

        $binaryModeDescriptionsEnabled = sprintf(
            '%s <b>%s</b>.',
            __('Pending payments', 'woocommerce-mercadopago'),
            __('will be automatically declined', 'woocommerce-mercadopago')
        );

        $binaryModeDescriptionsDisabled = sprintf(
            '%s <b>%s</b>.',
            __('Pending payments', 'woocommerce-mercadopago'),
            __('will not be automatically declined', 'woocommerce-mercadopago')
        );

        $this->customGatewaySettings = [
            'gateway_title'                             => __('Debit and Credit', 'woocommerce-mercadopago'),
            'gateway_description'                       => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'gateway_method_title'                      => __('Mercado pago - Customized Checkout', 'woocommerce-mercadopago'),
            'gateway_method_description'                => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'header_title'                              => __('Transparent Checkout | Credit card', 'woocommerce-mercadopago'),
            'header_description'                        => __('With the Transparent Checkout, you can sell inside your store environment, without redirection and with the security from Mercado Pago.', 'woocommerce-mercadopago'),
            'card_settings_title'                       => __('Mercado Pago Plugin general settings', 'woocommerce-mercadopago'),
            'card_settings_subtitle'                    => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
            'card_settings_button_text'                 => __('Go to Settings', 'woocommerce-mercadopago'),
            'enabled_title'                             => __('Enable the checkout', 'woocommerce-mercadopago'),
            'enabled_subtitle'                          => __('By disabling it, you will disable all credit cards payments from Mercado Pago Transparent Checkout.', 'woocommerce-mercadopago'),
            'enabled_descriptions_enabled'              => $enabledDescriptionsEnabled,
            'enabled_descriptions_disabled'             => $enabledDescriptionsDisabled,
            'title_title'                               => __('Title in the store Checkout', 'woocommerce-mercadopago'),
            'title_description'                         => __('Change the display text in Checkout, maximum characters: 85', 'woocommerce-mercadopago'),
            'title_default'                             => __('Debit and Credit', 'woocommerce-mercadopago'),
            'title_desc_tip'                            => __('The text inserted here will not be translated to other languages', 'woocommerce-mercadopago'),
            'card_info_fees_title'                      => __('Installments Fees', 'woocommerce-mercadopago'),
            'card_info_fees_subtitle'                   => __('Set installment fees and whether they will be charged from the store or from the buyer.', 'woocommerce-mercadopago'),
            'card_info_fees_button_url'                 => __('Set fees', 'woocommerce-mercadopago'),
            'currency_conversion_title'                 => __('Convert Currency', 'woocommerce-mercadopago'),
            'currency_conversion_subtitle'              => __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago'),
            'currency_conversion_descriptions_enabled'  => $currencyConversionDescriptionsEnabled,
            'currency_conversion_descriptions_disabled' => $currencyConversionDescriptionsDisabled,
            'wallet_button_title'                       => __('Payments via Mercado Pago account', 'woocommerce-mercadopago'),
            'wallet_button_subtitle'                    => __('Your customers pay faster with saved cards, money balance or other available methods in their Mercado Pago accounts.', 'woocommerce-mercadopago'),
            'wallet_button_descriptions_enabled'        => $walletButtonDescriptionsEnabled,
            'wallet_button_descriptions_disabled'       => $walletButtonDescriptionsDisabled,
            'wallet_button_preview_description'         => __('Check an example of how it will appear in your store:', 'woocommerce-mercadopago'),
            'advanced_configuration_title'              => __('Advanced configuration of the personalized payment experience', 'woocommerce-mercadopago'),
            'advanced_configuration_subtitle'           => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'binary_mode_title'                         => __('Automatic decline of payments without instant approval', 'woocommerce-mercadopago'),
            'binary_mode_subtitle'                      => __('Enable it if you want to automatically decline payments that are not instantly approved by banks or other institutions.', 'woocommerce-mercadopago'),
            'binary_mode_descriptions_enabled'          => $binaryModeDescriptionsEnabled,
            'binary_mode_descriptions_disabled'         => $binaryModeDescriptionsDisabled,
            'discount_title'                            => __('Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'discount_description'                      => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'discount_checkbox_label'                   => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'commission_title'                          => __('Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'commission_description'                    => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'commission_checkbox_label'                 => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set ticket settings translations
     *
     * @return void
     */
    private function setTicketGatewaySettingsTranslations(): void
    {
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

        $this->ticketGatewaySettings = [
            'gateway_title'                => __('Invoice', 'woocommerce-mercadopago'),
            'gateway_description'          => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'method_title'                 => __('Mercado pago - Customized Checkout', 'woocommerce-mercadopago'),
            'header_title'                 => __('Transparent Checkout | Invoice or Loterica', 'woocommerce-mercadopago'),
            'header_description'           => __('With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago.', 'woocommerce-mercadopago'),
            'card_settings_title'          => __('Mercado Pago plugin general settings', 'woocommerce-mercadopago'),
            'card_settings_subtitle'       => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
            'card_settings_button_text'    => __('Go to Settings', 'woocommerce-mercadopago'),
            'enabled_title'                => __('Enable the Checkout', 'woocommerce-mercadopago'),
            'enabled_subtitle'             => __('By disabling it, you will disable all invoice payments from Mercado Pago Transparent Checkout.', 'woocommerce-mercadopago'),
            'enabled_enabled'              => __('The transparent checkout for tickets is <b>enabled</b>.', 'woocommerce-mercadopago'),
            'enabled_disabled'             => __('The transparent checkout for tickets is <b>disabled</b>.', 'woocommerce-mercadopago'),
            'title_title'                  => __('Title in the store Checkout', 'woocommerce-mercadopago'),
            'title_description'            => __('Change the display text in Checkout, maximum characters: 85', 'woocommerce-mercadopago'),
            'title_default'                => __('Invoice', 'woocommerce-mercadopago'),
            'title_desc_tip'               => __('The text inserted here will not be translated to other languages', 'woocommerce-mercadopago'),
            'currency_conversion_title'    => __('Convert Currency', 'woocommerce-mercadopago'),
            'currency_conversion_subtitle' => __('Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.', 'woocommerce-mercadopago'),
            'currency_conversion_enabled'  => $currencyConversionDescriptionsEnabled,
            'currency_conversion_disabled' => $currencyConversionDescriptionsDisabled,
            'date_expiration_title'        => __('Payment Due', 'woocommerce-mercadopago'),
            'date_expiration_description'  => __('In how many days will cash payments expire.', 'woocommerce-mercadopago'),
            'advanced_title_title'         => __('Advanced configuration of the cash payment experience', 'woocommerce-mercadopago'),
            'advanced_description_title'   => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'stock_reduce_title'           => __('Reduce inventory', 'woocommerce-mercadopago'),
            'stock_reduce_subtitle'        => __('Activates inventory reduction during the creation of an order, whether or not the final payment is credited. Disable this option to reduce it only when payments are approved.', 'woocommerce-mercadopago'),
            'stock_reduce_enabled'         => __('Reduce inventory is <b>enabled</b>.', 'woocommerce-mercadopago'),
            'stock_reduce_disabled'        => __('Reduce inventory is <b>disabled</b>.', 'woocommerce-mercadopago'),
            'type_payments_title'          => __('Payment methods', 'woocommerce-mercadopago'),
            'type_payments_description'    => __('Enable the available payment methods', 'woocommerce-mercadopago'),
            'type_payments_desctip'        => __('Choose the available payment methods in your store.', 'woocommerce-mercadopago'),
            'type_payments_label'          => __('All payment methods', 'woocommerce-mercadopago'),
            'discount_title'               => __('Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'discount_description'         => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'discount_checkbox_label'      => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
            'commission_title'             => __('Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago'),
            'commission_description'       => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'commission_checkbox_label'    => __('Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set pix settings translations
     *
     * @return void
     */
    private function setPixGatewaySettingsTranslations(): void
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

        $this->pixGatewaySettings = [
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
            'expiration_date_options_15_minutes'        => __('15 minutes', 'woocommerce-mercadopago'),
            'expiration_date_options_30_minutes'        => __('30 minutes (recommended)', 'woocommerce-mercadopago'),
            'expiration_date_options_60_minutes'        => __('60 minutes', 'woocommerce-mercadopago'),
            'expiration_date_options_12_hours'          => __('12 hours', 'woocommerce-mercadopago'),
            'expiration_date_options_24_hours'          => __('24 hours', 'woocommerce-mercadopago'),
            'expiration_date_options_2_days'            => __('2 days', 'woocommerce-mercadopago'),
            'expiration_date_options_3_days'            => __('3 days', 'woocommerce-mercadopago'),
            'expiration_date_options_4_days'            => __('4 days', 'woocommerce-mercadopago'),
            'expiration_date_options_5_days'            => __('5 days', 'woocommerce-mercadopago'),
            'expiration_date_options_6_days'            => __('6 days', 'woocommerce-mercadopago'),
            'expiration_date_options_7_days'            => __('7 days', 'woocommerce-mercadopago'),
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
            'valid_payment_tips'       => __('At least one payment method is enabled', 'woocommerce-mercadopago'),
            'invalid_payment_tips'     => __('No payment method enabled', 'woocommerce-mercadopago'),
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
            'for_test_mode'                    => __(' for test mode', 'woocommerce-mercadopago'),
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
     * Set currency translations
     *
     * @return void
     */
    private function setCurrencyTranslations(): void
    {
        $notCompatibleCurrencyConversion = sprintf(
            '<b>%s</b> %s',
            __('Attention:', 'woocommerce-mercadopago'),
            __('The currency settings you have in WooCommerce are not compatible with the currency you use in your Mercado Pago account. Please activate the currency conversion.', 'woocommerce-mercadopago')
        );

        $baseConversionMessage = __('We are converting your currency from: ', 'woocommerce-mercadopago');
        $this->currency = [
            'not_compatible_currency_conversion' => $notCompatibleCurrencyConversion,
            'now_we_convert'     => $this->generateConversionMessage($baseConversionMessage),
        ];
    }

    /**
     * Generate conversion message
     *
     * @param string $baseMessage
     *
     * @return string
     */
    private function generateConversionMessage(string $baseMessage): string
    {
        return sprintf('%s %s %s ', $baseMessage, get_woocommerce_currency(), __("to ", 'woocommerce-mercadopago'));
    }

    /**
     * Set status sync metabox translations
     *
     * @return void
     */
    private function setStatusSyncTranslations(): void
    {
        $this->statusSync = [
            'metabox_title'                                    => __('Payment status on Mercado Pago', 'woocommerce-mercadopago'),
            'card_title'                                       => __('This is the payment status of your Mercado Pago Activities. To check the order status, please refer to Order details.', 'woocommerce-mercadopago'),
            'link_description_success'                         => __('View purchase details at Mercado Pago', 'woocommerce-mercadopago'),
            'sync_button_success'                              => __('Sync order status', 'woocommerce-mercadopago'),
            'link_description_pending'                         => __('View purchase details at Mercado Pago', 'woocommerce-mercadopago'),
            'sync_button_pending'                              => __('Sync order status', 'woocommerce-mercadopago'),
            'link_description_failure'                         => __('Check the reasons why the purchase was declined.', 'woocommerce-mercadopago'),
            'sync_button_failure'                              => __('Sync order status', 'woocommerce-mercadopago'),
            'response_success'                                 => __('Order update successfully. This page will be reloaded...', 'woocommerce-mercadopago'),
            'response_error'                                   => __('Unable to update order:', 'woocommerce-mercadopago'),
            'alert_title_accredited'                           => __('Payment made', 'woocommerce-mercadopago'),
            'description_accredited'                           => __('Payment made by the buyer and already credited in the account.', 'woocommerce-mercadopago'),
            'alert_title_settled'                              => __('Call resolved', 'woocommerce-mercadopago'),
            'description_settled'                              => __('Please contact Mercado Pago for further details.', 'woocommerce-mercadopago'),
            'alert_title_reimbursed'                           => __('Payment refunded', 'woocommerce-mercadopago'),
            'description_reimbursed'                           => __('Your refund request has been made. Please contact Mercado Pago for further details.', 'woocommerce-mercadopago'),
            'alert_title_refunded'                             => __('Payment returned', 'woocommerce-mercadopago'),
            'description_refunded'                             => __('The payment has been returned to the client.', 'woocommerce-mercadopago'),
            'alert_title_partially_refunded'                   => __('Payment returned', 'woocommerce-mercadopago'),
            'description_partially_refunded'                   => __('The payment has been partially returned to the client.', 'woocommerce-mercadopago'),
            'alert_title_by_collector'                         => __('Payment canceled', 'woocommerce-mercadopago'),
            'description_by_collector'                         => __('The payment has been successfully canceled.', 'woocommerce-mercadopago'),
            'alert_title_by_payer'                             => __('Purchase canceled', 'woocommerce-mercadopago'),
            'description_by_payer'                             => __('The payment has been canceled by the customer.', 'woocommerce-mercadopago'),
            'alert_title_pending'                              => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending'                              => __('Awaiting payment from the buyer.', 'woocommerce-mercadopago'),
            'alert_title_pending_waiting_payment'              => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_waiting_payment'              => __('Awaiting payment from the buyer.', 'woocommerce-mercadopago'),
            'alert_title_pending_waiting_for_remedy'           => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_waiting_for_remedy'           => __('Awaiting payment from the buyer.', 'woocommerce-mercadopago'),
            'alert_title_pending_waiting_transfer'             => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_waiting_transfer'             => __('Awaiting payment from the buyer.', 'woocommerce-mercadopago'),
            'alert_title_pending_review_manual'                => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_review_manual'                => __('We are veryfing the payment. We will notify you by email in up to 6 hours if everything is fine so that you can deliver the product or provide the service.', 'woocommerce-mercadopago'),
            'alert_title_waiting_bank_confirmation'            => __('Declined payment', 'woocommerce-mercadopago'),
            'description_waiting_bank_confirmation'            => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_pending_capture'                      => __('Payment authorized. Awaiting capture.', 'woocommerce-mercadopago'),
            'description_pending_capture'                      => __("The payment has been authorized on the client's card. Please capture the payment.", 'woocommerce-mercadopago'),
            'alert_title_in_process'                           => __('Payment in process', 'woocommerce-mercadopago'),
            'description_in_process'                           => __('Please wait or contact Mercado Pago for further details', 'woocommerce-mercadopago'),
            'alert_title_pending_contingency'                  => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_contingency'                  => __('The bank is reviewing the payment. As soon as we have their confirmation, we will notify you via email so that you can deliver the product or provide the service.', 'woocommerce-mercadopago'),
            'alert_title_pending_card_validation'              => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_card_validation'              => __('Awaiting payment information validation.', 'woocommerce-mercadopago'),
            'alert_title_pending_online_validation'            => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_online_validation'            => __('Awaiting payment information validation.', 'woocommerce-mercadopago'),
            'alert_title_pending_additional_info'              => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_additional_info'              => __('Awaiting payment information validation.', 'woocommerce-mercadopago'),
            'alert_title_offline_process'                      => __('Pending payment', 'woocommerce-mercadopago'),
            'description_offline_process'                      => __('Please wait or contact Mercado Pago for further details', 'woocommerce-mercadopago'),
            'alert_title_pending_challenge'                    => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_challenge'                    => __('Waiting for the buyer.', 'woocommerce-mercadopago'),
            'alert_title_pending_provider_response'            => __('Pending payment', 'woocommerce-mercadopago'),
            'description_pending_provider_response'            => __('Waiting for the card issuer.', 'woocommerce-mercadopago'),
            'alert_title_bank_rejected'                        => __('Declined payment', 'woocommerce-mercadopago'),
            'description_bank_rejected'                        => __('The payment could not be processed. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_rejected_by_bank'                     => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_by_bank'                     => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_rejected_insufficient_data'           => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_insufficient_data'           => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_bank_error'                           => __('Declined payment', 'woocommerce-mercadopago'),
            'description_bank_error'                           => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_by_admin'                             => __('Mercado Pago did not process the payment', 'woocommerce-mercadopago'),
            'description_by_admin'                             => __('Please contact Mercado Pago for further details.', 'woocommerce-mercadopago'),
            'alert_title_expired'                              => __('Expired payment deadline', 'woocommerce-mercadopago'),
            'description_expired'                              => __('The client did not pay within the time limit.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_bad_filled_card_number'   => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_bad_filled_card_number'   => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_bad_filled_security_code' => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_bad_filled_security_code' => __('The CVV is invalid. Please ask your client to review the details or use another card.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_bad_filled_date'          => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_bad_filled_date'          => __('The card is expired. Please ask your client to use another card or to contact the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_high_risk'                => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_high_risk'                => __('This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_fraud'                    => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_fraud'                    => __('The buyer is suspended in our platform. Your client must contact us to check what happened.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_blacklist'                => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_blacklist'                => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_insufficient_amount'      => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_insufficient_amount'      => __('The card does not have sufficient balance. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'description_cc_rejected_insufficient_amount_cc'   => __('The card does not have enough limit. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_other_reason'             => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_other_reason'             => __('The card-issuing bank declined the payment. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_max_attempts'             => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_max_attempts'             => __('The CVV was entered incorrectly several times. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_invalid_installments'     => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_invalid_installments'     => __('The card does not allow the number of installments entered. Please ask your client to choose another installment plan or to use another card.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_call_for_authorize'       => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_call_for_authorize'       => __('The card-issuing bank declined the payment. Please instruct your client to ask the bank to authotize it or to use another card.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_duplicated_payment'       => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_duplicated_payment'       => __('From Mercado Pago we have detected that this payment has already been made before. If that is not the case, your client may try to pay again.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_card_disabled'            => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_card_disabled'            => __('The card is not active yet. Please ask your client to use another card or to get in touch with the bank to activate it.', 'woocommerce-mercadopago'),
            'alert_title_payer_unavailable'                    => __('Declined payment', 'woocommerce-mercadopago'),
            'description_payer_unavailable'                    => __('The buyer is suspended in our platform. Your client must contact us to check what happened.', 'woocommerce-mercadopago'),
            'alert_title_rejected_high_risk'                   => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_high_risk'                   => __('This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago'),
            'alert_title_rejected_by_regulations'              => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_by_regulations'              => __('This payment was declined because it did not pass Mercado Pago security controls. Please ask your client to use another card.', 'woocommerce-mercadopago'),
            'alert_title_rejected_cap_exceeded'                => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_cap_exceeded'                => __('The amount exceeded the card limit. Please ask your client to use another card or to get in touch with the bank.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_3ds_challenge'            => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_3ds_challenge'            => __('Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago'),
            'alert_title_rejected_other_reason'                => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_other_reason'                => __('Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago'),
            'alert_title_authorization_revoked'                => __('Declined payment', 'woocommerce-mercadopago'),
            'description_authorization_revoked'                => __('Please ask your client to use another card or to get in touch with the card issuer.', 'woocommerce-mercadopago'),
            'alert_title_cc_amount_rate_limit_exceeded'        => __('Pending payment', 'woocommerce-mercadopago'),
            'description_cc_amount_rate_limit_exceeded'        => __("The amount exceeded the card's limit. Please ask your client to use another card or to get in touch with the bank.", 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_expired_operation'        => __('Expired payment deadline', 'woocommerce-mercadopago'),
            'description_cc_rejected_expired_operation'        => __('The client did not pay within the time limit.', 'woocommerce-mercadopago'),
            'alert_title_cc_rejected_bad_filled_other'         => __('Declined payment', 'woocommerce-mercadopago'),
            'description_cc_rejected_bad_filled_other'         => __('The debit function is not enabled for the card. Please tell your client that it is possible to pay with credit or to use another one.', 'woocommerce-mercadopago'),
            'description_cc_rejected_bad_filled_other_cc'      => __('The credit function is not enabled for the card. Please tell your client that it is possible to pay with debit or to use another one.', 'woocommerce-mercadopago'),
            'alert_title_rejected_call_for_authorize'          => __('Declined payment', 'woocommerce-mercadopago'),
            'description_rejected_call_for_authorize'          => __('The card-issuing bank declined the payment. Please instruct your client to ask the bank to authorize it.', 'woocommerce-mercadopago'),
            'alert_title_am_insufficient_amount'               => __('Declined payment', 'woocommerce-mercadopago'),
            'description_am_insufficient_amount'               => __('The buyer does not have enough balance to make the purchase. Please ask your client to deposit money to the Mercado Pago Account or to use a different payment method.', 'woocommerce-mercadopago'),
            'alert_title_generic'                              => __('There was an error', 'woocommerce-mercadopago'),
            'description_generic'                              => __('The transaction could not be completed.', 'woocommerce-mercadopago'),
        ];
    }
}

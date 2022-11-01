<?php

namespace MercadoPago\Woocommerce\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Translations
{
    private static Translations $instance;
    public static string $domain = 'woocommerce-mercadopago';
    public static array $notices = [];
    public static array $headerSettings = [];
    public static array $credentialsSettings = [];
    public static array $storeSettings = [];
    public static array $orderSettings = [];
    public static array $genericSettings = [];

    public function __construct()
    {
        $this->setNoticesTranslations();
        $this->setHeaderSettingsTranslations();
        $this->setCredentialsSettingsTranslations();
        $this->setStoreSettingsTranslations();
        $this->setOrderSettingsTranslations();
        $this->setGenericSettingsTranslations();
    }

    public static function getInstance(): Translations
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function translate($text): string
    {
        return __($text, self::$domain);
    }

    private function setNoticesTranslations(): void
    {
        $missWoocommerce = sprintf(
            $this->translate('The Mercado Pago module needs an active version of %s in order to work!'),
            '<a target="_blank" href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
        );

        self::$notices = [
            'php_wrong_version'     => $this->translate('Mercado Pago payments for WooCommerce requires PHP version 7.2 or later. Please update your PHP version.'),
            'missing_curl'          => $this->translate('Mercado Pago Error: PHP Extension CURL is not installed.'),
            'missing_gd_extensions' => $this->translate('Mercado Pago Error: PHP Extension GD is not installed. Installation of GD extension is required to send QR Code Pix by email.'),
            'activate_woocommerce'  => $this->translate('Activate WooCommerce'),
            'install_woocommerce'   => $this->translate('Install WooCommerce'),
            'see_woocommerce'       => $this->translate('See WooCommerce'),
            'miss_woocommerce'      => $missWoocommerce,
        ];
    }

    private function setHeaderSettingsTranslations(): void
    {
        $titleHeader = sprintf(
            '%s <b>%s</b> %s <br/> %s <b>%s</b> %s',
            $this->translate('Accept'),
            $this->translate('payments on the spot'),
            $this->translate('with'),
            $this->translate('the'),
            $this->translate('security'),
            $this->translate('from Mercado Pago')
        );

        $installmentsDescription = sprintf(
            '%s <b>%s</b> %s <b>%s</b> %s',
            $this->translate('Choose'),
            $this->translate('when you want to receive the money'),
            $this->translate('from your sales and if you want to offer'),
            $this->translate('interest-free installments'),
            $this->translate('to your clients.')
        );

        $questionsDescription = sprintf(
            '%s <b>%s</b> %s',
            $this->translate('Review the step-by-step of'),
            $this->translate('how to integrate the Mercado Pago Plugin'),
            $this->translate('on our website for developers.')
        );

        self::$headerSettings = [
            'ssl'                      => $this->translate('SSL'),
            'curl'                     => $this->translate('Curl'),
            'gd_extension'             => $this->translate('GD Extensions'),
            'title_header'             => $titleHeader,
            'title_requirements'       => $this->translate('Technical requirements'),
            'title_installments'       => $this->translate('Collections and installments'),
            'title_questions'          => $this->translate('Questions?'),
            'description_ssl'          => $this->translate('Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.'),
            'description_curl'         => $this->translate('It is an extension responsible for making payments via requests from the plugin to Mercado Pago.'),
            'description_gd_extension' => $this->translate('These extensions are responsible for the implementation and operation of Pix in your store.'),
            'description_installments' => $installmentsDescription,
            'description_questions'    => $questionsDescription,
            'button_installments'      => $this->translate('Set deadlines and fees'),
            'button_questions'         => $this->translate('Plugin manual'),
        ];
    }

    private function setCredentialsSettingsTranslations(): void
    {
        $subtitleCredentials = sprintf(
            '%s <b>%s</b>',
            $this->translate('To enable orders, you must create and activate production credentials in your Mercado Pago Account.'),
            $this->translate('Copy and paste the credentials below.')
        );

        self::$credentialsSettings = [
            'public_key'                => $this->translate('Public Key'),
            'access_token'              => $this->translate('Access Token'),
            'title_credentials'         => $this->translate('1. Integrate your store with Mercado Pago'),
            'title_credentials_prod'    => $this->translate('Production credentials'),
            'title_credentials_test'    => $this->translate('Test credentials'),
            'subtitle_credentials'      => $subtitleCredentials,
            'subtitle_credentials_test' => $this->translate('Enable Mercado Pago checkouts for test purchases in the store.'),
            'subtitle_credentials_prod' => $this->translate('Enable Mercado Pago checkouts to receive real payments in the store.'),
            'placeholder_public_key'    => $this->translate('Paste your Public Key here'),
            'placeholder_access_token'  => $this->translate('Paste your Access Token here'),
            'button_link_credentials'   => $this->translate('Check credentials'),
            'button_credentials'        => $this->translate('Save and continue'),
        ];
    }

    private function setStoreSettingsTranslations(): void
    {
        $helperUrl = sprintf(
            '%s %s <a class="mp-settings-blue-text" target="_blank" href="%s">%s</a>.',
            $this->translate('Add the URL to receive payments notifications.'),
            $this->translate('Find out more information in the'),
            '#',
            $this->translate('guides')
        );

        $helperIntegrator = sprintf(
            '%s %s <a class="mp-settings-blue-text" target="_blank" href="%s">%s</a>.',
            $this->translate('If you are a Mercado Pago Certified Partner, make sure to add your integrator_id.'),
            $this->translate('If you do not have the code, please'),
            '#',
            $this->translate('request it now')
        );

        self::$storeSettings = [
            'title_store'                  => $this->translate('2. Customize your business'),
            'title_info_store'             => $this->translate('Your store information'),
            'title_advanced_store'         => $this->translate('Advanced integration options (optional)'),
            'title_debug'                  => $this->translate('Debug and Log Mode'),
            'subtitle_store'               => $this->translate('Fill out the following information to have a better experience and offer more information to your clients.'),
            'subtitle_name_store'          => $this->translate('Name of your store in your client\'s invoice'),
            'subtitle_activities_store'    => $this->translate('Identification in Activities of Mercado Pago'),
            'subtitle_advanced_store'      => $this->translate('For further integration of your store with Mercado Pago (IPN, Certified Partners, Debug Mode)'),
            'subtitle_category_store'      => $this->translate('Store category'),
            'subtitle_url'                 => $this->translate('URL for IPN'),
            'subtitle_integrator'          => $this->translate('Integrator ID'),
            'subtitle_debug'               => $this->translate('We record your store\'s actions in order to provide a better assistance.'),
            'placeholder_name_store'       => $this->translate('Ex: Mary\'s Store'),
            'placeholder_activities_store' => $this->translate('Ex: Mary Store'),
            'placeholder_category_store'   => $this->translate('Select'),
            'placeholder_url'              => $this->translate('Ex: https://examples.com/my-custom-ipn-url'),
            'placeholder_integrator'       => $this->translate('Ex: 14987126498'),
            'accordion_advanced_store'     => $this->translate('Show advanced options'),
            'button_store'                 => $this->translate('Save and continue'),
            'helper_name_store'            => $this->translate('If this field is empty, the purchase will be identified as Mercado Pago.'),
            'helper_activities_store'      => $this->translate('In Activities, you will view this term before the order number'),
            'helper_category_store'        => $this->translate('Select "Other" if you do not find the appropriate category.'),
            'helper_integrator_link'       => $this->translate('request it now.'),
            'helper_url'                   => $helperUrl,
            'helper_integrator'            => $helperIntegrator,
        ];
    }

    private function setOrderSettingsTranslations(): void
    {
        self::$orderSettings = [
            'cancel_order'  => $this->translate('Cancel order'),
        ];
    }

    private function setGenericSettingsTranslations(): void
    {
        self::$genericSettings = [
            'by_mp'  => $this->translate('By Mercado Pago'),
        ];
    }
}

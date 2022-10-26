<?php

namespace MercadoPago\Woocommerce\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Translations
{
    /**
     * @var Translations
     */
    private static $instance;

    /**
     * @var string
     */
    public static $domain = 'woocommerce-mercadopago';

    /**
     * @var array
     */
    public static $headerSettings = [];

    /**
     * @var array
     */
    public static $credentialsSettings = [];

    public function __construct()
    {
        $this->setHeaderSettingsTranslations();
        $this->setCredentialsSettingsTranslations();
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
            'title_header'             => $this->translate($titleHeader),
            'title_requirements'       => $this->translate('Technical requirements'),
            'title_installments'       => $this->translate('Collections and installments'),
            'title_questions'          => $this->translate('Questions?'),
            'description_ssl'          => $this->translate('Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.'),
            'description_curl'         => $this->translate('It is an extension responsible for making payments via requests from the plugin to Mercado Pago.'),
            'description_gd_extension' => $this->translate('These extensions are responsible for the implementation and operation of Pix in your store.'),
            'description_installments' => $this->translate($installmentsDescription),
            'description_questions'    => $this->translate($questionsDescription),
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
            'subtitle_credentials'      => $this->translate($subtitleCredentials),
            'subtitle_credentials_test' => $this->translate('Enable Mercado Pago checkouts for test purchases in the store.'),
            'subtitle_credentials_prod' => $this->translate('Enable Mercado Pago checkouts to receive real payments in the store.'),
            'placeholder_public_key'    => $this->translate('Paste your Public Key here'),
            'placeholder_access_token'  => $this->translate('Paste your Access Token here'),
            'button_link_credentials'   => $this->translate('Check credentials'),
            'button_credentials'        => $this->translate('Save and continue'),
        ];
    }
}

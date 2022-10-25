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
    public static $credentialsSettings;

    public function __construct()
    {
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

    private function setCredentialsSettingsTranslations(): void
    {
        self::$credentialsSettings = [
            'public_key'               => $this->translate('Public key'),
            'access_token'             => $this->translate('Access Token'),
            'title_credentials'        => $this->translate('1. Integrate your store with Mercado Pago'),
            'title_credential_prod'    => $this->translate('Production credentials'),
            'title_credential_test'    => $this->translate('Test credentials'),
            'subtitle_credentials_one' => $this->translate('To enable orders, you must create and activate production credentials in your Mercado Pago Account.'),
            'subtitle_credentials_two' => $this->translate('Copy and paste the credentials below.'),
            'subtitle_credential_test' => $this->translate('Enable Mercado Pago checkouts for test purchases in the store.'),
            'subtitle_credential_prod' => $this->translate('Enable Mercado Pago checkouts to receive real payments in the store.'),
            'placeholder_public_key'   => $this->translate('Paste your Public Key here'),
            'placeholder_access_token' => $this->translate('Paste your Access Token here'),
            'button_link_credentials'  => $this->translate('Check credentials'),
            'button_credentials'       => $this->translate('Save and continue'),
        ];
    }
}

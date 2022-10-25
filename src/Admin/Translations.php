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

    private function setCredentialsSettingsTranslations()
    {
        self::$credentialsSettings = [
            'public_key'               => __('Public key', self::$domain),
            'access_token'             => __('Access Token', self::$domain),
            'title_credentials'        => __('1. Integrate your store with Mercado Pago', self::$domain),
            'title_credential_prod'    => __('Production credentials', self::$domain),
            'title_credential_test'    => __('Test credentials ', self::$domain),
            'subtitle_credentials_one' => __('To enable orders, you must create and activate production credentials in your Mercado Pago Account.', self::$domain),
            'subtitle_credentials_two' => __('Copy and paste the credentials below.', self::$domain),
            'subtitle_credential_test' => __('Enable Mercado Pago checkouts for test purchases in the store.', self::$domain),
            'subtitle_credential_prod' => __('Enable Mercado Pago checkouts to receive real payments in the store.', self::$domain),
            'placeholder_public_key'   => __('Paste your Public Key here', self::$domain),
            'placeholder_access_token' => __('Paste your Access Token here', self::$domain),
            'button_link_credentials'  => __('Check credentials', self::$domain),
            'button_credentials'       => __('Save and continue', self::$domain),
        ];
    }
}

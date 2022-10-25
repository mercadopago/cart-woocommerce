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
            'public_key'               => __('Public key', 'woocommerce-mercadopago'),
            'access_token'             => __('Access Token', 'woocommerce-mercadopago'),
            'title_credentials'        => __('1. Integrate your store with Mercado Pago  ', 'woocommerce-mercadopago'),
            'title_credential_prod'    => __('Production credentials', 'woocommerce-mercadopago'),
            'title_credential_test'    => __('Test credentials ', 'woocommerce-mercadopago'),
            'subtitle_credentials_one' => __('To enable orders, you must create and activate production credentials in your Mercado Pago Account. ', 'woocommerce-mercadopago'),
            'subtitle_credentials_two' => __('Copy and paste the credentials below.', 'woocommerce-mercadopago'),
            'subtitle_credential_test' => __('Enable Mercado Pago checkouts for test purchases in the store.', 'woocommerce-mercadopago'),
            'subtitle_credential_prod' => __('Enable Mercado Pago checkouts to receive real payments in the store.', 'woocommerce-mercadopago'),
            'placeholder_public_key'   => __('Paste your Public Key here', 'woocommerce-mercadopago'),
            'placeholder_access_token' => __('Paste your Access Token here', 'woocommerce-mercadopago'),
            'button_link_credentials'  => __('Check credentials', 'woocommerce-mercadopago'),
            'button_credentials'       => __('Save and continue', 'woocommerce-mercadopago'),
        ];
    }
}

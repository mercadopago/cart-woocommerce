<?php

namespace MercadoPago\Woocommerce\Translations;

use MercadoPago\Woocommerce\Helpers\Links;

if (!defined('ABSPATH')) {
    exit;
}

class StoreTranslations
{
    /**
     * @var Links
     */
    private $links;

    /**
     * @var array
     */
    public $checkoutBasic = [];

    /**
     * Translations constructor
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setCheckoutBasicTranslations();
    }

    /**
     * Set notices translations
     *
     * @return void
     */
    private function setCheckoutBasicTranslations(): void
    {
        $missWoocommerce = sprintf(
            __('The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago'),
            '<a target="_blank" href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
        );

        $this->checkoutBasic = [
            'gateway_title'           => __('Your saved cards or money in Mercado Pago', 'woocommerce-mercadopago'),
            'gateway_description'     => __('Debit, Credit and invoice in Mercado Pago environment', 'woocommerce-mercadopago'),
            'method_title'            => __('Mercado Pago - Checkout Pro', 'woocommerce-mercadopago'),
            'config_header_title'     => __('Checkout Pro', 'woocommerce-mercadopago'),
            'config_header_desc'      => __('With Checkout Pro you sell with all the safety inside Mercado Pago environment.', 'woocommerce-mercadopago'),
            'config_enabled_title'    => __('Enable the checkout', 'woocommerce-mercadopago'),
            'config_enabled_subtitle' => __('By disabling it, you will disable all payment methods of this checkout.', 'woocommerce-mercadopago'),
            'config_enabled_enabled'  => __('The checkout is <b>enabled</b>.', 'woocommerce-mercadopago'),
            'config_enabled_disabled' => __('The checkout is <b>disabled</b>.', 'woocommerce-mercadopago'),
        ];
    }
}

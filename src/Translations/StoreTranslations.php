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
     * @var array
     */
    public $checkoutCredits = [];

    /**
     * @var array
     */
    public $checkoutTicket = [];

    /**
     * Translations constructor
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setCheckoutBasicTranslations();
        $this->setCheckoutCreditsTranslations();
        $this->setCheckoutTicketTranslations();
    }

    /**
     * Set checkout basic translations
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

    /**
     * Set checkout credits translations
     *
     * @return void
     */
    private function setCheckoutCreditsTranslations(): void
    {
        $this->checkoutCredits = [
            'gateway_title'           => __('Installments without card', 'woocommerce-mercadopago'),
            'gateway_description'     => __('Customers who buy on spot and pay later in up to 12 installments', 'woocommerce-mercadopago'),
            'method_title'            => __('Mercado Pago - Installments without card', 'woocommerce-mercadopago'),
            'config_header_title'     => __('Installments without card', 'woocommerce-mercadopago'),
            'config_header_desc'      => __('Reach millions of buyers by offering Mercado Credito as a payment method. Our flexible payment options give your customers the possibility to buy today whatever they want in up to 12 installments without the need to use a credit card. For your business, the approval of the purchase is immediate and guaranteed.', 'woocommerce-mercadopago'),
            'config_enabled_title'    => __('Activate installments without card in your store checkout', 'woocommerce-mercadopago'),
            'config_enabled_subtitle' => __('Offer the option to pay in installments without card directly from your store\'s checkout.', 'woocommerce-mercadopago'),
            'config_enabled_enabled'  => __('Payment in installments without card in the store checkout is <b>active</b>', 'woocommerce-mercadopago'),
            'config_enabled_disabled' => __('Payment in installments without card in the store checkout is <b>inactive</b>', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set checkout ticket translations
     *
     * @return void
     */
    private function setCheckoutTicketTranslations(): void
    {
        $this->checkoutTicket = [
            'gateway_title'           => __('Invoice', 'woocommerce-mercadopago'),
            'gateway_description'     => __('Transparent Checkout in your store environment', 'woocommerce-mercadopago'),
            'method_title'            => __('Mercado pago - Customized Checkout', 'woocommerce-mercadopago'),
            'config_header_title'     => __('Transparent Checkout | Invoice or Loterica.', 'woocommerce-mercadopago'),
            'config_header_desc'      => __('With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago.', 'woocommerce-mercadopago'),
            'config_enabled_title'    => __('Activate installments without card in your store checkout', 'woocommerce-mercadopago'),
            'config_enabled_subtitle' => __('Offer the option to pay in installments without card directly from your store\'s checkout.', 'woocommerce-mercadopago'),
            'config_enabled_enabled'  => __('Payment in installments without card in the store checkout is <b>active</b>', 'woocommerce-mercadopago'),
            'config_enabled_disabled' => __('Payment in installments without card in the store checkout is <b>inactive</b>', 'woocommerce-mercadopago'),
        ];
    }
}

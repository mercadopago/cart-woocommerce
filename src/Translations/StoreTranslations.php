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
    public $commonCheckout = [];

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
    public $checkoutPix = [];

    /**
     * Translations constructor
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setCommonCheckoutTranslations();
        $this->setCheckoutBasicTranslations();
        $this->setCheckoutCreditsTranslations();
        $this->setCheckoutPixTranslations();
    }

    /**
     * Set common checkout translations
     *
     * @return void
     */
    private function setCommonCheckoutTranslations(): void
    {
        $this->commonCheckout = [
            'discount_title' => __('discount of', 'woocommerce-mercadopago'),
            'fee_title'      => __('fee of', 'woocommerce-mercadopago'),
        ];
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
     * Set checkout pix translations
     *
     * @return void
     */
    private function setCheckoutPixTranslations(): void
    {
        $this->checkoutPix = [
            'test_mode_title'                  => __('Pix in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'            => __('You can test the flow to generate a code, but you cannot finalize the payment.', 'woocommerce-mercadopago'),
            'pix_template_title'               => __('Pay instantly', 'woocommerce-mercadopago'),
            'pix_template_subtitle'            => __('By confirming your purchase, we will show you a code to make the payment.', 'woocommerce-mercadopago'),
            'pix_template_alt'                 => __('Pix logo', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
            'expiration_date_text'             => __('Code valid for ', 'woocommerce-mercadopago'),
            'title_purchase_pix'               => __('Now you just need to pay with Pix to finalize your purchase', 'woocommerce-mercadopago'),
            'title_how_to_pay'                 => __('How to pay with Pix:', 'woocommerce-mercadopago'),
            'step_one'                         => __('Go to your bank\'s app or website', 'woocommerce-mercadopago'),
            'step_two'                         => __('Search for the option to pay with Pix', 'woocommerce-mercadopago'),
            'step_three'                       => __('Scan the QR code or Pix code', 'woocommerce-mercadopago'),
            'step_four'                        => __('Done! You will see the payment confirmation', 'woocommerce-mercadopago'),
            'text_amount'                      => __('Value: ', 'woocommerce-mercadopago'),
            'text_scan_qr'                     => __('Scan the QR code:', 'woocommerce-mercadopago'),
            'text_time_qr_one'                 => __('Code valid for ', 'woocommerce-mercadopago'),
            'text_description_qr'              => __('If you prefer, you can pay by copying and pasting the following code', 'woocommerce-mercadopago'),
            'text_button'                      => __('Copy code', 'woocommerce-mercadopago'),
        ];
    }
}

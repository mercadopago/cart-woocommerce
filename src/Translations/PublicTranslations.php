<?php

namespace MercadoPago\Woocommerce\Translations;

if (!defined('ABSPATH')) {
    exit;
}

class PublicTranslations
{
    /**
     * @var array
     */
    public $pix = [];

    /**
     * PublicTranslations constructor
     */
    public function __construct()
    {
        $this->setPixPublicTranslations();
    }

    /**
     * Set pix checkout translations
     *
     * @return void
     */
    private function setPixPublicTranslations(): void
    {
        $this->pix = [
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

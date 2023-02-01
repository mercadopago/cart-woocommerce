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
    public $basicCheckout = [];

    /**
     * @var array
     */
    public $creditsCheckout = [];

    /**
     * @var array
     */
    public $customCheckout = [];

    /**
     * @var array
     */
    public $pixCheckout = [];

    /**
     * Translations constructor
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setCommonCheckoutTranslations();
        $this->setBasicCheckoutTranslations();
        $this->setCreditsCheckoutTranslations();
        $this->setCustomCheckoutTranslations();
        $this->setPixCheckoutTranslations();
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
     * Set basic checkout translations
     *
     * @return void
     */
    private function setBasicCheckoutTranslations(): void
    {
        $this->basicCheckout = [
            'test_mode_title'                                 => __('Checkout Pro in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                           => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                             => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'checkout_benefits_title'                         => __('Log in to Mercado Pago and earn benefits', 'woocommerce-mercadopago'),
            'checkout_benefits_title_phone'                   => __('Easy login', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_phone'                => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
            'checkout_benefits_alt_phone'                     => __('Blue phone image', 'woocommerce-mercadopago'),
            'checkout_benefits_title_wallet'                  => __('Quick payments', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_wallet'               => __('Use your saved cards, Pix or available balance.', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_wallet_2'             => __('Use your available Mercado Pago Wallet balance or saved cards.', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_wallet_3'             => __('Use your available money or saved cards.', 'woocommerce-mercadopago'),
            'checkout_benefits_alt_wallet'                    => __('Blue wallet image', 'woocommerce-mercadopago'),
            'checkout_benefits_title_protection'              => __('Protected purchases', 'woocommerce-mercadopago'),
            'checkout_benefits_title_protection_2'            => __('Reliable purchases', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_protection'           => __('Get your money back in case you don\'t receive your product.', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_protection_2'         => __('Get help if you have a problem with your purchase.', 'woocommerce-mercadopago'),
            'checkout_benefits_alt_protection'                => __('Blue protection image', 'woocommerce-mercadopago'),
            'checkout_benefits_title_phone_installments'      => __('Installments option', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_phone_installments'   => __('Pay with or without a credit card.', 'woocommerce-mercadopago'),
            'checkout_benefits_subtitle_phone_installments_2' => __('Interest-free installments with selected banks.', 'woocommerce-mercadopago'),
            'checkout_benefits_alt_phone_installments'        => __('Blue phone installments image', 'woocommerce-mercadopago'),
            'payment_methods_title'                           => __('Available payment methods', 'woocommerce-mercadopago'),
            'checkout_redirect_text'                          => __('By continuing, you will be taken to Mercado Pago to safely complete your purchase.', 'woocommerce-mercadopago'),
            'checkout_redirect_alt'                           => __('Checkout Pro redirect info image', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'                => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'                  => __('Terms and conditions', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits checkout translations
     *
     * @return void
     */
    private function setCreditsCheckoutTranslations(): void
    {
        $checkoutBenefits1 = sprintf(
            '<b>%s</b> %s.',
            __('Log in', 'woocommerce-mercadopago'),
            __('or create an account in Mercado Pago. If you use Mercado Libre, you already have one!', 'woocommerce-mercadopago')
        );

        $checkoutBenefits2 = sprintf(
            '%s <b>%s</b> %s.',
            __('Know your available limit in Mercado Crédito and', 'woocommerce-mercadopago'),
            __('choose how many installments', 'woocommerce-mercadopago'),
            __('you want to pay', 'woocommerce-mercadopago')
        );

        $checkoutBenefits3 = sprintf(
            '%s <b>%s</b>.',
            __('Pay the installments as you prefer:', 'woocommerce-mercadopago'),
            __('with money in your account, card of from the Mercado Pago app', 'woocommerce-mercadopago')
        );

        $this->creditsCheckout = [
            'test_mode_title'                                 => __('No card installments in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                           => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                             => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'checkout_benefits_title'                         => __('How to use it?', 'woocommerce-mercadopago'),
            'checkout_benefits_1'                             => $checkoutBenefits1,
            'checkout_benefits_2'                             => $checkoutBenefits2,
            'checkout_benefits_3'                             => $checkoutBenefits3,
            'checkout_redirect_text'                          => __('By continuing, you will be taken to Mercado Pago to safely complete your purchase.', 'woocommerce-mercadopago'),
            'checkout_redirect_alt'                           => __('Checkout Pro redirect info image', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'                => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'                  => __('Terms and conditions', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits checkout translations
     *
     * @return void
     */
    private function setCustomCheckoutTranslations(): void
    {
        $this->customCheckout = [
            'test_mode_title'                                 => __('No card installments in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                           => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                             => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'wallet_button_title'                             => __('Pay with saved cards', 'woocommerce-mercadopago'),
            'wallet_button_description'                       => __('Do you have a Mercado Libre account? Then use the same email and password to pay faster with Mercado Pago.', 'woocommerce-mercadopago'),
            'wallet_button_button_text'                       => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'available_payments_title'                        => __('With which card can you pay?', 'woocommerce-mercadopago'),
            'available_payments_image'                        => __('See current promotions', 'woocommerce-mercadopago'),
            'available_payments_credit_card_title'            => __('Credit cards', 'woocommerce-mercadopago'),
            'available_payments_credit_card_label'            => __('Up to 12 installments', 'woocommerce-mercadopago'),
            'available_payments_debit_card_title'             => __('Debit cards', 'woocommerce-mercadopago'),
            'payment_methods_promotion_text'                  => __('See current promotions', 'woocommerce-mercadopago'),
            'card_form_title'                                 => __('Fill in your card details', 'woocommerce-mercadopago'),
            'card_number_input_label'                         => __('Card number', 'woocommerce-mercadopago'),
            'card_number_input_helper'                        => __('Required data', 'woocommerce-mercadopago'),
            'card_holder_name_input_label'                    => __('Holder name as it appears on the card', 'woocommerce-mercadopago'),
            'card_holder_name_input_helper'                   => __('Required data', 'woocommerce-mercadopago'),
            'card_expiration_input_label'                     => __('Expiration', 'woocommerce-mercadopago'),
            'card_expiration_input_helper'                    => __('Required data', 'woocommerce-mercadopago'),
            'card_security_code_input_label'                  => __('Security Code', 'woocommerce-mercadopago'),
            'card_security_code_input_helper'                 => __('Required data', 'woocommerce-mercadopago'),
            'card_document_input_label'                       => __('Holder document', 'woocommerce-mercadopago'),
            'card_document_input_helper'                      => __('Invalid document', 'woocommerce-mercadopago'),
            'card_installments_title'                         => __('Select the number of installments', 'woocommerce-mercadopago'),
            'card_issuer_input_label'                         => __('Issuer', 'woocommerce-mercadopago'),
            'card_installments_input_helper'                  => __('Select the number of installments', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'                => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'                  => __('Terms and conditions', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set pix checkout translations
     *
     * @return void
     */
    private function setPixCheckoutTranslations(): void
    {
        $this->pixCheckout = [
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

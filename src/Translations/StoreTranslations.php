<?php

namespace MercadoPago\Woocommerce\Translations;

use MercadoPago\Woocommerce\Helpers\Links;

if (!defined('ABSPATH')) {
    exit;
}

class StoreTranslations
{
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
     * @var array
     */
    public $ticketCheckout = [];

    /**
     * @var array
     */
    public $orderStatus = [];

    /**
     * @var array
     */
    public $commonMessages = [];

    /**
     * @var array
     */
    public $threeDsTranslations;

    /**
     * @var array
     */
    public $links;

    /**
     * Translations constructor
     *
     * @param Links $links
     */
    public function __construct(Links $links)
    {
        $this->links = $links->getLinks();

        $this->setCommonCheckoutTranslations();
        $this->setBasicCheckoutTranslations();
        $this->setCreditsCheckoutTranslations();
        $this->setCustomCheckoutTranslations();
        $this->setTicketCheckoutTranslations();
        $this->setPixCheckoutTranslations();
        $this->setOrderStatusTranslations();
        $this->setCommonMessagesTranslations();
        $this->set3dsTranslations();
    }

    /**
     * Set common checkout translations
     *
     * @return void
     */
    private function setCommonCheckoutTranslations(): void
    {
        $this->commonCheckout = [
            'discount_title'     => __('discount of', 'woocommerce-mercadopago'),
            'fee_title'          => __('fee of', 'woocommerce-mercadopago'),
            'text_concatenation' => __('and', 'woocommerce-mercadopago'),
            'shipping_title'     => __('Shipping service used by the store.', 'woocommerce-mercadopago'),
            'store_discount'     => __('Discount provided by store', 'woocommerce-mercadopago'),
            'cart_discount'      => __('Mercado Pago Discount', 'woocommerce-mercadopago'),
            'cart_commission'    => __('Mercado Pago Commission', 'woocommerce-mercadopago'),
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
            'pay_with_mp_title'                               => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'cancel_url_text'                                 => __('Cancel &amp; Clear Cart', 'woocommerce-mercadopago'),
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
            'test_mode_title'                  => __('No card installments in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'            => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'              => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'checkout_benefits_title'          => __('How to use it?', 'woocommerce-mercadopago'),
            'checkout_benefits_1'              => $checkoutBenefits1,
            'checkout_benefits_2'              => $checkoutBenefits2,
            'checkout_benefits_3'              => $checkoutBenefits3,
            'checkout_redirect_text'           => __('By continuing, you will be taken to Mercado Pago to safely complete your purchase.', 'woocommerce-mercadopago'),
            'checkout_redirect_alt'            => __('Checkout Pro redirect info image', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
            'banner_title'                     => __('Pay in', 'woocommerce-mercadopago'),
            'banner_title_bold'                => __('installments', 'woocommerce-mercadopago'),
            'banner_title_end'                 => __('with Mercado Pago', 'woocommerce-mercadopago'),
            'banner_link'                      => __('Read more', 'woocommerce-mercadopago'),
            'modal_title'                      => __('Buy now and pay in installments with no card later!', 'woocommerce-mercadopago'),
            'modal_subtitle'                   => __('100% online, without paperwork or monthly fees', 'woocommerce-mercadopago'),
            'modal_how_to'                     => __('How does it work?', 'woocommerce-mercadopago'),
            'modal_step_1'                     => __('When paying, choose', 'woocommerce-mercadopago'),
            'modal_step_1_bold'                => __('Mercado Pago', 'woocommerce-mercadopago'),
            'modal_step_1_end'                 => __('. Login to your account or create one in a few steps.', 'woocommerce-mercadopago'),
            'modal_step_2'                     => __('Search for', 'woocommerce-mercadopago'),
            'modal_step_2_bold'                => __('Mercado Credito', 'woocommerce-mercadopago'),
            'modal_step_2_end'                 => __('among the options, select it and choose in how many installments you would like to pay.', 'woocommerce-mercadopago'),
            'modal_step_3'                     => __('Pay your installments monthly as you wish, in the Mercado Pago app.', 'woocommerce-mercadopago'),
            'modal_footer'                     => __('Questions? ', 'woocommerce-mercadopago'),
            'modal_footer_link'                => __('Check our FAQ', 'woocommerce-mercadopago'),
            'modal_footer_end'                 => __('. Credit subject to approval.', 'woocommerce-mercadopago')
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
            'test_mode_title'                                     => __('Checkout Custom in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                               => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                                 => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'wallet_button_title'                                 => __('Pay with saved cards', 'woocommerce-mercadopago'),
            'wallet_button_description'                           => __('Do you have a Mercado Libre account? Then use the same email and password to pay faster with Mercado Pago.', 'woocommerce-mercadopago'),
            'wallet_button_button_text'                           => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'available_payments_title'                            => __('With which card can you pay?', 'woocommerce-mercadopago'),
            'available_payments_image'                            => __('See current promotions', 'woocommerce-mercadopago'),
            'available_payments_credit_card_title'                => __('Credit cards', 'woocommerce-mercadopago'),
            'available_payments_credit_card_label'                => __('Up to 12 installments', 'woocommerce-mercadopago'),
            'available_payments_debit_card_title'                 => __('Debit cards', 'woocommerce-mercadopago'),
            'payment_methods_promotion_text'                      => __('See current promotions', 'woocommerce-mercadopago'),
            'card_form_title'                                     => __('Fill in your card details', 'woocommerce-mercadopago'),
            'card_number_input_label'                             => __('Card number', 'woocommerce-mercadopago'),
            'card_number_input_helper'                            => __('Required data', 'woocommerce-mercadopago'),
            'card_holder_name_input_label'                        => __('Holder name as it appears on the card', 'woocommerce-mercadopago'),
            'card_holder_name_input_helper'                       => __('Required data', 'woocommerce-mercadopago'),
            'card_expiration_input_label'                         => __('Expiration', 'woocommerce-mercadopago'),
            'card_expiration_input_helper'                        => __('Required data', 'woocommerce-mercadopago'),
            'card_security_code_input_label'                      => __('Security Code', 'woocommerce-mercadopago'),
            'card_security_code_input_helper'                     => __('Required data', 'woocommerce-mercadopago'),
            'card_document_input_label'                           => __('Holder document', 'woocommerce-mercadopago'),
            'card_document_input_helper'                          => __('Invalid document', 'woocommerce-mercadopago'),
            'card_installments_title'                             => __('Select the number of installments', 'woocommerce-mercadopago'),
            'card_issuer_input_label'                             => __('Issuer', 'woocommerce-mercadopago'),
            'card_installments_input_helper'                      => __('Select the number of installments', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'                    => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'                      => __('Terms and conditions', 'woocommerce-mercadopago'),
            'placeholders_card_expiration_date'                   => __('mm/yy', 'woocommerce-mercadopago'),
            'placeholders_issuer'                                 => __('Issuer', 'woocommerce-mercadopago'),
            'placeholders_installments'                           => __('Installments', 'woocommerce-mercadopago'),
            'cvv_hint_back'                                       => __('on the back', 'woocommerce-mercadopago'),
            'cvv_hint_front'                                      => __('on the front', 'woocommerce-mercadopago'),
            'cvv_text'                                            => __('digits', 'woocommerce-mercadopago'),
            'installment_obs_fee'                                 => __('No fee', 'woocommerce-mercadopago'),
            'installment_button'                                  => __('More options', 'woocommerce-mercadopago'),
            'bank_interest_text'                                  => __('If interest is applicable, it will be charged by your bank.', 'woocommerce-mercadopago'),
            'interest_text'                                       => __('Interest', 'woocommerce-mercadopago'),
            'input_helper_message_invalid_type'                   => __('Card number is required', 'woocommerce-mercadopago'),
            'input_helper_message_invalid_length'                 => __('Card number invalid', 'woocommerce-mercadopago'),
            'input_helper_message_card_holder_name_221'           => __('Holder name is required', 'woocommerce-mercadopago'),
            'input_helper_message_card_holder_name_316'           => __('Holder name invalid', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_type'   => __('Expiration date invalid', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_length' => __('Expiration date incomplete', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_value'  => __('Expiration date invalid', 'woocommerce-mercadopago'),
            'input_helper_message_security_code_invalid_type'     => __('Security code is required', 'woocommerce-mercadopago'),
            'input_helper_message_security_code_invalid_length'   => __('Security code incomplete', 'woocommerce-mercadopago'),
            'title_installment_cost'                              => __('Cost of installments', 'woocommerce-mercadopago'),
            'title_installment_total'                             => __('Total with installments', 'woocommerce-mercadopago'),
            'text_installments'                                   => __('installments of', 'woocommerce-mercadopago'),
            'wallet_button_order_receipt_title'                   => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'cancel_url_text'                                     => __('Cancel &amp; Clear Cart', 'woocommerce-mercadopago'),
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
            'customer_not_paid'                => __('Mercado Pago: The customer has not paid yet.', 'woocommerce-mercadopago'),
            'congrats_title'                   => __('Mercado Pago: Now you just need to pay with Pix to finalize your purchase.', 'woocommerce-mercadopago'),
            'congrats_subtitle'                => __('Scan the QR code below or copy and paste the code into your bank\'s application.', 'woocommerce-mercadopago'),
            'expiration_30_minutes'            => __('30 minutes', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set pix checkout translations
     *
     * @return void
     */
    private function setOrderStatusTranslations(): void
    {
        $this->orderStatus = [
            'payment_approved' => __('Payment approved.', 'woocommerce-mercadopago'),
            'pending_pix'      => __('Waiting for the Pix payment.', 'woocommerce-mercadopago'),
            'pending_ticket'   => __('Waiting for the ticket payment.', 'woocommerce-mercadopago'),
            'pending'          => __('The customer has not made the payment yet.', 'woocommerce-mercadopago'),
            'in_process'       => __('Payment is pending review.', 'woocommerce-mercadopago'),
            'rejected'         => __('Payment was declined. The customer can try again.', 'woocommerce-mercadopago'),
            'refunded'         => __('Payment was returned to the customer.', 'woocommerce-mercadopago'),
            'cancelled'        => __('Payment was canceled.', 'woocommerce-mercadopago'),
            'in_mediation'     => __('The payment is in mediation or the purchase was unknown by the customer.', 'woocommerce-mercadopago'),
            'charged_back'     => __('The payment is in mediation or the purchase was unknown by the customer.', 'woocommerce-mercadopago'),
            'validate_order_1' => __('The payment', 'woocommerce-mercadopago'),
            'validate_order_2' => __('was notified by Mercado Pago with status', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set checkout ticket translations
     *
     * @return void
     */
    private function setTicketCheckoutTranslations(): void
    {
        $this->ticketCheckout = [
            'test_mode_title'                  => __('Offline Methods in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'            => __('You can test the flow to generate an invoice, but you cannot finalize the payment.', 'woocommerce-mercadopago'),
            'test_mode_link_text'              => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'input_document_label'             => __('Holder document', 'woocommerce-mercadopago'),
            'input_document_helper'            => __('Invalid document', 'woocommerce-mercadopago'),
            'ticket_text_label'                => __('Select where you want to pay', 'woocommerce-mercadopago'),
            'input_table_button'               => __('more options', 'woocommerce-mercadopago'),
            'input_helper_label'               => __('Select a payment method', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree with our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
            'print_ticket_label'               => __('Great, we processed your purchase order. Complete the payment with ticket so that we finish approving it.', 'woocommerce-mercadopago'),
            'print_ticket_link'                => __('Print ticket', 'woocommerce-mercadopago'),
            'paycash_concatenator'             => __(' and ', 'woocommerce-mercadopago'),
            'congrats_title'                   => __('To print the ticket again click', 'woocommerce-mercadopago'),
            'congrats_subtitle'                => __('here', 'woocommerce-mercadopago'),
            'customer_not_paid'                => __('Mercado Pago: The customer has not paid yet.', 'woocommerce-mercadopago'),
        ];
    }


    /**
     * Set common messages translations
     *
     * @return void
     */
    private function setCommonMessagesTranslations(): void
    {
        $this->commonMessages = [
            'cho_default_error'                        => __('A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago'),
            'cho_form_error'                           => __('A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago'),
            'cho_see_order_form'                       => __('See your order form', 'woocommerce-mercadopago'),
            'cho_payment_declined'                     => __('Your payment was declined. You can try again.', 'woocommerce-mercadopago'),
            'cho_button_try_again'                     => __('Click to try again', 'woocommerce-mercadopago'),
            'cho_accredited'                           => __('That\'s it, payment accepted!', 'woocommerce-mercadopago'),
            'cho_pending_contingency'                  => __('We are processing your payment. In less than an hour we will send you the result by email.', 'woocommerce-mercadopago'),
            'cho_pending_review_manual'                => __('We are processing your payment. In less than 2 days we will send you by email if the payment has been approved or if additional information is needed.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_bad_filled_card_number'   => __('Check the card number.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_bad_filled_date'          => __('Check the expiration date.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_bad_filled_other'         => __('Check the information provided.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_bad_filled_security_code' => __('Check the informed security code.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_card_error'               => __('Your payment cannot be processed.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_blacklist'                => __('Your payment cannot be processed.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_call_for_authorize'       => __('You must authorize payments for your orders.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_card_disabled'            => __('Contact your card issuer to activate it. The phone is on the back of your card.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_duplicated_payment'       => __('You have already made a payment of this amount. If you have to pay again, use another card or other method of payment.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_high_risk'                => __('Your payment was declined. Please select another payment method. It is recommended in cash.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_insufficient_amount'      => __('Your payment does not have sufficient funds.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_invalid_installments'     => __('Payment cannot process the selected fee.', 'woocommerce-mercadopago'),
            'cho_cc_rejected_max_attempts'             => __('You have reached the limit of allowed attempts. Choose another card or other payment method.', 'woocommerce-mercadopago'),
            'cho_default'                              => __('This payment method cannot process your payment.', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits checkout translations
     *
     * @return void
     */
    private function set3dsTranslations(): void
    {
        $this->threeDsTranslations = [
            'title_loading_3ds_frame'    => __('We are taking you to validate the card', 'woocommerce-mercadopago'),
            'title_loading_3ds_frame2'   => __('with your bank', 'woocommerce-mercadopago'),
            'text_loading_3ds_frame'     => __('We need to confirm that you are the cardholder.', 'woocommerce-mercadopago'),
            'title_loading_3ds_response' => __('We are receiving the response from your bank', 'woocommerce-mercadopago'),
            'title_3ds_frame'            => __('Complete the bank validation so your payment can be approved', 'woocommerce-mercadopago'),
            'tooltip_3ds_frame'          => __('Please keep this page open. If you close it, you will not be able to resume the validation.', 'woocommerce-mercadopago'),
            'message_3ds_declined'       => __('<b>For safety reasons, your payment was declined</b><br>We recommend paying with your usual payment method and device for online purchases.', 'woocommerce-mercadopago'),
        ];
    }
}

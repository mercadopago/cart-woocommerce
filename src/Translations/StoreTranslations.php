<?php

namespace MercadoPago\Woocommerce\Translations;

use MercadoPago\Woocommerce\Helpers\Links;

if (!defined('ABSPATH')) {
    exit;
}

class StoreTranslations
{
    public array $commonCheckout = [];

    public array $basicCheckout = [];

    public array $creditsCheckout = [];

    public array $customCheckout = [];

    public array $pixCheckout = [];

    public array $ticketCheckout = [];

    public array $pseCheckout = [];

    public array $yapeCheckout = [];

    public array $orderStatus = [];

    public array $commonMessages = [];

    public array $buyerRefusedMessages = [];

    public array $threeDsTranslations;

    public array $links;

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
        $this->setPseCheckoutTranslations();
        $this->setYapeCheckoutTranslations();
        $this->setOrderStatusTranslations();
        $this->setCommonMessagesTranslations();
        $this->setbuyerRefusedMessagesTranslations();
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
            'discount_title'                   => __('discount of', 'woocommerce-mercadopago'),
            'fee_title'                        => __('fee of', 'woocommerce-mercadopago'),
            'text_concatenation'               => __('and', 'woocommerce-mercadopago'),
            'shipping_title'                   => __('Shipping service used by the store.', 'woocommerce-mercadopago'),
            'store_discount'                   => __('Discount provided by store', 'woocommerce-mercadopago'),
            'cart_discount'                    => __('Mercado Pago Discount', 'woocommerce-mercadopago'),
            'cart_commission'                  => __('Mercado Pago Commission', 'woocommerce-mercadopago'),
            'currency_conversion_error'        => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
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
            'test_mode_title'            => __('Checkout Pro in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'      => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'        => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'pay_with_mp_title'          => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'cancel_url_text'            => __('Cancel order', 'woocommerce-mercadopago'),
            'benefits_title'             => __('Discover how practical Mercado&nbspPago is', 'woocommerce-mercadopago'),
            'first_benefit_description'  => __('<b>Pay with your saved cards</b> or account money without filling out personal details.', 'woocommerce-mercadopago'),
            'second_benefit_description' => __('<b>Buy safely</b> with your preferred payment method.', 'woocommerce-mercadopago'),
            'redirect_title'             => __('We\'ll take you to Mercado&nbspPago', 'woocommerce-mercadopago'),
            'redirect_description'       => __('If you don\'t have an account, you can use your email.', 'woocommerce-mercadopago'),
            'account_money'              => __('Account money', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits checkout translations
     *
     * @return void
     */
    private function setCreditsCheckoutTranslations(): void
    {
        $this->creditsCheckout = [
            'test_mode_title'                           => __('No card installments in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                     => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                       => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'checkout_benefits_title'                   => __('Buy in up to 12 installments without credit cards', 'woocommerce-mercadopago'),
            'checkout_redirect_title'                   => __('We will take you to Mercado Pago', 'woocommerce-mercadopago'),
            'checkout_redirect_description'             => __('If you don\'t have a credits line yet, active it when paying.', 'woocommerce-mercadopago'),
            'checkout_redirect_alt'                     => __('Checkout Pro redirect info image', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'          => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'            => __('Terms and conditions', 'woocommerce-mercadopago'),
            'tooltip_link'                              => __('Learn more', 'woocommerce-mercadopago'),
            'modal_title'                               => __('Buy through Mercado Pago without cards and pay month by month', 'woocommerce-mercadopago'),
            'modal_step_1'                              => __('Add your product to the cart and, for the payment, select “Meses sin Tarjeta” or “Cuotas sin Tarjeta”.', 'woocommerce-mercadopago'),
            'modal_step_2'                              => __('Log in to Mercado Pago.', 'woocommerce-mercadopago'),
            'modal_step_3'                              => __('Choose the amount of installments that best suit you and you’re all set!', 'woocommerce-mercadopago'),
            'modal_footer'                              => __('Any Questions? Check our ', 'woocommerce-mercadopago'),
            'modal_footer_link'                         => __('Help', 'woocommerce-mercadopago'),
            'modal_footer_init'                         => __('Credit subject to approval.', 'woocommerce-mercadopago'),
            'message_error_amount'                      => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),
        ];
        $this->creditsCheckout = array_merge($this->creditsCheckout, $this->setCreditsStepsTranslations());
        $this->creditsCheckout = array_merge($this->creditsCheckout, $this->setCreditsTooltipTranslations());
    }

    /**
     * Set credits steps translations
     *
     * @return array
     */
    private function setCreditsStepsTranslations(): array
    {
        $checkoutStepOne = sprintf(
            '<b>%s</b> %s <b>%s</b>.',
            __('Find out the available limit', 'woocommerce-mercadopago'),
            __('of your Línea de Crédito and', 'woocommerce-mercadopago'),
            __('choose the number of installments', 'woocommerce-mercadopago')
        );

        $checkoutStepTwo = sprintf(
            '<b>%s</b> %s.',
            __('Confirm your payment,', 'woocommerce-mercadopago'),
            __('which is credited right away and is 100% protected', 'woocommerce-mercadopago')
        );

        $checkoutStepThree = sprintf(
            '<b>%s</b> %s.',
            __('Pay month by month', 'woocommerce-mercadopago'),
            __('from the Mercado Pago app with your preferred payment method', 'woocommerce-mercadopago')
        );

        return [
            'checkout_step_one'   => __($checkoutStepOne, 'woocommerce-mercadopago'),
            'checkout_step_two'   => __($checkoutStepTwo, 'woocommerce-mercadopago'),
            'checkout_step_three' => __($checkoutStepThree, 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set credits tooltip translations
     *
     * @return array
     */
    private function setCreditsTooltipTranslations(): array
    {
        $tooltipComponentOption1 = sprintf(
            '<b>%s</b> %s.',
            __('Up to 12 installments without cards', 'woocommerce-mercadopago'),
            __('through Mercado Pago', 'woocommerce-mercadopago')
        );

        $tooltipComponentOption2 = sprintf(
            '<b>%s</b> %s.',
            __('Buy now, pay later', 'woocommerce-mercadopago'),
            __('through Mercado Pago', 'woocommerce-mercadopago')
        );

        $tooltipComponentOption3 = sprintf(
            '%s <b>%s</b>.',
            __('With Mercado Pago,', 'woocommerce-mercadopago'),
            __('get it now and pay month by month', 'woocommerce-mercadopago')
        );

        $tooltipComponentOption4 = sprintf(
            '<b>%s</b> %s.',
            __('Pay in up to 12 installments', 'woocommerce-mercadopago'),
            __('without credit card', 'woocommerce-mercadopago')
        );

        return [
            'tooltip_component_option1'                 => $tooltipComponentOption1,
            'tooltip_component_option2'                 => $tooltipComponentOption2,
            'tooltip_component_option3'                 => $tooltipComponentOption3,
            'tooltip_component_option4'                 => $tooltipComponentOption4,
        ];
    }

    /**
     * Set custom checkout translations
     *
     * @return void
     */
    private function setCustomCheckoutTranslations(): void
    {
        $this->customCheckout = [
            'test_mode_title'                                     => __('Checkout Custom in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                               => __('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'),
            'test_mode_link_text'                                 => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'wallet_button_title'                                 => __('Pay with your saved cards', 'woocommerce-mercadopago'),
            'wallet_button_description'                           => __('Access Mercado Pago and pay faster without filling out forms.', 'woocommerce-mercadopago'),
            'card_number_input_label'                             => __('Card number', 'woocommerce-mercadopago'),
            'card_number_input_helper'                            => __('Required data', 'woocommerce-mercadopago'),
            'card_holder_name_input_label'                        => __('Holder name as it appears on the card', 'woocommerce-mercadopago'),
            'card_holder_name_input_helper'                       => __('Required data', 'woocommerce-mercadopago'),
            'card_expiration_input_label'                         => __('Expiration', 'woocommerce-mercadopago'),
            'card_expiration_input_helper'                        => __('Required data', 'woocommerce-mercadopago'),
            'card_security_code_input_label'                      => __('Security Code', 'woocommerce-mercadopago'),
            'card_security_code_input_helper'                     => __('Required data', 'woocommerce-mercadopago'),
            'card_document_input_label'                           => __('Holder ID', 'woocommerce-mercadopago'),
            'card_document_input_helper_empty'                    => __('Please complete this field.', 'woocommerce-mercadopago'),
            'card_document_input_helper_invalid'                  => __('Please enter the full ID number.', 'woocommerce-mercadopago'),
            'card_document_input_helper_wrong'                    => __('Please enter a valid ID.', 'woocommerce-mercadopago'),
            'card_installments_label'                             => __('Installments', 'woocommerce-mercadopago'),
            'card_issuer_input_label'                             => __('Issuer', 'woocommerce-mercadopago'),
            'card_installments_interest_text'                     => __('If there is any interest, it will be applied and charged by your bank.', 'woocommerce-mercadopago'),
            'placeholders_installments'                           => __('Select an option', 'woocommerce-mercadopago'),
            'placeholders_card_expiration_date'                   => __('mm/yy', 'woocommerce-mercadopago'),
            'placeholders_issuer'                                 => __('Issuer', 'woocommerce-mercadopago'),
            'cvv_hint_back'                                       => __('on the back', 'woocommerce-mercadopago'),
            'cvv_hint_front'                                      => __('on the front', 'woocommerce-mercadopago'),
            'cvv_text'                                            => __('digits', 'woocommerce-mercadopago'),
            'input_helper_message_invalid_type'                   => __('Card number is required', 'woocommerce-mercadopago'),
            'input_helper_message_invalid_length'                 => __('Card number invalid', 'woocommerce-mercadopago'),
            'input_helper_message_card_holder_name_221'           => __('Holder name is required', 'woocommerce-mercadopago'),
            'input_helper_message_card_holder_name_316'           => __('Holder name invalid', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_type'   => __('Expiration date invalid', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_length' => __('Expiration date incomplete', 'woocommerce-mercadopago'),
            'input_helper_message_expiration_date_invalid_value'  => __('Expiration date invalid', 'woocommerce-mercadopago'),
            'input_helper_message_security_code_invalid_type'     => __('Fill out this field.', 'woocommerce-mercadopago'),
            'input_helper_message_security_code_invalid_length'   => __('Type in the complete code.', 'woocommerce-mercadopago'),
            'title_installment_cost'                              => __('Cost of installments', 'woocommerce-mercadopago'),
            'title_installment_total'                             => __('Total with installments', 'woocommerce-mercadopago'),
            'text_installments'                                   => __('installments of', 'woocommerce-mercadopago'),
            'wallet_button_order_receipt_title'                   => __('Pay with Mercado Pago', 'woocommerce-mercadopago'),
            'cancel_url_text'                                     => __('Cancel order', 'woocommerce-mercadopago'),
            'message_error_amount'                                => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),
            'installments_error_invalid_amount'                   => __('This amount does not allow payments by credit card, we recommend paying with another method or changing the contents of your cart.', 'woocommerce-mercadopago'),
            'default_error_message'                               => __('Something went wrong, we recommend trying again or paying with another method.', 'woocommerce-mercadopago'),
            'payment_methods_list_text'                           => __('Saved payment methods', 'woocommerce-mercadopago'),
            'last_digits_text'                                    => __('ending in', 'woocommerce-mercadopago'),
            'new_card_text'                                       => __('New card', 'woocommerce-mercadopago'),
            'account_money_text'                                  => __('Account Money', 'woocommerce-mercadopago'),
            'account_money_invested_text'                         => __('Account Money Invested', 'woocommerce-mercadopago'),
            'interest_free_part_one_text'                         => __('Up to', 'woocommerce-mercadopago'),
            'interest_free_part_two_text'                         => __('interest-free installments', 'woocommerce-mercadopago'),
            'installments_text'                                   => __('installments', 'woocommerce-mercadopago'),
            'installments_required'                               => __('Select an option.', 'woocommerce-mercadopago'),
            'security_code_input_title_text'                      => __('Security code', 'woocommerce-mercadopago'),
            'security_code_placeholder_text_3_digits'             => __('E.g.: 123', 'woocommerce-mercadopago'),
            'security_code_placeholder_text_4_digits'             => __('E.g.: 1234', 'woocommerce-mercadopago'),
            'security_code_tooltip_text_3_digits'                 => __('It’s a 3 digit number. You can find it on the back of your card or on the app of your bank or digital wallet.', 'woocommerce-mercadopago'),
            'security_code_tooltip_text_4_digits'                 => __('It’s a 4 digit number. You can find it on the front of your card or on the app of your bank or digital wallet.', 'woocommerce-mercadopago'),
            'security_code_error_message_text'                    => __('Security code is required', 'woocommerce-mercadopago'),
            'placeholders_cardholder_name'                        => __('E.g.: María López', 'woocommerce-mercadopago'),
            'mercado_pago_card_name'                              => __('Mercado Pago Prepaid Card', 'woocommerce-mercadopago'),
            'locale'                                              => __('en-US', 'woocommerce-mercadopago'),
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
            'terms_and_conditions_description' => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
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
            'message_error_amount'             => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),
            'approved_template_title'          => __('Payment Approved', 'woocommerce-mercadopago'),
            'approved_template_description'    => __('Your payment with PIX has been successfully approved.', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set pix checkout translations
     *
     * @return void
     */
    private function setYapeCheckoutTranslations(): void
    {
        $this->yapeCheckout = [
            'test_mode_title'                  => __('Yape in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_link_text'              => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'test_mode_description'            => __('You can test the flow to generate a code, but you cannot finalize the payment.', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
            'yape_input_field_label'           => __('Cell phone linked to Yape', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
            'checkout_notice_message'          => __('Verify in Yape that the option "Compra por internet" is activated and that the daily limit is enough.', 'woocommerce-mercadopago'),
            'yape_title'                       => __('Pay with Yape in a few minutes', 'woocommerce-mercadopago'),
            'yape_subtitle'                    => __('Fill out the following details and confirm your purchase.', 'woocommerce-mercadopago'),
            'input_code_label'                 => __('Approval code', 'woocommerce-mercadopago'),
            'footer_text'                      => __('Processed by Mercado Pago', 'woocommerce-mercadopago'),
            'yape_tooltip_text'                => __('The code is available in the Yape app menu.', 'woocommerce-mercadopago'),
            'yape_input_code_error_message1'   => __('Enter the entire number.', 'woocommerce-mercadopago'),
            'yape_input_code_error_message2'   => __('Fill out this field.', 'woocommerce-mercadopago'),
            'yape_phone_number_error_message1' => __('Enter the entire number.', 'woocommerce-mercadopago'),
            'yape_phone_number_error_message2' => __('Fill out this field.', 'woocommerce-mercadopago'),
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
            'partial_refunded' => __('The payment was partially returned to the customer. the amount refunded was : ', 'woocommerce-mercadopago'),
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
            'test_mode_title'                         => __('Offline Methods in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'                   => __('You can test the flow to generate an invoice, but you cannot finalize the payment.', 'woocommerce-mercadopago'),
            'test_mode_link_text'                     => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'input_document_label'                    => __('Holder ID', 'woocommerce-mercadopago'),
            'input_document_helper_empty'             => __('Please complete this field.', 'woocommerce-mercadopago'),
            'input_document_helper_invalid'           => __('Please enter the full ID number.', 'woocommerce-mercadopago'),
            'input_document_helper_wrong'             => __('Please enter a valid ID.', 'woocommerce-mercadopago'),
            'ticket_text_label'                       => __('Select your payment method', 'woocommerce-mercadopago'),
            'input_table_button'                      => __('more options', 'woocommerce-mercadopago'),
            'input_helper_label'                      => __('Select a payment method.', 'woocommerce-mercadopago'),
            'terms_and_conditions_description'        => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'          => __('Terms and conditions', 'woocommerce-mercadopago'),
            'print_ticket_label'                      => __('Great, we processed your purchase order. Complete the payment with ticket so that we finish approving it.', 'woocommerce-mercadopago'),
            'print_ticket_link'                       => __('Print ticket', 'woocommerce-mercadopago'),
            'paycash_concatenator'                    => __(' and ', 'woocommerce-mercadopago'),
            'congrats_title'                          => __('To print the ticket again click', 'woocommerce-mercadopago'),
            'congrats_subtitle'                       => __('here', 'woocommerce-mercadopago'),
            'customer_not_paid'                       => __('Mercado Pago: The customer has not paid yet.', 'woocommerce-mercadopago'),
            'message_error_amount'                    => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),
            'billing_data_title'                      => __('Enter payment details', 'woocommerce-mercadopago'),
            'billing_data_checkbox_label'             => __('Use delivery details.', 'woocommerce-mercadopago'),
            'billing_data_postalcode_label'           => __('ZIP code', 'woocommerce-mercadopago'),
            'billing_data_postalcode_placeholder'     => __('E.g.: 01310-200', 'woocommerce-mercadopago'),
            'billing_data_postalcode_error_empty'     => __('Please complete this field.', 'woocommerce-mercadopago'),
            'billing_data_postalcode_error_partial'   => __('Please enter the full ZIP code.', 'woocommerce-mercadopago'),
            'billing_data_postalcode_error_invalid'   => __('Please enter a valid ZIP code.', 'woocommerce-mercadopago'),
            'billing_data_state_label'                => __('State', 'woocommerce-mercadopago'),
            'billing_data_state_placeholder'          => __('Select a state', 'woocommerce-mercadopago'),
            'billing_data_state_error_unselected'     => __('Please select an option.', 'woocommerce-mercadopago'),
            'billing_data_city_label'                 => __('City', 'woocommerce-mercadopago'),
            'billing_data_city_placeholder'           => __('E.g.: São Paulo', 'woocommerce-mercadopago'),
            'billing_data_city_error_empty'           => __('Please complete this field.', 'woocommerce-mercadopago'),
            'billing_data_city_error_invalid'         => __('Please enter the full name of the city.', 'woocommerce-mercadopago'),
            'billing_data_neighborhood_label'         => __('Neighborhood', 'woocommerce-mercadopago'),
            'billing_data_neighborhood_placeholder'   => __('E.g.: Jardim das flores', 'woocommerce-mercadopago'),
            'billing_data_neighborhood_error_empty'   => __('Please complete this field.', 'woocommerce-mercadopago'),
            'billing_data_neighborhood_error_invalid' => __('Please enter the full neighborhood name.', 'woocommerce-mercadopago'),
            'billing_data_address_label'              => __('Address', 'woocommerce-mercadopago'),
            'billing_data_address_placeholder'        => __('E.g.: Avenida das Flores', 'woocommerce-mercadopago'),
            'billing_data_address_error_empty'        => __('Please complete this field.', 'woocommerce-mercadopago'),
            'billing_data_address_error_invalid'      => __('Please enter the full address.', 'woocommerce-mercadopago'),
            'billing_data_address_comp_label'         => __('Complement (optional)', 'woocommerce-mercadopago'),
            'billing_data_address_comp_placeholder'   => __('E.g.: Apartament 52 block C', 'woocommerce-mercadopago'),
            'billing_data_number_label'               => __('Number', 'woocommerce-mercadopago'),
            'billing_data_number_placeholder'         => __('E.g.: 148', 'woocommerce-mercadopago'),
            'billing_data_number_toggle_label'        => __('No number', 'woocommerce-mercadopago'),
            'billing_data_number_error_empty'         => __('Please complete this field.', 'woocommerce-mercadopago'),
            'billing_data_number_error_invalid'       => __('Please enter a valid number.', 'woocommerce-mercadopago'),
        ];
    }


    /**
     * Set checkout pse translations
     *
     * @return void
     */
    private function setPseCheckoutTranslations(): void
    {
        $this->pseCheckout = [
            'test_mode_title'                  => __('Checkout PSE in Test Mode', 'woocommerce-mercadopago'),
            'test_mode_description'            => __('You can test the flow to generate a payment with PSE', 'woocommerce-mercadopago'),
            'test_mode_link_text'              => __('See the rules for the test mode.', 'woocommerce-mercadopago'),
            'input_document_label'             => __('Holder ID', 'woocommerce-mercadopago'),
            'input_document_helper_empty'      => __('Please complete this field.', 'woocommerce-mercadopago'),
            'input_document_helper_invalid'    => __('Please enter the full ID number.', 'woocommerce-mercadopago'),
            'input_document_helper_wrong'      => __('Please enter a valid ID.', 'woocommerce-mercadopago'),
            'pse_text_label'                   => __('Select where you want to pay', 'woocommerce-mercadopago'),
            'input_table_button'               => __('more options', 'woocommerce-mercadopago'),
            'person_type_label'                => __('Person type ', 'woocommerce-mercadopago'),
            'financial_institutions_label'     => __('Financial institution', 'woocommerce-mercadopago'),
            'financial_institutions_helper'    => __('Select the financial institution', 'woocommerce-mercadopago'),
            'financial_placeholder'            => __('Select the institution', 'woocommerce-mercadopago'),
            'customer_not_paid'                => __('Mercado Pago: The customer has not paid yet.', 'woocommerce-mercadopago'),
            'terms_and_conditions_description' => __('By continuing, you agree to our', 'woocommerce-mercadopago'),
            'terms_and_conditions_link_text'   => __('Terms and conditions', 'woocommerce-mercadopago'),
            'message_error_amount'             => __('There was an error. Please try again in a few minutes.', 'woocommerce-mercadopago'),

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
            'invalid_users'                            => __('<strong>Invalid transaction attempt</strong><br>You are trying to perform a productive transaction using test credentials, or test transaction using productive credentials. Please ensure that you are using the correct environment settings for the desired action.', 'woocommerce-mercadopago'),
            'invalid_operators'                        => __('<strong>Invalid transaction attempt</strong><br>It is not possible to pay with the email address entered. Please enter another e-mail address.', 'woocommerce-mercadopago'),
            'cho_default'                              => __('This payment method cannot process your payment.', 'woocommerce-mercadopago'),
        ];
    }

    /**
     * Set rejected payment messages translations for buyer
     *
     * @return void
     */
    private function setbuyerRefusedMessagesTranslations(): void
    {
        $this->buyerRefusedMessages = [
            'buyer_cc_rejected_call_for_authorize'          => __('<strong>Your bank needs you to authorize the payment</strong><br>Please call the telephone number on your card or pay with another method.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_high_risk'                   => __('<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.', 'woocommerce-mercadopago'),
            'buyer_rejected_high_risk'                      => __('<strong>For safety reasons, your payment was declined</strong><br>We recommended paying with your usual payment method and device for online purchases.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_bad_filled_other'            => __('<strong>One or more card details were entered incorrecctly</strong><br>Please enter them again as they appear on the card to complete the payment.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_bad_filled_security_code'    => __('<strong>One or more card details were entered incorrecctly</strong><br>Please enter them again as they appear on the card to complete the payment.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_bad_filled_date'             => __('<strong>One or more card details were entered incorrecctly</strong><br>Please enter them again as they appear on the card to complete the payment.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_bad_filled_card_number'      => __('<strong>One or more card details were entered incorrecctly</strong><br>Please enter them again as they appear on the card to complete the payment.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_insufficient_amount'         => __('<strong>Your credit card has no available limit</strong><br>Please pay using another card or choose another payment method.', 'woocommerce-mercadopago'),
            'buyer_insufficient_amount'                     => __('<strong>Your debit card has insufficient founds</strong><br>Please pay using another card or choose another payment method.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_invalid_installments'        => __('<strong>Your card does not accept the number of installments selected</strong><br>Please choose a different number of installments or use a different payment method .', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_card_disabled'               => __('<strong>You need to activate your card</strong><br>Please contact your bank by calling the number on the back of your card or choose another payment method.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_max_attempts'                => __('<strong>You reached the limit of payment attempts with this card</strong><br>Please pay using another card or choose another payment method.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_duplicated_payment'          => __('<strong>Your payment was declined because you already paid for this purchase</strong><br>Check your card transactions to verify it.', 'woocommerce-mercadopago'),
            'buyer_bank_error'                              => __('<strong>The card issuing bank declined the payment</strong><br>We recommended paying with another payment method or contact your bank.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_other_reason'                => __('<strong>The card issuing bank declined the payment</strong><br>We recommended paying with another payment method or contact your bank.', 'woocommerce-mercadopago'),
            'buyer_rejected_by_bank'                        => __('<strong>The card issuing bank declined the payment</strong><br>We recommended paying with another payment method or contact your bank.', 'woocommerce-mercadopago'),
            'buyer_cc_rejected_blacklist'                   => __('<strong>For safety reasons, the card issuing bank declined the payment</strong><br>We recommended paying with your usual payment method and device for online purchases.', 'woocommerce-mercadopago'),
            'buyer_default'                                 => __('<strong>Your payment was declined because something went wrong</strong><br>We recommended trying again or paying with another method.', 'woocommerce-mercadopago'),
            'buyer_yape_default'                            => __('<strong>Yape declined your payment</strong><br>Your payment could not be processed. Please try again or choose another payment method.', 'woocommerce-mercadopago'),
            'buyer_yape_cc_rejected_call_for_authorize'     => __('<strong>Yape declined your payment</strong><br>Your payment could not be processed. You can contact Yape to find out why or try again with this or another payment method.', 'woocommerce-mercadopago'),
            'buyer_yape_cc_unsupported_unsupported'         => __('<strong>Yape declined your payment</strong><br>Your payment was rejected because something went wrong. We recommend trying again or paying with another method.', 'woocommerce-mercadopago'),
            'buyer_yape_cc_amount_rate_limit_exceeded'      => __('<strong>Yape declined your payment</strong><br>This payment exceeds your daily limit for online purchases with Yape. We recommend paying with another method or trying again tomorrow.', 'woocommerce-mercadopago'),
            'buyer_yape_cc_rejected_max_attempts'           => __('<strong>Yape declined your payment</strong><br>After three incorrect approval codes, the payment can\'t be done with Yape for your safety. Pay with another method or try again in 24 hours.', 'woocommerce-mercadopago'),
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

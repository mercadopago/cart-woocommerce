<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ErrorMessages
 *
 * Centralized error messages management for payment processing
 *
 * @package MercadoPago\Woocommerce\Helpers
 */
class ErrorMessages
{
    /**
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * ErrorMessages constructor
     *
     * @param StoreTranslations $storeTranslations
     */
    public function __construct(StoreTranslations $storeTranslations)
    {
        $this->storeTranslations = $storeTranslations;
    }

    /**
     * Get all error messages (V1 and V2 merged)
     *
     * @return array
     */
    public function getErrorMessages(): array
    {
        return array_merge(
            $this->getErrorMessagesV1(),
            $this->getErrorMessagesV2()
        );
    }

    /**
     * Find and return the appropriate error message based on the input message
     *
     * @param string $message The error message to search for
     *
     * @return string The translated error message or default message if not found
     */
    public function findErrorMessage(string $message): string
    {
        $allErrorMessages = $this->getErrorMessages();

        foreach ($allErrorMessages as $keyword => $replacement) {
            if (stripos($message, $keyword) !== false) {
                return $replacement;
            }
        }

        return $this->getDefaultErrorMessage();
    }

    /**
     * @return array
     */
    private function getErrorMessagesV1(): array
    {
        return [
            "400"                                                                           => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
            "exception"                                                                     => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
            'buyer_default'                                                                 => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
            "cho_form_error"                                                                => $this->storeTranslations->commonMessages['cho_form_error'],
            "Invalid users involved"                                                        => $this->storeTranslations->checkoutErrorMessages['invalid_users'],
            "Invalid operators users involved"                                              => $this->storeTranslations->checkoutErrorMessages['payer_email_invalid'],
            "Invalid card_number_validation"                                                => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "POST to Gateway Transactions API fail"                                         => $this->storeTranslations->checkoutErrorMessages['api_fail'],
            "Connection to Card Token API fail"                                             => $this->storeTranslations->checkoutErrorMessages['api_fail'],
            "Invalid user identification number"                                            => $this->storeTranslations->checkoutErrorMessages['user_identification_invalid'],
            "Invalid transaction_amount"                                                    => $this->storeTranslations->checkoutErrorMessages['invalid_transaction_amount'],
            "Invalid value for transaction_amount"                                          => $this->storeTranslations->checkoutErrorMessages['invalid_transaction_amount'],
            "Installments attribute can't be null"                                          => $this->storeTranslations->checkoutErrorMessages['installments_required'],
            "Invalid installments"                                                          => $this->storeTranslations->checkoutErrorMessages['invalid_installments'],
            "Invalid coupon_amount"                                                         => $this->storeTranslations->checkoutErrorMessages['coupon_invalid'],
            "Coupon_amount attribute must be numeric"                                       => $this->storeTranslations->checkoutErrorMessages['coupon_not_numeric'],
            "Payer.email must be a valid email"                                             => $this->storeTranslations->checkoutErrorMessages['payer_email_invalid'],
            "Payer.email must be shorter than 254 characters"                               => $this->storeTranslations->checkoutErrorMessages['payer_email_too_long'],
            "The parameter cardholder.name cannot be null or empty"                         => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter cardholder.document.number cannot be null or empty"              => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "You must provide your cardholder_name with your card data"                     => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "You must provide your cardissuer_id with your card data"                       => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter cardholder.document.type cannot be null or empty"                => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter cardholder.document.subtype cannot be null or empty"             => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter expiration_month cannot be null or empty"                        => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter expiration_year cannot be null or empty"                         => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter card_number_id cannot be null or empty"                          => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid parameter security_code_length"                                        => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "The parameter security_code is a required field and cannot be null or empty"   => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid parameter card_number_length"                                          => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid parameter card_number"                                                 => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid card expiration month"                                                 => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid card expiration year"                                                  => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Secure_code_id can't be null"                                                  => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid security_code_length"                                                  => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "Invalid expiration date"                                                       => $this->storeTranslations->checkoutErrorMessages['card_details_incorrect'],
            "User unavailable"                                                              => $this->storeTranslations->checkoutErrorMessages['api_fail'],
            "O caller não está autorizado a acessar este recurso"                           => $this->storeTranslations->checkoutErrorMessages['caller_resource_unauthorized'],
            "O caller não está autorizado a realizar esta ação"                             => $this->storeTranslations->checkoutErrorMessages['caller_resource_unauthorized'],
            "Installments attribute must be numeric"                                        => $this->storeTranslations->checkoutErrorMessages['invalid_installments'],
            "Not found card on whitelist"                                                   => $this->storeTranslations->checkoutErrorMessages['card_not_whitelisted'],
            "Not found payment_method"                                                      => $this->storeTranslations->checkoutErrorMessages['payment_method_unavailable'],
        ];
    }

    /**
     * @return array
     */
    private function getErrorMessagesV2(): array
    {
        return [
            'Invalid test user email'                           => $this->storeTranslations->checkoutErrorMessagesV2['invalid_test_email'],
            'Email must be a valid email'                       => $this->storeTranslations->checkoutErrorMessagesV2['invalid_email'],
            'Invalid information. Please check and try again.'  => $this->storeTranslations->checkoutErrorMessagesV2['incorrect_card_details'],
            'Your credit card has no available limit. We recommend choosing another payment method.'                => $this->storeTranslations->checkoutErrorMessagesV2['card_no_limit'],
            'It was not possible to complete the payment. Please use another method to complete the purchase.'      => $this->storeTranslations->checkoutErrorMessagesV2['payment_not_completed'],
            'It was not possible to complete the payment due to a communication error. Please try again later.'     => $this->storeTranslations->checkoutErrorMessagesV2['communication_error_retry'],
            'The card issuing bank declined your payment. We recommend paying with another payment method or contacting your bank.'     => $this->storeTranslations->checkoutErrorMessagesV2['bank_declined_payment'],
            'Your payment was declined because you already paid for this purchase. Please check your card transactions to verify it.'   => $this->storeTranslations->checkoutErrorMessagesV2['duplicate_payment'],
            'Your bank needs you to authorize the payment. Please call the telephone number on your card or pay with another method.'   => $this->storeTranslations->checkoutErrorMessagesV2['bank_authorization_required'],
            'You reached the limit of payment attempts with this card. Please pay with another card or choose another payment method.'  => $this->storeTranslations->checkoutErrorMessagesV2['max_attempts_reached'],
            'Your payment was declined because something went wrong. We recommend trying again or paying with another payment method.'  => $this->storeTranslations->checkoutErrorMessagesV2['payment_generic_error'],
            'Your payment was declined. We recommend that you use the device and payment method you usually use for online shopping.'   => $this->storeTranslations->checkoutErrorMessagesV2['payment_declined_device'],
            'Your payment was declined due to an error in the store setup. Please get in touch with the store support and try again later.'     => $this->storeTranslations->checkoutErrorMessagesV2['store_setup_error'],
            'It was not possible to complete the payment due to a communication error. Please try again later or use another payment method.'   => $this->storeTranslations->checkoutErrorMessagesV2['communication_error_retry'],
            'Your payment was declined because some of your card details are incorrect. Please check the information to complete the purchase.'         => $this->storeTranslations->checkoutErrorMessagesV2['incorrect_card_details'],
            'For safety reasons, your payment was declined. We recommend paying with your usual payment method and device for online purchases.'        => $this->storeTranslations->checkoutErrorMessagesV2['payment_declined_safety'],
            'You have to activate your card. Please contact your bank by calling the number on the back of your card or choose another payment method.' => $this->storeTranslations->checkoutErrorMessagesV2['card_activation_required'],
            'One or more of the card details were entered incorrectly. Please enter them again exactly as they appear on the card to complete the payment.'         => $this->storeTranslations->checkoutErrorMessagesV2['incorrect_card_details'],
            'Your card does not accept the number of installments selected. Please choose a different number of installments or use another payment method.'        => $this->storeTranslations->checkoutErrorMessages['invalid_installments'],
            'For safety reasons, the card issuing bank declined the payment. We recommend paying with your usual payment method and device for online purchases.'   => $this->storeTranslations->checkoutErrorMessagesV2['bank_declined_safety'],
            'The payment method selected isn\'t available at the store. We recommend paying with another method or choosing another number of installments if you\'re trying to pay with a card.'   => $this->storeTranslations->checkoutErrorMessagesV2['payment_method_not_available'],
        ];
    }

    /**
     * @return string
     */
    public function getDefaultErrorMessage(): string
    {
        return $this->storeTranslations->buyerRefusedMessages['buyer_default'];
    }
}

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
     * Get all error messages (V1, V2, buyer refused, common and checkout error messages merged)
     *
     * @return array
     */
    public function getErrorMessages(): array
    {
        return array_merge(
            $this->getBuyerRefusedMessagesMapping(),
            $this->getCommonMessagesMapping(),
            $this->getCheckoutErrorMessagesV2Mapping(),
            $this->getErrorMessagesV1(),
            $this->getErrorMessagesV2()
        );
    }

    /**
     * Find and return the appropriate error message based on the input message
     *
     * This method is idempotent - if the message is identified as already translated, it will be returned as-is.
     * The lookup priority is:
     * 1. Exact key match (e.g., 'buyer_cc_rejected_high_risk') - uses normalized message
     * 2. Already translated detection (contains HTML markers)
     * 3. Keyword search (partial match in message) - normalizes both message and keyword
     * 4. Default message (fallback)
     *
     * @param string $message The error message key or text to search for
     *
     * @return string The translated error message or the original if already translated, or the default message if no match found
     */
    public function findErrorMessage(string $message): string
    {
        $allErrorMessages = $this->getErrorMessages();

        $normalizedMessage = $this->normalizeMessageForKeyMatch($message);

        if (isset($allErrorMessages[$normalizedMessage])) {
            return $allErrorMessages[$normalizedMessage];
        }

        if ($this->isAlreadyTranslated($message)) {
            return $message;
        }

        $messageWithoutSlashes = stripslashes($message);
        foreach ($allErrorMessages as $keyword => $replacement) {
            if (stripos($message, $keyword) !== false || stripos($messageWithoutSlashes, $keyword) !== false) {
                return $replacement;
            }
            $normalizedKeyword = $this->normalizeMessageForKeyMatch($keyword);
            if (stripos($normalizedMessage, $normalizedKeyword) !== false) {
                return $replacement;
            }
        }

        return $this->getDefaultErrorMessage();
    }

    /**
     * Normalize message for key matching
     *
     * Handles common variations in message format:
     * - Removes trailing punctuation (. ! ?) while preserving punctuation in the middle
     * - Removes escape characters (e.g., isn\'t becomes isn't)
     * - Trims whitespace
     *
     * @param string $message The message to normalize
     *
     * @return string The normalized message
     */
    private function normalizeMessageForKeyMatch(string $message): string
    {
        $normalized = trim($message);

        $normalized = stripslashes($normalized);

        $normalized = rtrim($normalized, '.!?');

        return $normalized;
    }

    /**
     * Check if a message appears to be already translated
     *
     * Translated messages typically contain HTML formatting tags
     * that are used in the StoreTranslations messages.
     *
     * @param string $message The message to check
     *
     * @return bool True if the message appears to be already translated
     */
    private function isAlreadyTranslated(string $message): bool
    {
        $translatedMarkers = [
            '<strong>',
            '</strong>',
            '<br>',
            '<br/>',
            '<br />',
            '<b>',
            '</b>',
        ];

        foreach ($translatedMarkers as $marker) {
            if (stripos($message, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get buyer refused messages mapping (keys to translations)
     *
     * @return array
     */
    private function getBuyerRefusedMessagesMapping(): array
    {
        return [
            'buyer_cc_rejected_call_for_authorize'      => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_call_for_authorize'],
            'buyer_cc_rejected_high_risk'               => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_high_risk'],
            'buyer_rejected_high_risk'                  => $this->storeTranslations->buyerRefusedMessages['buyer_rejected_high_risk'],
            'buyer_cc_rejected_bad_filled_other'        => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_bad_filled_other'],
            'buyer_cc_rejected_bad_filled_security_code' => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_bad_filled_security_code'],
            'buyer_cc_rejected_bad_filled_date'         => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_bad_filled_date'],
            'buyer_cc_rejected_bad_filled_card_number'  => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_bad_filled_card_number'],
            'buyer_cc_rejected_insufficient_amount'     => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_insufficient_amount'],
            'buyer_insufficient_amount'                 => $this->storeTranslations->buyerRefusedMessages['buyer_insufficient_amount'],
            'buyer_cc_rejected_invalid_installments'    => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_invalid_installments'],
            'buyer_cc_rejected_card_disabled'           => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_card_disabled'],
            'buyer_cc_rejected_max_attempts'            => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_max_attempts'],
            'buyer_cc_rejected_duplicated_payment'      => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_duplicated_payment'],
            'buyer_bank_error'                          => $this->storeTranslations->buyerRefusedMessages['buyer_bank_error'],
            'buyer_cc_rejected_other_reason'            => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_other_reason'],
            'buyer_rejected_by_bank'                    => $this->storeTranslations->buyerRefusedMessages['buyer_rejected_by_bank'],
            'buyer_cc_rejected_blacklist'               => $this->storeTranslations->buyerRefusedMessages['buyer_cc_rejected_blacklist'],
            'buyer_default'                             => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
            'buyer_yape_default'                        => $this->storeTranslations->buyerRefusedMessages['buyer_yape_default'],
            'buyer_yape_cc_rejected_call_for_authorize' => $this->storeTranslations->buyerRefusedMessages['buyer_yape_cc_rejected_call_for_authorize'],
            'buyer_yape_cc_unsupported_unsupported'     => $this->storeTranslations->buyerRefusedMessages['buyer_yape_cc_unsupported_unsupported'],
            'buyer_yape_cc_amount_rate_limit_exceeded'  => $this->storeTranslations->buyerRefusedMessages['buyer_yape_cc_amount_rate_limit_exceeded'],
            'buyer_yape_cc_rejected_max_attempts'       => $this->storeTranslations->buyerRefusedMessages['buyer_yape_cc_rejected_max_attempts'],
        ];
    }

    /**
     * Get common messages mapping (keys to translations)
     *
     * @return array
     */
    private function getCommonMessagesMapping(): array
    {
        return [
            'cho_form_error' => $this->storeTranslations->commonMessages['cho_form_error'],
        ];
    }

    /**
     * Get checkout error messages V2 mapping (keys to translations)
     *
     * @return array
     */
    private function getCheckoutErrorMessagesV2Mapping(): array
    {
        return [
            'invalid_email'      => $this->storeTranslations->checkoutErrorMessagesV2['invalid_email'],
            'invalid_test_email' => $this->storeTranslations->checkoutErrorMessagesV2['invalid_test_email'],
        ];
    }

    /**
     * @return array
     */
    private function getErrorMessagesV1(): array
    {
        return [
            "400"                                                                           => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
            "exception"                                                                     => $this->storeTranslations->buyerRefusedMessages['buyer_default'],
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

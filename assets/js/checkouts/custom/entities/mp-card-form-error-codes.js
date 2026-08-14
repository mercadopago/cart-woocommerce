/* eslint-disable no-unused-vars */
/**
 * CardForm SDK codes & gate-metric reason mapping
 *
 * Centralized cardForm SDK-related constants:
 *  - MPCardFormErrorCodes: error strings/substrings the SDK sends, matched in the cardForm callbacks.
 *  - CARD_VALIDATION_REASON_BY_CODE: maps the SDK card-number validity code (error[0].code) to the plugin's gate-metric reason vocabulary.
 */
const MPCardFormErrorCodes = {
    // onPaymentMethodsReceived — BIN not recognized by the payment methods API
    NO_PAYMENT_METHODS_FOUND: 'No payment methods found',

    // onError — card form initialization timeout (internal plugin code)
    INIT_CARD_FORM_TIMEOUT: 'INIT_CARD_FORM_TIMEOUT',

    // onError — SDK iframe timed out during load
    TIMED_OUT: 'timed out',

    // onError — security code validation substrings
    SECURITY_CODE_INVALID_NUMBER: 'should be a number',
    SECURITY_CODE_INVALID_LENGTH: 'should be of length',
};

// SDK card-number validity code (error[0].code) -> plugin gate-metric reason
const CARD_VALIDATION_REASON_BY_CODE = {
    invalid_length: 'invalid_length',
    invalid_type: 'empty_field',
    invalid_value: 'rejected_luhn',
};

window.MPCardFormErrorCodes = MPCardFormErrorCodes;
window.CARD_VALIDATION_REASON_BY_CODE = CARD_VALIDATION_REASON_BY_CODE;

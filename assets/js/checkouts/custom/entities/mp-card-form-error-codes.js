/* eslint-disable no-unused-vars */
/**
 * CardForm SDK Error Codes
 *
 * Centralized error codes for the Mercado Pago cardForm SDK callbacks.
 * @constant {Object}
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

window.MPCardFormErrorCodes = MPCardFormErrorCodes;

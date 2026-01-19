/* eslint-disable no-unused-vars */
/**
 * SuperToken Error Codes
 *
 * Centralized error codes for SuperToken checkout flow
 * @constant {Object}
 */
const MPSuperTokenErrorCodes = {
    // Validation errors
    SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
    SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',

    // Authentication errors
    AUTHENTICATOR_NOT_FOUND: 'AUTHENTICATOR_NOT_FOUND',
    AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',

    // Payment errors
    UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
    EMPTY_ACCOUNT_PAYMENT_METHODS: 'EMPTY_ACCOUNT_PAYMENT_METHODS',

    // System errors
    SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
    SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
    CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND: 'CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND',

    // Generic error
    UNKNOWN_ERROR: 'UNKNOWN_ERROR'
};

/**
 * Expected errors that should not trigger error metrics
 * These are business-level errors that are part of normal flow
 * @constant {Array<string>}
 */
const MPSuperTokenExpectedErrors = [
    MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR,
    MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID,
    MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
    MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
];

/**
 * Skippable error messages from SDK/external sources
 * These error messages should not trigger metrics reporting as they are
 * expected user behaviors or known SDK limitations
 * @constant {Array<string|RegExp>}
 */
const MPSuperTokenSkippableErrorMessages = [
    'user cancelled authentication',
    'no applications were detected on device',
    'authenticator flow is not supported',
    'payment request flow not supported',
    'invalid email address provided',
    'invalid amount value provided',
    'empty_account_payment_methods',
    'paymentrequest api is not supported on this browser',
    /the\s+site\s+id\s+\w+\s+is\s+not\s+supported/i,
];


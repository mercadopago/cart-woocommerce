/**
 * Single source of truth for Super Token error codes and the pure mapping from an
 * error code to the message shown to the buyer. No side effects: unlike the legacy
 * convertErrorCodeToErrorMessage (v2.1:262), this neither increments the retry
 * counter nor emits metrics — the caller (use case) owns the counter (SuperTokenState)
 * and the retry-limit metric (MetricsPort).
 *
 * Codes migrated 1:1 from v2.1/errors/super-token-error-constants.js.
 */

export const MPSuperTokenErrorCodes = {
  // Validation errors
  SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
  SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',

  // Authentication errors
  AUTHENTICATOR_NOT_FOUND: 'AUTHENTICATOR_NOT_FOUND',
  AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
  AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',

  // Payment errors
  UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
  EMPTY_ACCOUNT_PAYMENT_METHODS: 'EMPTY_ACCOUNT_PAYMENT_METHODS',
  GET_PAYMENT_METHOD_TIMEOUT_ERROR: 'GET_PAYMENT_METHOD_TIMEOUT_ERROR',
  FETCH_PAYMENT_METHOD_NOT_FOUND: 'FETCH_PAYMENT_METHOD_NOT_FOUND',
  PAYMENT_METHOD_NOT_EXISTS: 'PAYMENT_METHOD_NOT_EXISTS',
  UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS:
    'UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS',

  // System errors
  SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
  SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
  CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND: 'CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND',
  SUPER_TOKEN_METRICS_NOT_FOUND: 'SUPER_TOKEN_METRICS_NOT_FOUND',

  // Generic error
  UNKNOWN_ERROR: 'UNKNOWN_ERROR',
} as const;

export type SuperTokenErrorCode =
  (typeof MPSuperTokenErrorCodes)[keyof typeof MPSuperTokenErrorCodes];

/**
 * Single source of truth for the recoverable-error list (RN-2). Migrated 1:1 from the
 * duplicated `recoverableErrors` arrays in the Classic (`event-handler.js:433-437`) and
 * Blocks (`custom.block.js:128-132`) finalization handlers — the duplication that caused
 * PSW-3737/PSW-4113 to be fixed in two places. A recoverable error lets the buyer retry
 * without losing the checkout; any other code is unrecoverable.
 */
export const RECOVERABLE_ERRORS: readonly string[] = [
  MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
  MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
  MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
];

/**
 * Whether an error code is recoverable. Strict membership — matches the legacy
 * `recoverableErrors.includes(exception?.message)` (exact equality, not the substring
 * match `resolveErrorMessage` uses), so classification behaviour is unchanged.
 */
export const isRecoverable = (errorCode: string | undefined): boolean =>
  !!errorCode && RECOVERABLE_ERRORS.includes(errorCode);

/** Buyer-facing error copy the message resolution selects from. */
export interface ErrorMessageCopy {
  updateSecurityCodeWithRetryText: string;
  updateSecurityCodeNoRetryText: string;
  authorizePaymentMethodWithRetryText: string;
  authorizePaymentMethodNoRetryText: string;
  selectPaymentMethodErrorText: string;
  /** Fallback for unmapped codes; legacy reuses the update-security-code-with-retry text. */
  genericErrorText: string;
}

interface ErrorMessagePair {
  withRetry: string;
  withoutRetry: string;
}

const errorMessagesFor = (copy: ErrorMessageCopy): Record<string, ErrorMessagePair> => ({
  UPDATE_SECURITY_CODE_ERROR: {
    withRetry: copy.updateSecurityCodeWithRetryText,
    withoutRetry: copy.updateSecurityCodeNoRetryText,
  },
  AUTHORIZE_PAYMENT_METHOD_ERROR: {
    withRetry: copy.authorizePaymentMethodWithRetryText,
    withoutRetry: copy.authorizePaymentMethodNoRetryText,
  },
  AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: {
    withRetry: copy.authorizePaymentMethodWithRetryText,
    withoutRetry: copy.authorizePaymentMethodNoRetryText,
  },
  SELECT_PAYMENT_METHOD_ERROR: {
    withRetry: copy.selectPaymentMethodErrorText,
    withoutRetry: copy.selectPaymentMethodErrorText,
  },
});

/**
 * Resolves the buyer message for an error code. `errorCode.includes(key)` (substring)
 * match is preserved from the legacy implementation. `allowRetry` is decided by the
 * caller from the retry counter (SuperTokenState.shouldAllowRetry).
 */
export const resolveErrorMessage = (
  errorCode: string,
  allowRetry: boolean,
  copy: ErrorMessageCopy,
): string => {
  const errorMessages = errorMessagesFor(copy);
  const errorConfig =
    Object.entries(errorMessages).find(([key]) => errorCode.includes(key))?.[1] ?? null;

  if (!errorConfig) {
    return copy.genericErrorText;
  }

  return allowRetry ? errorConfig.withRetry : errorConfig.withoutRetry;
};

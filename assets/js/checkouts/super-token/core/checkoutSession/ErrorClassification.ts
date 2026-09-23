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
  UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS: 'UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS',

  // System errors
  SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
  SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
  CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND: 'CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND',
  SUPER_TOKEN_METRICS_NOT_FOUND: 'SUPER_TOKEN_METRICS_NOT_FOUND',

  // Generic error
  UNKNOWN_ERROR: 'UNKNOWN_ERROR',
} as const;

export type SuperTokenErrorCode = (typeof MPSuperTokenErrorCodes)[keyof typeof MPSuperTokenErrorCodes];

const KNOWN_ERROR_CODES: readonly SuperTokenErrorCode[] = Object.values(MPSuperTokenErrorCodes);

const SENSITIVE_ERROR_KEY_NAMES =
  'authorization|access_token|refresh_token|id_token|token|authorized_pseudotoken|pseudotoken|password|client[_-]?secret|x[_-]?api[_-]?key|api[_-]?key|secret|security_code|cvv|card_number|cookie|set[_-]?cookie|session(?:[_-]?(?:id|token))?|jwt';
const SENSITIVE_ERROR_KEY = new RegExp(`^(?:${SENSITIVE_ERROR_KEY_NAMES})$`, 'i');
const EMAIL_ADDRESS_PATTERN = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi;
const BEARER_TOKEN_PATTERN = /\b(Bearer\s+)[A-Z0-9._~+/-]+=*/gi;
const COOKIE_HEADER_PATTERN = /\b((?:Set-Cookie|Cookie)\s*[:=]\s*)[^\r\n]*/gi;
// Authorization schemes may contain spaces and Base64 padding (`==`), which can look like the
// start of another keyed field to the generic pattern below. Consume the complete header line
// first so no credential fragment survives under Basic, Bearer, Digest or future schemes.
const AUTHORIZATION_HEADER_PATTERN = /\b((?:Proxy-)?Authorization\s*:\s*)[^\r\n]*/gi;
const AUTHORIZATION_REDACTION_PLACEHOLDER = '__MP_AUTHORIZATION_REDACTED__';
const JWT_TOKEN_PATTERN = /\beyJ[A-Z0-9_-]*\.[A-Z0-9_-]+\.[A-Z0-9_-]+\b/gi;
const KEYED_SECRET_PATTERN = new RegExp(
  `((["']?(?:${SENSITIVE_ERROR_KEY_NAMES})["']?)\\s*[:=]\\s*)(?:"([^"\\r\\n]*)"|'([^'\\r\\n]*)'|([^,;\\r\\n}\\]&]*?))(?=\\s+[A-Z_][A-Z0-9_.-]*\\s*[:=]|[,;\\r\\n}\\]&]|$)`,
  'gi',
);

const redactKeyedSecret = (
  _match: string,
  prefix: string,
  _key: string,
  doubleQuotedValue?: string,
  singleQuotedValue?: string,
): string => {
  if (doubleQuotedValue !== undefined) {
    return `${prefix}"[REDACTED]"`;
  }
  if (singleQuotedValue !== undefined) {
    return `${prefix}'[REDACTED]'`;
  }
  return `${prefix}[REDACTED]`;
};

const redactSensitiveTelemetryValues = (message: string): string =>
  message
    .replace(EMAIL_ADDRESS_PATTERN, '[REDACTED_EMAIL]')
    // Use a neutral marker because the generic redactor treats the closing `]` in [REDACTED]
    // as a value delimiter and would otherwise process this header a second time.
    .replace(AUTHORIZATION_HEADER_PATTERN, `$1${AUTHORIZATION_REDACTION_PLACEHOLDER}`)
    .replace(KEYED_SECRET_PATTERN, redactKeyedSecret)
    // Reconsume the complete header so semicolon-delimited cookie attributes cannot escape.
    .replace(COOKIE_HEADER_PATTERN, '$1[REDACTED]')
    .replace(BEARER_TOKEN_PATTERN, '$1[REDACTED]')
    .replace(JWT_TOKEN_PATTERN, '[REDACTED_JWT]')
    .split(AUTHORIZATION_REDACTION_PLACEHOLDER)
    .join('[REDACTED]');

/**
 * Preserves the real error text needed for production troubleshooting while redacting only
 * credential/PII values. Error names, SDK codes, HTTP statuses and surrounding diagnostic context
 * remain intact. Objects without a message are serialized with sensitive keys replaced.
 */
export const toTelemetryErrorMessage = (error: unknown, fallback = 'Unknown error'): string => {
  try {
    if (typeof error === 'string' && error.trim()) {
      return redactSensitiveTelemetryValues(error);
    }

    if (error && typeof error === 'object') {
      const message = (error as { message?: unknown }).message;
      if (typeof message === 'string' && message.trim()) {
        return redactSensitiveTelemetryValues(message);
      }

      const errorCode = (error as { errorCode?: unknown }).errorCode;
      if (typeof errorCode === 'string' && errorCode.trim()) {
        return redactSensitiveTelemetryValues(errorCode);
      }

      const seenObjects = new WeakSet<object>();
      const serialized = JSON.stringify(error, (key, value) => {
        if (SENSITIVE_ERROR_KEY.test(key)) {
          return '[REDACTED]';
        }
        if (typeof value === 'string') {
          return redactSensitiveTelemetryValues(value);
        }
        if (typeof value === 'bigint') {
          return String(value);
        }
        if (value && typeof value === 'object') {
          if (seenObjects.has(value)) {
            return '[Circular]';
          }
          seenObjects.add(value);
        }
        return value;
      });
      if (serialized && serialized !== '{}') {
        return serialized;
      }
    }

    if (error !== null && error !== undefined) {
      const stringified = String(error);
      if (stringified && stringified !== '[object Object]') {
        return redactSensitiveTelemetryValues(stringified);
      }
    }
  } catch {
    // A malformed SDK object may throw from a getter or during serialization.
  }

  return fallback;
};

/**
 * Classifies an arbitrary exception into the allowlisted Super Token catalog. This is an auxiliary
 * dimension for grouping; the diagnostic message is produced independently by
 * `toTelemetryErrorMessage`, so an unknown code does not erase the real failure context.
 */
export const toSafeTelemetryErrorCode = (error: unknown): SuperTokenErrorCode => {
  const candidates: unknown[] = [error];

  if (error && typeof error === 'object') {
    try {
      candidates.push((error as { errorCode?: unknown }).errorCode, (error as { message?: unknown }).message);
    } catch {
      return MPSuperTokenErrorCodes.UNKNOWN_ERROR;
    }
  }

  for (const candidate of candidates) {
    if (typeof candidate !== 'string') {
      continue;
    }
    const knownCode = KNOWN_ERROR_CODES.find((code) => candidate.includes(code));
    if (knownCode) {
      return knownCode;
    }
  }

  return MPSuperTokenErrorCodes.UNKNOWN_ERROR;
};

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
export const resolveErrorMessage = (errorCode: string, allowRetry: boolean, copy: ErrorMessageCopy): string => {
  const errorMessages = errorMessagesFor(copy);
  const errorConfig = Object.entries(errorMessages).find(([key]) => errorCode.includes(key))?.[1] ?? null;

  if (!errorConfig) {
    return copy.genericErrorText;
  }

  return allowRetry ? errorConfig.withRetry : errorConfig.withoutRetry;
};

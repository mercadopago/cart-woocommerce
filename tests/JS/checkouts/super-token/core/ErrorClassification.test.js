const {
  MPSuperTokenErrorCodes,
  resolveErrorMessage,
  RECOVERABLE_ERRORS,
  isRecoverable,
  toTelemetryErrorMessage,
  toSafeTelemetryErrorCode,
} = require('@super-token/core/checkoutSession/ErrorClassification');

const errorCopy = {
  updateSecurityCodeWithRetryText: 'update-with-retry',
  updateSecurityCodeNoRetryText: 'update-no-retry',
  authorizePaymentMethodWithRetryText: 'authorize-with-retry',
  authorizePaymentMethodNoRetryText: 'authorize-no-retry',
  selectPaymentMethodErrorText: 'select-error',
  genericErrorText: 'generic',
};
const jwtFixture = ['eyJhbGciOiJIUzI1NiJ9', 'payload', 'signature'].join('.');

describe('ErrorClassification', () => {
  it('Given a recoverable update-security-code error, When retry is allowed, Then the with-retry message is returned', () => {
    expect(resolveErrorMessage('UPDATE_SECURITY_CODE_ERROR', true, errorCopy)).toBe('update-with-retry');
  });

  it('Given the same error, When retry is not allowed, Then the no-retry message is returned', () => {
    expect(resolveErrorMessage('UPDATE_SECURITY_CODE_ERROR', false, errorCopy)).toBe('update-no-retry');
  });

  it('Given an authorize-payment error, When resolved, Then the authorize messages are used', () => {
    expect(resolveErrorMessage('AUTHORIZE_PAYMENT_METHOD_ERROR', true, errorCopy)).toBe('authorize-with-retry');
    expect(resolveErrorMessage('AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED', false, errorCopy)).toBe('authorize-no-retry');
  });

  it('Given a select-payment error, When resolved, Then the same message is used regardless of retry', () => {
    expect(resolveErrorMessage('SELECT_PAYMENT_METHOD_ERROR', true, errorCopy)).toBe('select-error');
    expect(resolveErrorMessage('SELECT_PAYMENT_METHOD_ERROR', false, errorCopy)).toBe('select-error');
  });

  it('Given a code that only contains a known key as a substring, When resolved, Then it still matches', () => {
    expect(resolveErrorMessage('prefix:AUTHORIZE_PAYMENT_METHOD_ERROR:suffix', true, errorCopy)).toBe(
      'authorize-with-retry',
    );
  });

  it('Given an unmapped error code, When resolved, Then the generic message is returned', () => {
    expect(resolveErrorMessage('SOMETHING_ELSE', true, errorCopy)).toBe('generic');
  });

  it('Given the error codes catalog, When read, Then it exposes the known codes', () => {
    expect(MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS).toBe('PAYMENT_METHOD_NOT_EXISTS');
    expect(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID).toBe('SELECT_PAYMENT_METHOD_NOT_VALID');
  });

  describe('telemetry error sanitization', () => {
    it('Given a known code in an Error, When sanitized, Then it preserves only the allowlisted code', () => {
      expect(toSafeTelemetryErrorCode(new Error(`SDK: ${MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR}`))).toBe(
        MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
      );
    });

    it('Given a known errorCode field, When sanitized, Then it preserves the allowlisted code', () => {
      expect(toSafeTelemetryErrorCode({ errorCode: MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR })).toBe(
        MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR,
      );
    });

    it('Given an arbitrary message containing PII, When sanitized, Then it returns the fixed unknown code', () => {
      expect(toSafeTelemetryErrorCode(new Error('buyer john@example.com token=secret'))).toBe(
        MPSuperTokenErrorCodes.UNKNOWN_ERROR,
      );
    });

    it('Given a real SDK error, When formatted for telemetry, Then it preserves the diagnostic message', () => {
      expect(toTelemetryErrorMessage(new Error('Failed to build authenticator: invalid challenge'))).toBe(
        'Failed to build authenticator: invalid challenge',
      );
    });

    it('Given an error with sensitive values, When formatted for telemetry, Then it redacts only those values', () => {
      expect(
        toTelemetryErrorMessage(new Error('buyer john@example.com failed with pseudotoken=secret-value status=401')),
      ).toBe('buyer [REDACTED_EMAIL] failed with pseudotoken=[REDACTED] status=401');
    });

    it.each([
      ['api_key=key-123 status=401', 'api_key=[REDACTED] status=401'],
      ['client_secret=secret words status=401', 'client_secret=[REDACTED] status=401'],
      ['Authorization: Basic dXNlcjpwYXNz', 'Authorization: [REDACTED]'],
      [
        'Request failed Authorization: Basic dXNlcjpwYXNzd29yZA== on /v1/payments',
        'Request failed Authorization: [REDACTED]',
      ],
      [
        'Proxy-Authorization: Digest username="buyer", response="secret"',
        'Proxy-Authorization: [REDACTED]',
      ],
      ['password: correct horse battery staple', 'password: [REDACTED]'],
      ['password="correct horse battery staple" status=401', 'password="[REDACTED]" status=401'],
      ['session_id=session-value status=401', 'session_id=[REDACTED] status=401'],
      [`jwt=${jwtFixture} status=401`, 'jwt=[REDACTED] status=401'],
      [`SDK rejected ${jwtFixture} status=401`, 'SDK rejected [REDACTED_JWT] status=401'],
    ])(
      'Given a whitespace-bearing or commonly named credential in %s, When formatted for telemetry, Then its complete value is redacted',
      (message, expected) => {
        expect(toTelemetryErrorMessage(new Error(message))).toBe(expected);
      },
    );

    it.each([
      ['Cookie: session=secret; preference=value', 'Cookie: [REDACTED]'],
      ['Set-Cookie: session=secret; HttpOnly; Secure\nstatus=401', 'Set-Cookie: [REDACTED]\nstatus=401'],
      ['Cookie=theme=dark; SID=second', 'Cookie=[REDACTED]'],
      ['Set-Cookie=session=secret; HttpOnly; Secure\nstatus=401', 'Set-Cookie=[REDACTED]\nstatus=401'],
    ])(
      'Given a cookie-bearing header in %s, When formatted for telemetry, Then its complete value is redacted',
      (message, expected) => {
        expect(toTelemetryErrorMessage(new Error(message))).toBe(expected);
      },
    );

    it('Given an object without a message, When formatted for telemetry, Then it serializes context and redacts sensitive keys', () => {
      expect(toTelemetryErrorMessage({ status: 422, reason: 'invalid challenge', token: 'secret' })).toBe(
        '{"status":422,"reason":"invalid challenge","token":"[REDACTED]"}',
      );
    });

    it('Given an object with common credential key variants, When formatted for telemetry, Then their values are redacted', () => {
      expect(
        toTelemetryErrorMessage({
          status: 401,
          client_secret: 'client-value',
          api_key: 'api-value',
          sessionToken: 'session-value',
          cookie: 'session=secret',
          jwt: jwtFixture,
        }),
      ).toBe(
        '{"status":401,"client_secret":"[REDACTED]","api_key":"[REDACTED]","sessionToken":"[REDACTED]","cookie":"[REDACTED]","jwt":"[REDACTED]"}',
      );
    });

    it('Given a circular SDK error object, When formatted for telemetry, Then it preserves the available diagnostic context', () => {
      const sdkError = { status: 503, reason: 'authenticator unavailable' };
      sdkError.context = sdkError;

      expect(toTelemetryErrorMessage(sdkError)).toBe(
        '{"status":503,"reason":"authenticator unavailable","context":"[Circular]"}',
      );
    });
  });

  describe('recoverable-error classification — a recoverable error lets the buyer retry without losing the checkout; any other code is unrecoverable', () => {
    it('Given the recoverable list, When read, Then it is exactly the three retry-eligible codes', () => {
      expect(RECOVERABLE_ERRORS).toEqual([
        MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
        MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
        MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
      ]);
    });

    it.each([
      MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
      MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
      MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
    ])('Given the recoverable code %s, When classified, Then it is recoverable', (code) => {
      expect(isRecoverable(code)).toBe(true);
    });

    it('Given a code outside the list, When classified, Then it is not recoverable', () => {
      expect(isRecoverable(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR)).toBe(false);
      expect(isRecoverable(MPSuperTokenErrorCodes.SUPER_TOKEN_METRICS_NOT_FOUND)).toBe(false);
    });

    it('Given a code that only contains a recoverable key as a substring, When classified, Then it is NOT recoverable (strict equality, unlike the message match)', () => {
      expect(isRecoverable('prefix:AUTHORIZE_PAYMENT_METHOD_ERROR')).toBe(false);
    });

    it('Given an undefined code, When classified, Then it is not recoverable', () => {
      expect(isRecoverable(undefined)).toBe(false);
    });
  });
});

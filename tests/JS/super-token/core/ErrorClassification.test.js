const {
  MPSuperTokenErrorCodes,
  resolveErrorMessage,
  RECOVERABLE_ERRORS,
  isRecoverable,
} = require('@super-token/core/checkoutSession/ErrorClassification');

const errorCopy = {
  updateSecurityCodeWithRetryText: 'update-with-retry',
  updateSecurityCodeNoRetryText: 'update-no-retry',
  authorizePaymentMethodWithRetryText: 'authorize-with-retry',
  authorizePaymentMethodNoRetryText: 'authorize-no-retry',
  selectPaymentMethodErrorText: 'select-error',
  genericErrorText: 'generic',
};

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
    expect(resolveErrorMessage('prefix:AUTHORIZE_PAYMENT_METHOD_ERROR:suffix', true, errorCopy)).toBe('authorize-with-retry');
  });

  it('Given an unmapped error code, When resolved, Then the generic message is returned', () => {
    expect(resolveErrorMessage('SOMETHING_ELSE', true, errorCopy)).toBe('generic');
  });

  it('Given the error codes catalog, When read, Then it exposes the known codes', () => {
    expect(MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS).toBe('PAYMENT_METHOD_NOT_EXISTS');
    expect(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID).toBe('SELECT_PAYMENT_METHOD_NOT_VALID');
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

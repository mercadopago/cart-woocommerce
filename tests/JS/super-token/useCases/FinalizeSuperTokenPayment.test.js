const {
  FinalizeSuperTokenPayment,
} = require('@super-token/useCases/FinalizeSuperTokenPayment');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');

const buildPaymentMethods = (overrides = {}) => ({
  getActivePaymentMethod: jest.fn(() => ({ token: 'tok-1', type: 'credit_card' })),
  isSelectedPaymentMethodValid: jest.fn(() => true),
  validateInstallmentSelection: jest.fn(() => true),
  updateSecurityCode: jest.fn(() => Promise.resolve()),
  unmountCardForm: jest.fn(),
  ...overrides,
});

const buildAuthenticator = (overrides = {}) => ({
  authorizePayment: jest.fn(() => Promise.resolve()),
  setSuperTokenValidation: jest.fn(),
  ...overrides,
});

describe('FinalizeSuperTokenPayment', () => {
  describe('canonical finalization sequence — validate selection, then CVV update, authorize, and mark the token valid, identically for Classic and Blocks', () => {
    it('Given a valid selection with CVV and installments, When executed, Then it runs the canonical sequence and returns success', async () => {
      const paymentMethods = buildPaymentMethods();
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({ status: 'success' });
      expect(paymentMethods.updateSecurityCode).toHaveBeenCalledTimes(1);
      expect(authenticator.authorizePayment).toHaveBeenCalledWith('tok-1');
      expect(authenticator.setSuperTokenValidation).toHaveBeenCalledWith(true);
      expect(paymentMethods.unmountCardForm).not.toHaveBeenCalled();
    });

    it('Given the order-pay page, When executed, Then it unmounts the card form before authorizing', async () => {
      const paymentMethods = buildPaymentMethods();
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: true,
      });

      expect(result.status).toBe('success');
      expect(paymentMethods.unmountCardForm).toHaveBeenCalledTimes(1);
    });
  });

  describe('validation errors', () => {
    it('Given no active method, When executed, Then it returns fatal_error (SELECT_PAYMENT_METHOD_ERROR falls through the generic catch, as in legacy)', async () => {
      const paymentMethods = buildPaymentMethods({ getActivePaymentMethod: jest.fn(() => null) });
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({
        status: 'fatal_error',
        errorCode: MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR,
        error: expect.any(Error),
      });
      expect(authenticator.setSuperTokenValidation).toHaveBeenCalledWith(false);
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
    });

    it('Given an invalid selection, When executed, Then it returns validation_error and does NOT reset validation', async () => {
      const paymentMethods = buildPaymentMethods({
        isSelectedPaymentMethodValid: jest.fn(() => false),
      });
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({
        status: 'validation_error',
        errorCode: MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID,
        error: expect.any(Error),
      });
      expect(authenticator.setSuperTokenValidation).not.toHaveBeenCalled();
    });

    it('Given incomplete installments, When executed, Then it aborts as validation_error with no errorCode and never touches the SDK', async () => {
      const paymentMethods = buildPaymentMethods({
        validateInstallmentSelection: jest.fn(() => false),
      });
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({ status: 'validation_error' });
      expect(paymentMethods.updateSecurityCode).not.toHaveBeenCalled();
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
    });
  });

  describe('error classification — update-security-code and authorize errors (including user-cancelled) are recoverable; anything else is unrecoverable', () => {
    it.each([
      MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
      MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
      MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
    ])('Given authorize fails with recoverable %s, When executed, Then it returns recoverable_error and resets validation', async (code) => {
      const paymentMethods = buildPaymentMethods();
      const authenticator = buildAuthenticator({
        authorizePayment: jest.fn(() => Promise.reject(new Error(code))),
      });

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({ status: 'recoverable_error', errorCode: code, error: expect.any(Error) });
      expect(authenticator.setSuperTokenValidation).toHaveBeenLastCalledWith(false);
    });

    it('Given updateSecurityCode fails recoverably, When executed, Then authorize is never reached', async () => {
      const paymentMethods = buildPaymentMethods({
        updateSecurityCode: jest.fn(() =>
          Promise.reject(new Error(MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR)),
        ),
      });
      const authenticator = buildAuthenticator();

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result.status).toBe('recoverable_error');
      expect(authenticator.authorizePayment).not.toHaveBeenCalled();
    });

    it('Given an error outside the recoverable list, When executed, Then it returns fatal_error', async () => {
      const paymentMethods = buildPaymentMethods();
      const authenticator = buildAuthenticator({
        authorizePayment: jest.fn(() => Promise.reject(new Error('SOME_UNKNOWN_ERROR'))),
      });

      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods,
        authenticator,
        isOrderPayPage: false,
      });

      expect(result).toEqual({
        status: 'fatal_error',
        errorCode: 'SOME_UNKNOWN_ERROR',
        error: expect.any(Error),
      });
    });
  });

  describe('missing dependencies', () => {
    it('Given no payment-methods instance, When executed, Then it returns fatal_error (not found)', async () => {
      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods: null,
        authenticator: buildAuthenticator(),
        isOrderPayPage: false,
      });

      expect(result).toEqual({
        status: 'fatal_error',
        errorCode: MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND,
        error: expect.any(Error),
      });
    });

    it('Given no authenticator instance, When executed, Then it returns fatal_error without throwing', async () => {
      const result = await new FinalizeSuperTokenPayment().execute({
        paymentMethods: buildPaymentMethods(),
        authenticator: null,
        isOrderPayPage: false,
      });

      expect(result).toEqual({
        status: 'fatal_error',
        errorCode: MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND,
        error: expect.any(Error),
      });
    });
  });
});

const { BlocksCheckout } = require('@super-token/adapters/checkout/BlocksCheckout');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');

const SUCCESS = 'success';
const ERROR = 'error';

const buildDeps = (result, overrides = {}) => ({
  finalize: { execute: jest.fn(() => Promise.resolve(result)) },
  paymentMethods: {
    getActivePaymentMethod: jest.fn(() => ({ token: 'tok-1', type: 'credit_card' })),
    isSelectedPaymentMethodValid: jest.fn(() => true),
    forceShowValidationErrors: jest.fn(),
    selectLastPaymentMethodChoosen: jest.fn(),
  },
  authenticator: {},
  metrics: { registerClickOnPlaceOrderButton: jest.fn() },
  triggerHandler: { resetSuperTokenOnError: jest.fn(), setLastException: jest.fn() },
  errorHandler: { handleError: jest.fn() },
  emitResponse: { responseTypes: { SUCCESS, ERROR } },
  hasValidationErrors: jest.fn(() => false),
  removeLoader: jest.fn(),
  ...overrides,
});

describe('BlocksCheckout', () => {
  it('Given the place-order click, When finalizing, Then the click metric is always registered first', async () => {
    const deps = buildDeps({ status: 'success' });

    await new BlocksCheckout(deps).finalize();

    expect(deps.metrics.registerClickOnPlaceOrderButton).toHaveBeenCalledTimes(1);
  });

  it('Given other validation errors on the page, When finalizing, Then it re-selects the last method and returns success without running finalization (WC gate blocks placement)', async () => {
    const deps = buildDeps({ status: 'success' }, {
      paymentMethods: {
        getActivePaymentMethod: jest.fn(() => ({ token: 'tok-1', type: 'credit_card' })),
        isSelectedPaymentMethodValid: jest.fn(() => false),
        forceShowValidationErrors: jest.fn(),
        selectLastPaymentMethodChoosen: jest.fn(),
      },
      hasValidationErrors: jest.fn(() => true),
    });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.paymentMethods.forceShowValidationErrors).toHaveBeenCalledTimes(1);
    expect(deps.paymentMethods.selectLastPaymentMethodChoosen).toHaveBeenCalledTimes(1);
    expect(deps.finalize.execute).not.toHaveBeenCalled();
    expect(response).toEqual({ type: SUCCESS });
  });

  it('Given a successful finalization, When applied, Then it returns the success response', async () => {
    const deps = buildDeps({ status: 'success' });

    const response = await new BlocksCheckout(deps).finalize();

    expect(response).toEqual({ type: SUCCESS });
    expect(deps.removeLoader).not.toHaveBeenCalled();
  });

  it('Given an invalid selection, When applied, Then it removes the loader, shows the error, and returns an error response', async () => {
    const deps = buildDeps({
      status: 'validation_error',
      errorCode: MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID,
    });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
    expect(deps.errorHandler.handleError).toHaveBeenCalledTimes(1);
    expect(response).toEqual({ type: ERROR });
  });

  it('Given a recoverable error, When applied, Then it resets keeping the selection and stores the original exception', async () => {
    const originalError = new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR);
    const deps = buildDeps({
      status: 'recoverable_error',
      errorCode: MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
      error: originalError,
    });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
    expect(deps.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
    expect(deps.triggerHandler.setLastException).toHaveBeenCalledWith(originalError);
    expect(response).toEqual({ type: ERROR });
  });

  it('Given an unrecoverable error, When applied, Then it resets discarding the selection', async () => {
    const deps = buildDeps({ status: 'fatal_error', errorCode: 'SOME_UNKNOWN_ERROR' });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(false);
    expect(response).toEqual({ type: ERROR });
  });

  it('Given a pre-finalize step throws (e.g. the validation store is unavailable), When finalizing, Then it clears the loader and returns an error instead of rejecting', async () => {
    const deps = buildDeps({ status: 'success' }, {
      hasValidationErrors: jest.fn(() => {
        throw new TypeError("Cannot read properties of undefined (reading 'hasValidationErrors')");
      }),
    });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
    expect(deps.finalize.execute).not.toHaveBeenCalled();
    expect(response).toEqual({ type: ERROR });
  });

  it('Given the finalization rejects, When finalizing, Then it clears the loader and returns an error instead of rejecting', async () => {
    const deps = buildDeps({ status: 'success' }, {
      finalize: { execute: jest.fn(() => Promise.reject(new Error('unexpected'))) },
    });

    const response = await new BlocksCheckout(deps).finalize();

    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
    expect(response).toEqual({ type: ERROR });
  });
});

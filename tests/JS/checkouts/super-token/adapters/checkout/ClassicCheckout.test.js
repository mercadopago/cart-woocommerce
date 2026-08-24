const { ClassicCheckout } = require('@super-token/adapters/checkout/ClassicCheckout');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');

const buildDeps = (result, overrides = {}) => ({
  finalize: { execute: jest.fn(() => Promise.resolve(result)) },
  paymentMethods: {},
  authenticator: {},
  triggerHandler: { resetSuperTokenOnError: jest.fn(), setLastException: jest.fn() },
  errorHandler: { handleError: jest.fn() },
  isOrderPayPage: jest.fn(() => false),
  markPaymentReady: jest.fn(),
  submitCheckoutForm: jest.fn(),
  submitOrderPayForm: jest.fn(),
  removeLoader: jest.fn(),
  ...overrides,
});

describe('ClassicCheckout', () => {
  it('Given a successful finalization on the standard checkout, When applied, Then it marks the payment ready and submits the checkout form', async () => {
    const deps = buildDeps({ status: 'success' });

    await new ClassicCheckout(deps).finalize();

    expect(deps.markPaymentReady).toHaveBeenCalledTimes(1);
    expect(deps.submitCheckoutForm).toHaveBeenCalledTimes(1);
    expect(deps.submitOrderPayForm).not.toHaveBeenCalled();
    expect(deps.triggerHandler.resetSuperTokenOnError).not.toHaveBeenCalled();
  });

  it('Given a successful finalization on the order-pay page, When applied, Then it submits via the order-pay form (handle3dsPayOrderFormSubmission), not the checkout form', async () => {
    const deps = buildDeps({ status: 'success' }, { isOrderPayPage: jest.fn(() => true) });

    await new ClassicCheckout(deps).finalize();

    expect(deps.finalize.execute).toHaveBeenCalledWith(expect.objectContaining({ isOrderPayPage: true }));
    expect(deps.submitOrderPayForm).toHaveBeenCalledTimes(1);
    expect(deps.submitCheckoutForm).not.toHaveBeenCalled();
  });

  it('Given an invalid selection, When applied, Then it shows the error and removes the loader without submitting', async () => {
    const deps = buildDeps({
      status: 'validation_error',
      errorCode: MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID,
    });

    await new ClassicCheckout(deps).finalize();

    expect(deps.errorHandler.handleError).toHaveBeenCalledTimes(1);
    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
    expect(deps.submitCheckoutForm).not.toHaveBeenCalled();
  });

  it('Given an installment-incomplete abort, When applied, Then it only removes the loader (errors already shown)', async () => {
    const deps = buildDeps({ status: 'validation_error' });

    await new ClassicCheckout(deps).finalize();

    expect(deps.errorHandler.handleError).not.toHaveBeenCalled();
    expect(deps.removeLoader).toHaveBeenCalledTimes(1);
  });

  it('Given a recoverable error, When applied, Then it resets keeping the selection and stores the exception', async () => {
    const deps = buildDeps({
      status: 'recoverable_error',
      errorCode: MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
    });

    await new ClassicCheckout(deps).finalize();

    expect(deps.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
    expect(deps.triggerHandler.setLastException).toHaveBeenCalledWith(
      new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED),
    );
  });

  it('Given an unrecoverable error, When applied, Then it resets discarding the selection', async () => {
    const deps = buildDeps({ status: 'fatal_error', errorCode: 'SOME_UNKNOWN_ERROR' });

    await new ClassicCheckout(deps).finalize();

    expect(deps.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(false);
    expect(deps.triggerHandler.setLastException).toHaveBeenCalledWith(new Error('SOME_UNKNOWN_ERROR'));
  });
});

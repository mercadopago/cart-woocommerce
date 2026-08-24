/**
 * Parity (RN-2 / US-5): given the SAME typed FinalizeResult, the Classic and Blocks adapters
 * must translate it into equivalent semantics — the checkout stays usable in both, the reset
 * decision (recoverable vs. fatal) is the same, and success proceeds to placement in both.
 * Mechanism differs (jQuery submit vs. emitResponse); semantics must not. The canonical
 * sequence + error classification themselves are covered by FinalizeSuperTokenPayment.test.js;
 * here finalize.execute is stubbed to isolate the translation.
 */
const { ClassicCheckout } = require('@super-token/adapters/checkout/ClassicCheckout');
const { BlocksCheckout } = require('@super-token/adapters/checkout/BlocksCheckout');
const { MPSuperTokenErrorCodes } = require('@super-token/core/checkoutSession/ErrorClassification');

const SUCCESS = 'success';
const ERROR = 'error';

const buildClassicDeps = (result) => ({
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
});

const buildBlocksDeps = (result) => ({
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
});

describe('Checkout translation parity (Classic × Blocks translate the same FinalizeResult equivalently)', () => {
  it('Given a success result, When translated, Then both proceed to placement and neither resets', async () => {
    const classic = buildClassicDeps({ status: 'success' });
    const blocks = buildBlocksDeps({ status: 'success' });

    await new ClassicCheckout(classic).finalize();
    const response = await new BlocksCheckout(blocks).finalize();

    expect(classic.markPaymentReady).toHaveBeenCalledTimes(1);
    expect(classic.submitCheckoutForm).toHaveBeenCalledTimes(1);
    expect(classic.triggerHandler.resetSuperTokenOnError).not.toHaveBeenCalled();

    expect(response).toEqual({ type: SUCCESS });
    expect(blocks.triggerHandler.resetSuperTokenOnError).not.toHaveBeenCalled();
    expect(blocks.removeLoader).not.toHaveBeenCalled();
  });

  it('Given an invalid-selection validation error, When translated, Then both show the error and drop the loader without placing the order', async () => {
    const errorCode = MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID;
    const classic = buildClassicDeps({ status: 'validation_error', errorCode });
    const blocks = buildBlocksDeps({ status: 'validation_error', errorCode });

    await new ClassicCheckout(classic).finalize();
    const response = await new BlocksCheckout(blocks).finalize();

    expect(classic.errorHandler.handleError).toHaveBeenCalledTimes(1);
    expect(classic.removeLoader).toHaveBeenCalledTimes(1);
    expect(classic.submitCheckoutForm).not.toHaveBeenCalled();

    expect(blocks.errorHandler.handleError).toHaveBeenCalledTimes(1);
    expect(blocks.removeLoader).toHaveBeenCalledTimes(1);
    expect(response).toEqual({ type: ERROR });
  });

  it('Given a recoverable error, When translated, Then both reset keeping the selection (checkout stays usable)', async () => {
    const errorCode = MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED;
    const classic = buildClassicDeps({ status: 'recoverable_error', errorCode });
    const blocks = buildBlocksDeps({ status: 'recoverable_error', errorCode });

    await new ClassicCheckout(classic).finalize();
    const response = await new BlocksCheckout(blocks).finalize();

    expect(classic.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
    expect(blocks.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
    expect(classic.submitCheckoutForm).not.toHaveBeenCalled();
    expect(response).toEqual({ type: ERROR });
  });

  it('Given a fatal error, When translated, Then both reset discarding the selection', async () => {
    const classic = buildClassicDeps({ status: 'fatal_error', errorCode: 'SOME_UNKNOWN_ERROR' });
    const blocks = buildBlocksDeps({ status: 'fatal_error', errorCode: 'SOME_UNKNOWN_ERROR' });

    await new ClassicCheckout(classic).finalize();
    const response = await new BlocksCheckout(blocks).finalize();

    expect(classic.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(false);
    expect(blocks.triggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(false);
    expect(response).toEqual({ type: ERROR });
  });
});

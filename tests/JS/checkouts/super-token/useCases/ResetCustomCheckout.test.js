const { ResetCustomCheckout } = require('@super-token/useCases/ResetCustomCheckout');

const buildSession = (overrides = {}) => ({
  hideSuperTokenError: jest.fn(),
  reportCustomHandlerMissingOnReset: jest.fn(),
  createLoadSpinner: jest.fn(),
  setSuperTokenValidation: jest.fn(),
  hasStoredPaymentMethods: jest.fn(() => false),
  remountCardForm: jest.fn(),
  resetFlow: jest.fn(),
  currentAmount: jest.fn(() => '100.00'),
  loadSuperToken: jest.fn(() => Promise.resolve()),
  finalizeResetTail: jest.fn(),
  ...overrides,
});

const run = (session, shouldClearCache = true) =>
  new ResetCustomCheckout().execute({ session, shouldClearCache });

const flushMicrotasks = () => Promise.resolve();

describe('ResetCustomCheckout', () => {
  it('Given the reset, When executed, Then it hides the error, reports a missing handler, raises the spinner, invalidates the token and loads with the current amount, in order', () => {
    const calls = [];
    const session = buildSession({
      hideSuperTokenError: jest.fn(() => calls.push('hideError')),
      reportCustomHandlerMissingOnReset: jest.fn(() => calls.push('reportMissing')),
      createLoadSpinner: jest.fn(() => calls.push('spinner')),
      setSuperTokenValidation: jest.fn(() => calls.push('invalidate')),
      resetFlow: jest.fn(() => calls.push('resetFlow')),
      loadSuperToken: jest.fn(() => {
        calls.push('load');
        return Promise.resolve();
      }),
    });

    run(session);

    expect(calls).toEqual(['hideError', 'reportMissing', 'spinner', 'invalidate', 'resetFlow', 'load']);
    expect(session.setSuperTokenValidation).toHaveBeenCalledWith(false);
    expect(session.loadSuperToken).toHaveBeenCalledWith('100.00');
  });

  it('Given stored payment methods, When resetting, Then it remounts the card form', () => {
    const session = buildSession({ hasStoredPaymentMethods: jest.fn(() => true) });

    run(session);

    expect(session.remountCardForm).toHaveBeenCalledTimes(1);
  });

  it('Given no stored payment methods, When resetting, Then it does not remount the card form', () => {
    const session = buildSession({ hasStoredPaymentMethods: jest.fn(() => false) });

    run(session);

    expect(session.remountCardForm).not.toHaveBeenCalled();
  });

  it('Given shouldClearCache is false, When resetting, Then it does not reset the flow', () => {
    const session = buildSession();

    run(session, false);

    expect(session.resetFlow).not.toHaveBeenCalled();
    expect(session.loadSuperToken).toHaveBeenCalledWith('100.00');
  });

  it('Given the load resolves, When resetting, Then it runs the async tail once the load settles', async () => {
    const session = buildSession();

    run(session);

    expect(session.finalizeResetTail).not.toHaveBeenCalled();
    await flushMicrotasks();
    expect(session.finalizeResetTail).toHaveBeenCalledTimes(1);
  });
});

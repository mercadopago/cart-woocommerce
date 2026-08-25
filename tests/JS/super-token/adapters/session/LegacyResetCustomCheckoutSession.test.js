const {
  LegacyResetCustomCheckoutSession,
} = require('@super-token/adapters/session/LegacyResetCustomCheckoutSession');

const buildController = (overrides = {}) => ({
  hideSuperTokenError: jest.fn(),
  hasStoredPaymentMethods: jest.fn(() => false),
  unmountCardForm: jest.fn(),
  mountCardForm: jest.fn(),
  ...overrides,
});

const buildTriggerHandler = (controller, overrides = {}) => ({
  currentAmount: '100.00',
  customHandlerMissingReportedOnReset: false,
  mpSuperTokenPaymentMethods: controller,
  mpSuperTokenAuthenticator: { setSuperTokenValidation: jest.fn() },
  resetFlow: jest.fn(),
  loadSuperToken: jest.fn(() => Promise.resolve()),
  finalizeResetTail: jest.fn(),
  ...overrides,
});

afterEach(() => {
  delete window.mpCustomCheckoutHandler;
  delete window.sendMetric;
});

describe('LegacyResetCustomCheckoutSession', () => {
  it('Given the reset primitives, When run, Then it forwards to the controller and the trigger handler', () => {
    const controller = buildController({ hasStoredPaymentMethods: jest.fn(() => true) });
    const triggerHandler = buildTriggerHandler(controller);
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.hideSuperTokenError();
    session.setSuperTokenValidation(false);
    session.remountCardForm();
    session.resetFlow();

    expect(controller.hideSuperTokenError).toHaveBeenCalledTimes(1);
    expect(triggerHandler.mpSuperTokenAuthenticator.setSuperTokenValidation).toHaveBeenCalledWith(false);
    expect(controller.unmountCardForm).toHaveBeenCalledTimes(1);
    expect(controller.mountCardForm).toHaveBeenCalledTimes(1);
    expect(triggerHandler.resetFlow).toHaveBeenCalledTimes(1);
    expect(session.hasStoredPaymentMethods()).toBe(true);
    expect(session.currentAmount()).toBe('100.00');
  });

  it('Given the load orchestration and tail, When driven, Then it forwards to the trigger handler', () => {
    const triggerHandler = buildTriggerHandler(buildController());
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.loadSuperToken('200.00');
    session.finalizeResetTail();

    expect(triggerHandler.loadSuperToken).toHaveBeenCalledWith('200.00');
    expect(triggerHandler.finalizeResetTail).toHaveBeenCalledTimes(1);
  });

  it('Given the custom handler is present, When reporting the missing handler, Then it sends no metric', () => {
    window.mpCustomCheckoutHandler = { cardForm: { createLoadSpinner: jest.fn() } };
    window.sendMetric = jest.fn();
    const triggerHandler = buildTriggerHandler(buildController());
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.reportCustomHandlerMissingOnReset();

    expect(window.sendMetric).not.toHaveBeenCalled();
    expect(triggerHandler.customHandlerMissingReportedOnReset).toBe(false);
  });

  it('Given the custom handler is absent and it was not yet reported, When reporting, Then it sends the metric once and flags it', () => {
    window.sendMetric = jest.fn();
    const triggerHandler = buildTriggerHandler(buildController());
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.reportCustomHandlerMissingOnReset();

    expect(window.sendMetric).toHaveBeenCalledWith(
      'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
      'resetCustomCheckout',
      'mp_super_token_init_error',
    );
    expect(triggerHandler.customHandlerMissingReportedOnReset).toBe(true);
  });

  it('Given the missing handler was already reported, When reporting again, Then it sends no metric', () => {
    window.sendMetric = jest.fn();
    const triggerHandler = buildTriggerHandler(buildController(), {
      customHandlerMissingReportedOnReset: true,
    });
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.reportCustomHandlerMissingOnReset();

    expect(window.sendMetric).not.toHaveBeenCalled();
  });

  it('Given sendMetric is unavailable, When reporting the missing handler, Then it does not flag it', () => {
    const triggerHandler = buildTriggerHandler(buildController());
    const session = new LegacyResetCustomCheckoutSession(triggerHandler);

    session.reportCustomHandlerMissingOnReset();

    expect(triggerHandler.customHandlerMissingReportedOnReset).toBe(false);
  });

  it('Given the custom handler exposes a card form, When raising the spinner, Then it calls createLoadSpinner', () => {
    const createLoadSpinner = jest.fn();
    window.mpCustomCheckoutHandler = { cardForm: { createLoadSpinner } };
    const session = new LegacyResetCustomCheckoutSession(buildTriggerHandler(buildController()));

    session.createLoadSpinner();

    expect(createLoadSpinner).toHaveBeenCalledTimes(1);
  });

  it('Given no custom handler, When raising the spinner, Then it does not throw', () => {
    const session = new LegacyResetCustomCheckoutSession(buildTriggerHandler(buildController()));

    expect(() => session.createLoadSpinner()).not.toThrow();
  });
});

const { LegacyResetSession } = require('@super-token/adapters/session/LegacyResetSession');

const buildController = (overrides = {}) => ({
  SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: 'mp-super-token-list' },
  getLastPaymentMethodChoosen: jest.fn(() => null),
  deselectAllPaymentMethods: jest.fn(),
  hideAllPaymentMethodDetails: jest.fn(),
  unmountActiveSecurityCodeInstance: jest.fn(),
  clearActivePaymentMethod: jest.fn(),
  storeSelectedPreloadedPaymentMethod: jest.fn(),
  ...overrides,
});

const buildTriggerHandler = (controller) => ({
  savedInstallments: 'stale',
  resetCustomCheckout: jest.fn(),
  mpSuperTokenPaymentMethods: controller,
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('LegacyResetSession', () => {
  it('Given the checkout type input reads super_token, When queried, Then it reports the flow active', () => {
    document.body.innerHTML = '<input id="mp_checkout_type" value="super_token" />';
    const session = new LegacyResetSession(buildTriggerHandler(buildController()));

    expect(session.isSuperTokenCheckoutActive()).toBe(true);
  });

  it('Given another checkout type, When queried, Then it reports the flow inactive', () => {
    document.body.innerHTML = '<input id="mp_checkout_type" value="custom" />';
    const session = new LegacyResetSession(buildTriggerHandler(buildController()));

    expect(session.isSuperTokenCheckoutActive()).toBe(false);
  });

  it('Given a rendered list, When scrolling into view, Then it scrolls the controller-named list element', () => {
    document.body.innerHTML = '<div class="mp-super-token-list"></div>';
    const list = document.querySelector('.mp-super-token-list');
    list.scrollIntoView = jest.fn();
    const session = new LegacyResetSession(buildTriggerHandler(buildController()));

    session.scrollPaymentMethodListIntoView();

    expect(list.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth' });
  });

  it('Given the installments input, When read, Then it returns its value or null when empty', () => {
    const session = new LegacyResetSession(buildTriggerHandler(buildController()));

    document.body.innerHTML = '<input id="cardInstallments" value="3" />';
    expect(session.getSelectedInstallments()).toBe('3');

    document.body.innerHTML = '<input id="cardInstallments" value="" />';
    expect(session.getSelectedInstallments()).toBeNull();

    document.body.innerHTML = '';
    expect(session.getSelectedInstallments()).toBeNull();
  });

  it('Given a value, When storing the saved installments, Then it writes the trigger handler field', () => {
    const triggerHandler = buildTriggerHandler(buildController());
    const session = new LegacyResetSession(triggerHandler);

    session.storeSavedInstallments('3');
    expect(triggerHandler.savedInstallments).toBe('3');

    session.storeSavedInstallments(null);
    expect(triggerHandler.savedInstallments).toBeNull();
  });

  it('Given reset primitives, When run, Then it forwards to the controller and the trigger handler', () => {
    const method = { token: 'tok-1', type: 'credit_card' };
    const controller = buildController({ getLastPaymentMethodChoosen: jest.fn(() => method) });
    const triggerHandler = buildTriggerHandler(controller);
    const session = new LegacyResetSession(triggerHandler);

    expect(session.getLastPaymentMethodChoosen()).toBe(method);
    session.deselectAllPaymentMethods();
    session.hideAllPaymentMethodDetails();
    session.unmountActiveSecurityCodeInstance();
    session.clearActivePaymentMethod();
    session.resetCustomCheckout(true);
    session.storeSelectedPreloadedPaymentMethod(method);

    expect(controller.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
    expect(controller.hideAllPaymentMethodDetails).toHaveBeenCalledTimes(1);
    expect(controller.unmountActiveSecurityCodeInstance).toHaveBeenCalledTimes(1);
    expect(controller.clearActivePaymentMethod).toHaveBeenCalledTimes(1);
    expect(triggerHandler.resetCustomCheckout).toHaveBeenCalledWith(true);
    expect(controller.storeSelectedPreloadedPaymentMethod).toHaveBeenCalledWith(method);
  });
});

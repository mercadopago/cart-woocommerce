const { LegacyRestoreSession } = require('@super-token/adapters/session/LegacyRestoreSession');

const CARD = { token: 'tok-1', type: 'credit_card', id: 'card-1' };

const buildController = (overrides = {}) => ({
  getSelectedPreloadedPaymentMethod: jest.fn(() => null),
  hasCheckoutError: jest.fn(() => false),
  selectLastPaymentMethodChoosen: jest.fn(),
  selectPreloadedPaymentMethod: jest.fn(() => Promise.resolve()),
  storeSelectedPreloadedPaymentMethod: jest.fn(),
  getActivePaymentMethod: jest.fn(() => null),
  getPaymentMethodElementFromDOM: jest.fn(() => null),
  showPaymentMethodDetails: jest.fn(),
  paymentMethodIdentifier: jest.fn(() => 'card-1'),
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  sendMetric: jest.fn(),
  ...overrides,
});

const buildTriggerHandler = (controller, overrides = {}) => ({
  savedInstallments: null,
  mpSuperTokenPaymentMethods: controller,
  ...overrides,
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('LegacyRestoreSession', () => {
  it('Given the payment-methods controller, When reading the preloaded method, Then it forwards to getSelectedPreloadedPaymentMethod', () => {
    const controller = buildController({
      getSelectedPreloadedPaymentMethod: jest.fn(() => CARD),
    });
    const session = new LegacyRestoreSession(buildTriggerHandler(controller), buildMetrics());

    expect(session.getPreloadedPaymentMethod()).toBe(CARD);
  });

  it('Given a checkout error, When queried, Then it forwards to hasCheckoutError', () => {
    const controller = buildController({ hasCheckoutError: jest.fn(() => true) });
    const session = new LegacyRestoreSession(buildTriggerHandler(controller), buildMetrics());

    expect(session.hasCheckoutError()).toBe(true);
  });

  it('When selecting the last chosen method, Then it forwards to the controller', () => {
    const controller = buildController();
    const session = new LegacyRestoreSession(buildTriggerHandler(controller), buildMetrics());

    session.selectLastChosenMethod();

    expect(controller.selectLastPaymentMethodChoosen).toHaveBeenCalledTimes(1);
  });

  it('When clearing the preloaded method, Then it stores null in the controller', () => {
    const controller = buildController();
    const session = new LegacyRestoreSession(buildTriggerHandler(controller), buildMetrics());

    session.clearPreloadedMethod();

    expect(controller.storeSelectedPreloadedPaymentMethod).toHaveBeenCalledWith(null);
  });

  it('Given a savedInstallments value, When read and cleared, Then it reads from and writes to the trigger handler field', () => {
    const controller = buildController();
    const triggerHandler = buildTriggerHandler(controller, { savedInstallments: '3' });
    const session = new LegacyRestoreSession(triggerHandler, buildMetrics());

    expect(session.getSavedInstallments()).toBe('3');
    session.clearSavedInstallments();
    expect(triggerHandler.savedInstallments).toBeNull();
  });

  it('When reporting a restore metric, Then it calls sendMetric with the fixed value and message', () => {
    const metrics = buildMetrics();
    const session = new LegacyRestoreSession(buildTriggerHandler(buildController()), metrics);

    session.reportRestoreMetric('super_token_restore_element_not_found');

    expect(metrics.sendMetric).toHaveBeenCalledWith(
      'super_token_restore_element_not_found',
      'true',
      'mp_super_token_restore_error',
    );
  });

  it('Given a rendered element, When looking up the installments dropdown, Then it queries by the controller-derived id', () => {
    document.body.innerHTML = `
      <div id="container">
        <select id="mp-super-token-installments-select-card-1">
          <option value="1">1x</option>
          <option value="3">3x</option>
        </select>
      </div>`;
    const container = document.getElementById('container');
    const controller = buildController({ paymentMethodIdentifier: jest.fn(() => 'card-1') });
    const session = new LegacyRestoreSession(buildTriggerHandler(controller), buildMetrics());

    const dropdown = session.getInstallmentsDropdown(CARD, container);

    expect(dropdown).not.toBeNull();
    expect(dropdown.id).toBe('mp-super-token-installments-select-card-1');
  });

  it('Given a dropdown with options, When checking option existence, Then it returns true for present values and false for absent', () => {
    document.body.innerHTML = `<select><option value="3">3x</option></select>`;
    const dropdown = document.querySelector('select');
    const session = new LegacyRestoreSession(buildTriggerHandler(buildController()), buildMetrics());

    expect(session.hasInstallmentOption(dropdown, '3')).toBe(true);
    expect(session.hasInstallmentOption(dropdown, '6')).toBe(false);
  });

  it('Given saved installments, When applying the selection, Then it sets the dropdown value, syncs the card input, and dispatches change', () => {
    document.body.innerHTML = `
      <select><option value="3">3x</option><option value="6">6x</option></select>
      <input id="cardInstallments" />`;
    const dropdown = document.querySelector('select');
    const changeHandler = jest.fn();
    dropdown.addEventListener('change', changeHandler);
    const session = new LegacyRestoreSession(buildTriggerHandler(buildController()), buildMetrics());

    session.applyInstallmentsSelection(dropdown, '6');

    expect(dropdown.value).toBe('6');
    expect(document.getElementById('cardInstallments').value).toBe('6');
    expect(changeHandler).toHaveBeenCalledTimes(1);
  });

  it('Given no cardInstallments input, When applying the selection, Then it sets the dropdown value without throwing', () => {
    document.body.innerHTML = `<select><option value="3">3x</option></select>`;
    const dropdown = document.querySelector('select');
    const session = new LegacyRestoreSession(buildTriggerHandler(buildController()), buildMetrics());

    expect(() => session.applyInstallmentsSelection(dropdown, '3')).not.toThrow();
    expect(dropdown.value).toBe('3');
  });
});

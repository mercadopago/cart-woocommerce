const {
  RestorePreloadedPaymentMethod,
} = require('@super-token/useCases/RestorePreloadedPaymentMethod');

const CARD = { token: 'tok-1', type: 'credit_card' };
const ELEMENT = { id: 'method-el' };
const DROPDOWN = { options: [], value: '' };

const buildSession = (overrides = {}) => ({
  getPreloadedPaymentMethod: jest.fn(() => CARD),
  hasCheckoutError: jest.fn(() => false),
  selectLastChosenMethod: jest.fn(),
  selectPreloadedMethod: jest.fn(() => Promise.resolve()),
  clearPreloadedMethod: jest.fn(),
  getActiveMethod: jest.fn(() => CARD),
  getSavedInstallments: jest.fn(() => null),
  clearSavedInstallments: jest.fn(),
  reportRestoreMetric: jest.fn(),
  getMethodElement: jest.fn(() => ELEMENT),
  showMethodDetails: jest.fn(),
  getInstallmentsDropdown: jest.fn(() => DROPDOWN),
  hasInstallmentOption: jest.fn(() => true),
  applyInstallmentsSelection: jest.fn(),
  ...overrides,
});

const run = (session) => new RestorePreloadedPaymentMethod().execute({ session });

describe('RestorePreloadedPaymentMethod', () => {
  it('Given no preloaded method and no checkout error, When restoring, Then it returns early without any selection', async () => {
    const session = buildSession({
      getPreloadedPaymentMethod: jest.fn(() => null),
    });

    await run(session);

    expect(session.selectPreloadedMethod).not.toHaveBeenCalled();
    expect(session.selectLastChosenMethod).not.toHaveBeenCalled();
  });

  it('Given no preloaded method and a checkout error, When restoring, Then it selects the last chosen method', async () => {
    const session = buildSession({
      getPreloadedPaymentMethod: jest.fn(() => null),
      hasCheckoutError: jest.fn(() => true),
    });

    await run(session);

    expect(session.selectLastChosenMethod).toHaveBeenCalledTimes(1);
    expect(session.selectPreloadedMethod).not.toHaveBeenCalled();
  });

  it('Given a preloaded method and no active method after selection, When restoring, Then it reports the active-method-not-set metric', async () => {
    const session = buildSession({ getActiveMethod: jest.fn(() => null) });

    await run(session);

    expect(session.reportRestoreMetric).toHaveBeenCalledWith(
      'super_token_restore_active_method_not_set',
    );
    expect(session.showMethodDetails).not.toHaveBeenCalled();
  });

  it('Given a preloaded method with no DOM element, When restoring, Then it reports the element-not-found metric', async () => {
    const session = buildSession({ getMethodElement: jest.fn(() => null) });

    await run(session);

    expect(session.reportRestoreMetric).toHaveBeenCalledWith(
      'super_token_restore_element_not_found',
    );
    expect(session.showMethodDetails).not.toHaveBeenCalled();
  });

  it('Given a preloaded method with no saved installments, When restoring, Then it shows details and returns without touching the dropdown', async () => {
    const session = buildSession({ getSavedInstallments: jest.fn(() => null) });

    await run(session);

    expect(session.showMethodDetails).toHaveBeenCalledWith(ELEMENT);
    expect(session.getInstallmentsDropdown).not.toHaveBeenCalled();
  });

  it('Given saved installments but no dropdown, When restoring, Then it reports the dropdown-not-found metric', async () => {
    const session = buildSession({
      getSavedInstallments: jest.fn(() => '3'),
      getInstallmentsDropdown: jest.fn(() => null),
    });

    await run(session);

    expect(session.reportRestoreMetric).toHaveBeenCalledWith(
      'super_token_restore_installments_dropdown_not_found',
    );
    expect(session.applyInstallmentsSelection).not.toHaveBeenCalled();
  });

  it('Given saved installments but the option no longer exists, When restoring, Then it reports the option-not-found metric', async () => {
    const session = buildSession({
      getSavedInstallments: jest.fn(() => '6'),
      hasInstallmentOption: jest.fn(() => false),
    });

    await run(session);

    expect(session.reportRestoreMetric).toHaveBeenCalledWith(
      'super_token_restore_installment_option_not_found',
    );
    expect(session.applyInstallmentsSelection).not.toHaveBeenCalled();
  });

  it('Given a preloaded method with saved installments, When restoring, Then it applies the selection', async () => {
    const session = buildSession({ getSavedInstallments: jest.fn(() => '3') });

    await run(session);

    expect(session.selectPreloadedMethod).toHaveBeenCalledTimes(1);
    expect(session.clearPreloadedMethod).toHaveBeenCalledTimes(1);
    expect(session.clearSavedInstallments).toHaveBeenCalledTimes(1);
    expect(session.showMethodDetails).toHaveBeenCalledWith(ELEMENT);
    expect(session.applyInstallmentsSelection).toHaveBeenCalledWith(DROPDOWN, '3');
  });

  it('Given a preloaded method, When restoring, Then it clears saved installments regardless of whether they were used', async () => {
    const session = buildSession({ getSavedInstallments: jest.fn(() => null) });

    await run(session);

    expect(session.clearSavedInstallments).toHaveBeenCalledTimes(1);
  });
});

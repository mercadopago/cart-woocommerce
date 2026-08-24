const { SelectSavedPaymentMethod } = require('@super-token/useCases/SelectSavedPaymentMethod');

const buildSession = (overrides = {}) => ({
  paymentMethodAlreadySelected: jest.fn(() => false),
  emitEventFromSelectPaymentMethod: jest.fn(),
  storeActivePaymentMethod: jest.fn(),
  hideAllPaymentMethodDetails: jest.fn(),
  closeAccordion: jest.fn(),
  deselectAllPaymentMethods: jest.fn(),
  selectPaymentMethod: jest.fn(),
  fillCardTokenFields: jest.fn(),
  setCheckoutTypeToSuperToken: jest.fn(),
  showPaymentMethodDetails: jest.fn(),
  handleInstallmentsWithoutFeePillVisibility: jest.fn(),
  handleWithEscPaymentMethod: jest.fn((pm) => Promise.resolve(pm)),
  mountSecurityCodeField: jest.fn(),
  notifySelectionSettled: jest.fn(),
  ...overrides,
});

const buildMetrics = () => ({
  sendMetric: jest.fn(),
  registerSelectPaymentMethod: jest.fn(),
});

const paymentMethod = { token: 'tok-1', type: 'credit_card' };
const paymentMethodElement = {};

describe('SelectSavedPaymentMethod', () => {
  it('Given a method that is already selected, When executed, Then it does nothing', async () => {
    const session = buildSession({ paymentMethodAlreadySelected: jest.fn(() => true) });
    const metrics = buildMetrics();

    await new SelectSavedPaymentMethod().execute({ session, metrics, paymentMethod, paymentMethodElement });

    expect(metrics.registerSelectPaymentMethod).not.toHaveBeenCalled();
    expect(session.storeActivePaymentMethod).not.toHaveBeenCalled();
    expect(session.notifySelectionSettled).not.toHaveBeenCalled();
  });

  it('Given a new selection, When executed, Then it registers the metric, stores the method as active, and selects it in the UI', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await new SelectSavedPaymentMethod().execute({ session, metrics, paymentMethod, paymentMethodElement });

    expect(metrics.registerSelectPaymentMethod).toHaveBeenCalledWith('credit_card');
    expect(session.storeActivePaymentMethod).toHaveBeenCalledWith(paymentMethod);
    expect(session.selectPaymentMethod).toHaveBeenCalledWith(paymentMethodElement);
    expect(session.setCheckoutTypeToSuperToken).toHaveBeenCalledTimes(1);
    expect(session.notifySelectionSettled).toHaveBeenCalledTimes(1);
  });

  it('Given the ESC verification returns a method, When executed, Then it mounts the security-code field for that method', async () => {
    const verified = { token: 'tok-1', type: 'credit_card', verified: true };
    const session = buildSession({ handleWithEscPaymentMethod: jest.fn(() => Promise.resolve(verified)) });
    const metrics = buildMetrics();

    await new SelectSavedPaymentMethod().execute({ session, metrics, paymentMethod, paymentMethodElement });

    expect(session.mountSecurityCodeField).toHaveBeenCalledWith(verified);
  });

  it('Given the ESC verification returns null, When executed, Then it does not mount the security-code field', async () => {
    const session = buildSession({ handleWithEscPaymentMethod: jest.fn(() => Promise.resolve(null)) });
    const metrics = buildMetrics();

    await new SelectSavedPaymentMethod().execute({ session, metrics, paymentMethod, paymentMethodElement });

    expect(session.mountSecurityCodeField).not.toHaveBeenCalled();
    expect(session.notifySelectionSettled).toHaveBeenCalledTimes(1);
  });
});

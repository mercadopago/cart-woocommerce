const { LegacySelectionSession } = require('@super-token/adapters/session/LegacySelectionSession');

const buildLegacy = (overrides = {}) => ({
  SUPER_TOKEN_CHECKOUT_TYPE: 'super_token',
  paymentMethodAlreadySelected: jest.fn(() => false),
  emitEventFromSelectPaymentMethod: jest.fn(),
  storeActivePaymentMethod: jest.fn(),
  hideAllPaymentMethodDetails: jest.fn(),
  closeAccordion: jest.fn(),
  deselectAllPaymentMethods: jest.fn(),
  selectPaymentMethod: jest.fn(),
  fillCardTokenFields: jest.fn(),
  setCheckoutType: jest.fn(),
  showPaymentMethodDetails: jest.fn(),
  handleInstallmentsWithoutFeePillVisibility: jest.fn(),
  handleWithEscPaymentMethod: jest.fn(() => Promise.resolve(null)),
  mountSecurityCodeField: jest.fn(),
  selectedSupertokenMethodEvent: jest.fn((isNewCard) => ({ isNewCard })),
  ...overrides,
});

const paymentMethod = { token: 'tok-1', type: 'credit_card' };
const paymentMethodElement = {};

describe('LegacySelectionSession', () => {
  it('Given a payment method, When each pass-through primitive runs, Then it forwards to the matching legacy method', async () => {
    const legacy = buildLegacy();
    const session = new LegacySelectionSession(legacy);

    session.paymentMethodAlreadySelected(paymentMethod);
    session.emitEventFromSelectPaymentMethod(paymentMethod);
    session.storeActivePaymentMethod(paymentMethod);
    session.hideAllPaymentMethodDetails();
    session.closeAccordion();
    session.deselectAllPaymentMethods();
    session.selectPaymentMethod(paymentMethodElement);
    session.fillCardTokenFields(paymentMethod);
    session.showPaymentMethodDetails(paymentMethodElement);
    session.handleInstallmentsWithoutFeePillVisibility();
    await session.handleWithEscPaymentMethod(paymentMethod, paymentMethodElement);
    session.mountSecurityCodeField(paymentMethod);

    expect(legacy.paymentMethodAlreadySelected).toHaveBeenCalledWith(paymentMethod);
    expect(legacy.emitEventFromSelectPaymentMethod).toHaveBeenCalledWith(paymentMethod);
    expect(legacy.storeActivePaymentMethod).toHaveBeenCalledWith(paymentMethod);
    expect(legacy.hideAllPaymentMethodDetails).toHaveBeenCalledTimes(1);
    expect(legacy.closeAccordion).toHaveBeenCalledTimes(1);
    expect(legacy.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
    expect(legacy.selectPaymentMethod).toHaveBeenCalledWith(paymentMethodElement);
    expect(legacy.fillCardTokenFields).toHaveBeenCalledWith(paymentMethod);
    expect(legacy.showPaymentMethodDetails).toHaveBeenCalledWith(paymentMethodElement);
    expect(legacy.handleInstallmentsWithoutFeePillVisibility).toHaveBeenCalledTimes(1);
    expect(legacy.handleWithEscPaymentMethod).toHaveBeenCalledWith(paymentMethod, paymentMethodElement);
    expect(legacy.mountSecurityCodeField).toHaveBeenCalledWith(paymentMethod);
  });

  it('Given the legacy already-selected guard, When queried, Then it returns the legacy boolean', () => {
    const legacy = buildLegacy({ paymentMethodAlreadySelected: jest.fn(() => true) });

    expect(new LegacySelectionSession(legacy).paymentMethodAlreadySelected(paymentMethod)).toBe(true);
  });

  it('Given the ESC verification, When handled, Then it returns the legacy result promise', async () => {
    const verified = { token: 'tok-1', type: 'credit_card', verified: true };
    const legacy = buildLegacy({ handleWithEscPaymentMethod: jest.fn(() => Promise.resolve(verified)) });

    await expect(
      new LegacySelectionSession(legacy).handleWithEscPaymentMethod(paymentMethod, paymentMethodElement),
    ).resolves.toBe(verified);
  });

  it('Given no 1:1 legacy method, When setCheckoutTypeToSuperToken runs, Then it calls setCheckoutType with SUPER_TOKEN_CHECKOUT_TYPE', () => {
    const legacy = buildLegacy();

    new LegacySelectionSession(legacy).setCheckoutTypeToSuperToken();

    expect(legacy.setCheckoutType).toHaveBeenCalledWith('super_token');
  });

  describe('notifySelectionSettled', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('Given a settled selection, When notified, Then after 50ms it dispatches the legacy settled event', () => {
      const legacy = buildLegacy();
      const settledEvent = { settled: true };
      legacy.selectedSupertokenMethodEvent = jest.fn(() => settledEvent);
      const dispatchSpy = jest.spyOn(document, 'dispatchEvent').mockImplementation(() => true);

      new LegacySelectionSession(legacy).notifySelectionSettled();

      expect(dispatchSpy).not.toHaveBeenCalled();
      jest.advanceTimersByTime(50);
      expect(legacy.selectedSupertokenMethodEvent).toHaveBeenCalledWith(false);
      expect(dispatchSpy).toHaveBeenCalledWith(settledEvent);

      dispatchSpy.mockRestore();
    });
  });
});

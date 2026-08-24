const { ResetFlow } = require('@super-token/useCases/ResetFlow');

const buildSession = (overrides = {}) => ({
  isSuperTokenCheckoutActive: jest.fn(() => true),
  scrollPaymentMethodListIntoView: jest.fn(),
  getLastPaymentMethodChoosen: jest.fn(() => null),
  getSelectedInstallments: jest.fn(() => null),
  storeSavedInstallments: jest.fn(),
  deselectAllPaymentMethods: jest.fn(),
  hideAllPaymentMethodDetails: jest.fn(),
  unmountActiveSecurityCodeInstance: jest.fn(),
  clearActivePaymentMethod: jest.fn(),
  resetCustomCheckout: jest.fn(),
  storeSelectedPreloadedPaymentMethod: jest.fn(),
  ...overrides,
});

describe('ResetFlow', () => {
  it('Given the checkout is not on Super Token, When executed, Then it does nothing', () => {
    const session = buildSession({ isSuperTokenCheckoutActive: jest.fn(() => false) });

    new ResetFlow().execute({ session, preserveSelection: false });

    expect(session.deselectAllPaymentMethods).not.toHaveBeenCalled();
    expect(session.resetCustomCheckout).not.toHaveBeenCalled();
  });

  it('Given an unrecoverable reset (no selection preserved), When executed, Then it clears the flow and preserves nothing', () => {
    const session = buildSession();

    new ResetFlow().execute({ session, preserveSelection: false });

    expect(session.storeSavedInstallments).toHaveBeenCalledWith(null);
    expect(session.deselectAllPaymentMethods).toHaveBeenCalledTimes(1);
    expect(session.clearActivePaymentMethod).toHaveBeenCalledTimes(1);
    expect(session.resetCustomCheckout).toHaveBeenCalledWith(true);
    expect(session.getLastPaymentMethodChoosen).not.toHaveBeenCalled();
    expect(session.storeSelectedPreloadedPaymentMethod).not.toHaveBeenCalled();
  });

  it('Given a recoverable reset with a previous selection, When executed, Then it preserves the last method and its installments', () => {
    const lastMethod = { token: 'tok-1', type: 'credit_card' };
    const session = buildSession({
      getLastPaymentMethodChoosen: jest.fn(() => lastMethod),
      getSelectedInstallments: jest.fn(() => '3'),
    });

    new ResetFlow().execute({ session, preserveSelection: true });

    expect(session.storeSavedInstallments).toHaveBeenNthCalledWith(1, null);
    expect(session.storeSavedInstallments).toHaveBeenNthCalledWith(2, '3');
    expect(session.storeSelectedPreloadedPaymentMethod).toHaveBeenCalledWith(lastMethod);
  });

  it('Given a recoverable reset but no previous selection, When executed, Then it preserves nothing', () => {
    const session = buildSession({ getLastPaymentMethodChoosen: jest.fn(() => null) });

    new ResetFlow().execute({ session, preserveSelection: true });

    expect(session.storeSelectedPreloadedPaymentMethod).not.toHaveBeenCalled();
  });
});

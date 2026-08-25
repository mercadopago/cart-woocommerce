const { LegacyRenderSession } = require('@super-token/adapters/session/LegacyRenderSession');

const paymentMethod = { id: 'visa', type: 'credit_card' };

describe('LegacyRenderSession', () => {
  it('Given a payment method, When buildRow runs, Then it forwards to the legacy createPaymentMethodElement and returns its element', () => {
    const legacyRow = document.createElement('article');
    const legacy = { createPaymentMethodElement: jest.fn(() => legacyRow) };
    const session = new LegacyRenderSession(legacy);

    const row = session.buildRow(paymentMethod);

    expect(legacy.createPaymentMethodElement).toHaveBeenCalledWith(paymentMethod);
    expect(row).toBe(legacyRow);
  });

  it('Given a tree-built row, When onSelectPaymentMethod runs, Then it forwards to the legacy onSelectSuperTokenPaymentMethod', () => {
    const row = document.createElement('article');
    const legacy = { onSelectSuperTokenPaymentMethod: jest.fn(() => Promise.resolve()) };
    const session = new LegacyRenderSession(legacy);

    session.onSelectPaymentMethod(row, paymentMethod);

    expect(legacy.onSelectSuperTokenPaymentMethod).toHaveBeenCalledWith(row, paymentMethod);
  });

  it('Given the tax-info primitive, When it runs, Then it forwards to CheckoutPage.updateTaxInfoForSelect', () => {
    window.CheckoutPage = { updateTaxInfoForSelect: jest.fn() };
    const session = new LegacyRenderSession({});

    session.updateInstallmentsTaxInfo('3', 'tax-info-id', [{ installments: 3 }]);

    expect(window.CheckoutPage.updateTaxInfoForSelect).toHaveBeenCalledWith('3', 'tax-info-id', [{ installments: 3 }]);
    delete window.CheckoutPage;
  });

  it('Given a selected installment, When installmentSelected runs, Then it dispatches the field event and records the metric', () => {
    window.MPCheckoutFieldsDispatcher = { addEventListenerDispatcher: jest.fn() };
    const installmentsFilled = jest.fn();
    const session = new LegacyRenderSession({ mpSuperTokenMetrics: { installmentsFilled } });

    session.installmentSelected('credit_card');

    expect(window.MPCheckoutFieldsDispatcher.addEventListenerDispatcher).toHaveBeenCalledWith(
      null,
      'focusout',
      'super_token_installments_filled',
      { onlyDispatch: true },
    );
    expect(installmentsFilled).toHaveBeenCalledWith('credit_card');
    delete window.MPCheckoutFieldsDispatcher;
  });

  it('Given the dispatcher is missing, When reported, Then it emits the missing metric exactly once', () => {
    delete window.MPCheckoutFieldsDispatcher;
    window.sendMetric = jest.fn();
    const session = new LegacyRenderSession({});

    session.reportInstallmentDispatcherMissing('super_token_installments_setup');
    session.reportInstallmentDispatcherMissing('super_token_installments_setup');

    expect(window.sendMetric).toHaveBeenCalledTimes(1);
    expect(window.sendMetric).toHaveBeenCalledWith(
      'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING',
      'super_token_installments_setup',
      'mp_super_token_init_error',
    );
    delete window.sendMetric;
  });
});

const { buildTypedRow } = require('@super-token/adapters/view/shared/typedRow');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('../fixtures');
const { creditCard, debitCard, accountMoney, consumerCredits } = require('../../../core/fixtures');

// A no-op RowPresentation (the shared dispatch is presentation-agnostic; the v2/v2.1 chrome is
// applied by each view, not here).
const PRESENTATION = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

const buildRowSession = (overrides = {}) => ({
  onSelectPaymentMethod: jest.fn(),
  updateInstallmentsTaxInfo: jest.fn(),
  installmentSelected: jest.fn(),
  reportInstallmentDispatcherMissing: jest.fn(),
  getFastPaymentToken: jest.fn(() => 'fpt'),
  renderCreditsContract: jest.fn(() => Promise.resolve({ update: jest.fn() })),
  updateCreditsContract: jest.fn(),
  dispatchInstallmentsFilledField: jest.fn(),
  recordCreditsContractRendered: jest.fn(),
  recordOpenCreditsInfoModal: jest.fn(),
  recordConsumerCreditsHint: jest.fn(),
  recordConsumerCreditsDueDate: jest.fn(),
  ...overrides,
});

describe('buildTypedRow (shared per-type dispatch — v2 and v2.1 build the same interactive row)', () => {
  const deps = buildViewDeps();

  it('Given account money and a rowSession, When built, Then it is an interactive row wired to selection', () => {
    const rowSession = buildRowSession();
    const paymentMethod = accountMoney({ id: 'am' });

    const row = buildTypedRow(paymentMethod, deps, PRESENTATION, { container: null, paymentMethods: [], rowSession });

    expect(row.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
    row.click();
    expect(rowSession.onSelectPaymentMethod).toHaveBeenCalledWith(row, expect.objectContaining({ id: 'am' }));
  });

  it('Given a credit card with a rowSession and installmentOptions, When built, Then it is a card row with the installments select', () => {
    const paymentMethod = creditCard({ id: 'cc1', installments: [{ installments: 1, installment_amount: 100, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'], total_amount: 100 }] });

    const row = buildTypedRow(paymentMethod, deps, PRESENTATION, {
      container: null,
      paymentMethods: [],
      rowSession: buildRowSession(),
      installmentOptions: () => [{ value: '1', title: '1x R$100' }],
    });

    expect(row.querySelector('#mp-super-token-installments-select-cc11234')).not.toBeNull();
  });

  it('Given a debit card with a rowSession and installmentOptions, When built, Then it is a card row with a security-code container and no installments', () => {
    const row = buildTypedRow(debitCard({ id: 'dc1' }), deps, PRESENTATION, {
      container: null,
      paymentMethods: [],
      rowSession: buildRowSession(),
      installmentOptions: () => [],
    });

    expect(row.querySelector('.mp-super-token-security-code-container')).not.toBeNull();
    expect(row.querySelector('select[data-checkout="installments"]')).toBeNull();
  });

  it('Given consumer credits with the full context, When built, Then it is an interactive credits row', () => {
    const rowSession = buildRowSession();
    const row = buildTypedRow(consumerCredits({ id: 'cc' }), deps, PRESENTATION, {
      container: null,
      paymentMethods: [],
      rowSession,
      installmentOptions: () => [],
      consumerCreditsHint: () => 'hint',
    });

    expect(row.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
    row.click();
    expect(rowSession.onSelectPaymentMethod).toHaveBeenCalledWith(row, expect.objectContaining({ id: 'cc' }));
  });

  it('Given no rowSession but an injected buildRow, When built, Then the legacy factory is used', () => {
    const legacyRow = document.createElement('article');
    const buildRow = jest.fn(() => legacyRow);

    const row = buildTypedRow(accountMoney({ id: 'am' }), deps, PRESENTATION, {
      container: null,
      paymentMethods: [],
      buildRow,
    });

    expect(row).toBe(legacyRow);
    expect(buildRow).toHaveBeenCalledWith(expect.objectContaining({ id: 'am' }));
  });

  it('Given neither a rowSession nor a buildRow, When built, Then it falls back to the presentation-only row (unwired)', () => {
    const rowSession = buildRowSession();

    // Positive control: built with a rowSession, clicking the row forwards to the session.
    const wired = buildTypedRow(accountMoney({ id: 'am' }), deps, PRESENTATION, { container: null, paymentMethods: [], rowSession });
    wired.click();
    expect(rowSession.onSelectPaymentMethod).toHaveBeenCalledTimes(1);

    // Fallback: built without a rowSession, the row is presentation-only — clicking wires nothing.
    rowSession.onSelectPaymentMethod.mockClear();
    const presentationOnly = buildTypedRow(accountMoney({ id: 'am' }), deps, PRESENTATION, { container: null, paymentMethods: [] });
    presentationOnly.click();
    expect(rowSession.onSelectPaymentMethod).not.toHaveBeenCalled();
    expect(presentationOnly.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
    expect(wired).not.toBe(presentationOnly);
  });
});

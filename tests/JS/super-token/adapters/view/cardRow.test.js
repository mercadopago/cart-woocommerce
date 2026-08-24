const { buildCardRow } = require('@super-token/adapters/view/shared/cardRow');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('./fixtures');
const { creditCard, debitCard, prepaidCard, installment } = require('../../core/fixtures');

const presentation = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

const buildSession = (overrides = {}) => ({
  onSelectPaymentMethod: jest.fn(),
  updateInstallmentsTaxInfo: jest.fn(),
  installmentSelected: jest.fn(),
  reportInstallmentDispatcherMissing: jest.fn(),
  ...overrides,
});

// identifier = `${id}${last_four_digits}`; cc1 + 1234
const SELECT_ID = 'mp-super-token-installments-select-cc11234';
const TAX_INFO_ID = 'mp-super-token-installments-tax-info-cc11234';

const cardWithInstallments = (overrides = {}) =>
  creditCard({
    installments: [
      installment({ installments: 1, installment_amount: 100, total_amount: 100 }),
      installment({ installments: 3, installment_rate: 0.1, installment_amount: 40, total_amount: 120 }),
    ],
    ...overrides,
  });

const installmentOptions = () => [
  { value: '1', title: '1x R$100' },
  { value: '3', title: '3x R$40 (R$120)' },
];

describe('buildCardRow (credit card)', () => {
  let cardInstallments;

  beforeEach(() => {
    document.body.innerHTML = '';
    // The shared checkout field the installments select mirrors into.
    cardInstallments = document.createElement('input');
    cardInstallments.id = 'cardInstallments';
    document.body.appendChild(cardInstallments);
  });

  const render = (card, session) => {
    const row = buildCardRow(card, buildViewDeps(), presentation, session, installmentOptions);
    document.body.appendChild(row);
    return row;
  };

  it('Given a credit card with installments, When built, Then it renders the installments select, tax-info and security-code field', () => {
    const row = render(cardWithInstallments(), buildSession());

    const select = row.querySelector(`#${SELECT_ID}`);
    expect(select).not.toBeNull();
    expect(select.querySelectorAll('option')).toHaveLength(2);
    expect(select.querySelector('option').selected).toBe(true);
    expect(row.querySelector(`#${TAX_INFO_ID}`)).not.toBeNull();
    expect(row.querySelector(`.${SHARED_STYLES.SECURITY_CODE_CONTAINER}`)).not.toBeNull();
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_DETAILS}`).classList.contains(SHARED_STYLES.PAYMENT_METHOD_HIDE)).toBe(true);
  });

  it('Given a value already selected, When built, Then it syncs #cardInstallments and refreshes the tax info once', () => {
    const session = buildSession();

    render(cardWithInstallments(), session);

    expect(cardInstallments.value).toBe('1');
    expect(session.updateInstallmentsTaxInfo).toHaveBeenCalledWith('1', TAX_INFO_ID, expect.any(Array));
    expect(session.updateInstallmentsTaxInfo).toHaveBeenCalledTimes(1);
  });

  it('Given the buyer picks an installment, When the select changes, Then it dispatches, records the metric, refreshes tax info and syncs the field', () => {
    const session = buildSession();
    const row = render(cardWithInstallments(), session);
    const select = row.querySelector(`#${SELECT_ID}`);

    select.value = '3';
    select.dispatchEvent(new Event('change'));

    expect(session.installmentSelected).toHaveBeenCalledWith('credit_card');
    expect(session.updateInstallmentsTaxInfo).toHaveBeenLastCalledWith('3', TAX_INFO_ID, expect.any(Array));
    expect(cardInstallments.value).toBe('3');
  });

  it('Given the installments setup, When wired, Then it reports a missing dispatcher once', () => {
    const session = buildSession();

    render(cardWithInstallments(), session);

    expect(session.reportInstallmentDispatcherMissing).toHaveBeenCalledWith('super_token_installments_setup');
  });

  it('Given the row, When clicked, Then it forwards the selection (shared interactive wiring)', () => {
    const session = buildSession();
    const card = cardWithInstallments();
    const row = render(card, session);

    row.click();

    expect(session.onSelectPaymentMethod).toHaveBeenCalledWith(row, card);
  });
});

describe('buildCardRow (debit / prepaid — no installments)', () => {
  const render = (card) => {
    const session = buildSession();
    const row = buildCardRow(card, buildViewDeps(), presentation, session, installmentOptions);
    return { row, session };
  };

  it('Given a debit card that requires a CVV, When built, Then it renders the security-code field but no installments select', () => {
    const { row } = render(debitCard());

    expect(row.querySelector(`.${SHARED_STYLES.SECURITY_CODE_CONTAINER}`)).not.toBeNull();
    expect(row.querySelector('select[data-checkout="installments"]')).toBeNull();
  });

  it('Given a prepaid card with no CVV, When built, Then the detail section has neither installments nor a security-code field', () => {
    const { row } = render(prepaidCard());

    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_DETAILS}`)).not.toBeNull();
    expect(row.querySelector('select[data-checkout="installments"]')).toBeNull();
    expect(row.querySelector(`.${SHARED_STYLES.SECURITY_CODE_CONTAINER}`)).toBeNull();
  });

  it('Given a debit row, When clicked, Then it forwards the selection', () => {
    const card = debitCard();
    const { row, session } = render(card);

    row.click();

    expect(session.onSelectPaymentMethod).toHaveBeenCalledWith(row, card);
  });
});

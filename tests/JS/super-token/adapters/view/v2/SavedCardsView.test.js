const { V2SavedCardsView } = require('@super-token/adapters/view/v2/SavedCardsView');
const { V2_STYLES } = require('@super-token/adapters/view/v2/styles');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');
const { buildViewDeps, buildEmailListener } = require('../fixtures');
const { creditCard, debitCard, accountMoney } = require('../../../core/fixtures');

describe('V2SavedCardsView (RN-2: v2 renders a flat list, no e-mail, no blocks)', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
  });

  it('Given several methods, When rendered, Then they form a flat list in original order below the header', () => {
    const view = new V2SavedCardsView(buildViewDeps());
    const methods = [creditCard({ id: 'a' }), creditCard({ id: 'b' }), accountMoney({ id: 'c' })];

    view.render({ container, paymentMethods: methods });

    const children = Array.from(container.children);
    expect(children[0].classList.contains(V2_STYLES.PAYMENT_METHODS_LIST_HEADER)).toBe(true);
    const rowIds = children
      .filter((child) => child.classList.contains(SHARED_STYLES.PAYMENT_METHOD))
      .map((row) => row.dataset.id);
    expect(rowIds).toEqual(['a1234', 'b1234', 'c']);
    expect(container.querySelectorAll(`.${SHARED_STYLES.PAYMENT_METHOD}`)).toHaveLength(3);
  });

  it('Given a render, When the header is inspected, Then it shows the list text and MP logo but never an e-mail', () => {
    const view = new V2SavedCardsView(buildViewDeps({ emailListener: buildEmailListener() }));

    view.render({ container, paymentMethods: [creditCard()] });

    const header = container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`);
    expect(header.querySelector('span').textContent).toBe('Meios de pagamento');
    expect(header.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER_LOGO}`).src).toContain(
      '/new-mp-logo.png',
    );
    expect(container.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`)).toBeNull();
  });

  it('Given an MP credit card, When rendered by v2, Then it is treated as a regular card (no MP override, last four shown)', () => {
    const view = new V2SavedCardsView(buildViewDeps());
    const mpCredit = creditCard({ id: 'mp', issuer: { name: 'Mercado Pago' } });

    view.render({ container, paymentMethods: [mpCredit] });

    const title = container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_TITLE}`).textContent;
    expect(title).toBe('Mercado Pago Crédito');
    expect(container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}`)).not.toBeNull();
  });

  it('Given a rendered list, When reset is called, Then the list header is removed', () => {
    const view = new V2SavedCardsView(buildViewDeps());
    view.render({ container, paymentMethods: [creditCard()] });

    view.reset(container);

    expect(container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)).toBeNull();
  });

  it('Given a rowSession, When an account-money row is rendered, Then it wires selection (PSW-4276 v2 defect)', () => {
    const onSelectPaymentMethod = jest.fn();
    const view = new V2SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [accountMoney({ id: 'am' })],
      rowSession: { onSelectPaymentMethod },
    });

    const row = container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`);
    row.click();
    expect(onSelectPaymentMethod).toHaveBeenCalledWith(row, expect.objectContaining({ id: 'am' }));
  });

  it('Given a rowSession and installmentOptions, When a credit card is rendered, Then the tree builds the card row with the installments select', () => {
    const view = new V2SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [creditCard({ id: 'cc1', installments: [{ installments: 1, installment_amount: 100, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'], total_amount: 100 }] })],
      rowSession: {
        onSelectPaymentMethod: jest.fn(),
        updateInstallmentsTaxInfo: jest.fn(),
        installmentSelected: jest.fn(),
        reportInstallmentDispatcherMissing: jest.fn(),
      },
      installmentOptions: () => [{ value: '1', title: '1x R$100' }],
    });

    expect(container.querySelector('#mp-super-token-installments-select-cc11234')).not.toBeNull();
  });

  it('Given a rowSession and installmentOptions, When a debit card is rendered, Then the tree builds it with a security-code container and no installments', () => {
    const view = new V2SavedCardsView(buildViewDeps());

    view.render({
      container,
      paymentMethods: [debitCard({ id: 'dc1' })],
      rowSession: {
        onSelectPaymentMethod: jest.fn(),
        updateInstallmentsTaxInfo: jest.fn(),
        installmentSelected: jest.fn(),
        reportInstallmentDispatcherMissing: jest.fn(),
      },
      installmentOptions: () => [],
    });

    expect(container.querySelector('.mp-super-token-security-code-container')).not.toBeNull();
    expect(container.querySelector('select[data-checkout="installments"]')).toBeNull();
  });

  it('Given no rowSession, When rendered, Then the rows stay presentation-only (parity fallback preserved)', () => {
    const onSelectPaymentMethod = jest.fn();

    // Positive control: with a rowSession the account-money row forwards the click.
    new V2SavedCardsView(buildViewDeps()).render({
      container,
      paymentMethods: [accountMoney({ id: 'am' })],
      rowSession: { onSelectPaymentMethod },
    });
    container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`).click();
    expect(onSelectPaymentMethod).toHaveBeenCalledTimes(1);

    // Fallback: without a rowSession the row is presentation-only — clicking it selects nothing.
    onSelectPaymentMethod.mockClear();
    container.innerHTML = '';
    new V2SavedCardsView(buildViewDeps()).render({ container, paymentMethods: [accountMoney({ id: 'am' })] });
    const row = container.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD}`);
    row.click();
    expect(onSelectPaymentMethod).not.toHaveBeenCalled();
    expect(row.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
  });
});

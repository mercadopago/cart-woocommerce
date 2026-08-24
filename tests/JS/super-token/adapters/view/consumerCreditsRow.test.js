const { buildConsumerCreditsRow } = require('@super-token/adapters/view/shared/consumerCreditsRow');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('./fixtures');
const { consumerCredits, installment } = require('../../core/fixtures');

const presentation = { mercadoPagoCreditCard: () => null, accountMoneyRowClasses: () => [] };
const options = () => [
  { value: '3', title: '3x R$40' },
  { value: '6', title: '6x R$25' },
];
const hint = () => 'HINT';
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

const buildSession = (contractController = { update: jest.fn() }, overrides = {}) => ({
  onSelectPaymentMethod: jest.fn(),
  updateInstallmentsTaxInfo: jest.fn(),
  installmentSelected: jest.fn(),
  reportInstallmentDispatcherMissing: jest.fn(),
  getFastPaymentToken: jest.fn(() => 'fast-token'),
  renderCreditsContract: jest.fn(() => Promise.resolve(contractController)),
  updateCreditsContract: jest.fn(),
  dispatchInstallmentsFilledField: jest.fn(),
  recordCreditsContractRendered: jest.fn(),
  recordOpenCreditsInfoModal: jest.fn(),
  recordConsumerCreditsHint: jest.fn(),
  recordConsumerCreditsDueDate: jest.fn(),
  ...overrides,
});

const cc = (overrides = {}) =>
  consumerCredits({
    id: 'coc1',
    token: 'tok-coc',
    credits_pricing_id: 'cp1',
    next_due_date: '2026-03-15',
    installments: [installment({ installments: 3 }), installment({ installments: 6 })],
    ...overrides,
  });

const SELECT_ID = 'mp-super-token-installments-select-coc1';

describe('buildConsumerCreditsRow', () => {
  let cardInstallments;

  beforeEach(() => {
    document.body.innerHTML = '';
    cardInstallments = document.createElement('input');
    cardInstallments.id = 'cardInstallments';
    document.body.appendChild(cardInstallments);
  });

  const render = (session) => {
    const row = buildConsumerCreditsRow(cc(), buildViewDeps(), presentation, session, options, hint);
    document.body.appendChild(row);
    return row;
  };

  it('Given a consumer-credits method, When built, Then it renders the placeholder + options select and the hint/due-date/legal slots', () => {
    const row = render(buildSession());

    const select = row.querySelector(`#${SELECT_ID}`);
    expect(select).not.toBeNull();
    expect(select.querySelectorAll('option')).toHaveLength(3); // placeholder + 2 options
    expect(select.querySelector('option').disabled).toBe(true);
    expect(row.querySelector('#mp-consumer-credits-hint')).not.toBeNull();
    expect(row.querySelector('#mp-consumer-credits-due-date')).not.toBeNull();
    expect(row.querySelector('#mp-consumer-credits-legal-text')).not.toBeNull();
    expect(row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_DETAILS}`)).not.toBeNull();
  });

  it('Given the row, When built, Then it requests the SDK credits contract with the fast token and pricing/pseudotoken', () => {
    const session = buildSession();

    render(session);

    expect(session.renderCreditsContract).toHaveBeenCalledWith(
      'mp-consumer-credits-legal-text',
      expect.objectContaining({ fastPaymentToken: 'fast-token', pricingId: 'cp1', pseudotoken: 'tok-coc' }),
    );
  });

  it('Given the contract resolved, When an installment is chosen, Then it applies the hint/due-date, syncs the field and updates the contract', async () => {
    const controller = { update: jest.fn() };
    const session = buildSession(controller);
    const row = render(session);
    await flush();

    const select = row.querySelector(`#${SELECT_ID}`);
    select.value = '3';
    select.dispatchEvent(new Event('change'));

    expect(session.dispatchInstallmentsFilledField).toHaveBeenCalled();
    expect(row.querySelector('#mp-consumer-credits-hint').innerHTML).toBe('HINT');
    expect(row.querySelector('#mp-consumer-credits-hint').style.display).toBe('block');
    expect(row.querySelector('#mp-consumer-credits-due-date').style.display).toBe('block');
    expect(cardInstallments.value).toBe('3');
    expect(session.updateCreditsContract).toHaveBeenCalledWith(controller, '3');
    expect(session.recordConsumerCreditsHint).toHaveBeenCalledWith(true, undefined);
  });

  it('Given a Brazilian site, When an installment is chosen, Then the debit-auto legal text is a DOM span (not raw HTML)', async () => {
    const session = buildSession();
    const row = render(session);
    await flush();

    const select = row.querySelector(`#${SELECT_ID}`);
    select.value = '3';
    select.dispatchEvent(new Event('change'));

    const debitAuto = document.getElementById('mp-consumer-credits-debit-auto-text');
    expect(debitAuto.style.display).toBe('block');
    const span = debitAuto.querySelector('span');
    expect(span).not.toBeNull();
    expect(span.textContent).toBe('Débito automático');
  });

  it('Given the contract render rejects, When built, Then it records the render failure', async () => {
    const session = buildSession(undefined, {
      renderCreditsContract: jest.fn(() => Promise.reject(new Error('boom'))),
    });

    render(session);
    await flush();

    expect(session.recordCreditsContractRendered).toHaveBeenCalledWith(false, expect.any(Error));
  });
});

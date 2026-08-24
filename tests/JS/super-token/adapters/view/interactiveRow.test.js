const { buildInteractiveRow } = require('@super-token/adapters/view/shared/interactiveRow');
const { SHARED_STYLES } = require('@super-token/adapters/view/shared/styles');
const { buildViewDeps } = require('./fixtures');
const { accountMoney } = require('../../core/fixtures');

const presentation = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => [],
};

describe('buildInteractiveRow', () => {
  it('Given a payment method, When built, Then it renders the presentation row skeleton', () => {
    const row = buildInteractiveRow(accountMoney({ id: 'am' }), buildViewDeps(), presentation, {
      onSelectPaymentMethod: jest.fn(),
    });

    expect(row.tagName).toBe('ARTICLE');
    expect(row.classList.contains(SHARED_STYLES.PAYMENT_METHOD)).toBe(true);
    expect(row.dataset.id).toBe('am');
  });

  it('Given a built row, When it is clicked, Then it forwards the selection to the session', () => {
    const session = { onSelectPaymentMethod: jest.fn() };
    const method = accountMoney({ id: 'am' });
    const row = buildInteractiveRow(method, buildViewDeps(), presentation, session);

    row.click();

    expect(session.onSelectPaymentMethod).toHaveBeenCalledWith(row, method);
  });

  it.each([
    ['Enter', 'Enter'],
    ['Space', ' '],
  ])('Given a built row, When %s is pressed, Then it forwards the selection and prevents default', (code, key) => {
    const session = { onSelectPaymentMethod: jest.fn() };
    const method = accountMoney({ id: 'am' });
    const row = buildInteractiveRow(method, buildViewDeps(), presentation, session);
    const event = new KeyboardEvent('keydown', { code, key, cancelable: true });

    row.dispatchEvent(event);

    expect(session.onSelectPaymentMethod).toHaveBeenCalledWith(row, method);
    expect(event.defaultPrevented).toBe(true);
  });

  it('Given a built row, When another key is pressed, Then nothing is selected', () => {
    const session = { onSelectPaymentMethod: jest.fn() };
    const row = buildInteractiveRow(accountMoney({ id: 'am' }), buildViewDeps(), presentation, session);

    row.dispatchEvent(new KeyboardEvent('keydown', { code: 'Tab', key: 'Tab' }));

    expect(session.onSelectPaymentMethod).not.toHaveBeenCalled();
  });
});

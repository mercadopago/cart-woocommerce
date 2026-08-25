const { PaymentMethodCatalog } = require('@super-token/core/checkoutSession/PaymentMethodCatalog');
const { creditCard, debitCard, prepaidCard, accountMoney, consumerCredits } = require('./fixtures');

describe('PaymentMethodCatalog', () => {
  it('Given more than three saved cards, When reordering cards-first, Then at most three cards are kept then account money (RN-1)', () => {
    const catalog = new PaymentMethodCatalog('cards_first');
    const cards = [
      creditCard({ id: 'c1' }),
      creditCard({ id: 'c2' }),
      debitCard({ id: 'c3' }),
      prepaidCard({ id: 'c4' }),
      creditCard({ id: 'c5' }),
    ];

    const result = catalog.reorderAccountPaymentMethods([...cards, accountMoney()]);

    expect(result.map((pm) => pm.id)).toEqual(['c1', 'c2', 'c3', 'am1']);
  });

  it('Given account-money-first preference, When reordering, Then money-specialized methods come before the cards (RN-1)', () => {
    const catalog = new PaymentMethodCatalog('account_money_first');
    const methods = [creditCard({ id: 'c1' }), accountMoney(), consumerCredits()];

    const result = catalog.reorderAccountPaymentMethods(methods);

    expect(result.map((pm) => pm.id)).toEqual(['am1', 'coc1', 'c1']);
  });

  it('Given no order preference, When reordering, Then it defaults to cards-first', () => {
    const catalog = new PaymentMethodCatalog();
    const methods = [accountMoney(), creditCard({ id: 'c1' })];

    const result = catalog.reorderAccountPaymentMethods(methods);

    expect(result.map((pm) => pm.id)).toEqual(['c1', 'am1']);
  });

  it('Given account-money-first but no account money present, When reordering, Then it falls back to cards-first', () => {
    const catalog = new PaymentMethodCatalog('account_money_first');
    const methods = [creditCard({ id: 'c1' }), consumerCredits()];

    const result = catalog.reorderAccountPaymentMethods(methods);

    expect(result.map((pm) => pm.id)).toEqual(['c1', 'coc1']);
  });
});

const {
  isCreditCard,
  isDebitCard,
  isAccountMoney,
  isPrepaidCard,
  isConsumerCredits,
  isNewCard,
  isMercadoPagoCard,
  isMercadoPagoCreditCard,
  paymentMethodIdentifier,
} = require('@super-token/core/checkoutSession/PaymentMethodClassifier');
const { creditCard, debitCard, prepaidCard, accountMoney, consumerCredits } = require('./fixtures');

describe('PaymentMethodClassifier', () => {
  it('Given each payment method type, When classified, Then only the matching predicate is true (RN-6)', () => {
    expect(isCreditCard(creditCard())).toBe(true);
    expect(isDebitCard(debitCard())).toBe(true);
    expect(isPrepaidCard(prepaidCard())).toBe(true);
    expect(isAccountMoney(accountMoney())).toBe(true);
    expect(isConsumerCredits(consumerCredits())).toBe(true);

    expect(isDebitCard(creditCard())).toBe(false);
    expect(isPrepaidCard(creditCard())).toBe(false);
  });

  it('Given a prepaid card issued by Mercado Pago, When classified, Then it is a Mercado Pago card, not a plain prepaid distinction (RN-6)', () => {
    const mpPrepaid = prepaidCard({ issuer: { name: 'Mercado Pago' } });

    expect(isMercadoPagoCard(mpPrepaid)).toBe(true);
    expect(isPrepaidCard(mpPrepaid)).toBe(true);
  });

  it('Given a credit card issued by Mercado Pago, When classified, Then it is a Mercado Pago credit card', () => {
    const mpCredit = creditCard({ issuer: { name: 'MERCADO PAGO' } });

    expect(isMercadoPagoCreditCard(mpCredit)).toBe(true);
    expect(isMercadoPagoCard(mpCredit)).toBe(false);
  });

  it('Given a non-Mercado-Pago issuer, When classified, Then the Mercado Pago predicates are false', () => {
    expect(isMercadoPagoCard(prepaidCard({ issuer: { name: 'Some Bank' } }))).toBe(false);
    expect(isMercadoPagoCreditCard(creditCard({ issuer: { name: 'Itau' } }))).toBe(false);
  });

  it('Given the new-card pseudo option, When classified, Then isNewCard is true', () => {
    expect(isNewCard({ id: 'new_card', type: 'new_card' })).toBe(true);
    expect(isNewCard(creditCard())).toBe(false);
  });

  it('Given null or undefined, When classified, Then every predicate is false', () => {
    expect(isCreditCard(null)).toBe(false);
    expect(isAccountMoney(undefined)).toBe(false);
    expect(isMercadoPagoCard(null)).toBe(false);
  });

  it('Given a card with last four digits, When building the identifier, Then it combines id and last four digits', () => {
    const card = creditCard({ id: 'cc1', card: { card_number: { last_four_digits: '1234' } } });

    expect(paymentMethodIdentifier(card)).toBe('cc11234');
  });

  it('Given a method without a card, When building the identifier, Then only the id is used', () => {
    expect(paymentMethodIdentifier(accountMoney({ id: 'am1' }))).toBe('am1');
  });

  it('Given no payment method, When building the identifier, Then it returns an empty string', () => {
    expect(paymentMethodIdentifier(null)).toBe('');
  });
});

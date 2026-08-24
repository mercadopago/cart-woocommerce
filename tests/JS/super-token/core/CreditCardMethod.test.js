const { CreditCardMethod } = require('@super-token/core/paymentMethods/CreditCardMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, creditCard, debitCard } = require('./fixtures');

describe('CreditCardMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given a credit card, When matched, Then it returns true', () => {
      expect(new CreditCardMethod(buildConfig()).matches(creditCard())).toBe(true);
    });

    it('Given a non-credit method, When matched, Then it returns false', () => {
      expect(new CreditCardMethod(buildConfig()).matches(debitCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given a credit card module, When asked, Then it requires installments', () => {
      expect(new CreditCardMethod(buildConfig()).requiresInstallments()).toBe(true);
    });

    it('Given a credit card module, When inspected, Then it inherits installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new CreditCardMethod(buildConfig())).toBeInstanceOf(BasePaymentMethodWithInstallments);
    });
  });

  describe('requiresCvv', () => {
    it('Given mandatory security code settings, When asked, Then a CVV is required', () => {
      const card = creditCard({ security_code_settings: { mode: 'mandatory', length: 3 } });
      expect(new CreditCardMethod(buildConfig()).requiresCvv(card)).toBe(true);
    });

    it('Given optional security code settings, When asked, Then no CVV is required', () => {
      const card = creditCard({ security_code_settings: { mode: 'optional', length: 3 } });
      expect(new CreditCardMethod(buildConfig()).requiresCvv(card)).toBe(false);
    });
  });

  describe('decorate — sets the display name and thumbnail', () => {
    it('Given a regular issuer credit card, When decorated, Then the issuer name gets the Crédito suffix', () => {
      const decorated = new CreditCardMethod(buildConfig()).decorate(
        creditCard({ issuer: { name: 'Itau' } }),
      );

      expect(decorated.name).toBe('Itau Crédito');
      expect(decorated.thumbnail).toBe('/white.png');
    });

    it('Given a credit card without an issuer, When decorated, Then it falls back to the method name plus Crédito', () => {
      const decorated = new CreditCardMethod(buildConfig()).decorate(
        creditCard({ issuer: undefined, name: 'Visa' }),
      );

      expect(decorated.name).toBe('Visa Crédito');
    });

    it('Given a Mercado Pago credit card on a blue site (v2.1), When decorated, Then it gets the MP name and blue icon', () => {
      const decorated = new CreditCardMethod(buildConfig({ siteId: 'MLA', variant: 'v2.1' })).decorate(
        creditCard({ issuer: { name: 'Mercado Pago' } }),
      );

      expect(decorated.name).toBe('Cartao Mercado Pago');
      expect(decorated.thumbnail).toBe('/mp-blue.png');
    });

    it('Given a Mercado Pago credit card on a dark site (v2.1), When decorated, Then it gets the dark icon', () => {
      const decorated = new CreditCardMethod(buildConfig({ siteId: 'MLB', variant: 'v2.1' })).decorate(
        creditCard({ issuer: { name: 'Mercado Pago' } }),
      );

      expect(decorated.thumbnail).toBe('/mp-dark.png');
    });

    it('Given a Mercado Pago credit card on v2, When decorated, Then it stays a regular issuer card (no MP name/icon)', () => {
      const decorated = new CreditCardMethod(buildConfig({ siteId: 'MLA', variant: 'v2' })).decorate(
        creditCard({ issuer: { name: 'Mercado Pago' } }),
      );

      expect(decorated.name).toBe('Mercado Pago Crédito');
      expect(decorated.thumbnail).toBe('/white.png');
      expect(decorated.thumbnail).not.toBe('/mp-blue.png');
    });

    it('Given a non-credit method, When decorated, Then it is returned unchanged', () => {
      const debit = debitCard({ name: 'untouched', thumbnail: 'untouched' });
      const decorated = new CreditCardMethod(buildConfig()).decorate(debit);

      expect(decorated).toBe(debit);
      expect(decorated.name).toBe('untouched');
    });
  });
});

const { DebitCardMethod } = require('@super-token/core/paymentMethods/DebitCardMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, debitCard, creditCard } = require('./fixtures');

describe('DebitCardMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given a debit card, When matched, Then it returns true', () => {
      expect(new DebitCardMethod(buildConfig()).matches(debitCard())).toBe(true);
    });

    it('Given a non-debit method, When matched, Then it returns false', () => {
      expect(new DebitCardMethod(buildConfig()).matches(creditCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given a debit card module, When asked, Then it does not require installments', () => {
      expect(new DebitCardMethod(buildConfig()).requiresInstallments()).toBe(false);
    });

    it('Given a debit card module, When inspected, Then it does not inherit installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new DebitCardMethod(buildConfig())).not.toBeInstanceOf(
        BasePaymentMethodWithInstallments,
      );
    });
  });

  describe('requiresCvv', () => {
    it('Given mandatory security code settings, When asked, Then a CVV is required', () => {
      const card = debitCard({ security_code_settings: { mode: 'mandatory', length: 3 } });
      expect(new DebitCardMethod(buildConfig()).requiresCvv(card)).toBe(true);
    });

    it('Given optional security code settings, When asked, Then no CVV is required', () => {
      const card = debitCard({ security_code_settings: { mode: 'optional', length: 3 } });
      expect(new DebitCardMethod(buildConfig()).requiresCvv(card)).toBe(false);
    });
  });

  describe('decorate — sets the display name and thumbnail', () => {
    it('Given a debit card, When decorated, Then the issuer name gets the Débito suffix and the white-card thumbnail', () => {
      const decorated = new DebitCardMethod(buildConfig()).decorate(
        debitCard({ issuer: { name: 'Itau' } }),
      );

      expect(decorated.name).toBe('Itau Débito');
      expect(decorated.thumbnail).toBe('/white.png');
    });

    it('Given a debit card without an issuer, When decorated, Then it falls back to the method name plus Débito', () => {
      const decorated = new DebitCardMethod(buildConfig()).decorate(
        debitCard({ issuer: undefined, name: 'Maestro' }),
      );

      expect(decorated.name).toBe('Maestro Débito');
    });

    it('Given a non-debit method, When decorated, Then it is returned unchanged', () => {
      const card = creditCard({ name: 'untouched' });
      const decorated = new DebitCardMethod(buildConfig()).decorate(card);

      expect(decorated).toBe(card);
      expect(decorated.name).toBe('untouched');
    });
  });
});

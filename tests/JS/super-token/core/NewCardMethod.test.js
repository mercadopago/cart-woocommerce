const { NewCardMethod } = require('@super-token/core/paymentMethods/NewCardMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, newCard, creditCard } = require('./fixtures');

describe('NewCardMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given the new-card pseudo-method, When matched, Then it returns true', () => {
      expect(new NewCardMethod(buildConfig()).matches(newCard())).toBe(true);
    });

    it('Given any real payment method, When matched, Then it returns false', () => {
      expect(new NewCardMethod(buildConfig()).matches(creditCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given the new-card module, When asked, Then it does not require installments', () => {
      expect(new NewCardMethod(buildConfig()).requiresInstallments()).toBe(false);
    });

    it('Given the new-card module, When inspected, Then it does not inherit installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new NewCardMethod(buildConfig())).not.toBeInstanceOf(BasePaymentMethodWithInstallments);
    });
  });

  describe('requiresCvv', () => {
    it('Given the new-card pseudo-method (no security code settings), When asked, Then no CVV is required', () => {
      expect(new NewCardMethod(buildConfig()).requiresCvv(newCard())).toBe(false);
    });
  });

  describe('decorate', () => {
    it('Given the new-card pseudo-method, When decorated, Then it is returned unchanged', () => {
      const method = newCard();
      const decorated = new NewCardMethod(buildConfig()).decorate(method);

      expect(decorated).toBe(method);
    });
  });
});

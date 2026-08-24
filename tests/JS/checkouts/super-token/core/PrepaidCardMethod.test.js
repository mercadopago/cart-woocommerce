const { PrepaidCardMethod } = require('@super-token/core/paymentMethods/PrepaidCardMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, prepaidCard, debitCard } = require('./fixtures');

describe('PrepaidCardMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given a prepaid card, When matched, Then it returns true', () => {
      expect(new PrepaidCardMethod(buildConfig()).matches(prepaidCard())).toBe(true);
    });

    it('Given a non-prepaid method, When matched, Then it returns false', () => {
      expect(new PrepaidCardMethod(buildConfig()).matches(debitCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given a prepaid card module, When asked, Then it does not require installments', () => {
      expect(new PrepaidCardMethod(buildConfig()).requiresInstallments()).toBe(false);
    });

    it('Given a prepaid card module, When inspected, Then it does not inherit installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new PrepaidCardMethod(buildConfig())).not.toBeInstanceOf(
        BasePaymentMethodWithInstallments,
      );
    });
  });

  describe('requiresCvv', () => {
    it('Given mandatory security code settings, When asked, Then a CVV is required', () => {
      const card = prepaidCard({ security_code_settings: { mode: 'mandatory', length: 3 } });
      expect(new PrepaidCardMethod(buildConfig()).requiresCvv(card)).toBe(true);
    });

    it('Given no security code settings, When asked, Then no CVV is required', () => {
      expect(new PrepaidCardMethod(buildConfig()).requiresCvv(prepaidCard())).toBe(false);
    });
  });

  describe('decorate — sets the display name and thumbnail', () => {
    it('Given a regular prepaid card, When decorated, Then only the white-card thumbnail is applied and the name is untouched', () => {
      const decorated = new PrepaidCardMethod(buildConfig()).decorate(
        prepaidCard({ issuer: { name: 'Some Bank' }, name: '' }),
      );

      expect(decorated.thumbnail).toBe('/white.png');
      expect(decorated.name).toBe('');
    });

    it('Given a Mercado Pago prepaid card, When decorated, Then it gets the MP card name and thumbnail cumulatively', () => {
      const decorated = new PrepaidCardMethod(
        buildConfig({ thumbnails: { paymentMethodsThumbnails: { pp1: '/prepaid-brand.png' } } }),
      ).decorate(prepaidCard({ id: 'pp1', issuer: { name: 'Mercado Pago' } }));

      expect(decorated.name).toBe('Mercado Pago');
      expect(decorated.thumbnail).toBe('/prepaid-brand.png');
    });

    it('Given a per-id thumbnail override, When decorated, Then the override wins over the white fallback', () => {
      const decorated = new PrepaidCardMethod(
        buildConfig({ thumbnails: { paymentMethodsThumbnails: { pp1: '/override.png' } } }),
      ).decorate(prepaidCard({ id: 'pp1', issuer: { name: 'Bank' } }));

      expect(decorated.thumbnail).toBe('/override.png');
    });

    it('Given a non-prepaid method, When decorated, Then it is returned unchanged', () => {
      const card = debitCard({ name: 'untouched' });
      const decorated = new PrepaidCardMethod(buildConfig()).decorate(card);

      expect(decorated).toBe(card);
      expect(decorated.name).toBe('untouched');
    });
  });
});

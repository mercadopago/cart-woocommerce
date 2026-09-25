const { ConsumerCreditsMethod } = require('@super-token/core/paymentMethods/ConsumerCreditsMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, consumerCredits, creditCard } = require('./fixtures');

describe('ConsumerCreditsMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given a digital_currency method, When matched, Then it returns true', () => {
      expect(new ConsumerCreditsMethod(buildConfig()).matches(consumerCredits())).toBe(true);
    });

    it('Given a non-credits method, When matched, Then it returns false', () => {
      expect(new ConsumerCreditsMethod(buildConfig()).matches(creditCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given a consumer credits module, When asked, Then it requires installments', () => {
      expect(new ConsumerCreditsMethod(buildConfig()).requiresInstallments()).toBe(true);
    });

    it('Given a consumer credits module, When inspected, Then it inherits installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new ConsumerCreditsMethod(buildConfig())).toBeInstanceOf(
        BasePaymentMethodWithInstallments,
      );
    });
  });

  describe('buildConsumerCreditsHint — site-specific legal line for the selected installment', () => {
    it('Given MLB conditions, When built, Then it composes the interest/CET/IOF/borrowed line', () => {
      const installment = {
        labels: ['tem_2,5|tea_30|cetm_3|ceta_35|iof_0,5'],
        consumer_credits: { conditions: {} },
        installment_iof_amount: 1.5,
        total_amount: 100,
      };

      const hint = new ConsumerCreditsMethod(buildConfig({ siteId: 'MLB' })).buildConsumerCreditsHint(installment);

      expect(hint).toBe('Taxa de juros: 2,5 a.m. 30 a.a.. CET: 3 a.m. 35 a.a.. IOF: R$ 1,50 (0,5). Valor emprestado: R$ 98,50.');
    });

    it('Given MLA conditions, When built, Then it composes the cftea/tna/tea line with the fixed-rate suffix', () => {
      const installment = { labels: ['cftea_120|tna_80|tea_90'], consumer_credits: { conditions: {} } };

      const hint = new ConsumerCreditsMethod(buildConfig({ siteId: 'MLA' })).buildConsumerCreditsHint(installment);

      expect(hint).toBe('<strong>CFTEA: 120</strong> - TNA: 80 - TEA: 90. Tasa fija');
    });

    it('Given an installment without conditions, When built, Then it throws', () => {
      expect(() =>
        new ConsumerCreditsMethod(buildConfig()).buildConsumerCreditsHint({ labels: [] }),
      ).toThrow('no_installment_conditions');
    });
  });

  describe('requiresCvv', () => {
    it('Given consumer credits (no security code settings), When asked, Then no CVV is required', () => {
      expect(new ConsumerCreditsMethod(buildConfig()).requiresCvv(consumerCredits())).toBe(false);
    });
  });

  describe('decorate — sets the display name and thumbnail', () => {
    it('Given consumer credits in Brazil, When decorated, Then it gets the money icon and the BR name', () => {
      const decorated = new ConsumerCreditsMethod(buildConfig({ siteId: 'MLB' })).decorate(
        consumerCredits(),
      );

      expect(decorated.thumbnail).toBe('/money.png');
      expect(decorated.name).toBe('Linha de Crédito Mercado&nbsp;Pago');
    });

    it('Given consumer credits in Mexico, When decorated, Then it gets the MX name', () => {
      const decorated = new ConsumerCreditsMethod(buildConfig({ siteId: 'MLM' })).decorate(
        consumerCredits(),
      );

      expect(decorated.name).toBe('Meses sin Tarjeta con Mercado&nbsp;Pago');
    });

    it('Given consumer credits on any other site, When decorated, Then it gets the default name', () => {
      const decorated = new ConsumerCreditsMethod(buildConfig({ siteId: 'MLA' })).decorate(
        consumerCredits(),
      );

      expect(decorated.name).toBe('Cuotas sin Tarjeta con Mercado&nbsp;Pago');
    });

    it('Given a non-credits method, When decorated, Then it is returned unchanged', () => {
      const card = creditCard({ name: 'untouched' });
      const decorated = new ConsumerCreditsMethod(buildConfig()).decorate(card);

      expect(decorated).toBe(card);
      expect(decorated.name).toBe('untouched');
    });
  });
});

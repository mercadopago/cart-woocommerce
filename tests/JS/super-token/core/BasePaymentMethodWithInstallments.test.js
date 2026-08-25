const { CreditCardMethod } = require('@super-token/core/paymentMethods/CreditCardMethod');
const { ConsumerCreditsMethod } = require('@super-token/core/paymentMethods/ConsumerCreditsMethod');
const { DebitCardMethod } = require('@super-token/core/paymentMethods/DebitCardMethod');
const { buildConfig, installment, creditCard, consumerCredits, debitCard } = require('./fixtures');

describe('BasePaymentMethodWithInstallments', () => {
  describe('requiresInstallments (RN-8)', () => {
    it('Given credit card and consumer credits, When asked, Then they require installments', () => {
      const config = buildConfig();
      expect(new CreditCardMethod(config).requiresInstallments()).toBe(true);
      expect(new ConsumerCreditsMethod(config).requiresInstallments()).toBe(true);
    });

    it('Given a method without installments (debit), When asked, Then it does not require installments', () => {
      expect(new DebitCardMethod(buildConfig()).requiresInstallments()).toBe(false);
    });
  });

  describe('numberOfInstallmentsWithoutFee (RN-4)', () => {
    const creditMethod = new CreditCardMethod(buildConfig());

    it('Given a credit card, When counting, Then only rate-0 MERCADOPAGO installments count and the largest is returned', () => {
      const card = creditCard({
        installments: [
          installment({ installments: 1, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] }),
          installment({ installments: 3, installment_rate: 0, installment_rate_collector: ['MERCADOPAGO'] }),
          installment({ installments: 6, installment_rate: 0.1, installment_rate_collector: ['MERCADOPAGO'] }),
        ],
      });

      expect(creditMethod.numberOfInstallmentsWithoutFee(card)).toBe(3);
    });

    it('Given consumer credits, When counting, Then rate-0 installments count regardless of collector', () => {
      const creditsMethod = new ConsumerCreditsMethod(buildConfig());
      const credits = consumerCredits({
        installments: [
          installment({ installments: 3, installment_rate: 0, installment_rate_collector: [] }),
          installment({ installments: 6, installment_rate: 0.2, installment_rate_collector: [] }),
        ],
      });

      expect(creditsMethod.numberOfInstallmentsWithoutFee(credits)).toBe(3);
    });

    it('Given consumer credits with no rate-0 installments, When counting, Then it returns 0', () => {
      const creditsMethod = new ConsumerCreditsMethod(buildConfig());
      const credits = consumerCredits({
        installments: [installment({ installments: 3, installment_rate: 0.2, installment_rate_collector: [] })],
      });

      expect(creditsMethod.numberOfInstallmentsWithoutFee(credits)).toBe(0);
    });

    it('Given a credit card with only third-party fee-free installments, When counting, Then it returns 0 without throwing (RN-4)', () => {
      const card = creditCard({
        installments: [
          installment({ installments: 3, installment_rate: 0, installment_rate_collector: ['THIRD_PARTY'] }),
        ],
      });

      expect(creditMethod.numberOfInstallmentsWithoutFee(card)).toBe(0);
    });

    it('Given a credit card with only paid installments, When counting, Then it returns 0 without throwing', () => {
      const card = creditCard({
        installments: [
          installment({ installments: 3, installment_rate: 0.1, installment_rate_collector: ['MERCADOPAGO'] }),
        ],
      });

      expect(creditMethod.numberOfInstallmentsWithoutFee(card)).toBe(0);
    });

    it('Given a method with no installments, When counting, Then it returns 0', () => {
      expect(creditMethod.numberOfInstallmentsWithoutFee(creditCard({ installments: [] }))).toBe(0);
    });

    it('Given a method that has no installments concept, When counting, Then it returns 0', () => {
      expect(creditMethod.numberOfInstallmentsWithoutFee(debitCard())).toBe(0);
    });
  });

  describe('buildInstallmentTitle (RN-5)', () => {
    it('Given a single installment, When building the title, Then it shows the total without parentheses', () => {
      const title = new CreditCardMethod(buildConfig()).buildInstallmentTitle(
        installment({ installments: 1, total_amount: 100 }),
      );

      expect(title.startsWith('1x ')).toBe(true);
      expect(title).not.toContain('(');
    });

    it('Given an installment with a rate, When building the title, Then it shows the amount and total in parentheses without an asterisk', () => {
      const title = new CreditCardMethod(buildConfig()).buildInstallmentTitle(
        installment({ installments: 3, installment_amount: 40, installment_rate: 0.1, total_amount: 120 }),
      );

      expect(title.startsWith('3x ')).toBe(true);
      expect(title).toContain('(');
      expect(title.endsWith('*')).toBe(false);
    });

    it('Given a third-party interest-free installment on a disclaimer site, When building the title, Then it carries the asterisk (RN-5)', () => {
      const title = new CreditCardMethod(buildConfig({ siteId: 'MCO' })).buildInstallmentTitle(
        installment({
          installments: 3,
          installment_amount: 40,
          installment_rate: 0,
          installment_rate_collector: ['THIRD_PARTY'],
          total_amount: 120,
        }),
      );

      expect(title.endsWith('*')).toBe(true);
    });

    it('Given a Mercado Pago interest-free installment on a non-disclaimer site, When building the title, Then it shows the interest-free text', () => {
      const title = new CreditCardMethod(buildConfig({ siteId: 'MLB' })).buildInstallmentTitle(
        installment({
          installments: 3,
          installment_amount: 40,
          installment_rate: 0,
          installment_rate_collector: ['MERCADOPAGO'],
          total_amount: 120,
        }),
      );

      expect(title.endsWith('sem juros')).toBe(true);
    });
  });
});

const { createPaymentMethodRegistry } = require('@super-token/core/paymentMethods/registry');
const { CreditCardMethod } = require('@super-token/core/paymentMethods/CreditCardMethod');
const { DebitCardMethod } = require('@super-token/core/paymentMethods/DebitCardMethod');
const { AccountMoneyMethod } = require('@super-token/core/paymentMethods/AccountMoneyMethod');
const {
  buildConfig,
  creditCard,
  debitCard,
  prepaidCard,
  accountMoney,
  consumerCredits,
} = require('./fixtures');

describe('PaymentMethodRegistry', () => {
  describe('resolve', () => {
    it('Given a payment method, When resolved, Then it returns the module that owns its type', () => {
      const registry = createPaymentMethodRegistry(buildConfig());

      expect(registry.resolve(creditCard())).toBeInstanceOf(CreditCardMethod);
      expect(registry.resolve(debitCard())).toBeInstanceOf(DebitCardMethod);
      expect(registry.resolve(accountMoney())).toBeInstanceOf(AccountMoneyMethod);
    });

    it('Given an unrecognized method, When resolved, Then it returns undefined', () => {
      const registry = createPaymentMethodRegistry(buildConfig());

      expect(registry.resolve({ id: 'x', type: 'unknown_type' })).toBeUndefined();
    });
  });

  describe('decorate (RN-7) — equivalent to normalizeAccountPaymentMethods', () => {
    it('Given account money on a non-Mexico site, When decorated, Then it gets the wallet icon and account-money name', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLB' }));
      const decorated = registry.decorate(accountMoney());

      expect(decorated.thumbnail).toBe('/wallet.png');
      expect(decorated.name).toBe('Dinheiro na conta');
    });

    it('Given account money in Mexico with balance and investment, When decorated, Then the combined name is used', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLM' }));
      const decorated = registry.decorate(
        accountMoney({ has_account_money: true, has_account_money_invested: true }),
      );

      expect(decorated.name).toBe('Disponible e invertido');
    });

    it('Given consumer credits, When decorated, Then it gets the money icon and site-specific name', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLB' }));
      const decorated = registry.decorate(consumerCredits());

      expect(decorated.thumbnail).toBe('/money.png');
      expect(decorated.name).toBe('Linha de Crédito Mercado&nbsp;Pago');
    });

    it('Given a regular credit card, When decorated, Then the issuer name gets the Crédito suffix', () => {
      const registry = createPaymentMethodRegistry(buildConfig());
      const decorated = registry.decorate(creditCard({ issuer: { name: 'Itau' } }));

      expect(decorated.name).toBe('Itau Crédito');
      expect(decorated.thumbnail).toBe('/white.png');
    });

    it('Given a regular debit card, When decorated, Then the issuer name gets the Débito suffix', () => {
      const registry = createPaymentMethodRegistry(buildConfig());
      const decorated = registry.decorate(debitCard({ issuer: { name: 'Itau' } }));

      expect(decorated.name).toBe('Itau Débito');
    });

    it('Given a Mercado Pago credit card on a blue site (v2.1), When decorated, Then it gets the MP credit name and blue icon (RN-7)', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLA', variant: 'v2.1' }));
      const decorated = registry.decorate(creditCard({ issuer: { name: 'Mercado Pago' } }));

      expect(decorated.name).toBe('Cartao Mercado Pago');
      expect(decorated.thumbnail).toBe('/mp-blue.png');
    });

    it('Given a Mercado Pago credit card on a dark site (v2.1), When decorated, Then it gets the dark icon (RN-7)', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLB', variant: 'v2.1' }));
      const decorated = registry.decorate(creditCard({ issuer: { name: 'Mercado Pago' } }));

      expect(decorated.thumbnail).toBe('/mp-dark.png');
    });

    it('Given a Mercado Pago credit card on v2, When decorated, Then it stays a regular issuer card (no MP name/icon)', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLA', variant: 'v2' }));
      const decorated = registry.decorate(creditCard({ issuer: { name: 'Mercado Pago' } }));

      expect(decorated.name).toBe('Mercado Pago Crédito');
      expect(decorated.thumbnail).toBe('/white.png');
    });

    it('Given a Mercado Pago prepaid card, When decorated, Then it gets both the MP name and the prepaid thumbnail (cumulative)', () => {
      const registry = createPaymentMethodRegistry(
        buildConfig({ thumbnails: { paymentMethodsThumbnails: { pp1: '/prepaid-brand.png' } } }),
      );
      const decorated = registry.decorate(
        prepaidCard({ id: 'pp1', issuer: { name: 'Mercado Pago' } }),
      );

      expect(decorated.name).toBe('Mercado Pago');
      expect(decorated.thumbnail).toBe('/prepaid-brand.png');
    });

    it('Given a per-id thumbnail override, When decorating a prepaid card, Then the override wins over the white fallback', () => {
      const registry = createPaymentMethodRegistry(
        buildConfig({ thumbnails: { paymentMethodsThumbnails: { pp1: '/override.png' } } }),
      );
      const decorated = registry.decorate(prepaidCard({ id: 'pp1', issuer: { name: 'Bank' } }));

      expect(decorated.thumbnail).toBe('/override.png');
    });
  });

  describe('decorateAccountPaymentMethods', () => {
    it('Given a mixed list, When decorated, Then every method gets its display name and thumbnail', () => {
      const registry = createPaymentMethodRegistry(buildConfig({ siteId: 'MLB' }));
      const [decoratedCredit, decoratedAccountMoney] = registry.decorateAccountPaymentMethods([
        creditCard({ issuer: { name: 'Itau' } }),
        accountMoney(),
      ]);

      expect(decoratedCredit.name).toBe('Itau Crédito');
      expect(decoratedAccountMoney.name).toBe('Dinheiro na conta');
    });
  });
});

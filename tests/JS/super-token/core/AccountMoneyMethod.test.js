const { AccountMoneyMethod } = require('@super-token/core/paymentMethods/AccountMoneyMethod');
const {
  BasePaymentMethodWithInstallments,
} = require('@super-token/core/paymentMethods/BasePaymentMethodWithInstallments');
const { buildConfig, accountMoney, creditCard } = require('./fixtures');

describe('AccountMoneyMethod', () => {
  describe('matches — recognizes only its own payment-method type', () => {
    it('Given account money, When matched, Then it returns true', () => {
      expect(new AccountMoneyMethod(buildConfig()).matches(accountMoney())).toBe(true);
    });

    it('Given a non-account-money method, When matched, Then it returns false', () => {
      expect(new AccountMoneyMethod(buildConfig()).matches(creditCard())).toBe(false);
    });
  });

  describe('installments — only credit card and consumer credits offer them', () => {
    it('Given an account money module, When asked, Then it does not require installments', () => {
      expect(new AccountMoneyMethod(buildConfig()).requiresInstallments()).toBe(false);
    });

    it('Given an account money module, When inspected, Then it does not inherit installment behavior from BasePaymentMethodWithInstallments', () => {
      expect(new AccountMoneyMethod(buildConfig())).not.toBeInstanceOf(
        BasePaymentMethodWithInstallments,
      );
    });
  });

  describe('requiresCvv', () => {
    it('Given account money (no security code settings), When asked, Then no CVV is required', () => {
      expect(new AccountMoneyMethod(buildConfig()).requiresCvv(accountMoney())).toBe(false);
    });
  });

  describe('decorate — sets the display name and thumbnail', () => {
    it('Given account money outside Mexico, When decorated, Then it gets the wallet icon and the generic name', () => {
      const decorated = new AccountMoneyMethod(buildConfig({ siteId: 'MLB' })).decorate(
        accountMoney(),
      );

      expect(decorated.thumbnail).toBe('/wallet.png');
      expect(decorated.name).toBe('Dinheiro na conta');
    });

    it('Given Mexico with balance and investment, When decorated, Then it uses the combined name', () => {
      const decorated = new AccountMoneyMethod(buildConfig({ siteId: 'MLM' })).decorate(
        accountMoney({ has_account_money: true, has_account_money_invested: true }),
      );

      expect(decorated.name).toBe('Disponible e invertido');
    });

    it('Given Mexico with balance only, When decorated, Then it uses the wallet name', () => {
      const decorated = new AccountMoneyMethod(buildConfig({ siteId: 'MLM' })).decorate(
        accountMoney({ has_account_money: true, has_account_money_invested: false }),
      );

      expect(decorated.name).toBe('Disponible');
    });

    it('Given Mexico with investment only, When decorated, Then it uses the investment name', () => {
      const decorated = new AccountMoneyMethod(buildConfig({ siteId: 'MLM' })).decorate(
        accountMoney({ has_account_money: false, has_account_money_invested: true }),
      );

      expect(decorated.name).toBe('Invertido');
    });

    it('Given Mexico with neither balance nor investment, When decorated, Then it uses the available name', () => {
      const decorated = new AccountMoneyMethod(buildConfig({ siteId: 'MLM' })).decorate(
        accountMoney({ has_account_money: false, has_account_money_invested: false }),
      );

      expect(decorated.name).toBe('En cuenta');
    });

    it('Given a non-account-money method, When decorated, Then it is returned unchanged', () => {
      const card = creditCard({ name: 'untouched' });
      const decorated = new AccountMoneyMethod(buildConfig()).decorate(card);

      expect(decorated).toBe(card);
      expect(decorated.name).toBe('untouched');
    });
  });
});

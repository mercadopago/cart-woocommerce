const { V2AccountMoneyDecoration } = require('@super-token/adapters/view/v2/AccountMoneyDecoration');
const { V21_STYLES } = require('@super-token/adapters/view/v2.1/styles');

describe('V2AccountMoneyDecoration (RN-2: v2 has no account-money decoration)', () => {
  it('Given an account-money row, When decorated, Then no balance line is added (no-op)', () => {
    const decoration = new V2AccountMoneyDecoration();
    const row = document.createElement('article');
    row.dataset.type = 'account_money';

    expect(() => decoration.decorate(row)).not.toThrow();
    expect(row.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)).toBeNull();
  });

  it('Given a container, When the decoration is cleared, Then it is a safe no-op', () => {
    const decoration = new V2AccountMoneyDecoration();
    const container = document.createElement('div');

    expect(() => decoration.clear(container)).not.toThrow();
  });
});

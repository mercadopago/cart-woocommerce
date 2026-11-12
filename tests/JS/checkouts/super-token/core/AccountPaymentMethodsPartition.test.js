const {
  partitionAccountPaymentMethods,
} = require('@super-token/core/checkoutSession/AccountPaymentMethodsPartition');

describe('partitionAccountPaymentMethods', () => {
  it('supports Super Token and Smart Pix in the same SDK response without cross-rendering', () => {
    const savedCard = { type: 'credit_card', token: 'card-token' };
    const smartTransfer = {
      id: 'smart-transfer-bb',
      name: 'Banco do Brasil',
      thumbnail: 'https://http2.mlstatic.com/storage/account-data/bb.png',
      token: 'smart-transfer-token',
      type: 'smart_transfer',
      open_finance_account_id: 'open-finance-account',
    };

    expect(partitionAccountPaymentMethods([savedCard, smartTransfer])).toEqual({
      superToken: [savedCard],
      smartTransfer: [smartTransfer],
    });
  });

  it('fails closed for unknown types and malformed smart_transfer entries', () => {
    expect(partitionAccountPaymentMethods([
      { type: 'future_method' },
      { type: 'smart_transfer' },
      {
        id: '',
        name: 'Banco do Brasil',
        thumbnail: '',
        token: 'smart-transfer-token',
        type: 'smart_transfer',
        open_finance_account_id: 'open-finance-account',
      },
      {
        id: 'smart-transfer-bb',
        name: 'Banco do Brasil',
        thumbnail: '',
        token: '',
        type: 'smart_transfer',
        open_finance_account_id: 'open-finance-account',
      },
      {
        id: 'smart-transfer-bb',
        name: 'Banco do Brasil',
        thumbnail: '',
        token: 'smart-transfer-token',
        type: 'smart_transfer',
        open_finance_account_id: '',
      },
    ])).toEqual({
      superToken: [],
      smartTransfer: [],
    });
  });
});

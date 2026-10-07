const { resolveMlcCopy } = require('@super-token/adapters/platform/resolveMlcCopy');

const BUNDLE_ACCOUNT_MONEY_TEXT = 'Dinero disponible en Mercado Pago';
const BUNDLE_BANK_INTEREST_HINT_TEXT = 'Si hay intereses, los aplicará y cobrará tu banco.';

describe('resolveMlcCopy', () => {
  it('Given a non-MLC site, When resolved, Then the same params are returned unchanged', () => {
    const params = { site_id: 'MLB', account_money_text: 'Dinheiro na conta' };

    expect(resolveMlcCopy(params)).toBe(params);
  });

  it('Given MLC on an older plugin, When resolved, Then the bundle copy replaces generic plugin copy', () => {
    const params = {
      site_id: 'MLC',
      account_money_text: 'Texto genérico es.mo',
    };

    const resolved = resolveMlcCopy(params);

    expect(resolved.account_money_text).toBe(BUNDLE_ACCOUNT_MONEY_TEXT);
  });

  it('Given MLC with a plugin locale marker, When resolved, Then the bundle copy still wins', () => {
    const params = {
      site_id: 'mlc',
      super_token_copy_locale: 'es_CL',
      account_money_text: 'Dinero disponible en tu cuenta de Mercado Pago',
    };

    const resolved = resolveMlcCopy(params);

    expect(resolved.account_money_text).toBe(BUNDLE_ACCOUNT_MONEY_TEXT);
  });

  it('Given MLC with empty plugin copy, When resolved, Then the bundle copy is used', () => {
    const params = {
      site_id: 'MLC',
      account_money_text: '',
    };

    const resolved = resolveMlcCopy(params);

    expect(resolved.account_money_text).toBe(BUNDLE_ACCOUNT_MONEY_TEXT);
  });

  it('Given nested installment copy, When resolved, Then interest_free_option_text and bank_interest_hint_text are mapped under input_helper_message.installments', () => {
    const params = {
      site_id: 'MLC',
      input_helper_message: {
        someOtherKey: 'kept',
        installments: { unrelatedKey: 'kept-too' },
      },
    };

    const resolved = resolveMlcCopy(params);

    expect(resolved.input_helper_message).toEqual({
      someOtherKey: 'kept',
      installments: {
        unrelatedKey: 'kept-too',
        interest_free_option_text: 'sin interés',
        bank_interest_hint_text: BUNDLE_BANK_INTEREST_HINT_TEXT,
      },
    });
  });
});

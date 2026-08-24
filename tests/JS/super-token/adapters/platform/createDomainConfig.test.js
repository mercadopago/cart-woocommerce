const { createDomainConfig } = require('@super-token/adapters/platform/createDomainConfig');

const params = {
  site_id: 'mlb',
  intl: 'pt-BR',
  currency: 'BRL',
  payment_methods_order: 'account_money_first',
  account_money_text: 'Dinheiro na conta',
  account_money_wallet_with_investment_text: 'disponível e investido',
  account_money_wallet_text: 'disponível',
  account_money_investment_text: 'investido',
  account_money_available_text: 'disponível',
  mercado_pago_card_name: 'Mercado Pago',
  mercado_pago_credit_card_name: 'Crédito Mercado Pago',
  last_digits_text: 'terminado em',
  interest_free_part_one_text: 'Até',
  interest_free_part_two_text: 'sem juros',
  interest_free_option_text: '(sem juros)',
  interest_rate_mlb_text: 'Taxa de juros',
  effective_total_cost_mlb_text: 'CET',
  iof_mlb_text: 'IOF',
  borrowed_amount_mlb_text: 'Valor emprestado',
  per_month: 'a.m.',
  per_year: 'a.a.',
  cat_mlm_text: 'CAT',
  no_iva_text: 'sin IVA',
  tna_mlm_text: 'TNA',
  system_amortization_mlm_text: 'Sistema de amortización',
  cftea_mla_text: 'CFTEA',
  tna_mla_text: 'TNA',
  tea_mla_text: 'TEA',
  fixed_rate_text: 'Tasa fija',
  payment_methods_thumbnails: { visa: 'visa.png' },
  white_card_path: 'white.png',
  yellow_wallet_path: 'wallet.png',
  yellow_money_path: 'money.png',
  mp_logo_blue_path: 'blue.png',
  mp_logo_dark_path: 'dark.png',
};

describe('createDomainConfig', () => {
  it('Given the localized params, When mapped, Then the site id is uppercased and copy/thumbnails are mapped', () => {
    const config = createDomainConfig(params, 'v2.1');

    expect(config.siteId).toBe('MLB');
    expect(config.intl).toBe('pt-BR');
    expect(config.currency).toBe('BRL');
    expect(config.paymentMethodsOrder).toBe('account_money_first');
    expect(config.variant).toBe('v2.1');
    expect(config.copy).toEqual({
      accountMoneyText: 'Dinheiro na conta',
      accountMoneyWalletWithInvestmentText: 'disponível e investido',
      accountMoneyWalletText: 'disponível',
      accountMoneyInvestmentText: 'investido',
      accountMoneyAvailableText: 'disponível',
      mercadoPagoCardName: 'Mercado Pago',
      mercadoPagoCreditCardName: 'Crédito Mercado Pago',
      lastDigitsText: 'terminado em',
      interestFreePartOneText: 'Até',
      interestFreePartTwoText: 'sem juros',
      installmentsInterestFreeOptionText: '(sem juros)',
      consumerCreditsHint: {
        interestRateMlb: 'Taxa de juros',
        effectiveTotalCostMlb: 'CET',
        iofMlb: 'IOF',
        borrowedAmountMlb: 'Valor emprestado',
        perMonth: 'a.m.',
        perYear: 'a.a.',
        catMlm: 'CAT',
        noIvaMlm: 'sin IVA',
        tnaMlm: 'TNA',
        systemAmortizationMlm: 'Sistema de amortización',
        cfteaMla: 'CFTEA',
        tnaMla: 'TNA',
        teaMla: 'TEA',
        fixedRate: 'Tasa fija',
      },
    });
    expect(config.thumbnails).toEqual({
      paymentMethodsThumbnails: { visa: 'visa.png' },
      whiteCardPath: 'white.png',
      yellowWalletPath: 'wallet.png',
      yellowMoneyPath: 'money.png',
      mpLogoBluePath: 'blue.png',
      mpLogoDarkPath: 'dark.png',
    });
  });

  it('Given interest_free_option_text only nested (older-plugin localized contract), When mapped, Then the nested value is used', () => {
    const { interest_free_option_text, ...withoutTopLevel } = params;
    const legacyParams = {
      ...withoutTopLevel,
      input_helper_message: { installments: { interest_free_option_text: 'sem juros (aninhado)' } },
    };

    const config = createDomainConfig(legacyParams, 'v2');

    expect(config.copy.installmentsInterestFreeOptionText).toBe('sem juros (aninhado)');
  });

  it('Given retro-compat keys absent from an older plugin version, When mapped, Then they coalesce to empty strings instead of undefined', () => {
    const {
      interest_free_option_text,
      mercado_pago_credit_card_name,
      mp_logo_blue_path,
      mp_logo_dark_path,
      ...older
    } = params;

    const config = createDomainConfig(older, 'v2');

    expect(config.copy.installmentsInterestFreeOptionText).toBe('');
    expect(config.copy.mercadoPagoCreditCardName).toBe('');
    expect(config.thumbnails.mpLogoBluePath).toBe('');
    expect(config.thumbnails.mpLogoDarkPath).toBe('');
  });
});

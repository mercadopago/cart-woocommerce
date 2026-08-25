const { createVariantViewDeps } = require('@super-token/adapters/view/createVariantViewDeps');
const { buildEmailListener } = require('./fixtures');

const buildParams = (overrides = {}) => ({
  site_id: 'mlb',
  payment_methods_list_text: 'Meios de pagamento',
  saved_cards_title: 'Cartões salvos',
  saved_card_title: 'Cartão salvo',
  mp_methods_title: 'Com Mercado Pago',
  saved_payment_method_title: 'Meio salvo',
  account_money_balance_text: 'Saldo',
  mercado_pago_card_name: 'Mercado Pago',
  mercado_pago_credit_card_name: 'Cartão de crédito Mercado Pago',
  last_digits_text: 'final',
  interest_free_part_one_text: 'em',
  interest_free_part_two_text: 'sem juros',
  account_money_text: 'Dinheiro na conta',
  account_money_wallet_with_investment_text: 'Disponível e investido',
  account_money_wallet_text: 'Disponível',
  account_money_investment_text: 'Investido',
  account_money_available_text: 'Em conta',
  new_mp_logo_path: '/new-mp-logo.png',
  mp_logo_blue_path: '/mp-blue.png',
  mp_logo_dark_path: '/mp-dark.png',
  white_card_path: '/white.png',
  yellow_wallet_path: '/wallet.png',
  yellow_money_path: '/money.png',
  payment_methods_thumbnails: { visa: '/visa.png' },
  current_user_email: 'buyer@example.com',
  input_title: { installments: 'Parcelas' },
  input_helper_message: { installments: { required: 'Selecione as parcelas' } },
  security_code_input_title_text: 'Código de segurança',
  security_code_tooltip_text_3_digits: '3 dígitos no verso',
  security_code_tooltip_text_4_digits: '4 dígitos na frente',
  placeholders: { installments: 'Selecione as parcelas' },
  consumer_credits_due_date: 'Primeira parcela em',
  mlb_installment_debit_auto_text: 'Débito automático',
  months_abbreviated: { jan: 'jan', dec: 'dez' },
  ...overrides,
});

describe('createVariantViewDeps', () => {
  it('Given the localized params, When mapped, Then every copy/thumbnail field lands on the deps and siteId is uppercased', () => {
    const deps = createVariantViewDeps(buildParams(), null);

    expect(deps.siteId).toBe('MLB');
    expect(deps.currentUserEmail).toBe('buyer@example.com');
    expect(deps.copy).toEqual({
      paymentMethodsListText: 'Meios de pagamento',
      savedCardsTitle: 'Cartões salvos',
      savedCardTitle: 'Cartão salvo',
      mpMethodsTitle: 'Com Mercado Pago',
      savedPaymentMethodTitle: 'Meio salvo',
      accountMoneyBalanceText: 'Saldo',
      mercadoPagoCardName: 'Mercado Pago',
      mercadoPagoCreditCardName: 'Cartão de crédito Mercado Pago',
      lastDigitsText: 'final',
      interestFreePartOneText: 'em',
      interestFreePartTwoText: 'sem juros',
      accountMoneyText: 'Dinheiro na conta',
      accountMoneyWalletWithInvestmentText: 'Disponível e investido',
      accountMoneyWalletText: 'Disponível',
      accountMoneyInvestmentText: 'Investido',
      accountMoneyAvailableText: 'Em conta',
      installmentsInputTitle: 'Parcelas',
      installmentsRequiredMessage: 'Selecione as parcelas',
      securityCodeInputTitle: 'Código de segurança',
      securityCodeTooltip3Digits: '3 dígitos no verso',
      securityCodeTooltip4Digits: '4 dígitos na frente',
      installmentsPlaceholder: 'Selecione as parcelas',
      consumerCreditsDueDateText: 'Primeira parcela em',
      consumerCreditsDebitAutoText: 'Débito automático',
    });
    expect(deps.monthsAbbreviated).toEqual({ jan: 'jan', dec: 'dez' });
    expect(deps.thumbnails).toEqual({
      newMpLogoPath: '/new-mp-logo.png',
      mpLogoBluePath: '/mp-blue.png',
      mpLogoDarkPath: '/mp-dark.png',
      whiteCardPath: '/white.png',
      yellowWalletPath: '/wallet.png',
      yellowMoneyPath: '/money.png',
      paymentMethodsThumbnails: { visa: '/visa.png' },
    });
  });

  it('Given an e-mail listener, When mapped, Then it is passed through onto the deps', () => {
    const emailListener = buildEmailListener();

    const deps = createVariantViewDeps(buildParams(), emailListener);

    expect(deps.emailListener).toBe(emailListener);
  });

  it('Given no e-mail listener, When mapped, Then emailListener is null', () => {
    const deps = createVariantViewDeps(buildParams(), null);

    expect(deps.emailListener).toBeNull();
  });

  it('Given saved-methods titles, credit-card name and logos absent from an older plugin version, When mapped, Then they coalesce to empty strings instead of undefined', () => {
    const deps = createVariantViewDeps(
      buildParams({
        saved_cards_title: undefined,
        saved_card_title: undefined,
        mp_methods_title: undefined,
        saved_payment_method_title: undefined,
        account_money_balance_text: undefined,
        mercado_pago_credit_card_name: undefined,
        mp_logo_blue_path: undefined,
        mp_logo_dark_path: undefined,
      }),
      null,
    );

    expect(deps.copy.savedCardsTitle).toBe('');
    expect(deps.copy.savedCardTitle).toBe('');
    expect(deps.copy.mpMethodsTitle).toBe('');
    expect(deps.copy.savedPaymentMethodTitle).toBe('');
    expect(deps.copy.accountMoneyBalanceText).toBe('');
    expect(deps.copy.mercadoPagoCreditCardName).toBe('');
    expect(deps.thumbnails.mpLogoBluePath).toBe('');
    expect(deps.thumbnails.mpLogoDarkPath).toBe('');
  });
});

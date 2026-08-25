/**
 * Shared builders for the variant-view tests: a VariantViewDeps value object and a fake
 * WCEmailListener. Payment method builders are reused from the core fixtures.
 */

const buildViewDeps = (overrides = {}) => {
  const base = {
    siteId: 'MLB',
    currentUserEmail: 'buyer@example.com',
    emailListener: null,
    monthsAbbreviated: { jan: 'jan', feb: 'fev', mar: 'mar', apr: 'abr', may: 'mai', jun: 'jun', jul: 'jul', aug: 'ago', sep: 'set', oct: 'out', nov: 'nov', dec: 'dez' },
    copy: {
      paymentMethodsListText: 'Meios de pagamento',
      savedCardsTitle: 'Cartões salvos',
      savedCardTitle: 'Cartão salvo',
      mpMethodsTitle: 'Com Mercado Pago',
      savedPaymentMethodTitle: 'Meio de pagamento salvo',
      accountMoneyBalanceText: 'Saldo disponível',
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
    },
    thumbnails: {
      newMpLogoPath: '/new-mp-logo.png',
      mpLogoBluePath: '/mp-blue.png',
      mpLogoDarkPath: '/mp-dark.png',
      whiteCardPath: '/white.png',
      yellowWalletPath: '/wallet.png',
      yellowMoneyPath: '/money.png',
      paymentMethodsThumbnails: {},
    },
  };
  return {
    ...base,
    ...overrides,
    copy: { ...base.copy, ...(overrides.copy || {}) },
    thumbnails: { ...base.thumbnails, ...(overrides.thumbnails || {}) },
  };
};

const buildEmailListener = (overrides = {}) => ({
  isValid: overrides.isValid ?? ((email) => !!email && email.includes('@')),
  getEmail: overrides.getEmail ?? (() => 'buyer@example.com'),
  onEmailChange: overrides.onEmailChange ?? (() => {}),
});

module.exports = { buildViewDeps, buildEmailListener };

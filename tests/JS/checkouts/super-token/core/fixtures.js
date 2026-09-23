/**
 * Shared builders for the Super Token domain tests: a domain config and raw payment
 * methods matching the SDK shapes. Deep-merges copy/thumbnails so a test can override
 * a single field without restating the whole object.
 */

const baseConfig = () => ({
  siteId: 'MLB',
  intl: 'pt-BR',
  currency: 'BRL',
  paymentMethodsOrder: 'cards_first',
  variant: 'v2',
  copy: {
    accountMoneyText: 'Dinheiro na conta',
    accountMoneyWalletWithInvestmentText: 'Disponible e invertido',
    accountMoneyWalletText: 'Disponible',
    accountMoneyInvestmentText: 'Invertido',
    accountMoneyAvailableText: 'En cuenta',
    mercadoPagoCardName: 'Mercado Pago',
    mercadoPagoCreditCardName: 'Cartao Mercado Pago',
    lastDigitsText: 'final',
    interestFreePartOneText: 'em',
    interestFreePartTwoText: 'sem juros',
    installmentsInterestFreeOptionText: 'sem juros',
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
  },
  thumbnails: {
    paymentMethodsThumbnails: {},
    whiteCardPath: '/white.png',
    yellowWalletPath: '/wallet.png',
    yellowMoneyPath: '/money.png',
    mpLogoBluePath: '/mp-blue.png',
    mpLogoDarkPath: '/mp-dark.png',
  },
});

const buildConfig = (overrides = {}) => {
  const base = baseConfig();
  return {
    ...base,
    ...overrides,
    copy: { ...base.copy, ...(overrides.copy || {}) },
    thumbnails: { ...base.thumbnails, ...(overrides.thumbnails || {}) },
  };
};

const installment = (overrides = {}) => ({
  installments: 1,
  installment_amount: 100,
  installment_rate: 0,
  installment_rate_collector: ['MERCADOPAGO'],
  total_amount: 100,
  ...overrides,
});

const creditCard = (overrides = {}) => ({
  id: 'cc1',
  token: 'tok-cc',
  name: '',
  thumbnail: '',
  type: 'credit_card',
  issuer: { name: 'Itau' },
  card: { card_number: { last_four_digits: '1234' } },
  security_code_settings: { mode: 'mandatory', length: 3 },
  ...overrides,
});

const debitCard = (overrides = {}) => ({
  id: 'dc1',
  token: 'tok-dc',
  name: '',
  thumbnail: '',
  type: 'debit_card',
  issuer: { name: 'Itau' },
  card: { card_number: { last_four_digits: '5678' } },
  security_code_settings: { mode: 'mandatory', length: 3 },
  ...overrides,
});

const prepaidCard = (overrides = {}) => ({
  id: 'pp1',
  token: 'tok-pp',
  name: '',
  thumbnail: '',
  type: 'prepaid_card',
  issuer: { name: 'Some Bank' },
  card: { card_number: { last_four_digits: '9012' } },
  ...overrides,
});

const accountMoney = (overrides = {}) => ({
  id: 'am1',
  token: 'tok-am',
  name: '',
  thumbnail: '',
  type: 'account_money',
  has_account_money: true,
  has_account_money_invested: false,
  ...overrides,
});

const consumerCredits = (overrides = {}) => ({
  id: 'coc1',
  token: 'tok-coc',
  name: '',
  thumbnail: '',
  type: 'digital_currency',
  credits_pricing_id: 'cp1',
  ...overrides,
});

const newCard = (overrides = {}) => ({
  id: 'new',
  type: 'new_card',
  ...overrides,
});

module.exports = {
  buildConfig,
  installment,
  creditCard,
  debitCard,
  prepaidCard,
  accountMoney,
  consumerCredits,
  newCard,
};

/** Chilean checkout copy shipped with the CDN bundle for existing plugin installations. */
const MLC_COPY = {
  accountMoneyText: 'Dinero disponible en Mercado Pago',
  accountMoneyBalanceText: 'Suficiente para pagar esta compra.',
  interestFreePartOneText: 'Hasta',
  interestFreePartTwoText: 'cuotas sin interés',
  interestFreeOptionText: 'sin interés',
  bankInterestHintText: 'Si hay intereses, los aplicará y cobrará tu banco.',
} as const;

interface MlcCopyParams {
  site_id: string;
  account_money_text?: string;
  account_money_balance_text?: string;
  interest_free_part_one_text?: string;
  interest_free_part_two_text?: string;
  interest_free_option_text?: string;
  input_helper_message?: {
    installments?: {
      interest_free_option_text?: string;
      bank_interest_hint_text?: string;
      [key: string]: unknown;
    };
    [key: string]: unknown;
  };
}

/** Preserve all other sites and all unrelated localized params unchanged. */
export function resolveMlcCopy<T extends { site_id: string }>(params: T): T {
  if (typeof params.site_id !== 'string' || params.site_id.toUpperCase() !== 'MLC') {
    return params;
  }

  const source = params as T & MlcCopyParams;
  const helper = source.input_helper_message ?? {};
  const installments = helper.installments ?? {};
  const interestFreeOptionText = MLC_COPY.interestFreeOptionText;

  return {
    ...params,
    account_money_text: MLC_COPY.accountMoneyText,
    account_money_balance_text: MLC_COPY.accountMoneyBalanceText,
    interest_free_part_one_text: MLC_COPY.interestFreePartOneText,
    interest_free_part_two_text: MLC_COPY.interestFreePartTwoText,
    interest_free_option_text: interestFreeOptionText,
    input_helper_message: {
      ...helper,
      installments: {
        ...installments,
        interest_free_option_text: interestFreeOptionText,
        bank_interest_hint_text: MLC_COPY.bankInterestHintText,
      },
    },
  } as T;
}

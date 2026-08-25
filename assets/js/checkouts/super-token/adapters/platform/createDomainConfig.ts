/**
 * Composition-root mapper: turns the localized store params
 * (`wc_mercadopago_supertoken_bundle_params`) into the domain's `SuperTokenDomainConfig`
 * value object, so the core (PaymentMethodCatalog + PaymentMethodRegistry) never reads
 * `window.*`. The field mapping mirrors the legacy controller's SCREAMING_CASE reads
 * (`super-token-payment-methods.js` constructor) 1:1.
 */

import type { SuperTokenDomainConfig } from '@super-token/core/config';

/**
 * The subset of `wc_mercadopago_supertoken_bundle_params` the domain config is built from.
 * Grounded in `getSuperTokenLocalizeData()` (CustomGateway.php) and the legacy controller reads.
 */
export interface SuperTokenDomainParams {
  site_id: string;
  intl: string;
  currency: string;
  payment_methods_order?: string;
  account_money_text: string;
  account_money_wallet_with_investment_text: string;
  account_money_wallet_text: string;
  account_money_investment_text: string;
  account_money_available_text: string;
  mercado_pago_card_name: string;
  mercado_pago_credit_card_name?: string;
  last_digits_text: string;
  interest_free_part_one_text: string;
  interest_free_part_two_text: string;
  interest_free_option_text?: string;
  input_helper_message?: {
    installments?: {
      interest_free_option_text?: string;
    };
  };
  interest_rate_mlb_text: string;
  effective_total_cost_mlb_text: string;
  iof_mlb_text: string;
  borrowed_amount_mlb_text: string;
  per_month: string;
  per_year: string;
  cat_mlm_text: string;
  no_iva_text: string;
  tna_mlm_text: string;
  system_amortization_mlm_text: string;
  cftea_mla_text: string;
  tna_mla_text: string;
  tea_mla_text: string;
  fixed_rate_text: string;
  payment_methods_thumbnails: Record<string, string>;
  white_card_path: string;
  yellow_wallet_path: string;
  yellow_money_path: string;
  mp_logo_blue_path: string;
  mp_logo_dark_path: string;
}

export function createDomainConfig(
  params: SuperTokenDomainParams,
  variant: string,
): SuperTokenDomainConfig {
  return {
    siteId: params.site_id.toUpperCase(),
    intl: params.intl,
    currency: params.currency,
    paymentMethodsOrder: params.payment_methods_order,
    variant,
    // This mapper is the domain's boundary against the localized store params. Older plugin
    // versions still served the CDN bundle (< 8.8.0 for saved-methods/logo keys) may omit some
    // keys, so coalesce here to keep a literal "undefined" out of the rendered UI. The core
    // consumers stay clean of retro-compat concerns.
    copy: {
      accountMoneyText: params.account_money_text,
      accountMoneyWalletWithInvestmentText: params.account_money_wallet_with_investment_text,
      accountMoneyWalletText: params.account_money_wallet_text,
      accountMoneyInvestmentText: params.account_money_investment_text,
      accountMoneyAvailableText: params.account_money_available_text,
      mercadoPagoCardName: params.mercado_pago_card_name,
      mercadoPagoCreditCardName: params.mercado_pago_credit_card_name ?? '',
      lastDigitsText: params.last_digits_text,
      interestFreePartOneText: params.interest_free_part_one_text,
      interestFreePartTwoText: params.interest_free_part_two_text,
      // Legacy contract exposes this only nested under input_helper_message.installments.
      installmentsInterestFreeOptionText:
        params.interest_free_option_text ??
        params.input_helper_message?.installments?.interest_free_option_text ??
        '',
      consumerCreditsHint: {
        interestRateMlb: params.interest_rate_mlb_text ?? '',
        effectiveTotalCostMlb: params.effective_total_cost_mlb_text ?? '',
        iofMlb: params.iof_mlb_text ?? '',
        borrowedAmountMlb: params.borrowed_amount_mlb_text ?? '',
        perMonth: params.per_month ?? '',
        perYear: params.per_year ?? '',
        catMlm: params.cat_mlm_text ?? '',
        noIvaMlm: params.no_iva_text ?? '',
        tnaMlm: params.tna_mlm_text ?? '',
        systemAmortizationMlm: params.system_amortization_mlm_text ?? '',
        cfteaMla: params.cftea_mla_text ?? '',
        tnaMla: params.tna_mla_text ?? '',
        teaMla: params.tea_mla_text ?? '',
        fixedRate: params.fixed_rate_text ?? '',
      },
    },
    thumbnails: {
      paymentMethodsThumbnails: params.payment_methods_thumbnails,
      whiteCardPath: params.white_card_path,
      yellowWalletPath: params.yellow_wallet_path,
      yellowMoneyPath: params.yellow_money_path,
      mpLogoBluePath: params.mp_logo_blue_path ?? '',
      mpLogoDarkPath: params.mp_logo_dark_path ?? '',
    },
  };
}

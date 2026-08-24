/**
 * Composition-root mapper: turns the localized store params
 * (`wc_mercadopago_supertoken_bundle_params`) into the view-local `VariantViewDeps` value
 * object, so the variant views never read `window.*`. The field mapping mirrors the legacy
 * controller's SCREAMING_CASE reads (`super-token-payment-methods.js` constructor, 67-123) 1:1.
 * The live e-mail listener is injected separately (it is a runtime instance, not a param).
 */

import type { VariantViewDeps, EmailListenerPort } from './VariantViewDeps';

/**
 * The subset of `wc_mercadopago_supertoken_bundle_params` the variant views are built from.
 * Grounded in the legacy controller reads (payment-methods.js:67-123).
 */
export interface SuperTokenViewParams {
  site_id: string;
  payment_methods_list_text: string;
  saved_cards_title?: string;
  saved_card_title?: string;
  mp_methods_title?: string;
  saved_payment_method_title?: string;
  account_money_balance_text?: string;
  mercado_pago_card_name: string;
  mercado_pago_credit_card_name?: string;
  last_digits_text: string;
  interest_free_part_one_text: string;
  interest_free_part_two_text: string;
  account_money_text: string;
  account_money_wallet_with_investment_text: string;
  account_money_wallet_text: string;
  account_money_investment_text: string;
  account_money_available_text: string;
  new_mp_logo_path: string;
  mp_logo_blue_path?: string;
  mp_logo_dark_path?: string;
  white_card_path: string;
  yellow_wallet_path: string;
  yellow_money_path: string;
  payment_methods_thumbnails: Record<string, string>;
  current_user_email: string;
  input_title: { installments: string };
  input_helper_message: { installments: { required: string } };
  security_code_input_title_text: string;
  security_code_tooltip_text_3_digits: string;
  security_code_tooltip_text_4_digits: string;
  placeholders: { installments: string };
  consumer_credits_due_date: string;
  mlb_installment_debit_auto_text: string;
  months_abbreviated: Record<string, string>;
}

export function createVariantViewDeps(
  params: SuperTokenViewParams,
  emailListener: EmailListenerPort | null,
): VariantViewDeps {
  return {
    // Uppercased once here so the views compare it directly (VariantViewDeps contract).
    siteId: params.site_id.toUpperCase(),
    // Boundary against the localized store params: older plugin versions still served the CDN
    // bundle (saved-methods titles, credit-card name and logo paths landed in 8.8.0) may omit
    // these keys, so coalesce to keep a literal "undefined" out of the rendered titles/images.
    copy: {
      paymentMethodsListText: params.payment_methods_list_text,
      savedCardsTitle: params.saved_cards_title ?? '',
      savedCardTitle: params.saved_card_title ?? '',
      mpMethodsTitle: params.mp_methods_title ?? '',
      savedPaymentMethodTitle: params.saved_payment_method_title ?? '',
      accountMoneyBalanceText: params.account_money_balance_text ?? '',
      mercadoPagoCardName: params.mercado_pago_card_name,
      mercadoPagoCreditCardName: params.mercado_pago_credit_card_name ?? '',
      lastDigitsText: params.last_digits_text,
      interestFreePartOneText: params.interest_free_part_one_text,
      interestFreePartTwoText: params.interest_free_part_two_text,
      accountMoneyText: params.account_money_text,
      accountMoneyWalletWithInvestmentText: params.account_money_wallet_with_investment_text,
      accountMoneyWalletText: params.account_money_wallet_text,
      accountMoneyInvestmentText: params.account_money_investment_text,
      accountMoneyAvailableText: params.account_money_available_text,
      installmentsInputTitle: params.input_title.installments,
      installmentsRequiredMessage: params.input_helper_message.installments.required,
      securityCodeInputTitle: params.security_code_input_title_text,
      securityCodeTooltip3Digits: params.security_code_tooltip_text_3_digits,
      securityCodeTooltip4Digits: params.security_code_tooltip_text_4_digits,
      installmentsPlaceholder: params.placeholders.installments,
      consumerCreditsDueDateText: params.consumer_credits_due_date,
      consumerCreditsDebitAutoText: params.mlb_installment_debit_auto_text,
    },
    thumbnails: {
      newMpLogoPath: params.new_mp_logo_path,
      mpLogoBluePath: params.mp_logo_blue_path ?? '',
      mpLogoDarkPath: params.mp_logo_dark_path ?? '',
      whiteCardPath: params.white_card_path,
      yellowWalletPath: params.yellow_wallet_path,
      yellowMoneyPath: params.yellow_money_path,
      paymentMethodsThumbnails: params.payment_methods_thumbnails,
    },
    emailListener,
    currentUserEmail: params.current_user_email,
    monthsAbbreviated: params.months_abbreviated,
  };
}

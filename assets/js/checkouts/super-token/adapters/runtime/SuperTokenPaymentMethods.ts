/**
 * Ported `MPSuperTokenPaymentMethods` (v2.1/entities/super-token-payment-methods.js) — the
 * published `window.mpSuperTokenPaymentMethods` instance that owns the saved-methods *state* (the
 * fetched methods, the fast payment token, the active/last/preloaded selections, the CVV field
 * handle, the per-error retry counters, the ESC selection generation and the once-guards) and the
 * *primitives* the checkout uses to render, select, verify and reset those methods in the DOM.
 *
 * Only its saved-method selection orchestration delegates: `onSelectSuperTokenPaymentMethod` →
 * `SelectSavedPaymentMethod`, driven through `LegacySelectionSession` with `this` as the primitive
 * source (the same pattern as the ported trigger handler/authenticator). The legacy seam check
 * (`typeof window.mpSuperTokenSelectPaymentMethod === 'function'`) and its inline fallback collapse
 * away: the entity *is* the implementation, so it calls the use case (and its own methods) directly.
 *
 * The render *orchestration* delegates too: `onCustomCheckoutWasRendered` builds the DOM shell
 * (wallet/flags hidden, area converted, horizontal row + privacy footer, new-card accordion,
 * focus, animation) and hands the saved-methods list to the injected `renderSavedMethods` view —
 * the same collapse of the legacy `window.mpSuperTokenRenderSavedMethods` seam. The legacy row/
 * block builders (`createPaymentMethodElement`, the detail/installment/security-code HTML builders
 * and their row helpers, plus `organize/reorder/normalize/group` and the block renderers) are
 * *not* ported: the view owns them (`adapters/view/**`, `buildPaymentMethodRow`), so keeping a copy
 * here would be dead code once the seam fallback is gone.
 *
 * This completes the port (slices 6a state/selection/primitives, 6b submit validation/restore,
 * 6c render orchestration/shell). Inert until the flip.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity
 * with the legacy class. At the flip the bundle bootstrap constructs it with the ported TS
 * SDK/metrics/e-mail-listener collaborators and the localized bundle params, then publishes it
 * through `globalBridge.publish`.
 */
import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import { SelectSavedPaymentMethod } from '@super-token/useCases/SelectSavedPaymentMethod';
import {
  LegacySelectionSession,
  type LegacyPaymentMethodsController,
} from '@super-token/adapters/session/LegacySelectionSession';
import type {
  AccountMoneyPaymentMethod,
  ConsumerCreditsPaymentMethod,
  CreditCardPaymentMethod,
  DebitCardPaymentMethod,
  PaymentMethod,
  PrepaidCardPaymentMethod,
  RawAccountPaymentMethodsResponse,
  RawMpSdkInstance,
  RawSdkSecurityCodeField,
  RawSdkSecurityCodeValidity,
  SecurityCodeSettings,
} from '@super-token/types/external-globals';

/**
 * The refactored saved-methods view, injected at construction. Replaces the legacy
 * `window.mpSuperTokenRenderSavedMethods` seam (and its inline `organize` + e-mail-listener
 * fallback): the entity holds the view directly and calls it, so the seam check collapses away.
 * At the flip the composition root wires `createVariantView` as this port.
 */
export type RenderSavedMethods = (container: HTMLElement, paymentMethods: PaymentMethod[]) => void;

/** The subset of the e-mail listener the controller reads (block header + change listener). */
export interface PaymentMethodsEmailListener {
  isValid(email: string): boolean;
  getEmail(): string | null | undefined;
  onEmailChange(cb: (email: string, isValid: boolean) => void): void;
}

/** The subset of the metrics adapter the 6a subset emits through. */
export interface SuperTokenPaymentMethodsMetrics {
  sendMetric(name: string, value: string, message: string): void;
  registerSelectPaymentMethod(paymentMethodType: string): void;
  getPaymentMethodLoadingTime(identifier: string, seconds: string): void;
  fetchPaymentMethodSuccess(identifier: string, securityCodeRequired: boolean | null): void;
  hasEscNotExists(identifier: string): void;
  fetchPaymentMethodSkipped(identifier: string, reason: string): void;
  fetchPaymentMethodTimeout(identifier: string): void;
  getPaymentMethodFail(error: unknown, identifier: string): void;
  errorToMountCVVField(error: unknown, paymentMethod: PaymentMethod): void;
  updateSecurityCodeGetCardIdSuccess(): void;
  updateSecurityCodeCardTokenCreated(): void;
  updateSecurityCodePseudotokenUpdated(): void;
  updateSecurityCodeSuccess(): void;
  errorToUpdateSecurityCode(error: unknown, paymentMethod: PaymentMethod): void;
  errorToExcludeRecaptchaFromPreValidation(reason: string, error: unknown): void;
  captchaFieldToggledOnPreValidation(action: string, fieldName: string): void;
  errorToSubmitWithoutInstallmentSelected(paymentMethodType: string): void;
  getSdkInstanceId(): string;
  errorToRenderAccountPaymentMethods(error: unknown): void;
}

/** Localized `wc_mercadopago_supertoken_bundle_params` the controller reads at construction. */
export interface SuperTokenPaymentMethodsParams {
  yellow_wallet_path: string;
  yellow_money_path: string;
  white_card_path: string;
  payment_methods_list_text: string;
  payment_methods_list_alt_text: string;
  last_digits_text: string;
  new_card_text: string;
  account_money_text: string;
  account_money_wallet_with_investment_text: string;
  account_money_wallet_text: string;
  account_money_investment_text: string;
  account_money_available_text: string;
  interest_free_part_one_text: string;
  interest_free_part_two_text: string;
  input_helper_message: {
    installments: {
      bank_interest_hint_text: string;
      required: string;
      interest_free_option_text: string;
    };
    securityCode: Record<string, string>;
  };
  input_title: { installments: string };
  placeholders: { installments: string };
  security_code_input_title_text: string;
  security_code_placeholder_text_3_digits: string;
  security_code_placeholder_text_4_digits: string;
  security_code_tooltip_text_3_digits: string;
  security_code_tooltip_text_4_digits: string;
  site_id: string;
  currency: string;
  intl: string;
  mercado_pago_card_name: string;
  mercado_pago_credit_card_name: string;
  consumer_credits_due_date: string;
  mlb_installment_debit_auto_text: string;
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
  mercadopago_privacy_policy: string;
  new_mp_logo_path: string;
  mp_logo_blue_path: string;
  mp_logo_dark_path: string;
  saved_cards_title: string;
  saved_card_title: string;
  mp_methods_title: string;
  account_money_balance_text: string;
  saved_payment_method_title: string;
  current_user_email: string;
  months_abbreviated: Record<string, string>;
  payment_methods_thumbnails: Record<string, string>;
  payment_methods_order: string;
  update_security_code_with_retry_error_text: string;
  update_security_code_no_retry_error_text: string;
  authorize_payment_method_with_retry_error_text: string;
  authorize_payment_method_no_retry_error_text: string;
  select_payment_method_error_text: string;
}

/** jQuery's static shape the captcha pre-validation spy patches — narrowed from `window.jQuery`. */
type RecaptchaSerializeFn = { (this: unknown, ...args: unknown[]): string; __mpRecaptchaSpy?: boolean };
interface JQueryWithFn {
  fn?: { serialize: RecaptchaSerializeFn };
}

export class SuperTokenPaymentMethods implements LegacyPaymentMethodsController {
  SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
  CUSTOM_CHECKOUT_TYPE = 'custom';
  COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];
  CUSTOM_BLOCK_ORIGINAL_ID = 'radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
  CUSTOM_CHECKOUT_BLOCKS_SELECTOR = '#radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
  CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_box.payment_method_woo-mercado-pago-custom';
  CARD_FLAGS_SELECTOR = '.mp-checkout-custom-card-flags';
  CHECKOUT_CUSTOM_CONTAINER_SELECTOR = '.mp-checkout-custom-container';
  NEW_CHECKOUT_CONTAINER_SELECTOR = '#mp-checkout-custom-root';
  OLD_CHECKOUT_CONTAINER_SELECTOR = '#mp-checkout-custom-container';
  CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
  COLOMBIA_ACCRONYM = 'MCO';
  MEXICO_ACCRONYM = 'MLM';
  BRAZIL_ACCRONYM = 'MLB';
  CHECKOUT_CUSTOM_LOAD_SELECTOR = '.mp-checkout-custom-load';
  SELECTED_SUPERTOKEN_METHOD_EVENT = 'mp_super_token_payment_method_selected';
  WALLET_BUTTON_SELECTOR = '.mp-wallet-button-container-wrapper';
  CARD_HOLDER_NAME_HELPER_INFO_SELECTOR = '#mp-card-holder-name-helper-info';
  SUPER_TOKEN_STYLES = {
    ROOT_ID: 'mp-checkout-super-token-root',
    ACCORDION: 'mp-super-token-payment-method__accordion',
    ACCORDION_HEADER: 'mp-super-token-payment-method__accordion-header',
    ACCORDION_TITLE: 'mp-super-token-payment-method__accordion-title',
    ACCORDION_CONTENT: 'mp-super-token-payment-method__accordion-content',
    THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
    PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list',
    PAYMENT_METHOD: 'mp-super-token-payment-method',
    PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
    PAYMENT_METHOD_CONTENT_TITLE: 'mp-super-token-payment-method__content-title',
    PAYMENT_METHOD_TITLE: 'mp-super-token-payment-method__title',
    PAYMENT_METHOD_DESCRIPTION: 'mp-super-token-payment-method__description',
    PAYMENT_METHOD_LAST_FOUR_DIGITS: 'mp-super-token-payment-method__last-four-digits',
    PAYMENT_METHOD_SECURITY_CODE_FIELDS: 'mp-super-token-payment-method__security-code-fields',
    PAYMENT_METHOD_SECURITY_CODE: 'mp-super-token-payment-method__security-code',
    PAYMENT_METHOD_EXPIRATION_DATE: 'mp-super-token-payment-method__expiration-date',
    PAYMENT_METHOD_SELECTED: 'mp-super-token-payment-method__selected',
    PAYMENT_METHOD_ACCORDION: 'mp-super-token-payment-method__accordion',
    PAYMENT_METHOD_THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
    PAYMENT_METHOD_ACCORDION_CONTENT_OPEN: 'mp-super-token-payment-method__accordion-content--open',
    PAYMENT_METHOD_VALUE_PROP: 'mp-super-token-payment-method__value-prop',
    PAYMENT_METHOD_DETAILS: 'mp-super-token-payment-method__details',
    PAYMENT_METHOD_HEADER: 'mp-super-token-payment-method__header',
    PAYMENT_METHOD_HIDE: 'mp-super-token-hide',
    REMOVE_BOX_SHADOW: 'mp-box-shadow-none',
    MERCADO_PAGO_PRIVACY_POLICY_FOOTER: 'mp-privacy-policy-footer',
    PAYMENT_METHODS_LIST_HEADER: 'mp-payment-methods-header',
    PAYMENT_METHODS_LIST_HORIZONTAL_ROW: 'mp-payment-methods-list-horizontal-row',
    PAYMENT_METHODS_LIST_HEADER_LOGO: 'mp-payment-methods-header-logo',
    ANIMATION_CLASS: 'mp-initial-state',
    BLOCK: 'mp-super-token-block',
    BLOCK_SAVED_CARDS: 'mp-super-token-block--saved-cards',
    BLOCK_OTHER_MP_METHODS: 'mp-super-token-block--other-mp',
    BLOCK_HEADER: 'mp-super-token-block__header',
    BLOCK_HEADER_INFO: 'mp-super-token-block__header-info',
    BLOCK_TITLE: 'mp-super-token-block__title',
    BLOCK_EMAIL: 'mp-super-token-block__email',
    BLOCK_HEADER_LOGO: 'mp-super-token-block__header-logo',
    ACCOUNT_MONEY_ROW: 'mp-super-token-account-money-row',
    ACCOUNT_MONEY_ROW_OPEN: 'mp-super-token-account-money-row--open',
    ACCOUNT_MONEY_BALANCE_LINE: 'mp-super-token-am-balance-text',
    ACCOUNT_MONEY_BALANCE_LINE_OPEN: 'mp-super-token-am-balance-text--open',
  };

  // Localized params (assigned from the injected `params` in the constructor).
  YELLOW_WALLET_PATH: string;
  YELLOW_MONEY_PATH: string;
  WHITE_CARD_PATH: string;
  PAYMENT_METHODS_LIST_TEXT: string;
  PAYMENT_METHODS_LIST_ALT_TEXT: string;
  LAST_DIGITS_TEXT: string;
  NEW_CARD_TEXT: string;
  ACCOUNT_MONEY_TEXT: string;
  ACCOUNT_MONEY_WALLET_WITH_INVESTMENT_TEXT: string;
  ACCOUNT_MONEY_WALLET_TEXT: string;
  ACCOUNT_MONEY_INVESTMENT_TEXT: string;
  ACCOUNT_MONEY_AVAILABLE_TEXT: string;
  INTEREST_FREE_PART_ONE_TEXT: string;
  INTEREST_FREE_PART_TWO_TEXT: string;
  BANK_INTEREST_HINT_TEXT: string;
  INSTALLMENTS_INPUT_TITLE: string;
  INSTALLMENTS_PLACEHOLDER: string;
  INSTALLMENTS_REQUIRED_MESSAGE: string;
  INSTALLMENTS_INTEREST_FREE_OPTION_TEXT: string;
  SECURITY_CODE_INPUT_TITLE_TEXT: string;
  SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS: string;
  SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS: string;
  SECURITY_CODE_ERROR_MESSAGES: Record<string, string>;
  SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS: string;
  SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS: string;
  SITE_ID: string;
  CURRENCY: string;
  INTL: string;
  MERCADO_PAGO_CARD_NAME: string;
  MERCADO_PAGO_CREDIT_CARD_NAME: string;
  CONSUMER_CREDITS_DUE_DATE: string;
  MLB_INSTALLMENT_DEBIT_AUTO_TEXT: string;
  INTEREST_RATE_MLB_TEXT: string;
  EFFECTIVE_TOTAL_COST_MLB_TEXT: string;
  IOF_MLB_TEXT: string;
  BORROWED_AMOUNT_MLB_TEXT: string;
  PER_MONTH: string;
  PER_YEAR: string;
  CAT_MLM_TEXT: string;
  NO_IVA_TEXT: string;
  TNA_MLM_TEXT: string;
  SYSTEM_AMORTIZATION_MLM_TEXT: string;
  CFTEA_MLA_TEXT: string;
  TNA_MLA_TEXT: string;
  TEA_MLA_TEXT: string;
  FIXED_RATE_TEXT: string;
  MERCADO_PAGO_PRIVACY_POLICY: string;
  NEW_MP_LOGO_PATH: string;
  MP_LOGO_BLUE_PATH: string;
  MP_LOGO_DARK_PATH: string;
  SAVED_CARDS_TITLE: string;
  SAVED_CARD_TITLE: string;
  MP_METHODS_TITLE: string;
  ACCOUNT_MONEY_BALANCE_TEXT: string;
  SAVED_PAYMENT_METHOD_TITLE: string;
  CURRENT_USER_EMAIL: string;
  PAYMENT_METHODS_THUMBNAILS: Record<string, string>;
  PAYMENT_METHODS_ORDER: string;
  UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT: string;
  UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT: string;
  AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT: string;
  AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT: string;
  SELECT_PAYMENT_METHOD_ERROR_TEXT: string;
  // We use the update_security_code_with_retry_error_text because it's the same message for the generic error
  SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT: string;

  NEW_CARD_TYPE = 'new_card';
  CREDIT_CARD_TYPE = 'credit_card';
  DEBIT_CARD_TYPE = 'debit_card';
  ACCOUNT_MONEY_TYPE = 'account_money';
  PREPAID_CARD_TYPE = 'prepaid_card';
  CONSUMER_CREDITS_TYPE = 'digital_currency';
  MERCADO_PAGO_ISSUER_NAME = 'mercado pago';
  PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
  PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';
  MAX_ATTEMPTS_BY_ERROR_CODE = 3;
  GET_PAYMENT_METHOD_TIMEOUT_MS = 5000;
  ACCOUNT_MONEY_ANIMATION_MS = 300;

  // Attributes
  paymentMethods: PaymentMethod[] = [];
  superToken: string | null = null;
  securityFieldsActiveInstance: RawSdkSecurityCodeField | null = null;
  activePaymentMethod: PaymentMethod | null = null;
  amount: string | null = null;
  selectedPreloadedPaymentMethod: PaymentMethod | null = null; // Should not be resetted
  securityCodeReferences: Record<string, boolean> = {};
  lastPaymentMethodChoosen: PaymentMethod | null = null; // Should not be resetted
  attemptsByErrorCode: Record<string, number> = {};
  isRendering = false;
  escSelectionGeneration = 0;
  securityFieldDispatcherMissingReported = false;
  installmentsDispatcherMissingReported = false;
  creditsInstallmentsDispatcherMissingReported = false;

  // Dependencies
  emailHeaderListenerRegistered = false;

  private readonly selectUseCase = new SelectSavedPaymentMethod();

  constructor(
    public mpSdkInstance: RawMpSdkInstance,
    public mpSuperTokenMetrics: SuperTokenPaymentMethodsMetrics,
    params: SuperTokenPaymentMethodsParams,
    private readonly renderSavedMethods: RenderSavedMethods,
    public wcEmailListener: PaymentMethodsEmailListener | null = null,
  ) {
    this.YELLOW_WALLET_PATH = params.yellow_wallet_path;
    this.YELLOW_MONEY_PATH = params.yellow_money_path;
    this.WHITE_CARD_PATH = params.white_card_path;
    this.PAYMENT_METHODS_LIST_TEXT = params.payment_methods_list_text;
    this.PAYMENT_METHODS_LIST_ALT_TEXT = params.payment_methods_list_alt_text;
    this.LAST_DIGITS_TEXT = params.last_digits_text;
    this.NEW_CARD_TEXT = params.new_card_text;
    this.ACCOUNT_MONEY_TEXT = params.account_money_text;
    this.ACCOUNT_MONEY_WALLET_WITH_INVESTMENT_TEXT = params.account_money_wallet_with_investment_text;
    this.ACCOUNT_MONEY_WALLET_TEXT = params.account_money_wallet_text;
    this.ACCOUNT_MONEY_INVESTMENT_TEXT = params.account_money_investment_text;
    this.ACCOUNT_MONEY_AVAILABLE_TEXT = params.account_money_available_text;
    this.INTEREST_FREE_PART_ONE_TEXT = params.interest_free_part_one_text;
    this.INTEREST_FREE_PART_TWO_TEXT = params.interest_free_part_two_text;
    this.BANK_INTEREST_HINT_TEXT = params.input_helper_message.installments.bank_interest_hint_text;
    this.INSTALLMENTS_INPUT_TITLE = params.input_title.installments;
    this.INSTALLMENTS_PLACEHOLDER = params.placeholders.installments;
    this.INSTALLMENTS_REQUIRED_MESSAGE = params.input_helper_message.installments.required;
    this.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT = params.input_helper_message.installments.interest_free_option_text;
    this.SECURITY_CODE_INPUT_TITLE_TEXT = params.security_code_input_title_text;
    this.SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS = params.security_code_placeholder_text_3_digits;
    this.SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS = params.security_code_placeholder_text_4_digits;
    this.SECURITY_CODE_ERROR_MESSAGES = params.input_helper_message.securityCode;
    this.SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS = params.security_code_tooltip_text_3_digits;
    this.SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS = params.security_code_tooltip_text_4_digits;
    this.SITE_ID = params.site_id;
    this.CURRENCY = params.currency;
    this.INTL = params.intl;
    this.MERCADO_PAGO_CARD_NAME = params.mercado_pago_card_name;
    this.MERCADO_PAGO_CREDIT_CARD_NAME = params.mercado_pago_credit_card_name;
    this.CONSUMER_CREDITS_DUE_DATE = params.consumer_credits_due_date;
    this.MLB_INSTALLMENT_DEBIT_AUTO_TEXT = params.mlb_installment_debit_auto_text;
    this.INTEREST_RATE_MLB_TEXT = params.interest_rate_mlb_text;
    this.EFFECTIVE_TOTAL_COST_MLB_TEXT = params.effective_total_cost_mlb_text;
    this.IOF_MLB_TEXT = params.iof_mlb_text;
    this.BORROWED_AMOUNT_MLB_TEXT = params.borrowed_amount_mlb_text;
    this.PER_MONTH = params.per_month;
    this.PER_YEAR = params.per_year;
    this.CAT_MLM_TEXT = params.cat_mlm_text;
    this.NO_IVA_TEXT = params.no_iva_text;
    this.TNA_MLM_TEXT = params.tna_mlm_text;
    this.SYSTEM_AMORTIZATION_MLM_TEXT = params.system_amortization_mlm_text;
    this.CFTEA_MLA_TEXT = params.cftea_mla_text;
    this.TNA_MLA_TEXT = params.tna_mla_text;
    this.TEA_MLA_TEXT = params.tea_mla_text;
    this.FIXED_RATE_TEXT = params.fixed_rate_text;
    this.MERCADO_PAGO_PRIVACY_POLICY = params.mercadopago_privacy_policy;
    this.NEW_MP_LOGO_PATH = params.new_mp_logo_path;
    this.MP_LOGO_BLUE_PATH = params.mp_logo_blue_path;
    this.MP_LOGO_DARK_PATH = params.mp_logo_dark_path;
    this.SAVED_CARDS_TITLE = params.saved_cards_title;
    this.SAVED_CARD_TITLE = params.saved_card_title;
    this.MP_METHODS_TITLE = params.mp_methods_title;
    this.ACCOUNT_MONEY_BALANCE_TEXT = params.account_money_balance_text;
    this.SAVED_PAYMENT_METHOD_TITLE = params.saved_payment_method_title;
    this.CURRENT_USER_EMAIL = params.current_user_email;
    this.PAYMENT_METHODS_THUMBNAILS = params.payment_methods_thumbnails;
    this.PAYMENT_METHODS_ORDER = params.payment_methods_order;
    this.UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT = params.update_security_code_with_retry_error_text;
    this.UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT = params.update_security_code_no_retry_error_text;
    this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT = params.authorize_payment_method_with_retry_error_text;
    this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT = params.authorize_payment_method_no_retry_error_text;
    this.SELECT_PAYMENT_METHOD_ERROR_TEXT = params.select_payment_method_error_text;
    this.SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT = params.update_security_code_with_retry_error_text;
  }

  escapeHtml(str: unknown): string {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  reset(): void {
    this.isRendering = false;
    this.paymentMethods = [];
    this.attemptsByErrorCode = {};
    this.securityCodeReferences = {};
    this.activePaymentMethod = null;

    this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);
    this.unmountActiveSecurityCodeInstance();
    this.hideAllPaymentMethodDetails();
    this.restoreCustomCheckoutEntireElementOriginalId();
    this.showWalletButton();
    this.showCardFlags();
    this.removePaymentMethodElements();
    this.removeAccordion();
    this.deselectAllPaymentMethods();
    this.removeMercadoPagoPrivacyPolicyFooter();
    this.removeHorizontalRow();
    this.removePaymentMethodsListClasses();
    document.querySelectorAll(`.${this.SUPER_TOKEN_STYLES.BLOCK}`).forEach(blockElement => blockElement.remove());
  }

  storePaymentMethodsInMemory(accountPaymentMethods: PaymentMethod[]): void {
    this.paymentMethods = accountPaymentMethods;
  }

  getStoredPaymentMethods(): PaymentMethod[] {
    return this.paymentMethods;
  }

  hasStoredPaymentMethods(): boolean {
    return this.paymentMethods.length > 0;
  }

  storeSelectedPreloadedPaymentMethod(paymentMethod: PaymentMethod | null): void {
    this.selectedPreloadedPaymentMethod = paymentMethod;
  }

  getSelectedPreloadedPaymentMethod(): PaymentMethod | null {
    return this.selectedPreloadedPaymentMethod;
  }

  getSelectedPreloadedPaymentMethodFromActivePaymentMethods(): PaymentMethod | undefined {
    return this.paymentMethods.find(
      paymentMethod => this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier(this.selectedPreloadedPaymentMethod),
    );
  }

  paymentMethodIdentifier(paymentMethod: PaymentMethod | null): string {
    if (!paymentMethod) return '';

    return `${paymentMethod?.id}${('card' in paymentMethod ? paymentMethod.card?.card_number?.last_four_digits : undefined) || ''}`;
  }

  setSuperToken(token: string | null): void {
    this.superToken = token;
  }

  getSuperToken(): string | null {
    return this.superToken;
  }

  paymentMethodsAreRendered(): boolean {
    return !!document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`);
  }

  getAttemptByErrorCode(errorCode: string): number {
    return Math.min(this.attemptsByErrorCode[errorCode] || 0, this.MAX_ATTEMPTS_BY_ERROR_CODE);
  }

  shouldAllowRetry(attempt: number): boolean {
    return attempt < this.MAX_ATTEMPTS_BY_ERROR_CODE;
  }

  storeAttemptByErrorCode(errorCode: string): void {
    this.attemptsByErrorCode[errorCode] = (this.attemptsByErrorCode[errorCode] || 0) + 1;
  }

  convertErrorCodeToErrorMessage(errorCode: string): string {
    this.storeAttemptByErrorCode(errorCode);

    const errorMessages: Record<string, { withRetry: string; withoutRetry: string }> = {
      'UPDATE_SECURITY_CODE_ERROR': {
        withRetry: this.UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT
      },
      'AUTHORIZE_PAYMENT_METHOD_ERROR': {
        withRetry: this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT
      },
      'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED': {
        withRetry: this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT
      },
      'SELECT_PAYMENT_METHOD_ERROR': {
        withRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT,
        withoutRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT
      },
    };

    const errorConfig = Object.entries(errorMessages).find(([key]) => errorCode.includes(key))?.[1] || null;

    if (!errorConfig) {
      return this.SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT;
    }

    const allowRetry = this.shouldAllowRetry(this.getAttemptByErrorCode(errorCode));
    if (!allowRetry) {
      this.mpSuperTokenMetrics.sendMetric('super_token_retry_limit_reached', errorCode, '');
    }
    return allowRetry ? errorConfig.withRetry : errorConfig.withoutRetry;
  }

  showSuperTokenError(errorMessage: string): void {
    const paymentMethodList = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
    if (!paymentMethodList) return;

    const andesNotice = document.createElement('andes-notice');
    andesNotice.id = 'mp-fast-payments-error';
    andesNotice.setAttribute('type', 'warning');
    andesNotice.setAttribute('description', errorMessage);

    paymentMethodList.insertBefore(andesNotice, paymentMethodList.firstChild);
    andesNotice.scrollIntoView({ behavior: 'smooth' });
  }

  hideSuperTokenError(): void {
    this.excludeRecaptchaFromPreValidation();

    const andesNotice = document.getElementById('mp-fast-payments-error');
    if (!andesNotice) return;

    andesNotice.remove();
  }

  /**
   * Captcha tokens (reCAPTCHA g-recaptcha-response, hCaptcha h-captcha-response, Cloudflare
   * Turnstile cf-turnstile-response) are single-use. The Classic pre-validation request
   * (mp_validate_checkout) serializes form.checkout via jQuery .serialize() and, server-side,
   * re-runs woocommerce_checkout_process — the captcha plugin consumes the token there, so the real
   * submit then fails the captcha and blocks the buyer. Install a one-time spy on jQuery's global
   * .serialize() that omits the checkout form's captcha field from every serialize EXCEPT the real
   * submit: it disables the field just for that one call (disable → serialize → re-enable in
   * finally, so the live field is never left disabled) whenever mercado_pago_submit is false. The
   * real submit serializes with mercado_pago_submit true, so it keeps the token. The flag is read at
   * serialize time (not install time), so no sticky disabled state leaks into the real submit. Acts
   * only when form.checkout itself is serialized (captchas on other forms/widgets on the page are
   * ignored); runs only on the standard Classic checkout (absent on Blocks and order-pay). Each disable/enable emits a
   * success metric (action + field name); failures emit an error metric. Best-effort: never throws.
   */
  excludeRecaptchaFromPreValidation(): void {
    const metrics = this.mpSuperTokenMetrics;
    const CAPTCHA_SELECTOR = '[name^="g-recaptcha-response"], [name^="h-captcha-response"], [name^="cf-turnstile-response"]';

    try {
      // form.checkout exists only on the standard Classic checkout — absent on Blocks and on
      // the order-pay page (form#order_review), neither of which runs this pre-validation.
      const checkoutForm = document.querySelector('form.checkout');
      if (!checkoutForm) {
        return;
      }

      // Only the checkout form's own captcha matters — ignore any captcha elsewhere on the page.
      if (!checkoutForm.querySelector(CAPTCHA_SELECTOR)) {
        return;
      }

      const jq = window.jQuery as unknown as JQueryWithFn | undefined;
      if (!jq?.fn || typeof jq.fn.serialize !== 'function') {
        metrics?.errorToExcludeRecaptchaFromPreValidation('serialize_unavailable', 'jQuery.fn.serialize is not available');
        return;
      }

      // Install the spy once, capturing the original first (avoids self-recursion).
      if (jq.fn.serialize.__mpRecaptchaSpy) {
        return;
      }

      const originalSerialize = jq.fn.serialize;
      const patchedSerialize: RecaptchaSerializeFn = function (this: unknown, ...args: unknown[]): string {
        // Act only when the checkout form itself is being serialized (pre-validation / real
        // submit) — never on serializes of other forms on the page. `this` is the jQuery
        // collection .serialize() was called on. On the real submit (mercado_pago_submit ===
        // true) keep the token; otherwise omit it, scoping the disable to this call (finally).
        const serializedForm = this ? (this as Record<number, HTMLFormElement | undefined>)[0] : undefined;
        const isCheckoutForm = !!serializedForm
          && typeof serializedForm.matches === 'function'
          && serializedForm.matches('form.checkout');
        const captchaFields = serializedForm && isCheckoutForm && !window.mpEventHandler?.mercado_pago_submit
          ? Array.from(serializedForm.querySelectorAll<HTMLInputElement>(CAPTCHA_SELECTOR)).filter((field) => !field.disabled)
          : [];

        if (!captchaFields.length) {
          return originalSerialize.apply(this, args);
        }

        captchaFields.forEach((field) => {
          field.disabled = true;
          metrics?.captchaFieldToggledOnPreValidation('disabled', field.name);
        });

        try {
          return originalSerialize.apply(this, args);
        } finally {
          captchaFields.forEach((field) => {
            field.disabled = false;
            metrics?.captchaFieldToggledOnPreValidation('enabled', field.name);
          });
        }
      };

      patchedSerialize.__mpRecaptchaSpy = true;
      jq.fn.serialize = patchedSerialize;
    } catch (error) {
      metrics?.errorToExcludeRecaptchaFromPreValidation('setup', error);
    }
  }

  getCustomCheckoutEntireElement(): HTMLElement | null {
    return document.querySelector<HTMLElement>(`#${this.SUPER_TOKEN_STYLES.ROOT_ID}`)
      || document.querySelector<HTMLElement>(this.CUSTOM_CHECKOUT_BLOCKS_SELECTOR)
      || document.querySelector<HTMLElement>(this.CUSTOM_CHECKOUT_CLASSIC_SELECTOR);
  }

  getWalletButtonElement(): HTMLElement | null {
    return document.querySelector<HTMLElement>(this.WALLET_BUTTON_SELECTOR);
  }

  getCardFlagsElement(): HTMLElement | null {
    return document.querySelector<HTMLElement>(this.CARD_FLAGS_SELECTOR);
  }

  hideWalletButton(): void {
    const walletButtonElement = this.getWalletButtonElement();

    if (!walletButtonElement) return;

    walletButtonElement.style.display = 'none';
  }

  showWalletButton(): void {
    const walletButtonElement = this.getWalletButtonElement();

    if (!walletButtonElement) return;

    walletButtonElement.style.display = 'flex';
  }

  hideCardFlags(): void {
    const cardFlagsElement = this.getCardFlagsElement();

    if (!cardFlagsElement) return;

    cardFlagsElement.style.display = 'none';
  }

  showCardFlags(): void {
    const cardFlagsElement = this.getCardFlagsElement();

    if (!cardFlagsElement) return;

    cardFlagsElement.style.display = 'flex';
  }

  removeAccordion(): void {
    const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);
    const accordionHeader = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);

    accordionElement?.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)?.classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
    accordionElement?.classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION);
    accordionHeader?.remove();
  }

  removePaymentMethodsListClasses(): void {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    const checkoutContainer = customCheckoutEntireElement?.querySelector<HTMLElement>(this.NEW_CHECKOUT_CONTAINER_SELECTOR) ?? customCheckoutEntireElement?.querySelector<HTMLElement>(this.OLD_CHECKOUT_CONTAINER_SELECTOR);

    if (checkoutContainer) {
      checkoutContainer.style.height = 'auto';
    }

    customCheckoutEntireElement?.parentElement?.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
    customCheckoutEntireElement?.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    customCheckoutEntireElement?.parentElement?.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
    customCheckoutEntireElement?.removeAttribute('role');
    customCheckoutEntireElement?.removeAttribute('aria-label');
    customCheckoutEntireElement?.removeAttribute('tabindex');
  }

  removePaymentMethodElements(): void {
    document
      .querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`)
      .forEach(element => element.remove());
  }

  closeAccordion(): void {
    const accordionContent = document.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR);
    const accordionElement = document.querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);

    if (accordionContent) {
      accordionContent.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);
    }

    if (accordionElement) {
      accordionElement.style.height = '48px';
    }
  }

  deselectAllPaymentMethods(): void {
    document
      .querySelectorAll<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`)
      .forEach(element => {
        element.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
        element.setAttribute('aria-selected', 'false');
        if (element.dataset?.type === this.ACCOUNT_MONEY_TYPE && this.ACCOUNT_MONEY_BALANCE_TEXT) {
          element.setAttribute('aria-label', element.dataset.baseAriaLabel ?? '');
        }
      });
    this.removeAccountMoneyBalanceLine();
  }

  selectNewCardAccordion(): void {
    const accordionElement = document.querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION}`);
    const accordionContent = document.querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT}`);
    const accordionHeader = document.querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);

    if (!accordionElement || !accordionContent || !accordionHeader) {
      window.console?.warn?.('Accordion elements not found');
      return;
    }

    accordionElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
    accordionElement.style.height = '48px';
    accordionHeader.setAttribute('aria-selected', 'true');

    setTimeout(() => {
      accordionContent.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);

      requestAnimationFrame(() => {
        accordionElement.style.height = 'auto';
        accordionElement.style.overflow = 'visible';
      });
    }, 10);
  }

  selectPaymentMethod(paymentMethodElement: HTMLElement): void {
    paymentMethodElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
    paymentMethodElement.setAttribute('aria-selected', 'true');
    if (paymentMethodElement.dataset?.type === this.ACCOUNT_MONEY_TYPE) {
      this.applyAccountMoneySelectionDecoration(paymentMethodElement);
    }
  }

  getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods: PaymentMethod[]): PaymentMethod | null | undefined {
    const paymentMethodSelected = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`) || null;
    if (!paymentMethodSelected) return null;

    return accountPaymentMethods.find(paymentMethod => this.paymentMethodIdentifier(paymentMethod) === paymentMethodSelected.id);
  }

  getPaymentMethodElementFromDOM(paymentMethod: PaymentMethod): HTMLElement | null {
    return document.getElementById(this.paymentMethodIdentifier(paymentMethod));
  }

  setCheckoutType(type: string): void {
    const element = document.querySelector<HTMLInputElement>(this.CHECKOUT_TYPE_SELECTOR);
    if (!element) return;

    element.value = type;
  }

  setPaymentMethodChildrenAriaVisible(paymentMethodElement: HTMLElement): void {
    const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
    if (securityCodeContainer) {
      const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
      const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
      const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');

      securityCodeLabel?.setAttribute('aria-hidden', 'false');
      securityCodeLabel?.setAttribute('tabindex', '0');
      securityCodeInput?.setAttribute('aria-hidden', 'false');
      securityCodeInput?.setAttribute('tabindex', '0');
      securityCodeTooltip?.setAttribute('aria-hidden', 'false');
      securityCodeTooltip?.setAttribute('tabindex', '0');
    }

    const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement as unknown as PaymentMethod)}`);

    if (!installmentsDropdown) return;

    installmentsDropdown.setAttribute('aria-hidden', 'false');
    installmentsDropdown.setAttribute('tabindex', '0');
  }

  setPaymentMethodChildrenAriaHidden(paymentMethodElement: HTMLElement): void {
    const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
    if (securityCodeContainer) {
      const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
      const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
      const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');

      securityCodeLabel?.setAttribute('aria-hidden', 'true');
      securityCodeLabel?.setAttribute('tabindex', '-1');
      securityCodeInput?.setAttribute('aria-hidden', 'true');
      securityCodeInput?.setAttribute('tabindex', '-1');
      securityCodeTooltip?.setAttribute('aria-hidden', 'true');
      securityCodeTooltip?.setAttribute('tabindex', '-1');
    }

    const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement as unknown as PaymentMethod)}`);

    if (!installmentsDropdown) return;

    installmentsDropdown.setAttribute('aria-hidden', 'true');
    installmentsDropdown.setAttribute('tabindex', '-1');
  }

  showPaymentMethodDetails(paymentMethodElement: HTMLElement): void {
    paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
      ?.classList
      ?.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

    this.setPaymentMethodChildrenAriaVisible(paymentMethodElement);
  }

  hideAllPaymentMethodDetails(): void {
    document
      .querySelectorAll<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
      ?.forEach(element => {
        element
          ?.classList
          ?.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

        this.setPaymentMethodChildrenAriaHidden(element);
      });
  }

  fillCardTokenFields(paymentMethod: PaymentMethod): void {
    (document.getElementById('paymentMethodId') as HTMLInputElement).value = paymentMethod.id;
    (document.getElementById('paymentTypeId') as HTMLInputElement).value = paymentMethod.type;
    (document.getElementById('cardTokenId') as HTMLInputElement).value = paymentMethod.token;
  }

  paymentMethodAlreadySelected(paymentMethod: PaymentMethod): boolean {
    const paymentMethodElement = this.getPaymentMethodElementFromDOM(paymentMethod);
    if (!paymentMethodElement) return false;

    return paymentMethodElement.classList.contains(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
  }

  getActivePaymentMethod(): PaymentMethod | null {
    return this.activePaymentMethod;
  }

  storeActivePaymentMethod(paymentMethod: PaymentMethod | null): void {
    this.activePaymentMethod = paymentMethod;
    this.lastPaymentMethodChoosen = paymentMethod || this.lastPaymentMethodChoosen;
  }

  clearActivePaymentMethod(): void {
    this.activePaymentMethod = null;
  }

  getLastPaymentMethodChoosen(): PaymentMethod | null {
    return this.lastPaymentMethodChoosen;
  }

  hasCheckoutError(): boolean {
    return !!document.querySelector('#mp-fast-payments-error');
  }

  formatSelectedPaymentMethodName(paymentMethod: PaymentMethod): string {
    if (this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier({ id: this.NEW_CARD_TYPE } as unknown as PaymentMethod)) {
      return 'new_credit_card';
    }

    if (paymentMethod?.type === this.ACCOUNT_MONEY_TYPE) {
      return this.ACCOUNT_MONEY_TYPE;
    }

    const paymentMethodName = `${paymentMethod?.id || 'none'} ${paymentMethod?.type || 'none'}`.toLowerCase();

    const lastFourDigits = 'card' in paymentMethod ? paymentMethod.card?.card_number?.last_four_digits : undefined;

    return paymentMethodName.concat(lastFourDigits ? ` ${lastFourDigits}` : '');
  }

  emitEventFromSelectPaymentMethod(paymentMethod: PaymentMethod): void {
    const formattedPaymentMethodName = this.formatSelectedPaymentMethodName(paymentMethod);

    document.dispatchEvent(new CustomEvent(this.SELECTED_SUPERTOKEN_METHOD_EVENT, { detail: { payment_method: formattedPaymentMethodName } }));
  }

  async onSelectSuperTokenPaymentMethod(paymentMethodElement: HTMLElement, paymentMethod: PaymentMethod): Promise<void> {
    await this.selectUseCase.execute({
      session: new LegacySelectionSession(this),
      metrics: this.mpSuperTokenMetrics,
      paymentMethod,
      paymentMethodElement,
    });
  }

  async selectPreloadedPaymentMethod(): Promise<void> {
    this.closeAccordion();

    const paymentMethod = this.getSelectedPreloadedPaymentMethodFromActivePaymentMethods();
    if (!paymentMethod) {
      this.mpSuperTokenMetrics.sendMetric('super_token_preloaded_method_not_found', 'true', '');
      return;
    }

    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }

    this.storeActivePaymentMethod(paymentMethod);
    await this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
  }

  selectLastPaymentMethodChoosen(): void {
    this.closeAccordion();

    const paymentMethod = this.getLastPaymentMethodChoosen();
    if (!paymentMethod) return;

    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }

    this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
  }

  onSelectNewCardPaymentMethod(): void {
    if (this.paymentMethodAlreadySelected({ id: this.NEW_CARD_TYPE } as unknown as PaymentMethod)) {
      return;
    }

    this.mpSuperTokenMetrics.sendMetric('super_token_withdraw', 'true', '');
    this.emitEventFromSelectPaymentMethod({ id: this.NEW_CARD_TYPE } as unknown as PaymentMethod);
    this.storeActivePaymentMethod({ id: this.NEW_CARD_TYPE } as unknown as PaymentMethod);
    this.deselectAllPaymentMethods();
    this.hideAllPaymentMethodDetails();
    this.unmountActiveSecurityCodeInstance();
    this.selectNewCardAccordion();
    this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);
    this.handleInstallmentsWithoutFeePillVisibility();

    setTimeout(() => {
      this.unmountCardForm();
      this.mountCardForm();
      this.showCardHolderNameHelperInfo();
    }, 50);

    setTimeout(() => {
      document.dispatchEvent(
        this.selectedSupertokenMethodEvent(true)
      );
    }, 50);
  }

  selectedSupertokenMethodEvent = (isNewCardSelected: boolean): CustomEvent => {
    return new CustomEvent('supertoken_payment_method_selected',
      {
        detail: {
          new_card_selected: isNewCardSelected,
          checkout_type: (document.querySelector('#mp_checkout_type') as HTMLInputElement | null)?.value,
        }
      }
    );
  };

  isCreditCard(paymentMethod: PaymentMethod): paymentMethod is CreditCardPaymentMethod {
    return paymentMethod?.type === this.CREDIT_CARD_TYPE;
  }

  isDebitCard(paymentMethod: PaymentMethod): paymentMethod is DebitCardPaymentMethod {
    return paymentMethod?.type === this.DEBIT_CARD_TYPE;
  }

  isAccountMoney(paymentMethod: PaymentMethod): paymentMethod is AccountMoneyPaymentMethod {
    return paymentMethod?.type === this.ACCOUNT_MONEY_TYPE;
  }

  isPrepaidCard(paymentMethod: PaymentMethod): paymentMethod is PrepaidCardPaymentMethod {
    return paymentMethod?.type === this.PREPAID_CARD_TYPE;
  }

  isMercadoPagoCard(paymentMethod: PaymentMethod): paymentMethod is PrepaidCardPaymentMethod {
    return paymentMethod?.type === this.PREPAID_CARD_TYPE && 'issuer' in paymentMethod && !!paymentMethod?.issuer?.name?.toLowerCase()?.includes(this.MERCADO_PAGO_ISSUER_NAME);
  }

  isMercadoPagoCreditCard(paymentMethod: PaymentMethod): paymentMethod is CreditCardPaymentMethod {
    return paymentMethod?.type === this.CREDIT_CARD_TYPE && 'issuer' in paymentMethod && !!paymentMethod?.issuer?.name?.toLowerCase()?.includes(this.MERCADO_PAGO_ISSUER_NAME);
  }

  isConsumerCredits(paymentMethod: PaymentMethod): paymentMethod is ConsumerCreditsPaymentMethod {
    return paymentMethod?.type === this.CONSUMER_CREDITS_TYPE;
  }

  applyAccountMoneySelectionDecoration(paymentMethodRow: HTMLElement): void {
    // Remove any leftover balance line synchronously before appending the new one.
    // The close removal is deferred (~transition duration), so a fast AM -> other -> AM
    // toggle could otherwise leave two balance nodes coexisting and strand an --open
    // node on a deselected row. Normal deselection still animates via removeAccountMoneyBalanceLine().
    this.getCustomCheckoutEntireElement()
      ?.querySelectorAll(`.${this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`)
      .forEach(node => node.remove());
    const contentSection = paymentMethodRow?.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_CONTENT}`);
    if (!contentSection) return;
    const balanceParagraph = document.createElement('p');
    balanceParagraph.classList.add(this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_BALANCE_LINE);
    balanceParagraph.setAttribute('aria-live', 'polite');
    balanceParagraph.textContent = this.ACCOUNT_MONEY_BALANCE_TEXT;
    contentSection.appendChild(balanceParagraph);

    // Trigger row/title and balance line transitions in the same frame.
    // Stale-frame guard: if another method gets selected before this frame runs,
    // the AM row is no longer selected/connected — skip reopening it (avoids an
    // orphan --open state on an unselected row).
    requestAnimationFrame(() => {
      if (!paymentMethodRow?.isConnected
        || !paymentMethodRow.classList.contains(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED)) {
        return;
      }
      paymentMethodRow.classList.add(this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_ROW_OPEN);
      balanceParagraph.classList.add(this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);
    });

    const currentLabel = paymentMethodRow?.getAttribute('aria-label') ?? '';
    if (this.ACCOUNT_MONEY_BALANCE_TEXT && !currentLabel.includes(this.ACCOUNT_MONEY_BALANCE_TEXT)) {
      paymentMethodRow?.setAttribute('aria-label', `${currentLabel}. ${this.ACCOUNT_MONEY_BALANCE_TEXT}`);
    }
  }

  removeAccountMoneyBalanceLine(): void {
    const customCheckoutRoot = this.getCustomCheckoutEntireElement();
    if (!customCheckoutRoot) return;

    const accountMoneyRow = customCheckoutRoot
      .querySelector(`.${this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_ROW_OPEN}`)
      ?? customCheckoutRoot.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_ROW}`);

    accountMoneyRow?.classList.remove(this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_ROW_OPEN);

    const balanceLine = customCheckoutRoot
      .querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`);
    if (!balanceLine) return;

    // Already closing — avoid duplicate listeners/timers on the same node (fast toggle).
    if (balanceLine.dataset.closing === '1') return;
    balanceLine.dataset.closing = '1';

    balanceLine.classList.remove(this.SUPER_TOKEN_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);

    // Remove the node only after the close transition actually finishes (event-driven),
    // so the DOM removal never lands a frame before the animation ends — which is what
    // caused the title to nudge at the end of the close. A timeout fallback guarantees
    // cleanup if transitionend never fires (reduced motion, detached node, etc.).
    let removed = false;
    let fallbackTimer: ReturnType<typeof setTimeout>;
    const finalize = () => {
      if (removed) return;
      removed = true;
      balanceLine.remove();
    };
    // Listen until the max-height transition ends specifically — opacity/margin-top end
    // separately, so we can't use { once: true } (it would fire on the wrong property).
    const onTransitionEnd = function handleTransitionEnd(event: TransitionEvent) {
      if (event.target === balanceLine && event.propertyName === 'max-height') {
        clearTimeout(fallbackTimer);
        balanceLine.removeEventListener('transitionend', handleTransitionEnd);
        finalize();
      }
    };
    balanceLine.addEventListener('transitionend', onTransitionEnd);
    fallbackTimer = setTimeout(finalize, this.ACCOUNT_MONEY_ANIMATION_MS + 50);
  }

  getMpIconPaths(): { blue: string; dark: string } {
    return { blue: this.MP_LOGO_BLUE_PATH, dark: this.MP_LOGO_DARK_PATH };
  }

  getSiteId(): string | undefined {
    return this.SITE_ID?.toUpperCase();
  }

  securityCodeIsRequired(securityCodeSettings: SecurityCodeSettings | null | undefined): boolean {
    if (!securityCodeSettings) {
      return false;
    }

    return securityCodeSettings?.mode === 'mandatory';
  }

  shouldFetchPaymentMethodAgain(paymentMethod: PaymentMethod | null, paymentMethodElement: HTMLElement | null): boolean {
    if (!paymentMethod || !paymentMethodElement) throw new Error(MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS);

    if (paymentMethodElement.hasAttribute('data-cvv-is-required-double-check')) return false;

    return (this.isCreditCard(paymentMethod) || this.isDebitCard(paymentMethod))
      && this.securityCodeIsRequired(paymentMethod.security_code_settings)
      && paymentMethod.has_esc === true;
  }

  getSkipReason(paymentMethod: PaymentMethod, paymentMethodElement: HTMLElement | null): string {
    if (paymentMethodElement && paymentMethodElement.hasAttribute('data-cvv-is-required-double-check')) {
      return 'already_checked';
    }

    if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod)) {
      return 'not_card';
    }

    if (!this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
      return 'security_code_not_required';
    }

    if (paymentMethod?.has_esc !== true) {
      return 'esc_disabled';
    }

    return 'unknown';
  }

  hasMissingEsc(paymentMethod: PaymentMethod): boolean {
    return (this.isCreditCard(paymentMethod) || this.isDebitCard(paymentMethod))
      && this.securityCodeIsRequired(paymentMethod?.security_code_settings)
      && typeof paymentMethod?.has_esc === 'undefined';
  }

  getPaymentMethodElementByIdentifier(paymentMethod: PaymentMethod): HTMLElement | null {
    return document.getElementById(this.paymentMethodIdentifier(paymentMethod));
  }

  timeoutRequest(errorCode: string, timeoutMs = 5000): Promise<never> {
    return new Promise<never>((_, reject) =>
      setTimeout(() => reject(new Error(errorCode)), timeoutMs)
    );
  }

  parseMsToSeconds(milliseconds: number): string {
    return (milliseconds / 1000).toFixed(2);
  }

  async fetchPaymentMethod(paymentMethod: PaymentMethod, paymentMethodElement: HTMLElement): Promise<PaymentMethod> {
    const currentPaymentMethodIdentifier = this.paymentMethodIdentifier(paymentMethod);

    const REQUEST_START_TIME = Date.now();
    const result = await Promise.race([
      this.mpSdkInstance.getAccountPaymentMethod(this.getSuperToken() as string, paymentMethod.token),
      this.timeoutRequest(MPSuperTokenErrorCodes.GET_PAYMENT_METHOD_TIMEOUT_ERROR, this.GET_PAYMENT_METHOD_TIMEOUT_MS)
    ]);

    const updatedPaymentMethod = result?.data;

    if (!updatedPaymentMethod) throw new Error(MPSuperTokenErrorCodes.FETCH_PAYMENT_METHOD_NOT_FOUND);

    paymentMethodElement.setAttribute('data-cvv-is-required-double-check', 'true');

    this.mpSuperTokenMetrics.getPaymentMethodLoadingTime(
      currentPaymentMethodIdentifier,
      this.parseMsToSeconds(Date.now() - REQUEST_START_TIME)
    );

    return updatedPaymentMethod;
  }

  updatePaymentMethodInList(updatedPaymentMethod: PaymentMethod): void {
    if (!this.paymentMethods) {
      throw new Error(MPSuperTokenErrorCodes.UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS);
    }

    const updatedPaymentMethodList = this.paymentMethods
      .map((pm) => this
        .paymentMethodIdentifier(pm) === this.paymentMethodIdentifier(updatedPaymentMethod)
        ? updatedPaymentMethod : pm
      );

    this.paymentMethods = updatedPaymentMethodList;
  }

  showDetailsSkeleton(paymentMethodElement: HTMLElement): void {
    const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
    if (!wrapper) return;

    wrapper.classList.add('mp-super-token-method-details-wrapper--loading');

    const skeleton = document.createElement('div');
    skeleton.classList.add('mp-super-token-method-details-skeleton');
    wrapper.appendChild(skeleton);
  }

  hideDetailsSkeleton(paymentMethodElement: HTMLElement): void {
    const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
    if (!wrapper) return;

    wrapper.classList.remove('mp-super-token-method-details-wrapper--loading');

    const skeleton = wrapper.querySelector('.mp-super-token-method-details-skeleton');
    if (skeleton) skeleton.remove();
  }

  removeSecurityCodeField(paymentMethod: PaymentMethod): void {
    const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);

    if (securityCodeContainer) {
      securityCodeContainer.remove();
    }
  }

  async handleWithEscPaymentMethod(paymentMethod: PaymentMethod, paymentMethodElement: HTMLElement): Promise<PaymentMethod | null> {
    try {
      if (this.shouldFetchPaymentMethodAgain(paymentMethod, paymentMethodElement)) {
        this.showDetailsSkeleton(paymentMethodElement);

        const currentGeneration = ++this.escSelectionGeneration;
        const updatedPaymentMethod = await this.fetchPaymentMethod(paymentMethod, paymentMethodElement);

        if (currentGeneration !== this.escSelectionGeneration) {
          this.hideDetailsSkeleton(paymentMethodElement);
          return null;
        }

        this.updatePaymentMethodInList(updatedPaymentMethod);
        this.storeActivePaymentMethod(updatedPaymentMethod);

        if (!this.securityCodeIsRequired('security_code_settings' in updatedPaymentMethod ? updatedPaymentMethod.security_code_settings : undefined)) {
          this.removeSecurityCodeField(updatedPaymentMethod);
        }

        this.mpSuperTokenMetrics
          .fetchPaymentMethodSuccess(
            this.paymentMethodIdentifier(updatedPaymentMethod),
            ('security_code_settings' in updatedPaymentMethod && updatedPaymentMethod.security_code_settings)
              ? this.securityCodeIsRequired(updatedPaymentMethod.security_code_settings)
              : null
          );

        this.hideDetailsSkeleton(paymentMethodElement);

        return updatedPaymentMethod;
      } else if (this.hasMissingEsc(paymentMethod)) {
        this.mpSuperTokenMetrics.hasEscNotExists(this.paymentMethodIdentifier(paymentMethod));

        return paymentMethod;
      } else {
        this.mpSuperTokenMetrics
          .fetchPaymentMethodSkipped(
            this.paymentMethodIdentifier(paymentMethod),
            this.getSkipReason(paymentMethod, paymentMethodElement)
          );

        return paymentMethod;
      }
    } catch (error) {
      this.hideDetailsSkeleton(paymentMethodElement);

      if ((error as { message?: string })?.message === MPSuperTokenErrorCodes.GET_PAYMENT_METHOD_TIMEOUT_ERROR) {
        this.mpSuperTokenMetrics.getPaymentMethodLoadingTime(
          this.paymentMethodIdentifier(paymentMethod),
          this.parseMsToSeconds(this.GET_PAYMENT_METHOD_TIMEOUT_MS)
        );

        this.mpSuperTokenMetrics.fetchPaymentMethodTimeout(this.paymentMethodIdentifier(paymentMethod));
      }

      this.mpSuperTokenMetrics.getPaymentMethodFail(error, this.paymentMethodIdentifier(paymentMethod));

      return paymentMethod;
    }
  }

  mountCardForm(): void {
    if (window.mpCustomCheckoutHandler?.cardForm?.formMounted) {
      return;
    }

    window.mpCustomCheckoutHandler?.cardForm?.initCardForm(this.getAmount());
  }

  unmountCardForm(): void {
    if (window.mpCustomCheckoutHandler?.cardForm?.formMounted) {
      window.mpCustomCheckoutHandler?.cardForm?.form?.unmount();
    }
  }

  unmountActiveSecurityCodeInstance(): void {
    if (this.securityFieldsActiveInstance) {
      this.securityFieldsActiveInstance.unmount();
      this.securityFieldsActiveInstance = null;
    }
  }

  storeActiveSecurityCodeInstance(securityCodeInstance: RawSdkSecurityCodeField): void {
    this.securityFieldsActiveInstance = securityCodeInstance;
  }

  storeAmount(amount: string | null): void {
    this.amount = amount;
  }

  getAmount(): string | null {
    return this.amount;
  }

  getCheckoutLoaderElement(): HTMLElement | null {
    return document.querySelector<HTMLElement>('.mp-checkout-custom-load');
  }

  moveCheckoutLoaderToPaymentMethodsList(): void {
    const checkoutLoaderElement = this.getCheckoutLoaderElement();
    const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!checkoutLoaderElement || !paymentMethodsListElement) {
      return;
    }

    paymentMethodsListElement.parentElement?.appendChild(checkoutLoaderElement);
  }

  removeCheckoutLoaderFromPaymentMethodsList(): void {
    const checkoutLoaderElement = this.getCheckoutLoaderElement();
    const checkoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!checkoutLoaderElement || !checkoutEntireElement) {
      return;
    }

    checkoutEntireElement.appendChild(checkoutLoaderElement);
  }

  getPaymentMethodsListElement(): Element | null {
    return document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
  }

  restoreCustomCheckoutEntireElementOriginalId(): void {
    const paymentMethodsListElement = this.getPaymentMethodsListElement();
    if (!paymentMethodsListElement) return;

    paymentMethodsListElement.setAttribute('id', this.CUSTOM_BLOCK_ORIGINAL_ID);
  }

  hidePaymentMethodsList(): void {
    const paymentMethodsListElement = document.querySelector<HTMLElement>(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!paymentMethodsListElement) {
      return;
    }

    paymentMethodsListElement.style.display = 'none';
  }

  showPaymentMethodsList(): void {
    const paymentMethodsListElement = document.querySelector<HTMLElement>(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!paymentMethodsListElement) {
      return;
    }

    paymentMethodsListElement.style.display = 'flex';
  }

  async updateSecurityCode(): Promise<void> {
    const paymentMethod = this.activePaymentMethod;

    if (
      !paymentMethod
      || !this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)
    ) {
      return;
    }

    try {
      const { card_id } = await this.mpSdkInstance.getCardId(this.getSuperToken() as string, paymentMethod.token);
      this.mpSuperTokenMetrics?.updateSecurityCodeGetCardIdSuccess();

      const { id } = await this.mpSdkInstance.fields.createCardToken({ cardId: card_id });
      this.mpSuperTokenMetrics?.updateSecurityCodeCardTokenCreated();

      await this.mpSdkInstance.updatePseudotoken(this.getSuperToken() as string, paymentMethod.token, id);
      this.mpSuperTokenMetrics?.updateSecurityCodePseudotokenUpdated();

      this.mpSuperTokenMetrics?.updateSecurityCodeSuccess();
    } catch (error) {
      this.mpSuperTokenMetrics.errorToUpdateSecurityCode(error, paymentMethod);
      throw new Error(MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR);
    }
  }

  toggleSecurityCodeErrorMessage(errorMessage: string, paymentMethod: PaymentMethod): void {
    const securityCodeContainerElement = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
    if (!securityCodeContainerElement) {
      return;
    }

    const securityCodeLabelElement = securityCodeContainerElement.querySelector('label') as HTMLElement;
    const securityCodeErrorMessageElement = securityCodeContainerElement.querySelector('#mp-super-token-security-code-error-message') as HTMLElement;
    const helperErrorElement = securityCodeContainerElement.querySelector('#mp-input-with-tooltip-helper-error') as HTMLElement;
    const securityCodeInputElement = securityCodeContainerElement.querySelector('.mp-super-token-security-code-input') as HTMLElement;

    // Clean up
    securityCodeLabelElement.classList.remove('error');
    securityCodeContainerElement.classList.remove('error');
    securityCodeInputElement.classList.remove('error');
    helperErrorElement.style.display = 'none';
    securityCodeErrorMessageElement.textContent = '';

    if (!errorMessage) {
      return;
    }

    // Set error
    securityCodeLabelElement.classList.add('error');
    securityCodeContainerElement.classList.add('error');
    securityCodeInputElement.classList.add('error');

    const displayMessage = this.SECURITY_CODE_ERROR_MESSAGES[errorMessage] ?? errorMessage;
    securityCodeErrorMessageElement.textContent = displayMessage;
    helperErrorElement.setAttribute('aria-label', displayMessage);
    helperErrorElement.style.display = 'flex';
  }

  verifyIsSecurityCodeReferenceTrue(paymentMethod: PaymentMethod): boolean {
    return this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] === true;
  }

  setSecurityCodeReferenceFalse(paymentMethod: PaymentMethod): void {
    this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = false;
  }

  setSecurityCodeReferenceTrue(paymentMethod: PaymentMethod): void {
    this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = true;
  }

  mountSecurityCodeField(paymentMethod: PaymentMethod): void {
    if (!this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
      return;
    }

    this.unmountCardForm();
    this.unmountActiveSecurityCodeInstance();
    this.setSecurityCodeReferenceFalse(paymentMethod);

    const waitSecurityCodeFieldMountInterval = setInterval(() => {
      if (document.getElementById(`mp-super-token-security-code-input-${paymentMethod.token}`)) {
        clearInterval(waitSecurityCodeFieldMountInterval);

        const securityCodePlaceholderText = ('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)?.length === 3
          ? this.SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS
          : this.SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS;

        if (!window.MPCheckoutFieldsDispatcher && typeof window.sendMetric === 'function' && !this.securityFieldDispatcherMissingReported) {
          window.sendMetric('MP_CHECKOUT_FIELDS_DISPATCHER_MISSING', 'super_token_cvv_mount', 'mp_super_token_init_error');
          this.securityFieldDispatcherMissingReported = true;
        }

        const securityCodeField = this.mpSdkInstance.fields.create('securityCode', {
          placeholder: securityCodePlaceholderText,
          ariaRequired: true,
          style: {
            'font-size': '16px',
            height: '48px',
            padding: '12px',
            fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif'
          }
        })
          .mount(`mp-super-token-security-code-input-${paymentMethod.token}`)
          .on('error', (e) => this.mpSuperTokenMetrics.errorToMountCVVField(e, paymentMethod))
          .on('ready', () => {
            securityCodeField.update({
              settings: ('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)
            });

            this.mpSuperTokenMetrics.sendMetric('super_token_cvv_field_ready', 'true', '');
            this.storeActiveSecurityCodeInstance(securityCodeField);

            if (this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
              const securityCodeTooltip = document.querySelector(`#mp-super-token-security-code-container-${paymentMethod.token} .mp-super-token-security-code-tooltip`);
              const securityCodeInput = document.querySelector(`#mp-super-token-security-code-input-${paymentMethod.token}`);

              if (!securityCodeTooltip || !securityCodeInput) return;

              const securityCodeTooltipClone = securityCodeTooltip.cloneNode(true) as HTMLElement;
              securityCodeTooltipClone.style.display = 'flex';
              securityCodeInput.appendChild(securityCodeTooltipClone);
            }
          })
          .on('validityChange', (e: RawSdkSecurityCodeValidity) => {
            this.setSecurityCodeReferenceTrue(paymentMethod);

            if (e.errorMessages.length === 0) {
              if (window.MPCheckoutFieldsDispatcher) {
                window.MPCheckoutFieldsDispatcher.addEventListenerDispatcher(
                  null,
                  "focusout",
                  "super_token_cvv_filled",
                  {
                    onlyDispatch: true
                  }
                );
              }

              this.mpSuperTokenMetrics.sendMetric('super_token_cvv_filled', 'true', '');
              this.toggleSecurityCodeErrorMessage('', paymentMethod);
            } else {
              this.setSecurityCodeReferenceFalse(paymentMethod);
            }

            const errorMessage = e.errorMessages[0]?.cause ?? '';

            this.toggleSecurityCodeErrorMessage(errorMessage, paymentMethod);
          });
      }
    }, 200);
  }

  handleInstallmentsWithoutFeePillVisibility(): void {
    const allPaymentMethods = document.querySelectorAll<HTMLElement>('.mp-super-token-payment-method');

    allPaymentMethods.forEach(paymentMethodElement => {
      const valuePropPill = paymentMethodElement.querySelector<HTMLElement>(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_VALUE_PROP}`);
      if (!valuePropPill) {
        return;
      }

      valuePropPill.style.display = 'flex';
    });
  }

  showCardHolderNameHelperInfo(): void {
    const cardHolderNameHelperInfo = document.querySelector<HTMLElement>(this.CARD_HOLDER_NAME_HELPER_INFO_SELECTOR);
    if (cardHolderNameHelperInfo) {
      cardHolderNameHelperInfo.style.display = 'flex';
    }
  }

  removeMercadoPagoPrivacyPolicyFooter(): void {
    const footer = this.getCustomCheckoutEntireElement()?.querySelector('#mp-super-token-privacy-policy-footer') ?? null;
    if (!footer) {
      return;
    }

    footer.remove();
  }

  removeHorizontalRow(): void {
    const horizontalRow = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW}`);
    if (!horizontalRow) {
      return;
    }
    horizontalRow.remove();
  }

  installmentsWasSelected(paymentMethod: PaymentMethod | null): boolean {
    const installmentsSelect = document.getElementById(
      `mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`,
    ) as HTMLSelectElement | null;

    return !!installmentsSelect?.value;
  }

  setInstallmentsErrorState(paymentMethod: PaymentMethod | null, hasError: boolean): void {
    const paymentMethodIdentifier = this.paymentMethodIdentifier(paymentMethod);
    const installmentsSelect = document.getElementById(`mp-super-token-installments-select-${paymentMethodIdentifier}`);
    const installmentsLabel = document.querySelector<HTMLElement>(`label[for="mp-super-token-installments-select-${paymentMethodIdentifier}"]`);
    const installmentsErrorHelper = document.querySelector<HTMLElement>(`#mp-super-token-installments-error-${paymentMethodIdentifier}`);

    if (!installmentsSelect || !installmentsLabel || !installmentsErrorHelper) return;

    if (hasError) {
      installmentsErrorHelper.style.display = 'flex';
      installmentsSelect.classList.add('mp-super-token-error');
      installmentsLabel.classList.add('mp-super-token-label-error');
    } else {
      installmentsErrorHelper.style.display = 'none';
      installmentsSelect.classList.remove('mp-super-token-error');
      installmentsLabel.classList.remove('mp-super-token-label-error');
    }
  }

  forceSecurityCodeValidation(paymentMethod: PaymentMethod): void {
    const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
    if (!securityCodeContainer) {
      return;
    }

    const activeInstance = this.securityFieldsActiveInstance;
    if (!activeInstance) {
      this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
      return;
    }

    activeInstance.focus();
    setTimeout(() => {
      activeInstance.blur();
      setTimeout(() => {
        const hasError = securityCodeContainer.classList.contains('error');
        if (!hasError) {
          this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
        }
      }, 100);
    }, 50);
  }

  forceShowValidationErrors(): void {
    const paymentMethod = this.activePaymentMethod;
    if (!paymentMethod) {
      return;
    }

    if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod) && !this.isConsumerCredits(paymentMethod)) {
      return;
    }

    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }

    if (
      this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined) &&
      !this.verifyIsSecurityCodeReferenceTrue(paymentMethod)
    ) {
      this.forceSecurityCodeValidation(paymentMethod);
    }

    if ((this.isCreditCard(paymentMethod) || this.isConsumerCredits(paymentMethod)) && !this.installmentsWasSelected(paymentMethod)) {
      this.setInstallmentsErrorState(paymentMethod, true);
    }

    paymentMethodElement.scrollIntoView({ behavior: 'smooth' });
  }

  isSelectedPaymentMethodValid(): boolean {
    try {
      const paymentMethod = this.activePaymentMethod;
      if (!paymentMethod) {
        return false;
      }

      if (this.isAccountMoney(paymentMethod) || this.isPrepaidCard(paymentMethod)) {
        return true;
      }

      if (paymentMethod.id === this.NEW_CARD_TYPE) {
        return true;
      }

      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      if (!paymentMethodElement) {
        return false;
      }

      if (!this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
        return true;
      }

      if (!this.securityFieldsActiveInstance) {
        return false;
      }

      const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
      if (!securityCodeContainer) {
        return false;
      }

      if (!this.verifyIsSecurityCodeReferenceTrue(paymentMethod)) {
        return false;
      }

      const hasError = securityCodeContainer.classList.contains('error');
      const helperError = securityCodeContainer.querySelector<HTMLElement>('#mp-input-with-tooltip-helper-error');
      const isErrorVisible = helperError && helperError.style.display === 'flex';

      if (hasError || isErrorVisible) {
        return false;
      }

      return true;
    } catch (error) {
      return false;
    }
  }

  validateInstallmentSelection(): boolean {
    try {
      const paymentMethod = this.activePaymentMethod;
      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      const installmentsDropdown = paymentMethodElement?.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`);

      if (installmentsDropdown && paymentMethod && (this.isCreditCard(paymentMethod) || this.isConsumerCredits(paymentMethod)) && !this.installmentsWasSelected(paymentMethod)) {
        const paymentMethodType = this.isConsumerCredits(paymentMethod) ? 'consumer_credits' : 'credit_card';
        this.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected(paymentMethodType);
        this.forceShowValidationErrors();
        return false;
      }

      return true;
    } catch (error) {
      this.mpSuperTokenMetrics?.sendMetric('error_to_validate_installment_selection', 'true', (error as Error)?.message ?? 'unknown');
      try {
        this.forceShowValidationErrors();
      } catch (uiError) {
        // Rendering the errors is best-effort; never let it mask the original failure being rethrown.
      }
      throw error;
    }
  }

  async getAccountPaymentMethods(token: string): Promise<RawAccountPaymentMethodsResponse> {
    this.setSuperToken(token);

    return await this.mpSdkInstance.getAccountPaymentMethods(token);
  }

  addMercadoPagoPrivacyPolicyFooter(): void {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;

    const footer = document.createElement('footer');
    footer.classList.add(this.SUPER_TOKEN_STYLES.MERCADO_PAGO_PRIVACY_POLICY_FOOTER);
    footer.id = 'mp-super-token-privacy-policy-footer';
    footer.innerHTML = `<span>${this.MERCADO_PAGO_PRIVACY_POLICY}</span>`;

    customCheckoutEntireElement.insertBefore(footer, customCheckoutEntireElement.firstChild);
  }

  addHorizontalRow(): void {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;

    const horizontalRow = document.createElement('hr');
    horizontalRow.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW);

    customCheckoutEntireElement.insertBefore(horizontalRow, customCheckoutEntireElement.firstChild);
  }

  convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement: HTMLElement): void {
    customCheckoutEntireElement.id = this.SUPER_TOKEN_STYLES.ROOT_ID;
    customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    customCheckoutEntireElement.setAttribute('role', 'listbox');
    customCheckoutEntireElement.setAttribute('aria-label', this.PAYMENT_METHODS_LIST_ALT_TEXT);
    customCheckoutEntireElement.setAttribute('tabindex', '0');
    customCheckoutEntireElement.parentElement?.classList.add('mp-box-shadow-none');
    customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
  }

  convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement: HTMLElement): void {
    const creditCardFormElement = customCheckoutEntireElement.querySelector<HTMLElement>(this.NEW_CHECKOUT_CONTAINER_SELECTOR)
      ?? customCheckoutEntireElement.querySelector<HTMLElement>(this.OLD_CHECKOUT_CONTAINER_SELECTOR);
    if (!creditCardFormElement) return;

    const createAccordionHeader = (): HTMLElement => {
      const accordionHeader = document.createElement('section');
      accordionHeader.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_HEADER);
      accordionHeader.setAttribute('aria-label', this.NEW_CARD_TEXT);
      accordionHeader.setAttribute('tabindex', '0');
      accordionHeader.setAttribute('role', 'option');
      accordionHeader.setAttribute('aria-selected', 'false');
      // Build via DOM APIs (not innerHTML): setAttribute/textContent never parse their values as
      // HTML, so the localized WHITE_CARD_PATH/NEW_CARD_TEXT cannot break out of the markup (CWE-79),
      // matching the XSS-safe rendering used across the refactored view tree.
      const cardIcon = document.createElement('img');
      cardIcon.setAttribute('src', this.WHITE_CARD_PATH);
      const cardLabel = document.createElement('span');
      cardLabel.className = this.SUPER_TOKEN_STYLES.ACCORDION_TITLE;
      cardLabel.textContent = this.NEW_CARD_TEXT;
      accordionHeader.append(cardIcon, cardLabel);
      accordionHeader.addEventListener('click', () => {
        this.onSelectNewCardPaymentMethod();
      });
      accordionHeader.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.code === 'Space' || e.key === 'Enter') {
          e.preventDefault();
          this.onSelectNewCardPaymentMethod();
        }
      });

      return accordionHeader;
    };

    const addAccordionClasses = (accordionElement: HTMLElement): void => {
      accordionElement.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION);
      accordionElement.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)?.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
    };

    addAccordionClasses(creditCardFormElement);
    const accordionHeader = createAccordionHeader();
    creditCardFormElement.addEventListener('keyup', (e: KeyboardEvent) => {
      if (e.key === 'Tab') {
        accordionHeader.focus();
      }
    });

    creditCardFormElement.appendChild(accordionHeader);
  }

  focusFirstPaymentMethod(): void {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();

    const firstPaymentMethod = customCheckoutEntireElement?.querySelector<HTMLElement>('article');
    if (firstPaymentMethod) {
      firstPaymentMethod.focus();
      return;
    }

    const firstAccordion = customCheckoutEntireElement?.querySelector<HTMLElement>('section');
    if (firstAccordion) {
      firstAccordion.focus();
    }
  }

  onCustomCheckoutWasRendered(customCheckoutEntireElement: HTMLElement, paymentMethods: PaymentMethod[]): void {
    this.hideWalletButton();
    this.hideCardFlags();
    this.convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement);
    this.addHorizontalRow();
    this.addMercadoPagoPrivacyPolicyFooter();
    this.renderSavedMethods(customCheckoutEntireElement, paymentMethods);
    this.convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement);
    this.focusFirstPaymentMethod();
    void this.selectPreloadedPaymentMethod();
    this.removeAnimationInitialState();
    this.hideAllPaymentMethodDetails();
    // After loading the payment methods, set the checkout type to super_token
    this.setCheckoutType(this.SUPER_TOKEN_CHECKOUT_TYPE);
  }

  removeAnimationInitialState(): void {
    const ANIMATION_DELAY = 750;

    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;

    setTimeout(() => {
      customCheckoutEntireElement.classList.remove(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
    }, ANIMATION_DELAY);
  }

  renderAccountPaymentMethods(accountPaymentMethods: PaymentMethod[], amount: string | null): void {
    try {
      this.storeAmount(amount);
      this.storeActivePaymentMethod(this.getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods) ?? null);

      if (this.paymentMethodsAreRendered() || this.isRendering) return;

      if (!this.hasStoredPaymentMethods()) this.storePaymentMethodsInMemory(accountPaymentMethods);
      this.isRendering = true;

      const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();

      if (!customCheckoutEntireElement) {
        this.isRendering = false;
        throw new Error(MPSuperTokenErrorCodes.CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND);
      }

      this.onCustomCheckoutWasRendered(customCheckoutEntireElement, accountPaymentMethods);

      this.isRendering = false;
      setTimeout(() => {
        const sdkInstanceId = this.mpSuperTokenMetrics.getSdkInstanceId();
        this.mpSuperTokenMetrics.sendMetric('super_token_methods_ready', 'true', '');
        document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId } }));
      }, 500);
    } catch (error) {
      this.mpSuperTokenMetrics.errorToRenderAccountPaymentMethods(error);
    }
  }
}

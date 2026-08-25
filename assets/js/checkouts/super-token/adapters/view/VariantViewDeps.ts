/**
 * Stable dependencies the variant views are built with. This is the *view-local*
 * config seam (TASK-008): the composition root (TASK-009) maps the localized
 * `wc_mercadopago_supertoken_bundle_params` into this value object and injects it,
 * so the views never read `window.*`. Kept here (not in `core/config.ts`) because
 * these are presentation inputs owned by the view layer.
 */

/**
 * Minimal shape of the legacy `WCEmailListener` the v2.1 view needs to show and keep
 * the buyer's e-mail in the block header. Grounded in v2.1/entities/email-listener.js.
 */
export interface EmailListenerPort {
  isValid(email: string | null | undefined): boolean;
  getEmail(): string;
  onEmailChange(callback: (email: string, isValid: boolean) => void): void;
}

/** Copy (i18n) the views use to title blocks and label methods. */
export interface SuperTokenViewCopy {
  paymentMethodsListText: string;
  savedCardsTitle: string;
  savedCardTitle: string;
  mpMethodsTitle: string;
  savedPaymentMethodTitle: string;
  accountMoneyBalanceText: string;
  mercadoPagoCardName: string;
  mercadoPagoCreditCardName: string;
  lastDigitsText: string;
  interestFreePartOneText: string;
  interestFreePartTwoText: string;
  accountMoneyText: string;
  accountMoneyWalletWithInvestmentText: string;
  accountMoneyWalletText: string;
  accountMoneyInvestmentText: string;
  accountMoneyAvailableText: string;
  // Card detail accordion (installments + security code).
  installmentsInputTitle: string;
  installmentsRequiredMessage: string;
  securityCodeInputTitle: string;
  securityCodeTooltip3Digits: string;
  securityCodeTooltip4Digits: string;
  // Consumer-credits detail accordion.
  installmentsPlaceholder: string;
  consumerCreditsDueDateText: string;
  consumerCreditsDebitAutoText: string;
}

/** Asset paths the views resolve onto rows and headers. */
export interface SuperTokenViewThumbnails {
  newMpLogoPath: string;
  mpLogoBluePath: string;
  mpLogoDarkPath: string;
  whiteCardPath: string;
  yellowWalletPath: string;
  yellowMoneyPath: string;
  /** Per-payment-method-id thumbnail overrides, keyed by payment method id. */
  paymentMethodsThumbnails: Record<string, string>;
}

export interface VariantViewDeps {
  /**
   * Uppercase site id (MLB, MLA, MLM, ...). Normalized to uppercase once by the composition
   * root when it builds these deps from the localized params (TASK-009) — the views compare
   * it directly and rely on this contract, so it must not arrive in mixed case.
   */
  siteId: string;
  copy: SuperTokenViewCopy;
  thumbnails: SuperTokenViewThumbnails;
  /** Live e-mail listener, or null when the store cannot resolve the buyer e-mail. */
  emailListener: EmailListenerPort | null;
  /** Fallback e-mail used for the block header when the listener yields none. */
  currentUserEmail: string;
  /** Abbreviated month names keyed by lowercase 3-letter English key (jan..dec) for due dates. */
  monthsAbbreviated: Record<string, string>;
}

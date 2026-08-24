/**
 * The exact slice of localized store configuration the domain depends on. The core
 * never reads `window.wc_mercadopago_supertoken_bundle_params` directly — the
 * composition root (TASK-008/009) maps those params into this value object and
 * injects it. Keeping the contract here (instead of reusing the loose param bag)
 * makes the domain's real inputs explicit and testable.
 */

/** Site-specific legal texts of the consumer-credits installment hint (RN-5, per-site). */
export interface ConsumerCreditsHintCopy {
  // MLB
  interestRateMlb: string;
  effectiveTotalCostMlb: string;
  iofMlb: string;
  borrowedAmountMlb: string;
  perMonth: string;
  perYear: string;
  // MLM
  catMlm: string;
  noIvaMlm: string;
  tnaMlm: string;
  systemAmortizationMlm: string;
  // MLA / default
  cfteaMla: string;
  tnaMla: string;
  teaMla: string;
  fixedRate: string;
}

/** Copy (i18n) the domain uses to name and label payment methods. */
export interface SuperTokenCopy {
  accountMoneyText: string;
  accountMoneyWalletWithInvestmentText: string;
  accountMoneyWalletText: string;
  accountMoneyInvestmentText: string;
  accountMoneyAvailableText: string;
  mercadoPagoCardName: string;
  mercadoPagoCreditCardName: string;
  lastDigitsText: string;
  interestFreePartOneText: string;
  interestFreePartTwoText: string;
  installmentsInterestFreeOptionText: string;
  consumerCreditsHint: ConsumerCreditsHintCopy;
}

/** Asset paths the domain resolves onto payment methods during decoration. */
export interface SuperTokenThumbnails {
  /** Per-payment-method-id thumbnail overrides, keyed by payment method id. */
  paymentMethodsThumbnails: Record<string, string>;
  whiteCardPath: string;
  yellowWalletPath: string;
  yellowMoneyPath: string;
  mpLogoBluePath: string;
  mpLogoDarkPath: string;
}

export interface SuperTokenDomainConfig {
  /** Uppercase site id (MLB, MLA, MLM, MCO, MPE, MLC, ...). */
  siteId: string;
  /** BCP-47 locale tag for Intl.NumberFormat. */
  intl: string;
  /** ISO currency code for Intl.NumberFormat. */
  currency: string;
  /** Ordering preference: 'cards_first' (default) or 'account_money_first'. */
  paymentMethodsOrder?: string;
  /**
   * Resolved A/B variant ('v2' | 'v2.1'). Only the Mercado Pago credit-card decoration
   * differs between variants (v2.1 gets the MP name + logo; v2 keeps the issuer card).
   */
  variant: string;
  copy: SuperTokenCopy;
  thumbnails: SuperTokenThumbnails;
}

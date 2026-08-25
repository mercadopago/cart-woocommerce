/**
 * Domain invariants for the Super Token checkout — the literal values the business
 * rules are defined against. These are NOT configuration: they never vary per store
 * or per site, so they live in the domain instead of the injected config.
 *
 * Preserved 1:1 from the legacy MPSuperTokenPaymentMethods class fields
 * (assets/js/checkouts/super-token/v2.1/entities/super-token-payment-methods.js).
 */

import type { PaymentMethodType } from '@super-token/types/external-globals';

export const CREDIT_CARD_TYPE: PaymentMethodType = 'credit_card';
export const DEBIT_CARD_TYPE: PaymentMethodType = 'debit_card';
export const ACCOUNT_MONEY_TYPE: PaymentMethodType = 'account_money';
export const PREPAID_CARD_TYPE: PaymentMethodType = 'prepaid_card';
/** The SDK names consumer credits 'digital_currency', not 'consumer_credits'. */
export const CONSUMER_CREDITS_TYPE: PaymentMethodType = 'digital_currency';
/** UI-only pseudo-method for the "add new card" option; never returned by the SDK. */
export const NEW_CARD_TYPE = 'new_card';

export const MERCADO_PAGO_ISSUER_NAME = 'mercado pago';

export const COLOMBIA_ACCRONYM = 'MCO';
export const MEXICO_ACCRONYM = 'MLM';
export const BRAZIL_ACCRONYM = 'MLB';
export const ARGENTINA_ACCRONYM = 'MLA';

/** Sites whose installment titles carry the third-party bank-interest asterisk (RN-5). */
export const COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];

/** Sites whose Mercado Pago credit-card icon is the blue variant; all others use dark (RN-7). */
export const MP_CARD_BLUE_SITES = ['MLA', 'MLM'];

export const PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
export const PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';

/** Max saved cards shown across credit/debit/prepaid — single source (RN-1). */
export const MAX_CREDIT_CARDS = 3;
/** Max retries per error code before the flow stops offering a retry (RN-2). */
export const MAX_ATTEMPTS_BY_ERROR_CODE = 3;

/** Installments beyond this are dropped in Colombia (RN, getInstallmentsLimit). */
export const COLOMBIA_INSTALLMENTS_LIMIT = 6;

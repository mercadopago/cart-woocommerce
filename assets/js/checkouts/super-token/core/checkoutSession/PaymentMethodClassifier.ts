/**
 * Pure classification of account payment methods (RN-6) plus the stable identity
 * key the session state matches methods by. No DOM, SDK or window — the predicates
 * only read the raw SDK payment-method shape.
 *
 * Preserved 1:1 from MPSuperTokenPaymentMethods (v2.1) lines 226-230, 778-804.
 */

import type {
  AccountMoneyPaymentMethod,
  ConsumerCreditsPaymentMethod,
  CreditCardPaymentMethod,
  DebitCardPaymentMethod,
  PaymentMethod,
  PrepaidCardPaymentMethod,
} from '@super-token/types/external-globals';
import {
  ACCOUNT_MONEY_TYPE,
  CONSUMER_CREDITS_TYPE,
  CREDIT_CARD_TYPE,
  DEBIT_CARD_TYPE,
  MERCADO_PAGO_ISSUER_NAME,
  NEW_CARD_TYPE,
  PREPAID_CARD_TYPE,
} from '@super-token/core/constants';

type MaybePaymentMethod = PaymentMethod | null | undefined;

const issuerIsMercadoPago = (issuerName: string | undefined): boolean =>
  !!issuerName?.toLowerCase()?.includes(MERCADO_PAGO_ISSUER_NAME);

export const isCreditCard = (
  paymentMethod: MaybePaymentMethod,
): paymentMethod is CreditCardPaymentMethod => paymentMethod?.type === CREDIT_CARD_TYPE;

export const isDebitCard = (
  paymentMethod: MaybePaymentMethod,
): paymentMethod is DebitCardPaymentMethod => paymentMethod?.type === DEBIT_CARD_TYPE;

export const isAccountMoney = (
  paymentMethod: MaybePaymentMethod,
): paymentMethod is AccountMoneyPaymentMethod => paymentMethod?.type === ACCOUNT_MONEY_TYPE;

export const isPrepaidCard = (
  paymentMethod: MaybePaymentMethod,
): paymentMethod is PrepaidCardPaymentMethod => paymentMethod?.type === PREPAID_CARD_TYPE;

export const isConsumerCredits = (
  paymentMethod: MaybePaymentMethod,
): paymentMethod is ConsumerCreditsPaymentMethod =>
  paymentMethod?.type === CONSUMER_CREDITS_TYPE;

export const isNewCard = (paymentMethod: { type?: string } | null | undefined): boolean =>
  paymentMethod?.type === NEW_CARD_TYPE;

export const isMercadoPagoCard = (paymentMethod: MaybePaymentMethod): boolean =>
  isPrepaidCard(paymentMethod) && issuerIsMercadoPago(paymentMethod.issuer?.name);

export const isMercadoPagoCreditCard = (paymentMethod: MaybePaymentMethod): boolean =>
  isCreditCard(paymentMethod) && issuerIsMercadoPago(paymentMethod.issuer?.name);

export const userHasAccountMoney = (paymentMethod: AccountMoneyPaymentMethod): boolean =>
  paymentMethod.has_account_money;

export const userHasAccountMoneyInvested = (paymentMethod: AccountMoneyPaymentMethod): boolean =>
  paymentMethod.has_account_money_invested;

/**
 * Stable key for a saved payment method: id + last four digits when present.
 * Used to reconcile a preloaded selection against the fetched list.
 */
export const paymentMethodIdentifier = (paymentMethod: MaybePaymentMethod): string => {
  if (!paymentMethod) return '';

  const lastFourDigits =
    'card' in paymentMethod ? paymentMethod.card?.card_number?.last_four_digits ?? '' : '';
  return `${paymentMethod.id}${lastFourDigits}`;
};

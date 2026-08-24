/**
 * Rules deciding whether a card needs a CVV/ESC re-fetch before it can be used
 * (RN-3). Pure: the legacy `data-cvv-is-required-double-check` DOM guard is lifted
 * into an `alreadyDoubleChecked` parameter the caller reads from the element, so the
 * core never touches the DOM.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): 1225-1267.
 */

import type { PaymentMethod, SecurityCodeSettings } from '@super-token/types/external-globals';
import { isCreditCard, isDebitCard } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';

type MaybePaymentMethod = PaymentMethod | null | undefined;

export const securityCodeIsRequired = (
  securityCodeSettings: SecurityCodeSettings | undefined,
): boolean => {
  if (!securityCodeSettings) {
    return false;
  }

  return securityCodeSettings.mode === 'mandatory';
};

/**
 * Whether the payment method must be re-fetched to obtain the ESC-backed token.
 * @param alreadyDoubleChecked replaces the legacy DOM guard: the caller passes
 *   whether the element is already marked as double-checked (prevents re-fetch loops).
 */
export const shouldFetchPaymentMethodAgain = (
  paymentMethod: MaybePaymentMethod,
  alreadyDoubleChecked: boolean,
): boolean => {
  if (!paymentMethod) {
    throw new Error(MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS);
  }

  if (alreadyDoubleChecked) return false;

  return (
    (isCreditCard(paymentMethod) || isDebitCard(paymentMethod)) &&
    securityCodeIsRequired(paymentMethod.security_code_settings) &&
    paymentMethod.has_esc === true
  );
};

export const hasMissingEsc = (paymentMethod: MaybePaymentMethod): boolean =>
  (isCreditCard(paymentMethod) || isDebitCard(paymentMethod)) &&
  securityCodeIsRequired(paymentMethod.security_code_settings) &&
  typeof paymentMethod.has_esc === 'undefined';

export type SkipReason =
  | 'already_checked'
  | 'not_card'
  | 'security_code_not_required'
  | 'esc_disabled'
  | 'unknown';

/** Diagnostic reason a re-fetch was skipped (observability, RN-3). */
export const getSkipReason = (
  paymentMethod: MaybePaymentMethod,
  alreadyDoubleChecked: boolean,
): SkipReason => {
  if (alreadyDoubleChecked) {
    return 'already_checked';
  }

  if (!isCreditCard(paymentMethod) && !isDebitCard(paymentMethod)) {
    return 'not_card';
  }

  if (!securityCodeIsRequired(paymentMethod.security_code_settings)) {
    return 'security_code_not_required';
  }

  if (paymentMethod.has_esc !== true) {
    return 'esc_disabled';
  }

  return 'unknown';
};

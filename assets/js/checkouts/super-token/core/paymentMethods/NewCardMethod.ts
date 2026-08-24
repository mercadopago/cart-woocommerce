/**
 * The "add a new card" option — a UI-selectable pseudo-method the SDK never returns
 * in the account list, so it carries no decoration. Modeled here so the view can
 * resolve every selectable option uniformly through the registry.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import { isNewCard } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BasePaymentMethod } from '@super-token/core/paymentMethods/BasePaymentMethod';

export class NewCardMethod extends BasePaymentMethod {
  matches(paymentMethod: PaymentMethod): boolean {
    return isNewCard(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    return paymentMethod;
  }
}

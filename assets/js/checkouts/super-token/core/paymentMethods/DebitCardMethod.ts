/**
 * Debit card — no installments (extends BasePaymentMethod, RN-8). Decorated as
 * "<issuer> Débito" with the per-id/white-card thumbnail.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize debit branch 2277-2285.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import { isDebitCard } from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BasePaymentMethod } from '@super-token/core/paymentMethods/BasePaymentMethod';

export class DebitCardMethod extends BasePaymentMethod {
  matches(paymentMethod: PaymentMethod): boolean {
    return isDebitCard(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    if (!isDebitCard(paymentMethod)) return paymentMethod;

    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    paymentMethod.name = `${paymentMethod.issuer?.name ?? paymentMethod.name} Débito`;
    return paymentMethod;
  }
}

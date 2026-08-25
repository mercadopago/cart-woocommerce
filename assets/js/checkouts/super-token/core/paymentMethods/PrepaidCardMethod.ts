/**
 * Prepaid card — no installments (RN-8). A Mercado Pago prepaid card also gets the
 * MP card name; every prepaid card gets the per-id/white-card thumbnail. Both effects
 * apply cumulatively, mirroring the two separate ifs in the legacy normalize.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize MP-card 2269-2271 and
 * prepaid 2273-2275 branches.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import {
  isMercadoPagoCard,
  isPrepaidCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BasePaymentMethod } from '@super-token/core/paymentMethods/BasePaymentMethod';

export class PrepaidCardMethod extends BasePaymentMethod {
  matches(paymentMethod: PaymentMethod): boolean {
    return isPrepaidCard(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    if (!isPrepaidCard(paymentMethod)) return paymentMethod;

    if (isMercadoPagoCard(paymentMethod)) {
      paymentMethod.name = this.config.copy.mercadoPagoCardName;
    }

    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    return paymentMethod;
  }
}

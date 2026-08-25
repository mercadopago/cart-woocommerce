/**
 * Credit card — has installments (RN-8). Decoration distinguishes a Mercado Pago
 * credit card (own name + blue/dark MP icon by site, RN-7) from a regular issuer
 * card ("<issuer> Crédito").
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize credit branch 2277-2285,
 * getMpCardThumbnailPath 813-816.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import { MP_CARD_BLUE_SITES } from '@super-token/core/constants';
import {
  isCreditCard,
  isMercadoPagoCreditCard,
} from '@super-token/core/checkoutSession/PaymentMethodClassifier';
import { BasePaymentMethodWithInstallments } from '@super-token/core/paymentMethods/BasePaymentMethodWithInstallments';

export class CreditCardMethod extends BasePaymentMethodWithInstallments {
  matches(paymentMethod: PaymentMethod): boolean {
    return isCreditCard(paymentMethod);
  }

  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    if (!isCreditCard(paymentMethod)) return paymentMethod;

    // Only the v2.1 variant gives a Mercado Pago credit card the special MP name + blue/dark icon
    // (RN-7); v2 treats every credit card as a regular issuer card. Gating it on the variant keeps
    // the v2.1 presentation out of the v2 A/B cohort (which otherwise leaked via the mutated thumbnail).
    if (this.config.variant === 'v2.1' && isMercadoPagoCreditCard(paymentMethod)) {
      paymentMethod.thumbnail = this.mercadoPagoCardThumbnail() || paymentMethod.thumbnail;
      paymentMethod.name = this.config.copy.mercadoPagoCreditCardName || paymentMethod.name;
      return paymentMethod;
    }

    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    paymentMethod.name = `${paymentMethod.issuer?.name ?? paymentMethod.name} Crédito`;
    return paymentMethod;
  }

  private mercadoPagoCardThumbnail(): string {
    return MP_CARD_BLUE_SITES.includes(this.config.siteId)
      ? this.config.thumbnails.mpLogoBluePath
      : this.config.thumbnails.mpLogoDarkPath;
  }
}

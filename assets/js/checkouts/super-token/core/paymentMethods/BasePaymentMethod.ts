/**
 * Common behavior for every payment-method module. A module knows how to recognize
 * its own method (`matches`), decorate it with the display name and thumbnail
 * (`decorate`, the per-method slice of the legacy normalizeAccountPaymentMethods),
 * and answer domain questions (`requiresInstallments`, `requiresCvv`). Pure — no DOM,
 * SDK or window.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalizeAccountPaymentMethods 2257-2290.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import type { SuperTokenDomainConfig } from '@super-token/core/config';
import { securityCodeIsRequired } from '@super-token/core/checkoutSession/PaymentMethodEligibility';

/** A card-shaped method whose thumbnail can be resolved from the per-id overrides. */
type CardLike = { id: string; thumbnail: string };

export interface PaymentMethodModule {
  matches(paymentMethod: PaymentMethod): boolean;
  /** Applies the display name and thumbnail in place, returning the same method. */
  decorate(paymentMethod: PaymentMethod): PaymentMethod;
  requiresInstallments(): boolean;
  requiresCvv(paymentMethod: PaymentMethod): boolean;
}

export abstract class BasePaymentMethod implements PaymentMethodModule {
  constructor(protected readonly config: SuperTokenDomainConfig) {}

  abstract matches(paymentMethod: PaymentMethod): boolean;

  abstract decorate(paymentMethod: PaymentMethod): PaymentMethod;

  requiresInstallments(): boolean {
    return false;
  }

  requiresCvv(paymentMethod: PaymentMethod): boolean {
    return 'security_code_settings' in paymentMethod
      ? securityCodeIsRequired(paymentMethod.security_code_settings)
      : false;
  }

  /** thumbnails[id] → existing thumbnail → white card fallback (RN-7). */
  protected resolveCardThumbnail(paymentMethod: CardLike): string {
    return (
      this.config.thumbnails.paymentMethodsThumbnails[paymentMethod.id] ||
      paymentMethod.thumbnail ||
      this.config.thumbnails.whiteCardPath
    );
  }
}

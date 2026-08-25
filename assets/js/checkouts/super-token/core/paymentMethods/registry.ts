/**
 * Resolves a raw payment method to the module that owns it. `resolve(pm).decorate(pm)`
 * is the pure equivalent of the legacy normalizeAccountPaymentMethods: each method's
 * decoration lives in its own module instead of a chain of ifs. The modules match on
 * mutually exclusive types, so resolution order is deterministic.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalizeAccountPaymentMethods 2257-2290.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import type { SuperTokenDomainConfig } from '@super-token/core/config';
import type { PaymentMethodModule } from '@super-token/core/paymentMethods/BasePaymentMethod';
import { CreditCardMethod } from '@super-token/core/paymentMethods/CreditCardMethod';
import { DebitCardMethod } from '@super-token/core/paymentMethods/DebitCardMethod';
import { PrepaidCardMethod } from '@super-token/core/paymentMethods/PrepaidCardMethod';
import { AccountMoneyMethod } from '@super-token/core/paymentMethods/AccountMoneyMethod';
import { ConsumerCreditsMethod } from '@super-token/core/paymentMethods/ConsumerCreditsMethod';
import { NewCardMethod } from '@super-token/core/paymentMethods/NewCardMethod';

export class PaymentMethodRegistry {
  private readonly modules: PaymentMethodModule[];

  constructor(config: SuperTokenDomainConfig) {
    this.modules = [
      new CreditCardMethod(config),
      new DebitCardMethod(config),
      new PrepaidCardMethod(config),
      new AccountMoneyMethod(config),
      new ConsumerCreditsMethod(config),
      new NewCardMethod(config),
    ];
  }

  resolve(paymentMethod: PaymentMethod): PaymentMethodModule | undefined {
    return this.modules.find((module) => module.matches(paymentMethod));
  }

  /** Decorates one payment method (name + thumbnail) via its resolved module. */
  decorate(paymentMethod: PaymentMethod): PaymentMethod {
    return this.resolve(paymentMethod)?.decorate(paymentMethod) ?? paymentMethod;
  }

  /**
   * Decorates every method with its display name and thumbnail (the list form of
   * `decorate`). Pure equivalent of the legacy `normalizeAccountPaymentMethods`.
   */
  decorateAccountPaymentMethods(paymentMethods: PaymentMethod[]): PaymentMethod[] {
    return paymentMethods.map((paymentMethod) => this.decorate(paymentMethod));
  }
}

export const createPaymentMethodRegistry = (
  config: SuperTokenDomainConfig,
): PaymentMethodRegistry => new PaymentMethodRegistry(config);

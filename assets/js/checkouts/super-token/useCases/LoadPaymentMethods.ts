/**
 * Loading the buyer's saved payment methods — orchestrates the fetch (auth flow, grounded
 * in `MPSuperTokenAuthenticator.getAccountPaymentMethods`, 139-175) and reuses the domain
 * core to order then decorate the result: `PaymentMethodCatalog.reorderAccountPaymentMethods`
 * (cap + cards/account-money order) then `PaymentMethodRegistry.decorateAccountPaymentMethods`
 * (per-method name/thumbnail decoration) — the pure equivalent of the legacy
 * `renderAccountPaymentMethods` pre-processing (payment-methods.js:2307). When the fetch
 * returns nothing, it renders nothing (matches the legacy empty-list short-circuit,
 * trigger-handler.js:260).
 *
 * The injected renderer receives the already-decorated list — decoration is owned here
 * (via the core), not re-done downstream.
 */

import { PaymentMethodCatalog } from '@super-token/core/checkoutSession/PaymentMethodCatalog';
import { PaymentMethodRegistry } from '@super-token/core/paymentMethods/registry';
import type { SuperTokenDomainConfig } from '@super-token/core/config';
import type { PaymentMethod } from '@super-token/types/external-globals';

/** Runs the SDK auth flow and returns the raw account payment methods, or null. */
export interface AccountPaymentMethodsGateway {
  fetchAccountPaymentMethods(amount: string, buyerEmail: string): Promise<PaymentMethod[] | null>;
}

/** Renders the (already ordered + normalized) payment methods into the checkout. */
export interface AccountPaymentMethodsRenderer {
  renderAccountPaymentMethods(
    paymentMethods: PaymentMethod[],
    amount: string | null,
  ): Promise<void> | void;
}

export interface LoadPaymentMethodsContext {
  gateway: AccountPaymentMethodsGateway;
  renderer: AccountPaymentMethodsRenderer;
  amount: string;
  buyerEmail: string;
}

export class LoadPaymentMethods {
  private readonly catalog: PaymentMethodCatalog;
  private readonly registry: PaymentMethodRegistry;

  constructor(config: SuperTokenDomainConfig) {
    this.catalog = new PaymentMethodCatalog(config.paymentMethodsOrder);
    this.registry = new PaymentMethodRegistry(config);
  }

  async execute(ctx: LoadPaymentMethodsContext): Promise<PaymentMethod[]> {
    const { gateway, renderer, amount, buyerEmail } = ctx;

    const accountPaymentMethods = await gateway.fetchAccountPaymentMethods(amount, buyerEmail);
    if (!accountPaymentMethods || accountPaymentMethods.length === 0) {
      return [];
    }

    const ordered = this.catalog.reorderAccountPaymentMethods(accountPaymentMethods);
    const decorated = this.registry.decorateAccountPaymentMethods(ordered);

    await renderer.renderAccountPaymentMethods(decorated, amount);

    return decorated;
  }
}

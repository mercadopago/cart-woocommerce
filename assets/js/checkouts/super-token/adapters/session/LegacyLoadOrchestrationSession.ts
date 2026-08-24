/**
 * Session adapter that lets the refactored `LoadSuperToken` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.loadSuperToken` (super-token-trigger-handler.js:286-330). The use
 * case owns the load *order* (format, debounce guard, amount-change reset, cache short-circuit,
 * e-mail-listener registration, fetch+render, stale metrics); this adapter supplies the
 * *primitives*, forwarding each to the legacy trigger handler instance and the collaborators it
 * holds — a transitional scaffold while the primitives are ported.
 *
 * `fetchAndRender` reaches the still-legacy `fetchAndRenderSuperTokenPaymentMethods`, itself
 * already delegating to the fetch+render seam (Phase 7b), so this composes on it. The
 * e-mail-listener registration and the stale-metrics dispatch are single legacy methods the
 * inline fallback also calls, kept here as forwarded primitives.
 *
 * The metric name string stays here (the legacy boundary), exposed to the use case as a named
 * intention through `createLoadSuperTokenMetrics`, keeping it out of the domain.
 */

import type {
  LoadSuperTokenSession,
  LoadSuperTokenMetrics,
} from '@super-token/useCases/LoadSuperToken';
import type { PaymentMethod } from '@super-token/types/external-globals';

/** The subset of the legacy trigger handler (and the collaborators it holds) the load orchestration calls. */
export interface LegacyLoadOrchestrationTriggerHandler {
  // formatAmount returns null for an empty/NaN amount (parity with the legacy); the null flows
  // through the amount fields to the SDK exactly as before, so the types own it end to end.
  currentAmount: string | null;
  isFetchingPaymentMethods: boolean;
  amountHasChanged(): boolean;
  emailHasChanged(): boolean;
  resetFlow(): void;
  isSuperTokenPaymentMethodsLoaded(): boolean;
  ensureEmailListenerRegistered(): void;
  fetchAndRenderSuperTokenPaymentMethods(): Promise<void>;
  dispatchStaleCacheMetricsOnce(): void;
  mpSuperTokenAuthenticator: { formatAmount(amount: string | null): string | null };
  mpSuperTokenPaymentMethods: {
    getStoredPaymentMethods(): PaymentMethod[];
    renderAccountPaymentMethods(paymentMethods: PaymentMethod[], amount: string | null): void;
  };
}

export class LegacyLoadOrchestrationSession implements LoadSuperTokenSession {
  constructor(private readonly triggerHandler: LegacyLoadOrchestrationTriggerHandler) {}

  formatAmount(amount: string | null): string | null {
    return this.triggerHandler.mpSuperTokenAuthenticator.formatAmount(amount);
  }

  setCurrentAmount(amount: string | null): void {
    this.triggerHandler.currentAmount = amount;
  }

  currentAmount(): string | null {
    return this.triggerHandler.currentAmount;
  }

  isFetching(): boolean {
    return this.triggerHandler.isFetchingPaymentMethods;
  }

  amountHasChanged(): boolean {
    return this.triggerHandler.amountHasChanged();
  }

  emailHasChanged(): boolean {
    return this.triggerHandler.emailHasChanged();
  }

  resetFlow(): void {
    this.triggerHandler.resetFlow();
  }

  isMethodsLoaded(): boolean {
    return this.triggerHandler.isSuperTokenPaymentMethodsLoaded();
  }

  renderStored(amount: string | null): void {
    const paymentMethods = this.triggerHandler.mpSuperTokenPaymentMethods;
    paymentMethods.renderAccountPaymentMethods(paymentMethods.getStoredPaymentMethods(), amount);
  }

  ensureEmailListenerRegistered(): void {
    this.triggerHandler.ensureEmailListenerRegistered();
  }

  fetchAndRender(): Promise<void> {
    return this.triggerHandler.fetchAndRenderSuperTokenPaymentMethods();
  }

  dispatchStaleCacheMetricsOnce(): void {
    this.triggerHandler.dispatchStaleCacheMetricsOnce();
  }
}

/** Legacy `sendMetric` name from super-token-trigger-handler.js:294. */
const RESET_ON_AMOUNT_CHANGE_METRIC = 'super_token_reset_on_amount_change';

/** The subset of the legacy `MPSuperTokenMetrics` the load orchestration reports through. */
export interface LegacyLoadOrchestrationMetrics {
  sendMetric(name: string, value: string, message: string): void;
}

/**
 * Wraps the legacy metrics instance as the use case's named metric intention, keeping the metric
 * name string at this legacy boundary rather than in the domain.
 */
export function createLoadSuperTokenMetrics(
  metrics: LegacyLoadOrchestrationMetrics,
): LoadSuperTokenMetrics {
  return {
    resetOnAmountChange: () => metrics.sendMetric(RESET_ON_AMOUNT_CHANGE_METRIC, 'true', ''),
  };
}

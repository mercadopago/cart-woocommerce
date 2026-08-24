/**
 * Loading the Super Token payment methods — the orchestration of the legacy
 * `MPSuperTokenTriggerHandler.loadSuperToken` (super-token-trigger-handler.js:286-330), the
 * entry point the Classic `event-handler.js` and the Blocks `cart-update.helper.js` call on
 * every amount refresh. Owns the *order*: format + store the amount, the debounce guard (skip a
 * redundant re-fetch), the amount-change reset, the cache short-circuit (re-render the stored
 * methods), the one-time e-mail-listener registration, the fetch+render, and the one-time stale
 * cache metrics.
 *
 * The fetch+render step delegates to the refactored `FetchAndRenderPaymentMethods` (Phase 7b)
 * through the session, so this composes on it rather than re-implementing the load. The
 * e-mail-listener registration delegates to the refactored `EnsureEmailListenerRegistered` use
 * case through the `mpSuperTokenEnsureEmailListenerRegistered` seam (Phase 10), completing the
 * chain from e-mail change to the TS reset flow.
 */

/** Subset of the legacy trigger handler the load orchestration drives. */
export interface LoadSuperTokenSession {
  formatAmount(amount: string | null): string | null;
  setCurrentAmount(amount: string | null): void;
  currentAmount(): string | null;
  isFetching(): boolean;
  amountHasChanged(): boolean;
  emailHasChanged(): boolean;
  resetFlow(): void;
  isMethodsLoaded(): boolean;
  renderStored(amount: string | null): void;
  ensureEmailListenerRegistered(): void;
  fetchAndRender(): Promise<void>;
  dispatchStaleCacheMetricsOnce(): void;
}

/** Subset of `MPSuperTokenMetrics` emitted by the load orchestration. */
export interface LoadSuperTokenMetrics {
  resetOnAmountChange(): void;
}

export interface LoadSuperTokenContext {
  session: LoadSuperTokenSession;
  metrics: LoadSuperTokenMetrics;
  currentAmount: string | null;
}

export class LoadSuperToken {
  async execute(ctx: LoadSuperTokenContext): Promise<void> {
    const { session, metrics, currentAmount } = ctx;

    session.setCurrentAmount(session.formatAmount(currentAmount));

    // Prevent unnecessary re-fetching of payment methods.
    if (session.isFetching() && !session.amountHasChanged() && !session.emailHasChanged()) {
      return;
    }

    if (session.amountHasChanged()) {
      session.resetFlow();
      metrics.resetOnAmountChange();
    }

    if (session.isMethodsLoaded()) {
      session.renderStored(session.currentAmount());
      return;
    }

    session.ensureEmailListenerRegistered();

    await session.fetchAndRender();

    session.dispatchStaleCacheMetricsOnce();
  }
}

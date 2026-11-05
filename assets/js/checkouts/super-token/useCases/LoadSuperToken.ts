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

/** Why Super Token stays off for the store: the plugin lacks the site's catalog (`outdated_plugin`). */
export type SuperTokenBlock = { reason: 'outdated_plugin' };

/** Subset of the legacy trigger handler the load orchestration drives. */
export interface LoadSuperTokenSession {
  getBlock(): SuperTokenBlock | null;
  markBlockReported(): boolean;
  formatAmount(amount: string | null): string | null;
  setCurrentAmount(amount: string | null): void;
  currentAmount(): string | null;
  isFetching(): boolean;
  amountHasChanged(): boolean;
  emailHasChanged(): boolean;
  cancelInvalidAmount(): void;
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
  invalidAmount(): void;
  outdatedPlugin(): void;
}

export interface LoadSuperTokenContext {
  session: LoadSuperTokenSession;
  metrics: LoadSuperTokenMetrics;
  currentAmount: string | null;
}

export class LoadSuperToken {
  async execute(ctx: LoadSuperTokenContext): Promise<void> {
    const { session, metrics, currentAmount } = ctx;

    // MCO needs the plugin's es_CO catalog: otherwise the store stays on the Custom Checkout, through
    // the same cancellation as an invalid amount, and reports why once per page.
    if (session.getBlock()) {
      session.cancelInvalidAmount();
      if (session.markBlockReported()) {
        metrics.outdatedPlugin();
      }
      return;
    }

    const normalizedAmount = session.formatAmount(currentAmount);
    session.setCurrentAmount(normalizedAmount);

    // MLC and MCO have no decimal places; other sites normalize to two.
    if (normalizedAmount === null || !/^(?:0|[1-9]\d*)(?:\.\d{2})?$/.test(normalizedAmount) || !Number.isFinite(Number(normalizedAmount))) {
      session.cancelInvalidAmount();
      metrics.invalidAmount();
      return;
    }

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

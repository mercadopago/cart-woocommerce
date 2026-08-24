/**
 * Cancelling an in-flight Super Token load — the legacy
 * `MPSuperTokenTriggerHandler.cancelLoad` (super-token-trigger-handler.js:237-241), called by the
 * Classic `event-handler.js` when the buyer switches away from the custom method while a fetch is
 * still in flight. Bumps the load generation so the awaiting `FetchAndRenderPaymentMethods` drops
 * its stale result through its generation guard, clears the fetching flag, and resets the stored
 * payment methods.
 *
 * The three state pieces (`loadGeneration`, `isFetchingPaymentMethods`, the controller's stored
 * methods) are the same cluster the fetch+render (Phase 7a) and load orchestration (Phase 7c)
 * steps already drive, reached here through the same trigger session — no new legacy surface.
 */

/** Subset of the legacy trigger handler (and the controller it holds) the cancel drives. */
export interface CancelLoadSession {
  bumpLoadGeneration(): void;
  setFetching(isFetching: boolean): void;
  resetPaymentMethods(): void;
}

export interface CancelLoadContext {
  session: CancelLoadSession;
}

export class CancelLoad {
  execute(ctx: CancelLoadContext): void {
    const { session } = ctx;

    // Bump first so a fetch awaiting mid-flight sees the changed generation and renders nothing.
    session.bumpLoadGeneration();
    session.setFetching(false);
    session.resetPaymentMethods();
  }
}

/**
 * Resetting the whole custom checkout — the legacy `MPSuperTokenTriggerHandler.resetCustomCheckout`
 * (super-token-trigger-handler.js:100-145), called after a recoverable error (through
 * `resetSuperTokenOnError`) and when the buyer changes the e-mail mid-flow. Owns the fixed order
 * of the reset head — hide the error, report a missing custom handler once, raise the spinner,
 * invalidate the token, remount the card form when methods are stored and clear the cache when
 * asked — then kicks off `loadSuperToken` and hands the async tail off to the session.
 *
 * `shouldClearCache` mirrors the legacy default: the error path clears it (fresh fetch), the
 * e-mail-change path also clears it; only internal callers could opt out. The tail
 * (`finalizeResetTail`) — the delayed spinner removal, the preloaded-method restore and the
 * deferred last-exception handling — stays a single injected session step for now, ported later.
 *
 * Fire-and-forget like the legacy: neither caller awaits it, so the load promise is intentionally
 * floated with its tail attached.
 */

/** Subset of the legacy trigger handler (and the collaborators it holds) the reset head drives. */
export interface ResetCustomCheckoutSession {
  hideSuperTokenError(): void;
  reportCustomHandlerMissingOnReset(): void;
  createLoadSpinner(): void;
  setSuperTokenValidation(isValid: boolean): void;
  hasStoredPaymentMethods(): boolean;
  remountCardForm(): void;
  resetFlow(): void;
  currentAmount(): string | null;
  loadSuperToken(currentAmount: string | null): Promise<void>;
  finalizeResetTail(): void;
}

export interface ResetCustomCheckoutContext {
  session: ResetCustomCheckoutSession;
  shouldClearCache: boolean;
}

export class ResetCustomCheckout {
  execute(ctx: ResetCustomCheckoutContext): void {
    const { session, shouldClearCache } = ctx;

    session.hideSuperTokenError();
    session.reportCustomHandlerMissingOnReset();
    session.createLoadSpinner();
    session.setSuperTokenValidation(false);

    if (session.hasStoredPaymentMethods()) {
      session.remountCardForm();
    }

    if (shouldClearCache) {
      session.resetFlow();
    }

    void session.loadSuperToken(session.currentAmount()).finally(() => session.finalizeResetTail());
  }
}

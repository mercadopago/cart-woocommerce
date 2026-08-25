/**
 * Registering the e-mail change listener for the Super Token flow — the logic of
 * `MPSuperTokenTriggerHandler.ensureEmailListenerRegistered`
 * (super-token-trigger-handler.js:319-335). Owns the once-guard, the callback registration,
 * and the mark-as-listening step; the callback itself owns the decision to trigger a reset
 * when the buyer changes the e-mail mid-flow.
 *
 * The callback fires on every e-mail change event but resets only when all four conditions
 * hold: the new address is valid, the current amount is present, the address is different
 * from the stored one, and the stored address is already known (non-null). The first
 * meaningful e-mail captured by `loadSuperToken` acts as the baseline; subsequent distinct
 * values trigger a full reset so the saved cards are re-fetched for the new account.
 */

export interface EnsureEmailListenerSession {
  isListening(): boolean;
  registerEmailChangeCallback(
    callback: (email: string, isValid: boolean) => Promise<void>,
  ): void;
  currentAmount(): string | null;
  isDifferentEmail(email: string): boolean;
  isBuyerEmailKnown(): boolean;
  setBuyerEmail(email: string): void;
  reportEmailChangeMetric(): void;
  triggerReset(): void;
  setupEmailChangeHandlers(): void;
  markAsListening(): void;
}

export interface EnsureEmailListenerContext {
  session: EnsureEmailListenerSession;
}

export class EnsureEmailListenerRegistered {
  execute(ctx: EnsureEmailListenerContext): void {
    const { session } = ctx;

    if (session.isListening()) return;

    session.registerEmailChangeCallback(async (email, isValid) => {
      if (!isValid || !session.currentAmount()) return;

      if (session.isDifferentEmail(email) && session.isBuyerEmailKnown()) {
        session.setBuyerEmail(email);
        session.reportEmailChangeMetric();
        session.triggerReset();
      }
    });

    session.setupEmailChangeHandlers();
    session.markAsListening();
  }
}

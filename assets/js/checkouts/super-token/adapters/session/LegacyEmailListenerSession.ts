/**
 * Session adapter that lets the refactored `EnsureEmailListenerRegistered` use case drive the
 * still-legacy e-mail listener registration (trigger-handler.js:319-335). The use case owns
 * the once-guard, callback registration, and the reset decision; this adapter supplies the
 * *primitives*, forwarding each to the legacy trigger handler, its wcEmailListener and the
 * metrics instance — a transitional scaffold while the primitives are ported.
 *
 * The metric name string for the e-mail-change reset is kept here (the legacy boundary) so
 * the domain stays free of metric names.
 *
 * `triggerReset()` delegates to the legacy `resetCustomCheckout()` method on the trigger
 * handler, which in turn delegates to `window.mpSuperTokenResetCustomCheckout` (the
 * `ResetCustomCheckout` seam), completing the chain from e-mail change to the TS reset flow.
 */

import type { EnsureEmailListenerSession } from '@super-token/useCases/EnsureEmailListenerRegistered';

const EMAIL_CHANGE_METRIC = 'super_token_reset_on_email_change';

/** The subset of the legacy `MPSuperTokenTriggerHandler` the email-listener step uses. */
export interface LegacyEmailListenerTriggerHandler {
  isAlreadyListeningForm: boolean;
  currentAmount: string | null;
  wcBuyerEmail: string | null;
  isDifferentEmail(email: string): boolean;
  resetCustomCheckout(shouldClearCache?: boolean): void;
  wcEmailListener: {
    onEmailChange(callback: (email: string, isValid: boolean) => Promise<void>): void;
    setupEmailChangeHandlers(): void;
  };
  mpSuperTokenMetrics: {
    sendMetric(name: string, value: string, message: string): void;
  };
}

export class LegacyEmailListenerSession implements EnsureEmailListenerSession {
  constructor(private readonly triggerHandler: LegacyEmailListenerTriggerHandler) {}

  isListening(): boolean {
    return this.triggerHandler.isAlreadyListeningForm;
  }

  registerEmailChangeCallback(
    callback: (email: string, isValid: boolean) => Promise<void>,
  ): void {
    this.triggerHandler.wcEmailListener.onEmailChange(callback);
  }

  currentAmount(): string | null {
    return this.triggerHandler.currentAmount;
  }

  isDifferentEmail(email: string): boolean {
    return this.triggerHandler.isDifferentEmail(email);
  }

  isBuyerEmailKnown(): boolean {
    return this.triggerHandler.wcBuyerEmail != null;
  }

  setBuyerEmail(email: string): void {
    this.triggerHandler.wcBuyerEmail = email;
  }

  reportEmailChangeMetric(): void {
    this.triggerHandler.mpSuperTokenMetrics.sendMetric(EMAIL_CHANGE_METRIC, 'true', '');
  }

  triggerReset(): void {
    this.triggerHandler.resetCustomCheckout();
  }

  setupEmailChangeHandlers(): void {
    this.triggerHandler.wcEmailListener.setupEmailChangeHandlers();
  }

  markAsListening(): void {
    this.triggerHandler.isAlreadyListeningForm = true;
  }
}

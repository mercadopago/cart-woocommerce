/**
 * Resetting the Super Token flow after an error — the application sequence of
 * `resetSuperTokenOnError(preserveSelection)` (trigger-handler.js:196-222). Owns the
 * decision (whether to preserve the last selection and its installments) and the fixed
 * order of the reset operations; the DOM reads/writes and the deeper `resetCustomCheckout`
 * dance are injected session operations, named after what the legacy does.
 *
 * `preserveSelection` mirrors the recoverable-error case, where the buyer retries without
 * losing the previously chosen method (RN-2).
 */

import type { PaymentMethod } from '@super-token/types/external-globals';

/** Subset of `MPSuperTokenTriggerHandler`/`MPSuperTokenPaymentMethods` used on reset. */
export interface ResetSession {
  /** Legacy `document.querySelector('#mp_checkout_type')?.value === 'super_token'`. */
  isSuperTokenCheckoutActive(): boolean;
  scrollPaymentMethodListIntoView(): void;
  getLastPaymentMethodChoosen(): PaymentMethod | null;
  /** Legacy `document.getElementById('cardInstallments')?.value || null`. */
  getSelectedInstallments(): string | null;
  storeSavedInstallments(installments: string | null): void;
  deselectAllPaymentMethods(): void;
  hideAllPaymentMethodDetails(): void;
  unmountActiveSecurityCodeInstance(): void;
  clearActivePaymentMethod(): void;
  resetCustomCheckout(shouldClearCache: boolean): void;
  storeSelectedPreloadedPaymentMethod(paymentMethod: PaymentMethod | null): void;
}

export interface ResetFlowContext {
  session: ResetSession;
  preserveSelection: boolean;
}

export class ResetFlow {
  execute(ctx: ResetFlowContext): void {
    const { session, preserveSelection } = ctx;

    if (!session.isSuperTokenCheckoutActive()) {
      return;
    }

    session.scrollPaymentMethodListIntoView();
    session.storeSavedInstallments(null);

    let lastMethodToPreserve: PaymentMethod | null = null;
    if (preserveSelection) {
      lastMethodToPreserve = session.getLastPaymentMethodChoosen() || null;
      session.storeSavedInstallments(session.getSelectedInstallments());
    }

    session.deselectAllPaymentMethods();
    session.hideAllPaymentMethodDetails();
    session.unmountActiveSecurityCodeInstance();
    session.clearActivePaymentMethod();

    session.resetCustomCheckout(true);

    if (lastMethodToPreserve) {
      session.storeSelectedPreloadedPaymentMethod(lastMethodToPreserve);
    }
  }
}

/**
 * Session adapter that lets the refactored `ResetCustomCheckout` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.resetCustomCheckout` (super-token-trigger-handler.js:100-145). The
 * use case owns the reset *order*; this adapter supplies the *primitives*, forwarding each to the
 * legacy trigger handler instance and the collaborators it holds — its controller
 * (`mpSuperTokenPaymentMethods`) and authenticator — or reading the legacy runtime globals the
 * spinner and the missing-handler metric touch, a transitional scaffold while they are ported.
 *
 * The custom-handler-missing metric strings and the once-guard stay here (the legacy boundary):
 * the use case just calls `reportCustomHandlerMissingOnReset` unconditionally, and this adapter
 * keeps the `window.mpCustomCheckoutHandler` check, the flag on the trigger handler and the metric
 * name out of the domain.
 *
 * The async tail (delayed spinner removal, preloaded-method restore, deferred last-exception
 * handling) stays the legacy `finalizeResetTail`, forwarded whole — the same method the inline
 * fallback runs, so both paths share one source.
 */

import type { ResetCustomCheckoutSession } from '@super-token/useCases/ResetCustomCheckout';

/** Legacy `sendMetric` call from super-token-trigger-handler.js:105 (custom handler missing on reset). */
const CUSTOM_HANDLER_MISSING_METRIC = 'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS';
const CUSTOM_HANDLER_MISSING_CONTEXT = 'resetCustomCheckout';
const INIT_ERROR_MESSAGE = 'mp_super_token_init_error';

/** The subset of the legacy controller the reset head reads through the trigger handler. */
export interface LegacyResetCustomCheckoutController {
  hideSuperTokenError(): void;
  hasStoredPaymentMethods(): boolean;
  unmountCardForm(): void;
  mountCardForm(): void;
}

/** The subset of the legacy `MPSuperTokenTriggerHandler` the reset head drives. */
export interface LegacyResetCustomCheckoutTriggerHandler {
  currentAmount: string | null;
  customHandlerMissingReportedOnReset: boolean;
  mpSuperTokenPaymentMethods: LegacyResetCustomCheckoutController;
  mpSuperTokenAuthenticator: { setSuperTokenValidation(isValid: boolean): void };
  resetFlow(): void;
  loadSuperToken(currentAmount: string): Promise<void>;
  finalizeResetTail(): void;
}

export class LegacyResetCustomCheckoutSession implements ResetCustomCheckoutSession {
  constructor(private readonly triggerHandler: LegacyResetCustomCheckoutTriggerHandler) {}

  private get controller(): LegacyResetCustomCheckoutController {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }

  hideSuperTokenError(): void {
    this.controller.hideSuperTokenError();
  }

  reportCustomHandlerMissingOnReset(): void {
    if (window.mpCustomCheckoutHandler || this.triggerHandler.customHandlerMissingReportedOnReset) {
      return;
    }
    if (typeof window.sendMetric !== 'function') {
      return;
    }
    window.sendMetric(CUSTOM_HANDLER_MISSING_METRIC, CUSTOM_HANDLER_MISSING_CONTEXT, INIT_ERROR_MESSAGE);
    this.triggerHandler.customHandlerMissingReportedOnReset = true;
  }

  createLoadSpinner(): void {
    window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
  }

  setSuperTokenValidation(isValid: boolean): void {
    this.triggerHandler.mpSuperTokenAuthenticator.setSuperTokenValidation(isValid);
  }

  hasStoredPaymentMethods(): boolean {
    return this.controller.hasStoredPaymentMethods();
  }

  remountCardForm(): void {
    this.controller.unmountCardForm();
    this.controller.mountCardForm();
  }

  resetFlow(): void {
    this.triggerHandler.resetFlow();
  }

  currentAmount(): string | null {
    return this.triggerHandler.currentAmount;
  }

  loadSuperToken(currentAmount: string): Promise<void> {
    return this.triggerHandler.loadSuperToken(currentAmount);
  }

  finalizeResetTail(): void {
    this.triggerHandler.finalizeResetTail();
  }
}

/**
 * Session adapter that lets the refactored `ResetFlow` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.resetSuperTokenOnError(preserveSelection)`
 * (trigger-handler.js:196-222). The use case owns the reset *order* and the
 * preserve-selection decision; this adapter supplies the *primitives*, forwarding to the
 * trigger handler, to the controller it holds (`mpSuperTokenPaymentMethods`), or to the DOM
 * — a transitional scaffold while the primitives are ported into the tree.
 */

import type { ResetSession } from '@super-token/useCases/ResetFlow';
import type { PaymentMethod } from '@super-token/types/external-globals';

const CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
const SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
const INSTALLMENTS_INPUT_ID = 'cardInstallments';

/** Subset of the legacy controller the reset sequence reads through the trigger handler. */
export interface LegacyResetController {
  SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: string };
  getLastPaymentMethodChoosen(): PaymentMethod | null;
  deselectAllPaymentMethods(): void;
  hideAllPaymentMethodDetails(): void;
  unmountActiveSecurityCodeInstance(): void;
  clearActivePaymentMethod(): void;
  storeSelectedPreloadedPaymentMethod(paymentMethod: PaymentMethod | null): void;
}

/**
 * Subset of the legacy `MPSuperTokenTriggerHandler` the reset sequence uses. `savedInstallments`
 * is its own mutable field; `mpSuperTokenPaymentMethods` is the controller it holds.
 */
export interface LegacyResetTriggerHandler {
  savedInstallments: string | null;
  resetCustomCheckout(shouldClearCache: boolean): void;
  mpSuperTokenPaymentMethods: LegacyResetController;
}

export class LegacyResetSession implements ResetSession {
  constructor(private readonly triggerHandler: LegacyResetTriggerHandler) {}

  private get controller(): LegacyResetController {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }

  isSuperTokenCheckoutActive(): boolean {
    return (
      document.querySelector<HTMLInputElement>(CHECKOUT_TYPE_SELECTOR)?.value ===
      SUPER_TOKEN_CHECKOUT_TYPE
    );
  }

  scrollPaymentMethodListIntoView(): void {
    const list = document.querySelector(`.${this.controller.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
    list?.scrollIntoView({ behavior: 'smooth' });
  }

  getLastPaymentMethodChoosen(): PaymentMethod | null {
    return this.controller.getLastPaymentMethodChoosen();
  }

  getSelectedInstallments(): string | null {
    const input = document.getElementById(INSTALLMENTS_INPUT_ID) as HTMLInputElement | null;
    return input?.value || null;
  }

  storeSavedInstallments(installments: string | null): void {
    this.triggerHandler.savedInstallments = installments;
  }

  deselectAllPaymentMethods(): void {
    this.controller.deselectAllPaymentMethods();
  }

  hideAllPaymentMethodDetails(): void {
    this.controller.hideAllPaymentMethodDetails();
  }

  unmountActiveSecurityCodeInstance(): void {
    this.controller.unmountActiveSecurityCodeInstance();
  }

  clearActivePaymentMethod(): void {
    this.controller.clearActivePaymentMethod();
  }

  resetCustomCheckout(shouldClearCache: boolean): void {
    this.triggerHandler.resetCustomCheckout(shouldClearCache);
  }

  storeSelectedPreloadedPaymentMethod(paymentMethod: PaymentMethod | null): void {
    this.controller.storeSelectedPreloadedPaymentMethod(paymentMethod);
  }
}

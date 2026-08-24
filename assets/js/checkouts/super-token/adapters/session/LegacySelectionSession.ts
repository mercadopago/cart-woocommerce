/**
 * Session adapter that lets the refactored `SelectSavedPaymentMethod` use case drive the
 * still-legacy `MPSuperTokenPaymentMethods` controller (v2/v2.1
 * `onSelectSuperTokenPaymentMethod`, payment-methods.js:709-737). The use case owns the
 * selection *order*; this adapter supplies the *primitives*, forwarding each one to the
 * legacy instance — a transitional scaffold while the primitives are ported into the tree.
 *
 * Two use-case names have no 1:1 legacy method and are bridged here:
 *  - `setCheckoutTypeToSuperToken()` → `setCheckoutType(SUPER_TOKEN_CHECKOUT_TYPE)`;
 *  - `notifySelectionSettled()`      → the legacy trailing
 *    `setTimeout(dispatch(selectedSupertokenMethodEvent(false)), 50)`.
 * Everything else forwards straight through.
 */

import type { SelectionSession } from '@super-token/useCases/SelectSavedPaymentMethod';
import type { PaymentMethod } from '@super-token/types/external-globals';

/** Legacy delay before the settled event, mirroring payment-methods.js:732. */
const SELECTION_SETTLED_DELAY_MS = 50;

/**
 * The subset of the legacy `MPSuperTokenPaymentMethods` controller the selection sequence
 * calls. Grounded in `onSelectSuperTokenPaymentMethod` (payment-methods.js:709-737); the
 * legacy globals are opaque handles, so the primitives are named here.
 */
export interface LegacyPaymentMethodsController {
  SUPER_TOKEN_CHECKOUT_TYPE: string;
  paymentMethodAlreadySelected(paymentMethod: PaymentMethod): boolean;
  emitEventFromSelectPaymentMethod(paymentMethod: PaymentMethod): void;
  storeActivePaymentMethod(paymentMethod: PaymentMethod): void;
  hideAllPaymentMethodDetails(): void;
  closeAccordion(): void;
  deselectAllPaymentMethods(): void;
  selectPaymentMethod(paymentMethodElement: HTMLElement): void;
  fillCardTokenFields(paymentMethod: PaymentMethod): void;
  setCheckoutType(checkoutType: string): void;
  showPaymentMethodDetails(paymentMethodElement: HTMLElement): void;
  handleInstallmentsWithoutFeePillVisibility(): void;
  handleWithEscPaymentMethod(
    paymentMethod: PaymentMethod,
    paymentMethodElement: HTMLElement,
  ): Promise<PaymentMethod | null>;
  mountSecurityCodeField(paymentMethod: PaymentMethod): void;
  selectedSupertokenMethodEvent(isNewCardSelected: boolean): CustomEvent;
}

export class LegacySelectionSession implements SelectionSession {
  constructor(private readonly legacy: LegacyPaymentMethodsController) {}

  paymentMethodAlreadySelected(paymentMethod: PaymentMethod): boolean {
    return this.legacy.paymentMethodAlreadySelected(paymentMethod);
  }

  emitEventFromSelectPaymentMethod(paymentMethod: PaymentMethod): void {
    this.legacy.emitEventFromSelectPaymentMethod(paymentMethod);
  }

  storeActivePaymentMethod(paymentMethod: PaymentMethod): void {
    this.legacy.storeActivePaymentMethod(paymentMethod);
  }

  hideAllPaymentMethodDetails(): void {
    this.legacy.hideAllPaymentMethodDetails();
  }

  closeAccordion(): void {
    this.legacy.closeAccordion();
  }

  deselectAllPaymentMethods(): void {
    this.legacy.deselectAllPaymentMethods();
  }

  selectPaymentMethod(paymentMethodElement: HTMLElement): void {
    this.legacy.selectPaymentMethod(paymentMethodElement);
  }

  fillCardTokenFields(paymentMethod: PaymentMethod): void {
    this.legacy.fillCardTokenFields(paymentMethod);
  }

  setCheckoutTypeToSuperToken(): void {
    this.legacy.setCheckoutType(this.legacy.SUPER_TOKEN_CHECKOUT_TYPE);
  }

  showPaymentMethodDetails(paymentMethodElement: HTMLElement): void {
    this.legacy.showPaymentMethodDetails(paymentMethodElement);
  }

  handleInstallmentsWithoutFeePillVisibility(): void {
    this.legacy.handleInstallmentsWithoutFeePillVisibility();
  }

  handleWithEscPaymentMethod(
    paymentMethod: PaymentMethod,
    paymentMethodElement: HTMLElement,
  ): Promise<PaymentMethod | null> {
    return this.legacy.handleWithEscPaymentMethod(paymentMethod, paymentMethodElement);
  }

  mountSecurityCodeField(paymentMethod: PaymentMethod): void {
    this.legacy.mountSecurityCodeField(paymentMethod);
  }

  notifySelectionSettled(): void {
    setTimeout(() => {
      document.dispatchEvent(this.legacy.selectedSupertokenMethodEvent(false));
    }, SELECTION_SETTLED_DELAY_MS);
  }
}

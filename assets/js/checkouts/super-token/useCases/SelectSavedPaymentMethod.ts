/**
 * Selecting a saved Super Token payment method — the application sequence of
 * `onSelectSuperTokenPaymentMethod` (payment-methods.js:709-737). Preserves the legacy
 * order and its two decisions (skip when the method is already selected; mount the
 * security-code field only when the ESC verification returns a method). The DOM and
 * timing primitives are injected session operations, named after what the legacy does.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';

/** Subset of `MPSuperTokenPaymentMethods` used to select a saved method. */
export interface SelectionSession {
  paymentMethodAlreadySelected(paymentMethod: PaymentMethod): boolean;
  emitEventFromSelectPaymentMethod(paymentMethod: PaymentMethod): void;
  storeActivePaymentMethod(paymentMethod: PaymentMethod): void;
  hideAllPaymentMethodDetails(): void;
  closeAccordion(): void;
  deselectAllPaymentMethods(): void;
  selectPaymentMethod(paymentMethodElement: HTMLElement): void;
  fillCardTokenFields(paymentMethod: PaymentMethod): void;
  setCheckoutTypeToSuperToken(): void;
  showPaymentMethodDetails(paymentMethodElement: HTMLElement): void;
  handleInstallmentsWithoutFeePillVisibility(): void;
  handleWithEscPaymentMethod(
    paymentMethod: PaymentMethod,
    paymentMethodElement: HTMLElement,
  ): Promise<PaymentMethod | null>;
  mountSecurityCodeField(paymentMethod: PaymentMethod): void;
  /** Legacy trailing `setTimeout(dispatch(selectedSupertokenMethodEvent(false)), 50)`. */
  notifySelectionSettled(): void;
}

/** Subset of `MPSuperTokenMetrics` emitted during selection. */
export interface SelectionMetrics {
  sendMetric(name: string, value: string, message: string): void;
  registerSelectPaymentMethod(paymentMethodType: string): void;
}

export interface SelectSavedPaymentMethodContext {
  session: SelectionSession;
  metrics: SelectionMetrics;
  paymentMethod: PaymentMethod;
  paymentMethodElement: HTMLElement;
}

export class SelectSavedPaymentMethod {
  async execute(ctx: SelectSavedPaymentMethodContext): Promise<void> {
    const { session, metrics, paymentMethod, paymentMethodElement } = ctx;

    if (session.paymentMethodAlreadySelected(paymentMethod)) {
      return;
    }

    metrics.sendMetric('super_token_withdraw', 'false', '');
    session.emitEventFromSelectPaymentMethod(paymentMethod);
    metrics.registerSelectPaymentMethod(paymentMethod.type);
    session.storeActivePaymentMethod(paymentMethod);
    session.hideAllPaymentMethodDetails();
    session.closeAccordion();
    session.deselectAllPaymentMethods();
    session.selectPaymentMethod(paymentMethodElement);
    session.fillCardTokenFields(paymentMethod);
    session.setCheckoutTypeToSuperToken();
    session.showPaymentMethodDetails(paymentMethodElement);
    session.handleInstallmentsWithoutFeePillVisibility();

    const verifiedPaymentMethod = await session.handleWithEscPaymentMethod(
      paymentMethod,
      paymentMethodElement,
    );
    if (verifiedPaymentMethod !== null) {
      session.mountSecurityCodeField(verifiedPaymentMethod);
    }

    session.notifySelectionSettled();
  }
}

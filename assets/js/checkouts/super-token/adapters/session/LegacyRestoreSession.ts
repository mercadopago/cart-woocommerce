/**
 * Session adapter that lets the refactored `RestorePreloadedPaymentMethod` use case drive the
 * still-legacy restore sequence (trigger-handler.js:166-213). The use case owns the selection
 * sequence and the four early-exit branches; this adapter supplies the *primitives*, forwarding
 * each to the legacy payment-methods controller, the trigger handler's `savedInstallments` field,
 * the legacy metrics, or the DOM — a transitional scaffold while the primitives are ported.
 *
 * The restore metric name, value, and message are all fixed (`name, 'true',
 * 'mp_super_token_restore_error'`); the adapter encodes the value and message so the use case
 * only passes the varying name.
 *
 * The installments dropdown is looked up via `paymentMethodIdentifier` (legacy controller) and
 * the element's own `querySelector`; option membership and value application are also DOM
 * operations kept here at the legacy boundary.
 */

import type { RestorePreloadedSession } from '@super-token/useCases/RestorePreloadedPaymentMethod';
import type { PaymentMethod } from '@super-token/types/external-globals';

const RESTORE_METRIC_VALUE = 'true';
const RESTORE_METRIC_MESSAGE = 'mp_super_token_restore_error';
const INSTALLMENTS_DROPDOWN_PREFIX = 'mp-super-token-installments-select-';
const CARD_INSTALLMENTS_INPUT_ID = 'cardInstallments';

/** Subset of the legacy `MPSuperTokenPaymentMethods` the restore sequence reads. */
export interface LegacyRestoreController {
  getSelectedPreloadedPaymentMethod(): PaymentMethod | null;
  hasCheckoutError(): boolean;
  selectLastPaymentMethodChoosen(): void;
  selectPreloadedPaymentMethod(): Promise<void>;
  storeSelectedPreloadedPaymentMethod(method: PaymentMethod | null): void;
  getActivePaymentMethod(): PaymentMethod | null;
  getPaymentMethodElementFromDOM(method: PaymentMethod): HTMLElement | null;
  showPaymentMethodDetails(element: HTMLElement): void;
  paymentMethodIdentifier(method: PaymentMethod): string;
}

/** Subset of the legacy `MPSuperTokenMetrics` the restore sequence reports through. */
export interface LegacyRestoreMetrics {
  sendMetric(name: string, value: string, message: string): void;
}

/** Subset of the legacy trigger handler the restore sequence reads for `savedInstallments`. */
export interface LegacyRestoreTriggerHandler {
  savedInstallments: string | null;
  mpSuperTokenPaymentMethods: LegacyRestoreController;
}

export class LegacyRestoreSession implements RestorePreloadedSession {
  constructor(
    private readonly triggerHandler: LegacyRestoreTriggerHandler,
    private readonly metrics: LegacyRestoreMetrics,
  ) {}

  private get controller(): LegacyRestoreController {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }

  getPreloadedPaymentMethod(): PaymentMethod | null {
    return this.controller.getSelectedPreloadedPaymentMethod();
  }

  hasCheckoutError(): boolean {
    return this.controller.hasCheckoutError();
  }

  selectLastChosenMethod(): void {
    this.controller.selectLastPaymentMethodChoosen();
  }

  selectPreloadedMethod(): Promise<void> {
    return this.controller.selectPreloadedPaymentMethod();
  }

  clearPreloadedMethod(): void {
    this.controller.storeSelectedPreloadedPaymentMethod(null);
  }

  getActiveMethod(): PaymentMethod | null {
    return this.controller.getActivePaymentMethod();
  }

  getSavedInstallments(): string | null {
    return this.triggerHandler.savedInstallments;
  }

  clearSavedInstallments(): void {
    this.triggerHandler.savedInstallments = null;
  }

  reportRestoreMetric(name: string): void {
    this.metrics.sendMetric(name, RESTORE_METRIC_VALUE, RESTORE_METRIC_MESSAGE);
  }

  getMethodElement(method: PaymentMethod): HTMLElement | null {
    return this.controller.getPaymentMethodElementFromDOM(method);
  }

  showMethodDetails(element: HTMLElement): void {
    this.controller.showPaymentMethodDetails(element);
  }

  getInstallmentsDropdown(
    method: PaymentMethod,
    element: HTMLElement,
  ): HTMLSelectElement | null {
    const id = `${INSTALLMENTS_DROPDOWN_PREFIX}${this.controller.paymentMethodIdentifier(method)}`;
    return element.querySelector<HTMLSelectElement>(`#${id}`);
  }

  hasInstallmentOption(dropdown: HTMLSelectElement, value: string): boolean {
    return [...dropdown.options].some((o) => o.value === value);
  }

  applyInstallmentsSelection(dropdown: HTMLSelectElement, value: string): void {
    dropdown.value = value;
    const cardInstallments = document.getElementById(
      CARD_INSTALLMENTS_INPUT_ID,
    ) as HTMLInputElement | null;
    if (cardInstallments) cardInstallments.value = value;
    dropdown.dispatchEvent(new Event('change'));
  }
}

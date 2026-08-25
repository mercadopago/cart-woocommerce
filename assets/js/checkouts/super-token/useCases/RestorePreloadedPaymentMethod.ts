/**
 * Restoring the preloaded payment method after a reset — the logic of
 * `MPSuperTokenTriggerHandler.restorePreloadedPaymentMethod`
 * (super-token-trigger-handler.js:166-213), called asynchronously by `finalizeResetTail` after
 * the spinner delay. Owns the selection sequence and the four early-exit branches that each emit
 * a distinct restore metric; the DOM reads/writes and the legacy controller calls are injected
 * session operations.
 *
 * Error path (no preloaded method with a checkout error): re-selects the last chosen method so
 * the buyer doesn't land on an empty state.
 * Happy path: selects the preloaded method, shows its details, and restores the installments
 * dropdown to the value that was saved before the reset.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';

const RESTORE_ACTIVE_METHOD_NOT_SET_METRIC = 'super_token_restore_active_method_not_set';
const RESTORE_ELEMENT_NOT_FOUND_METRIC = 'super_token_restore_element_not_found';
const RESTORE_DROPDOWN_NOT_FOUND_METRIC = 'super_token_restore_installments_dropdown_not_found';
const RESTORE_OPTION_NOT_FOUND_METRIC = 'super_token_restore_installment_option_not_found';

/** The four restore-failure metric names, as a closed contract the metrics adapter emits. */
export type RestoreErrorReason =
  | typeof RESTORE_ACTIVE_METHOD_NOT_SET_METRIC
  | typeof RESTORE_ELEMENT_NOT_FOUND_METRIC
  | typeof RESTORE_DROPDOWN_NOT_FOUND_METRIC
  | typeof RESTORE_OPTION_NOT_FOUND_METRIC;

export interface RestorePreloadedSession {
  getPreloadedPaymentMethod(): PaymentMethod | null;
  hasCheckoutError(): boolean;
  selectLastChosenMethod(): void;
  selectPreloadedMethod(): Promise<void>;
  clearPreloadedMethod(): void;
  getActiveMethod(): PaymentMethod | null;
  getSavedInstallments(): string | null;
  clearSavedInstallments(): void;
  reportRestoreMetric(reason: RestoreErrorReason): void;
  getMethodElement(method: PaymentMethod): HTMLElement | null;
  showMethodDetails(element: HTMLElement): void;
  getInstallmentsDropdown(
    method: PaymentMethod,
    element: HTMLElement,
  ): HTMLSelectElement | null;
  hasInstallmentOption(dropdown: HTMLSelectElement, value: string): boolean;
  applyInstallmentsSelection(dropdown: HTMLSelectElement, value: string): void;
}

export interface RestorePreloadedContext {
  session: RestorePreloadedSession;
}

export class RestorePreloadedPaymentMethod {
  async execute(ctx: RestorePreloadedContext): Promise<void> {
    const { session } = ctx;

    if (!session.getPreloadedPaymentMethod()) {
      if (session.hasCheckoutError()) {
        session.selectLastChosenMethod();
      }
      return;
    }

    await session.selectPreloadedMethod();
    session.clearPreloadedMethod();

    const activeMethod = session.getActiveMethod();
    const savedInstallments = session.getSavedInstallments();
    session.clearSavedInstallments();

    if (!activeMethod) {
      session.reportRestoreMetric(RESTORE_ACTIVE_METHOD_NOT_SET_METRIC);
      return;
    }

    const element = session.getMethodElement(activeMethod);
    if (!element) {
      session.reportRestoreMetric(RESTORE_ELEMENT_NOT_FOUND_METRIC);
      return;
    }

    session.showMethodDetails(element);

    if (!savedInstallments) return;

    const dropdown = session.getInstallmentsDropdown(activeMethod, element);
    if (!dropdown) {
      session.reportRestoreMetric(RESTORE_DROPDOWN_NOT_FOUND_METRIC);
      return;
    }

    if (!session.hasInstallmentOption(dropdown, savedInstallments)) {
      session.reportRestoreMetric(RESTORE_OPTION_NOT_FOUND_METRIC);
      return;
    }

    session.applyInstallmentsSelection(dropdown, savedInstallments);
  }
}

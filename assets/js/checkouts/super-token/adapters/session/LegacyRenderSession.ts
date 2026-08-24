/**
 * Session adapter that lets the refactored variant view drive the saved-methods render while
 * the interactive row (details + installments select + security-code container + behaviour
 * wiring) still lives in the legacy `MPSuperTokenPaymentMethods` controller
 * (`createPaymentMethodElement`, v2.1 payment-methods.js:1966). The view owns the render
 * *order* (grouping, blocks, header, e-mail listener); this adapter supplies the row as the
 * single legacy *primitive* — a transitional scaffold that disappears once the row itself is
 * ported into the tree (then the view builds its own presentation row).
 */

import type { CreditsContractParameters, RenderRowSession } from '@super-token/ports';
import type {
  Installment,
  PaymentMethod,
  RawMpSdkInstance,
  RawSdkCreditsContractController,
} from '@super-token/types/external-globals';

const DISPATCHER_MISSING_METRIC = 'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING';
const DISPATCHER_MISSING_MESSAGE = 'mp_super_token_init_error';
const INSTALLMENTS_FILLED_EVENT = 'super_token_installments_filled';
const CONSUMER_CREDITS_METHOD_TYPE = 'consumer_credits';

/** The legacy metrics instance methods the render session forwards to. */
interface LegacyRenderMetrics {
  installmentsFilled(methodType: string): void;
  renderCreditsContract(success: boolean, error?: unknown): void;
  registerOpenCreditsInfoModal(linkText: string): void;
  renderConsumerCreditsHint(success: boolean, error?: unknown): void;
  renderConsumerCreditsDueDate(success: boolean, error?: unknown): void;
  errorToUpdateCreditsContract(error: unknown): void;
}

// Module-level, mirroring the legacy per-controller `installmentsDispatcherMissingReported` flag so
// the missing-dispatcher metric is emitted once even across re-renders (new session per render).
let dispatcherMissingReported = false;

/**
 * The subset of the legacy `MPSuperTokenPaymentMethods` controller the render sequence calls.
 * Grounded in `createPaymentMethodElement` (payment-methods.js:1966-2194) and its click/keydown
 * wiring (`onSelectSuperTokenPaymentMethod`, 709); the legacy global is an opaque handle, so the
 * primitives are named here.
 */
export interface LegacyRenderController {
  createPaymentMethodElement(paymentMethod: PaymentMethod): HTMLElement;
  onSelectSuperTokenPaymentMethod(row: HTMLElement, paymentMethod: PaymentMethod): Promise<void>;
  mpSuperTokenMetrics: LegacyRenderMetrics;
  mpSdkInstance: RawMpSdkInstance;
  getSuperToken(): string;
}

export class LegacyRenderSession implements RenderRowSession {
  constructor(private readonly legacy: LegacyRenderController) {}

  /** Builds a row type not yet ported into the tree, via the legacy controller. */
  buildRow(paymentMethod: PaymentMethod): HTMLElement {
    return this.legacy.createPaymentMethodElement(paymentMethod);
  }

  /** Selection primitive for the rows the tree builds itself; the legacy method delegates to the
   *  refactored selection seam, so this reuses that path. Fire-and-forget, matching the legacy
   *  click handler (payment-methods.js:2019). */
  onSelectPaymentMethod(row: HTMLElement, paymentMethod: PaymentMethod): void {
    void this.legacy.onSelectSuperTokenPaymentMethod(row, paymentMethod);
  }

  updateInstallmentsTaxInfo(
    selectedValue: string,
    taxInfoElementId: string,
    installments: Installment[],
  ): void {
    window.CheckoutPage?.updateTaxInfoForSelect(selectedValue, taxInfoElementId, installments);
  }

  installmentSelected(methodType: string): void {
    window.MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(null, 'focusout', INSTALLMENTS_FILLED_EVENT, {
      onlyDispatch: true,
    });
    this.legacy.mpSuperTokenMetrics.installmentsFilled(methodType);
  }

  reportInstallmentDispatcherMissing(context: string): void {
    if (window.MPCheckoutFieldsDispatcher || typeof window.sendMetric !== 'function' || dispatcherMissingReported) {
      return;
    }
    window.sendMetric(DISPATCHER_MISSING_METRIC, context, DISPATCHER_MISSING_MESSAGE);
    dispatcherMissingReported = true;
  }

  getFastPaymentToken(): string {
    return this.legacy.getSuperToken();
  }

  renderCreditsContract(
    elementId: string,
    parameters: CreditsContractParameters,
  ): Promise<RawSdkCreditsContractController> {
    return this.legacy.mpSdkInstance.renderCreditsContract(elementId, parameters);
  }

  updateCreditsContract(controller: RawSdkCreditsContractController, installments: string): void {
    try {
      controller.update({ installments });
      this.legacy.mpSuperTokenMetrics.installmentsFilled(CONSUMER_CREDITS_METHOD_TYPE);
    } catch (error) {
      this.legacy.mpSuperTokenMetrics.errorToUpdateCreditsContract(error);
    }
  }

  dispatchInstallmentsFilledField(): void {
    window.MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(null, 'focusout', INSTALLMENTS_FILLED_EVENT, {
      onlyDispatch: true,
    });
  }

  recordCreditsContractRendered(success: boolean, error?: unknown): void {
    this.legacy.mpSuperTokenMetrics.renderCreditsContract(success, error);
  }

  recordOpenCreditsInfoModal(linkText: string): void {
    this.legacy.mpSuperTokenMetrics.registerOpenCreditsInfoModal(linkText);
  }

  recordConsumerCreditsHint(success: boolean, error?: unknown): void {
    this.legacy.mpSuperTokenMetrics.renderConsumerCreditsHint(success, error);
  }

  recordConsumerCreditsDueDate(success: boolean, error?: unknown): void {
    this.legacy.mpSuperTokenMetrics.renderConsumerCreditsDueDate(success, error);
  }
}

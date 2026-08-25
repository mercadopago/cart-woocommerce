/**
 * Blocks checkout adapter — translates the neutral `FinalizeResult` (RN-3) into the
 * WooCommerce Blocks flow of `custom.block.js` (`case 'super_token'`, 71-140). Owns the
 * Blocks-only steps that surround the shared spine: the click metric (81) and the
 * validation pre-branch (86-94, backed by the WC Blocks validation store). The finalize
 * result is mapped to an `emitResponse` type.
 *
 * Notes on fidelity to the legacy handler:
 * - Success returns `{ type: SUCCESS }` only. The legacy common path also carries
 *   `meta.paymentMethodData` (custom.block.js:218-223); that payload is assembled by the
 *   `onPaymentSetup` wiring and merged there when this adapter is wired in (TASK-013).
 * - The validation pre-branch returns SUCCESS (not ERROR), mirroring the legacy `break`
 *   that fell through to the common SUCCESS return: the field errors were already shown by
 *   `forceShowValidationErrors`, and WC's own `hasValidationErrors` gate blocks placement,
 *   so no extra payment-error notice is raised.
 * - The legacy `mp_custom_checkout_handler_missing` metric stays observable: the composition
 *   root injects `removeLoader`, and when the loader handler is absent that injected callback
 *   re-emits the metric (custom.block.js) instead of failing silently, so the delegated path
 *   keeps the same diagnostic signal the inline path had.
 */

import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import type {
  FinalizeResult,
  FinalizeSuperTokenPayment,
  FinalizationAuthenticator,
  FinalizationPaymentMethods,
} from '@super-token/useCases/FinalizeSuperTokenPayment';
import type { CheckoutErrorHandler } from './ClassicCheckout';

export interface BlocksPaymentMethods extends FinalizationPaymentMethods {
  forceShowValidationErrors(): void;
  selectLastPaymentMethodChoosen(): void;
}

export interface BlocksTriggerHandler {
  resetSuperTokenOnError(preserveSelection: boolean): void;
  setLastException(exception: unknown): void;
}

export interface BlocksMetrics {
  registerClickOnPlaceOrderButton(): void;
}

/** The subset of WC Blocks `emitResponse` the finalization maps onto. */
export interface EmitResponse {
  responseTypes: { SUCCESS: string; ERROR: string };
}

export interface BlocksResponse {
  type: string;
}

export interface BlocksCheckoutDeps {
  finalize: FinalizeSuperTokenPayment;
  paymentMethods: BlocksPaymentMethods;
  authenticator: FinalizationAuthenticator;
  metrics: BlocksMetrics;
  triggerHandler: BlocksTriggerHandler;
  errorHandler: CheckoutErrorHandler;
  emitResponse: EmitResponse;
  /** `select(VALIDATION_STORE_KEY).hasValidationErrors()`. */
  hasValidationErrors(): boolean;
  /** `mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner()`. */
  removeLoader(): void;
}

export class BlocksCheckout {
  constructor(private readonly deps: BlocksCheckoutDeps) {}

  async finalize(): Promise<BlocksResponse> {
    try {
      this.deps.metrics.registerClickOnPlaceOrderButton();

      const activeMethod = this.deps.paymentMethods.getActivePaymentMethod();
      const isSelectionValid =
        !!activeMethod && this.deps.paymentMethods.isSelectedPaymentMethodValid();

      if (activeMethod && !isSelectionValid) {
        this.deps.paymentMethods.forceShowValidationErrors();
      }

      if (this.deps.hasValidationErrors()) {
        this.deps.paymentMethods.selectLastPaymentMethodChoosen();
        return this.success();
      }

      const result = await this.deps.finalize.execute({
        paymentMethods: this.deps.paymentMethods,
        authenticator: this.deps.authenticator,
        isOrderPayPage: false,
      });

      return this.applyResult(result);
    } catch (error) {
      // Error Cascade guard: the WC Blocks consumer (custom.block.js) awaits this finalizer
      // without a try/catch, so an unexpected throw in a pre-finalize step — e.g. the WC
      // validation store being unavailable in a Fluid Checkout hybrid — would reject unhandled and
      // leave the card-form spinner stuck. Clear the loader and surface a Blocks error instead.
      this.deps.removeLoader();
      return this.error();
    }
  }

  private applyResult(result: FinalizeResult): BlocksResponse {
    if (result.status === 'success') {
      return this.success();
    }

    this.deps.removeLoader();

    if (result.status === 'validation_error') {
      if (result.errorCode === MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
        this.deps.errorHandler.handleError(result.error ?? new Error(result.errorCode));
      }
      return this.error();
    }

    this.deps.triggerHandler.resetSuperTokenOnError(result.status === 'recoverable_error');
    this.deps.triggerHandler.setLastException(result.error ?? new Error(result.errorCode));
    return this.error();
  }

  private success(): BlocksResponse {
    return { type: this.deps.emitResponse.responseTypes.SUCCESS };
  }

  private error(): BlocksResponse {
    return { type: this.deps.emitResponse.responseTypes.ERROR };
  }
}

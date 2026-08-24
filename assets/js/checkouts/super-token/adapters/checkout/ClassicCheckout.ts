/**
 * Classic checkout adapter — translates the neutral `FinalizeResult` (RN-3) into the
 * WooCommerce Classic flow of `event-handler.js:handleWithSuperTokenSubmit` (390-441).
 * All checkout-specific effects (mercado_pago_submit flag, submit, loader, error handler,
 * trigger-handler reset) are injected — this holds only the translation.
 *
 * Submit path preserves the legacy branch (event-handler.js:420-424): the standard
 * checkout submits `form.checkout` (`$checkout_form.trigger('submit')`); the order-pay
 * page has no `form.checkout` and submits `#order_review` via
 * `handle3dsPayOrderFormSubmission()` — the order-pay-page submit mechanism, active for
 * Super Token (the 3DS challenge is only an internal branch of it and is not modified).
 */

import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import type {
  FinalizeResult,
  FinalizeSuperTokenPayment,
  FinalizationAuthenticator,
  FinalizationPaymentMethods,
} from '@super-token/useCases/FinalizeSuperTokenPayment';

export interface ClassicTriggerHandler {
  resetSuperTokenOnError(preserveSelection: boolean): void;
  setLastException(exception: unknown): void;
}

export interface CheckoutErrorHandler {
  handleError(exception: unknown): void;
}

export interface ClassicCheckoutDeps {
  finalize: FinalizeSuperTokenPayment;
  paymentMethods: FinalizationPaymentMethods;
  authenticator: FinalizationAuthenticator;
  triggerHandler: ClassicTriggerHandler;
  errorHandler: CheckoutErrorHandler;
  isOrderPayPage(): boolean;
  /** Sets `mercado_pago_submit = true` so the following WooCommerce submit is allowed. */
  markPaymentReady(): void;
  /** Standard checkout submit — `$checkout_form.trigger('submit')`. */
  submitCheckoutForm(): void;
  /** Order-pay-page submit — `handle3dsPayOrderFormSubmission()` (serializes #order_review). */
  submitOrderPayForm(): void;
  /** `cardForm.removeLoadSpinner()` + `hideCheckoutClassicLoader()`. */
  removeLoader(): void;
}

export class ClassicCheckout {
  constructor(private readonly deps: ClassicCheckoutDeps) {}

  async finalize(): Promise<void> {
    const isOrderPayPage = this.deps.isOrderPayPage();

    const result = await this.deps.finalize.execute({
      paymentMethods: this.deps.paymentMethods,
      authenticator: this.deps.authenticator,
      isOrderPayPage,
    });

    this.applyResult(result, isOrderPayPage);
  }

  private applyResult(result: FinalizeResult, isOrderPayPage: boolean): void {
    switch (result.status) {
      case 'success':
        this.deps.markPaymentReady();
        if (isOrderPayPage) {
          this.deps.submitOrderPayForm();
        } else {
          this.deps.submitCheckoutForm();
        }
        return;

      case 'validation_error':
        if (result.errorCode === MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
          this.deps.errorHandler.handleError(result.error ?? new Error(result.errorCode));
        }
        this.deps.removeLoader();
        return;

      case 'recoverable_error':
        this.deps.triggerHandler.resetSuperTokenOnError(true);
        this.deps.triggerHandler.setLastException(result.error ?? new Error(result.errorCode));
        return;

      case 'fatal_error':
        this.deps.triggerHandler.resetSuperTokenOnError(false);
        this.deps.triggerHandler.setLastException(result.error ?? new Error(result.errorCode));
        return;
    }
  }
}

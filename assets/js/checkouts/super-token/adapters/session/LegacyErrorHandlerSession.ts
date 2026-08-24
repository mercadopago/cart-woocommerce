/**
 * Session adapter that lets the refactored `HandleError` use case drive the still-legacy
 * `MPSuperTokenErrorHandler.handleError` (super-token-error-handler.js:57-62). The use case
 * owns the parse → metric → display sequence and the validation-error branch; this adapter
 * supplies the *primitives*, forwarding each to the legacy metrics instance and the payment-
 * methods controller — a transitional scaffold while they are ported into the tree.
 */

import type { HandleErrorSession } from '@super-token/useCases/HandleError';

/** Subset of the legacy `MPSuperTokenMetrics` the error-handler reports through. */
export interface LegacyErrorMetrics {
  errorOnSubmit(errorCode: string, error: string): void;
}

/** Subset of the legacy `MPSuperTokenPaymentMethods` the error-handler displays through. */
export interface LegacyErrorPaymentMethods {
  forceShowValidationErrors(): void;
  convertErrorCodeToErrorMessage(errorCode: string): string;
  showSuperTokenError(errorMessage: string): void;
}

export class LegacyErrorHandlerSession implements HandleErrorSession {
  constructor(
    private readonly metrics: LegacyErrorMetrics,
    private readonly controller: LegacyErrorPaymentMethods,
  ) {}

  reportErrorMetric(code: string, message: string): void {
    this.metrics.errorOnSubmit(code, message);
  }

  forceShowValidationErrors(): void {
    this.controller.forceShowValidationErrors();
  }

  getErrorMessage(code: string): string {
    return this.controller.convertErrorCodeToErrorMessage(code);
  }

  showError(message: string): void {
    this.controller.showSuperTokenError(message);
  }
}

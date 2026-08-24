/**
 * Handling a Super Token runtime error — the logic of `MPSuperTokenErrorHandler.handleError`
 * (super-token-error-handler.js:57-62). Owns the parse → metric → display sequence and the
 * branch that decides between a validation-error display (force show validation) and a generic
 * error display (convert code to message, then show). Returns the normalised error code so the
 * caller can store or classify it.
 *
 * Error parsing is inline: the legacy normalises exceptions to strings (`${exception}` for
 * objects, passthrough for strings) and falls back to `UNKNOWN_ERROR` when the result is empty.
 * No session method is needed for this step — it is pure coercion with no side effects.
 */

import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';

export interface HandleErrorSession {
  reportErrorMetric(code: string, message: string): void;
  forceShowValidationErrors(): void;
  getErrorMessage(code: string): string;
  showError(message: string): void;
}

export interface HandleErrorContext {
  session: HandleErrorSession;
  exception: unknown;
}

export class HandleError {
  execute(ctx: HandleErrorContext): string {
    const { session, exception } = ctx;

    const normalized = typeof exception !== 'string' ? `${exception}` : exception;
    const code = normalized || MPSuperTokenErrorCodes.UNKNOWN_ERROR;
    const message = normalized || 'Unknown error';

    session.reportErrorMetric(code, message);

    if (code.includes(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID)) {
      session.forceShowValidationErrors();
    } else {
      session.showError(session.getErrorMessage(code));
    }

    return code;
  }
}

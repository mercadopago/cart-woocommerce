/**
 * Canonical Super Token finalization (RN-1) — the single source shared by the Classic
 * (`event-handler.js:397-419`) and Blocks (`custom.block.js:77-117`) checkouts, which
 * today run the same sequence copied in two places (root cause of PSW-3737/PSW-4113).
 *
 * The use case drives the injected session/authenticator through the exact legacy order
 * and returns a neutral typed result (RN-3); the checkout adapters translate it. It holds
 * NO checkout logic (loader, jQuery submit, emitResponse) — those, plus the flow-specific
 * steps that genuinely differ between the two checkouts (the click metric, the Blocks
 * validation pre-branch), live in the adapters. Error classification is the single source
 * `isRecoverable` (RN-2).
 */

import {
  MPSuperTokenErrorCodes,
  isRecoverable,
} from '@super-token/core/checkoutSession/ErrorClassification';
import type { PaymentMethod } from '@super-token/types/external-globals';

export type FinalizeStatus = 'success' | 'validation_error' | 'recoverable_error' | 'fatal_error';

export interface FinalizeResult {
  status: FinalizeStatus;
  /** Present for classified errors; absent for the installment-incomplete abort. */
  errorCode?: string;
  /**
   * The original exception, forwarded so the adapter can report it
   * (`setLastException` / `handleError`) without losing its stack and properties.
   */
  error?: unknown;
}

/** Subset of `MPSuperTokenPaymentMethods` the finalization spine drives. */
export interface FinalizationPaymentMethods {
  getActivePaymentMethod(): PaymentMethod | null;
  isSelectedPaymentMethodValid(): boolean;
  validateInstallmentSelection(): boolean;
  updateSecurityCode(): Promise<void>;
  unmountCardForm(): void;
}

/** Subset of `MPSuperTokenAuthenticator` the finalization spine drives. */
export interface FinalizationAuthenticator {
  authorizePayment(pseudotoken: string): Promise<void>;
  setSuperTokenValidation(value: boolean): void;
}

export interface FinalizeContext {
  paymentMethods: FinalizationPaymentMethods | null | undefined;
  authenticator: FinalizationAuthenticator | null | undefined;
  /** Classic order-pay-page unmounts the card form before authorizing; false on Blocks. */
  isOrderPayPage: boolean;
}

export class FinalizeSuperTokenPayment {
  async execute(ctx: FinalizeContext): Promise<FinalizeResult> {
    const { paymentMethods, authenticator, isOrderPayPage } = ctx;

    try {
      if (!paymentMethods) {
        throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
      }
      if (!authenticator) {
        throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);
      }

      const activeMethod = paymentMethods.getActivePaymentMethod();
      const isSelectionValid = !!activeMethod && paymentMethods.isSelectedPaymentMethodValid();

      if (!activeMethod) {
        throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);
      }
      if (!isSelectionValid) {
        throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);
      }

      // validateInstallmentSelection already renders the errors when it returns false
      // (legacy payment-methods.js:2512); the adapter only has to drop the loader.
      if (!paymentMethods.validateInstallmentSelection()) {
        return { status: 'validation_error' };
      }

      if (isOrderPayPage) {
        paymentMethods.unmountCardForm();
      }

      await paymentMethods.updateSecurityCode();
      await authenticator.authorizePayment(activeMethod.token);
      authenticator.setSuperTokenValidation(true);

      return { status: 'success' };
    } catch (exception) {
      const errorCode = (exception as Error)?.message;

      if (errorCode === MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
        return { status: 'validation_error', errorCode, error: exception };
      }

      authenticator?.setSuperTokenValidation(false);

      return {
        status: isRecoverable(errorCode) ? 'recoverable_error' : 'fatal_error',
        errorCode,
        error: exception,
      };
    }
  }
}

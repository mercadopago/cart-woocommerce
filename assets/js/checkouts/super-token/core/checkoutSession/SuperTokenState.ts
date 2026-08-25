/**
 * In-memory session state for a Super Token checkout: the fetched payment methods,
 * the active/preloaded selection, the super token, the amount, and the per-error
 * retry counter (RN-2). Pure domain state — no DOM, SDK or window. The view/adapter
 * layer owns rendering flags and DOM references; only the business state lives here.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): retry logic 250-260, session
 * getters/setters 202-238, 652-666, 1729-1734, and the state-only part of reset() 180.
 */

import type { PaymentMethod } from '@super-token/types/external-globals';
import { MAX_ATTEMPTS_BY_ERROR_CODE } from '@super-token/core/constants';
import { paymentMethodIdentifier } from '@super-token/core/checkoutSession/PaymentMethodClassifier';

/** The active selection can be a real payment method or the "new card" pseudo-option. */
export type ActiveSelection = PaymentMethod | { id: string };

export class SuperTokenState {
  private paymentMethods: PaymentMethod[] = [];
  private superToken: string | null = null;
  private amount: number | null = null;
  private activePaymentMethod: ActiveSelection | null = null;
  private attemptsByErrorCode: Record<string, number> = {};

  // Preserved across reset() — the buyer's prior selection must survive re-renders.
  private selectedPreloadedPaymentMethod: PaymentMethod | null = null;
  private lastPaymentMethodChoosen: ActiveSelection | null = null;

  storePaymentMethodsInMemory(accountPaymentMethods: PaymentMethod[]): void {
    this.paymentMethods = accountPaymentMethods;
  }

  getStoredPaymentMethods(): PaymentMethod[] {
    return this.paymentMethods;
  }

  hasStoredPaymentMethods(): boolean {
    return this.paymentMethods.length > 0;
  }

  storeSelectedPreloadedPaymentMethod(paymentMethod: PaymentMethod | null): void {
    this.selectedPreloadedPaymentMethod = paymentMethod;
  }

  getSelectedPreloadedPaymentMethod(): PaymentMethod | null {
    return this.selectedPreloadedPaymentMethod;
  }

  getSelectedPreloadedPaymentMethodFromActivePaymentMethods(): PaymentMethod | undefined {
    const selectedIdentifier = paymentMethodIdentifier(this.selectedPreloadedPaymentMethod);
    return this.paymentMethods.find(
      (paymentMethod) => paymentMethodIdentifier(paymentMethod) === selectedIdentifier,
    );
  }

  setSuperToken(token: string | null): void {
    this.superToken = token;
  }

  getSuperToken(): string | null {
    return this.superToken;
  }

  storeAmount(amount: number | null): void {
    this.amount = amount;
  }

  getAmount(): number | null {
    return this.amount;
  }

  getActivePaymentMethod(): ActiveSelection | null {
    return this.activePaymentMethod;
  }

  storeActivePaymentMethod(paymentMethod: ActiveSelection | null): void {
    this.activePaymentMethod = paymentMethod;
    this.lastPaymentMethodChoosen = paymentMethod || this.lastPaymentMethodChoosen;
  }

  clearActivePaymentMethod(): void {
    this.activePaymentMethod = null;
  }

  getLastPaymentMethodChoosen(): ActiveSelection | null {
    return this.lastPaymentMethodChoosen;
  }

  getAttemptByErrorCode(errorCode: string): number {
    return Math.min(this.attemptsByErrorCode[errorCode] || 0, MAX_ATTEMPTS_BY_ERROR_CODE);
  }

  shouldAllowRetry(attempt: number): boolean {
    return attempt < MAX_ATTEMPTS_BY_ERROR_CODE;
  }

  storeAttemptByErrorCode(errorCode: string): void {
    this.attemptsByErrorCode[errorCode] = (this.attemptsByErrorCode[errorCode] || 0) + 1;
  }

  /**
   * Resets the per-checkout domain state. Mirrors the state-only portion of the
   * legacy reset(); the preloaded and last selections are intentionally preserved.
   */
  reset(): void {
    this.paymentMethods = [];
    this.attemptsByErrorCode = {};
    this.activePaymentMethod = null;
  }
}

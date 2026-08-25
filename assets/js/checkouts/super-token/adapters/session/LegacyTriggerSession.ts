/**
 * Session adapter that lets the refactored `FetchAndRenderPaymentMethods` use case drive the
 * still-legacy `MPSuperTokenTriggerHandler.fetchAndRenderSuperTokenPaymentMethods`
 * (super-token-trigger-handler.js:243-275). The use case owns the load *order* (e-mail gate,
 * fetching flag, generation guard); this adapter supplies the *primitives*, forwarding each to
 * the legacy trigger handler instance and the collaborators it holds — its wcEmailListener, the
 * authenticator for the fetch (itself already delegating to the load seam) and the controller
 * for the render — a transitional scaffold while the primitives are ported.
 *
 * The metric name strings stay here (the legacy boundary), exposed to the use case as named
 * intentions through `createFetchAndRenderMetrics`, keeping them out of the domain.
 *
 * The same adapter also backs the refactored `CancelLoad` (super-token-trigger-handler.js:237-241):
 * cancel touches the same load-state cluster (fetching flag, load generation) plus the controller
 * reset, so it reuses this session rather than a second scaffold onto the same trigger handler.
 */

import type {
  FetchAndRenderSession,
  FetchAndRenderMetrics,
} from '@super-token/useCases/FetchAndRenderPaymentMethods';
import type { CancelLoadSession } from '@super-token/useCases/CancelLoad';
import type { PaymentMethod } from '@super-token/types/external-globals';

/** The subset of the legacy trigger handler (and the collaborators it holds) the fetch+render and cancel steps call. */
export interface LegacyLoadTriggerHandler {
  currentAmount: string | null;
  isFetchingPaymentMethods: boolean;
  loadGeneration: number;
  getBuyerEmail(): string | null | undefined;
  wcEmailListener: { isValid(email: string): boolean };
  mpSuperTokenAuthenticator: {
    getAccountPaymentMethods(amount: string, buyerEmail: string): Promise<PaymentMethod[] | null>;
  };
  mpSuperTokenPaymentMethods: {
    renderAccountPaymentMethods(paymentMethods: PaymentMethod[], amount: string | null): Promise<void> | void;
    reset(): void;
  };
}

export class LegacyTriggerSession implements FetchAndRenderSession, CancelLoadSession {
  constructor(private readonly triggerHandler: LegacyLoadTriggerHandler) {}

  getBuyerEmail(): string | null | undefined {
    return this.triggerHandler.getBuyerEmail();
  }

  isValidEmail(email: string): boolean {
    return this.triggerHandler.wcEmailListener.isValid(email);
  }

  setFetching(isFetching: boolean): void {
    this.triggerHandler.isFetchingPaymentMethods = isFetching;
  }

  getLoadGeneration(): number {
    return this.triggerHandler.loadGeneration;
  }

  currentAmount(): string | null {
    return this.triggerHandler.currentAmount;
  }

  fetchAccountPaymentMethods(amount: string, buyerEmail: string): Promise<PaymentMethod[] | null> {
    return this.triggerHandler.mpSuperTokenAuthenticator.getAccountPaymentMethods(amount, buyerEmail);
  }

  renderAccountPaymentMethods(paymentMethods: PaymentMethod[], amount: string | null): Promise<void> | void {
    return this.triggerHandler.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(paymentMethods, amount);
  }

  bumpLoadGeneration(): void {
    this.triggerHandler.loadGeneration++;
  }

  resetPaymentMethods(): void {
    this.triggerHandler.mpSuperTokenPaymentMethods.reset();
  }
}

/** Legacy `sendMetric` names from super-token-trigger-handler.js:246,252,256. */
const SKIPPED_NO_EMAIL_METRIC = 'super_token_skipped_no_email';
const SKIPPED_INVALID_EMAIL_METRIC = 'super_token_skipped_invalid_email';
const EMAIL_CAPTURED_METRIC = 'super_token_email_captured';

/** The subset of the legacy `MPSuperTokenMetrics` the e-mail gate reports through. */
export interface LegacyLoadMetrics {
  sendMetric(name: string, value: string, message: string): void;
}

/**
 * Wraps the legacy metrics instance as the use case's named metric intentions, keeping the
 * metric name strings at this legacy boundary rather than in the domain.
 */
export function createFetchAndRenderMetrics(metrics: LegacyLoadMetrics): FetchAndRenderMetrics {
  return {
    skippedNoEmail: () => metrics.sendMetric(SKIPPED_NO_EMAIL_METRIC, 'true', ''),
    skippedInvalidEmail: () => metrics.sendMetric(SKIPPED_INVALID_EMAIL_METRIC, 'true', ''),
    emailCaptured: () => metrics.sendMetric(EMAIL_CAPTURED_METRIC, 'true', ''),
  };
}

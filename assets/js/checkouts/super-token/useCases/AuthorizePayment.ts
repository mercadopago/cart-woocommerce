/**
 * Authorizing the payment at submit — the application sequence of
 * `authorizePayment(pseudotoken)` (super-token-authenticator.js:182-207). Owns the fixed
 * order (stored handle → re-verify simplified auth → authorize on the SDK → store the
 * authorized pseudotoken) and, crucially, the error *classification*: it always throws a
 * typed error code on failure (USER_CANCELLED vs generic), which the consumers
 * (event-handler.js / custom.block.js) branch on. The SDK call and DOM/state writes are
 * injected session operations.
 *
 * Unlike the load, this is NOT fail-safe: the throw is the contract — the caller must learn
 * the payment did not authorize — so callers must let it propagate, never swallow it.
 */

import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import type { RawSdkAuthenticator } from '@super-token/types/external-globals';

/** Legacy metric name for an auth that expired between load and submit (authenticator.js:189). */
const AUTH_EXPIRED_ON_SUBMIT_METRIC = 'super_token_auth_expired_on_submit';
/** SDK error message fragment that marks a buyer-cancelled authorization (authenticator.js:203). */
const USER_CANCELLED = 'USER_CANCELLED';

/** Subset of `MPSuperTokenAuthenticator` used to authorize at submit. */
export interface AuthorizeSession {
  getStoredAuthenticator(): RawSdkAuthenticator | null;
  getSimplifiedAuth(authenticator: RawSdkAuthenticator): Promise<boolean>;
  /** Legacy `callSdkWithMetrics(() => authenticator.authorizePayment(pseudotoken), 'authorizePayment')`. */
  authorizePaymentOnSdk(authenticator: RawSdkAuthenticator, pseudotoken: string): Promise<unknown>;
  storeAuthorizedPseudotoken(pseudotoken: string): void;
}

/** Subset of `MPSuperTokenMetrics` emitted while authorizing. */
export interface AuthorizePaymentMetrics {
  sendMetric(name: string, value: string, message: string): void;
  errorToAuthorizePayment(error: unknown): void;
}

export interface AuthorizePaymentContext {
  session: AuthorizeSession;
  metrics: AuthorizePaymentMetrics;
  pseudotoken: string;
}

export class AuthorizePayment {
  async execute(ctx: AuthorizePaymentContext): Promise<void> {
    const { session, metrics, pseudotoken } = ctx;

    try {
      const authenticator = session.getStoredAuthenticator();
      if (!authenticator) {
        throw new Error(MPSuperTokenErrorCodes.AUTHENTICATOR_NOT_FOUND);
      }

      const hasSimplified = await session.getSimplifiedAuth(authenticator);
      if (!hasSimplified) {
        metrics.sendMetric(AUTH_EXPIRED_ON_SUBMIT_METRIC, 'true', '');
        return;
      }

      await session.authorizePaymentOnSdk(authenticator, pseudotoken);
      session.storeAuthorizedPseudotoken(pseudotoken);
    } catch (error) {
      metrics.errorToAuthorizePayment(error);

      // The SDK may reject with a non-Error carrying the message (plain object or a
      // cross-realm error); classify on the message like the legacy path, not `instanceof`.
      const rawMessage = (error as { message?: unknown } | null | undefined)?.message;
      const message = typeof rawMessage === 'string' ? rawMessage : '';
      if (message.includes(USER_CANCELLED)) {
        throw new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED);
      }
      throw new Error(MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR);
    }
  }
}

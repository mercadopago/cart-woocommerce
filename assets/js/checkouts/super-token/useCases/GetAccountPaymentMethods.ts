/**
 * Loading the buyer's account payment methods — the application sequence of
 * `getAccountPaymentMethods(amount, buyerEmail)` (super-token-authenticator.js:144-180).
 * Owns the fixed order and its three fail-safe gates (not simplified auth, no fast payment
 * token, empty methods), always resolving to `null` on any failure so the load never blocks
 * the checkout. The SDK/authenticator handle and its stored state are injected session
 * operations, named after what the legacy does; the metrics mirror the legacy signals.
 *
 * The `authenticator` handle stays opaque here: the use case only threads it between the
 * session steps that build, verify and consume it. Storing it (and the fast payment token)
 * keeps the still-legacy `authorizePayment` and the plugin consumers seeing the same state.
 */

import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import type {
  AccountPaymentMethod,
  RawSdkAuthenticator,
  RawAccountPaymentMethodsResponse,
} from '@super-token/types/external-globals';

/** Subset of `MPSuperTokenAuthenticator`/`MPSuperTokenPaymentMethods` used to load methods. */
export interface AuthenticatorSession {
  isCurrentLoad(): boolean;
  buildAuthenticator(amount: string | null, buyerEmail: string): Promise<RawSdkAuthenticator | null>;
  storeAuthenticator(authenticator: RawSdkAuthenticator): void;
  getSimplifiedAuth(authenticator: RawSdkAuthenticator): Promise<boolean>;
  /** Legacy `document.dispatchEvent(new CustomEvent('mp-behavior-tracking-super-token-init'))`. */
  notifyBehaviorTrackingInit(): void;
  getFastPaymentToken(authenticator: RawSdkAuthenticator): Promise<string | null>;
  storeFastPaymentToken(token: string): void;
  fetchAccountPaymentMethods(token: string): Promise<RawAccountPaymentMethodsResponse | null>;
}

/** Subset of `MPSuperTokenMetrics` emitted while loading the account payment methods. */
export interface AuthenticatorMetrics {
  isNotSimplifiedAuth(): void;
  canUseSuperToken(canUse: boolean): void;
  cannotGetFastPaymentToken(): void;
  errorToGetAccountPaymentMethods(error: unknown): void;
  smartPixEligibilityFailure?(error: unknown, secrets: readonly string[]): void;
}

export interface GetAccountPaymentMethodsContext {
  session: AuthenticatorSession;
  metrics: AuthenticatorMetrics;
  amount: string | null;
  buyerEmail: string;
  signal?: AbortSignal;
}

export class GetAccountPaymentMethods {
  async execute(ctx: GetAccountPaymentMethodsContext): Promise<AccountPaymentMethod[] | null> {
    const { session, metrics, amount, buyerEmail, signal } = ctx;
    let fastPaymentToken: string | null = null;

    try {
      if (signal?.aborted) {
        return null;
      }

      const authenticator = await session.buildAuthenticator(amount, buyerEmail);
      if (signal?.aborted || !session.isCurrentLoad() || !authenticator) {
        return null;
      }

      session.storeAuthenticator(authenticator);

      const isSimplified = await session.getSimplifiedAuth(authenticator);
      if (signal?.aborted || !session.isCurrentLoad()) {
        return null;
      }
      if (!isSimplified) {
        metrics.isNotSimplifiedAuth();
        return null;
      }

      session.notifyBehaviorTrackingInit();
      metrics.canUseSuperToken(true);

      fastPaymentToken = await session.getFastPaymentToken(authenticator);
      if (signal?.aborted || !session.isCurrentLoad()) {
        return null;
      }
      if (!fastPaymentToken) {
        metrics.cannotGetFastPaymentToken();
        return null;
      }

      session.storeFastPaymentToken(fastPaymentToken);

      const accountPaymentMethods = await session.fetchAccountPaymentMethods(fastPaymentToken);
      if (signal?.aborted || !session.isCurrentLoad()) {
        return null;
      }
      if (!accountPaymentMethods?.data?.length) {
        throw new Error(MPSuperTokenErrorCodes.EMPTY_ACCOUNT_PAYMENT_METHODS);
      }

      return accountPaymentMethods.data;
    } catch (error) {
      if (signal?.aborted || !session.isCurrentLoad()) {
        return null;
      }
      metrics.errorToGetAccountPaymentMethods(error);
      try {
        metrics.smartPixEligibilityFailure?.(error, [buyerEmail, fastPaymentToken || '']);
      } catch {
        // Optional Smart Pix diagnostics cannot affect shared eligibility or the Pix fallback.
      }
      return null;
    }
  }
}

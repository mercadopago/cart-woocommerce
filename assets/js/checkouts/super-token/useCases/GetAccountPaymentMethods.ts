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
  PaymentMethod,
  RawSdkAuthenticator,
  RawAccountPaymentMethodsResponse,
} from '@super-token/types/external-globals';

/** Subset of `MPSuperTokenAuthenticator`/`MPSuperTokenPaymentMethods` used to load methods. */
export interface AuthenticatorSession {
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
}

export interface GetAccountPaymentMethodsContext {
  session: AuthenticatorSession;
  metrics: AuthenticatorMetrics;
  amount: string | null;
  buyerEmail: string;
}

export class GetAccountPaymentMethods {
  async execute(ctx: GetAccountPaymentMethodsContext): Promise<PaymentMethod[] | null> {
    const { session, metrics, amount, buyerEmail } = ctx;

    try {
      const authenticator = await session.buildAuthenticator(amount, buyerEmail);
      if (!authenticator) {
        return null;
      }

      session.storeAuthenticator(authenticator);

      const isSimplified = await session.getSimplifiedAuth(authenticator);
      if (!isSimplified) {
        metrics.isNotSimplifiedAuth();
        return null;
      }

      session.notifyBehaviorTrackingInit();
      metrics.canUseSuperToken(true);

      const fastPaymentToken = await session.getFastPaymentToken(authenticator);
      if (!fastPaymentToken) {
        metrics.cannotGetFastPaymentToken();
        return null;
      }

      session.storeFastPaymentToken(fastPaymentToken);

      const accountPaymentMethods = await session.fetchAccountPaymentMethods(fastPaymentToken);
      if (!accountPaymentMethods?.data?.length) {
        throw new Error(MPSuperTokenErrorCodes.EMPTY_ACCOUNT_PAYMENT_METHODS);
      }

      return accountPaymentMethods.data;
    } catch (error) {
      metrics.errorToGetAccountPaymentMethods(error);
      return null;
    }
  }
}

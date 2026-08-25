/**
 * Session adapter that lets the refactored `GetAccountPaymentMethods` use case drive the
 * still-legacy `MPSuperTokenAuthenticator.getAccountPaymentMethods` flow
 * (super-token-authenticator.js:144-180). The use case owns the load *order* and its
 * fail-safe gates; this adapter supplies the *primitives*, forwarding each to the legacy
 * authenticator instance (build/verify/store the handle and token), to the controller it
 * needs for the fetch (`mpSuperTokenPaymentMethods.getAccountPaymentMethods`), or to the DOM
 * (the behavior-tracking event) — a transitional scaffold while the primitives are ported.
 *
 * Storing the handle and the fast payment token on the legacy instance is deliberate: the
 * still-legacy `authorizePayment` and the plugin consumers read that same state at submit.
 */

import type { AuthenticatorSession } from '@super-token/useCases/GetAccountPaymentMethods';
import type { AuthorizeSession } from '@super-token/useCases/AuthorizePayment';
import type {
  RawSdkAuthenticator,
  RawAccountPaymentMethodsResponse,
} from '@super-token/types/external-globals';

/** Legacy `document.dispatchEvent` name from super-token-authenticator.js:157. */
const BEHAVIOR_TRACKING_INIT_EVENT = 'mp-behavior-tracking-super-token-init';
/** Legacy SDK label passed to `callSdkWithMetrics` from super-token-authenticator.js:194. */
const AUTHORIZE_PAYMENT_SDK_METHOD = 'authorizePayment';

/**
 * The subset of the legacy `MPSuperTokenAuthenticator` the load and submit flows call. The
 * SDK authenticator is an opaque handle threaded between these primitives.
 */
export interface LegacyAuthenticator {
  buildAuthenticator(amount: string, buyerEmail: string): Promise<RawSdkAuthenticator | null>;
  storeAuthenticator(authenticator: RawSdkAuthenticator): void;
  getSimplifiedAuth(authenticator: RawSdkAuthenticator): Promise<boolean>;
  getFastPaymentToken(authenticator: RawSdkAuthenticator): Promise<string | null>;
  storeFastPaymentToken(token: string): void;
  getStoredAuthenticator(): RawSdkAuthenticator | null;
  storeAuthorizedPseudotoken(pseudotoken: string): void;
}

/** The one method of the legacy controller the load flow needs — the account fetch. */
export interface LegacyAccountPaymentMethodsSource {
  getAccountPaymentMethods(token: string): Promise<RawAccountPaymentMethodsResponse | null>;
}

export class LegacyAuthenticatorSession implements AuthenticatorSession, AuthorizeSession {
  constructor(
    private readonly authenticator: LegacyAuthenticator,
    private readonly paymentMethods: LegacyAccountPaymentMethodsSource,
  ) {}

  buildAuthenticator(amount: string, buyerEmail: string): Promise<RawSdkAuthenticator | null> {
    return this.authenticator.buildAuthenticator(amount, buyerEmail);
  }

  storeAuthenticator(authenticator: RawSdkAuthenticator): void {
    this.authenticator.storeAuthenticator(authenticator);
  }

  getSimplifiedAuth(authenticator: RawSdkAuthenticator): Promise<boolean> {
    return this.authenticator.getSimplifiedAuth(authenticator);
  }

  notifyBehaviorTrackingInit(): void {
    document.dispatchEvent(new CustomEvent(BEHAVIOR_TRACKING_INIT_EVENT));
  }

  getFastPaymentToken(authenticator: RawSdkAuthenticator): Promise<string | null> {
    return this.authenticator.getFastPaymentToken(authenticator);
  }

  storeFastPaymentToken(token: string): void {
    this.authenticator.storeFastPaymentToken(token);
  }

  fetchAccountPaymentMethods(token: string): Promise<RawAccountPaymentMethodsResponse | null> {
    return this.paymentMethods.getAccountPaymentMethods(token);
  }

  getStoredAuthenticator(): RawSdkAuthenticator | null {
    return this.authenticator.getStoredAuthenticator();
  }

  authorizePaymentOnSdk(authenticator: RawSdkAuthenticator, pseudotoken: string): Promise<unknown> {
    const callWithMetrics = window.callSdkWithMetrics ?? ((sdkCall: () => Promise<unknown>) => sdkCall());
    return callWithMetrics(() => authenticator.authorizePayment(pseudotoken), AUTHORIZE_PAYMENT_SDK_METHOD);
  }

  storeAuthorizedPseudotoken(pseudotoken: string): void {
    this.authenticator.storeAuthorizedPseudotoken(pseudotoken);
  }
}

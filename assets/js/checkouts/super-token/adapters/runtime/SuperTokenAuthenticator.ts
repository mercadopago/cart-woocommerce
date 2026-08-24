/**
 * Ported `MPSuperTokenAuthenticator` (v2.1/entities/super-token-authenticator.js) — the published
 * `window.mpSuperTokenAuthenticator` instance the Classic (`event-handler.js`) and Blocks
 * (`custom.block.js`) checkout consumers call (`authorizePayment`, `setSuperTokenValidation`) and
 * the `authenticator` dependency wired into the trigger handler and the checkout finalizers.
 *
 * It owns the load/submit *state* (the amount and e-mail last used, the SDK authenticator handle
 * and the fast payment token) and the *primitives* that build/verify/consume that handle. Its two
 * orchestrations delegate to the use cases that already own their order and fail-safe rules:
 * `getAccountPaymentMethods` → `GetAccountPaymentMethods`, `authorizePayment` → `AuthorizePayment`,
 * driven through `LegacyAuthenticatorSession` with `this` as the primitive source. Unlike the load,
 * the authorize is not fail-safe — its typed throw is the contract the callers branch on.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity
 * with the legacy class. At the flip the bundle bootstrap constructs it with the ported TS
 * SDK/payment-methods/metrics collaborators and the localized `platform_id`, then publishes it
 * through `globalBridge.publish`. The raw ad-hoc `sendMetric` calls are kept verbatim; swapping
 * them for the adapter's semantic methods is a flip-time change (the active metrics instance today
 * is still the legacy one, which has no semantic methods).
 */
import { GetAccountPaymentMethods } from '@super-token/useCases/GetAccountPaymentMethods';
import type { AuthenticatorMetrics } from '@super-token/useCases/GetAccountPaymentMethods';
import { AuthorizePayment } from '@super-token/useCases/AuthorizePayment';
import type { AuthorizePaymentMetrics } from '@super-token/useCases/AuthorizePayment';
import {
  LegacyAuthenticatorSession,
  type LegacyAccountPaymentMethodsSource,
} from '@super-token/adapters/session/LegacyAuthenticatorSession';
import type {
  PaymentMethod,
  RawSdkAuthenticator,
  RawMpSdkInstance,
} from '@super-token/types/external-globals';

/** Superset of the metrics the load use case, the submit use case and the primitives emit. */
export interface SuperTokenAuthenticatorMetrics extends AuthenticatorMetrics, AuthorizePaymentMetrics {
  errorToBuildAuthenticator(error: unknown): void;
  errorToGetSimplifiedAuth(error: unknown): void;
  errorToGetFastPaymentToken(error: unknown): void;
  registerAuthorizedPseudotoken(authorizedPseudotokenInputExists: boolean): void;
}

export class SuperTokenAuthenticator {
  private readonly SUPER_TOKEN_VALIDATION_ELEMENT_ID = 'super_token_validation';
  private readonly AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID = 'authorized_pseudotoken';
  private readonly AUTHENTICATOR_VERSION = 2;

  private amountUsed: string | null = null;
  private emailUsed: string | null = null;
  private authenticator: RawSdkAuthenticator | null = null;
  private fastPaymentToken: string | null = null;

  private readonly getAccountPaymentMethodsUseCase = new GetAccountPaymentMethods();
  private readonly authorizePaymentUseCase = new AuthorizePayment();

  constructor(
    private readonly mpSdkInstance: RawMpSdkInstance,
    private readonly paymentMethods: LegacyAccountPaymentMethodsSource,
    private readonly metrics: SuperTokenAuthenticatorMetrics,
    private readonly platformId: string,
  ) {}

  reset(): void {
    this.authenticator = null;
    this.fastPaymentToken = null;
  }

  setSuperTokenValidation(value: boolean): void {
    const element = document.getElementById(this.SUPER_TOKEN_VALIDATION_ELEMENT_ID) as HTMLInputElement | null;
    if (element) {
      element.value = value ? 'true' : 'false';
    }
  }

  getAmountUsed(): string | null {
    return this.amountUsed;
  }

  getEmailUsed(): string | null {
    return this.emailUsed;
  }

  storeAuthenticator(authenticator: RawSdkAuthenticator): void {
    this.authenticator = authenticator;
  }

  getStoredAuthenticator(): RawSdkAuthenticator | null {
    return this.authenticator;
  }

  storeFastPaymentToken(token: string): void {
    this.fastPaymentToken = token;
  }

  formatAmount(amount: string | null | undefined = ''): string | null {
    const rawValue = amount?.replace(/[^\d.,]/g, '');
    if (!rawValue) return null;

    const lastCommaIndex = rawValue.lastIndexOf(',');
    const lastDotIndex = rawValue.lastIndexOf('.');

    const isEuropean = lastCommaIndex > lastDotIndex;
    const normalizedValue = rawValue.replace(/[.,]/g, (match) => {
      if (isEuropean) {
        return match === ',' ? '.' : '';
      }
      return match === '.' ? '.' : '';
    });

    const value = parseFloat(normalizedValue);

    return isNaN(value) ? null : value.toFixed(2);
  }

  async buildAuthenticator(amount: string | null, buyerEmail: string): Promise<RawSdkAuthenticator | null> {
    try {
      this.amountUsed = amount;
      this.emailUsed = buyerEmail;

      const callWithMetrics =
        window.callSdkWithMetrics ?? (<T>(sdkCall: () => Promise<T>): Promise<T> => sdkCall());
      const authenticator = await callWithMetrics(
        () =>
          this.mpSdkInstance.authenticator(amount, buyerEmail, {
            platformId: this.platformId,
            version: this.AUTHENTICATOR_VERSION,
          }),
        'buildAuthenticator',
      );

      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_falsy', String(authenticator), `typeof:${typeof authenticator}`);
        return null;
      }

      return authenticator;
    } catch (error) {
      this.metrics.errorToBuildAuthenticator(error);
      return null;
    }
  }

  async getSimplifiedAuth(authenticator: RawSdkAuthenticator): Promise<boolean> {
    try {
      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_null', 'getSimplifiedAuth', '');
        return false;
      }

      return await authenticator.getSimplifiedAuth();
    } catch (error) {
      this.metrics.errorToGetSimplifiedAuth(error);
      return false;
    }
  }

  async getFastPaymentToken(authenticator: RawSdkAuthenticator): Promise<string | null> {
    try {
      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_null', 'getFastPaymentToken', '');
        return null;
      }

      return await authenticator.getFastPaymentToken();
    } catch (error) {
      this.metrics.errorToGetFastPaymentToken(error);
      return null;
    }
  }

  storeAuthorizedPseudotoken(pseudotoken: string): void {
    const element = document.getElementById(this.AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID) as HTMLInputElement | null;

    this.metrics.registerAuthorizedPseudotoken(element ? true : false);

    if (element) {
      element.value = pseudotoken;
    }
  }

  getAccountPaymentMethods(amount: string | null, buyerEmail: string): Promise<PaymentMethod[] | null> {
    return this.getAccountPaymentMethodsUseCase.execute({
      session: new LegacyAuthenticatorSession(this, this.paymentMethods),
      metrics: this.metrics,
      amount,
      buyerEmail,
    });
  }

  authorizePayment(pseudotoken: string): Promise<void> {
    return this.authorizePaymentUseCase.execute({
      session: new LegacyAuthenticatorSession(this, this.paymentMethods),
      metrics: this.metrics,
      pseudotoken,
    });
  }
}

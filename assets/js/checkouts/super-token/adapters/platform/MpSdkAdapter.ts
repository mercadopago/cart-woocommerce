import type { PaymentSdkPort, SdkAuthenticator, SdkCardTokenFields } from '@super-token/ports';
import type {
  RawMpSdkInstance,
  RawSdkCreditsContractController,
  PaymentMethod,
} from '@super-token/types/external-globals';

/**
 * Platform adapter: the single point that touches the MP JS SDK
 * (`window.mpSdkInstance`) for the Super Token flow (RN-1, DD-4).
 *
 * The SDK instance is injected (never read from `window.*` here) so the domain
 * and tests can pass a fake. Every method is a thin delegation — the adapter adds
 * no orchestration (retries, `callSdkWithMetrics` wrapping and auth revalidation
 * stay with their use cases, TASK-006) and never reads card data (CVV/PAN/token)
 * into JS state: card fields live only inside the SDK iframe (SEC-1, PCI).
 */
export class MpSdkAdapter implements PaymentSdkPort {
  private readonly sdk: RawMpSdkInstance;

  constructor(sdk: RawMpSdkInstance) {
    this.sdk = sdk;
  }

  authenticator(
    amount: string | null,
    buyerEmail: string,
    options: { platformId: string; version: number },
  ): Promise<SdkAuthenticator> {
    return this.sdk.authenticator(amount, buyerEmail, options);
  }

  getAccountPaymentMethods(superToken: string): Promise<{ data: PaymentMethod[] }> {
    return this.sdk.getAccountPaymentMethods(superToken);
  }

  getAccountPaymentMethod(superToken: string, paymentMethodToken: string): Promise<{ data: PaymentMethod }> {
    return this.sdk.getAccountPaymentMethod(superToken, paymentMethodToken);
  }

  getCardId(superToken: string, paymentMethodToken: string): Promise<{ card_id: string }> {
    return this.sdk.getCardId(superToken, paymentMethodToken);
  }

  updatePseudotoken(superToken: string, paymentMethodToken: string, cardTokenId: string): Promise<void> {
    return this.sdk.updatePseudotoken(superToken, paymentMethodToken, cardTokenId);
  }

  renderCreditsContract(elementId: string, parameters: object): Promise<RawSdkCreditsContractController> {
    return this.sdk.renderCreditsContract(elementId, parameters);
  }

  getSDKInstanceId(): string {
    return this.sdk.getSDKInstanceId();
  }

  get fields(): SdkCardTokenFields {
    return this.sdk.fields;
  }
}

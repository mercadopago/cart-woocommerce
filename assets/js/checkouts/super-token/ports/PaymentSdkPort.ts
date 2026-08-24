/**
 * Port: gateway to the Mercado Pago JS SDK (`window.mpSdkInstance`).
 *
 * Contract only — no implementation. Grounded in the real SDK surface the Super
 * Token flow uses today (authenticate → resolve payment method → card id → card
 * token → pseudotoken). The concrete adapter wraps `window.mpSdkInstance`
 * (TASK-005); the full surface is completed as the domain migrates (TASK-006).
 */
export interface SdkAuthenticator {
  getSimplifiedAuth(): Promise<boolean>;
  getFastPaymentToken(): Promise<string | null>;
  authorizePayment(pseudotoken: string): Promise<unknown>;
}

export interface SdkCardTokenFields {
  createCardToken(params: { cardId: string }): Promise<{ id: string }>;
  create(field: 'securityCode', options: object): unknown;
}

export interface PaymentSdkPort {
  authenticator(
    amount: string | null,
    buyerEmail: string,
    options: { platformId: string; version: number },
  ): Promise<SdkAuthenticator>;
  getAccountPaymentMethods(superToken: string): Promise<{ data: import('@super-token/types/external-globals').PaymentMethod[] }>;
  getAccountPaymentMethod(superToken: string, paymentMethodToken: string): Promise<{ data: import('@super-token/types/external-globals').PaymentMethod }>;
  getCardId(superToken: string, paymentMethodToken: string): Promise<{ card_id: string }>;
  updatePseudotoken(superToken: string, paymentMethodToken: string, cardTokenId: string): Promise<void>;
  renderCreditsContract(elementId: string, parameters: object): Promise<{ update(params: { installments: string }): void }>;
  getSDKInstanceId(): string;
  fields: SdkCardTokenFields;
}

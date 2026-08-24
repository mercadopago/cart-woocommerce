/**
 * Raw platform globals the platform adapters (TASK-005) are the sole authorized
 * readers of. These describe the *external* surfaces as they exist today — the MP
 * JS SDK instance (`window.mpSdkInstance`), the MeliData client, and the WooCommerce
 * Blocks registry — so the adapters can wrap them under the ports without `any`.
 *
 * Grounded in real usage (`v2/entities/super-token-payment-methods.js`,
 * `super-token-authenticator.js`, `super-token-metrics.js`, `melidata-client.js`).
 */

// ─── Payment method types (raw SDK shape) ────────────────────────────────────
// Mapped from v2/entities/super-token-payment-methods.js via renderAccountPaymentMethods.

export type PaymentMethodType =
  | 'credit_card'
  | 'debit_card'
  | 'account_money'
  | 'prepaid_card'
  | 'digital_currency'; // consumer_credits — the SDK uses 'digital_currency', NOT 'consumer_credits'

export interface SecurityCodeSettings {
  /** 'mandatory' triggers the CVV field; any other value = not required. */
  mode: 'mandatory' | string;
  length: 3 | 4;
}

export interface Installment {
  installments: number;
  installment_amount: number;
  installment_rate: number;
  installment_rate_collector: string[];
  total_amount: number;
  labels?: string[];
  consumer_credits?: { conditions: unknown };
  /** IOF amount in BRL — MLB + digital_currency only. */
  installment_iof_amount?: number;
}

interface CardPaymentMethodBase {
  id: string;
  token: string;
  name: string;
  thumbnail: string;
  card?: { card_number: { last_four_digits: string } };
  issuer?: { name: string };
  security_code_settings?: SecurityCodeSettings;
  /** true = ESC present; undefined = not yet queried; false = no ESC. */
  has_esc?: boolean;
}

export interface CreditCardPaymentMethod extends CardPaymentMethodBase {
  type: 'credit_card';
  installments?: Installment[];
}

export interface DebitCardPaymentMethod extends CardPaymentMethodBase {
  type: 'debit_card';
  installments?: Installment[];
}

export interface PrepaidCardPaymentMethod extends CardPaymentMethodBase {
  type: 'prepaid_card';
}

export interface AccountMoneyPaymentMethod {
  id: string;
  token: string;
  name: string;
  thumbnail: string;
  type: 'account_money';
  has_account_money: boolean;
  has_account_money_invested: boolean;
}

export interface ConsumerCreditsPaymentMethod {
  id: string;
  token: string;
  name: string;
  thumbnail: string;
  type: 'digital_currency';
  credits_pricing_id: string;
  next_due_date?: string;
  installments?: Installment[];
}

export type PaymentMethod =
  | CreditCardPaymentMethod
  | DebitCardPaymentMethod
  | PrepaidCardPaymentMethod
  | AccountMoneyPaymentMethod
  | ConsumerCreditsPaymentMethod;

// ─── MP JS SDK ────────────────────────────────────────────────────────────────

/** Payload of the SDK security-code field `validityChange` event (payment-methods.js:1924-1947). */
export interface RawSdkSecurityCodeValidity {
  errorMessages: Array<{ cause?: string }>;
}

export interface RawSdkSecurityCodeField {
  mount(elementId: string): RawSdkSecurityCodeField;
  on(event: 'error', handler: (payload: unknown) => void): RawSdkSecurityCodeField;
  on(event: 'ready', handler: () => void): RawSdkSecurityCodeField;
  on(event: 'validityChange', handler: (payload: RawSdkSecurityCodeValidity) => void): RawSdkSecurityCodeField;
  update(options: object): RawSdkSecurityCodeField;
  unmount(): void;
  focus(): void;
  blur(): void;
}

export interface RawSdkCreditsContractController {
  // The SDK receives the raw `<select>` value at runtime (a string), matching the legacy
  // `contractController.update({ installments: selectedValue })` — keep the type honest to
  // that contract so the call site never needs to cast.
  update(params: { installments: string }): void;
}

export interface RawSdkAuthenticator {
  getSimplifiedAuth(): Promise<boolean>;
  getFastPaymentToken(): Promise<string | null>;
  authorizePayment(pseudotoken: string): Promise<unknown>;
}

export interface RawSdkCardTokenFields {
  createCardToken(params: { cardId: string }): Promise<{ id: string }>;
  create(field: 'securityCode', options: object): RawSdkSecurityCodeField;
}

/**
 * `getAccountPaymentMethods` resolves to this envelope — consumers read `.data`.
 * Grounded in super-token-authenticator.js:165: `accountPaymentMethods.data.length`.
 */
export interface RawAccountPaymentMethodsResponse {
  data: PaymentMethod[];
}

/**
 * `getAccountPaymentMethod` resolves to this envelope — consumers read `.data`.
 * Grounded in super-token-payment-methods.js:1071: `result?.data`.
 */
export interface RawAccountPaymentMethodResponse {
  data: PaymentMethod;
}

export interface RawMpSdkInstance {
  authenticator(
    amount: string | null,
    buyerEmail: string,
    options: { platformId: string; version: number },
  ): Promise<RawSdkAuthenticator>;
  getAccountPaymentMethods(superToken: string): Promise<RawAccountPaymentMethodsResponse>;
  getAccountPaymentMethod(superToken: string, paymentMethodToken: string): Promise<RawAccountPaymentMethodResponse>;
  getCardId(superToken: string, paymentMethodToken: string): Promise<{ card_id: string }>;
  updatePseudotoken(superToken: string, paymentMethodToken: string, cardTokenId: string): Promise<void>;
  renderCreditsContract(elementId: string, parameters: object): Promise<RawSdkCreditsContractController>;
  getSDKInstanceId(): string;
  fields: RawSdkCardTokenFields;
}

// ─── MeliData client (CDN bundle: woocommerce.min.js) ────────────────────────
// Not exported from the mpmodules-melidata-client package — declared here from
// the real source at fury_mpmodules-melidata-client/src/clients/woocommerce/.

export interface MelidataBuyerClient {
  setCheckoutEvents(): void;
  setPageThankYouEvents(): void;
  setRejectedPaymentPageTimer(): void;
  setPendingPaymentPageTimer(): void;
  createCheckoutFunnelEvent(params: {
    path: string;
    forceSend?: boolean;
    methodId?: string[];
    inputId?: string[];
    defaultMethod?: string;
    errorMessage?: string;
    melidataType?: string;
    errorOrigin?: string;
  }): void;
  createCheckoutLoadingEvent(params: {
    path: string;
    forceSend?: boolean;
    dateNowInMilliseconds: number;
    checkoutType: string;
    loadingId?: string | null;
    loadedAs?: string;
  }): void;
  storeSuperTokenPaymentMethodId(paymentMethodId: string): void;
  getSelectedSuperTokenPaymentMethodId(): string;
  hasSelectedSuperTokenPaymentMethod(): boolean;
  resetSelectedSuperTokenPaymentMethodId(): void;
  getMpFlowId(): string | undefined;
}

export interface MelidataSellerClient {
  stepPaymentMethodsCallback(): void;
  calledStepPaymentMethodsCallback: boolean;
  addStoreConfigurationsStepTimer(params: {
    step: 'mode' | 'business' | 'credentials' | 'payment_methods';
    sendOnClose?: boolean;
    restartTimer?: boolean;
    preserveTimer?: boolean;
  }): void;
}

/** Resolved value of `window.melidataReady` (and the value of `window.melidata`). */
export interface MelidataClient {
  client: MelidataBuyerClient | MelidataSellerClient;
}

// ─── WooCommerce Blocks registry (for checkout type detection) ───────────────

export interface WcBlocksRegistry {
  getPaymentMethods?(): Record<string, unknown>;
}

// ─── Localized PHP params ─────────────────────────────────────────────────────

/** `wc_mercadopago_supertoken_bundle_params` — localized for the Super Token bundle. */
export interface SuperTokenBundleParams {
  plugin_version: string;
  platform_version: string;
  site_id: string;
  cust_id: string;
  location: string;
  platform_id: string;
  plugin_js_base_url?: string;
}

/** `wc_mercadopago_woocommerce_scripts_params` — localized for the loader/variant flow. */
export interface WoocommerceScriptsParams {
  plugin_version?: string;
  platform_version?: string;
  site_id?: string;
  cust_id?: string;
  theme?: string;
}

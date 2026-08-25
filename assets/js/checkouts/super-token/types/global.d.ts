import type {
  SuperTokenTriggerHandler,
  SuperTokenAuthenticator,
  SuperTokenPaymentMethods,
  SuperTokenMetrics,
  SuperTokenErrorHandler,
} from './instances';
import type {
  RawMpSdkInstance,
  MelidataClient,
  WcBlocksRegistry,
  SuperTokenBundleParams,
  WoocommerceScriptsParams,
  PaymentMethod,
  Installment,
} from './external-globals';
import type { ClassicCheckoutDeps } from '../adapters/checkout/ClassicCheckout';
import type { BlocksCheckoutDeps, BlocksResponse } from '../adapters/checkout/BlocksCheckout';
import type { CheckoutValidationVerdict } from '../adapters/validation/checkoutValidationResolver';

declare global {
  interface Window {
    mpSuperTokenTriggerHandler?: SuperTokenTriggerHandler;
    mpSuperTokenAuthenticator?: SuperTokenAuthenticator;
    mpSuperTokenPaymentMethods?: SuperTokenPaymentMethods;
    mpSuperTokenMetrics?: SuperTokenMetrics;
    mpSuperTokenErrorHandler?: SuperTokenErrorHandler;

    // Refactored finalization entry points the legacy checkout consumers delegate to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenFinalizeClassic?: (input: Omit<ClassicCheckoutDeps, 'finalize'>) => Promise<void>;
    mpSuperTokenFinalizeBlocks?: (input: Omit<BlocksCheckoutDeps, 'finalize'>) => Promise<BlocksResponse>;

    // Refactored saved-method selection entry point the legacy controller delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenSelectPaymentMethod?: (
      paymentMethod: PaymentMethod,
      paymentMethodElement: HTMLElement,
    ) => Promise<void>;

    // Refactored reset entry point the legacy trigger handler delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenResetOnError?: (preserveSelection: boolean) => void;

    // Refactored order+decorate entry point the legacy organizePaymentMethodsElements
    // delegates to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenOrderAndDecorate?: (paymentMethods: PaymentMethod[]) => PaymentMethod[];

    // Refactored saved-methods render entry point the legacy onCustomCheckoutWasRendered
    // delegates to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenRenderSavedMethods?: (container: HTMLElement, paymentMethods: PaymentMethod[]) => void;

    // Refactored account-payment-methods load entry point the legacy getAccountPaymentMethods
    // delegates to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenGetAccountPaymentMethods?: (
      amount: string | null,
      buyerEmail: string,
    ) => Promise<PaymentMethod[] | null>;

    // Refactored submit-time authorize entry point the legacy authorizePayment delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenAuthorizePayment?: (pseudotoken: string) => Promise<void>;

    // Refactored fetch+render load entry point the legacy fetchAndRenderSuperTokenPaymentMethods
    // delegates to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenFetchAndRenderPaymentMethods?: () => Promise<void>;

    // Refactored load-orchestration entry point the legacy loadSuperToken delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenLoadSuperToken?: (currentAmount: string) => Promise<void>;

    // Refactored cancel-load entry point the legacy cancelLoad delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenCancelLoad?: () => void;

    // Refactored reset-custom-checkout entry point the legacy resetCustomCheckout delegates to,
    // published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenResetCustomCheckout?: (shouldClearCache: boolean) => void;

    // Refactored error-handling entry point the legacy finalizeResetTail (and eventual other
    // callers of MPSuperTokenErrorHandler.handleError) delegates to, published once by the
    // bundle entrypoint (bootstrap.ts) via globalBridge. Returns the normalised error code.
    mpSuperTokenHandleError?: (exception: unknown) => string;

    // Refactored preloaded-method restore entry point the legacy finalizeResetTail delegates
    // to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenRestorePreloadedPaymentMethod?: () => Promise<void>;

    // Refactored e-mail listener registration entry point the legacy ensureEmailListenerRegistered
    // delegates to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge.
    mpSuperTokenEnsureEmailListenerRegistered?: () => void;

    // Super Token error-code constants, published by the bundle entrypoint (bootstrap.ts) via
    // globalBridge from core ErrorClassification and read as a global by the Classic
    // event-handler.js and Blocks custom.block.js. Replaces the legacy error-constants.js so the
    // whole v2/v2.1 tree can be deleted.
    MPSuperTokenErrorCodes?: Readonly<Record<string, string>>;

    // Ported checkout pre-validation resolver the still-legacy Classic event-handler.js delegates
    // to, published once by the bundle entrypoint (bootstrap.ts) via globalBridge. Replaces the
    // legacy shared/validators/checkout-validation-resolver.js so the v2/v2.1/shared tree can be
    // deleted.
    mpResolveCheckoutValidation?: (response: unknown) => CheckoutValidationVerdict;

    // Ported WooCommerce validation gate, published once by the bundle entrypoint (bootstrap.ts)
    // via globalBridge. No in-tree caller reads it today; kept for old-plugin CDN consumers.
    // Replaces the legacy v2.1/validators/checkout-form-validator.js.
    hasWooCommerceValidationErrors?: () => boolean;

    // External platform globals the platform adapters (TASK-005) read. Only the
    // adapters may touch these; the domain stays free of `window.*`.
    mpSdkInstance?: RawMpSdkInstance;
    // `melidata` is null between script injection and CDN onload (see melidata-client.js:44).
    melidata?: MelidataClient | null;
    melidataReady?: Promise<MelidataClient>;
    // WooCommerce Blocks registry — used for Classic/Blocks checkout detection.
    wc?: { wcBlocksRegistry?: WcBlocksRegistry };
    wc_mercadopago_supertoken_bundle_params?: SuperTokenBundleParams;
    wc_mercadopago_woocommerce_scripts_params?: WoocommerceScriptsParams;

    // Legacy checkout globals the transitional render session forwards to (installments wiring).
    // Only the legacy-boundary adapter reads these; the domain stays free of `window.*`.
    CheckoutPage?: {
      updateTaxInfoForSelect(selectedValue: string, taxInfoElementId: string, installments: Installment[]): void;
    };
    MPCheckoutFieldsDispatcher?: {
      addEventListenerDispatcher(
        element: null,
        event: string,
        name: string,
        options: { onlyDispatch: boolean },
      ): void;
    };
    sendMetric?: (name: string, value: string, message: string) => void;
    // Legacy custom checkout controller the reset head reads for the load spinner and the reset
    // tail reads to tear it down; only the legacy-boundary reset session and the ported trigger
    // handler's `finalizeResetTail` touch it, the domain stays free of `window.*`.
    mpCustomCheckoutHandler?: {
      cardForm?: {
        createLoadSpinner(): void;
        removeLoadSpinner(): void;
        formMounted?: boolean;
        initCardForm(amount: string | null): void;
        form?: { unmount(): void };
      };
      eventHandler?: { hideCheckoutClassicLoader(): void };
    };
    // Legacy Classic event handler; the ported payment-methods controller reads only
    // `mercado_pago_submit` in the captcha pre-validation spy (payment-methods.js:372).
    // `setSuperTokenDependencies` is the injection target the flip bootstrap calls to hand the
    // composed instances to the Classic handler (typed `unknown` to avoid a cross-module import
    // in this ambient file; the caller passes SuperTokenInstances).
    mpEventHandler?: {
      mercado_pago_submit?: boolean;
      setSuperTokenDependencies?(instances: unknown): void;
    };
    // SDK call wrapper (timing + metrics) from mp-sdk-metrics.js; read at the legacy boundary
    // only, to preserve the legacy authorize observability.
    callSdkWithMetrics?: <T>(sdkCall: () => Promise<T>, sdkMethod: string) => Promise<T>;
    // WooCommerce's global jQuery; read only by the ported e-mail listener as the default for its
    // injected jQuery accessor (constructor allows overriding it for tests).
    jQuery?: (target: unknown) => {
      val(): string | undefined;
      on(event: string, selector: string, handler: (inputEvent: unknown) => void): unknown;
    };
  }
}

export {};

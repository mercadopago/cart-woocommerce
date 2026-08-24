/**
 * Runtime instances the composition root builds and the transitional bridge
 * publishes to `window.mpSuperToken*`.
 *
 * These are intentionally opaque handles: the concrete classes still live as
 * global-scoped files under `v2/`/`v2.1/` and are ported into `core/`/`useCases/`
 * later (TASK-006). The optional discriminant keeps each nominally distinct
 * without over-specifying the legacy API here.
 */
export interface SuperTokenTriggerHandler {
  readonly kind?: 'trigger-handler';
  /** Set by the trigger handler once it binds the checkout form; read by the
   *  initialization-resilience check (TASK-010) to confirm the wiring took. */
  readonly isAlreadyListeningForm?: boolean;
}
export interface SuperTokenAuthenticator { readonly kind?: 'authenticator'; }
export interface SuperTokenPaymentMethods { readonly kind?: 'payment-methods'; }
export interface SuperTokenMetrics { readonly kind?: 'metrics'; }
export interface SuperTokenErrorHandler { readonly kind?: 'error-handler'; }

export interface SuperTokenInstances {
  triggerHandler: SuperTokenTriggerHandler;
  authenticator: SuperTokenAuthenticator;
  paymentMethods: SuperTokenPaymentMethods;
  metrics: SuperTokenMetrics;
  errorHandler: SuperTokenErrorHandler;
}

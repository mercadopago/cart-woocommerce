import type { SuperTokenInstances } from '@super-token/types/instances';
import type { ClassicCheckoutDeps } from '@super-token/adapters/checkout/ClassicCheckout';
import type { BlocksCheckoutDeps, BlocksResponse } from '@super-token/adapters/checkout/BlocksCheckout';
import type { PaymentMethod } from '@super-token/types/external-globals';
import type { CheckoutValidationVerdict } from '@super-token/adapters/validation/checkoutValidationResolver';

/**
 * Transitional compatibility bridge (TECH-4).
 *
 * Publishes the composed instances under the same `window.mpSuperToken*` names
 * used today, so integrators that still read those globals (Classic
 * `event-handler.js` fallback, Blocks `custom.block.js`) keep working during the
 * migration. This is the ONLY place new code writes to `window.*`.
 *
 * Tracked debt: removed once traffic on the old bundle is residual and no
 * external consumer depends on `window.mpSuperToken*` — no fixed date.
 */
export function publish(instances: SuperTokenInstances): void {
  window.mpSuperTokenTriggerHandler = instances.triggerHandler;
  window.mpSuperTokenAuthenticator = instances.authenticator;
  window.mpSuperTokenPaymentMethods = instances.paymentMethods;
  window.mpSuperTokenMetrics = instances.metrics;
  window.mpSuperTokenErrorHandler = instances.errorHandler;
}

/**
 * Publishes the Super Token error-code constants under `window.MPSuperTokenErrorCodes`, sourced
 * from the core ErrorClassification, so the still-legacy Classic `event-handler.js` and Blocks
 * `custom.block.js` keep reading them as a global. Replaces the legacy
 * `errors/super-token-error-constants.js` so the whole v2/v2.1 tree can be deleted.
 */
export function publishErrorCodes(codes: Readonly<Record<string, string>>): void {
  window.MPSuperTokenErrorCodes = codes;
}

/**
 * The finalization entry points the legacy checkout consumers call at submit time. The
 * consumer supplies the deps it owns (legacy instances + DOM callbacks); `finalize` (the
 * shared use case) is injected by the bundle entrypoint, so the consumers never construct
 * the domain themselves. `finalize` is omitted from the input for that reason.
 */
export interface SuperTokenFinalizers {
  finalizeClassic(input: Omit<ClassicCheckoutDeps, 'finalize'>): Promise<void>;
  finalizeBlocks(input: Omit<BlocksCheckoutDeps, 'finalize'>): Promise<BlocksResponse>;
}

/**
 * Publishes the refactored finalization entry points under `window.mpSuperTokenFinalize*`
 * so the still-legacy `event-handler.js`/`custom.block.js` can delegate to the unified
 * ClassicCheckout/BlocksCheckout. Kept separate from `publish` so the instance mirror stays
 * exactly the five legacy names.
 */
export function publishFinalizers(finalizers: SuperTokenFinalizers): void {
  window.mpSuperTokenFinalizeClassic = finalizers.finalizeClassic;
  window.mpSuperTokenFinalizeBlocks = finalizers.finalizeBlocks;
}

/**
 * The saved-method selection entry point the legacy `onSelectSuperTokenPaymentMethod`
 * (v2/v2.1 payment-methods.js) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the selected method and its DOM row.
 */
export interface SuperTokenSelectors {
  selectSavedPaymentMethod(
    paymentMethod: PaymentMethod,
    paymentMethodElement: HTMLElement,
  ): Promise<void>;
}

/**
 * Publishes the refactored selection entry point under `window.mpSuperTokenSelectPaymentMethod`
 * so the still-legacy controller can delegate its `onSelectSuperTokenPaymentMethod`. Kept
 * separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishSelectors(selectors: SuperTokenSelectors): void {
  window.mpSuperTokenSelectPaymentMethod = selectors.selectSavedPaymentMethod;
}

/**
 * The reset entry point the legacy `resetSuperTokenOnError` (trigger-handler.js) delegates to.
 * The bundle injects the ResetFlow use case + session behind it; the legacy shell only forwards
 * the `preserveSelection` decision.
 */
export interface SuperTokenReset {
  resetOnError(preserveSelection: boolean): void;
}

/**
 * Publishes the refactored reset entry point under `window.mpSuperTokenResetOnError` so the
 * still-legacy trigger handler can delegate its `resetSuperTokenOnError`. Kept separate from
 * `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishReset(reset: SuperTokenReset): void {
  window.mpSuperTokenResetOnError = reset.resetOnError;
}

/**
 * The order+decorate entry point the legacy `organizePaymentMethodsElements` delegates to,
 * replacing its inline `reorderAccountPaymentMethods` + `normalizeAccountPaymentMethods` with
 * the core `PaymentMethodCatalog` + `PaymentMethodRegistry`. Returns the same list, reordered
 * and decorated in place (parity with the legacy pair).
 */
export interface SuperTokenOrderAndDecorate {
  orderAndDecorate(paymentMethods: PaymentMethod[]): PaymentMethod[];
}

/**
 * Publishes the refactored order+decorate entry point under
 * `window.mpSuperTokenOrderAndDecorate`. Kept separate from `publish` so the instance mirror
 * stays exactly the five legacy names.
 */
export function publishOrderAndDecorate(seam: SuperTokenOrderAndDecorate): void {
  window.mpSuperTokenOrderAndDecorate = seam.orderAndDecorate;
}

/**
 * The saved-methods render entry point the legacy `onCustomCheckoutWasRendered` delegates to,
 * replacing its `organizePaymentMethodsElements` + `setupEmailHeaderListener` with the refactored
 * variant view (grouping + blocks + header + live e-mail listener). The per-row element is still
 * built by the legacy controller and forwarded in through the render session, so this seam owns
 * the render *order* while the row stays legacy — the scaffold that shrinks as the row is ported.
 */
export interface SuperTokenRenderSavedMethods {
  renderSavedMethods(container: HTMLElement, paymentMethods: PaymentMethod[]): void;
}

/**
 * Publishes the refactored render entry point under `window.mpSuperTokenRenderSavedMethods`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishRenderSavedMethods(seam: SuperTokenRenderSavedMethods): void {
  window.mpSuperTokenRenderSavedMethods = seam.renderSavedMethods;
}

/**
 * The account-payment-methods load entry point the legacy `getAccountPaymentMethods`
 * (super-token-authenticator.js) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the amount and buyer e-mail, and the use case
 * keeps the handle/token stored on the still-legacy authenticator so submit-time consumers
 * see the same state.
 */
export interface SuperTokenAccountPaymentMethods {
  getAccountPaymentMethods(amount: string | null, buyerEmail: string): Promise<PaymentMethod[] | null>;
}

/**
 * Publishes the refactored load entry point under `window.mpSuperTokenGetAccountPaymentMethods`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishAccountPaymentMethods(seam: SuperTokenAccountPaymentMethods): void {
  window.mpSuperTokenGetAccountPaymentMethods = seam.getAccountPaymentMethods;
}

/**
 * The submit-time authorize entry point the legacy `authorizePayment` (super-token-
 * authenticator.js) delegates to. The bundle injects the use case + session behind it; the
 * legacy shell only forwards the pseudotoken. Unlike the fail-safe seams, this one throws a
 * typed error code on failure — the shell must let it propagate (never swallow-and-retry, or
 * the SDK authorize would run twice).
 */
export interface SuperTokenAuthorizePayment {
  authorizePayment(pseudotoken: string): Promise<void>;
}

/**
 * Publishes the refactored authorize entry point under `window.mpSuperTokenAuthorizePayment`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishAuthorizePayment(seam: SuperTokenAuthorizePayment): void {
  window.mpSuperTokenAuthorizePayment = seam.authorizePayment;
}

/**
 * The fetch+render load entry point the legacy `fetchAndRenderSuperTokenPaymentMethods`
 * (super-token-trigger-handler.js) delegates to. The bundle injects the use case + session
 * behind it; the shell forwards no arguments — the session reads the amount, buyer e-mail and
 * load generation off the still-legacy trigger handler. Fail-safe like the load seam: the shell
 * falls back to the inline flow on any throw.
 */
export interface SuperTokenFetchAndRender {
  fetchAndRenderPaymentMethods(): Promise<void>;
}

/**
 * Publishes the refactored fetch+render entry point under
 * `window.mpSuperTokenFetchAndRenderPaymentMethods`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
export function publishFetchAndRender(seam: SuperTokenFetchAndRender): void {
  window.mpSuperTokenFetchAndRenderPaymentMethods = seam.fetchAndRenderPaymentMethods;
}

/**
 * The load-orchestration entry point the legacy `loadSuperToken` (super-token-trigger-handler.js)
 * delegates to — the entry Classic `event-handler.js` and Blocks `cart-update.helper.js` call on
 * every amount refresh. The bundle injects the use case + session behind it; the shell forwards
 * the amount. Fail-safe like the fetch+render seam: the shell falls back to the inline flow on
 * any throw.
 */
export interface SuperTokenLoad {
  loadSuperToken(currentAmount: string): Promise<void>;
}

/**
 * Publishes the refactored load-orchestration entry point under `window.mpSuperTokenLoadSuperToken`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishLoad(seam: SuperTokenLoad): void {
  window.mpSuperTokenLoadSuperToken = seam.loadSuperToken;
}

/**
 * The cancel-load entry point the legacy `cancelLoad` (super-token-trigger-handler.js) delegates
 * to — called by Classic `event-handler.js` when the buyer leaves the custom method mid-fetch.
 * The bundle injects the use case + session behind it; the shell forwards nothing (the session
 * reaches the load state off the still-legacy trigger handler). Fail-safe like the load seam: the
 * shell falls back to the inline flow on any throw.
 */
export interface SuperTokenCancelLoad {
  cancelLoad(): void;
}

/**
 * Publishes the refactored cancel-load entry point under `window.mpSuperTokenCancelLoad`. Kept
 * separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishCancelLoad(seam: SuperTokenCancelLoad): void {
  window.mpSuperTokenCancelLoad = seam.cancelLoad;
}

/**
 * The reset-custom-checkout entry point the legacy `resetCustomCheckout`
 * (super-token-trigger-handler.js) delegates to — reached both by the error reset
 * (`resetSuperTokenOnError`) and by the e-mail-change callback. The bundle injects the use case +
 * session behind it; the shell forwards the `shouldClearCache` flag. Fail-safe like the load seam:
 * the shell falls back to the inline flow on any throw.
 */
export interface SuperTokenResetCustomCheckout {
  resetCustomCheckout(shouldClearCache: boolean): void;
}

/**
 * Publishes the refactored reset-custom-checkout entry point under
 * `window.mpSuperTokenResetCustomCheckout`. Kept separate from `publish` so the instance mirror
 * stays exactly the five legacy names.
 */
export function publishResetCustomCheckout(seam: SuperTokenResetCustomCheckout): void {
  window.mpSuperTokenResetCustomCheckout = seam.resetCustomCheckout;
}

/**
 * The error-handling entry point the legacy `finalizeResetTail` (and eventually all callers of
 * `MPSuperTokenErrorHandler.handleError`) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the exception. Returns the normalised error code.
 * Fail-safe: the shell falls back to the legacy error-handler instance on any throw.
 */
export interface SuperTokenHandleError {
  handleError(exception: unknown): string;
}

/**
 * Publishes the refactored error-handling entry point under `window.mpSuperTokenHandleError`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishHandleError(seam: SuperTokenHandleError): void {
  window.mpSuperTokenHandleError = seam.handleError;
}

/**
 * The preloaded-method restore entry point the legacy `finalizeResetTail`
 * (super-token-trigger-handler.js) delegates to — the async body of `restorePreloadedPaymentMethod`
 * (trigger-handler.js:166-213). The bundle injects the use case + session behind it; the shell
 * awaits it inside the existing try/catch so any throw is handled by the caller's catch.
 * Unlike the fail-safe seams, the shell does not add a second try/catch layer — if the seam
 * throws, the existing catch in `finalizeResetTail` records the metric, which is the correct
 * behaviour.
 */
export interface SuperTokenRestorePreloaded {
  restorePreloadedPaymentMethod(): Promise<void>;
}

/**
 * Publishes the refactored restore entry point under
 * `window.mpSuperTokenRestorePreloadedPaymentMethod`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
export function publishRestorePreloaded(seam: SuperTokenRestorePreloaded): void {
  window.mpSuperTokenRestorePreloadedPaymentMethod = seam.restorePreloadedPaymentMethod;
}

/**
 * The e-mail listener registration entry point the legacy `ensureEmailListenerRegistered`
 * (super-token-trigger-handler.js) delegates to. The bundle injects the use case + session
 * behind it; the shell delegates on every call (the use case owns the once-guard internally).
 * Fail-safe: the shell falls back to the inline flow on any throw.
 */
export interface SuperTokenEnsureEmailListener {
  ensureEmailListenerRegistered(): void;
}

/**
 * Publishes the refactored e-mail listener registration under
 * `window.mpSuperTokenEnsureEmailListenerRegistered`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
export function publishEnsureEmailListener(seam: SuperTokenEnsureEmailListener): void {
  window.mpSuperTokenEnsureEmailListenerRegistered = seam.ensureEmailListenerRegistered;
}

/**
 * The checkout pre-validation resolver the still-legacy Classic `event-handler.js` delegates to.
 * The bundle injects the ported resolver behind it; the consumer forwards the parsed route response
 * and reads `verdict.action`. Replaces the legacy `shared/validators/checkout-validation-resolver.js`
 * global so the whole v2/v2.1/shared tree can be deleted.
 */
export interface SuperTokenCheckoutValidationResolver {
  resolveCheckoutValidation(response: unknown): CheckoutValidationVerdict;
}

/**
 * Publishes the ported checkout pre-validation resolver under `window.mpResolveCheckoutValidation`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishCheckoutValidationResolver(seam: SuperTokenCheckoutValidationResolver): void {
  window.mpResolveCheckoutValidation = seam.resolveCheckoutValidation;
}

/**
 * The approved WooCommerce validation gate. No in-tree caller reads it today, but old plugin
 * versions reach it through the shared CDN bundle, so it is published for compatibility. Replaces
 * the legacy `v2.1/validators/checkout-form-validator.js` global so the v2/v2.1 tree can be deleted.
 */
export interface SuperTokenWooCommerceValidationErrors {
  hasWooCommerceValidationErrors(): boolean;
}

/**
 * Publishes the ported validation gate under `window.hasWooCommerceValidationErrors`. Kept separate
 * from `publish` so the instance mirror stays exactly the five legacy names.
 */
export function publishWooCommerceValidationErrors(seam: SuperTokenWooCommerceValidationErrors): void {
  window.hasWooCommerceValidationErrors = seam.hasWooCommerceValidationErrors;
}

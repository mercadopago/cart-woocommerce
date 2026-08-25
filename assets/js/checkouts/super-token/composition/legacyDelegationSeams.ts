/**
 * Publishes the refactored orchestration entry points to window.mpSuperToken* (through the
 * transitional bridge) so the still-legacy JS classes delegate every major orchestration step to
 * the TS use cases. Each seam is one shared, stateless use case wrapped over a Legacy*Session that
 * reads the live legacy state from window.* at call time — keeping window.* at the composition edge.
 * The legacy classes remain state-holders with a delegate+fallback casca; the fallback keeps stores
 * safe if a seam fails. These sessions shrink as the primitives are ported into the tree.
 */
import { FinalizeSuperTokenPayment } from '@super-token/useCases/FinalizeSuperTokenPayment';
import { SelectSavedPaymentMethod } from '@super-token/useCases/SelectSavedPaymentMethod';
import type { SelectionMetrics } from '@super-token/useCases/SelectSavedPaymentMethod';
import { ResetFlow } from '@super-token/useCases/ResetFlow';
import { GetAccountPaymentMethods } from '@super-token/useCases/GetAccountPaymentMethods';
import type { AuthenticatorMetrics } from '@super-token/useCases/GetAccountPaymentMethods';
import { AuthorizePayment } from '@super-token/useCases/AuthorizePayment';
import type { AuthorizePaymentMetrics } from '@super-token/useCases/AuthorizePayment';
import { FetchAndRenderPaymentMethods } from '@super-token/useCases/FetchAndRenderPaymentMethods';
import {
  LegacyTriggerSession,
  createFetchAndRenderMetrics,
} from '@super-token/adapters/session/LegacyTriggerSession';
import type {
  LegacyLoadTriggerHandler,
  LegacyLoadMetrics,
} from '@super-token/adapters/session/LegacyTriggerSession';
import { LoadSuperToken } from '@super-token/useCases/LoadSuperToken';
import {
  LegacyLoadOrchestrationSession,
  createLoadSuperTokenMetrics,
} from '@super-token/adapters/session/LegacyLoadOrchestrationSession';
import type {
  LegacyLoadOrchestrationTriggerHandler,
  LegacyLoadOrchestrationMetrics,
} from '@super-token/adapters/session/LegacyLoadOrchestrationSession';
import { CancelLoad } from '@super-token/useCases/CancelLoad';
import { ResetCustomCheckout } from '@super-token/useCases/ResetCustomCheckout';
import { LegacyResetCustomCheckoutSession } from '@super-token/adapters/session/LegacyResetCustomCheckoutSession';
import type { LegacyResetCustomCheckoutTriggerHandler } from '@super-token/adapters/session/LegacyResetCustomCheckoutSession';
import { HandleError } from '@super-token/useCases/HandleError';
import { LegacyErrorHandlerSession } from '@super-token/adapters/session/LegacyErrorHandlerSession';
import type {
  LegacyErrorMetrics,
  LegacyErrorPaymentMethods,
} from '@super-token/adapters/session/LegacyErrorHandlerSession';
import { RestorePreloadedPaymentMethod } from '@super-token/useCases/RestorePreloadedPaymentMethod';
import { LegacyRestoreSession } from '@super-token/adapters/session/LegacyRestoreSession';
import type {
  LegacyRestoreTriggerHandler,
  LegacyRestoreMetrics,
} from '@super-token/adapters/session/LegacyRestoreSession';
import { EnsureEmailListenerRegistered } from '@super-token/useCases/EnsureEmailListenerRegistered';
import { LegacyEmailListenerSession } from '@super-token/adapters/session/LegacyEmailListenerSession';
import type { LegacyEmailListenerTriggerHandler } from '@super-token/adapters/session/LegacyEmailListenerSession';
import { ClassicCheckout } from '@super-token/adapters/checkout/ClassicCheckout';
import type { ClassicCheckoutDeps } from '@super-token/adapters/checkout/ClassicCheckout';
import { BlocksCheckout } from '@super-token/adapters/checkout/BlocksCheckout';
import type { BlocksCheckoutDeps, BlocksResponse } from '@super-token/adapters/checkout/BlocksCheckout';
import { LegacySelectionSession } from '@super-token/adapters/session/LegacySelectionSession';
import type { LegacyPaymentMethodsController } from '@super-token/adapters/session/LegacySelectionSession';
import { LegacyResetSession } from '@super-token/adapters/session/LegacyResetSession';
import type { LegacyResetTriggerHandler } from '@super-token/adapters/session/LegacyResetSession';
import { LegacyAuthenticatorSession } from '@super-token/adapters/session/LegacyAuthenticatorSession';
import type {
  LegacyAuthenticator,
  LegacyAccountPaymentMethodsSource,
} from '@super-token/adapters/session/LegacyAuthenticatorSession';
import type { PaymentMethod } from '@super-token/types/external-globals';
import { MPSuperTokenErrorCodes } from '@super-token/core/checkoutSession/ErrorClassification';
import { resolveCheckoutValidation } from '@super-token/adapters/validation/checkoutValidationResolver';
import type {
  CheckoutValidationVerdict,
  CheckoutValidationMetrics,
} from '@super-token/adapters/validation/checkoutValidationResolver';
import { hasWooCommerceValidationErrors } from '@super-token/adapters/validation/wooCommerceValidationErrors';
import type { WooCommerceValidationMetrics } from '@super-token/adapters/validation/wooCommerceValidationErrors';
import * as globalBridge from '@super-token/adapters/legacy/globalBridge';

// ─── Finalization (Phase 1) ──────────────────────────────────────────────────

// One shared instance: the use case is stateless, so a single copy serves every submit.
const finalizeUseCase = new FinalizeSuperTokenPayment();

function finalizeClassic(input: Omit<ClassicCheckoutDeps, 'finalize'>): Promise<void> {
  return new ClassicCheckout({ finalize: finalizeUseCase, ...input }).finalize();
}

function finalizeBlocks(input: Omit<BlocksCheckoutDeps, 'finalize'>): Promise<BlocksResponse> {
  return new BlocksCheckout({ finalize: finalizeUseCase, ...input }).finalize();
}

// ─── Checkout validation helpers ─────────────────────────────────────────────

// Publish the ported validators for the still-legacy Classic event-handler.js
// (mpResolveCheckoutValidation) and any old-plugin consumer of the approved validation gate
// (hasWooCommerceValidationErrors). Synchronous, both modes, so the globals exist before submit
// reads them (replaces the legacy shared/v2.1 validators). Metrics are read from the live
// window.mpSuperTokenMetrics singleton at call time — keeping window.* at the composition edge.
function resolveCheckoutValidationSeam(response: unknown): CheckoutValidationVerdict {
  const metrics = window.mpSuperTokenMetrics as unknown as CheckoutValidationMetrics | undefined;
  return resolveCheckoutValidation(response, metrics);
}

function hasWooCommerceValidationErrorsSeam(): boolean {
  const metrics = window.mpSuperTokenMetrics as unknown as WooCommerceValidationMetrics | undefined;
  return hasWooCommerceValidationErrors(metrics);
}

// ─── Selection (Phase 3) ─────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const selectUseCase = new SelectSavedPaymentMethod();

// Hybrid: the legacy CDN bundle still owns the controller/metrics instances and the DOM
// primitives. This wraps the legacy instance as the selection session, so the use case
// drives the *order* while the primitives keep coming from the legacy controller — the
// scaffold that shrinks as the primitives are ported into the tree.
function selectSavedPaymentMethod(
  paymentMethod: PaymentMethod,
  paymentMethodElement: HTMLElement,
): Promise<void> {
  const controller = window.mpSuperTokenPaymentMethods as unknown as LegacyPaymentMethodsController;
  const metrics = window.mpSuperTokenMetrics as unknown as SelectionMetrics;
  return selectUseCase.execute({
    session: new LegacySelectionSession(controller),
    metrics,
    paymentMethod,
    paymentMethodElement,
  });
}

// ─── Reset (Phase 4) ─────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const resetFlow = new ResetFlow();

// Hybrid: the legacy trigger handler still owns the reset primitives and the controller it
// holds. This wraps it as the reset session so the use case drives the *order* and the
// preserve-selection decision, while the primitives keep coming from the legacy instance.
function resetOnError(preserveSelection: boolean): void {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyResetTriggerHandler;
  resetFlow.execute({ session: new LegacyResetSession(triggerHandler), preserveSelection });
}

// ─── Account payment methods / load (Phase 7) ────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const getAccountPaymentMethodsUseCase = new GetAccountPaymentMethods();

// Hybrid: the legacy authenticator still owns the handle/token state and the SDK primitives,
// and the legacy controller still owns the account fetch. This wraps both as the load session
// so the use case drives the *order* and the fail-safe gates while the state stays legacy —
// the scaffold that shrinks as the primitives are ported into the tree.
function getAccountPaymentMethods(
  amount: string | null,
  buyerEmail: string,
): Promise<PaymentMethod[] | null> {
  const authenticator = window.mpSuperTokenAuthenticator as unknown as LegacyAuthenticator;
  const paymentMethods = window.mpSuperTokenPaymentMethods as unknown as LegacyAccountPaymentMethodsSource;
  const metrics = window.mpSuperTokenMetrics as unknown as AuthenticatorMetrics;
  return getAccountPaymentMethodsUseCase.execute({
    session: new LegacyAuthenticatorSession(authenticator, paymentMethods),
    metrics,
    amount,
    buyerEmail,
  });
}

// ─── Authorize at submit (Phase 8) ───────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const authorizePaymentUseCase = new AuthorizePayment();

// Hybrid: the legacy authenticator still owns the stored handle and the SDK authorize call.
// This wraps it as the authorize session so the use case drives the *order* and the error
// classification, while the primitives keep coming from the legacy instance. The paymentMethods
// arg is unused here (the load path needs it) but the session serves both roles.
function authorizePayment(pseudotoken: string): Promise<void> {
  const authenticator = window.mpSuperTokenAuthenticator as unknown as LegacyAuthenticator;
  const paymentMethods = window.mpSuperTokenPaymentMethods as unknown as LegacyAccountPaymentMethodsSource;
  const metrics = window.mpSuperTokenMetrics as unknown as AuthorizePaymentMetrics;
  return authorizePaymentUseCase.execute({
    session: new LegacyAuthenticatorSession(authenticator, paymentMethods),
    metrics,
    pseudotoken,
  });
}

// ─── Load: fetch + render (Phase 7b) ─────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const fetchAndRenderUseCase = new FetchAndRenderPaymentMethods();

// Hybrid: the legacy trigger handler still owns the load state (fetching flag, load generation,
// current amount, buyer e-mail) and the collaborators it drives — its wcEmailListener, the
// authenticator for the fetch (itself already delegating to the load seam) and the controller
// for the render. This wraps it as the fetch+render session so the use case drives the *order*
// and the e-mail gate while the state stays legacy.
function fetchAndRenderPaymentMethods(): Promise<void> {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyLoadTriggerHandler;
  const metrics = window.mpSuperTokenMetrics as unknown as LegacyLoadMetrics;
  return fetchAndRenderUseCase.execute({
    session: new LegacyTriggerSession(triggerHandler),
    metrics: createFetchAndRenderMetrics(metrics),
  });
}

// ─── Load orchestration (Phase 7c) ───────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const loadSuperTokenUseCase = new LoadSuperToken();

// Hybrid: the legacy trigger handler still owns the load state and the primitives the
// orchestration drives (format/amount/e-mail guards, reset, cache short-circuit, the e-mail
// listener registration and the fetch+render — itself already delegating to the fetch+render
// seam). This wraps it as the load session so the use case drives the *order* while the state
// stays legacy.
function loadSuperToken(currentAmount: string): Promise<void> {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyLoadOrchestrationTriggerHandler;
  const metrics = window.mpSuperTokenMetrics as unknown as LegacyLoadOrchestrationMetrics;
  return loadSuperTokenUseCase.execute({
    session: new LegacyLoadOrchestrationSession(triggerHandler),
    metrics: createLoadSuperTokenMetrics(metrics),
    currentAmount,
  });
}

// ─── Cancel load ─────────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const cancelLoadUseCase = new CancelLoad();

// Reuses the fetch+render trigger session: cancel drives the same load-state cluster (fetching
// flag, load generation) plus the controller reset, all still owned by the legacy trigger handler.
function cancelLoad(): void {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyLoadTriggerHandler;
  cancelLoadUseCase.execute({ session: new LegacyTriggerSession(triggerHandler) });
}

// ─── Reset custom checkout ───────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const resetCustomCheckoutUseCase = new ResetCustomCheckout();

// Hybrid: the legacy trigger handler still owns the reset primitives (spinner, validation, card
// form remount, resetFlow, the load orchestration — itself already delegating to the load seam —
// and the async tail). This wraps it as the reset session so the use case drives the *order*
// while the state stays legacy — the scaffold that shrinks as the primitives are ported.
function resetCustomCheckout(shouldClearCache: boolean): void {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyResetCustomCheckoutTriggerHandler;
  resetCustomCheckoutUseCase.execute({
    session: new LegacyResetCustomCheckoutSession(triggerHandler),
    shouldClearCache,
  });
}

// ─── Error handling ──────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const handleErrorUseCase = new HandleError();

// Hybrid: the legacy metrics and payment-methods controller still own the side-effecting
// operations (metric dispatch, validation display, error message lookup). This wraps them as
// the error session so the use case drives the *order* and the display branch while the
// primitives keep coming from the legacy instances — the scaffold that shrinks as they are ported.
function handleError(exception: unknown): string {
  const metrics = window.mpSuperTokenMetrics as unknown as LegacyErrorMetrics;
  const controller = window.mpSuperTokenPaymentMethods as unknown as LegacyErrorPaymentMethods;
  return handleErrorUseCase.execute({
    session: new LegacyErrorHandlerSession(metrics, controller),
    exception,
  });
}

// ─── Restore preloaded payment method ────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const restorePreloadedUseCase = new RestorePreloadedPaymentMethod();

// Hybrid: the legacy trigger handler still owns `savedInstallments` and the controller it holds
// owns the preloaded-method state and DOM primitives. This wraps both as the restore session so
// the use case drives the *order* and the four early-exit branches while the primitives keep
// coming from the legacy — the scaffold that shrinks as they are ported into the tree.
function restorePreloadedPaymentMethod(): Promise<void> {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyRestoreTriggerHandler;
  const metrics = window.mpSuperTokenMetrics as unknown as LegacyRestoreMetrics;
  return restorePreloadedUseCase.execute({
    session: new LegacyRestoreSession(triggerHandler, metrics),
  });
}

// ─── E-mail listener registration ────────────────────────────────────────────

// One shared instance: the use case is stateless; the once-guard lives on the trigger handler.
const ensureEmailListenerUseCase = new EnsureEmailListenerRegistered();

// Hybrid: the legacy trigger handler still owns `isAlreadyListeningForm`, `currentAmount`,
// `wcBuyerEmail`, `isDifferentEmail`, `wcEmailListener` and `mpSuperTokenMetrics`. This wraps
// it as the email-listener session so the use case drives the *order* and the reset decision
// while the primitives keep coming from the legacy — the scaffold that shrinks as they are ported.
function ensureEmailListenerRegistered(): void {
  const triggerHandler = window.mpSuperTokenTriggerHandler as unknown as LegacyEmailListenerTriggerHandler;
  ensureEmailListenerUseCase.execute({
    session: new LegacyEmailListenerSession(triggerHandler),
  });
}

/**
 * Publish every legacy-delegation seam. Synchronous and mode-agnostic, so the globals exist
 * before any submit/select/load reads them (error codes replace errors/super-token-error-constants.js).
 */
export function publishLegacyDelegationSeams(): void {
  globalBridge.publishFinalizers({ finalizeClassic, finalizeBlocks });
  globalBridge.publishErrorCodes(MPSuperTokenErrorCodes);
  globalBridge.publishCheckoutValidationResolver({ resolveCheckoutValidation: resolveCheckoutValidationSeam });
  globalBridge.publishWooCommerceValidationErrors({ hasWooCommerceValidationErrors: hasWooCommerceValidationErrorsSeam });
  globalBridge.publishSelectors({ selectSavedPaymentMethod });
  globalBridge.publishReset({ resetOnError });
  globalBridge.publishAccountPaymentMethods({ getAccountPaymentMethods });
  globalBridge.publishAuthorizePayment({ authorizePayment });
  globalBridge.publishFetchAndRender({ fetchAndRenderPaymentMethods });
  globalBridge.publishLoad({ loadSuperToken });
  globalBridge.publishCancelLoad({ cancelLoad });
  globalBridge.publishResetCustomCheckout({ resetCustomCheckout });
  globalBridge.publishHandleError({ handleError });
  globalBridge.publishRestorePreloaded({ restorePreloadedPaymentMethod });
  globalBridge.publishEnsureEmailListener({ ensureEmailListenerRegistered });
}

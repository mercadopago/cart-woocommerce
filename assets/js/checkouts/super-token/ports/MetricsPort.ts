/**
 * Port: Super Token observability, modeled on `MPSuperTokenMetrics`.
 *
 * Contract only — no implementation. Exposes the SEMANTIC metric methods the
 * domain emits. The low-level primitives (`sendMetric`, `dispatchMelidataErrorEvent`,
 * `normalizeErrorMessage`, `waitForMelidata`, `getSdkInstanceId`, `getEnvironment`)
 * are adapter internals and are NOT part of this contract.
 *
 * Representative critical-path subset — the full method list is completed as the
 * domain migrates (TASK-006). The concrete adapter lands in TASK-005.
 */
export interface MetricsPort {
  canUseSuperToken(canUse: boolean, error?: unknown): void;
  errorToAuthorizePayment(error: unknown): void;
  errorToGetAccountPaymentMethods(error: unknown): void;
  errorToUpdateSecurityCode(error: unknown, paymentMethod: unknown): void;
  updateSecurityCodeSuccess(): void;
  registerSelectPaymentMethod(paymentMethodType: string): void;
  renderCreditsContract(success: boolean, error?: unknown): void;

  // Initialization resilience (TASK-010). Metric names are preserved 1:1 from the
  // legacy `mp-super-token.js`; the caller (`InitializationResilience`) stays
  // sink-agnostic and lets the adapter decide the transport.
  superTokenSdkLoaded(): void;
  reportInitSource(source: string, elapsedMs: number): void;
  superTokenInitializationSuccess(dispatchedFrom: string): void;
  superTokenInitializationError(error: unknown, dispatchedFrom: string): void;
  superTokenClassesNotExist(missingSummary: string, dispatchedFrom: string): void;
  superTokenTriggerHandlerNotListening(dispatchedFrom: string): void;
  mpSdkInstanceNotExists(dispatchedFrom: string): void;
}

/**
 * Ported `MPSuperTokenTriggerHandler` (v2.1/entities/super-token-trigger-handler.js) — the
 * published `window.mpSuperTokenTriggerHandler` instance that drives the whole Super Token load
 * lifecycle: it owns the load *state* (the current amount, the buyer e-mail, the fetching flag,
 * the load generation, the once-guards and the saved installments) and the *primitives* the
 * checkout reads (`getBuyerEmail`, `amountHasChanged`, `customCheckoutIsActive`,
 * `isSuperTokenPaymentMethodsLoaded`, the last-exception accessors…).
 *
 * Its orchestrations delegate to the use cases that already own their order and gates, driven
 * through the `Legacy*Session` adapters with `this` as the primitive source (the same pattern as
 * the ported authenticator): `loadSuperToken` → `LoadSuperToken`,
 * `fetchAndRenderSuperTokenPaymentMethods` → `FetchAndRenderPaymentMethods`, `cancelLoad` →
 * `CancelLoad`, `resetCustomCheckout` → `ResetCustomCheckout`, `restorePreloadedPaymentMethod` →
 * `RestorePreloadedPaymentMethod`, `resetSuperTokenOnError` → `ResetFlow`,
 * `ensureEmailListenerRegistered` → `EnsureEmailListenerRegistered`. The legacy seam checks
 * (`typeof window.mpSuperToken* === 'function'`) collapse away: the entity *is* the
 * implementation, so it calls the use cases (and its own methods) directly.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity
 * with the legacy class. At the flip the bundle bootstrap constructs it with the ported TS
 * authenticator/e-mail-listener/payment-methods/error-handler/metrics collaborators and the
 * localized `current_user_email`, then publishes it through `globalBridge.publish`. The raw ad-hoc
 * `sendMetric` calls (the restore-error metric, the stale-cache dispatch) are kept verbatim;
 * swapping them for the metrics adapter's semantic methods is a flip-time change (the active
 * metrics instance today is still the legacy one, which has no semantic methods).
 */
import { LoadSuperToken } from '@super-token/useCases/LoadSuperToken';
import { FetchAndRenderPaymentMethods } from '@super-token/useCases/FetchAndRenderPaymentMethods';
import { CancelLoad } from '@super-token/useCases/CancelLoad';
import { ResetCustomCheckout } from '@super-token/useCases/ResetCustomCheckout';
import { RestorePreloadedPaymentMethod } from '@super-token/useCases/RestorePreloadedPaymentMethod';
import { ResetFlow } from '@super-token/useCases/ResetFlow';
import { EnsureEmailListenerRegistered } from '@super-token/useCases/EnsureEmailListenerRegistered';
import {
  LegacyTriggerSession,
  createFetchAndRenderMetrics,
} from '@super-token/adapters/session/LegacyTriggerSession';
import {
  LegacyLoadOrchestrationSession,
  createLoadSuperTokenMetrics,
} from '@super-token/adapters/session/LegacyLoadOrchestrationSession';
import { LegacyResetCustomCheckoutSession } from '@super-token/adapters/session/LegacyResetCustomCheckoutSession';
import { LegacyRestoreSession } from '@super-token/adapters/session/LegacyRestoreSession';
import { LegacyResetSession } from '@super-token/adapters/session/LegacyResetSession';
import { LegacyEmailListenerSession } from '@super-token/adapters/session/LegacyEmailListenerSession';
import type { PaymentMethod } from '@super-token/types/external-globals';

/** The subset of the ported authenticator the trigger handler reads and forwards to the sessions. */
export interface TriggerHandlerAuthenticator {
  getAmountUsed(): string | null;
  getEmailUsed(): string | null;
  formatAmount(amount: string | null): string | null;
  reset(): void;
  setSuperTokenValidation(isValid: boolean): void;
  getAccountPaymentMethods(amount: string | null, buyerEmail: string): Promise<PaymentMethod[] | null>;
}

/** The subset of the ported e-mail listener the trigger handler reads and forwards to the sessions. */
export interface TriggerHandlerEmailListener {
  getEmail(): string | null | undefined;
  isValid(email: string): boolean;
  onEmailChange(callback: (email: string, isValid: boolean) => Promise<void>): void;
  setupEmailChangeHandlers(): void;
}

/** The subset of the payment-methods controller the trigger handler forwards to the sessions. */
export interface TriggerHandlerPaymentMethods {
  SUPER_TOKEN_STYLES: { PAYMENT_METHOD_LIST: string };
  reset(): void;
  renderAccountPaymentMethods(paymentMethods: PaymentMethod[], amount: string | null): Promise<void> | void;
  getStoredPaymentMethods(): PaymentMethod[];
  hasStoredPaymentMethods(): boolean;
  hideSuperTokenError(): void;
  unmountCardForm(): void;
  mountCardForm(): void;
  getSelectedPreloadedPaymentMethod(): PaymentMethod | null;
  hasCheckoutError(): boolean;
  selectLastPaymentMethodChoosen(): void;
  selectPreloadedPaymentMethod(): Promise<void>;
  storeSelectedPreloadedPaymentMethod(method: PaymentMethod | null): void;
  getActivePaymentMethod(): PaymentMethod | null;
  getPaymentMethodElementFromDOM(method: PaymentMethod): HTMLElement | null;
  showPaymentMethodDetails(element: HTMLElement): void;
  paymentMethodIdentifier(method: PaymentMethod): string;
  getLastPaymentMethodChoosen(): PaymentMethod | null;
  deselectAllPaymentMethods(): void;
  hideAllPaymentMethodDetails(): void;
  unmountActiveSecurityCodeInstance(): void;
  clearActivePaymentMethod(): void;
}

/** The one method of the ported error handler the deferred last-exception tail calls. */
export interface TriggerHandlerErrorHandler {
  handleError(exception: unknown): string;
}

/** The subset of the metrics adapter the trigger handler emits through directly. */
export interface TriggerHandlerMetrics {
  sendMetric(name: string, value: string, message: string): void;
  sendStaleCacheMetrics(): Promise<void>;
}

const RESTORE_ERROR_METRIC = 'super_token_restore_error';
const RESTORE_ERROR_MESSAGE = 'mp_super_token_restore_error';

export class SuperTokenTriggerHandler {
  private readonly CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
  private readonly CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
  private readonly LOADING_ANIMATION_FINISH_DELAY = 500;
  private readonly AVOID_INSTANT_REMOVAL_LOADER_DELAY = 500;

  // State. `currentAmount` is the formatted amount; formatAmount returns null for an empty/NaN
  // input (parity with the legacy) and that null flows through to the SDK exactly as before.
  wcBuyerEmail: string | null = null;
  currentAmount: string | null = '';
  isAlreadyListeningForm = false;
  lastException: unknown = null;
  isFetchingPaymentMethods = false;
  customHandlerMissingReportedOnReset = false;
  loadGeneration = 0;
  cacheMetricsDispatched = false;
  savedInstallments: string | null = null;

  private readonly loadSuperTokenUseCase = new LoadSuperToken();
  private readonly fetchAndRenderUseCase = new FetchAndRenderPaymentMethods();
  private readonly cancelLoadUseCase = new CancelLoad();
  private readonly resetCustomCheckoutUseCase = new ResetCustomCheckout();
  private readonly restorePreloadedUseCase = new RestorePreloadedPaymentMethod();
  private readonly resetFlowUseCase = new ResetFlow();
  private readonly ensureEmailListenerUseCase = new EnsureEmailListenerRegistered();

  constructor(
    readonly mpSuperTokenAuthenticator: TriggerHandlerAuthenticator,
    readonly wcEmailListener: TriggerHandlerEmailListener,
    readonly mpSuperTokenPaymentMethods: TriggerHandlerPaymentMethods,
    readonly mpSuperTokenErrorHandler: TriggerHandlerErrorHandler,
    readonly mpSuperTokenMetrics: TriggerHandlerMetrics,
    private readonly currentUserEmail: string,
  ) {}

  hasLastException(): boolean {
    return !!this.getLastException();
  }

  getLastException(): unknown {
    return this.lastException;
  }

  setLastException(exception: unknown): void {
    this.lastException = exception;
  }

  getBuyerEmail(): string | null | undefined {
    this.wcBuyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.currentUserEmail;

    return this.wcBuyerEmail?.trim();
  }

  amountHasChanged(): boolean {
    const currentAmount = this.currentAmount;
    const amountUsed = this.mpSuperTokenAuthenticator.getAmountUsed();

    return currentAmount != null && amountUsed != null && currentAmount !== amountUsed;
  }

  emailHasChanged(): boolean {
    const buyerEmail = this.getBuyerEmail();
    const emailUsed = this.mpSuperTokenAuthenticator.getEmailUsed();

    return buyerEmail != null && emailUsed != null && buyerEmail !== emailUsed;
  }

  isDifferentEmail(newEmail: string): boolean {
    return this.wcBuyerEmail != newEmail;
  }

  getCustomCheckoutRadioElement(): HTMLElement | null {
    return (
      document.querySelector<HTMLElement>(this.CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR) ||
      document.querySelector<HTMLElement>(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR)
    );
  }

  isClassicCheckout(): boolean {
    return !!document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
  }

  customCheckoutIsEnable(): boolean {
    return !!this.getCustomCheckoutRadioElement();
  }

  customCheckoutIsActive(): boolean | undefined {
    return (this.getCustomCheckoutRadioElement() as HTMLInputElement | null)?.checked;
  }

  resetFlow(): void {
    this.mpSuperTokenAuthenticator.reset();
    this.mpSuperTokenPaymentMethods.reset();
  }

  resetCustomCheckout(shouldClearCache = true): void {
    this.resetCustomCheckoutUseCase.execute({
      session: new LegacyResetCustomCheckoutSession(this),
      shouldClearCache,
    });
  }

  finalizeResetTail(): void {
    setTimeout(async () => {
      window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();
      window.mpCustomCheckoutHandler?.eventHandler?.hideCheckoutClassicLoader();

      try {
        await this.restorePreloadedPaymentMethod();
      } catch (error) {
        this.mpSuperTokenMetrics.sendMetric(
          RESTORE_ERROR_METRIC,
          (error as { message?: string })?.message || 'unknown',
          RESTORE_ERROR_MESSAGE,
        );
      }

      const lastException = this.getLastException();
      if (lastException) {
        setTimeout(() => {
          this.mpSuperTokenErrorHandler.handleError(lastException);
          this.setLastException(null);
        }, this.LOADING_ANIMATION_FINISH_DELAY);
      }
    }, this.AVOID_INSTANT_REMOVAL_LOADER_DELAY);
  }

  restorePreloadedPaymentMethod(): Promise<void> {
    return this.restorePreloadedUseCase.execute({
      session: new LegacyRestoreSession(this, this.mpSuperTokenMetrics),
    });
  }

  resetSuperTokenOnError(preserveSelection = false): void {
    this.resetFlowUseCase.execute({
      session: new LegacyResetSession(this),
      preserveSelection,
    });
  }

  isSuperTokenPaymentMethodsLoaded(): boolean {
    return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods();
  }

  cancelLoad(): void {
    this.cancelLoadUseCase.execute({ session: new LegacyTriggerSession(this) });
  }

  fetchAndRenderSuperTokenPaymentMethods(): Promise<void> {
    return this.fetchAndRenderUseCase.execute({
      session: new LegacyTriggerSession(this),
      metrics: createFetchAndRenderMetrics(this.mpSuperTokenMetrics),
    });
  }

  ensureEmailListenerRegistered(): void {
    this.ensureEmailListenerUseCase.execute({
      session: new LegacyEmailListenerSession(this),
    });
  }

  dispatchStaleCacheMetricsOnce(): void {
    if (this.cacheMetricsDispatched) return;

    this.cacheMetricsDispatched = true;
    this.mpSuperTokenMetrics.sendStaleCacheMetrics().catch(() => {}); // fire-and-forget: must not delay checkout flow
  }

  loadSuperToken(currentAmount: string | null): Promise<void> {
    return this.loadSuperTokenUseCase.execute({
      session: new LegacyLoadOrchestrationSession(this),
      metrics: createLoadSuperTokenMetrics(this.mpSuperTokenMetrics),
      currentAmount,
    });
  }
}

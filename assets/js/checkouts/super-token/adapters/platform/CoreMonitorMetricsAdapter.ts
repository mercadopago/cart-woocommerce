import type { MetricsPort } from '@super-token/ports';
import type { RawMpSdkInstance, SuperTokenBundleParams } from '@super-token/types/external-globals';
import { sendToCoreMonitor } from './coreMonitorPayload';
import type { CoreMonitorPayload } from './coreMonitorPayload';
import { MelidataAdapter } from './MelidataAdapter';
import { SUPER_TOKEN_ALLOWED_VARIANTS, SUPER_TOKEN_VARIANT_COOKIE } from '@super-token/adapters/platform/constants';
import type { RestoreErrorReason } from '@super-token/useCases/RestorePreloadedPaymentMethod';

/**
 * Platform adapter: Super Token observability (RN-2). Implements `MetricsPort`
 * (the 7 contract methods are a subset) and carries the full public surface of the
 * legacy `MPSuperTokenMetrics`, moved 1:1 — identical behavior is the invariant of
 * this refactor. Metric names, URL and payload shape are preserved exactly.
 *
 * `SuperTokenBundleParams` is injected via the constructor instead of read from
 * `window.*` here, making the adapter testable without globals and making missing
 * required fields explicit (plugin_version, site_id and cust_id are required by
 * the Core Monitor endpoint — silent empty strings would produce rejected requests).
 *
 * `sendMetric` is PUBLIC on purpose: the published `window.mpSuperTokenMetrics` is consumed by
 * plugin code that is NOT versioned with this bundle (older plugin installs load the same
 * per-variant CDN bundle and their `event-handler.js`/`custom.block.js` call
 * `window.mpSuperTokenMetrics.sendMetric(...)` directly), so the instance must stay a superset of
 * the legacy surface. It is not part of `MetricsPort` (the semantic contract the new domain
 * depends on) — the tree's own call sites use the named semantic methods below. `getSdkInstanceId`
 * is PUBLIC because the flipped `SuperTokenPaymentMethods` reads it through its metrics interface;
 * the other low-level primitives (`getEnvironment`, `normalizeErrorMessage`) stay private. The
 * `MelidataAdapter` is built internally with `sendMetric` bound so the two stay decoupled without a
 * circular dependency.
 *
 * SEC note (PSW-4279): metric values must never carry sensitive tokens.
 * `authorized_pseudotoken` reports the non-sensitive boolean `'true'` (the pseudotoken
 * itself is not transmitted); `error_to_update_security_code` reports the payment-method
 * `id`, not its token — mirroring `error_to_mount_cvv_field`.
 */
export class CoreMonitorMetricsAdapter implements MetricsPort {
  private readonly PLATFORM_NAME = 'woocommerce';
  private readonly CUSTOM_CHECKOUT_STEPS = {
    LOAD_SUPER_TOKEN: 'load_super_token',
    SELECT_PAYMENT_METHOD: 'select_payment_method',
    POST_SUBMIT: 'post_submit',
  };

  private readonly PLUGIN_VERSION: string;
  private readonly PLATFORM_VERSION: string;
  private readonly SITE_ID: string;
  private readonly CUST_ID: string;
  private readonly LOCATION: string;
  private readonly PLUGIN_JS_BASE_URL: string | undefined;
  private readonly SUPER_TOKEN_JS_VERSION: string | null;

  private readonly resolveSdk: () => RawMpSdkInstance | undefined;
  private readonly melidata: MelidataAdapter;

  constructor(
    sdk: RawMpSdkInstance | undefined | (() => RawMpSdkInstance | undefined),
    superTokenJsVersion: string | null,
    params: SuperTokenBundleParams,
  ) {
    // Resolve the SDK lazily: bootstrap may run before `mpSdkInstance` exists (delayed-SDK
    // path the readiness watcher supports), so a value captured now could stay undefined.
    this.resolveSdk = typeof sdk === 'function' ? sdk : () => sdk;
    this.SUPER_TOKEN_JS_VERSION = superTokenJsVersion;
    this.PLUGIN_VERSION = params.plugin_version;
    this.PLATFORM_VERSION = params.platform_version;
    this.SITE_ID = params.site_id;
    this.CUST_ID = params.cust_id;
    this.LOCATION = params.location;
    this.PLUGIN_JS_BASE_URL = params.plugin_js_base_url;

    this.melidata = new MelidataAdapter((metricName, value, message) =>
      this.sendMetric(metricName, value, message),
    );
  }

  getSdkInstanceId(): string {
    try {
      return this.resolveSdk()?.getSDKInstanceId() || 'Unknown';
    } catch {
      return 'Unknown';
    }
  }

  private getEnvironment(): string {
    return 'prod';
  }

  // Reads the A/B experiment the loader set on the `mp_st_variant` cookie, so every metric
  // carries which variant the buyer saw. Preserved 1:1 from the legacy `getAbVariant`:
  // anything other than v2/v2.1 (including a missing cookie) reports 'unknown'.
  private getAbVariant(): string {
    const match =
      typeof document !== 'undefined'
        ? document.cookie.match(new RegExp('(?:^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)'))
        : null;
    // decodeURIComponent throws on a malformed cookie (e.g. a stray '%'); this runs inside
    // sendMetric during checkout orchestration, so a bad cookie must not abort it. Guard like
    // the legacy getAbVariant did and fall back to 'unknown'.
    let variant = 'unknown';
    if (match) {
      try {
        variant = decodeURIComponent(match[1]);
      } catch {
        variant = 'unknown';
      }
    }
    return SUPER_TOKEN_ALLOWED_VARIANTS[variant] === true ? variant : 'unknown';
  }

  private buildPayload(value: unknown, message: string, errorCode: string | null = null): CoreMonitorPayload {
    const details: CoreMonitorPayload['details'] = {
      site_id: this.SITE_ID,
      environment: this.getEnvironment(),
      sdk_instance_id: this.getSdkInstanceId(),
      cust_id: this.CUST_ID,
      js_version: this.SUPER_TOKEN_JS_VERSION,
      ab_variant: this.getAbVariant(),
    };

    if (errorCode) {
      details.event = `${errorCode}`;
    }

    return {
      value: `${value}`,
      message,
      plugin_version: this.PLUGIN_VERSION,
      platform: {
        name: this.PLATFORM_NAME,
        uri: window.location.origin,
        version: this.PLATFORM_VERSION,
        url: this.LOCATION,
      },
      details,
    };
  }

  sendMetric(metricName: string, value: unknown, message: string, errorCode: string | null = null): void {
    sendToCoreMonitor(metricName, this.buildPayload(value, message, errorCode));
  }

  private normalizeErrorMessage(error: unknown): string {
    if (!error) return 'Unknown error';

    const errorMessage =
      (error as { message?: string })?.message ||
      (typeof error === 'string' ? error : JSON.stringify(error));
    const normalizedErrorMessage = errorMessage?.includes('email')
      ? 'invalid_email_address_provided'
      : errorMessage;

    return normalizedErrorMessage || 'Unknown error';
  }

  private errorCodeOf(error: unknown): string {
    return (error as { errorCode?: string })?.errorCode || 'unknown';
  }

  /** Send an error metric + dispatch a melidata event. Covers ~20 methods. */
  private errorWith(metricName: string, step: string, error: unknown): void {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, step);
    this.sendMetric(metricName, 'true', errorMessage, this.errorCodeOf(error));
  }

  /** Send a boolean success metric (no melidata event). Covers update-security-code steps. */
  private successBoolean(metricName: string): void {
    this.sendMetric(metricName, 'true', '');
  }

  // ─── MetricsPort ────────────────────────────────────────────────────────────

  canUseSuperToken(canUseSuperToken: boolean, error: unknown = null): void {
    const errorMessage = canUseSuperToken ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('can_use_super_token', canUseSuperToken, errorMessage);
  }

  errorToAuthorizePayment(error: unknown): void {
    this.errorWith('error_to_authorize_payment', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT, error);
  }

  errorToGetAccountPaymentMethods(error: unknown): void {
    this.errorWith('error_to_get_account_payment_methods', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }

  errorToUpdateSecurityCode(error: unknown, paymentMethod: { id: string } | null): void {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_update_security_code', paymentMethod?.id || 'unknown', errorMessage, this.errorCodeOf(error));
  }

  updateSecurityCodeSuccess(): void {
    this.successBoolean('update_security_code_success');
  }

  registerSelectPaymentMethod(paymentMethodType: string): void {
    this.sendMetric('select_payment_method', `super_token_${paymentMethodType}`, '');
  }

  renderCreditsContract(success: boolean, error: unknown = null): void {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    if (!success) {
      this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    }
    this.sendMetric('render_credits_contract', success, errorMessage);
  }

  // ─── Full MPSuperTokenMetrics surface (1:1) ──────────────────────────────────
  // When the payment-methods controller is ported, its ad-hoc `sendMetric(...)` call sites
  // (e.g. `super_token_preloaded_method_not_found` in `selectPreloadedPaymentMethod`) should be
  // promoted to named semantic methods below — the tree's own callers speak the domain language,
  // while `sendMetric` stays public only as the legacy compatibility surface.

  errorToGetSimplifiedAuth(error: unknown): void {
    this.errorWith('error_to_get_simplified_auth', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }

  errorToGetFastPaymentToken(error: unknown): void {
    this.errorWith('error_to_get_fast_payment_token', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }

  errorToBuildAuthenticator(error: unknown): void {
    this.errorWith('error_to_build_authenticator', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }

  errorToMountCVVField(error: unknown, paymentMethod: { id?: string } | null): void {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'unknown', errorMessage, this.errorCodeOf(error));
  }

  updateSecurityCodeGetCardIdSuccess(): void {
    this.successBoolean('update_security_code_get_card_id_success');
  }

  updateSecurityCodeCardTokenCreated(): void {
    this.successBoolean('update_security_code_card_token_created');
  }

  updateSecurityCodePseudotokenUpdated(): void {
    this.successBoolean('update_security_code_pseudotoken_updated');
  }

  errorOnSubmit(errorCode: string, error: unknown, shouldNormalizeError = true): void {
    const errorMessage = shouldNormalizeError ? this.normalizeErrorMessage(error) : (error as string);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_on_submit_super_token', errorCode, errorMessage);
  }

  registerClickOnPlaceOrderButton(): void {
    this.successBoolean('super_token_click_on_place_order_button');
  }

  errorToExcludeRecaptchaFromPreValidation(context: string, error: unknown): void {
    this.sendMetric('error_to_exclude_recaptcha_from_pre_validation', context, this.normalizeErrorMessage(error));
  }

  captchaFieldToggledOnPreValidation(action: string, fieldName: string): void {
    this.sendMetric('super_token_captcha_field_toggled_on_pre_validation', action, fieldName);
  }

  registerAuthorizedPseudotoken(authorizedPseudotokenInputExists: boolean): void {
    this.sendMetric('authorized_pseudotoken', 'true', `input_exists:${authorizedPseudotokenInputExists ? 'true' : 'false'}`);
  }

  errorToRenderAccountPaymentMethods(error: unknown): void {
    const errorMessage = this.normalizeErrorMessage(error);
    this.sendMetric('error_to_render_account_payment_methods', 'true', errorMessage, this.errorCodeOf(error));
  }

  hasEscNotExists(paymentMethodIdentifier: string): void {
    this.sendMetric('has_esc_not_exists', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', 'has_esc attribute not found in payment method');
  }

  getPaymentMethodFail(error: unknown, currentPaymentMethodIdentifier: string): void {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('get_payment_method_fail', currentPaymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', errorMessage);
  }

  getPaymentMethodLoadingTime(currentPaymentMethodIdentifier: string, durationSeconds: string): void {
    this.sendMetric('get_payment_method_loading_time', currentPaymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', `${durationSeconds}s`);
  }

  fetchPaymentMethodSuccess(paymentMethodIdentifier: string, cvvIsMandatory: boolean | null): void {
    this.sendMetric('fetch_payment_method_success', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', `cvv_is_mandatory_${cvvIsMandatory}`);
  }

  fetchPaymentMethodSkipped(paymentMethodIdentifier: string, reason: string): void {
    this.sendMetric('fetch_payment_method_skipped', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', reason);
  }

  fetchPaymentMethodTimeout(paymentMethodIdentifier: string): void {
    this.sendMetric('fetch_payment_method_timeout', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', 'Fetch payment method timed out');
  }

  isNotSimplifiedAuth(): void {
    this.successBoolean('is_not_simplified_auth');
  }

  cannotGetFastPaymentToken(): void {
    this.successBoolean('cannot_get_fast_payment_token');
  }

  installmentsFilled(paymentMethodType: string): void {
    this.sendMetric('super_token_installments_filled', true, paymentMethodType);
  }

  renderConsumerCreditsDetailsInnerHTML(success: boolean): void {
    if (!success) {
      this.melidata.dispatchMelidataErrorEvent('render_consumer_credits_details_inner_html_failed', this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    }
    this.sendMetric('render_consumer_credits_details_inner_html', success, '');
  }

  registerOpenCreditsInfoModal(linkText: string): void {
    this.sendMetric('open_credits_info_modal', 'true', linkText);
  }

  renderConsumerCreditsDueDate(success: boolean, error: unknown = null): void {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('render_consumer_credits_due_date', success, errorMessage);
  }

  renderConsumerCreditsHint(success: boolean, error: unknown = null): void {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('render_consumer_credits_hint', success, errorMessage);
  }

  errorToUpdateCreditsContract(error: unknown): void {
    this.errorWith('error_to_update_credits_contract', this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD, error);
  }

  errorToSubmitWithoutInstallmentSelected(paymentMethodType = ''): void {
    this.melidata.dispatchMelidataErrorEvent('no_installment_selected', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_submit_without_installment_selected', 'true', paymentMethodType);
  }

  // ─── Initialization resilience (TASK-010) ───────────────────────────────────
  // Metric names, messages and levels are preserved 1:1 from the legacy
  // `mp-super-token.js`. The four `checkIfSuperTokenWasInitialized` signals moved
  // from the plugin-global `sendMetric(name, message, level)` onto Core Monitor:
  // the metric name is unchanged, the legacy `level` is carried as the payload
  // event (errorCode) and the human message is preserved.

  private readonly INIT_ERROR_LEVEL = 'mp_super_token_init_error';
  private readonly INIT_SUCCESS_LEVEL = 'mp_super_token_init_success';

  superTokenSdkLoaded(): void {
    this.sendMetric('super_token_sdk_loaded', 'true', '');
  }

  reportInitSource(source: string, elapsedMs: number): void {
    this.sendMetric('super_token_init_source', source, `elapsed_ms:${elapsedMs}`);
  }

  superTokenInitializationSuccess(dispatchedFrom: string): void {
    this.sendMetric(
      'SUPER_TOKEN_INITIALIZATION_SUCCESS',
      'true',
      `Super token was initialized successfully and is listening to the form Dispatched from: ${dispatchedFrom}`,
      this.INIT_SUCCESS_LEVEL,
    );
  }

  superTokenInitializationError(error: unknown, dispatchedFrom: string): void {
    const errorMessage = (error as { message?: string })?.message ?? String(error);
    this.sendMetric(
      'SUPER_TOKEN_INITIALIZATION_ERROR',
      'true',
      `An error occurred while checking super token initialization: ${errorMessage} Dispatched from: ${dispatchedFrom}`,
      this.INIT_ERROR_LEVEL,
    );
  }

  superTokenClassesNotExist(missingSummary: string, dispatchedFrom: string): void {
    this.sendMetric(
      'SUPER_TOKEN_CLASSES_NOT_EXISTS',
      'true',
      `${missingSummary} Dispatched from: ${dispatchedFrom}`,
      this.INIT_ERROR_LEVEL,
    );
  }

  superTokenTriggerHandlerNotListening(dispatchedFrom: string): void {
    this.sendMetric(
      'SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING',
      'true',
      `Trigger handler is not listening to the form after super token initialization Dispatched from: ${dispatchedFrom}`,
      this.INIT_ERROR_LEVEL,
    );
  }

  mpSdkInstanceNotExists(dispatchedFrom: string): void {
    this.sendMetric(
      'MP_SDK_INSTANCE_NOT_EXISTS',
      'true',
      `MP SDK instance did not load within the expected time Dispatched from: ${dispatchedFrom}`,
      this.INIT_ERROR_LEVEL,
    );
  }

  // ─── Orchestration signals ──────────────────────────────────────────────────
  // Named methods for the ad-hoc `sendMetric(...)` call sites that used to live in the use
  // cases, session adapters and Blocks consumer. The metric name/value/message are preserved
  // 1:1 and now encapsulated here so callers speak the domain language and `sendMetric` stays
  // private (the single place that knows the Core Monitor strings).

  registerWithdraw(): void {
    this.sendMetric('super_token_withdraw', 'false', '');
  }

  authExpiredOnSubmit(): void {
    this.sendMetric('super_token_auth_expired_on_submit', 'true', '');
  }

  skippedNoEmail(): void {
    this.sendMetric('super_token_skipped_no_email', 'true', '');
  }

  skippedInvalidEmail(): void {
    this.sendMetric('super_token_skipped_invalid_email', 'true', '');
  }

  emailCaptured(): void {
    this.sendMetric('super_token_email_captured', 'true', '');
  }

  resetOnAmountChange(): void {
    this.sendMetric('super_token_reset_on_amount_change', 'true', '');
  }

  resetOnEmailChange(): void {
    this.sendMetric('super_token_reset_on_email_change', 'true', '');
  }

  reportRestoreError(reason: RestoreErrorReason): void {
    this.sendMetric(reason, 'true', 'mp_super_token_restore_error');
  }

  customCheckoutHandlerMissingOnInstallmentValidation(): void {
    this.sendMetric(
      'mp_custom_checkout_handler_missing',
      'installment_validation_failed',
      'mpCustomCheckoutHandler was undefined during installment validation cleanup',
    );
  }

  async sendStaleCacheMetrics(): Promise<void> {
    const SESSION_KEY = 'mp_js_cache_age_checked';
    const ONE_DAY_MS = 86400000;

    const lastChecked = parseInt(localStorage.getItem(SESSION_KEY) || '0', 10);
    if (Date.now() - lastChecked < ONE_DAY_MS) return;

    // NOTE (multi-tab): localStorage has no atomic compare-and-set. Two tabs
    // opened simultaneously that both see a stale lastChecked will both proceed,
    // producing duplicate telemetry bursts once per day. This is a pre-existing
    // behaviour ported from MPSuperTokenMetrics (legacy) — acceptable for
    // cache-age telemetry (cosmetic duplication, not a security issue). A proper
    // fix would require cross-tab coordination (BroadcastChannel lock) which is
    // out of scope for this refactor.
    localStorage.setItem(SESSION_KEY, String(Date.now()));

    // Use injected params (same object the constructor received) to stay consistent with
    // the rest of the class — avoids re-reading window.* after the constructor resolved it.
    const basePath =
      this.PLUGIN_JS_BASE_URL ||
      '/wp-content/plugins/woocommerce-mercadopago/assets/js/';
    const files = [
      'checkouts/custom/entities/card-form.min.js',
      'checkouts/custom/entities/event-handler.min.js',
      'melidata/melidata-client.min.js',
      'checkouts/super-token-loader.min.js',
    ];

    await Promise.all(
      files.map(async (file) => {
        try {
          let response = await fetch(basePath + file, { method: 'HEAD', cache: 'no-store' });
          if (response.status === 405) {
            response = await fetch(basePath + file, { method: 'GET', cache: 'no-store', headers: { Range: 'bytes=0-0' } });
          }
          if (!response.ok) return;

          const lastModified = response.headers.get('last-modified');
          const age = response.headers.get('age');
          let ageDays: number | null = null;
          if (lastModified) {
            ageDays = Math.round((Date.now() - new Date(lastModified).getTime()) / 86400000);
          } else if (age) {
            ageDays = Math.round(parseInt(age, 10) / 86400);
          }
          if (!Number.isFinite(ageDays)) return;

          const fileName = (file.split('/').pop() as string).replace('.min.js', '');
          const lastModifiedDate = lastModified ? new Date(lastModified).toISOString().slice(0, 10) : 'unknown';
          this.sendMetric('mp_js_cache_age', String(ageDays), `file : ${fileName} age_days : ${ageDays} last_modified : ${lastModifiedDate}`);
        } catch {
          // Silence errors — must not impact checkout
        }
      }),
    );
  }
}

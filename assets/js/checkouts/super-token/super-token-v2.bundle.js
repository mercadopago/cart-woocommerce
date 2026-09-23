/******/ (() => { // webpackBootstrap
/******/ 	"use strict";

;// ./assets/js/checkouts/super-token/adapters/platform/coreMonitorPayload.ts
/**
 * Shared contract for the Core Monitor POST payload.
 *
 * Both `CoreMonitorMetricsAdapter` and `VariantConfigAdapter` send metrics to
 * the same endpoint (`CORE_MONITOR_URL/{metricName}`) with the same schema.
 * Sharing the interface and the send function ensures the two callers never
 * diverge on structure or transport.
 */

const CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
/**
 * Send a metric to Core Monitor. Prefers `fetch` (used by the Super Token metrics
 * adapter which needs to wait for the response); falls back to `sendBeacon`
 * (fire-and-forget, used by the variant config adapter for A/B telemetry).
 */
function sendToCoreMonitor(metricName, payload, transport = 'fetch') {
  const url = `${CORE_MONITOR_URL}/${metricName}`;
  const body = JSON.stringify(payload);
  try {
    if (transport === 'beacon') {
      if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
        // Wrap in a Blob so the request carries application/json; a raw string
        // makes sendBeacon send text/plain, which the Core Monitor endpoint rejects.
        navigator.sendBeacon(url, new Blob([body], {
          type: 'application/json'
        }));
        return;
      }
    }
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body,
      keepalive: transport === 'beacon'
    }).catch(() => {
      // best-effort telemetry — never throw
    });
  } catch {
    // Intentionally swallow telemetry errors to avoid breaking checkout flow.
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/MelidataAdapter.ts
/**
 * Platform adapter: emits Super Token error events to MeliData (RN-2).
 *
 * MeliData (`window.melidata`) is provided by a SEPARATE external CDN bundle,
 * loaded async/defer after `load` — its readiness is a network round-trip the
 * plugin cannot order via script enqueue. So this adapter is event-driven: when
 * MeliData is ready it dispatches immediately; otherwise it buffers events and
 * flushes them once `window.melidataReady` resolves. `.then()` fires even when
 * subscribed after resolution, so no signal is missed.
 *
 * Three readiness paths (tried in order in `armReadiness`):
 *   1. `window.melidata` already present → flush immediately.
 *   2. `window.melidataReady` exists → subscribe `.then`/`.catch`.
 *   3. Neither present yet → use `load` event OR, if `load` already fired
 *      (`document.readyState === 'complete'`), call `onFailure` directly so
 *      buffered events are never silently discarded.
 *
 * FIFO ordering is preserved by `isMelidataReady()`: it only short-circuits to
 * "ready" via `window.melidata` when the buffer is empty — while the buffer has
 * pending events any new call enqueues and waits for the same flush.
 *
 * Note: `super-token-metrics.js` (legacy) mirrored `MPCustomEventDispatcher`
 * (`mp-checkout-error-dispatcher.js`) with a per-event `waitForMelidata` + 5s race.
 * This adapter keeps one 5s deadline for the whole FIFO buffer rather than one timer
 * per event, preventing a never-settled readiness promise from retaining events forever.
 */
class MelidataAdapter {
  MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';
  MELIDATA_LOAD_TIMEOUT_METRIC = 'mp_melidata_load_timeout';
  MELIDATA_LOAD_TIMEOUT_MS = 5000;
  /**
   * Currently typed as `BufferedErrorEvent[]` because `dispatchMelidataErrorEvent`
   * is the only public method and the only event type flowing through the adapter.
   * When TASK-006+ adds other event types (loading-start, payment-method-selected,
   * etc.), the buffer will need to become a discriminated union so events of
   * different shapes can be buffered and flushed in FIFO order.
   */
  buffer = [];
  ready = false;
  failed = false;
  readinessArmed = false;
  loadListenerAdded = false;
  readinessTimer = null;
  constructor(sendMetric) {
    this.sendMetric = sendMetric;
  }
  dispatchMelidataErrorEvent(errorMessage, errorOrigin) {
    const cleanMessage = errorMessage?.replace(/^\[mercado pago\]:\s*/i, '').trim() || errorMessage;
    const event = {
      message: cleanMessage,
      errorOrigin: `${errorOrigin}_mercado_pago`
    };
    if (this.isMelidataReady()) {
      this.dispatch(event);
      return;
    }
    this.buffer.push(event);
    this.armReadiness();
  }

  /**
   * Returns true only when it is safe to dispatch without breaking FIFO order.
   * If the buffer has pending events, subsequent calls must enqueue even if
   * `window.melidata` is now available — otherwise new events would arrive in
   * MeliData before earlier buffered ones.
   */
  isMelidataReady() {
    return this.ready || this.failed || !!window.melidata && this.buffer.length === 0;
  }
  armReadiness() {
    if (this.readinessArmed) {
      return;
    }
    this.readinessArmed = true;
    if (window.melidata) {
      this.onReady();
      return;
    }
    this.armTimeout();
    if (window.melidataReady && typeof window.melidataReady.then === 'function') {
      window.melidataReady.then(() => this.onReady()).catch(() => this.onFailure());
      return;
    }

    // `window.melidataReady` not yet defined (the local melidata-client.js loader
    // has not run). If `load` already fired we will never receive it again — call
    // onFailure directly so the buffer is not silently discarded.
    if (document.readyState === 'complete') {
      this.onFailure();
      return;
    }

    // `load` has not fired yet — re-arm once it does (without polling). Register the
    // listener only once: consecutive events before `load` would otherwise each add a
    // new `{ once: true }` listener. Re-arming happens inside the callback, not here.
    if (!this.loadListenerAdded) {
      this.loadListenerAdded = true;
      window.addEventListener('load', () => {
        this.readinessArmed = false;
        this.armReadiness();
      }, {
        once: true
      });
    }
  }
  armTimeout() {
    if (this.readinessTimer || this.ready || this.failed) {
      return;
    }
    this.readinessTimer = setTimeout(() => this.onFailure(), this.MELIDATA_LOAD_TIMEOUT_MS);
  }
  clearTimeout() {
    if (!this.readinessTimer) {
      return;
    }
    clearTimeout(this.readinessTimer);
    this.readinessTimer = null;
  }
  onReady() {
    if (this.ready || this.failed) {
      return;
    }
    this.ready = true;
    this.clearTimeout();
    this.flush();
  }
  onFailure() {
    if (this.ready || this.failed) {
      return;
    }
    this.failed = true;
    this.clearTimeout();
    // The deadline belongs to the buffer, not to each buffered event. Emit one timeout signal
    // and keep the individual diagnostics on their original Core Monitor metrics + FIFO events.
    this.sendMetric(this.MELIDATA_LOAD_TIMEOUT_METRIC, 'true', `buffered:${this.buffer.length}`);
    this.flush();
  }
  flush() {
    while (this.buffer.length > 0) {
      this.dispatch(this.buffer.shift());
    }
  }
  dispatch(event) {
    document.dispatchEvent(new CustomEvent(this.MELIDATA_ERROR_EVENT_NAME, {
      detail: {
        message: event.message,
        errorOrigin: event.errorOrigin
      }
    }));
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/constants.ts
/**
 * Single source of truth for the Super Token bundle's setup constants: variant names, the A/B
 * cookie, the injected JS version and the CDN location the A/B config is read from.
 *
 * Scope note: the loader (`assets/js/checkouts/super-token-loader.js`, a standalone CDN asset) and the PHP
 * gateway are separate runtimes that cannot import from this module — they keep their own minimal
 * copies of the values they need (documented as the single source for each runtime).
 */

/**
 * Injected into the init telemetry as `js_version`. Kept in sync with the CDN bundle's version.
 */
const SUPER_TOKEN_JS_VERSION = '1.2.6';
const V2_VARIANT = 'v2';
const V21_VARIANT = 'v2.1';

/** Applied whenever the A/B resolution cannot produce a valid variant. */
const SUPER_TOKEN_FALLBACK_VARIANT = V2_VARIANT;

/** The only variants the runtime knows how to render; anything else falls back. */
const SUPER_TOKEN_ALLOWED_VARIANTS = {
  [V2_VARIANT]: true,
  [V21_VARIANT]: true
};

/** Cookie the A/B assignment is remembered on (written by VariantConfigAdapter, read for metrics). */
const SUPER_TOKEN_VARIANT_COOKIE = 'mp_st_variant';

/** `v1` = production storage segment | `homol` = homologação. */
const SUPER_TOKEN_BUNDLE_ENV = 'v1';
const SUPER_TOKEN_STORAGE_BASE_URL = `https://http2.mlstatic.com/storage/${SUPER_TOKEN_BUNDLE_ENV}/mercadopago/woocommerce/scripts`;
const SUPER_TOKEN_AB_CONFIG_URL = `${SUPER_TOKEN_STORAGE_BASE_URL}/config/super-token-variants.js`;
;// ./assets/js/checkouts/super-token/core/checkoutSession/ErrorClassification.ts
/**
 * Single source of truth for Super Token error codes and the pure mapping from an
 * error code to the message shown to the buyer. No side effects: unlike the legacy
 * convertErrorCodeToErrorMessage (v2.1:262), this neither increments the retry
 * counter nor emits metrics — the caller (use case) owns the counter (SuperTokenState)
 * and the retry-limit metric (MetricsPort).
 *
 * Codes migrated 1:1 from v2.1/errors/super-token-error-constants.js.
 */

const ErrorClassification_MPSuperTokenErrorCodes = {
  // Validation errors
  SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
  SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
  // Authentication errors
  AUTHENTICATOR_NOT_FOUND: 'AUTHENTICATOR_NOT_FOUND',
  AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
  AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
  // Payment errors
  UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
  EMPTY_ACCOUNT_PAYMENT_METHODS: 'EMPTY_ACCOUNT_PAYMENT_METHODS',
  GET_PAYMENT_METHOD_TIMEOUT_ERROR: 'GET_PAYMENT_METHOD_TIMEOUT_ERROR',
  FETCH_PAYMENT_METHOD_NOT_FOUND: 'FETCH_PAYMENT_METHOD_NOT_FOUND',
  PAYMENT_METHOD_NOT_EXISTS: 'PAYMENT_METHOD_NOT_EXISTS',
  UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS: 'UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS',
  // System errors
  SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
  SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
  CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND: 'CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND',
  SUPER_TOKEN_METRICS_NOT_FOUND: 'SUPER_TOKEN_METRICS_NOT_FOUND',
  // Generic error
  UNKNOWN_ERROR: 'UNKNOWN_ERROR'
};
const KNOWN_ERROR_CODES = Object.values(ErrorClassification_MPSuperTokenErrorCodes);
const SENSITIVE_ERROR_KEY_NAMES = 'authorization|access_token|refresh_token|id_token|token|authorized_pseudotoken|pseudotoken|password|client[_-]?secret|x[_-]?api[_-]?key|api[_-]?key|secret|security_code|cvv|card_number|cookie|set[_-]?cookie|session(?:[_-]?(?:id|token))?|jwt';
const SENSITIVE_ERROR_KEY = new RegExp(`^(?:${SENSITIVE_ERROR_KEY_NAMES})$`, 'i');
const EMAIL_ADDRESS_PATTERN = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi;
const BEARER_TOKEN_PATTERN = /\b(Bearer\s+)[A-Z0-9._~+/-]+=*/gi;
const COOKIE_HEADER_PATTERN = /\b((?:Set-Cookie|Cookie)\s*[:=]\s*)[^\r\n]*/gi;
// Authorization schemes may contain spaces and Base64 padding (`==`), which can look like the
// start of another keyed field to the generic pattern below. Consume the complete header line
// first so no credential fragment survives under Basic, Bearer, Digest or future schemes.
const AUTHORIZATION_HEADER_PATTERN = /\b((?:Proxy-)?Authorization\s*:\s*)[^\r\n]*/gi;
const AUTHORIZATION_REDACTION_PLACEHOLDER = '__MP_AUTHORIZATION_REDACTED__';
const JWT_TOKEN_PATTERN = /\beyJ[A-Z0-9_-]*\.[A-Z0-9_-]+\.[A-Z0-9_-]+\b/gi;
const KEYED_SECRET_PATTERN = new RegExp(`((["']?(?:${SENSITIVE_ERROR_KEY_NAMES})["']?)\\s*[:=]\\s*)(?:"([^"\\r\\n]*)"|'([^'\\r\\n]*)'|([^,;\\r\\n}\\]&]*?))(?=\\s+[A-Z_][A-Z0-9_.-]*\\s*[:=]|[,;\\r\\n}\\]&]|$)`, 'gi');
const redactKeyedSecret = (_match, prefix, _key, doubleQuotedValue, singleQuotedValue) => {
  if (doubleQuotedValue !== undefined) {
    return `${prefix}"[REDACTED]"`;
  }
  if (singleQuotedValue !== undefined) {
    return `${prefix}'[REDACTED]'`;
  }
  return `${prefix}[REDACTED]`;
};
const redactSensitiveTelemetryValues = message => message.replace(EMAIL_ADDRESS_PATTERN, '[REDACTED_EMAIL]')
// Use a neutral marker because the generic redactor treats the closing `]` in [REDACTED]
// as a value delimiter and would otherwise process this header a second time.
.replace(AUTHORIZATION_HEADER_PATTERN, `$1${AUTHORIZATION_REDACTION_PLACEHOLDER}`).replace(KEYED_SECRET_PATTERN, redactKeyedSecret)
// Reconsume the complete header so semicolon-delimited cookie attributes cannot escape.
.replace(COOKIE_HEADER_PATTERN, '$1[REDACTED]').replace(BEARER_TOKEN_PATTERN, '$1[REDACTED]').replace(JWT_TOKEN_PATTERN, '[REDACTED_JWT]').split(AUTHORIZATION_REDACTION_PLACEHOLDER).join('[REDACTED]');

/**
 * Preserves the real error text needed for production troubleshooting while redacting only
 * credential/PII values. Error names, SDK codes, HTTP statuses and surrounding diagnostic context
 * remain intact. Objects without a message are serialized with sensitive keys replaced.
 */
const toTelemetryErrorMessage = (error, fallback = 'Unknown error') => {
  try {
    if (typeof error === 'string' && error.trim()) {
      return redactSensitiveTelemetryValues(error);
    }
    if (error && typeof error === 'object') {
      const message = error.message;
      if (typeof message === 'string' && message.trim()) {
        return redactSensitiveTelemetryValues(message);
      }
      const errorCode = error.errorCode;
      if (typeof errorCode === 'string' && errorCode.trim()) {
        return redactSensitiveTelemetryValues(errorCode);
      }
      const seenObjects = new WeakSet();
      const serialized = JSON.stringify(error, (key, value) => {
        if (SENSITIVE_ERROR_KEY.test(key)) {
          return '[REDACTED]';
        }
        if (typeof value === 'string') {
          return redactSensitiveTelemetryValues(value);
        }
        if (typeof value === 'bigint') {
          return String(value);
        }
        if (value && typeof value === 'object') {
          if (seenObjects.has(value)) {
            return '[Circular]';
          }
          seenObjects.add(value);
        }
        return value;
      });
      if (serialized && serialized !== '{}') {
        return serialized;
      }
    }
    if (error !== null && error !== undefined) {
      const stringified = String(error);
      if (stringified && stringified !== '[object Object]') {
        return redactSensitiveTelemetryValues(stringified);
      }
    }
  } catch {
    // A malformed SDK object may throw from a getter or during serialization.
  }
  return fallback;
};

/**
 * Classifies an arbitrary exception into the allowlisted Super Token catalog. This is an auxiliary
 * dimension for grouping; the diagnostic message is produced independently by
 * `toTelemetryErrorMessage`, so an unknown code does not erase the real failure context.
 */
const toSafeTelemetryErrorCode = error => {
  const candidates = [error];
  if (error && typeof error === 'object') {
    try {
      candidates.push(error.errorCode, error.message);
    } catch {
      return ErrorClassification_MPSuperTokenErrorCodes.UNKNOWN_ERROR;
    }
  }
  for (const candidate of candidates) {
    if (typeof candidate !== 'string') {
      continue;
    }
    const knownCode = KNOWN_ERROR_CODES.find(code => candidate.includes(code));
    if (knownCode) {
      return knownCode;
    }
  }
  return ErrorClassification_MPSuperTokenErrorCodes.UNKNOWN_ERROR;
};

/**
 * Single source of truth for the recoverable-error list (RN-2). Migrated 1:1 from the
 * duplicated `recoverableErrors` arrays in the Classic (`event-handler.js:433-437`) and
 * Blocks (`custom.block.js:128-132`) finalization handlers — the duplication that caused
 * PSW-3737/PSW-4113 to be fixed in two places. A recoverable error lets the buyer retry
 * without losing the checkout; any other code is unrecoverable.
 */
const RECOVERABLE_ERRORS = [ErrorClassification_MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR, ErrorClassification_MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR, ErrorClassification_MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED];

/**
 * Whether an error code is recoverable. Strict membership — matches the legacy
 * `recoverableErrors.includes(exception?.message)` (exact equality, not the substring
 * match `resolveErrorMessage` uses), so classification behaviour is unchanged.
 */
const isRecoverable = errorCode => !!errorCode && RECOVERABLE_ERRORS.includes(errorCode);

/** Buyer-facing error copy the message resolution selects from. */

const errorMessagesFor = copy => ({
  UPDATE_SECURITY_CODE_ERROR: {
    withRetry: copy.updateSecurityCodeWithRetryText,
    withoutRetry: copy.updateSecurityCodeNoRetryText
  },
  AUTHORIZE_PAYMENT_METHOD_ERROR: {
    withRetry: copy.authorizePaymentMethodWithRetryText,
    withoutRetry: copy.authorizePaymentMethodNoRetryText
  },
  AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: {
    withRetry: copy.authorizePaymentMethodWithRetryText,
    withoutRetry: copy.authorizePaymentMethodNoRetryText
  },
  SELECT_PAYMENT_METHOD_ERROR: {
    withRetry: copy.selectPaymentMethodErrorText,
    withoutRetry: copy.selectPaymentMethodErrorText
  }
});

/**
 * Resolves the buyer message for an error code. `errorCode.includes(key)` (substring)
 * match is preserved from the legacy implementation. `allowRetry` is decided by the
 * caller from the retry counter (SuperTokenState.shouldAllowRetry).
 */
const resolveErrorMessage = (errorCode, allowRetry, copy) => {
  var _Object$entries$find$;
  const errorMessages = errorMessagesFor(copy);
  const errorConfig = (_Object$entries$find$ = Object.entries(errorMessages).find(([key]) => errorCode.includes(key))?.[1]) !== null && _Object$entries$find$ !== void 0 ? _Object$entries$find$ : null;
  if (!errorConfig) {
    return copy.genericErrorText;
  }
  return allowRetry ? errorConfig.withRetry : errorConfig.withoutRetry;
};
;// ./assets/js/checkouts/super-token/adapters/platform/CoreMonitorMetricsAdapter.ts





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
class CoreMonitorMetricsAdapter_CoreMonitorMetricsAdapter {
  PLATFORM_NAME = 'woocommerce';
  CUSTOM_CHECKOUT_STEPS = {
    LOAD_SUPER_TOKEN: 'load_super_token',
    SELECT_PAYMENT_METHOD: 'select_payment_method',
    POST_SUBMIT: 'post_submit'
  };
  constructor(sdk, superTokenJsVersion, params) {
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
    this.melidata = new MelidataAdapter((metricName, value, message) => this.sendMetric(metricName, value, message));
  }
  getSdkInstanceId() {
    try {
      return this.resolveSdk()?.getSDKInstanceId() || 'Unknown';
    } catch {
      return 'Unknown';
    }
  }
  getEnvironment() {
    return 'prod';
  }

  // Reads the A/B experiment the loader set on the `mp_st_variant` cookie, so every metric
  // carries which variant the buyer saw. Preserved 1:1 from the legacy `getAbVariant`:
  // anything other than v2/v2.1 (including a missing cookie) reports 'unknown'.
  getAbVariant() {
    const match = typeof document !== 'undefined' ? document.cookie.match(new RegExp('(?:^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)')) : null;
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
  buildPayload(value, message, errorCode = null) {
    const details = {
      site_id: this.SITE_ID,
      environment: this.getEnvironment(),
      sdk_instance_id: this.getSdkInstanceId(),
      cust_id: this.CUST_ID,
      js_version: this.SUPER_TOKEN_JS_VERSION,
      ab_variant: this.getAbVariant()
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
        url: this.LOCATION
      },
      details
    };
  }
  sendMetric(metricName, value, message, errorCode = null) {
    sendToCoreMonitor(metricName, this.buildPayload(value, message, errorCode));
  }
  normalizeErrorMessage(error) {
    return toTelemetryErrorMessage(error);
  }
  errorCodeOf(error) {
    return toSafeTelemetryErrorCode(error);
  }

  /** Send an error metric + dispatch a melidata event. Covers ~20 methods. */
  errorWith(metricName, step, error) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, step);
    this.sendMetric(metricName, 'true', errorMessage, this.errorCodeOf(error));
  }

  /** Send a boolean success metric (no melidata event). Covers update-security-code steps. */
  successBoolean(metricName) {
    this.sendMetric(metricName, 'true', '');
  }

  // ─── MetricsPort ────────────────────────────────────────────────────────────

  canUseSuperToken(canUseSuperToken, error = null) {
    const errorMessage = canUseSuperToken ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('can_use_super_token', canUseSuperToken, errorMessage);
  }
  errorToAuthorizePayment(error) {
    this.errorWith('error_to_authorize_payment', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT, error);
  }
  errorToGetAccountPaymentMethods(error) {
    this.errorWith('error_to_get_account_payment_methods', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }
  errorToUpdateSecurityCode(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_update_security_code', paymentMethod?.id || 'unknown', errorMessage, this.errorCodeOf(error));
  }
  updateSecurityCodeSuccess() {
    this.successBoolean('update_security_code_success');
  }
  registerSelectPaymentMethod(paymentMethodType) {
    this.sendMetric('select_payment_method', `super_token_${paymentMethodType}`, '');
  }
  renderCreditsContract(success, error = null) {
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

  errorToGetSimplifiedAuth(error) {
    this.errorWith('error_to_get_simplified_auth', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }
  errorToGetFastPaymentToken(error) {
    this.errorWith('error_to_get_fast_payment_token', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }
  errorToBuildAuthenticator(error) {
    this.errorWith('error_to_build_authenticator', this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN, error);
  }
  errorToMountCVVField(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'unknown', errorMessage, this.errorCodeOf(error));
  }
  updateSecurityCodeGetCardIdSuccess() {
    this.successBoolean('update_security_code_get_card_id_success');
  }
  updateSecurityCodeCardTokenCreated() {
    this.successBoolean('update_security_code_card_token_created');
  }
  updateSecurityCodePseudotokenUpdated() {
    this.successBoolean('update_security_code_pseudotoken_updated');
  }
  errorOnSubmit(errorCode, error, shouldNormalizeError = true) {
    // Keep the legacy argument for binary compatibility. Both paths preserve the diagnostic text;
    // the shared formatter only redacts credential/PII values.
    void shouldNormalizeError;
    const reportedErrorCode = toTelemetryErrorMessage(errorCode, 'UNKNOWN_ERROR');
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_on_submit_super_token', reportedErrorCode, errorMessage);
  }
  registerClickOnPlaceOrderButton() {
    this.successBoolean('super_token_click_on_place_order_button');
  }
  errorToExcludeRecaptchaFromPreValidation(context, error) {
    this.sendMetric('error_to_exclude_recaptcha_from_pre_validation', context, this.normalizeErrorMessage(error));
  }
  captchaFieldToggledOnPreValidation(action, fieldName) {
    this.sendMetric('super_token_captcha_field_toggled_on_pre_validation', action, fieldName);
  }
  registerAuthorizedPseudotoken(authorizedPseudotokenInputExists) {
    this.sendMetric('authorized_pseudotoken', 'true', `input_exists:${authorizedPseudotokenInputExists ? 'true' : 'false'}`);
  }
  errorToRenderAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.sendMetric('error_to_render_account_payment_methods', 'true', errorMessage, this.errorCodeOf(error));
  }
  hasEscNotExists(paymentMethodIdentifier) {
    this.sendMetric('has_esc_not_exists', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', 'has_esc attribute not found in payment method');
  }
  getPaymentMethodFail(error, currentPaymentMethodIdentifier) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.melidata.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('get_payment_method_fail', currentPaymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', errorMessage);
  }
  getPaymentMethodLoadingTime(currentPaymentMethodIdentifier, durationSeconds) {
    this.sendMetric('get_payment_method_loading_time', currentPaymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', `${durationSeconds}s`);
  }
  fetchPaymentMethodSuccess(paymentMethodIdentifier, cvvIsMandatory) {
    this.sendMetric('fetch_payment_method_success', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', `cvv_is_mandatory_${cvvIsMandatory}`);
  }
  fetchPaymentMethodSkipped(paymentMethodIdentifier, reason) {
    this.sendMetric('fetch_payment_method_skipped', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', reason);
  }
  fetchPaymentMethodTimeout(paymentMethodIdentifier) {
    this.sendMetric('fetch_payment_method_timeout', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', 'Fetch payment method timed out');
  }
  isNotSimplifiedAuth() {
    this.successBoolean('is_not_simplified_auth');
  }
  cannotGetFastPaymentToken() {
    this.successBoolean('cannot_get_fast_payment_token');
  }
  installmentsFilled(paymentMethodType) {
    this.sendMetric('super_token_installments_filled', true, paymentMethodType);
  }
  renderConsumerCreditsDetailsInnerHTML(success) {
    if (!success) {
      this.melidata.dispatchMelidataErrorEvent('render_consumer_credits_details_inner_html_failed', this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    }
    this.sendMetric('render_consumer_credits_details_inner_html', success, '');
  }
  registerOpenCreditsInfoModal(linkText) {
    this.sendMetric('open_credits_info_modal', 'true', linkText);
  }
  renderConsumerCreditsDueDate(success, error = null) {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('render_consumer_credits_due_date', success, errorMessage);
  }
  renderConsumerCreditsHint(success, error = null) {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    this.sendMetric('render_consumer_credits_hint', success, errorMessage);
  }
  errorToUpdateCreditsContract(error) {
    this.errorWith('error_to_update_credits_contract', this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD, error);
  }
  errorToSubmitWithoutInstallmentSelected(paymentMethodType = '') {
    this.melidata.dispatchMelidataErrorEvent('no_installment_selected', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_submit_without_installment_selected', 'true', paymentMethodType);
  }

  // ─── Initialization resilience (TASK-010) ───────────────────────────────────
  // Metric names, messages and levels are preserved 1:1 from the legacy
  // `mp-super-token.js`. The four `checkIfSuperTokenWasInitialized` signals moved
  // from the plugin-global `sendMetric(name, message, level)` onto Core Monitor:
  // the metric name is unchanged, the legacy `level` is carried as the payload
  // event (errorCode) and the human message is preserved.

  INIT_ERROR_LEVEL = 'mp_super_token_init_error';
  INIT_SUCCESS_LEVEL = 'mp_super_token_init_success';
  superTokenSdkLoaded() {
    this.sendMetric('super_token_sdk_loaded', 'true', '');
  }
  reportInitSource(source, elapsedMs) {
    this.sendMetric('super_token_init_source', source, `elapsed_ms:${elapsedMs}`);
  }
  superTokenInitializationSuccess(dispatchedFrom) {
    this.sendMetric('SUPER_TOKEN_INITIALIZATION_SUCCESS', 'true', `Super token was initialized successfully and is listening to the form Dispatched from: ${dispatchedFrom}`, this.INIT_SUCCESS_LEVEL);
  }
  superTokenInitializationError(error, dispatchedFrom) {
    const errorMessage = this.normalizeErrorMessage(error);
    this.sendMetric('SUPER_TOKEN_INITIALIZATION_ERROR', 'true', `An error occurred while checking super token initialization: ${errorMessage} Dispatched from: ${dispatchedFrom}`, this.INIT_ERROR_LEVEL);
  }
  superTokenClassesNotExist(missingSummary, dispatchedFrom) {
    this.sendMetric('SUPER_TOKEN_CLASSES_NOT_EXISTS', 'true', `${missingSummary} Dispatched from: ${dispatchedFrom}`, this.INIT_ERROR_LEVEL);
  }
  superTokenTriggerHandlerNotListening(dispatchedFrom) {
    this.sendMetric('SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING', 'true', `Trigger handler is not listening to the form after super token initialization Dispatched from: ${dispatchedFrom}`, this.INIT_ERROR_LEVEL);
  }
  mpSdkInstanceNotExists(dispatchedFrom) {
    this.sendMetric('MP_SDK_INSTANCE_NOT_EXISTS', 'true', `MP SDK instance did not load within the expected time Dispatched from: ${dispatchedFrom}`, this.INIT_ERROR_LEVEL);
  }

  // ─── Orchestration signals ──────────────────────────────────────────────────
  // Named methods for the ad-hoc `sendMetric(...)` call sites that used to live in the use
  // cases, session adapters and Blocks consumer. The metric name/value/message are preserved
  // 1:1 and now encapsulated here so callers speak the domain language and `sendMetric` stays
  // private (the single place that knows the Core Monitor strings).

  registerWithdraw() {
    this.sendMetric('super_token_withdraw', 'false', '');
  }
  authExpiredOnSubmit() {
    this.sendMetric('super_token_auth_expired_on_submit', 'true', '');
  }
  skippedNoEmail() {
    this.sendMetric('super_token_skipped_no_email', 'true', '');
  }
  skippedInvalidEmail() {
    this.sendMetric('super_token_skipped_invalid_email', 'true', '');
  }
  emailCaptured() {
    this.sendMetric('super_token_email_captured', 'true', '');
  }
  resetOnAmountChange() {
    this.sendMetric('super_token_reset_on_amount_change', 'true', '');
  }
  resetOnEmailChange() {
    this.sendMetric('super_token_reset_on_email_change', 'true', '');
  }
  reportRestoreError(reason) {
    this.sendMetric(reason, 'true', 'mp_super_token_restore_error');
  }
  customCheckoutHandlerMissingOnInstallmentValidation() {
    this.sendMetric('mp_custom_checkout_handler_missing', 'installment_validation_failed', 'mpCustomCheckoutHandler was undefined during installment validation cleanup');
  }
  async sendStaleCacheMetrics() {
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
    const basePath = this.PLUGIN_JS_BASE_URL || '/wp-content/plugins/woocommerce-mercadopago/assets/js/';
    const files = ['checkouts/custom/entities/card-form.min.js', 'checkouts/custom/entities/event-handler.min.js', 'melidata/melidata-client.min.js', 'checkouts/super-token-loader.min.js'];
    await Promise.all(files.map(async file => {
      try {
        let response = await fetch(basePath + file, {
          method: 'HEAD',
          cache: 'no-store'
        });
        if (response.status === 405) {
          response = await fetch(basePath + file, {
            method: 'GET',
            cache: 'no-store',
            headers: {
              Range: 'bytes=0-0'
            }
          });
        }
        if (!response.ok) return;
        const lastModified = response.headers.get('last-modified');
        const age = response.headers.get('age');
        let ageDays = null;
        if (lastModified) {
          ageDays = Math.round((Date.now() - new Date(lastModified).getTime()) / 86400000);
        } else if (age) {
          ageDays = Math.round(parseInt(age, 10) / 86400);
        }
        if (!Number.isFinite(ageDays)) return;
        const fileName = file.split('/').pop().replace('.min.js', '');
        const lastModifiedDate = lastModified ? new Date(lastModified).toISOString().slice(0, 10) : 'unknown';
        this.sendMetric('mp_js_cache_age', String(ageDays), `file : ${fileName} age_days : ${ageDays} last_modified : ${lastModifiedDate}`);
      } catch {
        // Silence errors — must not impact checkout
      }
    }));
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/VariantConfigAdapter.ts



/**
 * Platform adapter: resolves the A/B variant string (RN-4), porting the selection
 * logic of `super-token-loader.js` into the hexagonal tree. It reads the remote
 * config, cookie and weighted assignment and returns the logical variant — it does
 * NOT load the CDN bundle nor pick a view (that stays with the loader today and
 * moves to the single-bundle runtime resolution in TASK-008/013, ADR-005).
 *
 * Behavior preserved 1:1, including the `source:*` telemetry and the cookie as a
 * resilience fallback. The only consolidation: the loader had two error layers
 * (sync try/catch + async .catch); with `await` in one try/catch they collapse into
 * a single fallback path emitting the same load-failure metric.
 *
 * TODO: the selection logic (fetch → kill-switch → cookie → weighted → fallback) is
 * complex. Consider extracting it into a dedicated VariantSelectionStrategy or
 * simplifying via a state machine in a future task.
 */
class VariantConfigAdapter_VariantConfigAdapter {
  // Variant names, the A/B cookie, the CDN config URL and the allowed set live in
  // config/constants.ts (single source shared with bootstrap and the metrics adapter). Only the
  // selection-timing knobs and metric names below are internal to this adapter.
  SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS = 30;
  SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS = 3000;
  SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS = 2 / 24;
  METRIC_LOAD_SUPER_TOKEN_BUNDLE = 'load_super_token_bundle';
  METRIC_FETCH_AB_CONFIG = 'fetch_ab_config';
  METRIC_FETCH_AB_CONFIG_TIME = 'fetch_ab_config_loading_time';
  METRIC_SUPER_TOKEN_AB_VARIANT = 'super_token_ab_variant';
  METRIC_STATUS_FAILURE = 'false';
  MILLISECONDS_PER_DAY = 864e5;
  // Params are injected by the composition root (createPlatformAdapters) so this
  // adapter stays free of `window.*` reads; the fallback keeps it usable standalone.
  constructor(params) {
    var _ref;
    this.params = (_ref = params !== null && params !== void 0 ? params : window.wc_mercadopago_woocommerce_scripts_params) !== null && _ref !== void 0 ? _ref : {};
  }
  async resolve() {
    try {
      const cachedVariant = this.getVariantCookie();

      // Always fetch config so the kill switch (active:false) propagates immediately
      // to all visitors, including returning ones with a valid cookie.
      const abConfig = await this.fetchAbConfig(SUPER_TOKEN_AB_CONFIG_URL, this.SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS);
      if (!abConfig) {
        // Fetch failed — use cookie as resilience fallback to preserve user experience.
        if (cachedVariant && this.isAllowed(cachedVariant)) {
          this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
          return cachedVariant;
        }
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:fetch_failed');
        this.setVariantCookie('fetch_failed', this.SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS);
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }
      if (typeof abConfig.active !== 'boolean') {
        // active absent, null, or wrong type — malformed config, not a kill switch
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }
      if (!abConfig.active) {
        // active === false — Kill switch: clears cookie and returns default for ALL visitors.
        this.clearVariantCookie();
        const defaultVariant = abConfig.default && this.isAllowed(abConfig.default) ? abConfig.default : SUPER_TOKEN_FALLBACK_VARIANT;
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, defaultVariant, 'source:kill_switch');
        return defaultVariant;
      }
      if (!abConfig.variants || typeof abConfig.variants !== 'object') {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }

      // active:true — use existing valid cookie without re-assigning the variant.
      if (cachedVariant && this.isAllowed(cachedVariant)) {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
        return cachedVariant;
      }

      // No cookie or unknown/corrupted variant — clear and assign a new one.
      if (cachedVariant) {
        this.clearVariantCookie();
      }
      const assignedVariant = this.selectVariantByWeight(abConfig.variants);
      if (!this.isAllowed(assignedVariant)) {
        this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
        return SUPER_TOKEN_FALLBACK_VARIANT;
      }
      const cookieTtlDays = abConfig.cookie_ttl_days && abConfig.cookie_ttl_days > 0 ? abConfig.cookie_ttl_days : this.SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS;
      this.setVariantCookie(assignedVariant, cookieTtlDays);
      this.trackMetric(this.METRIC_SUPER_TOKEN_AB_VARIANT, assignedVariant, 'source:assigned');
      return assignedVariant;
    } catch (error) {
      this.trackMetric(this.METRIC_LOAD_SUPER_TOKEN_BUNDLE, this.METRIC_STATUS_FAILURE, toTelemetryErrorMessage(error, 'async_error'));
      return SUPER_TOKEN_FALLBACK_VARIANT;
    }
  }
  isAllowed(variant) {
    return SUPER_TOKEN_ALLOWED_VARIANTS[variant] === true;
  }
  buildPayload(value, message) {
    const p = this.params;
    return {
      value: `${value}`,
      message,
      plugin_version: p.plugin_version || '',
      platform: {
        name: 'woocommerce',
        uri: p.theme || '',
        version: p.platform_version || '',
        url: `${window.location.origin}${window.location.pathname}`
      },
      details: {
        site_id: p.site_id || '',
        environment: 'prod',
        cust_id: p.cust_id || ''
      }
    };
  }
  trackMetric(metricName, value, message) {
    sendToCoreMonitor(metricName, this.buildPayload(value, message), 'beacon');
  }
  getVariantCookie() {
    const cookiePattern = new RegExp('(^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)');
    const cookieMatch = document.cookie.match(cookiePattern);
    return cookieMatch ? cookieMatch[2] : null;
  }
  setVariantCookie(variantValue, ttlInDays) {
    try {
      const expiration = new Date(Date.now() + ttlInDays * this.MILLISECONDS_PER_DAY).toUTCString();
      document.cookie = SUPER_TOKEN_VARIANT_COOKIE + '=' + variantValue + ';expires=' + expiration + ';path=/;SameSite=Lax;Secure';
    } catch (_) {
      // Intentionally swallow cookie errors to avoid breaking checkout flow.
    }
  }
  clearVariantCookie() {
    document.cookie = SUPER_TOKEN_VARIANT_COOKIE + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax;Secure';
  }
  selectVariantByWeight(variants) {
    const variantNames = Object.keys(variants);
    const totalWeight = variantNames.reduce((weightSum, variantName) => weightSum + (variants[variantName].weight || 0), 0);
    if (totalWeight <= 0) {
      return SUPER_TOKEN_FALLBACK_VARIANT;
    }
    const randomPoint = Math.random() * totalWeight;
    let accumulatedWeight = 0;
    for (let i = 0; i < variantNames.length; i++) {
      accumulatedWeight += variants[variantNames[i]].weight || 0;
      if (randomPoint < accumulatedWeight) {
        return variantNames[i];
      }
    }
    return variantNames[0];
  }
  fetchAbConfig(configUrl, timeoutMs) {
    const fetchStartTime = Date.now();
    let hasFetchTimedOut = false;
    const fetchTimeoutPromise = new Promise(resolve => {
      setTimeout(() => {
        hasFetchTimedOut = true;
        resolve(null);
      }, timeoutMs);
    });
    const fetchConfigPromise = fetch(configUrl, {
      cache: 'no-cache'
    }).then(response => {
      if (!response.ok) {
        if (!hasFetchTimedOut) {
          this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', 'http:' + response.status);
        }
        return null;
      }
      return response.json().then(parsedConfig => {
        // Guard: if timeout already fired, discard result to avoid
        // emitting both 'timeout' and 'success' metrics for the same request.
        if (hasFetchTimedOut) return null;
        const elapsedMs = Date.now() - fetchStartTime;
        this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'success', 'success');
        this.trackMetric(this.METRIC_FETCH_AB_CONFIG_TIME, elapsedMs, '');
        return parsedConfig;
      }).catch(error => {
        if (!hasFetchTimedOut) {
          this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', toTelemetryErrorMessage(error, 'invalid_json'));
        }
        return null;
      });
    }).catch(error => {
      if (!hasFetchTimedOut) {
        this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'error', toTelemetryErrorMessage(error, 'network_or_cors'));
      }
      return null;
    });
    return Promise.race([fetchConfigPromise, fetchTimeoutPromise]).then(resolvedConfig => {
      if (hasFetchTimedOut) {
        this.trackMetric(this.METRIC_FETCH_AB_CONFIG, 'timeout', 'elapsed_ms:' + (Date.now() - fetchStartTime));
      }
      return resolvedConfig;
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/createDomainConfig.ts
/**
 * Composition-root mapper: turns the localized store params
 * (`wc_mercadopago_supertoken_bundle_params`) into the domain's `SuperTokenDomainConfig`
 * value object, so the core (PaymentMethodCatalog + PaymentMethodRegistry) never reads
 * `window.*`. The field mapping mirrors the legacy controller's SCREAMING_CASE reads
 * (`super-token-payment-methods.js` constructor) 1:1.
 */

/**
 * The subset of `wc_mercadopago_supertoken_bundle_params` the domain config is built from.
 * Grounded in `getSuperTokenLocalizeData()` (CustomGateway.php) and the legacy controller reads.
 */

function createDomainConfig(params, variant) {
  var _params$mercado_pago_, _ref, _params$interest_free, _params$interest_rate, _params$effective_tot, _params$iof_mlb_text, _params$borrowed_amou, _params$per_month, _params$per_year, _params$cat_mlm_text, _params$no_iva_text, _params$tna_mlm_text, _params$system_amorti, _params$cftea_mla_tex, _params$tna_mla_text, _params$tea_mla_text, _params$fixed_rate_te, _params$mp_logo_blue_, _params$mp_logo_dark_;
  return {
    siteId: params.site_id.toUpperCase(),
    intl: params.intl,
    currency: params.currency,
    paymentMethodsOrder: params.payment_methods_order,
    variant,
    // This mapper is the domain's boundary against the localized store params. Older plugin
    // versions still served the CDN bundle (< 8.8.0 for saved-methods/logo keys) may omit some
    // keys, so coalesce here to keep a literal "undefined" out of the rendered UI. The core
    // consumers stay clean of retro-compat concerns.
    copy: {
      accountMoneyText: params.account_money_text,
      accountMoneyWalletWithInvestmentText: params.account_money_wallet_with_investment_text,
      accountMoneyWalletText: params.account_money_wallet_text,
      accountMoneyInvestmentText: params.account_money_investment_text,
      accountMoneyAvailableText: params.account_money_available_text,
      mercadoPagoCardName: params.mercado_pago_card_name,
      mercadoPagoCreditCardName: (_params$mercado_pago_ = params.mercado_pago_credit_card_name) !== null && _params$mercado_pago_ !== void 0 ? _params$mercado_pago_ : '',
      lastDigitsText: params.last_digits_text,
      interestFreePartOneText: params.interest_free_part_one_text,
      interestFreePartTwoText: params.interest_free_part_two_text,
      // Legacy contract exposes this only nested under input_helper_message.installments.
      installmentsInterestFreeOptionText: (_ref = (_params$interest_free = params.interest_free_option_text) !== null && _params$interest_free !== void 0 ? _params$interest_free : params.input_helper_message?.installments?.interest_free_option_text) !== null && _ref !== void 0 ? _ref : '',
      consumerCreditsHint: {
        interestRateMlb: (_params$interest_rate = params.interest_rate_mlb_text) !== null && _params$interest_rate !== void 0 ? _params$interest_rate : '',
        effectiveTotalCostMlb: (_params$effective_tot = params.effective_total_cost_mlb_text) !== null && _params$effective_tot !== void 0 ? _params$effective_tot : '',
        iofMlb: (_params$iof_mlb_text = params.iof_mlb_text) !== null && _params$iof_mlb_text !== void 0 ? _params$iof_mlb_text : '',
        borrowedAmountMlb: (_params$borrowed_amou = params.borrowed_amount_mlb_text) !== null && _params$borrowed_amou !== void 0 ? _params$borrowed_amou : '',
        perMonth: (_params$per_month = params.per_month) !== null && _params$per_month !== void 0 ? _params$per_month : '',
        perYear: (_params$per_year = params.per_year) !== null && _params$per_year !== void 0 ? _params$per_year : '',
        catMlm: (_params$cat_mlm_text = params.cat_mlm_text) !== null && _params$cat_mlm_text !== void 0 ? _params$cat_mlm_text : '',
        noIvaMlm: (_params$no_iva_text = params.no_iva_text) !== null && _params$no_iva_text !== void 0 ? _params$no_iva_text : '',
        tnaMlm: (_params$tna_mlm_text = params.tna_mlm_text) !== null && _params$tna_mlm_text !== void 0 ? _params$tna_mlm_text : '',
        systemAmortizationMlm: (_params$system_amorti = params.system_amortization_mlm_text) !== null && _params$system_amorti !== void 0 ? _params$system_amorti : '',
        cfteaMla: (_params$cftea_mla_tex = params.cftea_mla_text) !== null && _params$cftea_mla_tex !== void 0 ? _params$cftea_mla_tex : '',
        tnaMla: (_params$tna_mla_text = params.tna_mla_text) !== null && _params$tna_mla_text !== void 0 ? _params$tna_mla_text : '',
        teaMla: (_params$tea_mla_text = params.tea_mla_text) !== null && _params$tea_mla_text !== void 0 ? _params$tea_mla_text : '',
        fixedRate: (_params$fixed_rate_te = params.fixed_rate_text) !== null && _params$fixed_rate_te !== void 0 ? _params$fixed_rate_te : ''
      }
    },
    thumbnails: {
      paymentMethodsThumbnails: params.payment_methods_thumbnails,
      whiteCardPath: params.white_card_path,
      yellowWalletPath: params.yellow_wallet_path,
      yellowMoneyPath: params.yellow_money_path,
      mpLogoBluePath: (_params$mp_logo_blue_ = params.mp_logo_blue_path) !== null && _params$mp_logo_blue_ !== void 0 ? _params$mp_logo_blue_ : '',
      mpLogoDarkPath: (_params$mp_logo_dark_ = params.mp_logo_dark_path) !== null && _params$mp_logo_dark_ !== void 0 ? _params$mp_logo_dark_ : ''
    }
  };
}
;// ./assets/js/checkouts/super-token/adapters/platform/createPlatformAdapters.ts





/** The platform edge the domain (TASK-006) is built on. */

const PARAMS_FALLBACK = {
  plugin_version: '',
  platform_version: '',
  site_id: '',
  cust_id: '',
  location: '',
  platform_id: ''
};

/**
 * Step 1 of the composition root (TASK-005): build the platform adapters.
 *
 * All `window.*` reads happen here once so the individual adapters stay free of
 * globals and are testable with injected values. `params` defaults to
 * `window.wc_mercadopago_supertoken_bundle_params`; if neither is present the
 * metrics adapter is built with empty strings, which the Core Monitor endpoint
 * will reject — this is intentional fail-visible behaviour rather than silent
 * success with useless telemetry.
 */
function createPlatformAdapters(options = {}) {
  var _options$sdk, _options$superTokenJs, _ref, _options$params, _ref2, _options$scriptsParam;
  const sdk = (_options$sdk = options.sdk) !== null && _options$sdk !== void 0 ? _options$sdk : window.mpSdkInstance;
  if (!sdk) {
    // Fail-visible: the MP JS SDK is required to build the payment adapter. Casting
    // undefined away would defer the failure to the first SDK call deep in the domain.
    throw new Error('createPlatformAdapters: MP JS SDK instance unavailable (window.mpSdkInstance is undefined).');
  }
  const superTokenJsVersion = (_options$superTokenJs = options.superTokenJsVersion) !== null && _options$superTokenJs !== void 0 ? _options$superTokenJs : null;
  const params = (_ref = (_options$params = options.params) !== null && _options$params !== void 0 ? _options$params : window.wc_mercadopago_supertoken_bundle_params) !== null && _ref !== void 0 ? _ref : PARAMS_FALLBACK;
  const scriptsParams = (_ref2 = (_options$scriptsParam = options.scriptsParams) !== null && _options$scriptsParam !== void 0 ? _options$scriptsParam : window.wc_mercadopago_woocommerce_scripts_params) !== null && _ref2 !== void 0 ? _ref2 : {};
  return {
    paymentSdk: new MpSdkAdapter(sdk),
    metrics: new CoreMonitorMetricsAdapter(sdk, superTokenJsVersion, params),
    dom: new WooDomAdapter(),
    variantConfig: new VariantConfigAdapter(scriptsParams)
  };
}
;// ./assets/js/checkouts/super-token/adapters/platform/SdkReadinessWatcher.ts
const MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
const CARD_FORM_MOUNTED_EVENT = 'mp_card_form_mounted';
const FALLBACK_POLL_INTERVAL_MS = 50;
const FALLBACK_POLL_MAX_WAIT_MS = 15000;
const COMPOSE_RETRY_INTERVAL_MS = 1000;
const INIT_SOURCE = {
  ALREADY_READY: 'already_ready',
  SDK_INSTANCE_EVENT: 'sdk_event',
  SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW: 'sdk_event_recovered',
  FALLBACK_POLL: 'fallback_poll',
  CARD_FORM_RECOVERY: 'card_form_recovery'
};
/**
 * Watches for the MP SDK instance and completes Super Token composition exactly once,
 * using three tiers in order of availability:
 *
 *   1. Already present at construction time  → compose immediately (ALREADY_READY)
 *   2. Arrives via `mp_sdk_instance_ready`   → compose on event (SDK_INSTANCE_EVENT
 *                                               or SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW if >15s)
 *   3. Appears in `window.mpSdkInstance`     → discover on the 50ms poll (FALLBACK_POLL),
 *                                               retry failed composition every 1s, capped at 15s
 *
 * After the poll cap, temporary load delays can still be recovered via
 * recoverIfSdkIsNowAvailable() — call it at natural page checkpoints
 * (e.g. when the card form mounts) to give a second chance before giving up.
 *
 * Reports `super_token_sdk_loaded` and `super_token_init_source` (with elapsed_ms)
 * through the MetricsPort. Never writes to `window.*`.
 */
class SdkReadinessWatcher {
  initialized = false;
  pendingCompose = null;
  activePoll = null;
  nextComposeAttemptAt = 0;
  failedComposeAttempts = 0;
  reportRetryExhausted = null;
  constructor(deps) {
    var _deps$readSdkInstance, _deps$now;
    this.metrics = deps.metrics;
    this.readSdkInstance = (_deps$readSdkInstance = deps.readSdkInstance) !== null && _deps$readSdkInstance !== void 0 ? _deps$readSdkInstance : () => window.mpSdkInstance;
    this.now = (_deps$now = deps.now) !== null && _deps$now !== void 0 ? _deps$now : () => Date.now();
    this.startedAt = this.now();
  }

  /**
   * Begins watching for SDK availability and retries `compose` at a bounded cadence until the
   * first successful composition.
   * The compose callback is stored so recoverIfSdkIsNowAvailable() can use it later.
   *
   * Idempotent: a second call is silently ignored to guard against accidental
   * double-registration of the event listener and poll (TASK-013 wiring safety).
   *
   * Note for TASK-013: in the already_ready path compose() is called synchronously
   * and any exception it throws propagates out of start(). Wrap start() in a
   * try-catch at the bundle entrypoint so a temporary compose failure does not abort
   * the bundle bootstrap before recoverIfSdkIsNowAvailable() has a chance to retry.
   */
  start(compose, reportRetryExhausted) {
    if (this.pendingCompose !== null) {
      return;
    }
    this.pendingCompose = compose;
    this.reportRetryExhausted = reportRetryExhausted !== null && reportRetryExhausted !== void 0 ? reportRetryExhausted : null;
    if (this.readSdkInstance()) {
      this.composeWith(INIT_SOURCE.ALREADY_READY);
      if (this.initialized) {
        return;
      }
    }
    document.addEventListener(MP_SDK_INSTANCE_READY_EVENT, () => this.composeWith(INIT_SOURCE.SDK_INSTANCE_EVENT), {
      once: true
    });
    this.activePoll = setInterval(() => {
      if (this.readSdkInstance() && this.now() >= this.nextComposeAttemptAt) {
        this.composeWith(INIT_SOURCE.FALLBACK_POLL);
      }
    }, FALLBACK_POLL_INTERVAL_MS);
    setTimeout(() => {
      if (this.activePoll !== null) {
        clearInterval(this.activePoll);
        this.activePoll = null;
      }
      if (!this.initialized && this.failedComposeAttempts > 0) {
        this.reportRetryExhausted?.(this.failedComposeAttempts);
      }
    }, FALLBACK_POLL_MAX_WAIT_MS);
  }

  /**
   * Registers a listener that calls recoverIfSdkIsNowAvailable() when the card form
   * mounts. Call this after start() to cover the case where the SDK arrives after the
   * 15s poll window.
   *
   * Intended wiring point for the composition root (TASK-013):
   *   watcher.start(compose);
   *   watcher.registerFormMountedRecovery();
   */
  registerFormMountedRecovery() {
    document.addEventListener(CARD_FORM_MOUNTED_EVENT, () => this.recoverIfSdkIsNowAvailable());
  }

  /**
   * Recovery entry point for temporary SDK load delays.
   *
   * Call this at a natural page checkpoint after the poll window closes (e.g. when
   * the card form mounts). If the SDK is now available and composition has not yet
   * happened, composes with source CARD_FORM_RECOVERY. Does nothing if already
   * initialized or if the SDK is still unavailable.
   */
  recoverIfSdkIsNowAvailable() {
    if (this.initialized || !this.pendingCompose) {
      return;
    }
    if (!this.readSdkInstance()) {
      return;
    }
    this.composeWith(INIT_SOURCE.CARD_FORM_RECOVERY);
  }
  composeWith(source) {
    if (this.initialized || !this.readSdkInstance() || !this.pendingCompose) {
      return;
    }
    const composed = this.pendingCompose();
    if (composed === false) {
      this.failedComposeAttempts += 1;
      // The 50 ms poll discovers SDK availability; it must not also hammer a failing composition.
      // Keep recovery automatic, but retry the expensive construction at a bounded cadence.
      this.nextComposeAttemptAt = this.now() + COMPOSE_RETRY_INTERVAL_MS;
      return;
    }
    if (this.activePoll !== null) {
      clearInterval(this.activePoll);
      this.activePoll = null;
    }
    this.initialized = true;
    this.metrics.superTokenSdkLoaded();
    this.reportInitSource(source);
  }
  reportInitSource(source) {
    const elapsedMs = this.now() - this.startedAt;
    const arrivedLateViaSdkEvent = source === INIT_SOURCE.SDK_INSTANCE_EVENT && elapsedMs > FALLBACK_POLL_MAX_WAIT_MS;
    const reportedSource = arrivedLateViaSdkEvent ? INIT_SOURCE.SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW : source;
    this.metrics.reportInitSource(reportedSource, elapsedMs);
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/InitializationHealthChecker.ts

const INIT_CHECK_SESSION_KEY = 'mp_super_token_init_checked';
/**
 * Checks the health of the Super Token initialization after the card form mounts,
 * and reports the outcome through the MetricsPort exactly once per session.
 *
 * Validates against the composition-root instances (not `window.*`), using a
 * fail-fast chain: SDK present → all classes present → trigger handler listening →
 * success. The first failure found is reported and remaining checks are skipped.
 *
 * The sessionStorage dedup key ensures the check fires at most once per page load,
 * regardless of how many times the form mounts.
 */
class InitializationHealthChecker {
  constructor(deps) {
    var _deps$readSdkInstance, _deps$session;
    this.metrics = deps.metrics;
    this.getInstances = deps.getInstances;
    this.readSdkInstance = (_deps$readSdkInstance = deps.readSdkInstance) !== null && _deps$readSdkInstance !== void 0 ? _deps$readSdkInstance : () => window.mpSdkInstance;
    this.session = (_deps$session = deps.session) !== null && _deps$session !== void 0 ? _deps$session : window.sessionStorage;
  }

  /**
   * Registers a listener that calls check() when the card form mounts.
   *
   * Intended wiring point for the composition root (TASK-013):
   *   checker.registerFormMountedCheck();
   */
  registerFormMountedCheck() {
    document.addEventListener(CARD_FORM_MOUNTED_EVENT, () => this.check(CARD_FORM_MOUNTED_EVENT));
  }

  /**
   * Runs the initialization health check.
   * `dispatchedFrom` identifies the event or trigger that called this method
   * (e.g. `'mp_card_form_mounted'`); it is included in every metric message.
   *
   * The sessionStorage dedup flag is only consumed on conclusive outcomes
   * (trigger not listening, success, unexpected error). When the SDK or instances
   * are absent the flag is NOT set, so a subsequent call can re-evaluate once the
   * composition root has had a chance to compose (e.g. after SdkReadinessWatcher
   * recovers on the same event). This prevents a false-permanent failure when the
   * health check runs before the recovery watcher on the same mp_card_form_mounted
   * dispatch — see adapters/platform/README.md TASK-013 pre-conditions.
   */
  check(dispatchedFrom) {
    const origin = dispatchedFrom || 'unknown';
    if (this.session.getItem(INIT_CHECK_SESSION_KEY) === 'true') {
      return;
    }
    let conclusive = false;
    try {
      if (!this.readSdkInstance()) {
        this.metrics.mpSdkInstanceNotExists(origin);
        return;
      }
      const instances = this.getInstances();
      if (!this.allInstancesArePresent(instances)) {
        this.metrics.superTokenClassesNotExist(this.buildMissingSummary(instances), origin);
        return;
      }
      conclusive = true;
      if (!instances.triggerHandler.isAlreadyListeningForm) {
        this.metrics.superTokenTriggerHandlerNotListening(origin);
        return;
      }
      this.metrics.superTokenInitializationSuccess(origin);
    } catch (error) {
      conclusive = true;
      this.metrics.superTokenInitializationError(error, origin);
    } finally {
      if (conclusive) {
        this.session.setItem(INIT_CHECK_SESSION_KEY, 'true');
      }
    }
  }
  allInstancesArePresent(instances) {
    return Boolean(instances && instances.metrics && instances.paymentMethods && instances.authenticator && instances.errorHandler && instances.triggerHandler);
  }
  buildMissingSummary(instances) {
    return `${instances?.metrics ? '' : 'Metrics class did not load. '}` + `${instances?.paymentMethods ? '' : 'Payment Methods class did not load. '}` + `${instances?.authenticator ? '' : 'Authenticator class did not load. '}` + `${instances?.errorHandler ? '' : 'Error Handler class did not load. '}` + `${instances?.triggerHandler ? '' : 'Trigger Handler class did not load.'}`;
  }
}
;// ./assets/js/checkouts/super-token/adapters/platform/index.ts










;// ./assets/js/checkouts/super-token/useCases/FinalizeSuperTokenPayment.ts
/**
 * Canonical Super Token finalization (RN-1) — the single source shared by the Classic
 * (`event-handler.js:397-419`) and Blocks (`custom.block.js:77-117`) checkouts, which
 * today run the same sequence copied in two places (root cause of PSW-3737/PSW-4113).
 *
 * The use case drives the injected session/authenticator through the exact legacy order
 * and returns a neutral typed result (RN-3); the checkout adapters translate it. It holds
 * NO checkout logic (loader, jQuery submit, emitResponse) — those, plus the flow-specific
 * steps that genuinely differ between the two checkouts (the click metric, the Blocks
 * validation pre-branch), live in the adapters. Error classification is the single source
 * `isRecoverable` (RN-2).
 */



/** Subset of `MPSuperTokenPaymentMethods` the finalization spine drives. */

/** Subset of `MPSuperTokenAuthenticator` the finalization spine drives. */

class FinalizeSuperTokenPayment {
  async execute(ctx) {
    const {
      paymentMethods,
      authenticator,
      isOrderPayPage
    } = ctx;
    try {
      if (!paymentMethods) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
      }
      if (!authenticator) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);
      }
      const activeMethod = paymentMethods.getActivePaymentMethod();
      const isSelectionValid = !!activeMethod && paymentMethods.isSelectedPaymentMethodValid();
      if (!activeMethod) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);
      }
      if (!isSelectionValid) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);
      }

      // validateInstallmentSelection already renders the errors when it returns false
      // (legacy payment-methods.js:2512); the adapter only has to drop the loader.
      if (!paymentMethods.validateInstallmentSelection()) {
        return {
          status: 'validation_error'
        };
      }
      if (isOrderPayPage) {
        paymentMethods.unmountCardForm();
      }
      await paymentMethods.updateSecurityCode();
      await authenticator.authorizePayment(activeMethod.token);
      authenticator.setSuperTokenValidation(true);
      return {
        status: 'success'
      };
    } catch (exception) {
      const errorCode = exception?.message;
      if (errorCode === ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
        return {
          status: 'validation_error',
          errorCode,
          error: exception
        };
      }
      authenticator?.setSuperTokenValidation(false);
      return {
        status: isRecoverable(errorCode) ? 'recoverable_error' : 'fatal_error',
        errorCode,
        error: exception
      };
    }
  }
}
;// ./assets/js/checkouts/super-token/useCases/SelectSavedPaymentMethod.ts
/**
 * Selecting a saved Super Token payment method — the application sequence of
 * `onSelectSuperTokenPaymentMethod` (payment-methods.js:709-737). Preserves the legacy
 * order and its two decisions (skip when the method is already selected; mount the
 * security-code field only when the ESC verification returns a method). The DOM and
 * timing primitives are injected session operations, named after what the legacy does.
 */

/** Subset of `MPSuperTokenPaymentMethods` used to select a saved method. */

/** Subset of `MPSuperTokenMetrics` emitted during selection. */

class SelectSavedPaymentMethod {
  async execute(ctx) {
    const {
      session,
      metrics,
      paymentMethod,
      paymentMethodElement
    } = ctx;
    if (session.paymentMethodAlreadySelected(paymentMethod)) {
      return;
    }
    metrics.sendMetric('super_token_withdraw', 'false', '');
    session.emitEventFromSelectPaymentMethod(paymentMethod);
    metrics.registerSelectPaymentMethod(paymentMethod.type);
    session.storeActivePaymentMethod(paymentMethod);
    session.hideAllPaymentMethodDetails();
    session.closeAccordion();
    session.deselectAllPaymentMethods();
    session.selectPaymentMethod(paymentMethodElement);
    session.fillCardTokenFields(paymentMethod);
    session.setCheckoutTypeToSuperToken();
    session.showPaymentMethodDetails(paymentMethodElement);
    session.handleInstallmentsWithoutFeePillVisibility();
    const verifiedPaymentMethod = await session.handleWithEscPaymentMethod(paymentMethod, paymentMethodElement);
    if (verifiedPaymentMethod !== null) {
      session.mountSecurityCodeField(verifiedPaymentMethod);
    }
    session.notifySelectionSettled();
  }
}
;// ./assets/js/checkouts/super-token/useCases/ResetFlow.ts
/**
 * Resetting the Super Token flow after an error — the application sequence of
 * `resetSuperTokenOnError(preserveSelection)` (trigger-handler.js:196-222). Owns the
 * decision (whether to preserve the last selection and its installments) and the fixed
 * order of the reset operations; the DOM reads/writes and the deeper `resetCustomCheckout`
 * dance are injected session operations, named after what the legacy does.
 *
 * `preserveSelection` mirrors the recoverable-error case, where the buyer retries without
 * losing the previously chosen method (RN-2).
 */

/** Subset of `MPSuperTokenTriggerHandler`/`MPSuperTokenPaymentMethods` used on reset. */

class ResetFlow {
  execute(ctx) {
    const {
      session,
      preserveSelection
    } = ctx;
    if (!session.isSuperTokenCheckoutActive()) {
      return;
    }
    session.scrollPaymentMethodListIntoView();
    session.storeSavedInstallments(null);
    let lastMethodToPreserve = null;
    if (preserveSelection) {
      lastMethodToPreserve = session.getLastPaymentMethodChoosen() || null;
      session.storeSavedInstallments(session.getSelectedInstallments());
    }
    session.deselectAllPaymentMethods();
    session.hideAllPaymentMethodDetails();
    session.unmountActiveSecurityCodeInstance();
    session.clearActivePaymentMethod();
    session.resetCustomCheckout(true);
    if (lastMethodToPreserve) {
      session.storeSelectedPreloadedPaymentMethod(lastMethodToPreserve);
    }
  }
}
;// ./assets/js/checkouts/super-token/useCases/GetAccountPaymentMethods.ts
/**
 * Loading the buyer's account payment methods — the application sequence of
 * `getAccountPaymentMethods(amount, buyerEmail)` (super-token-authenticator.js:144-180).
 * Owns the fixed order and its three fail-safe gates (not simplified auth, no fast payment
 * token, empty methods), always resolving to `null` on any failure so the load never blocks
 * the checkout. The SDK/authenticator handle and its stored state are injected session
 * operations, named after what the legacy does; the metrics mirror the legacy signals.
 *
 * The `authenticator` handle stays opaque here: the use case only threads it between the
 * session steps that build, verify and consume it. Storing it (and the fast payment token)
 * keeps the still-legacy `authorizePayment` and the plugin consumers seeing the same state.
 */



/** Subset of `MPSuperTokenAuthenticator`/`MPSuperTokenPaymentMethods` used to load methods. */

/** Subset of `MPSuperTokenMetrics` emitted while loading the account payment methods. */

class GetAccountPaymentMethods {
  async execute(ctx) {
    const {
      session,
      metrics,
      amount,
      buyerEmail
    } = ctx;
    try {
      const authenticator = await session.buildAuthenticator(amount, buyerEmail);
      if (!authenticator) {
        return null;
      }
      session.storeAuthenticator(authenticator);
      const isSimplified = await session.getSimplifiedAuth(authenticator);
      if (!isSimplified) {
        metrics.isNotSimplifiedAuth();
        return null;
      }
      session.notifyBehaviorTrackingInit();
      metrics.canUseSuperToken(true);
      const fastPaymentToken = await session.getFastPaymentToken(authenticator);
      if (!fastPaymentToken) {
        metrics.cannotGetFastPaymentToken();
        return null;
      }
      session.storeFastPaymentToken(fastPaymentToken);
      const accountPaymentMethods = await session.fetchAccountPaymentMethods(fastPaymentToken);
      if (!accountPaymentMethods?.data?.length) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.EMPTY_ACCOUNT_PAYMENT_METHODS);
      }
      return accountPaymentMethods.data;
    } catch (error) {
      metrics.errorToGetAccountPaymentMethods(error);
      return null;
    }
  }
}
;// ./assets/js/checkouts/super-token/useCases/AuthorizePayment.ts
/**
 * Authorizing the payment at submit — the application sequence of
 * `authorizePayment(pseudotoken)` (super-token-authenticator.js:182-207). Owns the fixed
 * order (stored handle → re-verify simplified auth → authorize on the SDK → store the
 * authorized pseudotoken) and, crucially, the error *classification*: it always throws a
 * typed error code on failure (USER_CANCELLED vs generic), which the consumers
 * (event-handler.js / custom.block.js) branch on. The SDK call and DOM/state writes are
 * injected session operations.
 *
 * Unlike the load, this is NOT fail-safe: the throw is the contract — the caller must learn
 * the payment did not authorize — so callers must let it propagate, never swallow it.
 */


/** Legacy metric name for an auth that expired between load and submit (authenticator.js:189). */
const AUTH_EXPIRED_ON_SUBMIT_METRIC = 'super_token_auth_expired_on_submit';
/** SDK error message fragment that marks a buyer-cancelled authorization (authenticator.js:203). */
const USER_CANCELLED = 'USER_CANCELLED';

/** Subset of `MPSuperTokenAuthenticator` used to authorize at submit. */

/** Subset of `MPSuperTokenMetrics` emitted while authorizing. */

class AuthorizePayment {
  async execute(ctx) {
    const {
      session,
      metrics,
      pseudotoken
    } = ctx;
    try {
      const authenticator = session.getStoredAuthenticator();
      if (!authenticator) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.AUTHENTICATOR_NOT_FOUND);
      }
      const hasSimplified = await session.getSimplifiedAuth(authenticator);
      if (!hasSimplified) {
        metrics.sendMetric(AUTH_EXPIRED_ON_SUBMIT_METRIC, 'true', '');
        return;
      }
      await session.authorizePaymentOnSdk(authenticator, pseudotoken);
      session.storeAuthorizedPseudotoken(pseudotoken);
    } catch (error) {
      metrics.errorToAuthorizePayment(error);

      // The SDK may reject with a non-Error carrying the message (plain object or a
      // cross-realm error); classify on the message like the legacy path, not `instanceof`.
      const rawMessage = error?.message;
      const message = typeof rawMessage === 'string' ? rawMessage : '';
      if (message.includes(USER_CANCELLED)) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED);
      }
      throw new Error(ErrorClassification_MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR);
    }
  }
}
;// ./assets/js/checkouts/super-token/useCases/FetchAndRenderPaymentMethods.ts
/**
 * Fetching and rendering the buyer's saved payment methods — the inner load step of the legacy
 * `fetchAndRenderSuperTokenPaymentMethods` (super-token-trigger-handler.js:243-275). Owns the
 * e-mail gate (capture → validate → skip metrics), the fetching flag, and the load-generation
 * guard around the async fetch, so a load cancelled or superseded mid-flight renders nothing.
 *
 * The RAW methods are handed to the renderer unchanged: ordering and decoration stay in the
 * render path (organizePaymentMethodsElements → the order+decorate seam), its single owner. The
 * fetch is grounded in the legacy `mpSuperTokenAuthenticator.getAccountPaymentMethods`, which
 * already delegates to the refactored load seam (Phase 7), so this step composes on it rather
 * than re-wrapping the SDK auth flow. Fail-safe: every step returns without throwing, so a
 * failed load never blocks the checkout.
 */

/** Subset of the legacy trigger handler (and the collaborators it holds) the fetch+render step drives. */

/** Subset of `MPSuperTokenMetrics` emitted while gating the load on the buyer e-mail. */

class FetchAndRenderPaymentMethods {
  async execute(ctx) {
    const {
      session,
      metrics
    } = ctx;
    const buyerEmail = session.getBuyerEmail();
    if (!buyerEmail) {
      metrics.skippedNoEmail();
      return;
    }

    // The SDK rejects invalid e-mails; validate before fetching to avoid provider errors.
    if (!session.isValidEmail(buyerEmail)) {
      metrics.skippedInvalidEmail();
      return;
    }
    metrics.emailCaptured();
    session.setFetching(true);
    const generation = session.getLoadGeneration();
    let paymentMethods;
    try {
      paymentMethods = await session.fetchAccountPaymentMethods(session.currentAmount(), buyerEmail);
    } catch (_) {
      // Transient fetch failure: only release the flag if we still own this load, so a newer
      // generation that superseded us keeps ownership. Without this the flag stays stuck at true
      // and LoadSuperToken discards every reload without an amount/e-mail change — no recovery.
      if (session.getLoadGeneration() === generation) {
        session.setFetching(false);
      }
      return;
    }
    // A newer load (or a cancel) bumped the generation while we awaited — drop this stale result.
    if (session.getLoadGeneration() !== generation) {
      return;
    }
    session.setFetching(false);
    if (!paymentMethods || !paymentMethods.length) {
      return;
    }
    session.renderAccountPaymentMethods(paymentMethods, session.currentAmount());
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyTriggerSession.ts
/**
 * Session adapter that lets the refactored `FetchAndRenderPaymentMethods` use case drive the
 * still-legacy `MPSuperTokenTriggerHandler.fetchAndRenderSuperTokenPaymentMethods`
 * (super-token-trigger-handler.js:243-275). The use case owns the load *order* (e-mail gate,
 * fetching flag, generation guard); this adapter supplies the *primitives*, forwarding each to
 * the legacy trigger handler instance and the collaborators it holds — its wcEmailListener, the
 * authenticator for the fetch (itself already delegating to the load seam) and the controller
 * for the render — a transitional scaffold while the primitives are ported.
 *
 * The metric name strings stay here (the legacy boundary), exposed to the use case as named
 * intentions through `createFetchAndRenderMetrics`, keeping them out of the domain.
 *
 * The same adapter also backs the refactored `CancelLoad` (super-token-trigger-handler.js:237-241):
 * cancel touches the same load-state cluster (fetching flag, load generation) plus the controller
 * reset, so it reuses this session rather than a second scaffold onto the same trigger handler.
 */

/** The subset of the legacy trigger handler (and the collaborators it holds) the fetch+render and cancel steps call. */

class LegacyTriggerSession {
  constructor(triggerHandler) {
    this.triggerHandler = triggerHandler;
  }
  getBuyerEmail() {
    return this.triggerHandler.getBuyerEmail();
  }
  isValidEmail(email) {
    return this.triggerHandler.wcEmailListener.isValid(email);
  }
  setFetching(isFetching) {
    this.triggerHandler.isFetchingPaymentMethods = isFetching;
  }
  getLoadGeneration() {
    return this.triggerHandler.loadGeneration;
  }
  currentAmount() {
    return this.triggerHandler.currentAmount;
  }
  fetchAccountPaymentMethods(amount, buyerEmail) {
    return this.triggerHandler.mpSuperTokenAuthenticator.getAccountPaymentMethods(amount, buyerEmail);
  }
  renderAccountPaymentMethods(paymentMethods, amount) {
    return this.triggerHandler.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(paymentMethods, amount);
  }
  bumpLoadGeneration() {
    this.triggerHandler.loadGeneration++;
  }
  resetPaymentMethods() {
    this.triggerHandler.mpSuperTokenPaymentMethods.reset();
  }
}

/** Legacy `sendMetric` names from super-token-trigger-handler.js:246,252,256. */
const SKIPPED_NO_EMAIL_METRIC = 'super_token_skipped_no_email';
const SKIPPED_INVALID_EMAIL_METRIC = 'super_token_skipped_invalid_email';
const EMAIL_CAPTURED_METRIC = 'super_token_email_captured';

/** The subset of the legacy `MPSuperTokenMetrics` the e-mail gate reports through. */

/**
 * Wraps the legacy metrics instance as the use case's named metric intentions, keeping the
 * metric name strings at this legacy boundary rather than in the domain.
 */
function createFetchAndRenderMetrics(metrics) {
  return {
    skippedNoEmail: () => metrics.sendMetric(SKIPPED_NO_EMAIL_METRIC, 'true', ''),
    skippedInvalidEmail: () => metrics.sendMetric(SKIPPED_INVALID_EMAIL_METRIC, 'true', ''),
    emailCaptured: () => metrics.sendMetric(EMAIL_CAPTURED_METRIC, 'true', '')
  };
}
;// ./assets/js/checkouts/super-token/useCases/LoadSuperToken.ts
/**
 * Loading the Super Token payment methods — the orchestration of the legacy
 * `MPSuperTokenTriggerHandler.loadSuperToken` (super-token-trigger-handler.js:286-330), the
 * entry point the Classic `event-handler.js` and the Blocks `cart-update.helper.js` call on
 * every amount refresh. Owns the *order*: format + store the amount, the debounce guard (skip a
 * redundant re-fetch), the amount-change reset, the cache short-circuit (re-render the stored
 * methods), the one-time e-mail-listener registration, the fetch+render, and the one-time stale
 * cache metrics.
 *
 * The fetch+render step delegates to the refactored `FetchAndRenderPaymentMethods` (Phase 7b)
 * through the session, so this composes on it rather than re-implementing the load. The
 * e-mail-listener registration delegates to the refactored `EnsureEmailListenerRegistered` use
 * case through the `mpSuperTokenEnsureEmailListenerRegistered` seam (Phase 10), completing the
 * chain from e-mail change to the TS reset flow.
 */

/** Subset of the legacy trigger handler the load orchestration drives. */

/** Subset of `MPSuperTokenMetrics` emitted by the load orchestration. */

class LoadSuperToken {
  async execute(ctx) {
    const {
      session,
      metrics,
      currentAmount
    } = ctx;
    session.setCurrentAmount(session.formatAmount(currentAmount));

    // Prevent unnecessary re-fetching of payment methods.
    if (session.isFetching() && !session.amountHasChanged() && !session.emailHasChanged()) {
      return;
    }
    if (session.amountHasChanged()) {
      session.resetFlow();
      metrics.resetOnAmountChange();
    }
    if (session.isMethodsLoaded()) {
      session.renderStored(session.currentAmount());
      return;
    }
    session.ensureEmailListenerRegistered();
    await session.fetchAndRender();
    session.dispatchStaleCacheMetricsOnce();
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyLoadOrchestrationSession.ts
/**
 * Session adapter that lets the refactored `LoadSuperToken` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.loadSuperToken` (super-token-trigger-handler.js:286-330). The use
 * case owns the load *order* (format, debounce guard, amount-change reset, cache short-circuit,
 * e-mail-listener registration, fetch+render, stale metrics); this adapter supplies the
 * *primitives*, forwarding each to the legacy trigger handler instance and the collaborators it
 * holds — a transitional scaffold while the primitives are ported.
 *
 * `fetchAndRender` reaches the still-legacy `fetchAndRenderSuperTokenPaymentMethods`, itself
 * already delegating to the fetch+render seam (Phase 7b), so this composes on it. The
 * e-mail-listener registration and the stale-metrics dispatch are single legacy methods the
 * inline fallback also calls, kept here as forwarded primitives.
 *
 * The metric name string stays here (the legacy boundary), exposed to the use case as a named
 * intention through `createLoadSuperTokenMetrics`, keeping it out of the domain.
 */

/** The subset of the legacy trigger handler (and the collaborators it holds) the load orchestration calls. */

class LegacyLoadOrchestrationSession {
  constructor(triggerHandler) {
    this.triggerHandler = triggerHandler;
  }
  formatAmount(amount) {
    return this.triggerHandler.mpSuperTokenAuthenticator.formatAmount(amount);
  }
  setCurrentAmount(amount) {
    this.triggerHandler.currentAmount = amount;
  }
  currentAmount() {
    return this.triggerHandler.currentAmount;
  }
  isFetching() {
    return this.triggerHandler.isFetchingPaymentMethods;
  }
  amountHasChanged() {
    return this.triggerHandler.amountHasChanged();
  }
  emailHasChanged() {
    return this.triggerHandler.emailHasChanged();
  }
  resetFlow() {
    this.triggerHandler.resetFlow();
  }
  isMethodsLoaded() {
    return this.triggerHandler.isSuperTokenPaymentMethodsLoaded();
  }
  renderStored(amount) {
    const paymentMethods = this.triggerHandler.mpSuperTokenPaymentMethods;
    paymentMethods.renderAccountPaymentMethods(paymentMethods.getStoredPaymentMethods(), amount);
  }
  ensureEmailListenerRegistered() {
    this.triggerHandler.ensureEmailListenerRegistered();
  }
  fetchAndRender() {
    return this.triggerHandler.fetchAndRenderSuperTokenPaymentMethods();
  }
  dispatchStaleCacheMetricsOnce() {
    this.triggerHandler.dispatchStaleCacheMetricsOnce();
  }
}

/** Legacy `sendMetric` name from super-token-trigger-handler.js:294. */
const RESET_ON_AMOUNT_CHANGE_METRIC = 'super_token_reset_on_amount_change';

/** The subset of the legacy `MPSuperTokenMetrics` the load orchestration reports through. */

/**
 * Wraps the legacy metrics instance as the use case's named metric intention, keeping the metric
 * name string at this legacy boundary rather than in the domain.
 */
function createLoadSuperTokenMetrics(metrics) {
  return {
    resetOnAmountChange: () => metrics.sendMetric(RESET_ON_AMOUNT_CHANGE_METRIC, 'true', '')
  };
}
;// ./assets/js/checkouts/super-token/useCases/CancelLoad.ts
/**
 * Cancelling an in-flight Super Token load — the legacy
 * `MPSuperTokenTriggerHandler.cancelLoad` (super-token-trigger-handler.js:237-241), called by the
 * Classic `event-handler.js` when the buyer switches away from the custom method while a fetch is
 * still in flight. Bumps the load generation so the awaiting `FetchAndRenderPaymentMethods` drops
 * its stale result through its generation guard, clears the fetching flag, and resets the stored
 * payment methods.
 *
 * The three state pieces (`loadGeneration`, `isFetchingPaymentMethods`, the controller's stored
 * methods) are the same cluster the fetch+render (Phase 7a) and load orchestration (Phase 7c)
 * steps already drive, reached here through the same trigger session — no new legacy surface.
 */

/** Subset of the legacy trigger handler (and the controller it holds) the cancel drives. */

class CancelLoad {
  execute(ctx) {
    const {
      session
    } = ctx;

    // Bump first so a fetch awaiting mid-flight sees the changed generation and renders nothing.
    session.bumpLoadGeneration();
    session.setFetching(false);
    session.resetPaymentMethods();
  }
}
;// ./assets/js/checkouts/super-token/useCases/ResetCustomCheckout.ts
/**
 * Resetting the whole custom checkout — the legacy `MPSuperTokenTriggerHandler.resetCustomCheckout`
 * (super-token-trigger-handler.js:100-145), called after a recoverable error (through
 * `resetSuperTokenOnError`) and when the buyer changes the e-mail mid-flow. Owns the fixed order
 * of the reset head — hide the error, report a missing custom handler once, raise the spinner,
 * invalidate the token, remount the card form when methods are stored and clear the cache when
 * asked — then kicks off `loadSuperToken` and hands the async tail off to the session.
 *
 * `shouldClearCache` mirrors the legacy default: the error path clears it (fresh fetch), the
 * e-mail-change path also clears it; only internal callers could opt out. The tail
 * (`finalizeResetTail`) — the delayed spinner removal, the preloaded-method restore and the
 * deferred last-exception handling — stays a single injected session step for now, ported later.
 *
 * Fire-and-forget like the legacy: neither caller awaits it, so the load promise is intentionally
 * floated with its tail attached.
 */

/** Subset of the legacy trigger handler (and the collaborators it holds) the reset head drives. */

class ResetCustomCheckout {
  execute(ctx) {
    const {
      session,
      shouldClearCache
    } = ctx;
    session.hideSuperTokenError();
    session.reportCustomHandlerMissingOnReset();
    session.createLoadSpinner();
    session.setSuperTokenValidation(false);
    if (session.hasStoredPaymentMethods()) {
      session.remountCardForm();
    }
    if (shouldClearCache) {
      session.resetFlow();
    }
    void session.loadSuperToken(session.currentAmount()).finally(() => session.finalizeResetTail());
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyResetCustomCheckoutSession.ts
/**
 * Session adapter that lets the refactored `ResetCustomCheckout` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.resetCustomCheckout` (super-token-trigger-handler.js:100-145). The
 * use case owns the reset *order*; this adapter supplies the *primitives*, forwarding each to the
 * legacy trigger handler instance and the collaborators it holds — its controller
 * (`mpSuperTokenPaymentMethods`) and authenticator — or reading the legacy runtime globals the
 * spinner and the missing-handler metric touch, a transitional scaffold while they are ported.
 *
 * The custom-handler-missing metric strings and the once-guard stay here (the legacy boundary):
 * the use case just calls `reportCustomHandlerMissingOnReset` unconditionally, and this adapter
 * keeps the `window.mpCustomCheckoutHandler` check, the flag on the trigger handler and the metric
 * name out of the domain.
 *
 * The async tail (delayed spinner removal, preloaded-method restore, deferred last-exception
 * handling) stays the legacy `finalizeResetTail`, forwarded whole — the same method the inline
 * fallback runs, so both paths share one source.
 */

/** Legacy `sendMetric` call from super-token-trigger-handler.js:105 (custom handler missing on reset). */
const CUSTOM_HANDLER_MISSING_METRIC = 'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS';
const CUSTOM_HANDLER_MISSING_CONTEXT = 'resetCustomCheckout';
const INIT_ERROR_MESSAGE = 'mp_super_token_init_error';

/** The subset of the legacy controller the reset head reads through the trigger handler. */

/** The subset of the legacy `MPSuperTokenTriggerHandler` the reset head drives. */

class LegacyResetCustomCheckoutSession {
  constructor(triggerHandler) {
    this.triggerHandler = triggerHandler;
  }
  get controller() {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }
  hideSuperTokenError() {
    this.controller.hideSuperTokenError();
  }
  reportCustomHandlerMissingOnReset() {
    if (window.mpCustomCheckoutHandler || this.triggerHandler.customHandlerMissingReportedOnReset) {
      return;
    }
    if (typeof window.sendMetric !== 'function') {
      return;
    }
    window.sendMetric(CUSTOM_HANDLER_MISSING_METRIC, CUSTOM_HANDLER_MISSING_CONTEXT, INIT_ERROR_MESSAGE);
    this.triggerHandler.customHandlerMissingReportedOnReset = true;
  }
  createLoadSpinner() {
    window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
  }
  setSuperTokenValidation(isValid) {
    this.triggerHandler.mpSuperTokenAuthenticator.setSuperTokenValidation(isValid);
  }
  hasStoredPaymentMethods() {
    return this.controller.hasStoredPaymentMethods();
  }
  remountCardForm() {
    this.controller.unmountCardForm();
    this.controller.mountCardForm();
  }
  resetFlow() {
    this.triggerHandler.resetFlow();
  }
  currentAmount() {
    return this.triggerHandler.currentAmount;
  }
  loadSuperToken(currentAmount) {
    return this.triggerHandler.loadSuperToken(currentAmount);
  }
  finalizeResetTail() {
    this.triggerHandler.finalizeResetTail();
  }
}
;// ./assets/js/checkouts/super-token/useCases/HandleError.ts
/**
 * Handling a Super Token runtime error — the logic of `MPSuperTokenErrorHandler.handleError`
 * (super-token-error-handler.js:57-62). Owns the parse → metric → display sequence and the
 * branch that decides between a validation-error display (force show validation) and a generic
 * error display (convert code to message, then show). Returns the normalised error code so the
 * caller can store or classify it.
 *
 * Error parsing is inline: the legacy normalises exceptions to strings (`${exception}` for
 * objects, passthrough for strings) and falls back to `UNKNOWN_ERROR` when the result is empty.
 * No session method is needed for this step — it is pure coercion with no side effects.
 */


class HandleError {
  execute(ctx) {
    const {
      session,
      exception
    } = ctx;
    const normalized = typeof exception !== 'string' ? `${exception}` : exception;
    const code = normalized || ErrorClassification_MPSuperTokenErrorCodes.UNKNOWN_ERROR;
    const message = normalized || 'Unknown error';
    session.reportErrorMetric(code, message);
    if (code.includes(ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID)) {
      session.forceShowValidationErrors();
    } else {
      session.showError(session.getErrorMessage(code));
    }
    return code;
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyErrorHandlerSession.ts
/**
 * Session adapter that lets the refactored `HandleError` use case drive the still-legacy
 * `MPSuperTokenErrorHandler.handleError` (super-token-error-handler.js:57-62). The use case
 * owns the parse → metric → display sequence and the validation-error branch; this adapter
 * supplies the *primitives*, forwarding each to the legacy metrics instance and the payment-
 * methods controller — a transitional scaffold while they are ported into the tree.
 */

/** Subset of the legacy `MPSuperTokenMetrics` the error-handler reports through. */

/** Subset of the legacy `MPSuperTokenPaymentMethods` the error-handler displays through. */

class LegacyErrorHandlerSession {
  constructor(metrics, controller) {
    this.metrics = metrics;
    this.controller = controller;
  }
  reportErrorMetric(code, message) {
    this.metrics.errorOnSubmit(code, message);
  }
  forceShowValidationErrors() {
    this.controller.forceShowValidationErrors();
  }
  getErrorMessage(code) {
    return this.controller.convertErrorCodeToErrorMessage(code);
  }
  showError(message) {
    this.controller.showSuperTokenError(message);
  }
}
;// ./assets/js/checkouts/super-token/useCases/RestorePreloadedPaymentMethod.ts
/**
 * Restoring the preloaded payment method after a reset — the logic of
 * `MPSuperTokenTriggerHandler.restorePreloadedPaymentMethod`
 * (super-token-trigger-handler.js:166-213), called asynchronously by `finalizeResetTail` after
 * the spinner delay. Owns the selection sequence and the four early-exit branches that each emit
 * a distinct restore metric; the DOM reads/writes and the legacy controller calls are injected
 * session operations.
 *
 * Error path (no preloaded method with a checkout error): re-selects the last chosen method so
 * the buyer doesn't land on an empty state.
 * Happy path: selects the preloaded method, shows its details, and restores the installments
 * dropdown to the value that was saved before the reset.
 */

const RESTORE_ACTIVE_METHOD_NOT_SET_METRIC = 'super_token_restore_active_method_not_set';
const RESTORE_ELEMENT_NOT_FOUND_METRIC = 'super_token_restore_element_not_found';
const RESTORE_DROPDOWN_NOT_FOUND_METRIC = 'super_token_restore_installments_dropdown_not_found';
const RESTORE_OPTION_NOT_FOUND_METRIC = 'super_token_restore_installment_option_not_found';

/** The four restore-failure metric names, as a closed contract the metrics adapter emits. */

class RestorePreloadedPaymentMethod {
  async execute(ctx) {
    const {
      session
    } = ctx;
    if (!session.getPreloadedPaymentMethod()) {
      if (session.hasCheckoutError()) {
        session.selectLastChosenMethod();
      }
      return;
    }
    await session.selectPreloadedMethod();
    session.clearPreloadedMethod();
    const activeMethod = session.getActiveMethod();
    const savedInstallments = session.getSavedInstallments();
    session.clearSavedInstallments();
    if (!activeMethod) {
      session.reportRestoreMetric(RESTORE_ACTIVE_METHOD_NOT_SET_METRIC);
      return;
    }
    const element = session.getMethodElement(activeMethod);
    if (!element) {
      session.reportRestoreMetric(RESTORE_ELEMENT_NOT_FOUND_METRIC);
      return;
    }
    session.showMethodDetails(element);
    if (!savedInstallments) return;
    const dropdown = session.getInstallmentsDropdown(activeMethod, element);
    if (!dropdown) {
      session.reportRestoreMetric(RESTORE_DROPDOWN_NOT_FOUND_METRIC);
      return;
    }
    if (!session.hasInstallmentOption(dropdown, savedInstallments)) {
      session.reportRestoreMetric(RESTORE_OPTION_NOT_FOUND_METRIC);
      return;
    }
    session.applyInstallmentsSelection(dropdown, savedInstallments);
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyRestoreSession.ts
/**
 * Session adapter that lets the refactored `RestorePreloadedPaymentMethod` use case drive the
 * still-legacy restore sequence (trigger-handler.js:166-213). The use case owns the selection
 * sequence and the four early-exit branches; this adapter supplies the *primitives*, forwarding
 * each to the legacy payment-methods controller, the trigger handler's `savedInstallments` field,
 * the legacy metrics, or the DOM — a transitional scaffold while the primitives are ported.
 *
 * The restore metric name, value, and message are all fixed (`name, 'true',
 * 'mp_super_token_restore_error'`); the adapter encodes the value and message so the use case
 * only passes the varying name.
 *
 * The installments dropdown is looked up via `paymentMethodIdentifier` (legacy controller) and
 * the element's own `querySelector`; option membership and value application are also DOM
 * operations kept here at the legacy boundary.
 */

const RESTORE_METRIC_VALUE = 'true';
const RESTORE_METRIC_MESSAGE = 'mp_super_token_restore_error';
const INSTALLMENTS_DROPDOWN_PREFIX = 'mp-super-token-installments-select-';
const CARD_INSTALLMENTS_INPUT_ID = 'cardInstallments';

/** Subset of the legacy `MPSuperTokenPaymentMethods` the restore sequence reads. */

/** Subset of the legacy `MPSuperTokenMetrics` the restore sequence reports through. */

/** Subset of the legacy trigger handler the restore sequence reads for `savedInstallments`. */

class LegacyRestoreSession {
  constructor(triggerHandler, metrics) {
    this.triggerHandler = triggerHandler;
    this.metrics = metrics;
  }
  get controller() {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }
  getPreloadedPaymentMethod() {
    return this.controller.getSelectedPreloadedPaymentMethod();
  }
  hasCheckoutError() {
    return this.controller.hasCheckoutError();
  }
  selectLastChosenMethod() {
    this.controller.selectLastPaymentMethodChoosen();
  }
  selectPreloadedMethod() {
    return this.controller.selectPreloadedPaymentMethod();
  }
  clearPreloadedMethod() {
    this.controller.storeSelectedPreloadedPaymentMethod(null);
  }
  getActiveMethod() {
    return this.controller.getActivePaymentMethod();
  }
  getSavedInstallments() {
    return this.triggerHandler.savedInstallments;
  }
  clearSavedInstallments() {
    this.triggerHandler.savedInstallments = null;
  }
  reportRestoreMetric(name) {
    this.metrics.sendMetric(name, RESTORE_METRIC_VALUE, RESTORE_METRIC_MESSAGE);
  }
  getMethodElement(method) {
    return this.controller.getPaymentMethodElementFromDOM(method);
  }
  showMethodDetails(element) {
    this.controller.showPaymentMethodDetails(element);
  }
  getInstallmentsDropdown(method, element) {
    const id = `${INSTALLMENTS_DROPDOWN_PREFIX}${this.controller.paymentMethodIdentifier(method)}`;
    return element.querySelector(`#${id}`);
  }
  hasInstallmentOption(dropdown, value) {
    return [...dropdown.options].some(o => o.value === value);
  }
  applyInstallmentsSelection(dropdown, value) {
    dropdown.value = value;
    const cardInstallments = document.getElementById(CARD_INSTALLMENTS_INPUT_ID);
    if (cardInstallments) cardInstallments.value = value;
    dropdown.dispatchEvent(new Event('change'));
  }
}
;// ./assets/js/checkouts/super-token/useCases/EnsureEmailListenerRegistered.ts
/**
 * Registering the e-mail change listener for the Super Token flow — the logic of
 * `MPSuperTokenTriggerHandler.ensureEmailListenerRegistered`
 * (super-token-trigger-handler.js:319-335). Owns the once-guard, the callback registration,
 * and the mark-as-listening step; the callback itself owns the decision to trigger a reset
 * when the buyer changes the e-mail mid-flow.
 *
 * The callback fires on every e-mail change event but resets only when all four conditions
 * hold: the new address is valid, the current amount is present, the address is different
 * from the stored one, and the stored address is already known (non-null). The first
 * meaningful e-mail captured by `loadSuperToken` acts as the baseline; subsequent distinct
 * values trigger a full reset so the saved cards are re-fetched for the new account.
 */

class EnsureEmailListenerRegistered {
  execute(ctx) {
    const {
      session
    } = ctx;
    if (session.isListening()) return;
    session.registerEmailChangeCallback(async (email, isValid) => {
      if (!isValid || !session.currentAmount()) return;
      if (session.isDifferentEmail(email) && session.isBuyerEmailKnown()) {
        session.setBuyerEmail(email);
        session.reportEmailChangeMetric();
        session.triggerReset();
      }
    });
    session.setupEmailChangeHandlers();
    session.markAsListening();
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyEmailListenerSession.ts
/**
 * Session adapter that lets the refactored `EnsureEmailListenerRegistered` use case drive the
 * still-legacy e-mail listener registration (trigger-handler.js:319-335). The use case owns
 * the once-guard, callback registration, and the reset decision; this adapter supplies the
 * *primitives*, forwarding each to the legacy trigger handler, its wcEmailListener and the
 * metrics instance — a transitional scaffold while the primitives are ported.
 *
 * The metric name string for the e-mail-change reset is kept here (the legacy boundary) so
 * the domain stays free of metric names.
 *
 * `triggerReset()` delegates to the legacy `resetCustomCheckout()` method on the trigger
 * handler, which in turn delegates to `window.mpSuperTokenResetCustomCheckout` (the
 * `ResetCustomCheckout` seam), completing the chain from e-mail change to the TS reset flow.
 */

const EMAIL_CHANGE_METRIC = 'super_token_reset_on_email_change';

/** The subset of the legacy `MPSuperTokenTriggerHandler` the email-listener step uses. */

class LegacyEmailListenerSession {
  constructor(triggerHandler) {
    this.triggerHandler = triggerHandler;
  }
  isListening() {
    return this.triggerHandler.isAlreadyListeningForm;
  }
  registerEmailChangeCallback(callback) {
    this.triggerHandler.wcEmailListener.onEmailChange(callback);
  }
  currentAmount() {
    return this.triggerHandler.currentAmount;
  }
  isDifferentEmail(email) {
    return this.triggerHandler.isDifferentEmail(email);
  }
  isBuyerEmailKnown() {
    return this.triggerHandler.wcBuyerEmail != null;
  }
  setBuyerEmail(email) {
    this.triggerHandler.wcBuyerEmail = email;
  }
  reportEmailChangeMetric() {
    this.triggerHandler.mpSuperTokenMetrics.sendMetric(EMAIL_CHANGE_METRIC, 'true', '');
  }
  triggerReset() {
    this.triggerHandler.resetCustomCheckout();
  }
  setupEmailChangeHandlers() {
    this.triggerHandler.wcEmailListener.setupEmailChangeHandlers();
  }
  markAsListening() {
    this.triggerHandler.isAlreadyListeningForm = true;
  }
}
;// ./assets/js/checkouts/super-token/adapters/checkout/ClassicCheckout.ts
/**
 * Classic checkout adapter — translates the neutral `FinalizeResult` (RN-3) into the
 * WooCommerce Classic flow of `event-handler.js:handleWithSuperTokenSubmit` (390-441).
 * All checkout-specific effects (mercado_pago_submit flag, submit, loader, error handler,
 * trigger-handler reset) are injected — this holds only the translation.
 *
 * Submit path preserves the legacy branch (event-handler.js:420-424): the standard
 * checkout submits `form.checkout` (`$checkout_form.trigger('submit')`); the order-pay
 * page has no `form.checkout` and submits `#order_review` via
 * `handle3dsPayOrderFormSubmission()` — the order-pay-page submit mechanism, active for
 * Super Token (the 3DS challenge is only an internal branch of it and is not modified).
 */


class ClassicCheckout {
  constructor(deps) {
    this.deps = deps;
  }
  async finalize() {
    const isOrderPayPage = this.deps.isOrderPayPage();
    const result = await this.deps.finalize.execute({
      paymentMethods: this.deps.paymentMethods,
      authenticator: this.deps.authenticator,
      isOrderPayPage
    });
    this.applyResult(result, isOrderPayPage);
  }
  applyResult(result, isOrderPayPage) {
    var _result$error2, _result$error3;
    switch (result.status) {
      case 'success':
        this.deps.markPaymentReady();
        if (isOrderPayPage) {
          this.deps.submitOrderPayForm();
        } else {
          this.deps.submitCheckoutForm();
        }
        return;
      case 'validation_error':
        if (result.errorCode === ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
          var _result$error;
          this.deps.errorHandler.handleError((_result$error = result.error) !== null && _result$error !== void 0 ? _result$error : new Error(result.errorCode));
        }
        this.deps.removeLoader();
        return;
      case 'recoverable_error':
        this.deps.triggerHandler.resetSuperTokenOnError(true);
        this.deps.triggerHandler.setLastException((_result$error2 = result.error) !== null && _result$error2 !== void 0 ? _result$error2 : new Error(result.errorCode));
        return;
      case 'fatal_error':
        this.deps.triggerHandler.resetSuperTokenOnError(false);
        this.deps.triggerHandler.setLastException((_result$error3 = result.error) !== null && _result$error3 !== void 0 ? _result$error3 : new Error(result.errorCode));
        return;
    }
  }
}
;// ./assets/js/checkouts/super-token/adapters/checkout/BlocksCheckout.ts
/**
 * Blocks checkout adapter — translates the neutral `FinalizeResult` (RN-3) into the
 * WooCommerce Blocks flow of `custom.block.js` (`case 'super_token'`, 71-140). Owns the
 * Blocks-only steps that surround the shared spine: the click metric (81) and the
 * validation pre-branch (86-94, backed by the WC Blocks validation store). The finalize
 * result is mapped to an `emitResponse` type.
 *
 * Notes on fidelity to the legacy handler:
 * - Success returns `{ type: SUCCESS }` only. The legacy common path also carries
 *   `meta.paymentMethodData` (custom.block.js:218-223); that payload is assembled by the
 *   `onPaymentSetup` wiring and merged there when this adapter is wired in (TASK-013).
 * - The validation pre-branch returns SUCCESS (not ERROR), mirroring the legacy `break`
 *   that fell through to the common SUCCESS return: the field errors were already shown by
 *   `forceShowValidationErrors`, and WC's own `hasValidationErrors` gate blocks placement,
 *   so no extra payment-error notice is raised.
 * - The legacy `mp_custom_checkout_handler_missing` metric stays observable: the composition
 *   root injects `removeLoader`, and when the loader handler is absent that injected callback
 *   re-emits the metric (custom.block.js) instead of failing silently, so the delegated path
 *   keeps the same diagnostic signal the inline path had.
 */



/** The subset of WC Blocks `emitResponse` the finalization maps onto. */

class BlocksCheckout {
  constructor(deps) {
    this.deps = deps;
  }
  async finalize() {
    try {
      this.deps.metrics.registerClickOnPlaceOrderButton();
      const activeMethod = this.deps.paymentMethods.getActivePaymentMethod();
      const isSelectionValid = !!activeMethod && this.deps.paymentMethods.isSelectedPaymentMethodValid();
      if (activeMethod && !isSelectionValid) {
        this.deps.paymentMethods.forceShowValidationErrors();
      }
      if (this.deps.hasValidationErrors()) {
        this.deps.paymentMethods.selectLastPaymentMethodChoosen();
        return this.success();
      }
      const result = await this.deps.finalize.execute({
        paymentMethods: this.deps.paymentMethods,
        authenticator: this.deps.authenticator,
        isOrderPayPage: false
      });
      return this.applyResult(result);
    } catch (error) {
      // Error Cascade guard: the WC Blocks consumer (custom.block.js) awaits this finalizer
      // without a try/catch, so an unexpected throw in a pre-finalize step — e.g. the WC
      // validation store being unavailable in a Fluid Checkout hybrid — would reject unhandled and
      // leave the card-form spinner stuck. Clear the loader and surface a Blocks error instead.
      this.deps.removeLoader();
      return this.error();
    }
  }
  applyResult(result) {
    var _result$error2;
    if (result.status === 'success') {
      return this.success();
    }
    this.deps.removeLoader();
    if (result.status === 'validation_error') {
      if (result.errorCode === ErrorClassification_MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
        var _result$error;
        this.deps.errorHandler.handleError((_result$error = result.error) !== null && _result$error !== void 0 ? _result$error : new Error(result.errorCode));
      }
      return this.error();
    }
    this.deps.triggerHandler.resetSuperTokenOnError(result.status === 'recoverable_error');
    this.deps.triggerHandler.setLastException((_result$error2 = result.error) !== null && _result$error2 !== void 0 ? _result$error2 : new Error(result.errorCode));
    return this.error();
  }
  success() {
    return {
      type: this.deps.emitResponse.responseTypes.SUCCESS
    };
  }
  error() {
    return {
      type: this.deps.emitResponse.responseTypes.ERROR
    };
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacySelectionSession.ts
/**
 * Session adapter that lets the refactored `SelectSavedPaymentMethod` use case drive the
 * still-legacy `MPSuperTokenPaymentMethods` controller (v2/v2.1
 * `onSelectSuperTokenPaymentMethod`, payment-methods.js:709-737). The use case owns the
 * selection *order*; this adapter supplies the *primitives*, forwarding each one to the
 * legacy instance — a transitional scaffold while the primitives are ported into the tree.
 *
 * Two use-case names have no 1:1 legacy method and are bridged here:
 *  - `setCheckoutTypeToSuperToken()` → `setCheckoutType(SUPER_TOKEN_CHECKOUT_TYPE)`;
 *  - `notifySelectionSettled()`      → the legacy trailing
 *    `setTimeout(dispatch(selectedSupertokenMethodEvent(false)), 50)`.
 * Everything else forwards straight through.
 */

/** Legacy delay before the settled event, mirroring payment-methods.js:732. */
const SELECTION_SETTLED_DELAY_MS = 50;

/**
 * The subset of the legacy `MPSuperTokenPaymentMethods` controller the selection sequence
 * calls. Grounded in `onSelectSuperTokenPaymentMethod` (payment-methods.js:709-737); the
 * legacy globals are opaque handles, so the primitives are named here.
 */

class LegacySelectionSession {
  constructor(legacy) {
    this.legacy = legacy;
  }
  paymentMethodAlreadySelected(paymentMethod) {
    return this.legacy.paymentMethodAlreadySelected(paymentMethod);
  }
  emitEventFromSelectPaymentMethod(paymentMethod) {
    this.legacy.emitEventFromSelectPaymentMethod(paymentMethod);
  }
  storeActivePaymentMethod(paymentMethod) {
    this.legacy.storeActivePaymentMethod(paymentMethod);
  }
  hideAllPaymentMethodDetails() {
    this.legacy.hideAllPaymentMethodDetails();
  }
  closeAccordion() {
    this.legacy.closeAccordion();
  }
  deselectAllPaymentMethods() {
    this.legacy.deselectAllPaymentMethods();
  }
  selectPaymentMethod(paymentMethodElement) {
    this.legacy.selectPaymentMethod(paymentMethodElement);
  }
  fillCardTokenFields(paymentMethod) {
    this.legacy.fillCardTokenFields(paymentMethod);
  }
  setCheckoutTypeToSuperToken() {
    this.legacy.setCheckoutType(this.legacy.SUPER_TOKEN_CHECKOUT_TYPE);
  }
  showPaymentMethodDetails(paymentMethodElement) {
    this.legacy.showPaymentMethodDetails(paymentMethodElement);
  }
  handleInstallmentsWithoutFeePillVisibility() {
    this.legacy.handleInstallmentsWithoutFeePillVisibility();
  }
  handleWithEscPaymentMethod(paymentMethod, paymentMethodElement) {
    return this.legacy.handleWithEscPaymentMethod(paymentMethod, paymentMethodElement);
  }
  mountSecurityCodeField(paymentMethod) {
    this.legacy.mountSecurityCodeField(paymentMethod);
  }
  notifySelectionSettled() {
    setTimeout(() => {
      document.dispatchEvent(this.legacy.selectedSupertokenMethodEvent(false));
    }, SELECTION_SETTLED_DELAY_MS);
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyResetSession.ts
/**
 * Session adapter that lets the refactored `ResetFlow` use case drive the still-legacy
 * `MPSuperTokenTriggerHandler.resetSuperTokenOnError(preserveSelection)`
 * (trigger-handler.js:196-222). The use case owns the reset *order* and the
 * preserve-selection decision; this adapter supplies the *primitives*, forwarding to the
 * trigger handler, to the controller it holds (`mpSuperTokenPaymentMethods`), or to the DOM
 * — a transitional scaffold while the primitives are ported into the tree.
 */

const CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
const SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
const INSTALLMENTS_INPUT_ID = 'cardInstallments';

/** Subset of the legacy controller the reset sequence reads through the trigger handler. */

/**
 * Subset of the legacy `MPSuperTokenTriggerHandler` the reset sequence uses. `savedInstallments`
 * is its own mutable field; `mpSuperTokenPaymentMethods` is the controller it holds.
 */

class LegacyResetSession {
  constructor(triggerHandler) {
    this.triggerHandler = triggerHandler;
  }
  get controller() {
    return this.triggerHandler.mpSuperTokenPaymentMethods;
  }
  isSuperTokenCheckoutActive() {
    return document.querySelector(CHECKOUT_TYPE_SELECTOR)?.value === SUPER_TOKEN_CHECKOUT_TYPE;
  }
  scrollPaymentMethodListIntoView() {
    const list = document.querySelector(`.${this.controller.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
    list?.scrollIntoView({
      behavior: 'smooth'
    });
  }
  getLastPaymentMethodChoosen() {
    return this.controller.getLastPaymentMethodChoosen();
  }
  getSelectedInstallments() {
    const input = document.getElementById(INSTALLMENTS_INPUT_ID);
    return input?.value || null;
  }
  storeSavedInstallments(installments) {
    this.triggerHandler.savedInstallments = installments;
  }
  deselectAllPaymentMethods() {
    this.controller.deselectAllPaymentMethods();
  }
  hideAllPaymentMethodDetails() {
    this.controller.hideAllPaymentMethodDetails();
  }
  unmountActiveSecurityCodeInstance() {
    this.controller.unmountActiveSecurityCodeInstance();
  }
  clearActivePaymentMethod() {
    this.controller.clearActivePaymentMethod();
  }
  resetCustomCheckout(shouldClearCache) {
    this.triggerHandler.resetCustomCheckout(shouldClearCache);
  }
  storeSelectedPreloadedPaymentMethod(paymentMethod) {
    this.controller.storeSelectedPreloadedPaymentMethod(paymentMethod);
  }
}
;// ./assets/js/checkouts/super-token/adapters/session/LegacyAuthenticatorSession.ts
/**
 * Session adapter that lets the refactored `GetAccountPaymentMethods` use case drive the
 * still-legacy `MPSuperTokenAuthenticator.getAccountPaymentMethods` flow
 * (super-token-authenticator.js:144-180). The use case owns the load *order* and its
 * fail-safe gates; this adapter supplies the *primitives*, forwarding each to the legacy
 * authenticator instance (build/verify/store the handle and token), to the controller it
 * needs for the fetch (`mpSuperTokenPaymentMethods.getAccountPaymentMethods`), or to the DOM
 * (the behavior-tracking event) — a transitional scaffold while the primitives are ported.
 *
 * Storing the handle and the fast payment token on the legacy instance is deliberate: the
 * still-legacy `authorizePayment` and the plugin consumers read that same state at submit.
 */

/** Legacy `document.dispatchEvent` name from super-token-authenticator.js:157. */
const BEHAVIOR_TRACKING_INIT_EVENT = 'mp-behavior-tracking-super-token-init';
/** Legacy SDK label passed to `callSdkWithMetrics` from super-token-authenticator.js:194. */
const AUTHORIZE_PAYMENT_SDK_METHOD = 'authorizePayment';

/**
 * The subset of the legacy `MPSuperTokenAuthenticator` the load and submit flows call. The
 * SDK authenticator is an opaque handle threaded between these primitives.
 */

/** The one method of the legacy controller the load flow needs — the account fetch. */

class LegacyAuthenticatorSession {
  constructor(authenticator, paymentMethods) {
    this.authenticator = authenticator;
    this.paymentMethods = paymentMethods;
  }
  buildAuthenticator(amount, buyerEmail) {
    return this.authenticator.buildAuthenticator(amount, buyerEmail);
  }
  storeAuthenticator(authenticator) {
    this.authenticator.storeAuthenticator(authenticator);
  }
  getSimplifiedAuth(authenticator) {
    return this.authenticator.getSimplifiedAuth(authenticator);
  }
  notifyBehaviorTrackingInit() {
    document.dispatchEvent(new CustomEvent(BEHAVIOR_TRACKING_INIT_EVENT));
  }
  getFastPaymentToken(authenticator) {
    return this.authenticator.getFastPaymentToken(authenticator);
  }
  storeFastPaymentToken(token) {
    this.authenticator.storeFastPaymentToken(token);
  }
  fetchAccountPaymentMethods(token) {
    return this.paymentMethods.getAccountPaymentMethods(token);
  }
  getStoredAuthenticator() {
    return this.authenticator.getStoredAuthenticator();
  }
  authorizePaymentOnSdk(authenticator, pseudotoken) {
    var _window$callSdkWithMe;
    const callWithMetrics = (_window$callSdkWithMe = window.callSdkWithMetrics) !== null && _window$callSdkWithMe !== void 0 ? _window$callSdkWithMe : sdkCall => sdkCall();
    return callWithMetrics(() => authenticator.authorizePayment(pseudotoken), AUTHORIZE_PAYMENT_SDK_METHOD);
  }
  storeAuthorizedPseudotoken(pseudotoken) {
    this.authenticator.storeAuthorizedPseudotoken(pseudotoken);
  }
}
;// ./assets/js/checkouts/super-token/adapters/validation/checkoutValidationResolver.ts
/**
 * Resolves the server-side checkout pre-validation verdict (wc_ajax_mp_validate_checkout)
 * against the live DOM and emits the validation funnel metrics. Ported 1:1 from the legacy
 * `shared/validators/checkout-validation-resolver.js` IIFE so the whole v2/v2.1/shared tree can
 * be deleted; published as `window.mpResolveCheckoutValidation` by the bundle entrypoint
 * (bootstrap.ts) via globalBridge. The Classic `event-handler.js` is a thin wrapper that
 * dispatches the verdict and fails open if this is absent or throws.
 *
 * The route only sees the serialized form.checkout body, so a required field rendered outside that
 * form (e.g. CartFlows funnel step) is reported empty even when it is visible and filled on screen.
 * The cross-check discards those false positives so the buyer is never blocked for a field that is
 * actually present and filled.
 *
 * Metrics are injected (never read from `window.*`): the bundle entrypoint reads the live
 * `window.mpSuperTokenMetrics` singleton at call time and passes it in, keeping window.* at the
 * composition edge.
 */


const METRIC_DETAIL = 'validate_checkout_then_continue';
const MAX_ANCESTOR_DEPTH = 20;
const checkoutValidationResolver_SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
const CHECKOUT_TYPE_LABEL = {
  ABSENT: 'absent',
  EMPTY: 'empty'
};

// Verdict contract shared with the plugin wrapper (event-handler.js). The action VALUES are the
// cross-boundary protocol — both sides MUST use the same strings.
const VALIDATION_ACTION = {
  PROCEED: 'PROCEED',
  BLOCK: 'BLOCK',
  FAIL_OPEN: 'FAIL_OPEN'
};
const FAIL_OPEN_REASON = {
  EMPTY_ERRORS: 'EMPTY_ERRORS',
  UNEXPECTED_ERROR: 'UNEXPECTED_ERROR',
  UNEXPECTED_RESPONSE: 'UNEXPECTED_RESPONSE'
};
const BLOCK_REASON = {
  EMPTY_FIELDS: 'EMPTY_FIELDS'
};
const VALIDATION_METRIC = {
  PASSED: 'MP_CHECKOUT_AJAX_VALIDATION_PASSED',
  BLOCKED: 'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
  SKIPPED: 'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
  FALSE_POSITIVE: 'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
  UNEXPECTED_ERROR: 'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_ERROR',
  UNEXPECTED_RESPONSE: 'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_RESPONSE'
};

/** The metric sink the resolver emits into. Injected by the composition root. */

function getFieldNodesByName(fieldName) {
  try {
    // WooCommerce field names follow billing_*/shipping_*/terms conventions and never
    // contain quotes or CSS-special characters, so the fallback is safe without escaping.
    const selector = typeof CSS !== 'undefined' && typeof CSS.escape === 'function' ? `[name=${CSS.escape(fieldName)}]` : `[name="${fieldName}"]`;
    return Array.from(document.querySelectorAll(selector));
  } catch {
    return [];
  }
}
function isFieldVisible(field) {
  var _field$closest$id$end;
  const isWcRegisteredField = (_field$closest$id$end = field.closest('.form-row')?.id?.endsWith('_field')) !== null && _field$closest$id$end !== void 0 ? _field$closest$id$end : false;
  let element = field;
  let depth = 0;
  while (element && element !== document.body && depth < MAX_ANCESTOR_DEPTH) {
    const style = getComputedStyle(element);
    if (style.display === 'none') return false;
    if (style.opacity === '0') return false;
    if (isWcRegisteredField && style.visibility === 'hidden') return false;
    element = element.parentElement;
    depth++;
  }
  return true;
}
function isFieldFilled(field) {
  const input = field;
  if (input.type === 'checkbox' || input.type === 'radio') {
    return !!input.checked;
  }
  return !!input.value?.trim();
}

// Block only when the buyer can see the field and correct it right here.
// Absent or hidden fields are rescued — we cannot verify their state, and the buyer
// cannot interact with them on this screen. The real WooCommerce submit is the backstop.
// Specific fields that should block despite being absent can be added later, driven by
// MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE metric data from production.
function shouldBlockFlaggedField(fieldName) {
  if (!fieldName) {
    return false;
  }
  const nodes = getFieldNodesByName(fieldName);
  if (!nodes.length) {
    return false;
  }
  const visibleNodes = nodes.filter(isFieldVisible);
  if (!visibleNodes.length) {
    return false;
  }
  return !visibleNodes.some(isFieldFilled);
}
function crossCheckErrorsAgainstDom(errors) {
  const flaggedErrors = Array.isArray(errors) ? errors : [];
  const realErrors = [];
  const rescuedFields = [];
  flaggedErrors.forEach(error => {
    if (shouldBlockFlaggedField(error?.field)) {
      realErrors.push(error);
      return;
    }
    rescuedFields.push(error?.field || 'unknown');
  });
  return {
    realErrors,
    rescuedFields
  };
}
function joinErrorFields(errors) {
  return errors.map(error => error?.field).filter(Boolean).join('/') || 'unknown';
}
function readCheckoutType() {
  const element = document.querySelector('#mp_checkout_type');
  if (!element) {
    return {
      checkoutType: null,
      metricValue: CHECKOUT_TYPE_LABEL.ABSENT
    };
  }
  // checkoutType is the raw value used by the guard (may be naturally empty);
  // metricValue normalizes a falsy value to 'empty' so the metric stays filterable.
  return {
    checkoutType: element.value,
    metricValue: element.value || CHECKOUT_TYPE_LABEL.EMPTY
  };
}
function resolveCheckoutValidation(response, metrics) {
  const emitMetric = (metricName, value) => {
    metrics?.sendMetric?.(metricName, value, METRIC_DETAIL);
  };
  const emitEmptyFieldsOnSubmitMetric = emptyFields => {
    // The emptyFields not should be normalized because
    // normalize method replace "email" value to "invalid_email_address_provided"
    // masking the real value of the field that is empty and causing the error.
    const shouldNormalizeError = false;
    emitMetric(VALIDATION_METRIC.BLOCKED, emptyFields);
    metrics?.errorOnSubmit?.(BLOCK_REASON.EMPTY_FIELDS, emptyFields, shouldNormalizeError);
  };
  const {
    checkoutType,
    metricValue
  } = readCheckoutType();
  const res = response;
  try {
    // Only the Super Token flow uses this layer; any other checkout type validates elsewhere.
    if (checkoutType !== checkoutValidationResolver_SUPER_TOKEN_CHECKOUT_TYPE) {
      emitMetric(VALIDATION_METRIC.SKIPPED, metricValue);
      return {
        action: VALIDATION_ACTION.PROCEED
      };
    }
    if (res?.success && res?.data?.valid === true) {
      emitMetric(VALIDATION_METRIC.PASSED, 'valid');
      return {
        action: VALIDATION_ACTION.PROCEED
      };
    }
    if (res?.success && res?.data?.valid === false) {
      const errors = res.data.errors;
      if (!Array.isArray(errors) || !errors.length) {
        return {
          action: VALIDATION_ACTION.FAIL_OPEN,
          reason: FAIL_OPEN_REASON.EMPTY_ERRORS
        };
      }
      const {
        realErrors,
        rescuedFields
      } = crossCheckErrorsAgainstDom(errors);
      if (rescuedFields.length) {
        emitMetric(VALIDATION_METRIC.FALSE_POSITIVE, rescuedFields.join('/'));
      }
      if (!realErrors.length) {
        return {
          action: VALIDATION_ACTION.PROCEED
        };
      }
      emitEmptyFieldsOnSubmitMetric(joinErrorFields(realErrors));
      return {
        action: VALIDATION_ACTION.BLOCK,
        errors: realErrors
      };
    }
    const responseError = toTelemetryErrorMessage(res?.data?.error, FAIL_OPEN_REASON.UNEXPECTED_RESPONSE);
    emitMetric(VALIDATION_METRIC.UNEXPECTED_RESPONSE, responseError);
    return {
      action: VALIDATION_ACTION.FAIL_OPEN,
      reason: FAIL_OPEN_REASON.UNEXPECTED_RESPONSE,
      detail: responseError
    };
  } catch (error) {
    const errorMessage = toTelemetryErrorMessage(error, FAIL_OPEN_REASON.UNEXPECTED_ERROR);
    emitMetric(VALIDATION_METRIC.UNEXPECTED_ERROR, errorMessage);
    return {
      action: VALIDATION_ACTION.FAIL_OPEN,
      reason: FAIL_OPEN_REASON.UNEXPECTED_ERROR,
      detail: errorMessage
    };
  }
}
;// ./assets/js/checkouts/super-token/adapters/validation/wooCommerceValidationErrors.ts
/**
 * Detects visible required/invalid WooCommerce checkout fields before a Super Token payment,
 * so the flow never starts on a form the buyer still has to complete. Ported 1:1 from the legacy
 * `v2.1/validators/checkout-form-validator.js` (identical to v2) so the whole v2/v2.1 tree can be
 * deleted; published as `window.hasWooCommerceValidationErrors` by the bundle entrypoint
 * (bootstrap.ts) via globalBridge.
 *
 * The current source has no in-tree caller, but the checkout-resilience rules document this as the
 * approved validation gate and old plugin versions reach it through the shared CDN bundle, so it is
 * ported for compatibility. Metrics are injected (never read from `window.*`): the bundle
 * entrypoint reads the live `window.mpSuperTokenMetrics` singleton at call time and passes it in.
 */

// Guard against malformed or unexpectedly deep DOM trees
const wooCommerceValidationErrors_MAX_ANCESTOR_DEPTH = 20;
const CONTAINER_FIELDS_SELECTOR = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';

/** The metric sink the checker emits into. Injected by the composition root. */

// Detects display:none or opacity:0 on the element itself or any ancestor (all fields).
// Also detects visibility:hidden for WooCommerce-registered fields (form-row id="*_field"),
// where server-side validation acts as a safety net for false positives.
// visibility:hidden is intentionally excluded for non-registered custom fields to avoid
// creating orders with missing data when server-side validation is absent.
function wooCommerceValidationErrors_isFieldVisible(field) {
  var _field$closest$id$end;
  const isWcField = (_field$closest$id$end = field.closest('.form-row')?.id?.endsWith('_field')) !== null && _field$closest$id$end !== void 0 ? _field$closest$id$end : false;
  let el = field;
  let depth = 0;
  while (el && el !== document.body && depth < wooCommerceValidationErrors_MAX_ANCESTOR_DEPTH) {
    const style = getComputedStyle(el);
    if (style.display === 'none') return false;
    if (style.opacity === '0') return false;
    if (isWcField && style.visibility === 'hidden') return false;
    el = el.parentElement;
    depth++;
  }
  return true;
}
function wooCommerceValidationErrors_isFieldFilled(field) {
  const input = field;
  return input.type === 'checkbox' || input.type === 'radio' ? input.checked : !!input.value.trim();
}
function hasWooCommerceValidationErrors(metrics) {
  const invalidFields = document.querySelectorAll('.woocommerce-invalid, .woocommerce-invalid-required-field, .validate-required.woocommerce-invalid');
  const visibleInvalidFields = Array.from(invalidFields).filter(container => {
    if (!wooCommerceValidationErrors_isFieldVisible(container)) return false;
    // Only consider skipping when the container is flagged as a stale required-field error.
    // Containers with just `woocommerce-invalid` (without `-required-field`) indicate
    // format errors (e.g. malformed email) and must NOT be skipped even when filled.
    if (!container.classList.contains('woocommerce-invalid-required-field')) {
      return true;
    }
    // Skip stale required-field containers only when ALL their fields are considered filled.
    // For checkbox/radio, filled = checked; for other inputs, filled = non-empty value.
    // This covers the case where the store populates fields via JS without triggering
    // WC re-validation events, leaving woocommerce-invalid-required-field stale on a filled container.
    const isDirectField = container.matches('input, select, textarea');
    const fields = isDirectField ? [container] : Array.from(container.querySelectorAll(CONTAINER_FIELDS_SELECTOR));
    const allFieldsFilled = fields.length > 0 && fields.every(wooCommerceValidationErrors_isFieldFilled);
    if (allFieldsFilled) {
      const fieldNames = fields.map(f => f.name || f.id || 'unknown').join('/');
      metrics?.sendMetric?.('MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED', fieldNames, 'visibleInvalidFields');
      return false;
    }
    return true;
  });
  const formScope = document.body.classList.contains('woocommerce-order-pay') ? '#order_review' : '.woocommerce-checkout';
  const requiredFields = document.querySelectorAll(`${formScope} .validate-required input, ${formScope} .validate-required select`);
  const emptyRequiredFields = Array.from(requiredFields).filter(field => {
    const input = field;
    if (input.type === 'hidden' || input.disabled) return false;
    if (!wooCommerceValidationErrors_isFieldVisible(field)) return false;
    if (input.type === 'checkbox' && input.id === 'terms' && input.name === 'terms') {
      return !input.checked;
    }
    return !input.value.trim();
  });
  const hasErrors = visibleInvalidFields.length > 0 || emptyRequiredFields.length > 0;
  if (hasErrors && metrics && typeof metrics.sendMetric === 'function') {
    const emptyFieldNames = emptyRequiredFields.map(field => {
      const input = field;
      return input.name || input.id || input.type;
    });
    const invalidFieldNames = visibleInvalidFields.map(container => {
      const field = container.querySelector('input[name], select[name]');
      return field ? field.name : container.id || 'unknown';
    });
    const allFieldNames = Array.from(new Set(emptyFieldNames.concat(invalidFieldNames))).join('/');
    metrics.sendMetric('MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR', allFieldNames || 'unknown', 'hasWooCommerceValidationErrors');
  }
  return hasErrors;
}
;// ./assets/js/checkouts/super-token/adapters/legacy/globalBridge.ts
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
function publish(instances) {
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
function publishErrorCodes(codes) {
  window.MPSuperTokenErrorCodes = codes;
}

/**
 * The finalization entry points the legacy checkout consumers call at submit time. The
 * consumer supplies the deps it owns (legacy instances + DOM callbacks); `finalize` (the
 * shared use case) is injected by the bundle entrypoint, so the consumers never construct
 * the domain themselves. `finalize` is omitted from the input for that reason.
 */

/**
 * Publishes the refactored finalization entry points under `window.mpSuperTokenFinalize*`
 * so the still-legacy `event-handler.js`/`custom.block.js` can delegate to the unified
 * ClassicCheckout/BlocksCheckout. Kept separate from `publish` so the instance mirror stays
 * exactly the five legacy names.
 */
function publishFinalizers(finalizers) {
  window.mpSuperTokenFinalizeClassic = finalizers.finalizeClassic;
  window.mpSuperTokenFinalizeBlocks = finalizers.finalizeBlocks;
}

/**
 * The saved-method selection entry point the legacy `onSelectSuperTokenPaymentMethod`
 * (v2/v2.1 payment-methods.js) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the selected method and its DOM row.
 */

/**
 * Publishes the refactored selection entry point under `window.mpSuperTokenSelectPaymentMethod`
 * so the still-legacy controller can delegate its `onSelectSuperTokenPaymentMethod`. Kept
 * separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishSelectors(selectors) {
  window.mpSuperTokenSelectPaymentMethod = selectors.selectSavedPaymentMethod;
}

/**
 * The reset entry point the legacy `resetSuperTokenOnError` (trigger-handler.js) delegates to.
 * The bundle injects the ResetFlow use case + session behind it; the legacy shell only forwards
 * the `preserveSelection` decision.
 */

/**
 * Publishes the refactored reset entry point under `window.mpSuperTokenResetOnError` so the
 * still-legacy trigger handler can delegate its `resetSuperTokenOnError`. Kept separate from
 * `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishReset(reset) {
  window.mpSuperTokenResetOnError = reset.resetOnError;
}

/**
 * The order+decorate entry point the legacy `organizePaymentMethodsElements` delegates to,
 * replacing its inline `reorderAccountPaymentMethods` + `normalizeAccountPaymentMethods` with
 * the core `PaymentMethodCatalog` + `PaymentMethodRegistry`. Returns the same list, reordered
 * and decorated in place (parity with the legacy pair).
 */

/**
 * Publishes the refactored order+decorate entry point under
 * `window.mpSuperTokenOrderAndDecorate`. Kept separate from `publish` so the instance mirror
 * stays exactly the five legacy names.
 */
function publishOrderAndDecorate(seam) {
  window.mpSuperTokenOrderAndDecorate = seam.orderAndDecorate;
}

/**
 * The saved-methods render entry point the legacy `onCustomCheckoutWasRendered` delegates to,
 * replacing its `organizePaymentMethodsElements` + `setupEmailHeaderListener` with the refactored
 * variant view (grouping + blocks + header + live e-mail listener). The per-row element is still
 * built by the legacy controller and forwarded in through the render session, so this seam owns
 * the render *order* while the row stays legacy — the scaffold that shrinks as the row is ported.
 */

/**
 * Publishes the refactored render entry point under `window.mpSuperTokenRenderSavedMethods`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishRenderSavedMethods(seam) {
  window.mpSuperTokenRenderSavedMethods = seam.renderSavedMethods;
}

/**
 * The account-payment-methods load entry point the legacy `getAccountPaymentMethods`
 * (super-token-authenticator.js) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the amount and buyer e-mail, and the use case
 * keeps the handle/token stored on the still-legacy authenticator so submit-time consumers
 * see the same state.
 */

/**
 * Publishes the refactored load entry point under `window.mpSuperTokenGetAccountPaymentMethods`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishAccountPaymentMethods(seam) {
  window.mpSuperTokenGetAccountPaymentMethods = seam.getAccountPaymentMethods;
}

/**
 * The submit-time authorize entry point the legacy `authorizePayment` (super-token-
 * authenticator.js) delegates to. The bundle injects the use case + session behind it; the
 * legacy shell only forwards the pseudotoken. Unlike the fail-safe seams, this one throws a
 * typed error code on failure — the shell must let it propagate (never swallow-and-retry, or
 * the SDK authorize would run twice).
 */

/**
 * Publishes the refactored authorize entry point under `window.mpSuperTokenAuthorizePayment`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishAuthorizePayment(seam) {
  window.mpSuperTokenAuthorizePayment = seam.authorizePayment;
}

/**
 * The fetch+render load entry point the legacy `fetchAndRenderSuperTokenPaymentMethods`
 * (super-token-trigger-handler.js) delegates to. The bundle injects the use case + session
 * behind it; the shell forwards no arguments — the session reads the amount, buyer e-mail and
 * load generation off the still-legacy trigger handler. Fail-safe like the load seam: the shell
 * falls back to the inline flow on any throw.
 */

/**
 * Publishes the refactored fetch+render entry point under
 * `window.mpSuperTokenFetchAndRenderPaymentMethods`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
function publishFetchAndRender(seam) {
  window.mpSuperTokenFetchAndRenderPaymentMethods = seam.fetchAndRenderPaymentMethods;
}

/**
 * The load-orchestration entry point the legacy `loadSuperToken` (super-token-trigger-handler.js)
 * delegates to — the entry Classic `event-handler.js` and Blocks `cart-update.helper.js` call on
 * every amount refresh. The bundle injects the use case + session behind it; the shell forwards
 * the amount. Fail-safe like the fetch+render seam: the shell falls back to the inline flow on
 * any throw.
 */

/**
 * Publishes the refactored load-orchestration entry point under `window.mpSuperTokenLoadSuperToken`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishLoad(seam) {
  window.mpSuperTokenLoadSuperToken = seam.loadSuperToken;
}

/**
 * The cancel-load entry point the legacy `cancelLoad` (super-token-trigger-handler.js) delegates
 * to — called by Classic `event-handler.js` when the buyer leaves the custom method mid-fetch.
 * The bundle injects the use case + session behind it; the shell forwards nothing (the session
 * reaches the load state off the still-legacy trigger handler). Fail-safe like the load seam: the
 * shell falls back to the inline flow on any throw.
 */

/**
 * Publishes the refactored cancel-load entry point under `window.mpSuperTokenCancelLoad`. Kept
 * separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishCancelLoad(seam) {
  window.mpSuperTokenCancelLoad = seam.cancelLoad;
}

/**
 * The reset-custom-checkout entry point the legacy `resetCustomCheckout`
 * (super-token-trigger-handler.js) delegates to — reached both by the error reset
 * (`resetSuperTokenOnError`) and by the e-mail-change callback. The bundle injects the use case +
 * session behind it; the shell forwards the `shouldClearCache` flag. Fail-safe like the load seam:
 * the shell falls back to the inline flow on any throw.
 */

/**
 * Publishes the refactored reset-custom-checkout entry point under
 * `window.mpSuperTokenResetCustomCheckout`. Kept separate from `publish` so the instance mirror
 * stays exactly the five legacy names.
 */
function publishResetCustomCheckout(seam) {
  window.mpSuperTokenResetCustomCheckout = seam.resetCustomCheckout;
}

/**
 * The error-handling entry point the legacy `finalizeResetTail` (and eventually all callers of
 * `MPSuperTokenErrorHandler.handleError`) delegates to. The bundle injects the use case + session
 * behind it; the legacy shell only forwards the exception. Returns the normalised error code.
 * Fail-safe: the shell falls back to the legacy error-handler instance on any throw.
 */

/**
 * Publishes the refactored error-handling entry point under `window.mpSuperTokenHandleError`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishHandleError(seam) {
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

/**
 * Publishes the refactored restore entry point under
 * `window.mpSuperTokenRestorePreloadedPaymentMethod`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
function publishRestorePreloaded(seam) {
  window.mpSuperTokenRestorePreloadedPaymentMethod = seam.restorePreloadedPaymentMethod;
}

/**
 * The e-mail listener registration entry point the legacy `ensureEmailListenerRegistered`
 * (super-token-trigger-handler.js) delegates to. The bundle injects the use case + session
 * behind it; the shell delegates on every call (the use case owns the once-guard internally).
 * Fail-safe: the shell falls back to the inline flow on any throw.
 */

/**
 * Publishes the refactored e-mail listener registration under
 * `window.mpSuperTokenEnsureEmailListenerRegistered`. Kept separate from `publish` so the
 * instance mirror stays exactly the five legacy names.
 */
function publishEnsureEmailListener(seam) {
  window.mpSuperTokenEnsureEmailListenerRegistered = seam.ensureEmailListenerRegistered;
}

/**
 * The checkout pre-validation resolver the still-legacy Classic `event-handler.js` delegates to.
 * The bundle injects the ported resolver behind it; the consumer forwards the parsed route response
 * and reads `verdict.action`. Replaces the legacy `shared/validators/checkout-validation-resolver.js`
 * global so the whole v2/v2.1/shared tree can be deleted.
 */

/**
 * Publishes the ported checkout pre-validation resolver under `window.mpResolveCheckoutValidation`.
 * Kept separate from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishCheckoutValidationResolver(seam) {
  window.mpResolveCheckoutValidation = seam.resolveCheckoutValidation;
}

/**
 * The approved WooCommerce validation gate. No in-tree caller reads it today, but old plugin
 * versions reach it through the shared CDN bundle, so it is published for compatibility. Replaces
 * the legacy `v2.1/validators/checkout-form-validator.js` global so the v2/v2.1 tree can be deleted.
 */

/**
 * Publishes the ported validation gate under `window.hasWooCommerceValidationErrors`. Kept separate
 * from `publish` so the instance mirror stays exactly the five legacy names.
 */
function publishWooCommerceValidationErrors(seam) {
  window.hasWooCommerceValidationErrors = seam.hasWooCommerceValidationErrors;
}
;// ./assets/js/checkouts/super-token/composition/legacyDelegationSeams.ts
/**
 * Publishes the refactored orchestration entry points to window.mpSuperToken* (through the
 * transitional bridge) so the still-legacy JS classes delegate every major orchestration step to
 * the TS use cases. Each seam is one shared, stateless use case wrapped over a Legacy*Session that
 * reads the live legacy state from window.* at call time — keeping window.* at the composition edge.
 * The legacy classes remain state-holders with a delegate+fallback casca; the fallback keeps stores
 * safe if a seam fails. These sessions shrink as the primitives are ported into the tree.
 */




























// ─── Finalization (Phase 1) ──────────────────────────────────────────────────

// One shared instance: the use case is stateless, so a single copy serves every submit.
const finalizeUseCase = new FinalizeSuperTokenPayment();
function finalizeClassic(input) {
  return new ClassicCheckout({
    finalize: finalizeUseCase,
    ...input
  }).finalize();
}
function finalizeBlocks(input) {
  return new BlocksCheckout({
    finalize: finalizeUseCase,
    ...input
  }).finalize();
}

// ─── Checkout validation helpers ─────────────────────────────────────────────

// Publish the ported validators for the still-legacy Classic event-handler.js
// (mpResolveCheckoutValidation) and any old-plugin consumer of the approved validation gate
// (hasWooCommerceValidationErrors). Synchronous, both modes, so the globals exist before submit
// reads them (replaces the legacy shared/v2.1 validators). Metrics are read from the live
// window.mpSuperTokenMetrics singleton at call time — keeping window.* at the composition edge.
function resolveCheckoutValidationSeam(response) {
  const metrics = window.mpSuperTokenMetrics;
  return resolveCheckoutValidation(response, metrics);
}
function hasWooCommerceValidationErrorsSeam() {
  const metrics = window.mpSuperTokenMetrics;
  return hasWooCommerceValidationErrors(metrics);
}

// ─── Selection (Phase 3) ─────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const selectUseCase = new SelectSavedPaymentMethod();

// Hybrid: the legacy CDN bundle still owns the controller/metrics instances and the DOM
// primitives. This wraps the legacy instance as the selection session, so the use case
// drives the *order* while the primitives keep coming from the legacy controller — the
// scaffold that shrinks as the primitives are ported into the tree.
function selectSavedPaymentMethod(paymentMethod, paymentMethodElement) {
  const controller = window.mpSuperTokenPaymentMethods;
  const metrics = window.mpSuperTokenMetrics;
  return selectUseCase.execute({
    session: new LegacySelectionSession(controller),
    metrics,
    paymentMethod,
    paymentMethodElement
  });
}

// ─── Reset (Phase 4) ─────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const resetFlow = new ResetFlow();

// Hybrid: the legacy trigger handler still owns the reset primitives and the controller it
// holds. This wraps it as the reset session so the use case drives the *order* and the
// preserve-selection decision, while the primitives keep coming from the legacy instance.
function resetOnError(preserveSelection) {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  resetFlow.execute({
    session: new LegacyResetSession(triggerHandler),
    preserveSelection
  });
}

// ─── Account payment methods / load (Phase 7) ────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const getAccountPaymentMethodsUseCase = new GetAccountPaymentMethods();

// Hybrid: the legacy authenticator still owns the handle/token state and the SDK primitives,
// and the legacy controller still owns the account fetch. This wraps both as the load session
// so the use case drives the *order* and the fail-safe gates while the state stays legacy —
// the scaffold that shrinks as the primitives are ported into the tree.
function getAccountPaymentMethods(amount, buyerEmail) {
  const authenticator = window.mpSuperTokenAuthenticator;
  const paymentMethods = window.mpSuperTokenPaymentMethods;
  const metrics = window.mpSuperTokenMetrics;
  return getAccountPaymentMethodsUseCase.execute({
    session: new LegacyAuthenticatorSession(authenticator, paymentMethods),
    metrics,
    amount,
    buyerEmail
  });
}

// ─── Authorize at submit (Phase 8) ───────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const authorizePaymentUseCase = new AuthorizePayment();

// Hybrid: the legacy authenticator still owns the stored handle and the SDK authorize call.
// This wraps it as the authorize session so the use case drives the *order* and the error
// classification, while the primitives keep coming from the legacy instance. The paymentMethods
// arg is unused here (the load path needs it) but the session serves both roles.
function authorizePayment(pseudotoken) {
  const authenticator = window.mpSuperTokenAuthenticator;
  const paymentMethods = window.mpSuperTokenPaymentMethods;
  const metrics = window.mpSuperTokenMetrics;
  return authorizePaymentUseCase.execute({
    session: new LegacyAuthenticatorSession(authenticator, paymentMethods),
    metrics,
    pseudotoken
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
function fetchAndRenderPaymentMethods() {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  const metrics = window.mpSuperTokenMetrics;
  return fetchAndRenderUseCase.execute({
    session: new LegacyTriggerSession(triggerHandler),
    metrics: createFetchAndRenderMetrics(metrics)
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
function loadSuperToken(currentAmount) {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  const metrics = window.mpSuperTokenMetrics;
  return loadSuperTokenUseCase.execute({
    session: new LegacyLoadOrchestrationSession(triggerHandler),
    metrics: createLoadSuperTokenMetrics(metrics),
    currentAmount
  });
}

// ─── Cancel load ─────────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const cancelLoadUseCase = new CancelLoad();

// Reuses the fetch+render trigger session: cancel drives the same load-state cluster (fetching
// flag, load generation) plus the controller reset, all still owned by the legacy trigger handler.
function cancelLoad() {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  cancelLoadUseCase.execute({
    session: new LegacyTriggerSession(triggerHandler)
  });
}

// ─── Reset custom checkout ───────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const resetCustomCheckoutUseCase = new ResetCustomCheckout();

// Hybrid: the legacy trigger handler still owns the reset primitives (spinner, validation, card
// form remount, resetFlow, the load orchestration — itself already delegating to the load seam —
// and the async tail). This wraps it as the reset session so the use case drives the *order*
// while the state stays legacy — the scaffold that shrinks as the primitives are ported.
function resetCustomCheckout(shouldClearCache) {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  resetCustomCheckoutUseCase.execute({
    session: new LegacyResetCustomCheckoutSession(triggerHandler),
    shouldClearCache
  });
}

// ─── Error handling ──────────────────────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const handleErrorUseCase = new HandleError();

// Hybrid: the legacy metrics and payment-methods controller still own the side-effecting
// operations (metric dispatch, validation display, error message lookup). This wraps them as
// the error session so the use case drives the *order* and the display branch while the
// primitives keep coming from the legacy instances — the scaffold that shrinks as they are ported.
function handleError(exception) {
  const metrics = window.mpSuperTokenMetrics;
  const controller = window.mpSuperTokenPaymentMethods;
  return handleErrorUseCase.execute({
    session: new LegacyErrorHandlerSession(metrics, controller),
    exception
  });
}

// ─── Restore preloaded payment method ────────────────────────────────────────

// One shared instance: the use case is stateless orchestration over the injected session.
const restorePreloadedUseCase = new RestorePreloadedPaymentMethod();

// Hybrid: the legacy trigger handler still owns `savedInstallments` and the controller it holds
// owns the preloaded-method state and DOM primitives. This wraps both as the restore session so
// the use case drives the *order* and the four early-exit branches while the primitives keep
// coming from the legacy — the scaffold that shrinks as they are ported into the tree.
function restorePreloadedPaymentMethod() {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  const metrics = window.mpSuperTokenMetrics;
  return restorePreloadedUseCase.execute({
    session: new LegacyRestoreSession(triggerHandler, metrics)
  });
}

// ─── E-mail listener registration ────────────────────────────────────────────

// One shared instance: the use case is stateless; the once-guard lives on the trigger handler.
const ensureEmailListenerUseCase = new EnsureEmailListenerRegistered();

// Hybrid: the legacy trigger handler still owns `isAlreadyListeningForm`, `currentAmount`,
// `wcBuyerEmail`, `isDifferentEmail`, `wcEmailListener` and `mpSuperTokenMetrics`. This wraps
// it as the email-listener session so the use case drives the *order* and the reset decision
// while the primitives keep coming from the legacy — the scaffold that shrinks as they are ported.
function ensureEmailListenerRegistered() {
  const triggerHandler = window.mpSuperTokenTriggerHandler;
  ensureEmailListenerUseCase.execute({
    session: new LegacyEmailListenerSession(triggerHandler)
  });
}

/**
 * Publish every legacy-delegation seam. Synchronous and mode-agnostic, so the globals exist
 * before any submit/select/load reads them (error codes replace errors/super-token-error-constants.js).
 */
function publishLegacyDelegationSeams() {
  publishFinalizers({
    finalizeClassic,
    finalizeBlocks
  });
  publishErrorCodes(ErrorClassification_MPSuperTokenErrorCodes);
  publishCheckoutValidationResolver({
    resolveCheckoutValidation: resolveCheckoutValidationSeam
  });
  publishWooCommerceValidationErrors({
    hasWooCommerceValidationErrors: hasWooCommerceValidationErrorsSeam
  });
  publishSelectors({
    selectSavedPaymentMethod
  });
  publishReset({
    resetOnError
  });
  publishAccountPaymentMethods({
    getAccountPaymentMethods
  });
  publishAuthorizePayment({
    authorizePayment
  });
  publishFetchAndRender({
    fetchAndRenderPaymentMethods
  });
  publishLoad({
    loadSuperToken
  });
  publishCancelLoad({
    cancelLoad
  });
  publishResetCustomCheckout({
    resetCustomCheckout
  });
  publishHandleError({
    handleError
  });
  publishRestorePreloaded({
    restorePreloadedPaymentMethod
  });
  publishEnsureEmailListener({
    ensureEmailListenerRegistered
  });
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/dom.ts
/**
 * Tiny declarative DOM builder shared by the variant views. Keeps rendering XSS-safe by
 * construction: text is set via `textContent` and attributes via `setAttribute`, never
 * `innerHTML`. Lets the views describe the element tree instead of imperatively creating,
 * configuring and appending each node.
 */

function el(tag, options = {}) {
  const node = document.createElement(tag);
  if (options.classes?.length) {
    node.classList.add(...options.classes);
  }
  if (options.text !== undefined) {
    node.textContent = options.text;
  }
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([name, value]) => node.setAttribute(name, value));
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      if (value !== undefined) {
        node.dataset[key] = value;
      }
    });
  }
  options.children?.forEach(child => {
    if (child) {
      node.appendChild(child);
    }
  });
  return node;
}
;// ./assets/js/checkouts/super-token/core/constants.ts
/**
 * Domain invariants for the Super Token checkout — the literal values the business
 * rules are defined against. These are NOT configuration: they never vary per store
 * or per site, so they live in the domain instead of the injected config.
 *
 * Preserved 1:1 from the legacy MPSuperTokenPaymentMethods class fields
 * (assets/js/checkouts/super-token/v2.1/entities/super-token-payment-methods.js).
 */

const CREDIT_CARD_TYPE = 'credit_card';
const DEBIT_CARD_TYPE = 'debit_card';
const ACCOUNT_MONEY_TYPE = 'account_money';
const PREPAID_CARD_TYPE = 'prepaid_card';
/** The SDK names consumer credits 'digital_currency', not 'consumer_credits'. */
const CONSUMER_CREDITS_TYPE = 'digital_currency';
/** UI-only pseudo-method for the "add new card" option; never returned by the SDK. */
const NEW_CARD_TYPE = 'new_card';
const MERCADO_PAGO_ISSUER_NAME = 'mercado pago';
const COLOMBIA_ACCRONYM = 'MCO';
const MEXICO_ACCRONYM = 'MLM';
const BRAZIL_ACCRONYM = 'MLB';
const ARGENTINA_ACCRONYM = 'MLA';

/** Sites whose installment titles carry the third-party bank-interest asterisk (RN-5). */
const COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];

/** Sites whose Mercado Pago credit-card icon is the blue variant; all others use dark (RN-7). */
const MP_CARD_BLUE_SITES = ['MLA', 'MLM'];
const PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
const PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';

/** Max saved cards shown across credit/debit/prepaid — single source (RN-1). */
const MAX_CREDIT_CARDS = 3;
/** Max retries per error code before the flow stops offering a retry (RN-2). */
const MAX_ATTEMPTS_BY_ERROR_CODE = 3;

/** Installments beyond this are dropped in Colombia (RN, getInstallmentsLimit). */
const COLOMBIA_INSTALLMENTS_LIMIT = 6;
;// ./assets/js/checkouts/super-token/core/checkoutSession/PaymentMethodClassifier.ts
/**
 * Pure classification of account payment methods (RN-6) plus the stable identity
 * key the session state matches methods by. No DOM, SDK or window — the predicates
 * only read the raw SDK payment-method shape.
 *
 * Preserved 1:1 from MPSuperTokenPaymentMethods (v2.1) lines 226-230, 778-804.
 */


const issuerIsMercadoPago = issuerName => !!issuerName?.toLowerCase()?.includes(MERCADO_PAGO_ISSUER_NAME);
const PaymentMethodClassifier_isCreditCard = paymentMethod => paymentMethod?.type === CREDIT_CARD_TYPE;
const PaymentMethodClassifier_isDebitCard = paymentMethod => paymentMethod?.type === DEBIT_CARD_TYPE;
const isAccountMoney = paymentMethod => paymentMethod?.type === ACCOUNT_MONEY_TYPE;
const isPrepaidCard = paymentMethod => paymentMethod?.type === PREPAID_CARD_TYPE;
const isConsumerCredits = paymentMethod => paymentMethod?.type === CONSUMER_CREDITS_TYPE;
const isNewCard = paymentMethod => paymentMethod?.type === NEW_CARD_TYPE;
const isMercadoPagoCard = paymentMethod => isPrepaidCard(paymentMethod) && issuerIsMercadoPago(paymentMethod.issuer?.name);
const isMercadoPagoCreditCard = paymentMethod => PaymentMethodClassifier_isCreditCard(paymentMethod) && issuerIsMercadoPago(paymentMethod.issuer?.name);
const userHasAccountMoney = paymentMethod => paymentMethod.has_account_money;
const userHasAccountMoneyInvested = paymentMethod => paymentMethod.has_account_money_invested;

/**
 * Stable key for a saved payment method: id + last four digits when present.
 * Used to reconcile a preloaded selection against the fetched list.
 */
const paymentMethodIdentifier = paymentMethod => {
  var _paymentMethod$card$c;
  if (!paymentMethod) return '';
  const lastFourDigits = 'card' in paymentMethod ? (_paymentMethod$card$c = paymentMethod.card?.card_number?.last_four_digits) !== null && _paymentMethod$card$c !== void 0 ? _paymentMethod$card$c : '' : '';
  return `${paymentMethod.id}${lastFourDigits}`;
};
;// ./assets/js/checkouts/super-token/adapters/view/shared/styles.ts
/**
 * CSS class names shared by both variant views — the saved-method row and the list
 * container. Literal in the legacy `SUPER_TOKEN_STYLES` (identical across v2/v2.1),
 * so they live in the code, not in the injected config. Variant-only classes live in
 * `../v2/styles.ts` and `../v2.1/styles.ts`.
 */
const SHARED_STYLES = {
  PAYMENT_METHOD: 'mp-super-token-payment-method',
  PAYMENT_METHOD_HEADER: 'mp-super-token-payment-method__header',
  PAYMENT_METHOD_THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
  PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
  PAYMENT_METHOD_CONTENT_TITLE: 'mp-super-token-payment-method__content-title',
  PAYMENT_METHOD_TITLE: 'mp-super-token-payment-method__title',
  PAYMENT_METHOD_LAST_FOUR_DIGITS: 'mp-super-token-payment-method__last-four-digits',
  PAYMENT_METHOD_VALUE_PROP: 'mp-super-token-payment-method__value-prop',
  PAYMENT_METHOD_SELECTED: 'mp-super-token-payment-method__selected',
  // Detail accordion (installments + security code), hidden until the row is selected.
  PAYMENT_METHOD_DETAILS: 'mp-super-token-payment-method__details',
  PAYMENT_METHOD_HIDE: 'mp-super-token-hide',
  METHOD_DETAILS_WRAPPER: 'mp-super-token-method-details-wrapper',
  // Installments select.
  INSTALLMENTS_SELECT_CONTAINER: 'mp-checkout-custom-installments-select-container',
  INPUT_LABEL: 'mp-input-label',
  SELECT_INPUT: 'mp-custom-checkout-select-input',
  INSTALLMENTS_TAX_INFO: 'mp-installments-tax-info',
  INSTALLMENTS_ERROR: 'mp-super-token-error',
  INSTALLMENTS_LABEL_ERROR: 'mp-super-token-label-error',
  // Security-code field container (the SDK mounts the CVV input into SECURITY_CODE_INPUT).
  SECURITY_CODE_CONTAINER: 'mp-super-token-security-code-container',
  SECURITY_CODE_LABEL: 'mp-super-token-security-code-label',
  SECURITY_CODE_INPUT: 'mp-super-token-security-code-input',
  SECURITY_CODE_TOOLTIP: 'mp-super-token-security-code-tooltip',
  INPUT_TOOLTIP_HELPER_ERROR: 'mp-input-with-tooltip-helper-error',
  SECURITY_CODE_ERROR_MESSAGE: 'mp-super-token-security-code-error-message'
};
;// ./assets/js/checkouts/super-token/adapters/view/shared/paymentMethodPresentation.ts
/**
 * Presentation resolution shared by both variant views: the display name and thumbnail
 * a saved method is rendered with, plus the interest-free installment count. Ported 1:1
 * from the legacy `normalizeAccountPaymentMethods`, `buildAccountMoneyName`,
 * `buildConsumerCreditsName` and `numberOfInstallmentsWithoutFee`
 * (v2/v2.1 entities/super-token-payment-methods.js) — but non-mutating: it returns a
 * view model instead of writing back onto the payment method.
 *
 * The ONLY per-variant difference in a row lives in `RowPresentation`: how a Mercado
 * Pago credit card is presented and whether an account-money row gets its extra class.
 * Everything else is identical across v2/v2.1, so it stays here with no `if (variant)`.
 */



/** Non-breaking space, kept in the consumer-credits copy so "Mercado Pago" never wraps. */
const NBSP = '\u00a0';

/** The per-variant row seam. v2 = no MP credit-card case, no account-money class. */

/** MLA/MLM use the blue Mercado Pago card icon; every other site uses the dark one (RN-7). */
function mpCardThumbnailPath(deps) {
  return MP_CARD_BLUE_SITES.includes(deps.siteId) ? deps.thumbnails.mpLogoBluePath : deps.thumbnails.mpLogoDarkPath;
}
function buildAccountMoneyName(paymentMethod, deps) {
  if (deps.siteId !== MEXICO_ACCRONYM) {
    return deps.copy.accountMoneyText;
  }
  const hasMoney = userHasAccountMoney(paymentMethod);
  const hasInvested = userHasAccountMoneyInvested(paymentMethod);
  if (hasMoney && hasInvested) return deps.copy.accountMoneyWalletWithInvestmentText;
  if (hasMoney) return deps.copy.accountMoneyWalletText;
  if (hasInvested) return deps.copy.accountMoneyInvestmentText;
  return deps.copy.accountMoneyAvailableText;
}

// Hardcoded copy in the legacy source. The legacy emitted the &nbsp; entity inside innerHTML;
// here the name is rendered via textContent, so NBSP is the real non-breaking-space character.
function buildConsumerCreditsName(siteId) {
  switch (siteId) {
    case MEXICO_ACCRONYM:
      return `Meses sin Tarjeta con Mercado${NBSP}Pago`;
    case BRAZIL_ACCRONYM:
      return `Linha de Crédito Mercado${NBSP}Pago`;
    default:
      return `Cuotas sin Tarjeta con Mercado${NBSP}Pago`;
  }
}

/** Largest interest-free installment count offered (drives the value-prop pill). Pure. */
function installmentsWithoutFee(paymentMethod) {
  if (!PaymentMethodClassifier_isCreditCard(paymentMethod) && !isConsumerCredits(paymentMethod)) {
    return 0;
  }
  const installments = 'installments' in paymentMethod ? paymentMethod.installments : undefined;
  if (!installments?.length) {
    return 0;
  }
  if (isConsumerCredits(paymentMethod)) {
    // Temporary: filters by rate only, since installment_rate_collector is not yet in the API.
    const free = installments.filter(installment => installment.installment_rate === 0);
    return free.length > 0 ? free[free.length - 1].installments : 0;
  }
  const free = installments.filter(installment => installment.installment_rate === 0 && installment.installment_rate_collector?.includes('MERCADOPAGO'));
  return free.length > 0 ? free[free.length - 1].installments : 0;
}
function resolveCardThumbnail(paymentMethod, deps) {
  return deps.thumbnails.paymentMethodsThumbnails[paymentMethod.id] || paymentMethod.thumbnail || deps.thumbnails.whiteCardPath;
}
function resolvePaymentMethodView(paymentMethod, deps, presentation) {
  var _unknownMethod$name, _unknownMethod$thumbn;
  if (isAccountMoney(paymentMethod)) {
    return {
      name: buildAccountMoneyName(paymentMethod, deps),
      thumbnail: deps.thumbnails.yellowWalletPath,
      suppressLastFour: false,
      extraClasses: presentation.accountMoneyRowClasses()
    };
  }
  if (isConsumerCredits(paymentMethod)) {
    return {
      name: buildConsumerCreditsName(deps.siteId),
      thumbnail: deps.thumbnails.yellowMoneyPath,
      suppressLastFour: false,
      extraClasses: []
    };
  }
  if (isMercadoPagoCard(paymentMethod)) {
    return {
      name: deps.copy.mercadoPagoCardName,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: []
    };
  }
  if (isPrepaidCard(paymentMethod)) {
    return {
      name: paymentMethod.name,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: []
    };
  }
  if (PaymentMethodClassifier_isCreditCard(paymentMethod) || PaymentMethodClassifier_isDebitCard(paymentMethod)) {
    var _paymentMethod$issuer;
    const mpCreditCard = isMercadoPagoCreditCard(paymentMethod) ? presentation.mercadoPagoCreditCard(paymentMethod) : null;
    if (mpCreditCard) {
      return {
        ...mpCreditCard,
        suppressLastFour: true,
        extraClasses: []
      };
    }
    const cardKind = PaymentMethodClassifier_isCreditCard(paymentMethod) ? 'Crédito' : 'Débito';
    return {
      name: `${(_paymentMethod$issuer = paymentMethod.issuer?.name) !== null && _paymentMethod$issuer !== void 0 ? _paymentMethod$issuer : paymentMethod.name} ${cardKind}`,
      thumbnail: resolveCardThumbnail(paymentMethod, deps),
      suppressLastFour: false,
      extraClasses: []
    };
  }

  // Unreachable for the known SDK union; defensive for an unexpected runtime type.
  const unknownMethod = paymentMethod;
  return {
    name: (_unknownMethod$name = unknownMethod.name) !== null && _unknownMethod$name !== void 0 ? _unknownMethod$name : '',
    thumbnail: (_unknownMethod$thumbn = unknownMethod.thumbnail) !== null && _unknownMethod$thumbn !== void 0 ? _unknownMethod$thumbn : '',
    suppressLastFour: false,
    extraClasses: []
  };
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/paymentMethodRow.ts
/**
 * Builds the presentational DOM of a single saved-method row, shared by both variant
 * views. Rendered through the `el` helper (textContent/setAttribute only, no `innerHTML`),
 * so user- and SDK-provided values (name, thumbnail) can never break out into markup
 * (RN-3, SEC-3).
 *
 * Scope: the visual skeleton only (thumbnail, title, last-four, value-prop, classes) — the
 * part where variants differ, via `RowPresentation`. Behaviour wiring (click/keydown,
 * installments dropdown, card detail accordion, metrics) is attached by the checkout
 * orchestrator (TASK-009), not here.
 *
 * `deps.siteId` is expected already normalized to uppercase by the composition point
 * (`createVariantView`), so no per-call `.toUpperCase()` is needed.
 */






const TEMPORARY_ID_BYTES = 8;
const TEMPORARY_ID_LENGTH = 13;
const MLB_INSTALLMENT_SUFFIX = 'x';
function temporaryId() {
  return Array.from(crypto.getRandomValues(new Uint8Array(TEMPORARY_ID_BYTES)), byte => byte.toString(36)).join('').substring(0, TEMPORARY_ID_LENGTH);
}
function installmentSuffix(deps) {
  return deps.siteId === BRAZIL_ACCRONYM ? MLB_INSTALLMENT_SUFFIX : '';
}
function valuePropText(freeInstallments, deps) {
  return `${deps.copy.interestFreePartOneText} ${freeInstallments}${installmentSuffix(deps)} ${deps.copy.interestFreePartTwoText}`;
}
function buildAriaLabel(name, lastFour, freeInstallments, deps) {
  const cleanName = (name !== null && name !== void 0 ? name : '').replace(/&nbsp;|\u00a0/g, ' ');
  const lastFourText = lastFour ? ` ${deps.copy.lastDigitsText} ${lastFour}` : '';
  const installmentsText = freeInstallments > 1 ? ` ${valuePropText(freeInstallments, deps)}` : '';
  return `${cleanName}${lastFourText}${installmentsText}`;
}
function buildPaymentMethodRow(paymentMethod, deps, presentation) {
  var _paymentMethod$card$c, _model$thumbnail;
  const model = resolvePaymentMethodView(paymentMethod, deps, presentation);
  const lastFour = model.suppressLastFour ? null : 'card' in paymentMethod ? (_paymentMethod$card$c = paymentMethod.card?.card_number?.last_four_digits) !== null && _paymentMethod$card$c !== void 0 ? _paymentMethod$card$c : null : null;
  const freeInstallments = installmentsWithoutFee(paymentMethod);
  const showValueProp = (PaymentMethodClassifier_isCreditCard(paymentMethod) || isConsumerCredits(paymentMethod)) && freeInstallments > 1;
  const id = paymentMethod?.id ? paymentMethodIdentifier(paymentMethod) : temporaryId();
  const ariaLabel = buildAriaLabel(model.name, lastFour, showValueProp ? freeInstallments : 0, deps);
  const row = el('article', {
    classes: [SHARED_STYLES.PAYMENT_METHOD, ...model.extraClasses],
    attrs: {
      id,
      'aria-label': ariaLabel,
      tabindex: '0',
      role: 'option',
      'aria-selected': 'false'
    },
    dataset: {
      type: paymentMethod?.type,
      id,
      baseAriaLabel: ariaLabel
    },
    children: [el('section', {
      classes: [SHARED_STYLES.PAYMENT_METHOD_HEADER],
      children: [el('figure', {
        classes: [SHARED_STYLES.PAYMENT_METHOD_THUMBNAIL],
        children: [el('img', {
          attrs: {
            src: (_model$thumbnail = model.thumbnail) !== null && _model$thumbnail !== void 0 ? _model$thumbnail : '',
            alt: '',
            'aria-hidden': 'true'
          }
        })]
      }), el('article', {
        classes: [SHARED_STYLES.PAYMENT_METHOD_CONTENT],
        children: [el('section', {
          classes: [SHARED_STYLES.PAYMENT_METHOD_CONTENT_TITLE],
          children: [el('span', {
            classes: [SHARED_STYLES.PAYMENT_METHOD_TITLE],
            text: model.name
          }), lastFour ? el('span', {
            classes: [SHARED_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS],
            text: `**** ${lastFour}`
          }) : null]
        }), showValueProp ? el('span', {
          classes: [SHARED_STYLES.PAYMENT_METHOD_VALUE_PROP],
          attrs: {
            'aria-hidden': 'true'
          },
          text: valuePropText(freeInstallments, deps)
        }) : null]
      })]
    })]
  });
  return row;
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/interactiveRow.ts
/**
 * Builds an interactive saved-method row entirely in the tree: the presentation skeleton
 * (`buildPaymentMethodRow`) plus the selection wiring (click / Space / Enter), forwarding the
 * selection itself to the injected session (the legacy `onSelectSuperTokenPaymentMethod`, which
 * already delegates to the refactored selection seam). This replaces the legacy
 * `createPaymentMethodElement` row for the types ported into the tree — currently account money,
 * which has no detail accordion, installments or security-code field. Card and consumer-credits
 * rows (which add those details) are ported in the following slices.
 *
 * Wiring mirrors payment-methods.js:2019-2027 exactly (`click`; `keydown` on 'Space'/'Enter' with
 * preventDefault).
 */


function buildInteractiveRow(paymentMethod, deps, presentation, session) {
  const row = buildPaymentMethodRow(paymentMethod, deps, presentation);
  row.addEventListener('click', () => session.onSelectPaymentMethod(row, paymentMethod));
  row.addEventListener('keydown', event => {
    if (event.code === 'Space' || event.key === 'Enter') {
      event.preventDefault();
      session.onSelectPaymentMethod(row, paymentMethod);
    }
  });
  return row;
}
;// ./assets/js/checkouts/super-token/core/checkoutSession/PaymentMethodEligibility.ts
/**
 * Rules deciding whether a card needs a CVV/ESC re-fetch before it can be used
 * (RN-3). Pure: the legacy `data-cvv-is-required-double-check` DOM guard is lifted
 * into an `alreadyDoubleChecked` parameter the caller reads from the element, so the
 * core never touches the DOM.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): 1225-1267.
 */



const securityCodeIsRequired = securityCodeSettings => {
  if (!securityCodeSettings) {
    return false;
  }
  return securityCodeSettings.mode === 'mandatory';
};

/**
 * Whether the payment method must be re-fetched to obtain the ESC-backed token.
 * @param alreadyDoubleChecked replaces the legacy DOM guard: the caller passes
 *   whether the element is already marked as double-checked (prevents re-fetch loops).
 */
const shouldFetchPaymentMethodAgain = (paymentMethod, alreadyDoubleChecked) => {
  if (!paymentMethod) {
    throw new Error(MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS);
  }
  if (alreadyDoubleChecked) return false;
  return (isCreditCard(paymentMethod) || isDebitCard(paymentMethod)) && securityCodeIsRequired(paymentMethod.security_code_settings) && paymentMethod.has_esc === true;
};
const hasMissingEsc = paymentMethod => (isCreditCard(paymentMethod) || isDebitCard(paymentMethod)) && securityCodeIsRequired(paymentMethod.security_code_settings) && typeof paymentMethod.has_esc === 'undefined';
/** Diagnostic reason a re-fetch was skipped (observability, RN-3). */
const getSkipReason = (paymentMethod, alreadyDoubleChecked) => {
  if (alreadyDoubleChecked) {
    return 'already_checked';
  }
  if (!isCreditCard(paymentMethod) && !isDebitCard(paymentMethod)) {
    return 'not_card';
  }
  if (!securityCodeIsRequired(paymentMethod.security_code_settings)) {
    return 'security_code_not_required';
  }
  if (paymentMethod.has_esc !== true) {
    return 'esc_disabled';
  }
  return 'unknown';
};
;// ./assets/js/checkouts/super-token/adapters/view/shared/securityCodeField.ts
/**
 * The security-code (CVV) field container for a saved card — an empty mount point the SDK fills
 * on selection (mountSecurityCodeField, still legacy), plus its label, tooltip and error slot.
 * Ported from the legacy `buildSecurityCodeInnerHTML` (payment-methods.js:1420-1470); returns null
 * when the card does not require a CVV (`security_code_settings.mode !== 'mandatory'`).
 *
 * Built with `el` (textContent/setAttribute), so the SDK-provided token only ever lands in id
 * attributes, never as markup. The decorative error icon is a fixed SVG with no interpolation.
 */




const THREE_DIGITS = 3;

// Fixed decorative error icon (no data interpolation) — parsed once and cloned per field.
const ERROR_ICON_SVG = '<svg aria-hidden="true" tabindex="-1" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">' + '<rect width="12" height="12" rx="6" fill="#CC1818"/>' + '<path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>' + '<path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>' + '</svg>';
function errorIcon() {
  const template = document.createElement('template');
  template.innerHTML = ERROR_ICON_SVG;
  return template.content.firstElementChild;
}
function buildSecurityCodeField(paymentMethod, deps) {
  if (!('security_code_settings' in paymentMethod)) {
    return null;
  }
  const settings = paymentMethod.security_code_settings;
  if (!securityCodeIsRequired(settings)) {
    return null;
  }
  const token = paymentMethod.token;
  const tooltipText = settings?.length === THREE_DIGITS ? deps.copy.securityCodeTooltip3Digits : deps.copy.securityCodeTooltip4Digits;
  return el('div', {
    classes: [SHARED_STYLES.SECURITY_CODE_CONTAINER],
    attrs: {
      id: `mp-super-token-security-code-container-${token}`
    },
    children: [el('label', {
      classes: [SHARED_STYLES.SECURITY_CODE_LABEL],
      attrs: {
        tabindex: '0'
      },
      text: deps.copy.securityCodeInputTitle
    }), el('div', {
      classes: [SHARED_STYLES.SECURITY_CODE_INPUT],
      attrs: {
        id: `mp-super-token-security-code-input-${token}`
      }
    }), el('span', {
      classes: [SHARED_STYLES.SECURITY_CODE_TOOLTIP],
      attrs: {
        tabindex: '0',
        'aria-label': tooltipText,
        role: 'tooltip',
        'data-tooltip': tooltipText,
        style: 'display: none !important;'
      },
      text: '?'
    }), el('div', {
      classes: [SHARED_STYLES.INPUT_TOOLTIP_HELPER_ERROR],
      attrs: {
        id: 'mp-input-with-tooltip-helper-error',
        tabindex: '0',
        role: 'alert'
      },
      children: [errorIcon(), el('span', {
        classes: [SHARED_STYLES.SECURITY_CODE_ERROR_MESSAGE],
        attrs: {
          id: 'mp-super-token-security-code-error-message',
          'aria-hidden': 'true',
          tabindex: '-1'
        }
      })]
    })]
  });
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/installmentsDom.ts
/**
 * Shared DOM helpers for the installments `<select>` of card and consumer-credits rows: the id
 * conventions and the error-state / selected / shared-field operations. All checkout-DOM only (no
 * globals). Ported from the legacy `setInstallmentsErrorState` (payment-methods.js:1681-1698),
 * `installmentsWasSelected` (1675-1678) and the `#cardInstallments` mirroring in the change handler.
 */



const CARD_INSTALLMENTS_FIELD_ID = 'cardInstallments';
function installmentsSelectId(paymentMethod) {
  return `mp-super-token-installments-select-${paymentMethodIdentifier(paymentMethod)}`;
}
function installmentsErrorHelperId(paymentMethod) {
  return `mp-super-token-installments-error-${paymentMethodIdentifier(paymentMethod)}`;
}
function taxInfoElementId(paymentMethod) {
  return `mp-super-token-installments-tax-info-${paymentMethodIdentifier(paymentMethod)}`;
}
function findInstallmentsSelect(row, paymentMethod) {
  return row.querySelector(`#${CSS.escape(installmentsSelectId(paymentMethod))}`);
}
function setInstallmentsErrorState(paymentMethod, hasError) {
  const selectId = installmentsSelectId(paymentMethod);
  const select = document.getElementById(selectId);
  const label = document.querySelector(`label[for="${selectId}"]`);
  const errorHelper = document.getElementById(installmentsErrorHelperId(paymentMethod));
  if (!select || !label || !errorHelper) {
    return;
  }
  if (hasError) {
    errorHelper.style.display = 'flex';
    select.classList.add(SHARED_STYLES.INSTALLMENTS_ERROR);
    label.classList.add(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR);
  } else {
    errorHelper.style.display = 'none';
    select.classList.remove(SHARED_STYLES.INSTALLMENTS_ERROR);
    label.classList.remove(SHARED_STYLES.INSTALLMENTS_LABEL_ERROR);
  }
}
function installmentsWasSelected(paymentMethod) {
  const select = document.getElementById(installmentsSelectId(paymentMethod));
  return !!select?.value;
}
function syncCardInstallments(value) {
  const field = document.getElementById(CARD_INSTALLMENTS_FIELD_ID);
  if (field) {
    field.value = value;
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/cardRow.ts
/**
 * Builds an interactive card row (credit / debit / prepaid) entirely in the tree: the presentation
 * skeleton and selection wiring from `buildInteractiveRow`, plus the detail accordion — the
 * installments `<select>` (credit only) with its tax-info line, and the security-code field — and
 * the installments behaviour. Ported from the legacy `createPaymentMethodElement` detail + wiring
 * (payment-methods.js:2015-2079) and `buildCreditCardDetailsInnerHTML` (1625-1673).
 *
 * DOM-only work (the row's own select, its error state and the shared #cardInstallments field) is
 * done through `installmentsDom`; the platform globals (CheckoutPage / MPCheckoutFieldsDispatcher /
 * sendMetric) stay behind the injected session.
 */







const INSTALLMENTS_FILLED_METHOD_TYPE = 'credit_card';
const DISPATCHER_MISSING_CONTEXT = 'super_token_installments_setup';

// `<input-helper>` is a custom element (outside HTMLElementTagNameMap), so it is built directly.
function buildInputHelper(message, inputId) {
  const inputHelper = document.createElement('input-helper');
  inputHelper.setAttribute('isVisible', 'false');
  inputHelper.setAttribute('type', 'error');
  inputHelper.setAttribute('message', message);
  inputHelper.setAttribute('input-id', inputId);
  return inputHelper;
}
function buildInstallmentsField(paymentMethod, deps, options) {
  const selectId = installmentsSelectId(paymentMethod);
  return el('div', {
    classes: [SHARED_STYLES.INSTALLMENTS_SELECT_CONTAINER],
    children: [el('label', {
      classes: [SHARED_STYLES.INPUT_LABEL],
      attrs: {
        for: selectId
      },
      text: deps.copy.installmentsInputTitle
    }), el('select', {
      classes: [SHARED_STYLES.SELECT_INPUT],
      attrs: {
        'data-checkout': 'installments',
        name: 'installments',
        id: selectId
      },
      children: options.map((option, index) => el('option', {
        attrs: index === 0 ? {
          value: option.value,
          selected: 'selected'
        } : {
          value: option.value
        },
        text: option.title
      }))
    }), buildInputHelper(deps.copy.installmentsRequiredMessage, installmentsErrorHelperId(paymentMethod)), el('div', {
      classes: [SHARED_STYLES.INSTALLMENTS_TAX_INFO],
      attrs: {
        id: taxInfoElementId(paymentMethod),
        style: 'display: none;'
      }
    })]
  });
}
function buildCardDetailsSection(paymentMethod, deps, installmentOptions) {
  const wrapperChildren = [];
  if (PaymentMethodClassifier_isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
    wrapperChildren.push(buildInstallmentsField(paymentMethod, deps, installmentOptions(paymentMethod)));
  }
  wrapperChildren.push(buildSecurityCodeField(paymentMethod, deps));
  const wrapper = el('div', {
    classes: [SHARED_STYLES.METHOD_DETAILS_WRAPPER],
    children: wrapperChildren
  });
  return el('section', {
    classes: [SHARED_STYLES.PAYMENT_METHOD_DETAILS, SHARED_STYLES.PAYMENT_METHOD_HIDE],
    children: [wrapper]
  });
}
function wireInstallments(row, paymentMethod, installments, session) {
  const dropdown = findInstallmentsSelect(row, paymentMethod);
  if (!dropdown) {
    return;
  }
  session.reportInstallmentDispatcherMissing(DISPATCHER_MISSING_CONTEXT);
  dropdown.addEventListener('change', event => {
    const selected = event.target.value;
    if (!selected) {
      return;
    }
    session.installmentSelected(INSTALLMENTS_FILLED_METHOD_TYPE);
    setInstallmentsErrorState(paymentMethod, false);
    session.updateInstallmentsTaxInfo(selected, taxInfoElementId(paymentMethod), installments);
    syncCardInstallments(selected);
  });
  dropdown.addEventListener('blur', () => {
    setInstallmentsErrorState(paymentMethod, !installmentsWasSelected(paymentMethod));
  });

  // Restore the tax info + shared field when a value is already selected (e.g. after a payment error).
  if (dropdown.value) {
    syncCardInstallments(dropdown.value);
    session.updateInstallmentsTaxInfo(dropdown.value, taxInfoElementId(paymentMethod), installments);
  }
}
function buildCardRow(paymentMethod, deps, presentation, session, installmentOptions) {
  const row = buildInteractiveRow(paymentMethod, deps, presentation, session);
  row.appendChild(buildCardDetailsSection(paymentMethod, deps, installmentOptions));
  if (PaymentMethodClassifier_isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
    wireInstallments(row, paymentMethod, paymentMethod.installments, session);
  }
  return row;
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/consumerCreditsRow.ts
/**
 * Builds the interactive consumer-credits (digital_currency) row in the tree: presentation +
 * selection wiring (buildInteractiveRow), the detail accordion (installments `<select>` with a
 * placeholder, plus hint / due-date / debit-auto / legal slots) and the asynchronous credits
 * behaviour. Ported from the legacy `createPaymentMethodElement` consumer-credits branch
 * (payment-methods.js:2081-2192), `buildConsumerCreditsDetailsInnerHTML` (1552-1606),
 * `buildConsumerCreditsDetailsDueDate` (1608-1623), `buildMLBConsumerCreditsLegalText` (1700-1710)
 * and `formatDateToDayAndMonth` (1080-1113).
 *
 * The site-specific hint (pure) is injected from the domain core; the SDK contract, the fast-payment
 * token and the metrics stay behind the injected session.
 */








const consumerCreditsRow_DISPATCHER_MISSING_CONTEXT = 'super_token_consumer_credits_installments_setup';
const CONTRACT_CUSTOMIZATION = {
  textColor: '#000000',
  textSize: '13px',
  linkColor: '#3483FA'
};
const HINT_ID = 'mp-consumer-credits-hint';
const DUE_DATE_ID = 'mp-consumer-credits-due-date';
const DEBIT_AUTO_ID = 'mp-consumer-credits-debit-auto-text';
const LEGAL_TEXT_ID = 'mp-consumer-credits-legal-text';
const NO_HINT_CONTENT = 'no_hint_content_to_render';
const MONTHS_MAPPING = {
  '01': 'jan',
  '02': 'feb',
  '03': 'mar',
  '04': 'apr',
  '05': 'may',
  '06': 'jun',
  '07': 'jul',
  '08': 'aug',
  '09': 'sep',
  '10': 'oct',
  '11': 'nov',
  '12': 'dec'
};
function formatDateToDayAndMonth(isoDate, deps) {
  var _deps$monthsAbbreviat;
  if (!isoDate) {
    return '';
  }
  const dateParts = isoDate.split('-');
  if (dateParts.length !== 3) {
    return isoDate;
  }
  const [, month, day] = dateParts;
  const monthText = (_deps$monthsAbbreviat = deps.monthsAbbreviated[MONTHS_MAPPING[month]]) !== null && _deps$monthsAbbreviat !== void 0 ? _deps$monthsAbbreviat : month;
  return `${parseInt(day, 10)}/${monthText}`;
}
function buildDetailsSection(paymentMethod, deps, options) {
  const selectId = installmentsSelectId(paymentMethod);
  return el('section', {
    classes: [SHARED_STYLES.PAYMENT_METHOD_DETAILS, SHARED_STYLES.PAYMENT_METHOD_HIDE],
    children: [el('div', {
      classes: [SHARED_STYLES.INSTALLMENTS_SELECT_CONTAINER],
      children: [el('label', {
        classes: [SHARED_STYLES.INPUT_LABEL],
        attrs: {
          for: selectId
        },
        text: deps.copy.installmentsInputTitle
      }), el('select', {
        classes: [SHARED_STYLES.SELECT_INPUT],
        attrs: {
          'data-checkout': 'installments',
          name: 'installments',
          id: selectId
        },
        children: [el('option', {
          attrs: {
            disabled: '',
            selected: '',
            value: ''
          },
          text: deps.copy.installmentsPlaceholder
        }), ...options.map(option => el('option', {
          attrs: {
            value: option.value
          },
          text: option.title
        }))]
      }), buildInputHelper(deps.copy.installmentsRequiredMessage, installmentsErrorHelperId(paymentMethod))]
    }), el('div', {
      attrs: {
        id: HINT_ID,
        style: 'display: none;'
      }
    }), el('div', {
      classes: ['mp-consumer-credits-due-date'],
      attrs: {
        id: DUE_DATE_ID,
        style: 'display: none;'
      }
    }), el('div', {
      classes: ['mp-consumer-credits-debit-auto-text'],
      attrs: {
        id: DEBIT_AUTO_ID,
        style: 'display: none; text-align: center;'
      }
    }), el('div', {
      attrs: {
        id: LEGAL_TEXT_ID,
        style: 'display: none;'
      }
    })]
  });
}

// Fills the due-date slot via DOM APIs (the date comes from the SDK, so it must never reach an
// HTML sink); throws when the slot is absent (mirrors the legacy method, whose throw is caught
// by the change handler to record the due-date failure metric).
function renderDueDate(paymentMethod, deps) {
  const element = document.getElementById(DUE_DATE_ID);
  if (!element) {
    throw new Error('Consumer credits due date element not found');
  }
  const nextDueDate = 'next_due_date' in paymentMethod ? paymentMethod.next_due_date : undefined;
  element.textContent = '';
  element.appendChild(el('span', {
    attrs: {
      style: 'font-weight: 400 !important;'
    },
    children: [document.createTextNode(`${deps.copy.consumerCreditsDueDateText} `), el('b', {
      attrs: {
        style: 'font-weight: 600 !important;'
      },
      text: formatDateToDayAndMonth(nextDueDate, deps)
    }), document.createTextNode('.')]
  }));
}
function renderMlbLegalText(deps) {
  const element = document.getElementById(DEBIT_AUTO_ID);
  if (!element) {
    return;
  }
  element.textContent = '';
  element.appendChild(el('span', {
    text: deps.copy.consumerCreditsDebitAutoText
  }));
}
function wireConsumerCredits(row, paymentMethod, deps, session, hint) {
  const select = findInstallmentsSelect(row, paymentMethod);
  if (!select || !isConsumerCredits(paymentMethod)) {
    return;
  }
  select.addEventListener('blur', () => {
    setInstallmentsErrorState(paymentMethod, !installmentsWasSelected(paymentMethod));
  });
  session.reportInstallmentDispatcherMissing(consumerCreditsRow_DISPATCHER_MISSING_CONTEXT);
  const parameters = {
    fastPaymentToken: session.getFastPaymentToken(),
    pricingId: paymentMethod.credits_pricing_id,
    pseudotoken: paymentMethod.token,
    customization: CONTRACT_CUSTOMIZATION
  };
  session.renderCreditsContract(LEGAL_TEXT_ID, parameters).then(contractController => {
    session.recordCreditsContractRendered(true);
    document.getElementById(LEGAL_TEXT_ID)?.addEventListener('click', event => {
      const target = event.target;
      if (target.tagName === 'A') {
        var _target$textContent$t;
        session.recordOpenCreditsInfoModal((_target$textContent$t = target.textContent?.trim()) !== null && _target$textContent$t !== void 0 ? _target$textContent$t : '');
      }
    });
    select.addEventListener('change', event => {
      var _paymentMethod$instal;
      const selectedValue = event.target.value;
      if (!selectedValue) {
        return;
      }
      session.dispatchInstallmentsFilledField();
      if (deps.siteId === BRAZIL_ACCRONYM) {
        renderMlbLegalText(deps);
        const debitAuto = document.getElementById(DEBIT_AUTO_ID);
        if (debitAuto) {
          debitAuto.style.display = 'block';
        }
      }
      syncCardInstallments(`${parseInt(selectedValue, 10)}`);
      const selectedInstallment = ((_paymentMethod$instal = paymentMethod.installments) !== null && _paymentMethod$instal !== void 0 ? _paymentMethod$instal : []).find(installment => installment.installments === parseInt(selectedValue, 10));
      if (selectedInstallment) {
        applySelectedInstallmentDetails(paymentMethod, deps, session, hint, selectedInstallment);
      }
      session.updateCreditsContract(contractController, selectedValue);
    });

    // Restore the selection (e.g. after a payment error) once the change listener is live.
    if (select.value) {
      select.dispatchEvent(new Event('change'));
    }
  }).catch(error => {
    session.recordCreditsContractRendered(false, error);
  });
}
function applySelectedInstallmentDetails(paymentMethod, deps, session, hint, selectedInstallment) {
  const hintElement = document.getElementById(HINT_ID);
  if (hintElement) {
    try {
      const hintContent = hint(selectedInstallment);
      hintElement.innerHTML = hintContent;
      hintElement.style.display = hintContent ? 'block' : 'none';
      session.recordConsumerCreditsHint(!!hintContent, hintContent ? undefined : NO_HINT_CONTENT);
    } catch (error) {
      hintElement.style.display = 'none';
      session.recordConsumerCreditsHint(false, error);
    }
  }
  try {
    renderDueDate(paymentMethod, deps);
    session.recordConsumerCreditsDueDate(true);
    const dueDate = document.getElementById(DUE_DATE_ID);
    if (dueDate) {
      dueDate.style.display = 'block';
    }
  } catch (error) {
    session.recordConsumerCreditsDueDate(false, error);
  }
  const legal = document.getElementById(LEGAL_TEXT_ID);
  if (legal) {
    legal.style.display = 'block';
  }
}
function buildConsumerCreditsRow(paymentMethod, deps, presentation, session, installmentOptions, hint) {
  const row = buildInteractiveRow(paymentMethod, deps, presentation, session);
  try {
    row.appendChild(buildDetailsSection(paymentMethod, deps, installmentOptions(paymentMethod)));
    session.recordConsumerCreditsDetails(true);
  } catch (error) {
    session.recordConsumerCreditsDetails(false);
    throw error;
  }
  wireConsumerCredits(row, paymentMethod, deps, session, hint);
  return row;
}
;// ./assets/js/checkouts/super-token/adapters/view/shared/typedRow.ts
/**
 * Per-type saved-method row dispatch, shared by every variant view. Both v2 and v2.1 build the
 * SAME interactive row for a given method type — only the surrounding chrome (flat list vs.
 * grouped blocks, header/e-mail) and the `RowPresentation` differ. Keeping the dispatch in one
 * place is deliberate: the variants previously diverged here (v2.1 was ported to interactive rows,
 * v2 was left presentation-only), which broke selection under the v2 variant once the legacy
 * `createPaymentMethodElement` (`context.buildRow`) was dropped.
 *
 * When the behaviour primitives (`rowSession` + the domain composers) are present, the tree builds
 * the row itself and wires selection. When they are absent (unit tests, or a not-yet-ported row
 * type) it falls back to the injected legacy factory or, failing that, the presentation-only row.
 */






function isCard(paymentMethod) {
  return PaymentMethodClassifier_isCreditCard(paymentMethod) || PaymentMethodClassifier_isDebitCard(paymentMethod) || isPrepaidCard(paymentMethod);
}
function buildTypedRow(paymentMethod, deps, presentation, context) {
  try {
    if (isAccountMoney(paymentMethod) && context.rowSession) {
      return buildInteractiveRow(paymentMethod, deps, presentation, context.rowSession);
    }
    if (isCard(paymentMethod) && context.rowSession && context.installmentOptions) {
      return buildCardRow(paymentMethod, deps, presentation, context.rowSession, context.installmentOptions);
    }
    if (isConsumerCredits(paymentMethod) && context.rowSession && context.installmentOptions && context.consumerCreditsHint) {
      return buildConsumerCreditsRow(paymentMethod, deps, presentation, context.rowSession, context.installmentOptions, context.consumerCreditsHint);
    }
    return context.buildRow ? context.buildRow(paymentMethod) : buildPaymentMethodRow(paymentMethod, deps, presentation);
  } catch (error) {
    context.rowSession?.recordPaymentMethodRowFailure?.(error);
    try {
      // Keep the method visible even when its interactive details fail. This fallback contains
      // presentation only, so the broken row cannot interrupt the remaining methods.
      return buildPaymentMethodRow(paymentMethod, deps, presentation);
    } catch {
      // A malformed row must never abort the list. If even its presentation cannot be built,
      // omit only this method and let the variant render every other row.
      return null;
    }
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2/styles.ts
/** CSS classes used only by the v2 view (the single global list header). */
const V2_STYLES = {
  PAYMENT_METHODS_LIST_HEADER: 'mp-payment-methods-header',
  PAYMENT_METHODS_LIST_HEADER_LOGO: 'mp-payment-methods-header-logo'
};
;// ./assets/js/checkouts/super-token/adapters/view/v2/SavedCardsView.ts
/**
 * v2 saved-methods rendering: a single flat list with one global header (no e-mail, no
 * grouped blocks). Ported from the legacy v2 `organizePaymentMethodsElements` +
 * `addPaymentMethodsListHeader`.
 */





/** v2 row seam: no Mercado Pago credit-card special case, no account-money row class. */
const V2_ROW_PRESENTATION = {
  mercadoPagoCreditCard: () => null,
  accountMoneyRowClasses: () => []
};
class V2SavedCardsView {
  constructor(deps) {
    this.deps = deps;
  }
  render(context) {
    const {
      container,
      paymentMethods
    } = context;
    // Build each row through the shared per-type dispatch so v2 wires selection (and card
    // installments / credits) exactly like v2.1, differing only in the flat-list chrome below.
    const buildRow = paymentMethod => buildTypedRow(paymentMethod, this.deps, V2_ROW_PRESENTATION, context);
    // Insert each row at the top in reverse so the first method ends up first, then prepend the
    // single list header above them all (faithful to the legacy insert order).
    let renderedRows = 0;
    [...paymentMethods].reverse().forEach(paymentMethod => {
      const row = buildRow(paymentMethod);
      if (row) {
        container.insertBefore(row, container.firstChild);
        renderedRows += 1;
      }
    });
    if (renderedRows > 0) {
      container.insertBefore(this.buildListHeader(), container.firstChild);
    }
  }
  reset(container) {
    container.querySelector(`.${V2_STYLES.PAYMENT_METHODS_LIST_HEADER}`)?.remove();
  }
  buildListHeader() {
    return el('header', {
      classes: [V2_STYLES.PAYMENT_METHODS_LIST_HEADER],
      children: [el('span', {
        text: this.deps.copy.paymentMethodsListText
      }), el('img', {
        classes: [V2_STYLES.PAYMENT_METHODS_LIST_HEADER_LOGO],
        attrs: {
          alt: 'Mercado Pago',
          src: this.deps.thumbnails.newMpLogoPath
        }
      })]
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2/AccountMoneyDecoration.ts
/**
 * v2 has no account-money selection decoration — the balance line is a v2.1-only feature.
 * Both hooks are intentional no-ops so the checkout orchestrator can call them uniformly
 * without branching on the variant.
 */
class V2AccountMoneyDecoration {
  decorate() {
    // no-op: v2 shows no account-money balance line
  }
  clear() {
    // no-op: v2 has nothing to undo
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2/V2View.ts
/**
 * The v2 variant view: composes the flat-list saved-cards view with the no-op
 * account-money decoration. Implements VariantViewPort by delegation (never inheritance —
 * v2.1 must not extend v2, ADR-001).
 */



class V2View {
  constructor(deps) {
    this.savedCardsView = new V2SavedCardsView(deps);
    this.accountMoneyDecoration = new V2AccountMoneyDecoration();
  }
  renderSavedPaymentMethods(context) {
    this.savedCardsView.render(context);
  }
  decorateSelection() {
    this.accountMoneyDecoration.decorate();
  }
  clearSelectionDecoration() {
    this.accountMoneyDecoration.clear();
  }
  reset(container) {
    this.savedCardsView.reset(container);
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2.1/styles.ts
/** CSS classes used only by the v2.1 view (grouped blocks + account-money decoration). */
const V21_STYLES = {
  BLOCK: 'mp-super-token-block',
  BLOCK_SAVED_CARDS: 'mp-super-token-block--saved-cards',
  BLOCK_OTHER_MP_METHODS: 'mp-super-token-block--other-mp',
  BLOCK_HEADER: 'mp-super-token-block__header',
  BLOCK_HEADER_INFO: 'mp-super-token-block__header-info',
  BLOCK_TITLE: 'mp-super-token-block__title',
  BLOCK_EMAIL: 'mp-super-token-block__email',
  BLOCK_HEADER_LOGO: 'mp-super-token-block__header-logo',
  ACCOUNT_MONEY_ROW: 'mp-super-token-account-money-row',
  ACCOUNT_MONEY_ROW_OPEN: 'mp-super-token-account-money-row--open',
  ACCOUNT_MONEY_BALANCE_LINE: 'mp-super-token-am-balance-text',
  ACCOUNT_MONEY_BALANCE_LINE_OPEN: 'mp-super-token-am-balance-text--open'
};
;// ./assets/js/checkouts/super-token/adapters/view/v2.1/SavedCardsView.ts
/**
 * v2.1 saved-methods rendering: two grouped blocks (saved cards vs. other MP methods) with a
 * per-block header that carries the buyer's e-mail and a live e-mail listener. Ported from the
 * legacy v2.1 `organizePaymentMethodsElements`, `groupPaymentMethods`, `buildBlockHeader`,
 * `renderSavedCardsBlock`, `renderOtherMpMethodsBlock` and `setupEmailHeaderListener`.
 */







class V21SavedCardsView {
  emailHeaderListenerRegistered = false;
  // The e-mail listener is registered once but must always target the current checkout
  // container: WooCommerce can rebuild the DOM (updated_checkout, multi-step) and re-render
  // into a replacement node, so the callback reads this field instead of closing over the
  // container from the first render (matches the legacy dynamic lookup).
  currentContainer = null;
  constructor(deps) {
    this.deps = deps;
    this.rowPresentation = {
      mercadoPagoCreditCard: paymentMethod => ({
        name: this.deps.copy.mercadoPagoCreditCardName || paymentMethod.name,
        thumbnail: mpCardThumbnailPath(this.deps) || paymentMethod.thumbnail
      }),
      accountMoneyRowClasses: () => [V21_STYLES.ACCOUNT_MONEY_ROW]
    };
  }
  render(context) {
    const {
      container
    } = context;
    this.currentContainer = container;
    try {
      this.renderBlocks(context);
    } catch (error) {
      // Leave a clean container for the legacy fallback: drop any block inserted before the
      // failure so the inline path (organizePaymentMethodsElements) never duplicates it.
      this.reset(container);
      throw error;
    }
  }
  renderBlocks(context) {
    const {
      container,
      paymentMethods
    } = context;
    const buildRow = this.rowFactory(context);
    const {
      cardPaymentMethods,
      otherPaymentMethods
    } = this.groupPaymentMethods(paymentMethods);
    const blockHeader = {
      email: this.deps.emailListener?.getEmail() || this.deps.currentUserEmail,
      icon: this.deps.thumbnails.newMpLogoPath
    };
    if (!cardPaymentMethods.length) {
      const title = otherPaymentMethods.length === 1 ? this.deps.copy.savedPaymentMethodTitle : this.deps.copy.paymentMethodsListText;
      this.renderBlock(container, otherPaymentMethods, V21_STYLES.BLOCK_OTHER_MP_METHODS, title, blockHeader, buildRow);
      this.setupEmailHeaderListener();
      return;
    }
    const savedCardsTitle = cardPaymentMethods.length === 1 ? this.deps.copy.savedCardTitle : this.deps.copy.savedCardsTitle;

    // Block 2 renders first so Block 1 ends up on top via insertBefore(firstChild).
    this.renderBlock(container, otherPaymentMethods, V21_STYLES.BLOCK_OTHER_MP_METHODS, this.deps.copy.mpMethodsTitle, null, buildRow);
    this.renderBlock(container, cardPaymentMethods, V21_STYLES.BLOCK_SAVED_CARDS, savedCardsTitle, blockHeader, buildRow);
    this.setupEmailHeaderListener();
  }
  reset(container) {
    container.querySelectorAll(`.${V21_STYLES.BLOCK}`).forEach(block => block.remove());
  }

  // Per-type row factory: delegates to the shared dispatch so v2.1 and v2 build the identical
  // interactive row for a given method type. Only the v2.1 chrome (grouped blocks, e-mail header)
  // lives here; the row itself (presentation + selection wiring + card/credits details) is shared.
  rowFactory(context) {
    return paymentMethod => buildTypedRow(paymentMethod, this.deps, this.rowPresentation, context);
  }
  groupPaymentMethods(paymentMethods) {
    const isCard = paymentMethod => PaymentMethodClassifier_isCreditCard(paymentMethod) || PaymentMethodClassifier_isDebitCard(paymentMethod) || isPrepaidCard(paymentMethod);
    const cardPaymentMethods = paymentMethods.filter(isCard).slice(0, MAX_CREDIT_CARDS);
    const otherPaymentMethods = paymentMethods.filter(paymentMethod => !isCard(paymentMethod));
    return {
      cardPaymentMethods,
      otherPaymentMethods
    };
  }
  renderBlock(container, paymentMethods, blockModifierClass, title, blockHeader, buildRow) {
    if (!paymentMethods.length) {
      return;
    }
    const rows = paymentMethods.map(buildRow).filter(row => row !== null);
    if (!rows.length) {
      return;
    }
    const section = el('section', {
      classes: [V21_STYLES.BLOCK, blockModifierClass],
      attrs: {
        role: 'group',
        'aria-label': title,
        tabindex: '0'
      },
      children: [this.buildBlockHeader(title, blockHeader), ...rows]
    });
    container.insertBefore(section, container.firstChild);
  }
  buildBlockHeader(title, blockHeader) {
    var _blockHeader$icon;
    if (!blockHeader) {
      // Intentionally no BLOCK_HEADER_INFO wrapper — setupEmailHeaderListener relies on its absence.
      return el('header', {
        classes: [V21_STYLES.BLOCK_HEADER],
        children: [this.buildTitleSpan(title)]
      });
    }
    const showEmail = this.deps.emailListener?.isValid(blockHeader.email);
    return el('header', {
      classes: [V21_STYLES.BLOCK_HEADER],
      children: [el('div', {
        classes: [V21_STYLES.BLOCK_HEADER_INFO],
        children: [this.buildTitleSpan(title), showEmail ? this.buildEmailSpan(blockHeader.email) : null]
      }), el('img', {
        classes: [V21_STYLES.BLOCK_HEADER_LOGO],
        attrs: {
          alt: '',
          'aria-hidden': 'true',
          src: (_blockHeader$icon = blockHeader.icon) !== null && _blockHeader$icon !== void 0 ? _blockHeader$icon : ''
        }
      })]
    });
  }
  buildTitleSpan(title) {
    return el('span', {
      classes: [V21_STYLES.BLOCK_TITLE],
      text: title
    });
  }
  buildEmailSpan(email) {
    return el('span', {
      classes: [V21_STYLES.BLOCK_EMAIL],
      text: email
    });
  }
  setupEmailHeaderListener() {
    if (this.emailHeaderListenerRegistered || !this.deps.emailListener) {
      return;
    }
    this.emailHeaderListenerRegistered = true;
    this.deps.emailListener.onEmailChange((email, isValid) => {
      try {
        const headerInfo = this.currentContainer?.querySelector(`.${V21_STYLES.BLOCK_HEADER_INFO}`);
        if (!headerInfo) {
          return;
        }
        const existingSpan = headerInfo.querySelector(`.${V21_STYLES.BLOCK_EMAIL}`);
        if (!isValid) {
          existingSpan?.remove();
          return;
        }
        if (existingSpan) {
          existingSpan.textContent = email;
        } else {
          headerInfo.appendChild(this.buildEmailSpan(email));
        }
      } catch (error) {
        window.console?.warn?.('ST: email header update failed', error);
      }
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2.1/AccountMoneyDecoration.ts
/**
 * v2.1 account-money selection decoration: shows a balance line under the account-money row
 * when selected and animates it away on deselect. Ported 1:1 from the legacy
 * `applyAccountMoneySelectionDecoration` / `removeAccountMoneyBalanceLine` (v2.1).
 */




const ACCOUNT_MONEY_ANIMATION_MS = 300;
const TRANSITION_END_FALLBACK_MS = ACCOUNT_MONEY_ANIMATION_MS + 50;
const CLOSING_FLAG = '1';
const BALANCE_TRANSITION_PROPERTY = 'max-height';
class V21AccountMoneyDecoration {
  constructor(deps) {
    this.deps = deps;
  }
  decorate(row) {
    var _row$getAttribute;
    if (row?.dataset?.type !== ACCOUNT_MONEY_TYPE) {
      return;
    }

    // Remove any leftover balance line synchronously before appending the new one, so a fast
    // AM -> other -> AM toggle can't leave two balance nodes coexisting. Scoped to this row (the
    // balance line is always appended as its descendant) — matching clear()'s bounded scope — so
    // a second Super Token widget on the same page can't have its balance line removed by this one.
    row.querySelectorAll(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`).forEach(node => node.remove());
    const content = row.querySelector(`.${SHARED_STYLES.PAYMENT_METHOD_CONTENT}`);
    if (!content) {
      return;
    }
    const balanceLine = el('p', {
      classes: [V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE],
      attrs: {
        'aria-live': 'polite'
      },
      text: this.deps.copy.accountMoneyBalanceText
    });
    content.appendChild(balanceLine);

    // Trigger the row/balance transitions in the next frame. Stale-frame guard: if another method
    // was selected before this frame runs, the AM row is no longer selected/connected — skip it,
    // to avoid stranding an --open state on a deselected row.
    requestAnimationFrame(() => {
      if (!row.isConnected || !row.classList.contains(SHARED_STYLES.PAYMENT_METHOD_SELECTED)) {
        return;
      }
      row.classList.add(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN);
      balanceLine.classList.add(V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);
    });
    const balanceText = this.deps.copy.accountMoneyBalanceText;
    const currentLabel = (_row$getAttribute = row.getAttribute('aria-label')) !== null && _row$getAttribute !== void 0 ? _row$getAttribute : '';
    if (balanceText && !currentLabel.includes(balanceText)) {
      row.setAttribute('aria-label', `${currentLabel}. ${balanceText}`);
    }
  }
  clear(container) {
    // Restore each account-money row's aria-label (the balance text is appended on selection).
    container.querySelectorAll(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`).forEach(row => {
      const baseAriaLabel = row.dataset?.baseAriaLabel;
      if (baseAriaLabel !== undefined) {
        row.setAttribute('aria-label', baseAriaLabel);
      }
    });
    this.removeBalanceLine(container);
  }
  removeBalanceLine(container) {
    var _container$querySelec;
    const openRow = (_container$querySelec = container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW_OPEN}`)) !== null && _container$querySelec !== void 0 ? _container$querySelec : container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_ROW}`);
    openRow?.classList.remove(V21_STYLES.ACCOUNT_MONEY_ROW_OPEN);
    const balanceLine = container.querySelector(`.${V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE}`);
    if (!balanceLine) {
      return;
    }

    // Already closing — avoid duplicate listeners/timers on the same node (fast toggle).
    if (balanceLine.dataset.closing === CLOSING_FLAG) {
      return;
    }
    balanceLine.dataset.closing = CLOSING_FLAG;
    balanceLine.classList.remove(V21_STYLES.ACCOUNT_MONEY_BALANCE_LINE_OPEN);

    // Remove the node only after the close transition finishes (event-driven), so the DOM removal
    // never lands a frame before the animation ends. A timeout fallback guarantees cleanup if
    // transitionend never fires (reduced motion, detached node, etc.).
    let removed = false;
    let fallbackTimer;
    const finalize = () => {
      if (removed) {
        return;
      }
      removed = true;
      balanceLine.remove();
    };
    const onTransitionEnd = event => {
      if (event.target === balanceLine && event.propertyName === BALANCE_TRANSITION_PROPERTY) {
        clearTimeout(fallbackTimer);
        balanceLine.removeEventListener('transitionend', onTransitionEnd);
        finalize();
      }
    };
    balanceLine.addEventListener('transitionend', onTransitionEnd);
    fallbackTimer = setTimeout(finalize, TRANSITION_END_FALLBACK_MS);
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/v2.1/V21View.ts
/**
 * The v2.1 variant view: composes the grouped-blocks saved-cards view with the account-money
 * decoration. Implements VariantViewPort by delegation (never inheritance — v2.1 must not
 * extend v2, ADR-001).
 */



class V21View {
  constructor(deps) {
    this.savedCardsView = new V21SavedCardsView(deps);
    this.accountMoneyDecoration = new V21AccountMoneyDecoration(deps);
  }
  renderSavedPaymentMethods(context) {
    this.savedCardsView.render(context);
  }
  decorateSelection(row) {
    this.accountMoneyDecoration.decorate(row);
  }
  clearSelectionDecoration(container) {
    this.accountMoneyDecoration.clear(container);
  }
  reset(container) {
    this.savedCardsView.reset(container);
  }
}
;// ./assets/js/checkouts/super-token/adapters/view/VariantViewFactory.ts
/**
 * The single place that maps a resolved A/B variant string to its concrete view (RN-4).
 * An unknown variant falls back to v2 — the safe baseline. This is the only variant
 * decision in the tree; consumers depend on VariantViewPort, never on the string.
 */



const FALLBACK_VARIANT = 'v2';
const VARIANT_VIEWS = {
  v2: deps => new V2View(deps),
  'v2.1': deps => new V21View(deps)
};
function createVariantView(variant, deps) {
  var _VARIANT_VIEWS$varian;
  const create = (_VARIANT_VIEWS$varian = VARIANT_VIEWS[variant]) !== null && _VARIANT_VIEWS$varian !== void 0 ? _VARIANT_VIEWS$varian : VARIANT_VIEWS[FALLBACK_VARIANT];
  return create(deps);
}
;// ./assets/js/checkouts/super-token/adapters/view/createVariantViewDeps.ts
/**
 * Composition-root mapper: turns the localized store params
 * (`wc_mercadopago_supertoken_bundle_params`) into the view-local `VariantViewDeps` value
 * object, so the variant views never read `window.*`. The field mapping mirrors the legacy
 * controller's SCREAMING_CASE reads (`super-token-payment-methods.js` constructor, 67-123) 1:1.
 * The live e-mail listener is injected separately (it is a runtime instance, not a param).
 */

/**
 * The subset of `wc_mercadopago_supertoken_bundle_params` the variant views are built from.
 * Grounded in the legacy controller reads (payment-methods.js:67-123).
 */

function createVariantViewDeps(params, emailListener) {
  var _params$saved_cards_t, _params$saved_card_ti, _params$mp_methods_ti, _params$saved_payment, _params$account_money, _params$mercado_pago_, _params$mp_logo_blue_, _params$mp_logo_dark_;
  return {
    // Uppercased once here so the views compare it directly (VariantViewDeps contract).
    siteId: params.site_id.toUpperCase(),
    // Boundary against the localized store params: older plugin versions still served the CDN
    // bundle (saved-methods titles, credit-card name and logo paths landed in 8.8.0) may omit
    // these keys, so coalesce to keep a literal "undefined" out of the rendered titles/images.
    copy: {
      paymentMethodsListText: params.payment_methods_list_text,
      savedCardsTitle: (_params$saved_cards_t = params.saved_cards_title) !== null && _params$saved_cards_t !== void 0 ? _params$saved_cards_t : '',
      savedCardTitle: (_params$saved_card_ti = params.saved_card_title) !== null && _params$saved_card_ti !== void 0 ? _params$saved_card_ti : '',
      mpMethodsTitle: (_params$mp_methods_ti = params.mp_methods_title) !== null && _params$mp_methods_ti !== void 0 ? _params$mp_methods_ti : '',
      savedPaymentMethodTitle: (_params$saved_payment = params.saved_payment_method_title) !== null && _params$saved_payment !== void 0 ? _params$saved_payment : '',
      accountMoneyBalanceText: (_params$account_money = params.account_money_balance_text) !== null && _params$account_money !== void 0 ? _params$account_money : '',
      mercadoPagoCardName: params.mercado_pago_card_name,
      mercadoPagoCreditCardName: (_params$mercado_pago_ = params.mercado_pago_credit_card_name) !== null && _params$mercado_pago_ !== void 0 ? _params$mercado_pago_ : '',
      lastDigitsText: params.last_digits_text,
      interestFreePartOneText: params.interest_free_part_one_text,
      interestFreePartTwoText: params.interest_free_part_two_text,
      accountMoneyText: params.account_money_text,
      accountMoneyWalletWithInvestmentText: params.account_money_wallet_with_investment_text,
      accountMoneyWalletText: params.account_money_wallet_text,
      accountMoneyInvestmentText: params.account_money_investment_text,
      accountMoneyAvailableText: params.account_money_available_text,
      installmentsInputTitle: params.input_title.installments,
      installmentsRequiredMessage: params.input_helper_message.installments.required,
      securityCodeInputTitle: params.security_code_input_title_text,
      securityCodeTooltip3Digits: params.security_code_tooltip_text_3_digits,
      securityCodeTooltip4Digits: params.security_code_tooltip_text_4_digits,
      installmentsPlaceholder: params.placeholders.installments,
      consumerCreditsDueDateText: params.consumer_credits_due_date,
      consumerCreditsDebitAutoText: params.mlb_installment_debit_auto_text
    },
    thumbnails: {
      newMpLogoPath: params.new_mp_logo_path,
      mpLogoBluePath: (_params$mp_logo_blue_ = params.mp_logo_blue_path) !== null && _params$mp_logo_blue_ !== void 0 ? _params$mp_logo_blue_ : '',
      mpLogoDarkPath: (_params$mp_logo_dark_ = params.mp_logo_dark_path) !== null && _params$mp_logo_dark_ !== void 0 ? _params$mp_logo_dark_ : '',
      whiteCardPath: params.white_card_path,
      yellowWalletPath: params.yellow_wallet_path,
      yellowMoneyPath: params.yellow_money_path,
      paymentMethodsThumbnails: params.payment_methods_thumbnails
    },
    emailListener,
    currentUserEmail: params.current_user_email,
    monthsAbbreviated: params.months_abbreviated
  };
}
;// ./assets/js/checkouts/super-token/adapters/view/index.ts


;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenPaymentMethods.ts
/**
 * Ported `MPSuperTokenPaymentMethods` (v2.1/entities/super-token-payment-methods.js) — the
 * published `window.mpSuperTokenPaymentMethods` instance that owns the saved-methods *state* (the
 * fetched methods, the fast payment token, the active/last/preloaded selections, the CVV field
 * handle, the per-error retry counters, the ESC selection generation and the once-guards) and the
 * *primitives* the checkout uses to render, select, verify and reset those methods in the DOM.
 *
 * Only its saved-method selection orchestration delegates: `onSelectSuperTokenPaymentMethod` →
 * `SelectSavedPaymentMethod`, driven through `LegacySelectionSession` with `this` as the primitive
 * source (the same pattern as the ported trigger handler/authenticator). The legacy seam check
 * (`typeof window.mpSuperTokenSelectPaymentMethod === 'function'`) and its inline fallback collapse
 * away: the entity *is* the implementation, so it calls the use case (and its own methods) directly.
 *
 * The render *orchestration* delegates too: `onCustomCheckoutWasRendered` builds the DOM shell
 * (wallet/flags hidden, area converted, horizontal row + privacy footer, new-card accordion,
 * focus, animation) and hands the saved-methods list to the injected `renderSavedMethods` view —
 * the same collapse of the legacy `window.mpSuperTokenRenderSavedMethods` seam. The legacy row/
 * block builders (`createPaymentMethodElement`, the detail/installment/security-code HTML builders
 * and their row helpers, plus `organize/reorder/normalize/group` and the block renderers) are
 * *not* ported: the view owns them (`adapters/view/**`, `buildPaymentMethodRow`), so keeping a copy
 * here would be dead code once the seam fallback is gone.
 *
 * This completes the port (slices 6a state/selection/primitives, 6b submit validation/restore,
 * 6c render orchestration/shell). Inert until the flip.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity
 * with the legacy class. At the flip the bundle bootstrap constructs it with the ported TS
 * SDK/metrics/e-mail-listener collaborators and the localized bundle params, then publishes it
 * through `globalBridge.publish`.
 */




/**
 * The refactored saved-methods view, injected at construction. Replaces the legacy
 * `window.mpSuperTokenRenderSavedMethods` seam (and its inline `organize` + e-mail-listener
 * fallback): the entity holds the view directly and calls it, so the seam check collapses away.
 * At the flip the composition root wires `createVariantView` as this port.
 */

/** The subset of the e-mail listener the controller reads (block header + change listener). */

/** The subset of the metrics adapter the 6a subset emits through. */

/** Localized `wc_mercadopago_supertoken_bundle_params` the controller reads at construction. */

/** jQuery's static shape the captcha pre-validation spy patches — narrowed from `window.jQuery`. */

class SuperTokenPaymentMethods {
  SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
  CUSTOM_CHECKOUT_TYPE = 'custom';
  COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];
  CUSTOM_BLOCK_ORIGINAL_ID = 'radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
  CUSTOM_CHECKOUT_BLOCKS_SELECTOR = '#radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
  CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_box.payment_method_woo-mercado-pago-custom';
  CARD_FLAGS_SELECTOR = '.mp-checkout-custom-card-flags';
  CHECKOUT_CUSTOM_CONTAINER_SELECTOR = '.mp-checkout-custom-container';
  NEW_CHECKOUT_CONTAINER_SELECTOR = '#mp-checkout-custom-root';
  OLD_CHECKOUT_CONTAINER_SELECTOR = '#mp-checkout-custom-container';
  CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
  COLOMBIA_ACCRONYM = 'MCO';
  MEXICO_ACCRONYM = 'MLM';
  BRAZIL_ACCRONYM = 'MLB';
  CHECKOUT_CUSTOM_LOAD_SELECTOR = '.mp-checkout-custom-load';
  SELECTED_SUPERTOKEN_METHOD_EVENT = 'mp_super_token_payment_method_selected';
  WALLET_BUTTON_SELECTOR = '.mp-wallet-button-container-wrapper';
  CARD_HOLDER_NAME_HELPER_INFO_SELECTOR = '#mp-card-holder-name-helper-info';
  SUPER_TOKEN_STYLES = {
    ROOT_ID: 'mp-checkout-super-token-root',
    ACCORDION: 'mp-super-token-payment-method__accordion',
    ACCORDION_HEADER: 'mp-super-token-payment-method__accordion-header',
    ACCORDION_TITLE: 'mp-super-token-payment-method__accordion-title',
    ACCORDION_CONTENT: 'mp-super-token-payment-method__accordion-content',
    THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
    PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list',
    PAYMENT_METHOD: 'mp-super-token-payment-method',
    PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
    PAYMENT_METHOD_CONTENT_TITLE: 'mp-super-token-payment-method__content-title',
    PAYMENT_METHOD_TITLE: 'mp-super-token-payment-method__title',
    PAYMENT_METHOD_DESCRIPTION: 'mp-super-token-payment-method__description',
    PAYMENT_METHOD_LAST_FOUR_DIGITS: 'mp-super-token-payment-method__last-four-digits',
    PAYMENT_METHOD_SECURITY_CODE_FIELDS: 'mp-super-token-payment-method__security-code-fields',
    PAYMENT_METHOD_SECURITY_CODE: 'mp-super-token-payment-method__security-code',
    PAYMENT_METHOD_EXPIRATION_DATE: 'mp-super-token-payment-method__expiration-date',
    PAYMENT_METHOD_SELECTED: 'mp-super-token-payment-method__selected',
    PAYMENT_METHOD_ACCORDION: 'mp-super-token-payment-method__accordion',
    PAYMENT_METHOD_THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
    PAYMENT_METHOD_ACCORDION_CONTENT_OPEN: 'mp-super-token-payment-method__accordion-content--open',
    PAYMENT_METHOD_VALUE_PROP: 'mp-super-token-payment-method__value-prop',
    PAYMENT_METHOD_DETAILS: 'mp-super-token-payment-method__details',
    PAYMENT_METHOD_HEADER: 'mp-super-token-payment-method__header',
    PAYMENT_METHOD_HIDE: 'mp-super-token-hide',
    REMOVE_BOX_SHADOW: 'mp-box-shadow-none',
    MERCADO_PAGO_PRIVACY_POLICY_FOOTER: 'mp-privacy-policy-footer',
    PAYMENT_METHODS_LIST_HEADER: 'mp-payment-methods-header',
    PAYMENT_METHODS_LIST_HORIZONTAL_ROW: 'mp-payment-methods-list-horizontal-row',
    PAYMENT_METHODS_LIST_HEADER_LOGO: 'mp-payment-methods-header-logo',
    ANIMATION_CLASS: 'mp-initial-state',
    BLOCK: 'mp-super-token-block',
    BLOCK_SAVED_CARDS: 'mp-super-token-block--saved-cards',
    BLOCK_OTHER_MP_METHODS: 'mp-super-token-block--other-mp',
    BLOCK_HEADER: 'mp-super-token-block__header',
    BLOCK_HEADER_INFO: 'mp-super-token-block__header-info',
    BLOCK_TITLE: 'mp-super-token-block__title',
    BLOCK_EMAIL: 'mp-super-token-block__email',
    BLOCK_HEADER_LOGO: 'mp-super-token-block__header-logo',
    ACCOUNT_MONEY_ROW: 'mp-super-token-account-money-row',
    ACCOUNT_MONEY_ROW_OPEN: 'mp-super-token-account-money-row--open',
    ACCOUNT_MONEY_BALANCE_LINE: 'mp-super-token-am-balance-text',
    ACCOUNT_MONEY_BALANCE_LINE_OPEN: 'mp-super-token-am-balance-text--open'
  };

  // Localized params (assigned from the injected `params` in the constructor).

  // We use the update_security_code_with_retry_error_text because it's the same message for the generic error

  NEW_CARD_TYPE = 'new_card';
  CREDIT_CARD_TYPE = 'credit_card';
  DEBIT_CARD_TYPE = 'debit_card';
  ACCOUNT_MONEY_TYPE = 'account_money';
  PREPAID_CARD_TYPE = 'prepaid_card';
  CONSUMER_CREDITS_TYPE = 'digital_currency';
  MERCADO_PAGO_ISSUER_NAME = 'mercado pago';
  PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
  PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';
  MAX_ATTEMPTS_BY_ERROR_CODE = 3;
  GET_PAYMENT_METHOD_TIMEOUT_MS = 5000;

  // Attributes
  paymentMethods = [];
  superToken = null;
  securityFieldsActiveInstance = null;
  activePaymentMethod = null;
  amount = null;
  selectedPreloadedPaymentMethod = null; // Should not be resetted
  securityCodeReferences = {};
  lastPaymentMethodChoosen = null; // Should not be resetted
  attemptsByErrorCode = {};
  isRendering = false;
  escSelectionGeneration = 0;
  securityFieldDispatcherMissingReported = false;
  installmentsDispatcherMissingReported = false;
  creditsInstallmentsDispatcherMissingReported = false;

  // Dependencies
  emailHeaderListenerRegistered = false;
  selectUseCase = new SelectSavedPaymentMethod();
  constructor(mpSdkInstance, mpSuperTokenMetrics, params, renderSavedMethods, wcEmailListener = null, variantView = null) {
    this.mpSdkInstance = mpSdkInstance;
    this.mpSuperTokenMetrics = mpSuperTokenMetrics;
    this.renderSavedMethods = renderSavedMethods;
    this.wcEmailListener = wcEmailListener;
    this.variantView = variantView;
    this.YELLOW_WALLET_PATH = params.yellow_wallet_path;
    this.YELLOW_MONEY_PATH = params.yellow_money_path;
    this.WHITE_CARD_PATH = params.white_card_path;
    this.PAYMENT_METHODS_LIST_TEXT = params.payment_methods_list_text;
    this.PAYMENT_METHODS_LIST_ALT_TEXT = params.payment_methods_list_alt_text;
    this.LAST_DIGITS_TEXT = params.last_digits_text;
    this.NEW_CARD_TEXT = params.new_card_text;
    this.ACCOUNT_MONEY_TEXT = params.account_money_text;
    this.ACCOUNT_MONEY_WALLET_WITH_INVESTMENT_TEXT = params.account_money_wallet_with_investment_text;
    this.ACCOUNT_MONEY_WALLET_TEXT = params.account_money_wallet_text;
    this.ACCOUNT_MONEY_INVESTMENT_TEXT = params.account_money_investment_text;
    this.ACCOUNT_MONEY_AVAILABLE_TEXT = params.account_money_available_text;
    this.INTEREST_FREE_PART_ONE_TEXT = params.interest_free_part_one_text;
    this.INTEREST_FREE_PART_TWO_TEXT = params.interest_free_part_two_text;
    this.BANK_INTEREST_HINT_TEXT = params.input_helper_message.installments.bank_interest_hint_text;
    this.INSTALLMENTS_INPUT_TITLE = params.input_title.installments;
    this.INSTALLMENTS_PLACEHOLDER = params.placeholders.installments;
    this.INSTALLMENTS_REQUIRED_MESSAGE = params.input_helper_message.installments.required;
    this.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT = params.input_helper_message.installments.interest_free_option_text;
    this.SECURITY_CODE_INPUT_TITLE_TEXT = params.security_code_input_title_text;
    this.SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS = params.security_code_placeholder_text_3_digits;
    this.SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS = params.security_code_placeholder_text_4_digits;
    this.SECURITY_CODE_ERROR_MESSAGES = params.input_helper_message.securityCode;
    this.SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS = params.security_code_tooltip_text_3_digits;
    this.SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS = params.security_code_tooltip_text_4_digits;
    this.SITE_ID = params.site_id;
    this.CURRENCY = params.currency;
    this.INTL = params.intl;
    this.MERCADO_PAGO_CARD_NAME = params.mercado_pago_card_name;
    this.MERCADO_PAGO_CREDIT_CARD_NAME = params.mercado_pago_credit_card_name;
    this.CONSUMER_CREDITS_DUE_DATE = params.consumer_credits_due_date;
    this.MLB_INSTALLMENT_DEBIT_AUTO_TEXT = params.mlb_installment_debit_auto_text;
    this.INTEREST_RATE_MLB_TEXT = params.interest_rate_mlb_text;
    this.EFFECTIVE_TOTAL_COST_MLB_TEXT = params.effective_total_cost_mlb_text;
    this.IOF_MLB_TEXT = params.iof_mlb_text;
    this.BORROWED_AMOUNT_MLB_TEXT = params.borrowed_amount_mlb_text;
    this.PER_MONTH = params.per_month;
    this.PER_YEAR = params.per_year;
    this.CAT_MLM_TEXT = params.cat_mlm_text;
    this.NO_IVA_TEXT = params.no_iva_text;
    this.TNA_MLM_TEXT = params.tna_mlm_text;
    this.SYSTEM_AMORTIZATION_MLM_TEXT = params.system_amortization_mlm_text;
    this.CFTEA_MLA_TEXT = params.cftea_mla_text;
    this.TNA_MLA_TEXT = params.tna_mla_text;
    this.TEA_MLA_TEXT = params.tea_mla_text;
    this.FIXED_RATE_TEXT = params.fixed_rate_text;
    this.MERCADO_PAGO_PRIVACY_POLICY = params.mercadopago_privacy_policy;
    this.NEW_MP_LOGO_PATH = params.new_mp_logo_path;
    this.MP_LOGO_BLUE_PATH = params.mp_logo_blue_path;
    this.MP_LOGO_DARK_PATH = params.mp_logo_dark_path;
    this.SAVED_CARDS_TITLE = params.saved_cards_title;
    this.SAVED_CARD_TITLE = params.saved_card_title;
    this.MP_METHODS_TITLE = params.mp_methods_title;
    this.ACCOUNT_MONEY_BALANCE_TEXT = params.account_money_balance_text;
    this.SAVED_PAYMENT_METHOD_TITLE = params.saved_payment_method_title;
    this.CURRENT_USER_EMAIL = params.current_user_email;
    this.PAYMENT_METHODS_THUMBNAILS = params.payment_methods_thumbnails;
    this.PAYMENT_METHODS_ORDER = params.payment_methods_order;
    this.UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT = params.update_security_code_with_retry_error_text;
    this.UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT = params.update_security_code_no_retry_error_text;
    this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT = params.authorize_payment_method_with_retry_error_text;
    this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT = params.authorize_payment_method_no_retry_error_text;
    this.SELECT_PAYMENT_METHOD_ERROR_TEXT = params.select_payment_method_error_text;
    this.SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT = params.update_security_code_with_retry_error_text;
  }
  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  reset() {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    this.isRendering = false;
    this.paymentMethods = [];
    this.attemptsByErrorCode = {};
    this.securityCodeReferences = {};
    this.activePaymentMethod = null;
    this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);
    this.unmountActiveSecurityCodeInstance();
    this.hideAllPaymentMethodDetails();
    this.restoreCustomCheckoutEntireElementOriginalId();
    this.showWalletButton();
    this.showCardFlags();
    this.removePaymentMethodElements();
    this.removeAccordion();
    this.deselectAllPaymentMethods();
    this.removeMercadoPagoPrivacyPolicyFooter();
    this.removeHorizontalRow();
    this.removePaymentMethodsListClasses();
    if (customCheckoutEntireElement) {
      this.variantView?.reset(customCheckoutEntireElement);
    }
  }
  storePaymentMethodsInMemory(accountPaymentMethods) {
    this.paymentMethods = accountPaymentMethods;
  }
  getStoredPaymentMethods() {
    return this.paymentMethods;
  }
  hasStoredPaymentMethods() {
    return this.paymentMethods.length > 0;
  }
  storeSelectedPreloadedPaymentMethod(paymentMethod) {
    this.selectedPreloadedPaymentMethod = paymentMethod;
  }
  getSelectedPreloadedPaymentMethod() {
    return this.selectedPreloadedPaymentMethod;
  }
  getSelectedPreloadedPaymentMethodFromActivePaymentMethods() {
    return this.paymentMethods.find(paymentMethod => this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier(this.selectedPreloadedPaymentMethod));
  }
  paymentMethodIdentifier(paymentMethod) {
    if (!paymentMethod) return '';
    return `${paymentMethod?.id}${('card' in paymentMethod ? paymentMethod.card?.card_number?.last_four_digits : undefined) || ''}`;
  }
  setSuperToken(token) {
    this.superToken = token;
  }
  getSuperToken() {
    return this.superToken;
  }
  paymentMethodsAreRendered() {
    return !!document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`);
  }
  getAttemptByErrorCode(errorCode) {
    return Math.min(this.attemptsByErrorCode[errorCode] || 0, this.MAX_ATTEMPTS_BY_ERROR_CODE);
  }
  shouldAllowRetry(attempt) {
    return attempt < this.MAX_ATTEMPTS_BY_ERROR_CODE;
  }
  storeAttemptByErrorCode(errorCode) {
    this.attemptsByErrorCode[errorCode] = (this.attemptsByErrorCode[errorCode] || 0) + 1;
  }
  convertErrorCodeToErrorMessage(errorCode) {
    this.storeAttemptByErrorCode(errorCode);
    const errorMessages = {
      UPDATE_SECURITY_CODE_ERROR: {
        withRetry: this.UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT
      },
      AUTHORIZE_PAYMENT_METHOD_ERROR: {
        withRetry: this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT
      },
      AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: {
        withRetry: this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT,
        withoutRetry: this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT
      },
      SELECT_PAYMENT_METHOD_ERROR: {
        withRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT,
        withoutRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT
      }
    };
    const errorConfig = Object.entries(errorMessages).find(([key]) => errorCode.includes(key))?.[1] || null;
    if (!errorConfig) {
      return this.SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT;
    }
    const allowRetry = this.shouldAllowRetry(this.getAttemptByErrorCode(errorCode));
    if (!allowRetry) {
      this.mpSuperTokenMetrics.sendMetric('super_token_retry_limit_reached', errorCode, '');
    }
    return allowRetry ? errorConfig.withRetry : errorConfig.withoutRetry;
  }
  showSuperTokenError(errorMessage) {
    const paymentMethodList = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
    if (!paymentMethodList) return;
    const andesNotice = document.createElement('andes-notice');
    andesNotice.id = 'mp-fast-payments-error';
    andesNotice.setAttribute('type', 'warning');
    andesNotice.setAttribute('description', errorMessage);
    paymentMethodList.insertBefore(andesNotice, paymentMethodList.firstChild);
    andesNotice.scrollIntoView({
      behavior: 'smooth'
    });
  }
  hideSuperTokenError() {
    this.excludeRecaptchaFromPreValidation();
    const andesNotice = document.getElementById('mp-fast-payments-error');
    if (!andesNotice) return;
    andesNotice.remove();
  }

  /**
   * Captcha tokens (reCAPTCHA g-recaptcha-response, hCaptcha h-captcha-response, Cloudflare
   * Turnstile cf-turnstile-response) are single-use. The Classic pre-validation request
   * (mp_validate_checkout) serializes form.checkout via jQuery .serialize() and, server-side,
   * re-runs woocommerce_checkout_process — the captcha plugin consumes the token there, so the real
   * submit then fails the captcha and blocks the buyer. Install a one-time spy on jQuery's global
   * .serialize() that omits the checkout form's captcha field from every serialize EXCEPT the real
   * submit: it disables the field just for that one call (disable → serialize → re-enable in
   * finally, so the live field is never left disabled) whenever mercado_pago_submit is false. The
   * real submit serializes with mercado_pago_submit true, so it keeps the token. The flag is read at
   * serialize time (not install time), so no sticky disabled state leaks into the real submit. Acts
   * only when form.checkout itself is serialized (captchas on other forms/widgets on the page are
   * ignored); runs only on the standard Classic checkout (absent on Blocks and order-pay). Each disable/enable emits a
   * success metric (action + field name); failures emit an error metric. Best-effort: never throws.
   */
  excludeRecaptchaFromPreValidation() {
    const metrics = this.mpSuperTokenMetrics;
    const CAPTCHA_SELECTOR = '[name^="g-recaptcha-response"], [name^="h-captcha-response"], [name^="cf-turnstile-response"]';
    try {
      // form.checkout exists only on the standard Classic checkout — absent on Blocks and on
      // the order-pay page (form#order_review), neither of which runs this pre-validation.
      const checkoutForm = document.querySelector('form.checkout');
      if (!checkoutForm) {
        return;
      }

      // Only the checkout form's own captcha matters — ignore any captcha elsewhere on the page.
      if (!checkoutForm.querySelector(CAPTCHA_SELECTOR)) {
        return;
      }
      const jq = window.jQuery;
      if (!jq?.fn || typeof jq.fn.serialize !== 'function') {
        metrics?.errorToExcludeRecaptchaFromPreValidation('serialize_unavailable', 'jQuery.fn.serialize is not available');
        return;
      }

      // Install the spy once, capturing the original first (avoids self-recursion).
      if (jq.fn.serialize.__mpRecaptchaSpy) {
        return;
      }
      const originalSerialize = jq.fn.serialize;
      const patchedSerialize = function (...args) {
        // Act only when the checkout form itself is being serialized (pre-validation / real
        // submit) — never on serializes of other forms on the page. `this` is the jQuery
        // collection .serialize() was called on. On the real submit (mercado_pago_submit ===
        // true) keep the token; otherwise omit it, scoping the disable to this call (finally).
        const serializedForm = this ? this[0] : undefined;
        const isCheckoutForm = !!serializedForm && typeof serializedForm.matches === 'function' && serializedForm.matches('form.checkout');
        const captchaFields = serializedForm && isCheckoutForm && !window.mpEventHandler?.mercado_pago_submit ? Array.from(serializedForm.querySelectorAll(CAPTCHA_SELECTOR)).filter(field => !field.disabled) : [];
        if (!captchaFields.length) {
          return originalSerialize.apply(this, args);
        }
        captchaFields.forEach(field => {
          field.disabled = true;
          metrics?.captchaFieldToggledOnPreValidation('disabled', field.name);
        });
        try {
          return originalSerialize.apply(this, args);
        } finally {
          captchaFields.forEach(field => {
            field.disabled = false;
            metrics?.captchaFieldToggledOnPreValidation('enabled', field.name);
          });
        }
      };
      patchedSerialize.__mpRecaptchaSpy = true;
      jq.fn.serialize = patchedSerialize;
    } catch (error) {
      metrics?.errorToExcludeRecaptchaFromPreValidation('setup', error);
    }
  }
  getCustomCheckoutEntireElement() {
    return document.querySelector(`#${this.SUPER_TOKEN_STYLES.ROOT_ID}`) || document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_SELECTOR) || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_SELECTOR);
  }
  getWalletButtonElement() {
    return document.querySelector(this.WALLET_BUTTON_SELECTOR);
  }
  getCardFlagsElement() {
    return document.querySelector(this.CARD_FLAGS_SELECTOR);
  }
  hideWalletButton() {
    const walletButtonElement = this.getWalletButtonElement();
    if (!walletButtonElement) return;
    walletButtonElement.style.display = 'none';
  }
  showWalletButton() {
    const walletButtonElement = this.getWalletButtonElement();
    if (!walletButtonElement) return;
    walletButtonElement.style.display = 'flex';
  }
  hideCardFlags() {
    const cardFlagsElement = this.getCardFlagsElement();
    if (!cardFlagsElement) return;
    cardFlagsElement.style.display = 'none';
  }
  showCardFlags() {
    const cardFlagsElement = this.getCardFlagsElement();
    if (!cardFlagsElement) return;
    cardFlagsElement.style.display = 'flex';
  }
  removeAccordion() {
    const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);
    const accordionHeader = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);
    accordionElement?.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)?.classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
    accordionElement?.classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION);
    accordionHeader?.remove();
  }
  removePaymentMethodsListClasses() {
    var _customCheckoutEntire;
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    const checkoutContainer = (_customCheckoutEntire = customCheckoutEntireElement?.querySelector(this.NEW_CHECKOUT_CONTAINER_SELECTOR)) !== null && _customCheckoutEntire !== void 0 ? _customCheckoutEntire : customCheckoutEntireElement?.querySelector(this.OLD_CHECKOUT_CONTAINER_SELECTOR);
    if (checkoutContainer) {
      checkoutContainer.style.height = 'auto';
    }
    customCheckoutEntireElement?.parentElement?.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
    customCheckoutEntireElement?.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    customCheckoutEntireElement?.parentElement?.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
    customCheckoutEntireElement?.removeAttribute('role');
    customCheckoutEntireElement?.removeAttribute('aria-label');
    customCheckoutEntireElement?.removeAttribute('tabindex');
  }
  removePaymentMethodElements() {
    document.querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`).forEach(element => element.remove());
  }
  closeAccordion() {
    const accordionContent = document.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR);
    const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);
    if (accordionContent) {
      accordionContent.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);
    }
    if (accordionElement) {
      accordionElement.style.height = '48px';
    }
  }
  deselectAllPaymentMethods() {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    document.querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`).forEach(element => {
      element.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
      element.setAttribute('aria-selected', 'false');
    });
    if (customCheckoutEntireElement) {
      this.variantView?.clearSelectionDecoration(customCheckoutEntireElement);
    }
  }
  selectNewCardAccordion() {
    const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION}`);
    const accordionContent = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT}`);
    const accordionHeader = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);
    if (!accordionElement || !accordionContent || !accordionHeader) {
      window.console?.warn?.('Accordion elements not found');
      return;
    }
    accordionElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
    accordionElement.style.height = '48px';
    accordionHeader.setAttribute('aria-selected', 'true');
    setTimeout(() => {
      accordionContent.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);
      requestAnimationFrame(() => {
        accordionElement.style.height = 'auto';
        accordionElement.style.overflow = 'visible';
      });
    }, 10);
  }
  selectPaymentMethod(paymentMethodElement) {
    paymentMethodElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
    paymentMethodElement.setAttribute('aria-selected', 'true');
    this.variantView?.decorateSelection(paymentMethodElement);
  }
  getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods) {
    const paymentMethodSelected = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`) || null;
    if (!paymentMethodSelected) return null;
    return accountPaymentMethods.find(paymentMethod => this.paymentMethodIdentifier(paymentMethod) === paymentMethodSelected.id);
  }
  getPaymentMethodElementFromDOM(paymentMethod) {
    return document.getElementById(this.paymentMethodIdentifier(paymentMethod));
  }
  setCheckoutType(type) {
    const element = document.querySelector(this.CHECKOUT_TYPE_SELECTOR);
    if (!element) return;
    element.value = type;
  }
  setPaymentMethodChildrenAriaVisible(paymentMethodElement) {
    const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
    if (securityCodeContainer) {
      const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
      const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
      const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');
      securityCodeLabel?.setAttribute('aria-hidden', 'false');
      securityCodeLabel?.setAttribute('tabindex', '0');
      securityCodeInput?.setAttribute('aria-hidden', 'false');
      securityCodeInput?.setAttribute('tabindex', '0');
      securityCodeTooltip?.setAttribute('aria-hidden', 'false');
      securityCodeTooltip?.setAttribute('tabindex', '0');
    }
    const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement)}`);
    if (!installmentsDropdown) return;
    installmentsDropdown.setAttribute('aria-hidden', 'false');
    installmentsDropdown.setAttribute('tabindex', '0');
  }
  setPaymentMethodChildrenAriaHidden(paymentMethodElement) {
    const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
    if (securityCodeContainer) {
      const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
      const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
      const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');
      securityCodeLabel?.setAttribute('aria-hidden', 'true');
      securityCodeLabel?.setAttribute('tabindex', '-1');
      securityCodeInput?.setAttribute('aria-hidden', 'true');
      securityCodeInput?.setAttribute('tabindex', '-1');
      securityCodeTooltip?.setAttribute('aria-hidden', 'true');
      securityCodeTooltip?.setAttribute('tabindex', '-1');
    }
    const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement)}`);
    if (!installmentsDropdown) return;
    installmentsDropdown.setAttribute('aria-hidden', 'true');
    installmentsDropdown.setAttribute('tabindex', '-1');
  }
  showPaymentMethodDetails(paymentMethodElement) {
    paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)?.classList?.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);
    this.setPaymentMethodChildrenAriaVisible(paymentMethodElement);
  }
  hideAllPaymentMethodDetails() {
    document.querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)?.forEach(element => {
      element?.classList?.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);
      this.setPaymentMethodChildrenAriaHidden(element);
    });
  }
  fillCardTokenFields(paymentMethod) {
    document.getElementById('paymentMethodId').value = paymentMethod.id;
    document.getElementById('paymentTypeId').value = paymentMethod.type;
    document.getElementById('cardTokenId').value = paymentMethod.token;
  }
  paymentMethodAlreadySelected(paymentMethod) {
    const paymentMethodElement = this.getPaymentMethodElementFromDOM(paymentMethod);
    if (!paymentMethodElement) return false;
    return paymentMethodElement.classList.contains(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
  }
  getActivePaymentMethod() {
    return this.activePaymentMethod;
  }
  storeActivePaymentMethod(paymentMethod) {
    this.activePaymentMethod = paymentMethod;
    this.lastPaymentMethodChoosen = paymentMethod || this.lastPaymentMethodChoosen;
  }
  clearActivePaymentMethod() {
    this.activePaymentMethod = null;
  }
  getLastPaymentMethodChoosen() {
    return this.lastPaymentMethodChoosen;
  }
  hasCheckoutError() {
    return !!document.querySelector('#mp-fast-payments-error');
  }
  formatSelectedPaymentMethodName(paymentMethod) {
    if (this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier({
      id: this.NEW_CARD_TYPE
    })) {
      return 'new_credit_card';
    }
    if (paymentMethod?.type === this.ACCOUNT_MONEY_TYPE) {
      return this.ACCOUNT_MONEY_TYPE;
    }
    const paymentMethodName = `${paymentMethod?.id || 'none'} ${paymentMethod?.type || 'none'}`.toLowerCase();
    const lastFourDigits = 'card' in paymentMethod ? paymentMethod.card?.card_number?.last_four_digits : undefined;
    return paymentMethodName.concat(lastFourDigits ? ` ${lastFourDigits}` : '');
  }
  emitEventFromSelectPaymentMethod(paymentMethod) {
    const formattedPaymentMethodName = this.formatSelectedPaymentMethodName(paymentMethod);
    document.dispatchEvent(new CustomEvent(this.SELECTED_SUPERTOKEN_METHOD_EVENT, {
      detail: {
        payment_method: formattedPaymentMethodName
      }
    }));
  }
  async onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod) {
    await this.selectUseCase.execute({
      session: new LegacySelectionSession(this),
      metrics: this.mpSuperTokenMetrics,
      paymentMethod,
      paymentMethodElement
    });
  }
  async selectPreloadedPaymentMethod() {
    this.closeAccordion();
    const paymentMethod = this.getSelectedPreloadedPaymentMethodFromActivePaymentMethods();
    if (!paymentMethod) {
      this.mpSuperTokenMetrics.sendMetric('super_token_preloaded_method_not_found', 'true', '');
      return;
    }
    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }
    this.storeActivePaymentMethod(paymentMethod);
    await this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
  }
  selectLastPaymentMethodChoosen() {
    this.closeAccordion();
    const paymentMethod = this.getLastPaymentMethodChoosen();
    if (!paymentMethod) return;
    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }
    this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
  }
  onSelectNewCardPaymentMethod() {
    if (this.paymentMethodAlreadySelected({
      id: this.NEW_CARD_TYPE
    })) {
      return;
    }
    this.mpSuperTokenMetrics.sendMetric('super_token_withdraw', 'true', '');
    this.emitEventFromSelectPaymentMethod({
      id: this.NEW_CARD_TYPE
    });
    this.storeActivePaymentMethod({
      id: this.NEW_CARD_TYPE
    });
    this.deselectAllPaymentMethods();
    this.hideAllPaymentMethodDetails();
    this.unmountActiveSecurityCodeInstance();
    this.selectNewCardAccordion();
    this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);
    this.handleInstallmentsWithoutFeePillVisibility();
    setTimeout(() => {
      this.unmountCardForm();
      this.mountCardForm();
      this.showCardHolderNameHelperInfo();
    }, 50);
    setTimeout(() => {
      document.dispatchEvent(this.selectedSupertokenMethodEvent(true));
    }, 50);
  }
  selectedSupertokenMethodEvent = isNewCardSelected => {
    return new CustomEvent('supertoken_payment_method_selected', {
      detail: {
        new_card_selected: isNewCardSelected,
        checkout_type: document.querySelector('#mp_checkout_type')?.value
      }
    });
  };
  isCreditCard(paymentMethod) {
    return paymentMethod?.type === this.CREDIT_CARD_TYPE;
  }
  isDebitCard(paymentMethod) {
    return paymentMethod?.type === this.DEBIT_CARD_TYPE;
  }
  isAccountMoney(paymentMethod) {
    return paymentMethod?.type === this.ACCOUNT_MONEY_TYPE;
  }
  isPrepaidCard(paymentMethod) {
    return paymentMethod?.type === this.PREPAID_CARD_TYPE;
  }
  isMercadoPagoCard(paymentMethod) {
    return paymentMethod?.type === this.PREPAID_CARD_TYPE && 'issuer' in paymentMethod && !!paymentMethod?.issuer?.name?.toLowerCase()?.includes(this.MERCADO_PAGO_ISSUER_NAME);
  }
  isMercadoPagoCreditCard(paymentMethod) {
    return paymentMethod?.type === this.CREDIT_CARD_TYPE && 'issuer' in paymentMethod && !!paymentMethod?.issuer?.name?.toLowerCase()?.includes(this.MERCADO_PAGO_ISSUER_NAME);
  }
  isConsumerCredits(paymentMethod) {
    return paymentMethod?.type === this.CONSUMER_CREDITS_TYPE;
  }
  getMpIconPaths() {
    return {
      blue: this.MP_LOGO_BLUE_PATH,
      dark: this.MP_LOGO_DARK_PATH
    };
  }
  getSiteId() {
    return this.SITE_ID?.toUpperCase();
  }
  securityCodeIsRequired(securityCodeSettings) {
    if (!securityCodeSettings) {
      return false;
    }
    return securityCodeSettings?.mode === 'mandatory';
  }
  shouldFetchPaymentMethodAgain(paymentMethod, paymentMethodElement) {
    if (!paymentMethod || !paymentMethodElement) throw new Error(ErrorClassification_MPSuperTokenErrorCodes.PAYMENT_METHOD_NOT_EXISTS);
    if (paymentMethodElement.hasAttribute('data-cvv-is-required-double-check')) return false;
    return (this.isCreditCard(paymentMethod) || this.isDebitCard(paymentMethod)) && this.securityCodeIsRequired(paymentMethod.security_code_settings) && paymentMethod.has_esc === true;
  }
  getSkipReason(paymentMethod, paymentMethodElement) {
    if (paymentMethodElement && paymentMethodElement.hasAttribute('data-cvv-is-required-double-check')) {
      return 'already_checked';
    }
    if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod)) {
      return 'not_card';
    }
    if (!this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
      return 'security_code_not_required';
    }
    if (paymentMethod?.has_esc !== true) {
      return 'esc_disabled';
    }
    return 'unknown';
  }
  hasMissingEsc(paymentMethod) {
    return (this.isCreditCard(paymentMethod) || this.isDebitCard(paymentMethod)) && this.securityCodeIsRequired(paymentMethod?.security_code_settings) && typeof paymentMethod?.has_esc === 'undefined';
  }
  getPaymentMethodElementByIdentifier(paymentMethod) {
    return document.getElementById(this.paymentMethodIdentifier(paymentMethod));
  }
  timeoutRequest(errorCode, timeoutMs = 5000) {
    return new Promise((_, reject) => setTimeout(() => reject(new Error(errorCode)), timeoutMs));
  }
  parseMsToSeconds(milliseconds) {
    return (milliseconds / 1000).toFixed(2);
  }
  async fetchPaymentMethod(paymentMethod, paymentMethodElement) {
    const currentPaymentMethodIdentifier = this.paymentMethodIdentifier(paymentMethod);
    const REQUEST_START_TIME = Date.now();
    const result = await Promise.race([this.mpSdkInstance.getAccountPaymentMethod(this.getSuperToken(), paymentMethod.token), this.timeoutRequest(ErrorClassification_MPSuperTokenErrorCodes.GET_PAYMENT_METHOD_TIMEOUT_ERROR, this.GET_PAYMENT_METHOD_TIMEOUT_MS)]);
    const updatedPaymentMethod = result?.data;
    if (!updatedPaymentMethod) throw new Error(ErrorClassification_MPSuperTokenErrorCodes.FETCH_PAYMENT_METHOD_NOT_FOUND);
    paymentMethodElement.setAttribute('data-cvv-is-required-double-check', 'true');
    this.mpSuperTokenMetrics.getPaymentMethodLoadingTime(currentPaymentMethodIdentifier, this.parseMsToSeconds(Date.now() - REQUEST_START_TIME));
    return updatedPaymentMethod;
  }
  updatePaymentMethodInList(updatedPaymentMethod) {
    if (!this.paymentMethods) {
      throw new Error(ErrorClassification_MPSuperTokenErrorCodes.UPDATE_PAYMENT_METHOD_WITH_ESC_FAILED_EMPTY_METHODS);
    }
    const updatedPaymentMethodList = this.paymentMethods.map(pm => this.paymentMethodIdentifier(pm) === this.paymentMethodIdentifier(updatedPaymentMethod) ? updatedPaymentMethod : pm);
    this.paymentMethods = updatedPaymentMethodList;
  }
  showDetailsSkeleton(paymentMethodElement) {
    const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
    if (!wrapper) return;
    wrapper.classList.add('mp-super-token-method-details-wrapper--loading');
    const skeleton = document.createElement('div');
    skeleton.classList.add('mp-super-token-method-details-skeleton');
    wrapper.appendChild(skeleton);
  }
  hideDetailsSkeleton(paymentMethodElement) {
    const wrapper = paymentMethodElement.querySelector('.mp-super-token-method-details-wrapper');
    if (!wrapper) return;
    wrapper.classList.remove('mp-super-token-method-details-wrapper--loading');
    const skeleton = wrapper.querySelector('.mp-super-token-method-details-skeleton');
    if (skeleton) skeleton.remove();
  }
  removeSecurityCodeField(paymentMethod) {
    const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
    if (securityCodeContainer) {
      securityCodeContainer.remove();
    }
  }
  async handleWithEscPaymentMethod(paymentMethod, paymentMethodElement) {
    try {
      if (this.shouldFetchPaymentMethodAgain(paymentMethod, paymentMethodElement)) {
        this.showDetailsSkeleton(paymentMethodElement);
        const currentGeneration = ++this.escSelectionGeneration;
        const updatedPaymentMethod = await this.fetchPaymentMethod(paymentMethod, paymentMethodElement);
        if (currentGeneration !== this.escSelectionGeneration) {
          this.hideDetailsSkeleton(paymentMethodElement);
          return null;
        }
        this.updatePaymentMethodInList(updatedPaymentMethod);
        this.storeActivePaymentMethod(updatedPaymentMethod);
        if (!this.securityCodeIsRequired('security_code_settings' in updatedPaymentMethod ? updatedPaymentMethod.security_code_settings : undefined)) {
          this.removeSecurityCodeField(updatedPaymentMethod);
        }
        this.mpSuperTokenMetrics.fetchPaymentMethodSuccess(this.paymentMethodIdentifier(updatedPaymentMethod), 'security_code_settings' in updatedPaymentMethod && updatedPaymentMethod.security_code_settings ? this.securityCodeIsRequired(updatedPaymentMethod.security_code_settings) : null);
        this.hideDetailsSkeleton(paymentMethodElement);
        return updatedPaymentMethod;
      } else if (this.hasMissingEsc(paymentMethod)) {
        this.mpSuperTokenMetrics.hasEscNotExists(this.paymentMethodIdentifier(paymentMethod));
        return paymentMethod;
      } else {
        this.mpSuperTokenMetrics.fetchPaymentMethodSkipped(this.paymentMethodIdentifier(paymentMethod), this.getSkipReason(paymentMethod, paymentMethodElement));
        return paymentMethod;
      }
    } catch (error) {
      this.hideDetailsSkeleton(paymentMethodElement);
      if (error?.message === ErrorClassification_MPSuperTokenErrorCodes.GET_PAYMENT_METHOD_TIMEOUT_ERROR) {
        this.mpSuperTokenMetrics.getPaymentMethodLoadingTime(this.paymentMethodIdentifier(paymentMethod), this.parseMsToSeconds(this.GET_PAYMENT_METHOD_TIMEOUT_MS));
        this.mpSuperTokenMetrics.fetchPaymentMethodTimeout(this.paymentMethodIdentifier(paymentMethod));
      }
      this.mpSuperTokenMetrics.getPaymentMethodFail(error, this.paymentMethodIdentifier(paymentMethod));
      return paymentMethod;
    }
  }
  mountCardForm() {
    if (window.mpCustomCheckoutHandler?.cardForm?.formMounted) {
      return;
    }
    window.mpCustomCheckoutHandler?.cardForm?.initCardForm(this.getAmount());
  }
  unmountCardForm() {
    if (window.mpCustomCheckoutHandler?.cardForm?.formMounted) {
      window.mpCustomCheckoutHandler?.cardForm?.form?.unmount();
    }
  }
  unmountActiveSecurityCodeInstance() {
    if (this.securityFieldsActiveInstance) {
      this.securityFieldsActiveInstance.unmount();
      this.securityFieldsActiveInstance = null;
    }
  }
  storeActiveSecurityCodeInstance(securityCodeInstance) {
    this.securityFieldsActiveInstance = securityCodeInstance;
  }
  storeAmount(amount) {
    this.amount = amount;
  }
  getAmount() {
    return this.amount;
  }
  getCheckoutLoaderElement() {
    return document.querySelector('.mp-checkout-custom-load');
  }
  moveCheckoutLoaderToPaymentMethodsList() {
    const checkoutLoaderElement = this.getCheckoutLoaderElement();
    const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!checkoutLoaderElement || !paymentMethodsListElement) {
      return;
    }
    paymentMethodsListElement.parentElement?.appendChild(checkoutLoaderElement);
  }
  removeCheckoutLoaderFromPaymentMethodsList() {
    const checkoutLoaderElement = this.getCheckoutLoaderElement();
    const checkoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!checkoutLoaderElement || !checkoutEntireElement) {
      return;
    }
    checkoutEntireElement.appendChild(checkoutLoaderElement);
  }
  getPaymentMethodsListElement() {
    return document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
  }
  restoreCustomCheckoutEntireElementOriginalId() {
    const paymentMethodsListElement = this.getPaymentMethodsListElement();
    if (!paymentMethodsListElement) return;
    paymentMethodsListElement.setAttribute('id', this.CUSTOM_BLOCK_ORIGINAL_ID);
  }
  hidePaymentMethodsList() {
    const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!paymentMethodsListElement) {
      return;
    }
    paymentMethodsListElement.style.display = 'none';
  }
  showPaymentMethodsList() {
    const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    if (!paymentMethodsListElement) {
      return;
    }
    paymentMethodsListElement.style.display = 'flex';
  }
  async updateSecurityCode() {
    const paymentMethod = this.activePaymentMethod;
    if (!paymentMethod || !this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
      return;
    }
    try {
      const {
        card_id
      } = await this.mpSdkInstance.getCardId(this.getSuperToken(), paymentMethod.token);
      this.mpSuperTokenMetrics?.updateSecurityCodeGetCardIdSuccess();
      const {
        id
      } = await this.mpSdkInstance.fields.createCardToken({
        cardId: card_id
      });
      this.mpSuperTokenMetrics?.updateSecurityCodeCardTokenCreated();
      await this.mpSdkInstance.updatePseudotoken(this.getSuperToken(), paymentMethod.token, id);
      this.mpSuperTokenMetrics?.updateSecurityCodePseudotokenUpdated();
      this.mpSuperTokenMetrics?.updateSecurityCodeSuccess();
    } catch (error) {
      this.mpSuperTokenMetrics.errorToUpdateSecurityCode(error, paymentMethod);
      throw new Error(ErrorClassification_MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR);
    }
  }
  toggleSecurityCodeErrorMessage(errorMessage, paymentMethod) {
    var _this$SECURITY_CODE_E;
    const securityCodeContainerElement = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
    if (!securityCodeContainerElement) {
      return;
    }
    const securityCodeLabelElement = securityCodeContainerElement.querySelector('label');
    const securityCodeErrorMessageElement = securityCodeContainerElement.querySelector('#mp-super-token-security-code-error-message');
    const helperErrorElement = securityCodeContainerElement.querySelector('#mp-input-with-tooltip-helper-error');
    const securityCodeInputElement = securityCodeContainerElement.querySelector('.mp-super-token-security-code-input');

    // Clean up
    securityCodeLabelElement.classList.remove('error');
    securityCodeContainerElement.classList.remove('error');
    securityCodeInputElement.classList.remove('error');
    helperErrorElement.style.display = 'none';
    securityCodeErrorMessageElement.textContent = '';
    if (!errorMessage) {
      return;
    }

    // Set error
    securityCodeLabelElement.classList.add('error');
    securityCodeContainerElement.classList.add('error');
    securityCodeInputElement.classList.add('error');
    const displayMessage = (_this$SECURITY_CODE_E = this.SECURITY_CODE_ERROR_MESSAGES[errorMessage]) !== null && _this$SECURITY_CODE_E !== void 0 ? _this$SECURITY_CODE_E : errorMessage;
    securityCodeErrorMessageElement.textContent = displayMessage;
    helperErrorElement.setAttribute('aria-label', displayMessage);
    helperErrorElement.style.display = 'flex';
  }
  verifyIsSecurityCodeReferenceTrue(paymentMethod) {
    return this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] === true;
  }
  setSecurityCodeReferenceFalse(paymentMethod) {
    this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = false;
  }
  setSecurityCodeReferenceTrue(paymentMethod) {
    this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = true;
  }
  mountSecurityCodeField(paymentMethod) {
    if (!this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
      return;
    }
    this.unmountCardForm();
    this.unmountActiveSecurityCodeInstance();
    this.setSecurityCodeReferenceFalse(paymentMethod);
    const waitSecurityCodeFieldMountInterval = setInterval(() => {
      if (document.getElementById(`mp-super-token-security-code-input-${paymentMethod.token}`)) {
        clearInterval(waitSecurityCodeFieldMountInterval);
        const securityCodePlaceholderText = ('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)?.length === 3 ? this.SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS : this.SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS;
        if (!window.MPCheckoutFieldsDispatcher && typeof window.sendMetric === 'function' && !this.securityFieldDispatcherMissingReported) {
          window.sendMetric('MP_CHECKOUT_FIELDS_DISPATCHER_MISSING', 'super_token_cvv_mount', 'mp_super_token_init_error');
          this.securityFieldDispatcherMissingReported = true;
        }
        const securityCodeField = this.mpSdkInstance.fields.create('securityCode', {
          placeholder: securityCodePlaceholderText,
          ariaRequired: true,
          style: {
            'font-size': '16px',
            height: '48px',
            padding: '12px',
            fontFamily: 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif'
          }
        }).mount(`mp-super-token-security-code-input-${paymentMethod.token}`).on('error', e => this.mpSuperTokenMetrics.errorToMountCVVField(e, paymentMethod)).on('ready', () => {
          securityCodeField.update({
            settings: 'security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined
          });
          this.mpSuperTokenMetrics.sendMetric('super_token_cvv_field_ready', 'true', '');
          this.storeActiveSecurityCodeInstance(securityCodeField);
          if (this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
            const securityCodeTooltip = document.querySelector(`#mp-super-token-security-code-container-${paymentMethod.token} .mp-super-token-security-code-tooltip`);
            const securityCodeInput = document.querySelector(`#mp-super-token-security-code-input-${paymentMethod.token}`);
            if (!securityCodeTooltip || !securityCodeInput) return;
            const securityCodeTooltipClone = securityCodeTooltip.cloneNode(true);
            securityCodeTooltipClone.style.display = 'flex';
            securityCodeInput.appendChild(securityCodeTooltipClone);
          }
        }).on('validityChange', e => {
          var _e$errorMessages$0$ca;
          this.setSecurityCodeReferenceTrue(paymentMethod);
          if (e.errorMessages.length === 0) {
            if (window.MPCheckoutFieldsDispatcher) {
              window.MPCheckoutFieldsDispatcher.addEventListenerDispatcher(null, 'focusout', 'super_token_cvv_filled', {
                onlyDispatch: true
              });
            }
            this.mpSuperTokenMetrics.sendMetric('super_token_cvv_filled', 'true', '');
            this.toggleSecurityCodeErrorMessage('', paymentMethod);
          } else {
            this.setSecurityCodeReferenceFalse(paymentMethod);
          }
          const errorMessage = (_e$errorMessages$0$ca = e.errorMessages[0]?.cause) !== null && _e$errorMessages$0$ca !== void 0 ? _e$errorMessages$0$ca : '';
          this.toggleSecurityCodeErrorMessage(errorMessage, paymentMethod);
        });
      }
    }, 200);
  }
  handleInstallmentsWithoutFeePillVisibility() {
    const allPaymentMethods = document.querySelectorAll('.mp-super-token-payment-method');
    allPaymentMethods.forEach(paymentMethodElement => {
      const valuePropPill = paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_VALUE_PROP}`);
      if (!valuePropPill) {
        return;
      }
      valuePropPill.style.display = 'flex';
    });
  }
  showCardHolderNameHelperInfo() {
    const cardHolderNameHelperInfo = document.querySelector(this.CARD_HOLDER_NAME_HELPER_INFO_SELECTOR);
    if (cardHolderNameHelperInfo) {
      cardHolderNameHelperInfo.style.display = 'flex';
    }
  }
  removeMercadoPagoPrivacyPolicyFooter() {
    var _this$getCustomChecko;
    const footer = (_this$getCustomChecko = this.getCustomCheckoutEntireElement()?.querySelector('#mp-super-token-privacy-policy-footer')) !== null && _this$getCustomChecko !== void 0 ? _this$getCustomChecko : null;
    if (!footer) {
      return;
    }
    footer.remove();
  }
  removeHorizontalRow() {
    const horizontalRow = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW}`);
    if (!horizontalRow) {
      return;
    }
    horizontalRow.remove();
  }
  installmentsWasSelected(paymentMethod) {
    const installmentsSelect = document.getElementById(`mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`);
    return !!installmentsSelect?.value;
  }
  setInstallmentsErrorState(paymentMethod, hasError) {
    const paymentMethodIdentifier = this.paymentMethodIdentifier(paymentMethod);
    const installmentsSelect = document.getElementById(`mp-super-token-installments-select-${paymentMethodIdentifier}`);
    const installmentsLabel = document.querySelector(`label[for="mp-super-token-installments-select-${paymentMethodIdentifier}"]`);
    const installmentsErrorHelper = document.querySelector(`#mp-super-token-installments-error-${paymentMethodIdentifier}`);
    if (!installmentsSelect || !installmentsLabel || !installmentsErrorHelper) return;
    if (hasError) {
      installmentsErrorHelper.style.display = 'flex';
      installmentsSelect.classList.add('mp-super-token-error');
      installmentsLabel.classList.add('mp-super-token-label-error');
    } else {
      installmentsErrorHelper.style.display = 'none';
      installmentsSelect.classList.remove('mp-super-token-error');
      installmentsLabel.classList.remove('mp-super-token-label-error');
    }
  }
  forceSecurityCodeValidation(paymentMethod) {
    const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
    if (!securityCodeContainer) {
      return;
    }
    const activeInstance = this.securityFieldsActiveInstance;
    if (!activeInstance) {
      this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
      return;
    }
    activeInstance.focus();
    setTimeout(() => {
      activeInstance.blur();
      setTimeout(() => {
        const hasError = securityCodeContainer.classList.contains('error');
        if (!hasError) {
          this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
        }
      }, 100);
    }, 50);
  }
  forceShowValidationErrors() {
    const paymentMethod = this.activePaymentMethod;
    if (!paymentMethod) {
      return;
    }
    if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod) && !this.isConsumerCredits(paymentMethod)) {
      return;
    }
    const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    if (!paymentMethodElement) {
      return;
    }
    if (this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined) && !this.verifyIsSecurityCodeReferenceTrue(paymentMethod)) {
      this.forceSecurityCodeValidation(paymentMethod);
    }
    if ((this.isCreditCard(paymentMethod) || this.isConsumerCredits(paymentMethod)) && !this.installmentsWasSelected(paymentMethod)) {
      this.setInstallmentsErrorState(paymentMethod, true);
    }
    paymentMethodElement.scrollIntoView({
      behavior: 'smooth'
    });
  }
  isSelectedPaymentMethodValid() {
    try {
      const paymentMethod = this.activePaymentMethod;
      if (!paymentMethod) {
        return false;
      }
      if (this.isAccountMoney(paymentMethod) || this.isPrepaidCard(paymentMethod)) {
        return true;
      }
      if (paymentMethod.id === this.NEW_CARD_TYPE) {
        return true;
      }
      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      if (!paymentMethodElement) {
        return false;
      }
      if (!this.securityCodeIsRequired('security_code_settings' in paymentMethod ? paymentMethod.security_code_settings : undefined)) {
        return true;
      }
      if (!this.securityFieldsActiveInstance) {
        return false;
      }
      const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
      if (!securityCodeContainer) {
        return false;
      }
      if (!this.verifyIsSecurityCodeReferenceTrue(paymentMethod)) {
        return false;
      }
      const hasError = securityCodeContainer.classList.contains('error');
      const helperError = securityCodeContainer.querySelector('#mp-input-with-tooltip-helper-error');
      const isErrorVisible = helperError && helperError.style.display === 'flex';
      if (hasError || isErrorVisible) {
        return false;
      }
      return true;
    } catch (error) {
      return false;
    }
  }
  validateInstallmentSelection() {
    try {
      const paymentMethod = this.activePaymentMethod;
      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      const installmentsDropdown = paymentMethodElement?.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`);
      if (installmentsDropdown && paymentMethod && (this.isCreditCard(paymentMethod) || this.isConsumerCredits(paymentMethod)) && !this.installmentsWasSelected(paymentMethod)) {
        const paymentMethodType = this.isConsumerCredits(paymentMethod) ? 'consumer_credits' : 'credit_card';
        this.mpSuperTokenMetrics.errorToSubmitWithoutInstallmentSelected(paymentMethodType);
        this.forceShowValidationErrors();
        return false;
      }
      return true;
    } catch (error) {
      this.mpSuperTokenMetrics?.sendMetric('error_to_validate_installment_selection', 'true', toTelemetryErrorMessage(error, 'unknown'));
      try {
        this.forceShowValidationErrors();
      } catch (uiError) {
        // Rendering the errors is best-effort; never let it mask the original failure being rethrown.
      }
      throw error;
    }
  }
  async getAccountPaymentMethods(token) {
    this.setSuperToken(token);
    return await this.mpSdkInstance.getAccountPaymentMethods(token);
  }
  addMercadoPagoPrivacyPolicyFooter() {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;
    const footer = document.createElement('footer');
    footer.classList.add(this.SUPER_TOKEN_STYLES.MERCADO_PAGO_PRIVACY_POLICY_FOOTER);
    footer.id = 'mp-super-token-privacy-policy-footer';
    footer.innerHTML = `<span>${this.MERCADO_PAGO_PRIVACY_POLICY}</span>`;
    customCheckoutEntireElement.insertBefore(footer, customCheckoutEntireElement.firstChild);
  }
  addHorizontalRow() {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;
    const horizontalRow = document.createElement('hr');
    horizontalRow.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW);
    customCheckoutEntireElement.insertBefore(horizontalRow, customCheckoutEntireElement.firstChild);
  }
  convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement) {
    customCheckoutEntireElement.id = this.SUPER_TOKEN_STYLES.ROOT_ID;
    customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
    customCheckoutEntireElement.setAttribute('role', 'listbox');
    customCheckoutEntireElement.setAttribute('aria-label', this.PAYMENT_METHODS_LIST_ALT_TEXT);
    customCheckoutEntireElement.setAttribute('tabindex', '0');
    customCheckoutEntireElement.parentElement?.classList.add('mp-box-shadow-none');
    customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
  }
  convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement) {
    var _customCheckoutEntire2;
    const creditCardFormElement = (_customCheckoutEntire2 = customCheckoutEntireElement.querySelector(this.NEW_CHECKOUT_CONTAINER_SELECTOR)) !== null && _customCheckoutEntire2 !== void 0 ? _customCheckoutEntire2 : customCheckoutEntireElement.querySelector(this.OLD_CHECKOUT_CONTAINER_SELECTOR);
    if (!creditCardFormElement) return;
    const createAccordionHeader = () => {
      const accordionHeader = document.createElement('section');
      accordionHeader.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_HEADER);
      accordionHeader.setAttribute('aria-label', this.NEW_CARD_TEXT);
      accordionHeader.setAttribute('tabindex', '0');
      accordionHeader.setAttribute('role', 'option');
      accordionHeader.setAttribute('aria-selected', 'false');
      // Build via DOM APIs (not innerHTML): setAttribute/textContent never parse their values as
      // HTML, so the localized WHITE_CARD_PATH/NEW_CARD_TEXT cannot break out of the markup (CWE-79),
      // matching the XSS-safe rendering used across the refactored view tree.
      const cardIcon = document.createElement('img');
      cardIcon.setAttribute('src', this.WHITE_CARD_PATH);
      const cardLabel = document.createElement('span');
      cardLabel.className = this.SUPER_TOKEN_STYLES.ACCORDION_TITLE;
      cardLabel.textContent = this.NEW_CARD_TEXT;
      accordionHeader.append(cardIcon, cardLabel);
      accordionHeader.addEventListener('click', () => {
        this.onSelectNewCardPaymentMethod();
      });
      accordionHeader.addEventListener('keydown', e => {
        if (e.code === 'Space' || e.key === 'Enter') {
          e.preventDefault();
          this.onSelectNewCardPaymentMethod();
        }
      });
      return accordionHeader;
    };
    const addAccordionClasses = accordionElement => {
      accordionElement.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION);
      accordionElement.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)?.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
    };
    addAccordionClasses(creditCardFormElement);
    const accordionHeader = createAccordionHeader();
    creditCardFormElement.addEventListener('keyup', e => {
      if (e.key === 'Tab') {
        accordionHeader.focus();
      }
    });
    creditCardFormElement.appendChild(accordionHeader);
  }
  focusFirstPaymentMethod() {
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    const firstPaymentMethod = customCheckoutEntireElement?.querySelector('article');
    if (firstPaymentMethod) {
      firstPaymentMethod.focus();
      return;
    }
    const firstAccordion = customCheckoutEntireElement?.querySelector('section');
    if (firstAccordion) {
      firstAccordion.focus();
    }
  }
  onCustomCheckoutWasRendered(customCheckoutEntireElement, paymentMethods) {
    this.hideWalletButton();
    this.hideCardFlags();
    this.convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement);
    this.addHorizontalRow();
    this.addMercadoPagoPrivacyPolicyFooter();
    this.renderSavedMethods(customCheckoutEntireElement, paymentMethods);
    this.convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement);
    this.focusFirstPaymentMethod();
    void this.selectPreloadedPaymentMethod();
    this.removeAnimationInitialState();
    this.hideAllPaymentMethodDetails();
    // After loading the payment methods, set the checkout type to super_token
    this.setCheckoutType(this.SUPER_TOKEN_CHECKOUT_TYPE);
  }
  removeAnimationInitialState() {
    const ANIMATION_DELAY = 750;
    const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
    if (!customCheckoutEntireElement) return;
    setTimeout(() => {
      customCheckoutEntireElement.classList.remove(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
    }, ANIMATION_DELAY);
  }
  renderAccountPaymentMethods(accountPaymentMethods, amount) {
    let ownsRenderingLock = false;
    try {
      var _this$getPaymentMetho;
      this.storeAmount(amount);
      this.storeActivePaymentMethod((_this$getPaymentMetho = this.getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods)) !== null && _this$getPaymentMetho !== void 0 ? _this$getPaymentMetho : null);
      if (this.paymentMethodsAreRendered() || this.isRendering) return;
      if (!this.hasStoredPaymentMethods()) this.storePaymentMethodsInMemory(accountPaymentMethods);
      this.isRendering = true;
      ownsRenderingLock = true;
      const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
      if (!customCheckoutEntireElement) {
        throw new Error(ErrorClassification_MPSuperTokenErrorCodes.CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND);
      }
      this.onCustomCheckoutWasRendered(customCheckoutEntireElement, accountPaymentMethods);
      setTimeout(() => {
        const sdkInstanceId = this.mpSuperTokenMetrics.getSdkInstanceId();
        this.mpSuperTokenMetrics.sendMetric('super_token_methods_ready', 'true', '');
        document.dispatchEvent(new CustomEvent('supertoken_loaded', {
          detail: {
            sdkInstanceId
          }
        }));
      }, 500);
    } catch (error) {
      this.mpSuperTokenMetrics.errorToRenderAccountPaymentMethods(error);
    } finally {
      if (ownsRenderingLock) {
        this.isRendering = false;
      }
    }
  }
}
;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenAuthenticator.ts
/**
 * Ported `MPSuperTokenAuthenticator` (v2.1/entities/super-token-authenticator.js) — the published
 * `window.mpSuperTokenAuthenticator` instance the Classic (`event-handler.js`) and Blocks
 * (`custom.block.js`) checkout consumers call (`authorizePayment`, `setSuperTokenValidation`) and
 * the `authenticator` dependency wired into the trigger handler and the checkout finalizers.
 *
 * It owns the load/submit *state* (the amount and e-mail last used, the SDK authenticator handle
 * and the fast payment token) and the *primitives* that build/verify/consume that handle. Its two
 * orchestrations delegate to the use cases that already own their order and fail-safe rules:
 * `getAccountPaymentMethods` → `GetAccountPaymentMethods`, `authorizePayment` → `AuthorizePayment`,
 * driven through `LegacyAuthenticatorSession` with `this` as the primitive source. Unlike the load,
 * the authorize is not fail-safe — its typed throw is the contract the callers branch on.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity
 * with the legacy class. At the flip the bundle bootstrap constructs it with the ported TS
 * SDK/payment-methods/metrics collaborators and the localized `platform_id`, then publishes it
 * through `globalBridge.publish`. The raw ad-hoc `sendMetric` calls are kept verbatim; swapping
 * them for the adapter's semantic methods is a flip-time change (the active metrics instance today
 * is still the legacy one, which has no semantic methods).
 */




/** Superset of the metrics the load use case, the submit use case and the primitives emit. */

class SuperTokenAuthenticator {
  SUPER_TOKEN_VALIDATION_ELEMENT_ID = 'super_token_validation';
  AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID = 'authorized_pseudotoken';
  AUTHENTICATOR_VERSION = 2;
  amountUsed = null;
  emailUsed = null;
  authenticator = null;
  fastPaymentToken = null;
  getAccountPaymentMethodsUseCase = new GetAccountPaymentMethods();
  authorizePaymentUseCase = new AuthorizePayment();
  constructor(mpSdkInstance, paymentMethods, metrics, platformId) {
    this.mpSdkInstance = mpSdkInstance;
    this.paymentMethods = paymentMethods;
    this.metrics = metrics;
    this.platformId = platformId;
  }
  reset() {
    this.authenticator = null;
    this.fastPaymentToken = null;
  }
  setSuperTokenValidation(value) {
    const element = document.getElementById(this.SUPER_TOKEN_VALIDATION_ELEMENT_ID);
    if (element) {
      element.value = value ? 'true' : 'false';
    }
  }
  getAmountUsed() {
    return this.amountUsed;
  }
  getEmailUsed() {
    return this.emailUsed;
  }
  storeAuthenticator(authenticator) {
    this.authenticator = authenticator;
  }
  getStoredAuthenticator() {
    return this.authenticator;
  }
  storeFastPaymentToken(token) {
    this.fastPaymentToken = token;
  }
  formatAmount(amount = '') {
    const rawValue = amount?.replace(/[^\d.,]/g, '');
    if (!rawValue) return null;
    const lastCommaIndex = rawValue.lastIndexOf(',');
    const lastDotIndex = rawValue.lastIndexOf('.');
    const isEuropean = lastCommaIndex > lastDotIndex;
    const normalizedValue = rawValue.replace(/[.,]/g, match => {
      if (isEuropean) {
        return match === ',' ? '.' : '';
      }
      return match === '.' ? '.' : '';
    });
    const value = parseFloat(normalizedValue);
    return isNaN(value) ? null : value.toFixed(2);
  }
  async buildAuthenticator(amount, buyerEmail) {
    try {
      var _window$callSdkWithMe;
      this.amountUsed = amount;
      this.emailUsed = buyerEmail;
      const callWithMetrics = (_window$callSdkWithMe = window.callSdkWithMetrics) !== null && _window$callSdkWithMe !== void 0 ? _window$callSdkWithMe : sdkCall => sdkCall();
      const authenticator = await callWithMetrics(() => this.mpSdkInstance.authenticator(amount, buyerEmail, {
        platformId: this.platformId,
        version: this.AUTHENTICATOR_VERSION
      }), 'buildAuthenticator');
      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_falsy', String(authenticator), `typeof:${typeof authenticator}`);
        return null;
      }
      return authenticator;
    } catch (error) {
      this.metrics.errorToBuildAuthenticator(error);
      return null;
    }
  }
  async getSimplifiedAuth(authenticator) {
    try {
      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_null', 'getSimplifiedAuth', '');
        return false;
      }
      return await authenticator.getSimplifiedAuth();
    } catch (error) {
      this.metrics.errorToGetSimplifiedAuth(error);
      return false;
    }
  }
  async getFastPaymentToken(authenticator) {
    try {
      if (!authenticator) {
        this.metrics.sendMetric('super_token_authenticator_null', 'getFastPaymentToken', '');
        return null;
      }
      return await authenticator.getFastPaymentToken();
    } catch (error) {
      this.metrics.errorToGetFastPaymentToken(error);
      return null;
    }
  }
  storeAuthorizedPseudotoken(pseudotoken) {
    const element = document.getElementById(this.AUTHORIZED_PSEUDOTOKEN_ELEMENT_ID);
    this.metrics.registerAuthorizedPseudotoken(element ? true : false);
    if (element) {
      element.value = pseudotoken;
    }
  }
  getAccountPaymentMethods(amount, buyerEmail) {
    return this.getAccountPaymentMethodsUseCase.execute({
      session: new LegacyAuthenticatorSession(this, this.paymentMethods),
      metrics: this.metrics,
      amount,
      buyerEmail
    });
  }
  authorizePayment(pseudotoken) {
    return this.authorizePaymentUseCase.execute({
      session: new LegacyAuthenticatorSession(this, this.paymentMethods),
      metrics: this.metrics,
      pseudotoken
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenErrorHandler.ts
/**
 * Ported `MPSuperTokenErrorHandler` (v2.1/errors/super-token-error-handler.js) — the published
 * `window.mpSuperTokenErrorHandler` instance the Classic (`event-handler.js`) and Blocks
 * (`custom.block.js`) checkout consumers call at submit time, and the `errorHandler` dependency
 * the `ClassicCheckout`/`BlocksCheckout` finalizers receive.
 *
 * It exposes the single external method `handleError`, delegating the parse → metric → display
 * sequence to the `HandleError` use case (which already owns that logic) and reusing
 * `LegacyErrorHandlerSession` to adapt its two collaborators — the metrics instance and the
 * payment-methods controller — into the use case's session port.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * or published at runtime), unit-tested for parity with the legacy class. At the flip the bundle
 * bootstrap constructs it with the ported TS `paymentMethods`/`metrics` instances and publishes it
 * through `globalBridge.publish`.
 */


class SuperTokenErrorHandler {
  handleErrorUseCase = new HandleError();
  constructor(paymentMethods, metrics) {
    this.paymentMethods = paymentMethods;
    this.metrics = metrics;
  }
  handleError(exception) {
    return this.handleErrorUseCase.execute({
      session: new LegacyErrorHandlerSession(this.metrics, this.paymentMethods),
      exception
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenEmailListener.ts
/**
 * Ported `WCEmailListener` (v2.1/entities/email-listener.js) — the buyer-email collaborator the
 * Super Token trigger handler holds as `wcEmailListener`. It owns e-mail validation, reading the
 * current e-mail from the checkout form, and the change-listener registration that lets the flow
 * re-fetch saved cards when the buyer switches accounts mid-checkout.
 *
 * It is a leaf collaborator (state `_callbacks` + DOM/e-mail primitives), so — unlike
 * `SuperTokenErrorHandler` — it delegates to no use case; it is a faithful port of the legacy
 * class. The external contract the trigger handler consumes (`isValid`, `getEmail`,
 * `onEmailChange`, `setupEmailChangeHandlers`) is preserved 1:1.
 *
 * jQuery and the input debounce are injected (with a `window.jQuery` fallback), matching the
 * tree's platform-adapter pattern (`InitializationHealthChecker`): faithful to the real
 * WooCommerce jQuery at runtime, unit-testable in jsdom without loading real jQuery.
 *
 * Part of the port-then-flip deletion of `v2/`/`v2.1/`: inert until the flip (not yet constructed
 * at runtime; `.ts` is invisible to the CDN bundle concat), unit-tested for parity with the legacy
 * class. At the flip the bundle bootstrap constructs it and hands it to the ported trigger handler.
 */

/** The `MPDebounce` surface the listener uses to debounce the input handler. */

/** Minimal jQuery surface the listener uses: read the field value and bind a delegated handler. */

class SuperTokenEmailListener {
  EMAIL_FIELD_SELECTOR = 'form[name="checkout"] input[type="email"], #email, #billing_email';
  INTERVAL_TIME = 1500;
  callbacks = [];
  constructor(mpDebounce, jquery) {
    this.mpDebounce = mpDebounce;
    this.jquery = jquery !== null && jquery !== void 0 ? jquery : window.jQuery;
  }
  isValid(email) {
    if (!email || email.length > 254) return false;
    const localPart = email.split('@')[0];
    if (localPart && localPart.length > 64) return false;
    const regex = /^[a-zA-Z0-9_%+-]+(\.[a-zA-Z0-9_%+-]+)*@[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/i;
    return regex.test(email);
  }
  getEmail() {
    return this.jquery(this.EMAIL_FIELD_SELECTOR).val()?.trim();
  }
  onEmailChange(callback) {
    this.callbacks.push(callback);
    return this;
  }
  setupEmailChangeHandlers() {
    const handleEmailUpdate = () => {
      const email = this.jquery(this.EMAIL_FIELD_SELECTOR).val();
      if (email) {
        this.callbacks.forEach(callback => callback(email, this.isValid(email)));
      }
    };
    this.jquery(document).on('input', this.EMAIL_FIELD_SELECTOR, this.mpDebounce.inputDebounce(handleEmailUpdate));
    setTimeout(() => handleEmailUpdate(), this.INTERVAL_TIME);
  }
}
;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenTriggerHandler.ts
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















/** The subset of the ported authenticator the trigger handler reads and forwards to the sessions. */

/** The subset of the ported e-mail listener the trigger handler reads and forwards to the sessions. */

/** The subset of the payment-methods controller the trigger handler forwards to the sessions. */

/** The one method of the ported error handler the deferred last-exception tail calls. */

/** The subset of the metrics adapter the trigger handler emits through directly. */

const RESTORE_ERROR_METRIC = 'super_token_restore_error';
const RESTORE_ERROR_MESSAGE = 'mp_super_token_restore_error';
class SuperTokenTriggerHandler {
  CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
  CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
  LOADING_ANIMATION_FINISH_DELAY = 500;
  AVOID_INSTANT_REMOVAL_LOADER_DELAY = 500;

  // State. `currentAmount` is the formatted amount; formatAmount returns null for an empty/NaN
  // input (parity with the legacy) and that null flows through to the SDK exactly as before.
  wcBuyerEmail = null;
  currentAmount = '';
  isAlreadyListeningForm = false;
  lastException = null;
  isFetchingPaymentMethods = false;
  customHandlerMissingReportedOnReset = false;
  loadGeneration = 0;
  cacheMetricsDispatched = false;
  savedInstallments = null;
  loadSuperTokenUseCase = new LoadSuperToken();
  fetchAndRenderUseCase = new FetchAndRenderPaymentMethods();
  cancelLoadUseCase = new CancelLoad();
  resetCustomCheckoutUseCase = new ResetCustomCheckout();
  restorePreloadedUseCase = new RestorePreloadedPaymentMethod();
  resetFlowUseCase = new ResetFlow();
  ensureEmailListenerUseCase = new EnsureEmailListenerRegistered();
  constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods, mpSuperTokenErrorHandler, mpSuperTokenMetrics, currentUserEmail) {
    this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
    this.wcEmailListener = wcEmailListener;
    this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
    this.mpSuperTokenErrorHandler = mpSuperTokenErrorHandler;
    this.mpSuperTokenMetrics = mpSuperTokenMetrics;
    this.currentUserEmail = currentUserEmail;
  }
  hasLastException() {
    return !!this.getLastException();
  }
  getLastException() {
    return this.lastException;
  }
  setLastException(exception) {
    this.lastException = exception;
  }
  getBuyerEmail() {
    this.wcBuyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.currentUserEmail;
    return this.wcBuyerEmail?.trim();
  }
  amountHasChanged() {
    const currentAmount = this.currentAmount;
    const amountUsed = this.mpSuperTokenAuthenticator.getAmountUsed();
    return currentAmount != null && amountUsed != null && currentAmount !== amountUsed;
  }
  emailHasChanged() {
    const buyerEmail = this.getBuyerEmail();
    const emailUsed = this.mpSuperTokenAuthenticator.getEmailUsed();
    return buyerEmail != null && emailUsed != null && buyerEmail !== emailUsed;
  }
  isDifferentEmail(newEmail) {
    return this.wcBuyerEmail != newEmail;
  }
  getCustomCheckoutRadioElement() {
    return document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR) || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
  }
  isClassicCheckout() {
    return !!document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
  }
  customCheckoutIsEnable() {
    return !!this.getCustomCheckoutRadioElement();
  }
  customCheckoutIsActive() {
    return this.getCustomCheckoutRadioElement()?.checked;
  }
  resetFlow() {
    this.mpSuperTokenAuthenticator.reset();
    this.mpSuperTokenPaymentMethods.reset();
  }
  resetCustomCheckout(shouldClearCache = true) {
    this.resetCustomCheckoutUseCase.execute({
      session: new LegacyResetCustomCheckoutSession(this),
      shouldClearCache
    });
  }
  finalizeResetTail() {
    setTimeout(async () => {
      window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();
      window.mpCustomCheckoutHandler?.eventHandler?.hideCheckoutClassicLoader();
      try {
        await this.restorePreloadedPaymentMethod();
      } catch (error) {
        this.mpSuperTokenMetrics.sendMetric(RESTORE_ERROR_METRIC, toTelemetryErrorMessage(error, 'unknown'), RESTORE_ERROR_MESSAGE);
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
  restorePreloadedPaymentMethod() {
    return this.restorePreloadedUseCase.execute({
      session: new LegacyRestoreSession(this, this.mpSuperTokenMetrics)
    });
  }
  resetSuperTokenOnError(preserveSelection = false) {
    this.resetFlowUseCase.execute({
      session: new LegacyResetSession(this),
      preserveSelection
    });
  }
  isSuperTokenPaymentMethodsLoaded() {
    return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods();
  }
  cancelLoad() {
    this.cancelLoadUseCase.execute({
      session: new LegacyTriggerSession(this)
    });
  }
  fetchAndRenderSuperTokenPaymentMethods() {
    return this.fetchAndRenderUseCase.execute({
      session: new LegacyTriggerSession(this),
      metrics: createFetchAndRenderMetrics(this.mpSuperTokenMetrics)
    });
  }
  ensureEmailListenerRegistered() {
    this.ensureEmailListenerUseCase.execute({
      session: new LegacyEmailListenerSession(this)
    });
  }
  dispatchStaleCacheMetricsOnce() {
    if (this.cacheMetricsDispatched) return;
    this.cacheMetricsDispatched = true;
    this.mpSuperTokenMetrics.sendStaleCacheMetrics().catch(() => {}); // fire-and-forget: must not delay checkout flow
  }
  loadSuperToken(currentAmount) {
    return this.loadSuperTokenUseCase.execute({
      session: new LegacyLoadOrchestrationSession(this),
      metrics: createLoadSuperTokenMetrics(this.mpSuperTokenMetrics),
      currentAmount
    });
  }
}
;// ./assets/js/checkouts/super-token/adapters/runtime/SuperTokenDebounce.ts
/**
 * Ported `MPDebounce` (v2.1/entities/debounce.js) — the input debounce the Super Token e-mail
 * listener uses to avoid re-fetching saved cards on every keystroke while the buyer types.
 *
 * Leaf collaborator (no state beyond the per-invocation timeout, no use case). Faithful 1:1 port
 * of the legacy class, kept as a standalone module so the flip bootstrap can construct it and hand
 * it to `SuperTokenEmailListener` — replacing the legacy `new MPDebounce()`.
 */

class SuperTokenDebounce {
  DEBOUNCE_TIME = 3000;
  inputDebounce(callback) {
    let inputTimeout;
    return inputEvent => {
      clearTimeout(inputTimeout);
      inputTimeout = setTimeout(() => callback(inputEvent), this.DEBOUNCE_TIME);
    };
  }
}
;// ./assets/js/checkouts/super-token/core/checkoutSession/PaymentMethodCatalog.ts
/**
 * Ordering and capping of the fetched account payment methods (RN-1). Caps saved
 * cards at MAX_CREDIT_CARDS (the single source, also reused by the view's grouping)
 * and orders cards-first (default) or account-money-first per store preference. Pure.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): reorderAccountPaymentMethods 2241-2255.
 */



class PaymentMethodCatalog {
  constructor(paymentMethodsOrder) {
    this.order = paymentMethodsOrder || PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST;
  }
  reorderAccountPaymentMethods(accountPaymentMethods) {
    const limitedCards = this.limitCardOptions(accountPaymentMethods);
    const accountMoneyOption = accountPaymentMethods.find(pm => isAccountMoney(pm));
    const consumerCreditsOption = accountPaymentMethods.find(pm => isConsumerCredits(pm));
    const isAccountMoneyFirst = this.order === PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST && !!accountMoneyOption;
    const moneySpecializedOptions = [];
    if (accountMoneyOption) moneySpecializedOptions.push(accountMoneyOption);
    if (consumerCreditsOption) moneySpecializedOptions.push(consumerCreditsOption);
    const result = isAccountMoneyFirst ? [moneySpecializedOptions, ...limitedCards] : [...limitedCards, moneySpecializedOptions];
    return result.flat();
  }

  /** Caps card-type methods (credit, debit, prepaid) at MAX_CREDIT_CARDS (RN-1). */
  limitCardOptions(accountPaymentMethods) {
    return accountPaymentMethods.filter(pm => PaymentMethodClassifier_isCreditCard(pm) || PaymentMethodClassifier_isDebitCard(pm) || isPrepaidCard(pm)).slice(0, MAX_CREDIT_CARDS);
  }
}
;// ./assets/js/checkouts/super-token/core/shared/formatting.ts
/**
 * Pure currency formatting shared by the installment rules. Uses the standard
 * language `Intl` (not a window/app global), so the core stays platform-free.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): formatCurrency 1106-1120.
 */


const formatCurrency = (value, options) => {
  const formatter = new Intl.NumberFormat(options.intl, {
    currency: options.currency,
    style: 'currency',
    currencyDisplay: 'narrowSymbol'
  });
  const formattedValue = formatter.format(value);
  if (options.siteId === MEXICO_ACCRONYM) {
    return formattedValue.replace(/^(\D+)/, '$1 ');
  }
  return formattedValue;
};
;// ./assets/js/checkouts/super-token/core/paymentMethods/BasePaymentMethod.ts
/**
 * Common behavior for every payment-method module. A module knows how to recognize
 * its own method (`matches`), decorate it with the display name and thumbnail
 * (`decorate`, the per-method slice of the legacy normalizeAccountPaymentMethods),
 * and answer domain questions (`requiresInstallments`, `requiresCvv`). Pure — no DOM,
 * SDK or window.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalizeAccountPaymentMethods 2257-2290.
 */



/** A card-shaped method whose thumbnail can be resolved from the per-id overrides. */

class BasePaymentMethod {
  constructor(config) {
    this.config = config;
  }
  requiresInstallments() {
    return false;
  }
  requiresCvv(paymentMethod) {
    return 'security_code_settings' in paymentMethod ? securityCodeIsRequired(paymentMethod.security_code_settings) : false;
  }

  /** thumbnails[id] → existing thumbnail → white card fallback (RN-7). */
  resolveCardThumbnail(paymentMethod) {
    return this.config.thumbnails.paymentMethodsThumbnails[paymentMethod.id] || paymentMethod.thumbnail || this.config.thumbnails.whiteCardPath;
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/BasePaymentMethodWithInstallments.ts
/**
 * Common installment behavior for the only two methods that have installments —
 * credit card and consumer credits (RN-8). Encodes interest-free counting (RN-4)
 * and the installment title, including the third-party bank-interest asterisk (RN-5).
 * Pure.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): numberOfInstallmentsWithoutFee
 * 1029-1046, needsBankInterestDisclaimer 1067-1069, buildInstallmentTitle 1122-1142.
 */






/** One option of the installment `<select>`: the number of installments and its display title. */

class BasePaymentMethodWithInstallments extends BasePaymentMethod {
  requiresInstallments() {
    return true;
  }

  /**
   * Highest interest-free installment count (RN-4). Consumer credits filter by
   * installment_rate === 0 (the API has no rate collector yet); credit cards also
   * require the MERCADOPAGO collector.
   */
  numberOfInstallmentsWithoutFee(paymentMethod) {
    if (!PaymentMethodClassifier_isCreditCard(paymentMethod) && !isConsumerCredits(paymentMethod)) {
      return 0;
    }
    if (!paymentMethod.installments || !paymentMethod.installments.length) {
      return 0;
    }
    if (isConsumerCredits(paymentMethod)) {
      const installmentsWithoutFee = paymentMethod.installments.filter(installment => installment.installment_rate === 0);
      return installmentsWithoutFee.length > 0 ? installmentsWithoutFee[installmentsWithoutFee.length - 1].installments : 0;
    }
    const installmentsWithoutFee = paymentMethod.installments.filter(installment => {
      var _installment$installm;
      return installment.installment_rate === 0 && ((_installment$installm = installment.installment_rate_collector) !== null && _installment$installm !== void 0 ? _installment$installm : []).includes('MERCADOPAGO');
    });
    return installmentsWithoutFee.length > 0 ? installmentsWithoutFee[installmentsWithoutFee.length - 1].installments : 0;
  }
  needsBankInterestDisclaimer() {
    return COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER.includes(this.config.siteId);
  }

  /** Colombia caps the installment options; every other site keeps them all (RN, legacy
   *  getInstallmentsLimit 1153-1157). */
  getInstallmentsLimit(installments) {
    return this.config.siteId === COLOMBIA_ACCRONYM ? installments.slice(0, Math.min(COLOMBIA_INSTALLMENTS_LIMIT, installments.length)) : installments;
  }

  /** The value/title pairs for the installment `<select>` options (legacy normalizeInstallments
   *  1159-1176; the MLA taxInfo it also attached is never read by the select, so it is dropped). */
  normalizedInstallments(installments) {
    return this.getInstallmentsLimit(installments).map(installment => ({
      value: `${installment.installments}`,
      title: this.buildInstallmentTitle(installment)
    }));
  }

  /**
   * Title shown for an installment option (RN-5). The trailing asterisk marks a
   * third-party interest-free installment on sites that show the bank disclaimer.
   */
  buildInstallmentTitle(installment) {
    var _installment$installm2;
    const installmentNumber = installment.installments;
    const installmentAmount = this.formatAmount(installment.installment_amount);
    const hasRate = installment.installment_rate !== 0;
    const isThirdParty = ((_installment$installm2 = installment.installment_rate_collector) !== null && _installment$installm2 !== void 0 ? _installment$installm2 : []).includes('THIRD_PARTY');
    const totalAmount = this.formatAmount(installment.total_amount);
    if (installmentNumber === 1) {
      return `${installmentNumber}x ${totalAmount}`;
    }
    if (hasRate) {
      return `${installmentNumber}x ${installmentAmount} (${totalAmount})`;
    }
    if (this.needsBankInterestDisclaimer() && isThirdParty && !hasRate) {
      return `${installmentNumber}x ${installmentAmount} (${totalAmount})*`;
    }
    return `${installmentNumber}x ${installmentAmount} ${this.config.copy.installmentsInterestFreeOptionText}`;
  }
  formatAmount(value) {
    return formatCurrency(value, {
      intl: this.config.intl,
      currency: this.config.currency,
      siteId: this.config.siteId
    });
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/CreditCardMethod.ts
/**
 * Credit card — has installments (RN-8). Decoration distinguishes a Mercado Pago
 * credit card (own name + blue/dark MP icon by site, RN-7) from a regular issuer
 * card ("<issuer> Crédito").
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize credit branch 2277-2285,
 * getMpCardThumbnailPath 813-816.
 */




class CreditCardMethod extends BasePaymentMethodWithInstallments {
  matches(paymentMethod) {
    return PaymentMethodClassifier_isCreditCard(paymentMethod);
  }
  decorate(paymentMethod) {
    var _paymentMethod$issuer;
    if (!PaymentMethodClassifier_isCreditCard(paymentMethod)) return paymentMethod;

    // Only the v2.1 variant gives a Mercado Pago credit card the special MP name + blue/dark icon
    // (RN-7); v2 treats every credit card as a regular issuer card. Gating it on the variant keeps
    // the v2.1 presentation out of the v2 A/B cohort (which otherwise leaked via the mutated thumbnail).
    if (this.config.variant === 'v2.1' && isMercadoPagoCreditCard(paymentMethod)) {
      paymentMethod.thumbnail = this.mercadoPagoCardThumbnail() || paymentMethod.thumbnail;
      paymentMethod.name = this.config.copy.mercadoPagoCreditCardName || paymentMethod.name;
      return paymentMethod;
    }
    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    paymentMethod.name = `${(_paymentMethod$issuer = paymentMethod.issuer?.name) !== null && _paymentMethod$issuer !== void 0 ? _paymentMethod$issuer : paymentMethod.name} Crédito`;
    return paymentMethod;
  }
  mercadoPagoCardThumbnail() {
    return MP_CARD_BLUE_SITES.includes(this.config.siteId) ? this.config.thumbnails.mpLogoBluePath : this.config.thumbnails.mpLogoDarkPath;
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/DebitCardMethod.ts
/**
 * Debit card — no installments (extends BasePaymentMethod, RN-8). Decorated as
 * "<issuer> Débito" with the per-id/white-card thumbnail.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize debit branch 2277-2285.
 */



class DebitCardMethod extends BasePaymentMethod {
  matches(paymentMethod) {
    return PaymentMethodClassifier_isDebitCard(paymentMethod);
  }
  decorate(paymentMethod) {
    var _paymentMethod$issuer;
    if (!PaymentMethodClassifier_isDebitCard(paymentMethod)) return paymentMethod;
    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    paymentMethod.name = `${(_paymentMethod$issuer = paymentMethod.issuer?.name) !== null && _paymentMethod$issuer !== void 0 ? _paymentMethod$issuer : paymentMethod.name} Débito`;
    return paymentMethod;
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/PrepaidCardMethod.ts
/**
 * Prepaid card — no installments (RN-8). A Mercado Pago prepaid card also gets the
 * MP card name; every prepaid card gets the per-id/white-card thumbnail. Both effects
 * apply cumulatively, mirroring the two separate ifs in the legacy normalize.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalize MP-card 2269-2271 and
 * prepaid 2273-2275 branches.
 */



class PrepaidCardMethod extends BasePaymentMethod {
  matches(paymentMethod) {
    return isPrepaidCard(paymentMethod);
  }
  decorate(paymentMethod) {
    if (!isPrepaidCard(paymentMethod)) return paymentMethod;
    if (isMercadoPagoCard(paymentMethod)) {
      paymentMethod.name = this.config.copy.mercadoPagoCardName;
    }
    paymentMethod.thumbnail = this.resolveCardThumbnail(paymentMethod);
    return paymentMethod;
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/AccountMoneyMethod.ts
/**
 * Account money (wallet) — no installments. Decorated with the yellow wallet icon
 * and a site-aware name; only Mexico distinguishes wallet vs. invested balance.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): buildAccountMoneyName 997-1016,
 * normalize account-money branch 2259-2262.
 */




class AccountMoneyMethod extends BasePaymentMethod {
  matches(paymentMethod) {
    return isAccountMoney(paymentMethod);
  }
  decorate(paymentMethod) {
    if (!isAccountMoney(paymentMethod)) return paymentMethod;
    paymentMethod.thumbnail = this.config.thumbnails.yellowWalletPath;
    paymentMethod.name = this.buildAccountMoneyName(paymentMethod);
    return paymentMethod;
  }
  buildAccountMoneyName(paymentMethod) {
    const {
      copy
    } = this.config;
    if (this.config.siteId !== MEXICO_ACCRONYM) {
      return copy.accountMoneyText;
    }
    if (userHasAccountMoney(paymentMethod) && userHasAccountMoneyInvested(paymentMethod)) {
      return copy.accountMoneyWalletWithInvestmentText;
    }
    if (userHasAccountMoney(paymentMethod)) {
      return copy.accountMoneyWalletText;
    }
    if (userHasAccountMoneyInvested(paymentMethod)) {
      return copy.accountMoneyInvestmentText;
    }
    return copy.accountMoneyAvailableText;
  }
}
;// ./assets/js/checkouts/super-token/core/shared/escapeHtml.ts
/**
 * Escapes a string for safe interpolation into an HTML string sink. Ported verbatim from the
 * legacy `escapeHtml` (payment-methods.js:174-178): it relies on the DOM to escape `<`, `>` and
 * `&`, then additionally escapes quotes. Used by the consumer-credits hint, which is assigned via
 * `innerHTML` and must keep escaping the SDK-provided condition values.
 */
function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = String(value);
  return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/ConsumerCreditsMethod.ts
/**
 * Consumer credits ("digital_currency") — has installments (RN-8). Decorated with the
 * yellow money icon and a site-specific name. The names are hardcoded literals in the
 * legacy code (not localized params), preserved verbatim including the &nbsp; entity.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): buildConsumerCreditsName 1018-1027,
 * normalize consumer-credits branch 2264-2267.
 */





const TWO_DECIMALS = 2;
class ConsumerCreditsMethod extends BasePaymentMethodWithInstallments {
  matches(paymentMethod) {
    return isConsumerCredits(paymentMethod);
  }
  decorate(paymentMethod) {
    if (!isConsumerCredits(paymentMethod)) return paymentMethod;
    paymentMethod.thumbnail = this.config.thumbnails.yellowMoneyPath;
    paymentMethod.name = this.buildConsumerCreditsName();
    return paymentMethod;
  }
  buildConsumerCreditsName() {
    switch (this.config.siteId) {
      case MEXICO_ACCRONYM:
        return 'Meses sin Tarjeta con Mercado&nbsp;Pago';
      case BRAZIL_ACCRONYM:
        return 'Linha de Crédito Mercado&nbsp;Pago';
      default:
        return 'Cuotas sin Tarjeta con Mercado&nbsp;Pago';
    }
  }

  /**
   * The site-specific legal hint (an HTML string, assigned via innerHTML downstream) for the
   * selected consumer-credits installment. Ported from the legacy `buildConsumerCreditsHint`
   * (payment-methods.js:1472-1550); throws when the installment carries no conditions.
   */
  buildConsumerCreditsHint(installment) {
    var _installment$labels$f;
    const rawLabels = (_installment$labels$f = installment?.labels?.find(label => label.toLowerCase().includes('|'))?.toLowerCase()) !== null && _installment$labels$f !== void 0 ? _installment$labels$f : '';
    const conditions = rawLabels.split('|').reduce((accumulator, label) => {
      const [key, value] = label.split('_');
      accumulator[key] = value;
      return accumulator;
    }, {});
    if (!installment?.consumer_credits?.conditions) {
      throw new Error('no_installment_conditions');
    }
    const copy = this.config.copy.consumerCreditsHint;
    switch (this.config.siteId) {
      case BRAZIL_ACCRONYM:
        {
          const parts = [];
          if (conditions.tem && conditions.tea) {
            parts.push(`${copy.interestRateMlb}: ${escapeHtml(conditions.tem)} ${copy.perMonth} ${escapeHtml(conditions.tea)} ${copy.perYear}`);
          }
          if (conditions.cetm && conditions.ceta) {
            parts.push(`${copy.effectiveTotalCostMlb}: ${escapeHtml(conditions.cetm)} ${copy.perMonth} ${escapeHtml(conditions.ceta)} ${copy.perYear}`);
          }
          if (conditions.iof) {
            const iofAmount = installment.installment_iof_amount || 0;
            if (iofAmount > 0) {
              const iofAmountFormatted = iofAmount.toFixed(TWO_DECIMALS).replace('.', ',');
              parts.push(`${copy.iofMlb}: R$ ${iofAmountFormatted} (${escapeHtml(conditions.iof)})`);
            }
          }
          const borrowedAmount = installment.total_amount - (installment.installment_iof_amount || 0);
          parts.push(`${copy.borrowedAmountMlb}: R$ ${borrowedAmount.toFixed(TWO_DECIMALS).replace('.', ',')}`);
          return `${parts.join('. ')}.`;
        }
      case MEXICO_ACCRONYM:
        {
          const mexParts = [];
          if (conditions.cat) {
            mexParts.push(`${copy.catMlm}: ${escapeHtml(conditions.cat)} ${copy.noIvaMlm}`);
          }
          if (conditions.tna) {
            mexParts.push(`${copy.tnaMlm}: ${escapeHtml(conditions.tna)}`);
          }
          if (mexParts.length > 0) {
            mexParts.push(`${copy.systemAmortizationMlm}`);
            return `${mexParts.join('. ')}.`;
          }
          return '';
        }
      default:
        {
          const argParts = [];
          if (conditions.cftea) {
            argParts.push(`<strong>${copy.cfteaMla}: ${escapeHtml(conditions.cftea)}</strong>`);
          }
          if (conditions.tna) {
            argParts.push(`${copy.tnaMla}: ${escapeHtml(conditions.tna)}`);
          }
          if (conditions.tea) {
            argParts.push(`${copy.teaMla}: ${escapeHtml(conditions.tea)}`);
          }
          if (argParts.length > 0) {
            return `${argParts.join(' - ')}. ${copy.fixedRate}`;
          }
          return '';
        }
    }
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/NewCardMethod.ts
/**
 * The "add a new card" option — a UI-selectable pseudo-method the SDK never returns
 * in the account list, so it carries no decoration. Modeled here so the view can
 * resolve every selectable option uniformly through the registry.
 */



class NewCardMethod extends BasePaymentMethod {
  matches(paymentMethod) {
    return isNewCard(paymentMethod);
  }
  decorate(paymentMethod) {
    return paymentMethod;
  }
}
;// ./assets/js/checkouts/super-token/core/paymentMethods/registry.ts
/**
 * Resolves a raw payment method to the module that owns it. `resolve(pm).decorate(pm)`
 * is the pure equivalent of the legacy normalizeAccountPaymentMethods: each method's
 * decoration lives in its own module instead of a chain of ifs. The modules match on
 * mutually exclusive types, so resolution order is deterministic.
 *
 * Preserved from MPSuperTokenPaymentMethods (v2.1): normalizeAccountPaymentMethods 2257-2290.
 */







class PaymentMethodRegistry {
  constructor(config) {
    this.modules = [new CreditCardMethod(config), new DebitCardMethod(config), new PrepaidCardMethod(config), new AccountMoneyMethod(config), new ConsumerCreditsMethod(config), new NewCardMethod(config)];
  }
  resolve(paymentMethod) {
    return this.modules.find(module => module.matches(paymentMethod));
  }

  /** Decorates one payment method (name + thumbnail) via its resolved module. */
  decorate(paymentMethod) {
    var _this$resolve$decorat;
    return (_this$resolve$decorat = this.resolve(paymentMethod)?.decorate(paymentMethod)) !== null && _this$resolve$decorat !== void 0 ? _this$resolve$decorat : paymentMethod;
  }

  /**
   * Decorates every method with its display name and thumbnail (the list form of
   * `decorate`). Pure equivalent of the legacy `normalizeAccountPaymentMethods`.
   */
  decorateAccountPaymentMethods(paymentMethods) {
    return paymentMethods.map(paymentMethod => this.decorate(paymentMethod));
  }
}
const createPaymentMethodRegistry = config => new PaymentMethodRegistry(config);
;// ./assets/js/checkouts/super-token/adapters/session/LegacyRenderSession.ts
/**
 * Session adapter that lets the refactored variant view drive the saved-methods render while
 * the interactive row (details + installments select + security-code container + behaviour
 * wiring) still lives in the legacy `MPSuperTokenPaymentMethods` controller
 * (`createPaymentMethodElement`, v2.1 payment-methods.js:1966). The view owns the render
 * *order* (grouping, blocks, header, e-mail listener); this adapter supplies the row as the
 * single legacy *primitive* — a transitional scaffold that disappears once the row itself is
 * ported into the tree (then the view builds its own presentation row).
 */

const DISPATCHER_MISSING_METRIC = 'MP_CHECKOUT_FIELDS_DISPATCHER_MISSING';
const DISPATCHER_MISSING_MESSAGE = 'mp_super_token_init_error';
const INSTALLMENTS_FILLED_EVENT = 'super_token_installments_filled';
const CONSUMER_CREDITS_METHOD_TYPE = 'consumer_credits';

/** The legacy metrics instance methods the render session forwards to. */

// Module-level so a context is reported once even across re-renders (new session per render), while
// keeping card and consumer-credits diagnostics independent from one another.
const dispatcherMissingContexts = new Set();

/**
 * The subset of the legacy `MPSuperTokenPaymentMethods` controller the render sequence calls.
 * Grounded in `createPaymentMethodElement` (payment-methods.js:1966-2194) and its click/keydown
 * wiring (`onSelectSuperTokenPaymentMethod`, 709); the legacy global is an opaque handle, so the
 * primitives are named here.
 */

class LegacyRenderSession {
  constructor(legacy) {
    this.legacy = legacy;
  }

  /** Builds a row type not yet ported into the tree, via the legacy controller. */
  buildRow(paymentMethod) {
    return this.legacy.createPaymentMethodElement(paymentMethod);
  }

  /** Selection primitive for the rows the tree builds itself; the legacy method delegates to the
   *  refactored selection seam, so this reuses that path. Fire-and-forget, matching the legacy
   *  click handler (payment-methods.js:2019). */
  onSelectPaymentMethod(row, paymentMethod) {
    void this.legacy.onSelectSuperTokenPaymentMethod(row, paymentMethod);
  }
  updateInstallmentsTaxInfo(selectedValue, taxInfoElementId, installments) {
    window.CheckoutPage?.updateTaxInfoForSelect(selectedValue, taxInfoElementId, installments);
  }
  installmentSelected(methodType) {
    window.MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(null, 'focusout', INSTALLMENTS_FILLED_EVENT, {
      onlyDispatch: true
    });
    this.legacy.mpSuperTokenMetrics.installmentsFilled(methodType);
  }
  reportInstallmentDispatcherMissing(context) {
    if (window.MPCheckoutFieldsDispatcher || typeof window.sendMetric !== 'function' || dispatcherMissingContexts.has(context)) {
      return;
    }
    window.sendMetric(DISPATCHER_MISSING_METRIC, context, DISPATCHER_MISSING_MESSAGE);
    dispatcherMissingContexts.add(context);
  }
  recordPaymentMethodRowFailure(error) {
    this.legacy.mpSuperTokenMetrics.errorToRenderAccountPaymentMethods(error);
  }
  getFastPaymentToken() {
    return this.legacy.getSuperToken();
  }
  renderCreditsContract(elementId, parameters) {
    return this.legacy.mpSdkInstance.renderCreditsContract(elementId, parameters);
  }
  updateCreditsContract(controller, installments) {
    try {
      controller.update({
        installments
      });
      this.legacy.mpSuperTokenMetrics.installmentsFilled(CONSUMER_CREDITS_METHOD_TYPE);
    } catch (error) {
      this.legacy.mpSuperTokenMetrics.errorToUpdateCreditsContract(error);
    }
  }
  dispatchInstallmentsFilledField() {
    window.MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(null, 'focusout', INSTALLMENTS_FILLED_EVENT, {
      onlyDispatch: true
    });
  }
  recordCreditsContractRendered(success, error) {
    this.legacy.mpSuperTokenMetrics.renderCreditsContract(success, error);
  }
  recordOpenCreditsInfoModal(linkText) {
    this.legacy.mpSuperTokenMetrics.registerOpenCreditsInfoModal(linkText);
  }
  recordConsumerCreditsHint(success, error) {
    this.legacy.mpSuperTokenMetrics.renderConsumerCreditsHint(success, error);
  }
  recordConsumerCreditsDueDate(success, error) {
    this.legacy.mpSuperTokenMetrics.renderConsumerCreditsDueDate(success, error);
  }
  recordConsumerCreditsDetails(success) {
    this.legacy.mpSuperTokenMetrics.renderConsumerCreditsDetailsInnerHTML(success);
  }
}
;// ./assets/js/checkouts/super-token/composition/variantRuntime.ts
/**
 * A/B variant resolution for the composition root. Bundle/prod resolves the variant through
 * VariantConfigAdapter (remote config → cookie → weighted → fallback v2, with source:* telemetry);
 * dev/self-construct has no loader cookie, so it follows the localized MP_SUPER_TOKEN_VERSION (then
 * cookie, then v2). The same resolved variant drives both the core decoration and the rendered view.
 * Variant names, cookie and JS version live in adapters/platform/constants.ts.
 */



// Build-time A/B variant pin, injected by webpack DefinePlugin (PSW-4417). Empty in the unified
// mp-super-token/ bundle staged for TASK-013, which resolves the variant at runtime; set to 'v2' /
// 'v2.1' in the per-path retrocompat bundles the 8.9.3 loader fetches from v1/ and v2.1/.

// Dev/self-construct reads the A/B variant cookie as a fallback (bundle/prod resolves it through
// VariantConfigAdapter).
function readVariantCookie() {
  const match = document.cookie.match(new RegExp('(^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)'));
  return match ? match[2] : null;
}

// Self-construct: dev mode (MP_SUPER_TOKEN_USE_BUNDLE=false, PHP sets self_construct=true) — the
// tree builds the stateful instances itself. Bundle mode leaves self_construct falsy; the selected
// refactored CDN bundle owns the same composition, with the path cutover still deferred to TASK-013.
function isSelfConstruct() {
  return Boolean(window.wc_mercadopago_supertoken_bundle_params?.self_construct);
}
function resolveSuperTokenVariant() {
  // A legacy retrocompat bundle (v1/, v2.1/) carries its variant frozen at build time, so it renders
  // the folder the older plugin's loader fetched — no runtime A/B resolution (which could diverge
  // from that folder). The runtime bundle leaves this empty and resolves below.
  if (true) {
    return Promise.resolve("v2");
  }
  if (isSelfConstruct()) {
    var _ref;
    const localized = window.wc_mercadopago_supertoken_bundle_params?.super_token_version;
    return Promise.resolve((_ref = localized !== null && localized !== void 0 ? localized : readVariantCookie()) !== null && _ref !== void 0 ? _ref : V2_VARIANT);
  }
  return new VariantConfigAdapter_VariantConfigAdapter().resolve();
}
;// ./assets/js/checkouts/super-token/composition/runtimeComposition.ts
/**
 * Runtime composition: resolves the A/B variant, publishes the order+decorate seam (Phase 5) and,
 * once the SDK exists, builds and publishes the stateful runtime instances + the saved-methods
 * render (Phase 6). In the pre-cutover hybrid the legacy bundle may build the instances first; the
 * window.mpSuperTokenTriggerHandler guard makes this a no-op then.
 */


















// SDK-readiness gate, mirroring the legacy v2.1/mp-super-token.js: run once the SDK instance
// exists (immediately if already there, else on the ready event, with a bounded poll fallback for
// stores where the event fired before this module loaded — see checkout-resilience rules).
const runtimeComposition_MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
const runtimeComposition_FALLBACK_POLL_INTERVAL_MS = 50;
const runtimeComposition_FALLBACK_POLL_MAX_WAIT_MS = 15000;
function whenSdkReady(run) {
  if (window.mpSdkInstance) {
    run();
    return;
  }
  const poll = setInterval(() => {
    if (window.mpSdkInstance) {
      clearInterval(poll);
      run();
    }
  }, runtimeComposition_FALLBACK_POLL_INTERVAL_MS);
  // Clear the poll here too: without it, if the ready event fires first the interval keeps
  // ticking (no-ops) until the timeout, and run() could fire twice (event + a later poll tick).
  document.addEventListener(runtimeComposition_MP_SDK_INSTANCE_READY_EVENT, () => {
    clearInterval(poll);
    run();
  }, {
    once: true
  });
  setTimeout(() => clearInterval(poll), runtimeComposition_FALLBACK_POLL_MAX_WAIT_MS);
}

// window.mpCustomCheckoutHandler is assigned on mp-custom-checkout.js's own DOMContentLoaded
// listener — a signal with no causal relationship to the SDK-readiness gate above. In self-construct
// mode this composition runs as a plain synchronous script (no CDN fetch delay), so it can execute
// before that listener fires; a single point-in-time read races it and false-positives. Poll instead,
// mirroring the waitForHandler pattern already used for this same global in cart-update.helper.js.
const CUSTOM_HANDLER_POLL_INTERVAL_MS = 100;
const CUSTOM_HANDLER_MAX_WAIT_MS = 15000;
async function waitForCustomCheckoutHandler() {
  const startedWaitingAt = Date.now();
  while (!window.mpCustomCheckoutHandler) {
    if (Date.now() - startedWaitingAt >= CUSTOM_HANDLER_MAX_WAIT_MS) {
      return false;
    }
    await new Promise(resolve => setTimeout(resolve, CUSTOM_HANDLER_POLL_INTERVAL_MS));
  }
  return true;
}

/**
 * Resolve the variant, then compose the order+decorate seam and the runtime instances. Called only
 * when the localized domain params are present; otherwise the legacy organizePaymentMethodsElements
 * keeps its inline reorder + normalize (the decoration would lack its copy/thumbnails).
 */
function composeRuntime(domainParams, recompose, metrics) {
  const viewParams = domainParams;
  const composeWithVariant = variant => {
    const domainConfig = createDomainConfig(domainParams, variant);
    const catalog = new PaymentMethodCatalog(domainConfig.paymentMethodsOrder);
    const registry = new PaymentMethodRegistry(domainConfig);
    const orderAndDecorate = paymentMethods => registry.decorateAccountPaymentMethods(catalog.reorderAccountPaymentMethods(paymentMethods));
    publishOrderAndDecorate({
      orderAndDecorate
    });

    // The installment `<select>` options + consumer-credits hint come from the domain core (the
    // view has no domain config).
    const creditCardMethod = new CreditCardMethod(domainConfig);
    const consumerCreditsMethod = new ConsumerCreditsMethod(domainConfig);
    const installmentOptions = paymentMethod => 'installments' in paymentMethod && paymentMethod.installments ? creditCardMethod.normalizedInstallments(paymentMethod.installments) : [];
    const consumerCreditsHint = installment => consumerCreditsMethod.buildConsumerCreditsHint(installment);

    // Build + publish the TS instances once the SDK exists — the tree is the runtime for both
    // variants (createVariantView falls back to v2). In the pre-cutover hybrid the legacy bundle
    // may build them first; the window.mpSuperTokenTriggerHandler guard makes this a no-op then.
    const buildAndPublishInstances = () => {
      const sdk = window.mpSdkInstance;
      // Idempotent: the ready event + poll fallback can both fire; the built-guard makes the
      // second a no-op (mirrors the legacy superTokenAlreadyBuilt guard).
      if (!sdk || window.mpSuperTokenTriggerHandler) {
        return;
      }
      const bundleParams = window.wc_mercadopago_supertoken_bundle_params;
      const entityMetrics = new CoreMonitorMetricsAdapter_CoreMonitorMetricsAdapter(sdk, SUPER_TOKEN_JS_VERSION, window.wc_mercadopago_supertoken_bundle_params);
      const emailListener = new SuperTokenEmailListener(new SuperTokenDebounce());

      // Lazy + memoized: created on first render and reused, so the e-mail header listener
      // (guarded per view instance) registers exactly once across re-renders.
      let view;
      // Referenced lazily by the render closure (assigned right after, before any render runs),
      // so the closure can be a constructor argument without a construction-order cycle.
      let paymentMethods;
      const getView = () => {
        if (!view) {
          view = createVariantView(variant, createVariantViewDeps(viewParams, emailListener));
        }
        return view;
      };
      const renderSavedMethods = (container, methods) => {
        // One stylesheet serves both variants; the root's data-variant scopes each variant's rules.
        container.setAttribute('data-variant', variant);
        // The view builds every row itself now (createPaymentMethodElement is dropped), so buildRow
        // is omitted; the render session only supplies the interactive-row behaviour primitives.
        const session = new LegacyRenderSession(paymentMethods);
        getView().renderSavedPaymentMethods({
          container,
          paymentMethods: orderAndDecorate(methods),
          rowSession: session,
          installmentOptions,
          consumerCreditsHint
        });
      };
      paymentMethods = new SuperTokenPaymentMethods(sdk, entityMetrics, bundleParams, renderSavedMethods, emailListener, getView());
      const authenticator = new SuperTokenAuthenticator(sdk, paymentMethods, entityMetrics, bundleParams.platform_id);
      const errorHandler = new SuperTokenErrorHandler(paymentMethods, entityMetrics);
      const triggerHandler = new SuperTokenTriggerHandler(authenticator, emailListener, paymentMethods, errorHandler, entityMetrics, bundleParams.current_user_email);
      const instances = {
        triggerHandler,
        authenticator,
        paymentMethods,
        metrics: entityMetrics,
        errorHandler
      };
      publish(instances);
      if (typeof window.mpEventHandler?.setSuperTokenDependencies === 'function') {
        window.mpEventHandler.setSuperTokenDependencies(instances);
      }

      // Kept from the legacy build: the custom-checkout-handler-missing init signal (the SDK-ready
      // and init-health signals are already owned by the resilience watcher/checker below). Polled,
      // not a single read — see waitForCustomCheckoutHandler above for why a point-in-time check
      // false-positives here. Fire-and-forget: reporting is a side effect, not a composition gate.
      void waitForCustomCheckoutHandler().then(handlerFound => {
        if (!handlerFound) {
          entityMetrics.sendMetric('MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', 'mp_super_token_init', 'mp_super_token_init_error');
        }
      });
    };
    recompose.current = buildAndPublishInstances;
    // whenSdkReady may invoke this synchronously or from the async poll / ready-event paths. A throw
    // on the async paths escapes the composeRuntime().catch below, leaving the composition failure
    // invisible, so guard the invocation here to keep every path instrumented. recompose.current
    // stays unwrapped so the resilience recovery path keeps its own super_token_recovery_compose_failed.
    whenSdkReady(() => {
      try {
        buildAndPublishInstances();
      } catch (error) {
        metrics.sendMetric('super_token_compose_failed', 'mp_super_token_init', toTelemetryErrorMessage(error));
      }
    });
  };
  return resolveSuperTokenVariant().then(composeWithVariant).catch(error => {
    metrics.sendMetric('super_token_compose_failed', 'mp_super_token_init', toTelemetryErrorMessage(error));
    try {
      composeWithVariant(SUPER_TOKEN_FALLBACK_VARIANT);
    } catch (fallbackError) {
      metrics.sendMetric('super_token_compose_failed', 'mp_super_token_init', toTelemetryErrorMessage(fallbackError));
    }
  });
}
;// ./assets/js/checkouts/super-token/composition/initializationResilience.ts
/**
 * Initialization resilience (Phase 2): SDK-readiness reporting and post-mount health check.
 * The watcher reports SDK readiness (super_token_sdk_loaded / super_token_init_source) — which it
 * now owns after the equivalent code was stripped from the legacy mp-super-token.js — and the
 * checker validates the composed instances after the card form mounts.
 */



// The health check validates the composed instances. In the hybrid they are the legacy globals
// the CDN bundle builds; return null until they exist so the checker can re-evaluate later.
function readLegacyInstances() {
  const {
    mpSuperTokenTriggerHandler,
    mpSuperTokenAuthenticator,
    mpSuperTokenPaymentMethods,
    mpSuperTokenMetrics,
    mpSuperTokenErrorHandler
  } = window;
  if (!mpSuperTokenTriggerHandler) {
    return null;
  }
  return {
    triggerHandler: mpSuperTokenTriggerHandler,
    authenticator: mpSuperTokenAuthenticator,
    paymentMethods: mpSuperTokenPaymentMethods,
    metrics: mpSuperTokenMetrics,
    errorHandler: mpSuperTokenErrorHandler
  };
}

/**
 * Wire the SDK-readiness watcher and the init health checker. Platform edge: the one place allowed
 * to read the localized params and the (still-legacy) instances, keeping the domain free of window.*.
 */
function startInitializationResilience(recompose, metrics) {
  const watcher = new SdkReadinessWatcher({
    metrics
  });
  const checker = new InitializationHealthChecker({
    metrics,
    getInstances: readLegacyInstances
  });

  // Single combined listener — recovery MUST run before the health check so a late-arriving SDK is
  // composed before the checker reads the instances (adapters/platform/README.md pre-condition).
  document.addEventListener(CARD_FORM_MOUNTED_EVENT, () => {
    watcher.recoverIfSdkIsNowAvailable();
    checker.check(CARD_FORM_MOUNTED_EVENT);
  });

  // In bundle mode the trigger-handler guard makes recomposition a no-op (the legacy bundle composes
  // the stateful classes), but in self-construct — and after the cutover — the tree composes, so the
  // watcher's card-form recovery path must re-run the composition for a late SDK that arrived after
  // the poll window closed. The watcher also owns the SDK-readiness signals.
  let firstComposeFailureReported = false;
  let lastComposeError;
  watcher.start(() => {
    try {
      recompose.current();
      return true;
    } catch (error) {
      lastComposeError = error;
      if (!firstComposeFailureReported) {
        metrics.sendMetric('super_token_recovery_compose_failed', 'mp_super_token_init', toTelemetryErrorMessage(error));
        firstComposeFailureReported = true;
      }
      return false;
    }
  }, attempts => {
    metrics.sendMetric('super_token_recovery_compose_failed', 'mp_super_token_init', `retry_exhausted:${attempts}; last_error:${toTelemetryErrorMessage(lastComposeError)}`);
  });
}
;// ./assets/js/checkouts/super-token/bootstrap.ts
var _window$wc_mercadopag;
/**
 * Bundle entrypoint for the refactored Super Token runtime — the only runtime after the cutover.
 * In dev/self-construct mode this file is built locally (build/super-token/bootstrap.ts.js) and
 * enqueued by CustomGateway; in bundle mode it is the single CDN bundle. Emergency rollback is
 * CDN-first (republish the previous bundle), no longer a per-store toggle.
 *
 * This is a thin orchestrator: it wires the three composition concerns and holds no logic itself.
 * Loading it (1) publishes every legacy-delegation seam to window.mpSuperToken* through the
 * transitional bridge so the still-legacy JS classes delegate each orchestration step to the TS use
 * cases, (2) resolves the A/B variant and — once the SDK exists — builds and publishes the stateful
 * runtime instances + saved-methods render, and (3) starts the SDK-readiness/init-health resilience.
 *
 * In the pre-cutover hybrid the legacy `mp-super-token.js` still builds the stateful classes and
 * mirrors them to `window.mpSuperToken*`; the runtime composition reuses those instances through
 * typed session adapters (guarded so it never rebuilds them) and the delegation seams keep a
 * fallback path so stores stay safe if a seam fails.
 */






// Publish the legacy-delegation seams (synchronous, both modes).
publishLegacyDelegationSeams();

// Order + decorate (Phase 5) + render (Phase 6). Platform edge: read the localized params once.
// When they are absent the seam is not published, so the legacy organizePaymentMethodsElements
// keeps its inline reorder + normalize (the decoration would otherwise lack its copy/thumbnails).
const domainParams = window.wc_mercadopago_supertoken_bundle_params;

// Shared late-bound composition trigger. `buildAndPublishInstances` is a closure created only once
// the A/B variant resolves (async), so it cannot be referenced when the resilience watcher starts at
// module load. composeRuntime binds it here; the watcher's card-form recovery path re-invokes it for
// a late SDK that missed the poll window. Idempotent via the trigger-handler guard (PSW-4277).
const recompose = {
  current: () => {}
};

// One Core Monitor adapter shared by the runtime composition (compose failures) and the init
// resilience (SDK-readiness + recovery). Lazy SDK read so it works before the SDK exists.
const bootstrap_PARAMS_FALLBACK = {
  plugin_version: '',
  platform_version: '',
  site_id: '',
  cust_id: '',
  location: '',
  platform_id: ''
};
const metrics = new CoreMonitorMetricsAdapter_CoreMonitorMetricsAdapter(() => window.mpSdkInstance, SUPER_TOKEN_JS_VERSION, (_window$wc_mercadopag = window.wc_mercadopago_supertoken_bundle_params) !== null && _window$wc_mercadopag !== void 0 ? _window$wc_mercadopag : bootstrap_PARAMS_FALLBACK);
if (domainParams) {
  // The SDK watcher must observe the real variant-bound callback. Starting it before this Promise
  // settles can mark an empty callback as initialized when the SDK was already present.
  void composeRuntime(domainParams, recompose, metrics).then(() => {
    startInitializationResilience(recompose, metrics);
  });
} else {
  startInitializationResilience(recompose, metrics);
}
/******/ })()
;
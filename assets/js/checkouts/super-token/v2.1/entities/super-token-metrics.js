/* globals wc_mercadopago_supertoken_metrics_params */
/* eslint-disable no-unused-vars */
class MPSuperTokenMetrics {
  PLATFORM_NAME = 'woocommerce';
  CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
  MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';
  MELIDATA_TIMEOUT_MS = 5000;
  MELIDATA_LOAD_TIMEOUT_METRIC = 'mp_melidata_load_timeout';

  // Params
  PLUGIN_VERSION = wc_mercadopago_supertoken_bundle_params.plugin_version;
  PLATFORM_VERSION = wc_mercadopago_supertoken_bundle_params.platform_version;
  SITE_ID = wc_mercadopago_supertoken_bundle_params.site_id;
  CUST_ID = wc_mercadopago_supertoken_bundle_params.cust_id;
  LOCATION = wc_mercadopago_supertoken_bundle_params.location;
  SUPER_TOKEN_JS_VERSION = null;
  CUSTOM_CHECKOUT_STEPS = {
    LOAD_SUPER_TOKEN: 'load_super_token',
    SELECT_PAYMENT_METHOD: 'select_payment_method',
    POST_SUBMIT: 'post_submit',
  }

  // Dependencies
  mpSdkInstance = null;

  constructor(mpSdkInstance, SUPER_TOKEN_JS_VERSION) {
    this.mpSdkInstance = mpSdkInstance;
    this.SUPER_TOKEN_JS_VERSION = SUPER_TOKEN_JS_VERSION;
  }

  getSdkInstanceId() {
    try {
      return this?.mpSdkInstance?.getSDKInstanceId() || 'Unknown';
    } catch (error) {
      return 'Unknown';
    }
  }

  getEnvironment() {
    return 'prod';
  }

  sendMetric(metricName, value, message, errorCode = null) {
    const details = {
      site_id: this.SITE_ID,
      environment: this.getEnvironment(),
      sdk_instance_id: this.getSdkInstanceId(),
      cust_id: this.CUST_ID,
      js_version: this.SUPER_TOKEN_JS_VERSION,
    };

    if (errorCode) {
      details.event = `${errorCode}`;
    }

    fetch(
      `${this.CORE_MONITOR_URL}/${metricName}`,
      {
        method: "POST",
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          "value": `${value}`,
          "message": `${message}`,
          "plugin_version": this.PLUGIN_VERSION,
          "platform": {
            name: this.PLATFORM_NAME,
            uri: window.location.origin,
            version: this.PLATFORM_VERSION,
            url: `${this.LOCATION}${this.THEME ? `_${this.THEME}` : ''}`,
          },
          "details": details
        }),
      }
    )
      .catch((error) => console.error('Super Token metrics error: ', error));
  }

  /**
   * Wait for the MeliData CDN client to be loaded before resolving.
   *
   * @see MPCustomEventDispatcher.waitForMelidata in assets/js/checkouts/mp-checkout-error-dispatcher.js
   *
   * NOTE: This is a mirror of the canonical implementation. The SuperToken bundle is
   * built and served separately from MeLi's CDN (`http2.mlstatic.com`), so the
   * `MPCustomEventDispatcher` global from the plugin bundle is not available here.
   * Keep these two implementations in lock-step.
   *
   * @returns {Promise<void>} Resolves when MeliData is ready (or never throws).
   */
  waitForMelidata_() {
    return new Promise((resolve) => {
      if (window.melidata) {
        resolve();
        return;
      }

      if (window.melidataReady && typeof window.melidataReady.then === 'function') {
        window.melidataReady.then(resolve).catch(resolve);
        return;
      }

      if (window.melidataReady) {
        resolve();
        return;
      }

      if (document.readyState === 'complete') {
        resolve();
        return;
      }

      window.addEventListener('load', () => {
        if (window.melidataReady && typeof window.melidataReady.then === 'function') {
          window.melidataReady.then(resolve).catch(resolve);
        } else {
          resolve();
        }
      }, { once: true });
    });
  }

  dispatchMelidataErrorEvent(errorMessage, errorOrigin) {
    const cleanMessage = errorMessage?.replace(/^\[mercado pago\]:\s*/i, '').trim() || errorMessage;
    const fullOrigin = `${errorOrigin}_mercado_pago`;
    const dispatch = () => {
      document.dispatchEvent(
        new CustomEvent(this.MELIDATA_ERROR_EVENT_NAME, { detail: { message: cleanMessage, errorOrigin: fullOrigin } })
      );
    };

    let timeoutId;
    const ready = this.waitForMelidata_();
    const timeout = new Promise((resolve) => {
      timeoutId = setTimeout(() => {
        this.sendMetric(this.MELIDATA_LOAD_TIMEOUT_METRIC, 'true', cleanMessage);
        resolve();
      }, this.MELIDATA_TIMEOUT_MS);
    });

    Promise.race([ready, timeout]).then(() => {
      clearTimeout(timeoutId);
      dispatch();
    }).catch(() => {
      // best-effort — dispatch failure must not impact checkout
    });
  }

  normalizeErrorMessage(error) {
    if (!error) return "Unknown error";

    const errorMessage = error?.message || (typeof error === 'string' ? error : JSON.stringify(error));
    const normalizedErrorMessage = errorMessage?.includes('email') ? 'invalid_email_address_provided' : errorMessage;

    return normalizedErrorMessage || "Unknown error";
  }

  canUseSuperToken(canUseSuperToken, error = null) {
    const errorMessage = canUseSuperToken ? "" : this.normalizeErrorMessage(error);

    this.sendMetric('can_use_super_token', canUseSuperToken, errorMessage);
  }

  errorToAuthorizePayment(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_authorize_payment', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  errorToGetSimplifiedAuth(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);
    this.sendMetric('error_to_get_simplified_auth', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  errorToGetFastPaymentToken(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);
    this.sendMetric('error_to_get_fast_payment_token', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  errorToBuildAuthenticator(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);

    this.sendMetric('error_to_build_authenticator', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  errorToMountCVVField(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'unknown', errorMessage, error?.errorCode || 'unknown');
  }

  errorToUpdateSecurityCode(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_update_security_code', paymentMethod?.token || 'Unknown payment method token', errorMessage, error?.errorCode || 'unknown');
  }

  updateSecurityCodeGetCardIdSuccess() {
    this.sendMetric('update_security_code_get_card_id_success', 'true', '');
  }

  updateSecurityCodeCardTokenCreated() {
    this.sendMetric('update_security_code_card_token_created', 'true', '');
  }

  updateSecurityCodePseudotokenUpdated() {
    this.sendMetric('update_security_code_pseudotoken_updated', 'true', '');
  }

  updateSecurityCodeSuccess() {
    this.sendMetric('update_security_code_success', 'true', '');
  }

  errorOnSubmit(errorCode, error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);

    this.sendMetric('error_on_submit_super_token', errorCode, errorMessage);
  }

  errorToGetAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);

    this.sendMetric('error_to_get_account_payment_methods', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  registerClickOnPlaceOrderButton() {
    this.sendMetric('super_token_click_on_place_order_button', 'true', "");
  }

  registerAuthorizedPseudotoken(pseudotoken, authorizedPseudotokenInputExists) {
    this.sendMetric('authorized_pseudotoken', pseudotoken, `input_exists:${authorizedPseudotokenInputExists ? "true" : "false"}`);
  }

  errorToRenderAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.sendMetric('error_to_render_account_payment_methods', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  hasEscNotExists(paymentMethodIdentifier) {
    this.sendMetric('has_esc_not_exists', paymentMethodIdentifier || 'UNKNOWN_PAYMENT_METHOD', 'has_esc attribute not found in payment method');
  }

  getPaymentMethodFail(error, currentPaymentMethodIdentifier) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
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
    this.sendMetric('is_not_simplified_auth', 'true', '');
  }

  cannotGetFastPaymentToken() {
    this.sendMetric('cannot_get_fast_payment_token', 'true', '');
  }

  // Usage metrics: payment method selection, credits contract render, credits info modal
  registerSelectPaymentMethod(paymentMethodType) {
    const value = `super_token_${paymentMethodType}`;
    this.sendMetric('select_payment_method', value, '');
  }

  renderCreditsContract(success, error = null) {
    const errorMessage = success ? '' : this.normalizeErrorMessage(error);
    if (!success) {
      this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    }
    this.sendMetric('render_credits_contract', success, errorMessage);
  }

  installmentsFilled(paymentMethodType) {
    this.sendMetric('super_token_installments_filled', true, paymentMethodType);
  }

  renderConsumerCreditsDetailsInnerHTML(success) {
    if (!success) {
      this.dispatchMelidataErrorEvent('render_consumer_credits_details_inner_html_failed', this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
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
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_update_credits_contract', 'true', errorMessage, error?.errorCode || 'unknown');
  }

  errorToSubmitWithoutInstallmentSelected(paymentMethodType = '') {
    this.dispatchMelidataErrorEvent('no_installment_selected', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_submit_without_installment_selected', 'true', paymentMethodType);
  }

  async sendStaleCacheMetrics() {
    const SESSION_KEY = 'mp_js_cache_age_checked';
    const ONE_DAY_MS = 86400000;

    const lastChecked = parseInt(localStorage.getItem(SESSION_KEY) || '0', 10);
    if (Date.now() - lastChecked < ONE_DAY_MS) return;

    localStorage.setItem(SESSION_KEY, String(Date.now()));

    const basePath = wc_mercadopago_supertoken_bundle_params?.plugin_js_base_url
      || '/wp-content/plugins/woocommerce-mercadopago/assets/js/';
    const files = [
      'checkouts/custom/entities/card-form.min.js',
      'checkouts/custom/entities/event-handler.min.js',
      'melidata/melidata-client.min.js',
      'checkouts/super-token-loader.min.js'
    ];

    await Promise.all(files.map(async (file) => {
      try {
        let response = await fetch(basePath + file, {
          method: 'HEAD',
          cache: 'no-store'
        });

        if (response.status === 405) {
          response = await fetch(basePath + file, {
            method: 'GET',
            cache: 'no-store',
            headers: { 'Range': 'bytes=0-0' }
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

        this.sendMetric(
          'mp_js_cache_age',
          String(ageDays),
          `file : ${fileName} age_days : ${ageDays} last_modified : ${lastModifiedDate}`
        );
      } catch {
        // Silence errors — must not impact checkout
      }
    }));
  }
}

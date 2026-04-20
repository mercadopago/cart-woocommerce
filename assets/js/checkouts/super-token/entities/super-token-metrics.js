/* globals wc_mercadopago_supertoken_metrics_params */
/* eslint-disable no-unused-vars */
class MPSuperTokenMetrics {
  PLATFORM_NAME = 'woocommerce';
  CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
  MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';

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

  sendMetric(metricName, value, message) {
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
            uri: window.location.href,
            version: this.PLATFORM_VERSION,
            url: `${this.LOCATION}${this.THEME ? `_${this.THEME}` : ''}`,
          },
          "details": {
            site_id: this.SITE_ID,
            environment: this.getEnvironment(),
            sdk_instance_id: this.getSdkInstanceId(),
            cust_id: this.CUST_ID,
            js_version: this.SUPER_TOKEN_JS_VERSION,
          }
        }),
      }
    )
      .catch((error) => console.error('Super Token metrics error: ', error));
  }

  dispatchMelidataErrorEvent(errorMessage, errorOrigin) {
    const cleanMessage = errorMessage?.replace(/^\[mercado pago\]:\s*/i, '').trim() || errorMessage;
    const fullOrigin = `${errorOrigin}_mercado_pago`;
    const dispatch = () => {
      document.dispatchEvent(
        new CustomEvent(this.MELIDATA_ERROR_EVENT_NAME, { detail: { message: cleanMessage, errorOrigin: fullOrigin } })
      );
    };

    if (window.melidata || document.readyState === 'complete') {
      dispatch();
      return;
    }

    window.addEventListener('load', () => dispatch(), { once: true });
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
    this.sendMetric('error_to_authorize_payment', 'true', errorMessage);
  }

  errorToGetSimplifiedAuth(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);
    this.sendMetric('error_to_get_simplified_auth', 'true', errorMessage);
  }

  errorToGetFastPaymentToken(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);
    this.sendMetric('error_to_get_fast_payment_token', 'true', errorMessage);
  }

  errorToBuildAuthenticator(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);

    this.sendMetric('error_to_build_authenticator', 'true', errorMessage);
  }

  errorToMountCVVField(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'unknown', errorMessage);
  }

  errorToUpdateSecurityCode(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_update_security_code', paymentMethod?.token || 'Unknown payment method token', errorMessage);
  }

  errorOnSubmit(errorCode, error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);

    this.sendMetric('error_on_submit_super_token', errorCode, errorMessage);
  }

  errorToGetAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);

    this.sendMetric('error_to_get_account_payment_methods', 'true', errorMessage);
  }

  registerClickOnPlaceOrderButton() {
    this.sendMetric('super_token_click_on_place_order_button', 'true', "");
  }

  registerAuthorizedPseudotoken(pseudotoken, authorizedPseudotokenInputExists) {
    this.sendMetric('authorized_pseudotoken', pseudotoken, `input_exists:${authorizedPseudotokenInputExists ? "true" : "false"}`);
  }

  errorToRenderAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.sendMetric('error_to_render_account_payment_methods', 'true', errorMessage);
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
    this.sendMetric('error_to_update_credits_contract', 'true', errorMessage);
  }

  errorToSubmitWithoutInstallmentSelected() {
    this.dispatchMelidataErrorEvent('no_installment_selected', this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_submit_without_installment_selected', 'true', '');
  }
}

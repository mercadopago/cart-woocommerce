/* globals wc_mercadopago_supertoken_metrics_params, MPSuperTokenSkippableErrorMessages */
/* eslint-disable no-unused-vars */
class MPSuperTokenMetrics {
  PLATFORM_NAME = 'woocommerce';
  CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
  MELIDATA_ERROR_EVENT_NAME = 'mp_checkout_error';
  PLUGIN_VERSION = wc_mercadopago_supertoken_metrics_params.plugin_version;
  PLATFORM_VERSION = wc_mercadopago_supertoken_metrics_params.platform_version;
  SITE_ID = wc_mercadopago_supertoken_metrics_params.site_id;
  CUST_ID = wc_mercadopago_supertoken_metrics_params.cust_id;
  LOCATION = wc_mercadopago_supertoken_metrics_params.location;

  CUSTOM_CHECKOUT_STEPS = {
    LOAD_SUPER_TOKEN: 'load_super_token',
    SELECT_PAYMENT_METHOD: 'select_payment_method',
    POST_SUBMIT: 'post_submit',
  }

  // Dependencies
  mpSdkInstance = null;

  constructor(mpSdkInstance) {
    this.mpSdkInstance = mpSdkInstance;
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
          }
        }),
      }
    )
      .catch((error) => console.error('Super Token metrics error: ', error));
  }

  dispatchMelidataErrorEvent(errorMessage, errorOrigin) {
    document.dispatchEvent(
      new CustomEvent(this.MELIDATA_ERROR_EVENT_NAME, { detail: { message: errorMessage, errorOrigin: errorOrigin } })
    );
  }

  normalizeErrorMessage(error) {
    if (!error) return "Unknown error";

    const errorMessage = error?.message || `${JSON.stringify(error)}`;
    const normalizedErrorMessage = errorMessage?.includes('invalid_email_address_provided') ? 'invalid_email_address_provided' : errorMessage;

    return normalizedErrorMessage || "Unknown error";
  }

  canUseSuperToken(canUseSuperToken, error = null) {
    const errorMessage = this.normalizeErrorMessage(error);

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

    if (this.shouldSkipError(errorMessage)) {
      return;
    }

    this.sendMetric('error_to_build_authenticator', 'true', errorMessage);
  }

  errorToMountCVVField(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD);
    this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'Unknown payment method', errorMessage);
  }

  errorToUpdateSecurityCode(error, paymentMethod) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);
    this.sendMetric('error_to_update_security_code', paymentMethod?.token || 'Unknown payment method token', errorMessage);
  }

  errorOnSubmit(errorCode, error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT);

    if (this.shouldSkipError(errorMessage)) {
      return;
    }

    this.sendMetric('error_on_submit_super_token', errorCode, errorMessage);
  }

  /**
   * Check if error is an expected error that should not be reported
   * @param {string} errorMessage - The error message to check
   * @returns {boolean} - True if error should be skipped, false otherwise
   */
  shouldSkipError(errorMessage) {
    if (!errorMessage) return false;

    const errorMessageString = typeof errorMessage !== 'string' ? `${errorMessage}` : errorMessage;
    const normalizedMessage = errorMessageString
      .replace(/\[mercado pago\]:\s*/gi, '')
      .trim()
      .toLowerCase();

    return MPSuperTokenSkippableErrorMessages.some(message => {
      if (message instanceof RegExp) {
        return message.test(normalizedMessage);
      }
      return normalizedMessage.includes(message);
    });
  }

  errorToGetAccountPaymentMethods(error) {
    const errorMessage = this.normalizeErrorMessage(error);

    this.dispatchMelidataErrorEvent(errorMessage, this.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN);

    if (this.shouldSkipError(errorMessage)) {
      return;
    }

    this.sendMetric('error_to_get_account_payment_methods', 'true', errorMessage);
  }

  registerClickOnPlaceOrderButton() {
    this.sendMetric('super_token_click_on_place_order_button', 'true', "");
  }

  registerAuthorizedPseudotoken(pseudotoken, authorizedPseudotokenInputExists) {
    this.sendMetric('authorized_pseudotoken', pseudotoken, `input_exists:${authorizedPseudotokenInputExists ? "true" : "false"}`);
  }
}

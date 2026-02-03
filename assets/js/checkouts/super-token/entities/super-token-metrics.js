/* globals wc_mercadopago_supertoken_metrics_params, MPSuperTokenSkippableErrorMessages */
/* eslint-disable no-unused-vars */
class MPSuperTokenMetrics {
    PLATFORM_NAME = 'woocommerce';
    CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
    PLUGIN_VERSION = wc_mercadopago_supertoken_metrics_params.plugin_version;
    PLATFORM_VERSION = wc_mercadopago_supertoken_metrics_params.platform_version;
    SITE_ID = wc_mercadopago_supertoken_metrics_params.site_id;
    CUST_ID = wc_mercadopago_supertoken_metrics_params.cust_id;
    LOCATION = wc_mercadopago_supertoken_metrics_params.location;

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

    canUseSuperToken(canUseSuperToken, error = null) {
        const errorMessage = error ? error?.message || 'Unknown error' : null;

        this.sendMetric('can_use_super_token', canUseSuperToken, errorMessage);
    }

    errorToAuthorizePayment(error) {
        this.sendMetric('error_to_authorize_payment', 'true', error?.message || 'Unknown error');
    }

    errorToGetSimplifiedAuth(error) {
        this.sendMetric('error_to_get_simplified_auth', 'true', error?.message || 'Unknown error');
    }

    errorToGetFastPaymentToken(error) {
        this.sendMetric('error_to_get_fast_payment_token', 'true', error?.message || 'Unknown error');
    }

    errorToBuildAuthenticator(error) {
        const errorMessage = error?.message || 'Unknown error';

        if (this.shouldSkipError(errorMessage)) {
            return;
        }

        this.sendMetric('error_to_build_authenticator', 'true', errorMessage);
    }

    errorToMountCVVField(error, paymentMethod) {
        this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'Unknown payment method', error?.message || 'Unknown error');
    }

    errorToUpdateSecurityCode(error, paymentMethod) {
        this.sendMetric('error_to_update_security_code', paymentMethod?.id || 'Unknown payment method', error?.message || 'Unknown error');
    }

    errorOnSubmit(errorCode, errorMessage) {
        if (this.shouldSkipError(errorMessage)) {
            return;
        }

        this.sendMetric('error_on_submit_super_token', errorCode, errorMessage || 'Unknown error');
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
        const errorMessage = error?.message || 'Unknown error';

        if (this.shouldSkipError(errorMessage)) {
            return;
        }

        this.sendMetric('error_to_get_account_payment_methods', 'true', errorMessage);
    }

    registerClickOnPlaceOrderButton() {
      this.sendMetric('super_token_click_on_place_order_button', 'true', "");
    }
}

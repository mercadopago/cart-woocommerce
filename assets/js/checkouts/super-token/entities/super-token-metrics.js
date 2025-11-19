/* globals wc_mercadopago_supertoken_metrics_params */
/* eslint-disable no-unused-vars */
class MPSuperTokenMetrics {
    PLATFORM_NAME = 'woocommerce';
    CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
    PLUGIN_VERSION = wc_mercadopago_supertoken_metrics_params.plugin_version;
    PLATFORM_VERSION = wc_mercadopago_supertoken_metrics_params.platform_version;
    SITE_ID = wc_mercadopago_supertoken_metrics_params.site_id;
    CUST_ID = wc_mercadopago_supertoken_metrics_params.cust_id;
    LOCATION = wc_mercadopago_supertoken_metrics_params.location;
    EMPTY_PRELOADED_PAYMENT_METHODS = 'EMPTY_PRELOADED_PAYMENT_METHODS';
    AUTHENTICATOR_FLOW_NOT_SUPPORTED = 'Authenticator flow is not supported';
    SITE_ID_NOT_SUPPORTED_PATTERN = /the\s+site\s+id\s+\w+\s+is\s+not\s+supported/i;

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

    errorToShowAuthenticator(error) {
        this.sendMetric('error_to_show_authenticator', 'true', error?.message || 'Unknown error');
    }

    errorToRenderAccountPaymentMethods(error) {
        this.sendMetric('error_to_render_account_payment_methods', 'true', error?.message || 'Unknown error');
    }

    errorToMountCVVField(error, paymentMethod) {
        this.sendMetric('error_to_mount_cvv_field', paymentMethod?.id || 'Unknown payment method', error?.message || 'Unknown error');
    }

    errorToUpdateSecurityCode(error, paymentMethod) {
        this.sendMetric('error_to_update_security_code', paymentMethod?.id || 'Unknown payment method', error?.message || 'Unknown error');
    }

    /**
     * Check if error is an expected error that should not be reported
     * @param {string} errorMessage - The error message to check
     * @returns {boolean} - True if error should be skipped, false otherwise
     */
    shouldSkipError(errorMessage) {
        if (!errorMessage) {
            return false;
        }

        if (errorMessage === this.EMPTY_PRELOADED_PAYMENT_METHODS) {
            return true;
        }

        const errorMessageLower = errorMessage.toLowerCase();
        if (errorMessageLower.includes(this.AUTHENTICATOR_FLOW_NOT_SUPPORTED.toLowerCase())) {
            return true;
        }

        // Pattern: "The site id XXX is not supported" (where XXX can be any site_id like mco, mlc, etc.)
        if (this.SITE_ID_NOT_SUPPORTED_PATTERN.test(errorMessage)) {
            return true;
        }

        return false;
    }

    errorToGetPreloadedPaymentMethods(error) {
        const errorMessage = error?.message || 'Unknown error';

        if (this.shouldSkipError(errorMessage)) {
            return;
        }

        this.sendMetric('error_to_get_preloaded_payment_methods', 'true', errorMessage);
    }
}

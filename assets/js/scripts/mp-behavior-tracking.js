/* globals wc_mercadopago_woocommerce_scripts_params */
(function () {
    /**
     * Send metric to Mercado Pago monitoring API
     * @param {string} metricName - The metric name/endpoint
     * @param {Object} payload - The payload to send
     */
    function sendMetric(metricName, payload) {
        const url = `https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/${metricName}`;

        navigator.sendBeacon(url, JSON.stringify(payload));
    }

    /**
     * Build the base payload for metrics
     * @param {string} value - Error value/name
     * @param {string} message - Error message
     * @returns {Object} - The payload object
     */
    function buildMetricPayload(value, message) {
        return {
            value: value,
            message: message,
            plugin_version: wc_mercadopago_woocommerce_scripts_params.plugin_version,
            platform: {
                name: 'woocommerce',
                uri: wc_mercadopago_woocommerce_scripts_params.theme,
                version: wc_mercadopago_woocommerce_scripts_params.platform_version,
                url: window.location.href,
            details: {
                site_id: wc_mercadopago_woocommerce_scripts_params.site_id || '',
                environment: 'prod',
                cust_id: wc_mercadopago_woocommerce_scripts_params.cust_id || '',
            }
          }
        };
      }

    try {
        const scriptTag = document.createElement('script');

        scriptTag.setAttribute('id', 'mp_behavior_tracking');
        scriptTag.src = 'https://http2.mlstatic.com/storage/v1/mercadopago/woocommerce/scripts/behavior-tracking.min.js';
        scriptTag.defer = true;

        scriptTag.onerror = function () {
            const payload = buildMetricPayload(
                'true',
                'Unable to load mp behavior tracking on page'
            );

            sendMetric('failed_to_load_mp_behavior_tracking', payload);
        };

        document.head.appendChild(scriptTag);
    } catch (e) {
        const payload = buildMetricPayload(
            null,
            'Failed to load mp behavior tracking script on page'
        );

        sendMetric('failed_to_load_mp_behavior_tracking', payload);
    }
})();

/* globals wc_mercadopago_woocommerce_scripts_params */
(function () {
    const SUPER_TOKEN_BUNDLE_ENV = 'v1';
    const REMOTE_SUPER_TOKEN_BASE_URL = `https://http2.mlstatic.com/storage/${SUPER_TOKEN_BUNDLE_ENV}/mercadopago/woocommerce/scripts/v1`;
    const REMOTE_SUPER_TOKEN_CSS_URL = `${REMOTE_SUPER_TOKEN_BASE_URL}/super-token.bundle.min.css`;
    const REMOTE_SUPER_TOKEN_JS_URL = `${REMOTE_SUPER_TOKEN_BASE_URL}/super-token.bundle.min.js`;
    const SUPER_TOKEN_CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
    const SUPER_TOKEN_JS_ID = 'wc_mercadopago_supertoken_bundle_js';
    const CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';
    const METRIC_LOAD_SUPER_TOKEN_BUNDLE_CSS = 'load_super_token_bundle_css';
    const METRIC_LOAD_SUPER_TOKEN_BUNDLE_JS = 'load_super_token_bundle_js';
    const METRIC_LOAD_SUPER_TOKEN_BUNDLE = 'load_super_token_bundle';
    const METRIC_STATUS_SUCCESS = 'true';
    const METRIC_STATUS_FAILURE = 'false';

    function getWooCommerceScriptsParams() {
        if (typeof wc_mercadopago_woocommerce_scripts_params === 'undefined') {
            return {};
        }

        return wc_mercadopago_woocommerce_scripts_params;
    }

    function sendMetric(metricName, payload) {
        const url = `${CORE_MONITOR_URL}/${metricName}`;
        const body = JSON.stringify(payload);

        try {
            if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
                navigator.sendBeacon(url, body);
                return;
            }

            if (typeof fetch === 'function') {
                fetch(url, {
                    method: 'POST',
                    body: body,
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true,
                });
                return;
            }
        } catch (error) {
            // Intentionally swallow telemetry errors to avoid breaking checkout flow.
        }
    }

    function buildMetricPayload(value, message) {
        const scriptsParams = getWooCommerceScriptsParams();

        return {
            value: value,
            message: message,
            plugin_version: scriptsParams.plugin_version || '',
            platform: {
                name: 'woocommerce',
                uri: scriptsParams.theme || '',
                version: scriptsParams.platform_version || '',
                url: window.location.href,
            },
            details: {
                site_id: scriptsParams.site_id || '',
                environment: 'prod',
                cust_id: scriptsParams.cust_id || '',
            },
        };
    }

    function trackMetric(metricName, value, message) {
        const payload = buildMetricPayload(value, message);
        sendMetric(metricName, payload);
    }

    function setAssetAttributes(element, attributes) {
        Object.keys(attributes).forEach(function (key) {
            element[key] = attributes[key];
        });
    }

    function loadAsset(config) {
        if (document.getElementById(config.id)) {
            return;
        }

        const assetTag = document.createElement(config.tagName);
        assetTag.setAttribute('id', config.id);
        setAssetAttributes(assetTag, config.attributes);

        assetTag.onerror = function () {
            trackMetric(config.metricName, METRIC_STATUS_FAILURE, config.errorMessage);
        };

        assetTag.onload = function () {
            trackMetric(config.metricName, METRIC_STATUS_SUCCESS, config.successMessage);
        };

        document.head.appendChild(assetTag);
    }

    function loadSuperTokenCss() {
        loadAsset({
            id: SUPER_TOKEN_CSS_ID,
            tagName: 'link',
            attributes: {
                rel: 'stylesheet',
                href: REMOTE_SUPER_TOKEN_CSS_URL,
                media: 'all',
            },
            metricName: METRIC_LOAD_SUPER_TOKEN_BUNDLE_CSS,
            successMessage: 'Super token bundle css loaded successfully',
            errorMessage: 'Unable to load super token bundle css on page',
        });
    }

    function loadSuperTokenJs() {
        loadAsset({
            id: SUPER_TOKEN_JS_ID,
            tagName: 'script',
            attributes: {
                src: REMOTE_SUPER_TOKEN_JS_URL,
                defer: true,
            },
            metricName: METRIC_LOAD_SUPER_TOKEN_BUNDLE_JS,
            successMessage: 'Super token bundle js loaded successfully',
            errorMessage: 'Unable to load super token bundle js on page',
        });
    }

    try {
        loadSuperTokenCss();
        loadSuperTokenJs();
    } catch (error) {
        const errorMessage = error && error.message ? error.message : 'Unknown error';
        trackMetric(METRIC_LOAD_SUPER_TOKEN_BUNDLE, METRIC_STATUS_FAILURE, errorMessage);
    }
})();

/* globals wc_mercadopago_woocommerce_scripts_params */
(function () {
    const SUPER_TOKEN_BUNDLE_ENV = 'v1'; // 'v1' = prod | 'homol' = homologação
    const SUPER_TOKEN_STORAGE_BASE_URL = `https://http2.mlstatic.com/storage/${SUPER_TOKEN_BUNDLE_ENV}/mercadopago/woocommerce/scripts`;
    const SUPER_TOKEN_AB_CONFIG_URL = `${SUPER_TOKEN_STORAGE_BASE_URL}/config/super-token-variants.js`;

    const SUPER_TOKEN_FALLBACK_VARIANT = 'v2';
    const SUPER_TOKEN_VARIANT_COOKIE = 'mp_st_variant';
    // Fallback TTL — authoritative value comes from cookie_ttl_days in the CDN config JSON,
    // defined by the Product team. Applied when cookie_ttl_days is missing, zero, or negative.
    const SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS = 30;
    const SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS = 3000;
    const SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS = 2 / 24; // 2h expressed as a fraction of a day

    const SUPER_TOKEN_VARIANT_FOLDER = { 'v2': 'v1', 'v2.1': 'v2.1' };
    const SUPER_TOKEN_ALLOWED_VARIANTS = { 'v2': true, 'v2.1': true };

    const SUPER_TOKEN_CSS_ID = 'wc_mercadopago_supertoken_bundle_css';
    const SUPER_TOKEN_JS_ID = 'wc_mercadopago_supertoken_bundle_js';
    const CORE_MONITOR_URL = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big';

    const METRIC_LOAD_SUPER_TOKEN_BUNDLE_CSS = 'load_super_token_bundle_css';
    const METRIC_LOAD_SUPER_TOKEN_BUNDLE_JS = 'load_super_token_bundle_js';
    const METRIC_LOAD_SUPER_TOKEN_BUNDLE = 'load_super_token_bundle';
    const METRIC_FETCH_AB_CONFIG = 'fetch_ab_config';
    const METRIC_FETCH_AB_CONFIG_TIME = 'fetch_ab_config_loading_time';
    const METRIC_SUPER_TOKEN_AB_VARIANT = 'super_token_ab_variant';
    const METRIC_STATUS_SUCCESS = 'true';
    const METRIC_STATUS_FAILURE = 'false';

    const SUPER_TOKEN_BUNDLE_CSS_FILENAME = '/super-token.bundle.min.css';
    const SUPER_TOKEN_BUNDLE_JS_FILENAME  = '/super-token.bundle.min.js';

    const MILLISECONDS_PER_DAY = 864e5;

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
            value: `${value}`,
            message: `${message}`,
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

    function getSuperTokenVariantCookie() {
        const cookiePattern = new RegExp('(^|;\\s*)' + SUPER_TOKEN_VARIANT_COOKIE + '=([^;]+)');
        const cookieMatch = document.cookie.match(cookiePattern);
        return cookieMatch ? cookieMatch[2] : null;
    }

    function setSuperTokenVariantCookie(variantValue, ttlInDays) {
        try {
            const expiration = new Date(Date.now() + ttlInDays * MILLISECONDS_PER_DAY).toUTCString();
            document.cookie = SUPER_TOKEN_VARIANT_COOKIE + '=' + variantValue
                + ';expires=' + expiration + ';path=/;SameSite=Lax;Secure';
        } catch (_) {
            // Intentionally swallow cookie errors to avoid breaking checkout flow.
        }
    }

    function clearSuperTokenVariantCookie() {
        document.cookie = SUPER_TOKEN_VARIANT_COOKIE
            + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax;Secure';
    }

    function selectVariantByWeight(variants) {
        const variantNames = Object.keys(variants);
        const totalWeight = variantNames.reduce(function (weightSum, variantName) {
            return weightSum + (variants[variantName].weight || 0);
        }, 0);

        if (totalWeight <= 0) {
            return SUPER_TOKEN_FALLBACK_VARIANT;
        }

        let randomPoint = Math.random() * totalWeight;
        let accumulatedWeight = 0;

        for (let i = 0; i < variantNames.length; i++) {
            accumulatedWeight += variants[variantNames[i]].weight || 0;
            if (randomPoint < accumulatedWeight) {
                return variantNames[i];
            }
        }

        return variantNames[0];
    }

    function getVariantBaseUrl(variant) {
        const allowedVariant = SUPER_TOKEN_ALLOWED_VARIANTS[variant] ? variant : SUPER_TOKEN_FALLBACK_VARIANT;
        const cdnFolder = SUPER_TOKEN_VARIANT_FOLDER[allowedVariant];
        return SUPER_TOKEN_STORAGE_BASE_URL + '/' + cdnFolder;
    }

    function loadSuperTokenCss(baseUrl) {
        loadAsset({
            id: SUPER_TOKEN_CSS_ID,
            tagName: 'link',
            attributes: {
                rel: 'stylesheet',
                href: baseUrl + SUPER_TOKEN_BUNDLE_CSS_FILENAME,
                media: 'all',
            },
            metricName: METRIC_LOAD_SUPER_TOKEN_BUNDLE_CSS,
            successMessage: 'Super token bundle css loaded successfully',
            errorMessage: 'Unable to load super token bundle css on page',
        });
    }

    function loadSuperTokenJs(baseUrl) {
        loadAsset({
            id: SUPER_TOKEN_JS_ID,
            tagName: 'script',
            attributes: {
                src: baseUrl + SUPER_TOKEN_BUNDLE_JS_FILENAME,
                defer: true,
            },
            metricName: METRIC_LOAD_SUPER_TOKEN_BUNDLE_JS,
            successMessage: 'Super token bundle js loaded successfully',
            errorMessage: 'Unable to load super token bundle js on page',
        });
    }

    function loadSuperTokenBundle(variant) {
        const baseUrl = getVariantBaseUrl(variant);
        loadSuperTokenCss(baseUrl);
        loadSuperTokenJs(baseUrl);
    }

    function fetchAbConfig(configUrl, timeoutMs) {
        const fetchStartTime = Date.now();
        let hasFetchTimedOut = false;

        const fetchTimeoutPromise = new Promise(function (resolve) {
            setTimeout(function () {
                hasFetchTimedOut = true;
                resolve(null);
            }, timeoutMs);
        });

        const fetchConfigPromise = fetch(configUrl, { cache: 'no-cache' })
            .then(function (response) {
                if (!response.ok) {
                    if (!hasFetchTimedOut) {
                        trackMetric(METRIC_FETCH_AB_CONFIG, 'error', 'http:' + response.status);
                    }
                    return null;
                }
                return response.json()
                    .then(function (parsedConfig) {
                        // Guard: if timeout already fired, discard result to avoid
                        // emitting both 'timeout' and 'success' metrics for the same request.
                        if (hasFetchTimedOut) { return null; }
                        const elapsedMs = Date.now() - fetchStartTime;
                        trackMetric(METRIC_FETCH_AB_CONFIG, 'success', 'success');
                        trackMetric(METRIC_FETCH_AB_CONFIG_TIME, elapsedMs, '');
                        return parsedConfig;
                    })
                    .catch(function () {
                        if (!hasFetchTimedOut) {
                            trackMetric(METRIC_FETCH_AB_CONFIG, 'error', 'invalid_json');
                        }
                        return null;
                    });
            })
            .catch(function () {
                if (!hasFetchTimedOut) {
                    trackMetric(METRIC_FETCH_AB_CONFIG, 'error', 'network_or_cors');
                }
                return null;
            });

        return Promise.race([fetchConfigPromise, fetchTimeoutPromise]).then(function (resolvedConfig) {
            if (hasFetchTimedOut) {
                trackMetric(METRIC_FETCH_AB_CONFIG, 'timeout', 'elapsed_ms:' + (Date.now() - fetchStartTime));
            }
            return resolvedConfig;
        });
    }

    try {
        const cachedVariant = getSuperTokenVariantCookie();

        // Always fetch config so the kill switch (active:false) propagates immediately
        // to all visitors, including returning ones with a valid cookie.
        // The cookie acts as a memory of the assigned variant, not a shortcut to skip the fetch.
        fetchAbConfig(SUPER_TOKEN_AB_CONFIG_URL, SUPER_TOKEN_CONFIG_FETCH_TIMEOUT_MS).then(function (abConfig) {
            if (!abConfig) {
                // Fetch failed — use cookie as resilience fallback to preserve user experience.
                if (cachedVariant && SUPER_TOKEN_ALLOWED_VARIANTS[cachedVariant]) {
                    trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
                    loadSuperTokenBundle(cachedVariant);
                } else {
                    trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:fetch_failed');
                    setSuperTokenVariantCookie('fetch_failed', SUPER_TOKEN_FETCH_FAILED_COOKIE_TTL_DAYS);
                    loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
                }
                return;
            }

            if (typeof abConfig.active !== 'boolean') {
                // active absent, null, or wrong type — malformed config, not a kill switch
                trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
                loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
                return;
            }

            if (!abConfig.active) {
                // active === false — Kill switch: clears cookie and loads default for ALL visitors immediately.
                clearSuperTokenVariantCookie();
                const defaultVariant = SUPER_TOKEN_ALLOWED_VARIANTS[abConfig.default] ? abConfig.default : SUPER_TOKEN_FALLBACK_VARIANT;
                trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, defaultVariant, 'source:kill_switch');
                loadSuperTokenBundle(defaultVariant);
                return;
            }

            if (!abConfig.variants || typeof abConfig.variants !== 'object') {
                trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
                loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
                return;
            }

            // active:true — use existing valid cookie without re-assigning the variant.
            if (cachedVariant && SUPER_TOKEN_ALLOWED_VARIANTS[cachedVariant]) {
                trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, cachedVariant, 'source:cookie');
                loadSuperTokenBundle(cachedVariant);
                return;
            }

            // No cookie or unknown/corrupted variant — clear and assign a new one.
            if (cachedVariant) {
                clearSuperTokenVariantCookie();
            }

            const assignedVariant = selectVariantByWeight(abConfig.variants);

            if (!SUPER_TOKEN_ALLOWED_VARIANTS[assignedVariant]) {
                trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, SUPER_TOKEN_FALLBACK_VARIANT, 'source:config_invalid');
                loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
                return;
            }

            const cookieTtlDays = abConfig.cookie_ttl_days > 0
                ? abConfig.cookie_ttl_days
                : SUPER_TOKEN_VARIANT_COOKIE_DEFAULT_TTL_DAYS;

            setSuperTokenVariantCookie(assignedVariant, cookieTtlDays);
            trackMetric(METRIC_SUPER_TOKEN_AB_VARIANT, assignedVariant, 'source:assigned');
            loadSuperTokenBundle(assignedVariant);
        }).catch(function (err) {
            const errMsg = err && err.message ? err.message : 'async_error';
            trackMetric(METRIC_LOAD_SUPER_TOKEN_BUNDLE, METRIC_STATUS_FAILURE, errMsg);
            loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
        });
    } catch (error) {
        // Synchronous failure before the fetch promise exists (e.g. `fetch` unavailable in old
        // browsers/WebViews) — mirror the async .catch and load the fallback bundle for resilience.
        // This emits a load-failure metric, NOT a variant assignment, so these clients stay out of
        // the A/B analysis: the 50/50 weight only applies to randomized (source:assigned) users.
        const errorMessage = error && error.message ? error.message : 'Unknown error';
        trackMetric(METRIC_LOAD_SUPER_TOKEN_BUNDLE, METRIC_STATUS_FAILURE, errorMessage);
        loadSuperTokenBundle(SUPER_TOKEN_FALLBACK_VARIANT);
    }
})();

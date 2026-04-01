/* globals wc_mercadopago_health_monitor_params */
(function () {
    const params = wc_mercadopago_health_monitor_params;

    const TIME_TO_MELIDATA_LOAD = 3000;

    const SHORTHAND_MAP = {
        'background': [
            'background-color', 'background-image', 'background-position-x',
            'background-position-y', 'background-repeat', 'background-attachment',
            'background-origin', 'background-clip', 'background-size'
        ],
        'border': [
            'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
            'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
            'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'
        ],
        'margin': ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
        'padding': ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
        'border-radius': [
            'border-top-left-radius', 'border-top-right-radius',
            'border-bottom-right-radius', 'border-bottom-left-radius'
        ],
        'font': ['font-style', 'font-variant-caps', 'font-weight', 'font-size', 'line-height', 'font-family'],
        'flex': ['flex-grow', 'flex-shrink', 'flex-basis'],
        'outline': ['outline-color', 'outline-style', 'outline-width'],
        'overflow': ['overflow-x', 'overflow-y'],
        'gap': ['row-gap', 'column-gap'],
        'border-color': ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'],
        'border-style': ['border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style'],
        'border-width': ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width'],
    };

    /**
    * Detects CSS overrides on a plugin element and its descendants.
    * Compares "plugin-only" styles against actual rendered styles to find
    * properties being overridden by themes, other plugins, or inline styles.
    *
    * @param {string} selector - CSS selector for the root element
    * @returns {Object|null} Map of element identifiers to overridden properties, {} if none, null if not found
    */
    function elementsWithCustomizations(selector) {
        const root = document.querySelector(selector);
        if (!root) return null;

        const elements = [root, ...root.querySelectorAll('*')];

        const MP_HREF_PATTERN = 'woocommerce-mercadopago';
        const MP_ID_PATTERNS = ['mercadopago', 'mercado-pago'];

        function isPluginSheet(sheet) {
            if (sheet.href && sheet.href.includes(MP_HREF_PATTERN)) return true;
            const nodeId = sheet.ownerNode?.id || '';
            return MP_ID_PATTERNS.some(p => nodeId.includes(p));
        }

        function* iterateRules(ruleList) {
            for (const rule of ruleList) {
                if (rule.cssRules) yield* iterateRules(rule.cssRules);
                if (rule.selectorText && rule.style) yield rule;
            }
        }

        function toLonghands(prop) {
            return SHORTHAND_MAP[prop] || [prop];
        }

        // --- Step 1: Collect properties the plugin explicitly sets per element ---

        const pluginPropsPerElement = new Map();

        for (const sheet of document.styleSheets) {
            if (!isPluginSheet(sheet)) continue;
            let rules;
            try { rules = sheet.cssRules || sheet.rules; } catch { continue; }

            for (const rule of iterateRules(rules)) {
                for (const el of elements) {
                    try { if (!el.matches(rule.selectorText)) continue; } catch { continue; }

                    if (!pluginPropsPerElement.has(el)) pluginPropsPerElement.set(el, new Set());
                    const propsSet = pluginPropsPerElement.get(el);
                    for (const prop of rule.style) {
                        for (const lh of toLonghands(prop)) propsSet.add(lh);
                    }
                }
            }
        }

        // --- Step 2: Freeze transitions/animations on all elements ---

        const savedTransitions = new Map();
        elements.forEach(el => {
            savedTransitions.set(el, {
                transition: el.style.getPropertyValue('transition'),
                transitionPriority: el.style.getPropertyPriority('transition'),
                animation: el.style.getPropertyValue('animation'),
                animationPriority: el.style.getPropertyPriority('animation'),
            });
            el.style.setProperty('transition', 'none', 'important');
            el.style.setProperty('animation', 'none', 'important');
        });

        // --- Step 3: Save and clear inline styles + disable external sheets ---

        const externalSheets = [...document.styleSheets].filter(s => !isPluginSheet(s));
        const savedInline = new Map();

        elements.forEach(el => {
            const css = el.style.cssText;
            // Keep only transition:none/animation:none that we just set
            const freezeOnly = 'transition: none !important; animation: none !important;';
            if (css && css !== freezeOnly) {
                savedInline.set(el, css);
                el.style.cssText = freezeOnly;
            }
        });

        externalSheets.forEach(s => { s.disabled = true; });

        // --- Step 4: Capture plugin-only computed values ---

        const pluginValues = new Map();
        pluginPropsPerElement.forEach((props, el) => {
            const cs = window.getComputedStyle(el);
            const values = {};
            for (const prop of props) {
                values[prop] = cs.getPropertyValue(prop).trim();
            }
            pluginValues.set(el, values);
        });

        // --- Step 5: Restore external sheets + inline styles ---

        externalSheets.forEach(s => { s.disabled = false; });

        savedInline.forEach((css, el) => {
            el.style.cssText = css;
            el.style.setProperty('transition', 'none', 'important');
            el.style.setProperty('animation', 'none', 'important');
        });

        // --- Step 6: Compare with actual rendered values ---

        const result = {};
        pluginValues.forEach((expected, el) => {
            const cs = window.getComputedStyle(el);
            const overridden = {};

            for (const [prop, expectedVal] of Object.entries(expected)) {
                const actualVal = cs.getPropertyValue(prop).trim();
                if (actualVal !== expectedVal) {
                    overridden[prop] = { expected: expectedVal, actual: actualVal };
                }
            }

            if (Object.keys(overridden).length > 0) {
                result[elementIdentifier(el)] = overridden;
            }
        });

        // --- Step 7: Restore transitions/animations ---

        savedTransitions.forEach(({ transition, transitionPriority, animation, animationPriority }, el) => {
            if (transition) {
                el.style.setProperty('transition', transition, transitionPriority);
            } else {
                el.style.removeProperty('transition');
            }
            if (animation) {
                el.style.setProperty('animation', animation, animationPriority);
            } else {
                el.style.removeProperty('animation');
            }
        });

        // Clean up: if an element had no inline style before, remove the style attribute entirely
        elements.forEach(el => {
            if (!savedInline.has(el) && el.style.length === 0) {
                el.removeAttribute('style');
            }
        });

        return result;
    }

    function elementIdentifier(el) {
        if (el.id) return `#${el.id}`;
        const mpClass = [...el.classList].find(c => c.startsWith('mp-'));
        if (mpClass) return `.${mpClass}`;
        if (el.classList[0]) return `.${el.classList[0]}`;
        const parent = el.parentElement;
        const idx = parent ? [...parent.children].indexOf(el) : 0;
        return `${el.tagName.toLowerCase()}:nth-child(${idx + 1})`;
    }

    /**
     * Send metric to Mercado Pago monitoring API
     *
     * @param {string} metricName
     * @param {Object} payload
     */
    function sendMetric(metricName, payload) {
        const url = 'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/' + metricName;
        navigator.sendBeacon(url, JSON.stringify(payload));
    }

    /**
     * Build base metric payload following the same structure as mp-behavior-tracking.js
     *
     * @param {string} value
     * @param {string} message
     * @returns {Object}
     */
    function buildPayload(value, message) {
        return {
            value: value,
            message: message,
            plugin_version: params.plugin_version,
            platform: {
                name: 'woocommerce',
                uri: params.theme,
                version: params.platform_version,
                url: window.location.href,
            },
            details: {
                site_id: params.site_id || 'not_available',
                cust_id: params.cust_id || 'not_available',
                environment: params.is_test ? 'homol' : 'prod',
                sdk_instance_id: window.sessionStorage.getItem('_mp_flow_id') || 'not_available',
            },
        };
    }

    /**
     * Detect CSS conflicts on critical checkout elements.
     * Only elements present in the DOM are checked — avoids false positives
     * for gateways that are not active on the current page.
     * Rate-limited to one metric per browser session via sessionStorage.
     */
    function checkCssConflicts() {
        const SESSION_KEY = 'mp_health_css_conflict_sent';

        if (sessionStorage.getItem(SESSION_KEY)) {
            return;
        }

        const CRITICAL_ELEMENTS = [
            '.mp-wallet-button-container',
            '.mp-super-token-payment-methods-list',
        ];

        const anomalies = [];

        CRITICAL_ELEMENTS.forEach(function (item) {
            const el = document.querySelector(item);

            if (!el) {
                return;
            }

            const result = elementsWithCustomizations(item);

            if (result && Object.keys(result).length > 0) {
                anomalies.push({ selector: item, issue: 'custom_styles_detected', details: result });
            }
        });

        if (anomalies.length === 0) {
            return;
        }

        sendMetric(
            'mp_css_conflict_detected',
            buildPayload(
                'true',
                anomalies.map(a => `${a.selector} (${a.issue})`).join('; ')
            )
        );

        sessionStorage.setItem(SESSION_KEY, '1');
    }

    /**
     * Detect missing critical JS globals — indicates that a script was dequeued
     * before it could set up the expected global variables.
     * Rate-limited to one metric per browser session via sessionStorage.
     */
    function checkScriptGlobals() {
        const SESSION_KEY = 'mp_health_script_globals_sent';

        if (sessionStorage.getItem(SESSION_KEY)) {
            return;
        }

        if (!params.is_checkout) {
            return;
        }

        const EXPECTED_GLOBALS = [
            { name: 'wc_mercadopago_checkout_session_data_register_params', label: 'session_data_register' },
            { name: 'mercadopago_melidata_params', label: 'melidata_params' },
            { name: 'MelidataClient', label: 'melidata_client' },
            { name: 'melidata' , label: 'melidata_instance' },
        ];

        if (params.payment_methods && params.payment_methods.includes('woo-mercado-pago-custom')) {
            EXPECTED_GLOBALS.push(
                { name: 'wc_mercadopago_supertoken_bundle_params', label: 'supertoken_bundle' },
                { name: 'MercadoPago', label: 'mercadopago_sdk' },
            );
        }

        const missing = EXPECTED_GLOBALS
            .filter(function (g) { return typeof window[g.name] === 'undefined'; })
            .map(function (g) { return g.label; });

        if (missing.length === 0) {
            return;
        }

        sendMetric(
            'mp_script_missing_globals',
            buildPayload('true', missing.join(', '))
        );

        sessionStorage.setItem(SESSION_KEY, '1');
    }

    try {
        const scripts = () => {
            setTimeout(() => {
                try {
                    checkCssConflicts();
                    checkScriptGlobals();
                } catch (e) {
                    // Never propagate errors — health checks must not interfere with checkout
                }
            }, TIME_TO_MELIDATA_LOAD);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scripts);
        } else {
            scripts();
        }
    } catch (e) {
        // Never propagate errors — health checks must not interfere with checkout
    }
})();

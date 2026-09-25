/**
 * Resolves the server-side checkout pre-validation verdict (wc_ajax_mp_validate_checkout)
 * against the live DOM and emits the validation funnel metrics. Hosted on the Super Token CDN
 * bundle so it can be hot-fixed without a plugin release; the plugin (event-handler.js) is a thin
 * wrapper that dispatches the verdict and fails open if this function is absent or throws.
 *
 * The route only sees the serialized form.checkout body, so a required field rendered outside that
 * form (e.g. CartFlows funnel step) is reported empty even when it is visible and filled on screen.
 * The cross-check discards those false positives so the buyer is never blocked for a field that is
 * actually present and filled.
 *
 * Exposed as window.mpResolveCheckoutValidation. Wrapped in an IIFE so the helper functions never
 * leak into the concatenated Super Token bundle scope.
 */
(function () {
    const METRIC_DETAIL = 'validate_checkout_then_continue';
    const MAX_ANCESTOR_DEPTH = 20;
    const SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';

    const CHECKOUT_TYPE_LABEL = {
        ABSENT: 'absent',
        EMPTY: 'empty',
    };

    // Verdict contract shared with the plugin wrapper (event-handler.js). The action VALUES are the
    // cross-boundary protocol — both sides MUST use the same strings.
    const VALIDATION_ACTION = {
        PROCEED: 'PROCEED',
        BLOCK: 'BLOCK',
        FAIL_OPEN: 'FAIL_OPEN',
    };

    const FAIL_OPEN_REASON = {
        EMPTY_ERRORS: 'EMPTY_ERRORS',
        UNEXPECTED_ERROR: 'UNEXPECTED_ERROR',
        UNEXPECTED_RESPONSE: 'UNEXPECTED_RESPONSE',
    };

    const BLOCK_REASON = {
        EMPTY_FIELDS: 'EMPTY_FIELDS',
    };

    const VALIDATION_METRIC = {
        PASSED: 'MP_CHECKOUT_AJAX_VALIDATION_PASSED',
        BLOCKED: 'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        SKIPPED: 'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
        FALSE_POSITIVE: 'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        UNEXPECTED_ERROR: 'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_ERROR',
        UNEXPECTED_RESPONSE: 'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_RESPONSE',
    };

    function getFieldNodesByName(fieldName) {
        try {
            // WooCommerce field names follow billing_*/shipping_*/terms conventions and never
            // contain quotes or CSS-special characters, so the fallback is safe without escaping.
            const selector = window.CSS && typeof window.CSS.escape === 'function'
                ? `[name=${window.CSS.escape(fieldName)}]`
                : `[name="${fieldName}"]`;
            return document.querySelectorAll(selector);
        } catch (error) {
            return [];
        }
    }

    function isFieldVisible(field) {
        const isWcRegisteredField = field?.closest?.('.form-row')?.id?.endsWith('_field') ?? false;
        let element = field;
        let depth = 0;

        while (element && element !== document.body && depth < MAX_ANCESTOR_DEPTH) {
            const style = window.getComputedStyle(element);
            if (style.display === 'none') return false;
            if (style.opacity === '0') return false;
            if (isWcRegisteredField && style.visibility === 'hidden') return false;
            element = element.parentElement;
            depth++;
        }

        return true;
    }

    function isFieldFilled(field) {
        if (field?.type === 'checkbox' || field?.type === 'radio') {
            return !!field.checked;
        }
        return !!field?.value?.trim();
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

        const visibleNodes = Array.from(nodes).filter(isFieldVisible);
        if (!visibleNodes.length) {
            return false;
        }

        return !visibleNodes.some(isFieldFilled);
    }

    function crossCheckErrorsAgainstDom(errors) {
        const flaggedErrors = Array.isArray(errors) ? errors : [];
        const realErrors = [];
        const rescuedFields = [];

        flaggedErrors.forEach((error) => {
            if (shouldBlockFlaggedField(error?.field)) {
                realErrors.push(error);
                return;
            }

            rescuedFields.push(error?.field || 'unknown');
        });

        return { realErrors, rescuedFields };
    }

    function joinErrorFields(errors) {
        return errors.map((error) => error?.field).filter(Boolean).join('/') || 'unknown';
    }

    function emitMetric(metricName, value) {
        window.mpSuperTokenMetrics?.sendMetric?.(metricName, value, METRIC_DETAIL);
    }

    function emitEmptyFieldsOnSubmitMetric(emptyFields) {
        // The emptyFields not should be normalized because
        // normalize method replace "email" value to "invalid_email_address_provided"
        // masking the real value of the field that is empty and causing the error.
        const shouldNormalizeError = false;

        emitMetric(VALIDATION_METRIC.BLOCKED, emptyFields);
        window.mpSuperTokenMetrics?.errorOnSubmit?.(BLOCK_REASON.EMPTY_FIELDS, emptyFields, shouldNormalizeError);
    }

    function readCheckoutType() {
        const element = document.querySelector('#mp_checkout_type');
        if (!element) {
            return { checkoutType: null, metricValue: CHECKOUT_TYPE_LABEL.ABSENT };
        }
        // checkoutType is the raw value used by the guard (may be naturally empty);
        // metricValue normalizes a falsy value to 'empty' so the metric stays filterable.
        return { checkoutType: element.value, metricValue: element.value || CHECKOUT_TYPE_LABEL.EMPTY };
    }

    window.mpResolveCheckoutValidation = function (response) {
        const { checkoutType, metricValue } = readCheckoutType();

        try {
            // Only the Super Token flow uses this layer; any other checkout type validates elsewhere.
            if (checkoutType !== SUPER_TOKEN_CHECKOUT_TYPE) {
                emitMetric(VALIDATION_METRIC.SKIPPED, metricValue);
                return { action: VALIDATION_ACTION.PROCEED };
            }

            if (response?.success && response?.data?.valid === true) {
                emitMetric(VALIDATION_METRIC.PASSED, 'valid');
                return { action: VALIDATION_ACTION.PROCEED };
            }

            if (response?.success && response?.data?.valid === false) {
                const errors = response.data.errors;
                if (!Array.isArray(errors) || !errors.length) {
                    return { action: VALIDATION_ACTION.FAIL_OPEN, reason: FAIL_OPEN_REASON.EMPTY_ERRORS };
                }

                const { realErrors, rescuedFields } = crossCheckErrorsAgainstDom(errors);

                if (rescuedFields.length) {
                    emitMetric(VALIDATION_METRIC.FALSE_POSITIVE, rescuedFields.join('/'));
                }

                if (!realErrors.length) {
                    return { action: VALIDATION_ACTION.PROCEED };
                }

                emitEmptyFieldsOnSubmitMetric(joinErrorFields(realErrors));
                return { action: VALIDATION_ACTION.BLOCK, errors: realErrors };
            }

            emitMetric(VALIDATION_METRIC.UNEXPECTED_RESPONSE, response?.data?.error || 'unknown');

            return {
                action: VALIDATION_ACTION.FAIL_OPEN,
                reason: FAIL_OPEN_REASON.UNEXPECTED_RESPONSE,
                detail: response?.data?.error,
            };
        } catch (error) {
            const errorMessage = error?.message || FAIL_OPEN_REASON.UNEXPECTED_ERROR;

            emitMetric(VALIDATION_METRIC.UNEXPECTED_ERROR, errorMessage);

            return {
                action: VALIDATION_ACTION.FAIL_OPEN,
                reason: FAIL_OPEN_REASON.UNEXPECTED_ERROR,
                detail: errorMessage,
            };
        }
    };
})();

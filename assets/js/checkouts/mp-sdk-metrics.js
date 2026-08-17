/**
 * Wrapper for Mercado Pago JS SDK calls.
 * Captures failures and sends a metric to Datadog (via sendMetric),
 * keeping the re-throw so existing .catch() handlers keep working.
 *
 * The error shape varies by failure origin:
 *   - Network failures (offline/timeout): string literal (e.g. "Failed to fetch")
 *   - API failures (4xx/5xx): object { message, status, cause, error, ok }
 *   - Card field validation (createCardToken): array of
 *     { cause, message, field, details:{ reason } } — client-side, not an API failure.
 *
 * @param {Function} sdkCall - Function that invokes the SDK method (returns a Promise).
 * @param {string} sdkMethod - Method name ("createCardToken", "yape.create", etc.) — used as api_route.
 * @returns {Promise} Result of the SDK call (re-throws on error).
 */

// createCardToken field-validation categories (see docs/agent/traps.md). Secure fields = PCI iframe; cardholderName/document = cardholder identity (non-PCI).
const TOKENIZATION_SECURE_FIELDS = ['cardNumber', 'expirationDate', 'securityCode'];
const TOKENIZATION_MESSAGE_SECURITY_FIELDS = 'invalid_security_fields';
const TOKENIZATION_MESSAGE_CARDHOLDER_FIELDS = 'invalid_cardholder_fields';

// createCardToken validates the non-PCI data (cardholderName / identification) first and rejects with [{ code, message }] (no field/details.reason); map those SDK codes to a field. See traps.md.
const TOKENIZATION_FIELD_BY_NON_PCI_CODE = {
    221: 'cardholderName',
    316: 'cardholderName',
    212: 'document',
    322: 'document',
    214: 'document',
    324: 'document',
};

function normalizeTokenizationField(field) {
    return (field === 'expirationMonth' || field === 'expirationYear') ? 'expirationDate' : field;
}

// Resolve the field for either error shape: PCI secure fields ({ field, details:{ reason } }) or non-PCI validateParams ({ code, message }).
function tokenizationFieldOf(item) {
    if (item?.field && item?.details?.reason) {
        return normalizeTokenizationField(item.field);
    }
    if (item?.code) {
        return TOKENIZATION_FIELD_BY_NON_PCI_CODE[item.code] || null;
    }
    return null;
}

function dedupeJoin(values) {
    return [...new Set(values.filter(Boolean))].join(',');
}

// Classify the createCardToken array into { message, reason } — reason carries the detail the message doesn't: secure -> which fields; cardholder/document -> the SDK message; unmapped -> message:null (opaque fallback kept) + raw message. See docs/agent/traps.md.
function classifyTokenizationError(errors) {
    const secureFields = [];
    const cardholderMessages = [];
    const documentMessages = [];
    const unmappedMessages = [];

    errors.forEach((item) => {
        const field = tokenizationFieldOf(item);
        if (field && TOKENIZATION_SECURE_FIELDS.includes(field)) {
            if (!secureFields.includes(field)) {
                secureFields.push(field);
            }
        } else if (field === 'cardholderName') {
            cardholderMessages.push(item?.message);
        } else if (field === 'document') {
            documentMessages.push(item?.message);
        } else if (item?.message) {
            unmappedMessages.push(item.message);
        }
    });

    if (secureFields.length) {
        const reason = TOKENIZATION_SECURE_FIELDS.filter((field) => secureFields.includes(field)).join(',');
        return { message: TOKENIZATION_MESSAGE_SECURITY_FIELDS, reason };
    }

    // Non-PCI fields: single message regardless of which fields failed; reason carries the SDK
    // error.message of all failing fields (cardholder, document, and any unmapped) so nothing is dropped.
    const combinedReason = dedupeJoin([...cardholderMessages, ...documentMessages, ...unmappedMessages]);
    if (cardholderMessages.length || documentMessages.length) {
        return { message: TOKENIZATION_MESSAGE_CARDHOLDER_FIELDS, reason: combinedReason };
    }
    return { message: null, reason: combinedReason || 'unknown' };
}

async function callSdkWithMetrics(sdkCall, sdkMethod) {
    try {
        return await sdkCall();
    } catch (error) {
        const status = String(error?.status ?? 0);

        // Only the Custom createCardToken rejects field-validation as an array (client-side, not an API failure); classify it instead of the opaque "Unknown SDK error". Scoped to the Custom checkout (shared wrapper) via #mp_checkout_type. See docs/agent/traps.md.
        const isCustomCheckout = typeof document !== 'undefined'
            && document.querySelector('#mp_checkout_type')?.value === 'custom';

        const isFieldValidation = isCustomCheckout
            && sdkMethod === 'createCardToken'
            && Array.isArray(error)
            && error.some((item) => item?.details?.reason || item?.code);

        const classified = isFieldValidation ? classifyTokenizationError(error) : null;
        const message = (classified && classified.message)
            || (typeof error === 'string' ? error : null)
            || error?.message
            || error?.cause?.[0]?.description
            || 'Unknown SDK error';

        // Guard: if mp-checkout-metrics.js didn't load, a bare sendMetric would throw a
        // ReferenceError and mask the original error (Error Cascade Prevention).
        if (typeof window.sendMetric === 'function') {
            const details = { api_route: sdkMethod };
            if (classified) {
                details.reason = classified.reason;
            }
            window.sendMetric(status, message, 'mp_api_error', details);
        }

        throw error;
    }
}

window.callSdkWithMetrics = callSdkWithMetrics;

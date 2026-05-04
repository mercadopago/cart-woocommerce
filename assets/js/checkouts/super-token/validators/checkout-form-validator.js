window.hasWooCommerceValidationErrors = function () {
    // Guard against malformed or unexpectedly deep DOM trees
    const MAX_ANCESTOR_DEPTH = 20;

    // Detects display:none on the element itself or any ancestor.
    // Intentionally limited to display:none — visibility:hidden and the
    // HTML hidden attribute are not covered, matching WooCommerce's own
    // field-visibility convention.
    function isFieldVisible(field) {
        let el = field;
        let depth = 0;
        while (el && el !== document.body && depth < MAX_ANCESTOR_DEPTH) {
            if (window.getComputedStyle(el).display === 'none') {
                return false;
            }
            el = el.parentElement;
            depth++;
        }
        return true;
    }

    const invalidFields = document.querySelectorAll(
        '.woocommerce-invalid, .woocommerce-invalid-required-field, .validate-required.woocommerce-invalid'
    );
    const visibleInvalidFields = Array.from(invalidFields).filter(isFieldVisible);

    const formScope = document.body.classList.contains('woocommerce-order-pay')
        ? '#order_review'
        : '.woocommerce-checkout';

    const requiredFields = document.querySelectorAll(
        `${formScope} .validate-required input, ${formScope} .validate-required select`
    );

    const emptyRequiredFields = Array.from(requiredFields).filter((field) => {
        if (field.type === 'hidden' || field.disabled) return false;
        if (!isFieldVisible(field)) return false;
        if (field.type === 'checkbox' && field.id === 'terms' && field.name === 'terms') {
            return !field.checked;
        }
        return !field.value.trim();
    });

    const hasErrors = visibleInvalidFields.length > 0 || emptyRequiredFields.length > 0;

    if (hasErrors && window.mpSuperTokenMetrics && typeof window.mpSuperTokenMetrics.sendMetric === 'function') {
        const emptyFieldNames = emptyRequiredFields.map((field) => {
            return field.name || field.id || field.type;
        });
        const invalidFieldNames = visibleInvalidFields.map((container) => {
            const field = container.querySelector('input[name], select[name]');
            return field ? field.name : (container.id || 'unknown');
        });
        const allFieldNames = Array.from(new Set(emptyFieldNames.concat(invalidFieldNames))).join(',');

        window.mpSuperTokenMetrics.sendMetric(
            'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
            allFieldNames || 'unknown',
            'hasWooCommerceValidationErrors'
        );
    }

    return hasErrors;
};

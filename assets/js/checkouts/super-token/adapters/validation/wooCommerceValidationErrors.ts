/**
 * Detects visible required/invalid WooCommerce checkout fields before a Super Token payment,
 * so the flow never starts on a form the buyer still has to complete. Ported 1:1 from the legacy
 * `v2.1/validators/checkout-form-validator.js` (identical to v2) so the whole v2/v2.1 tree can be
 * deleted; published as `window.hasWooCommerceValidationErrors` by the bundle entrypoint
 * (bootstrap.ts) via globalBridge.
 *
 * The current source has no in-tree caller, but the checkout-resilience rules document this as the
 * approved validation gate and old plugin versions reach it through the shared CDN bundle, so it is
 * ported for compatibility. Metrics are injected (never read from `window.*`): the bundle
 * entrypoint reads the live `window.mpSuperTokenMetrics` singleton at call time and passes it in.
 */

// Guard against malformed or unexpectedly deep DOM trees
const MAX_ANCESTOR_DEPTH = 20;

const CONTAINER_FIELDS_SELECTOR = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';

/** The metric sink the checker emits into. Injected by the composition root. */
export interface WooCommerceValidationMetrics {
  sendMetric?(name: string, value: string, message: string): void;
}

// Detects display:none or opacity:0 on the element itself or any ancestor (all fields).
// Also detects visibility:hidden for WooCommerce-registered fields (form-row id="*_field"),
// where server-side validation acts as a safety net for false positives.
// visibility:hidden is intentionally excluded for non-registered custom fields to avoid
// creating orders with missing data when server-side validation is absent.
function isFieldVisible(field: Element): boolean {
  const isWcField = field.closest('.form-row')?.id?.endsWith('_field') ?? false;
  let el: Element | null = field;
  let depth = 0;
  while (el && el !== document.body && depth < MAX_ANCESTOR_DEPTH) {
    const style = getComputedStyle(el);
    if (style.display === 'none') return false;
    if (style.opacity === '0') return false;
    if (isWcField && style.visibility === 'hidden') return false;
    el = el.parentElement;
    depth++;
  }
  return true;
}

function isFieldFilled(field: Element): boolean {
  const input = field as HTMLInputElement;
  return (input.type === 'checkbox' || input.type === 'radio') ? input.checked : !!input.value.trim();
}

export function hasWooCommerceValidationErrors(metrics?: WooCommerceValidationMetrics): boolean {
  const invalidFields = document.querySelectorAll(
    '.woocommerce-invalid, .woocommerce-invalid-required-field, .validate-required.woocommerce-invalid',
  );
  const visibleInvalidFields = Array.from(invalidFields).filter((container) => {
    if (!isFieldVisible(container)) return false;
    // Only consider skipping when the container is flagged as a stale required-field error.
    // Containers with just `woocommerce-invalid` (without `-required-field`) indicate
    // format errors (e.g. malformed email) and must NOT be skipped even when filled.
    if (!container.classList.contains('woocommerce-invalid-required-field')) {
      return true;
    }
    // Skip stale required-field containers only when ALL their fields are considered filled.
    // For checkbox/radio, filled = checked; for other inputs, filled = non-empty value.
    // This covers the case where the store populates fields via JS without triggering
    // WC re-validation events, leaving woocommerce-invalid-required-field stale on a filled container.
    const isDirectField = container.matches('input, select, textarea');
    const fields = isDirectField
      ? [container]
      : Array.from(container.querySelectorAll(CONTAINER_FIELDS_SELECTOR));
    const allFieldsFilled = fields.length > 0 && fields.every(isFieldFilled);
    if (allFieldsFilled) {
      const fieldNames = fields.map((f) => (f as HTMLInputElement).name || f.id || 'unknown').join('/');
      metrics?.sendMetric?.(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        fieldNames,
        'visibleInvalidFields',
      );
      return false;
    }
    return true;
  });

  const formScope = document.body.classList.contains('woocommerce-order-pay')
    ? '#order_review'
    : '.woocommerce-checkout';

  const requiredFields = document.querySelectorAll(
    `${formScope} .validate-required input, ${formScope} .validate-required select`,
  );

  const emptyRequiredFields = Array.from(requiredFields).filter((field) => {
    const input = field as HTMLInputElement;
    if (input.type === 'hidden' || input.disabled) return false;
    if (!isFieldVisible(field)) return false;
    if (input.type === 'checkbox' && input.id === 'terms' && input.name === 'terms') {
      return !input.checked;
    }
    return !input.value.trim();
  });

  const hasErrors = visibleInvalidFields.length > 0 || emptyRequiredFields.length > 0;

  if (hasErrors && metrics && typeof metrics.sendMetric === 'function') {
    const emptyFieldNames = emptyRequiredFields.map((field) => {
      const input = field as HTMLInputElement;
      return input.name || input.id || input.type;
    });
    const invalidFieldNames = visibleInvalidFields.map((container) => {
      const field = container.querySelector('input[name], select[name]') as HTMLInputElement | null;
      return field ? field.name : (container.id || 'unknown');
    });
    const allFieldNames = Array.from(new Set(emptyFieldNames.concat(invalidFieldNames))).join('/');

    metrics.sendMetric(
      'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
      allFieldNames || 'unknown',
      'hasWooCommerceValidationErrors',
    );
  }

  return hasErrors;
}

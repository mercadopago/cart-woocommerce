/**
 * Resolves the server-side checkout pre-validation verdict (wc_ajax_mp_validate_checkout)
 * against the live DOM and emits the validation funnel metrics. Ported 1:1 from the legacy
 * `shared/validators/checkout-validation-resolver.js` IIFE so the whole v2/v2.1/shared tree can
 * be deleted; published as `window.mpResolveCheckoutValidation` by the bundle entrypoint
 * (bootstrap.ts) via globalBridge. The Classic `event-handler.js` is a thin wrapper that
 * dispatches the verdict and fails open if this is absent or throws.
 *
 * The route only sees the serialized form.checkout body, so a required field rendered outside that
 * form (e.g. CartFlows funnel step) is reported empty even when it is visible and filled on screen.
 * The cross-check discards those false positives so the buyer is never blocked for a field that is
 * actually present and filled.
 *
 * Metrics are injected (never read from `window.*`): the bundle entrypoint reads the live
 * `window.mpSuperTokenMetrics` singleton at call time and passes it in, keeping window.* at the
 * composition edge.
 */

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

export interface ValidationError {
  field?: string | null;
  code?: string;
  message?: string;
}

export interface CheckoutValidationVerdict {
  action: string;
  errors?: ValidationError[];
  reason?: string;
  detail?: string;
}

/** The metric sink the resolver emits into. Injected by the composition root. */
export interface CheckoutValidationMetrics {
  sendMetric?(name: string, value: string, message: string): void;
  errorOnSubmit?(reason: string, fields: string, shouldNormalize: boolean): void;
}

interface ValidationResponse {
  success?: boolean;
  data?: {
    valid?: boolean;
    errors?: unknown;
    error?: string;
  };
}

function getFieldNodesByName(fieldName: string): Element[] {
  try {
    // WooCommerce field names follow billing_*/shipping_*/terms conventions and never
    // contain quotes or CSS-special characters, so the fallback is safe without escaping.
    const selector = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
      ? `[name=${CSS.escape(fieldName)}]`
      : `[name="${fieldName}"]`;
    return Array.from(document.querySelectorAll(selector));
  } catch {
    return [];
  }
}

function isFieldVisible(field: Element): boolean {
  const isWcRegisteredField = field.closest('.form-row')?.id?.endsWith('_field') ?? false;
  let element: Element | null = field;
  let depth = 0;

  while (element && element !== document.body && depth < MAX_ANCESTOR_DEPTH) {
    const style = getComputedStyle(element);
    if (style.display === 'none') return false;
    if (style.opacity === '0') return false;
    if (isWcRegisteredField && style.visibility === 'hidden') return false;
    element = element.parentElement;
    depth++;
  }

  return true;
}

function isFieldFilled(field: Element): boolean {
  const input = field as HTMLInputElement;
  if (input.type === 'checkbox' || input.type === 'radio') {
    return !!input.checked;
  }
  return !!input.value?.trim();
}

// Block only when the buyer can see the field and correct it right here.
// Absent or hidden fields are rescued — we cannot verify their state, and the buyer
// cannot interact with them on this screen. The real WooCommerce submit is the backstop.
// Specific fields that should block despite being absent can be added later, driven by
// MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE metric data from production.
function shouldBlockFlaggedField(fieldName: string | null | undefined): boolean {
  if (!fieldName) {
    return false;
  }

  const nodes = getFieldNodesByName(fieldName);
  if (!nodes.length) {
    return false;
  }

  const visibleNodes = nodes.filter(isFieldVisible);
  if (!visibleNodes.length) {
    return false;
  }

  return !visibleNodes.some(isFieldFilled);
}

function crossCheckErrorsAgainstDom(errors: unknown): { realErrors: ValidationError[]; rescuedFields: string[] } {
  const flaggedErrors: ValidationError[] = Array.isArray(errors) ? errors : [];
  const realErrors: ValidationError[] = [];
  const rescuedFields: string[] = [];

  flaggedErrors.forEach((error) => {
    if (shouldBlockFlaggedField(error?.field)) {
      realErrors.push(error);
      return;
    }

    rescuedFields.push(error?.field || 'unknown');
  });

  return { realErrors, rescuedFields };
}

function joinErrorFields(errors: ValidationError[]): string {
  return errors.map((error) => error?.field).filter(Boolean).join('/') || 'unknown';
}

function readCheckoutType(): { checkoutType: string | null; metricValue: string } {
  const element = document.querySelector('#mp_checkout_type') as HTMLInputElement | null;
  if (!element) {
    return { checkoutType: null, metricValue: CHECKOUT_TYPE_LABEL.ABSENT };
  }
  // checkoutType is the raw value used by the guard (may be naturally empty);
  // metricValue normalizes a falsy value to 'empty' so the metric stays filterable.
  return { checkoutType: element.value, metricValue: element.value || CHECKOUT_TYPE_LABEL.EMPTY };
}

export function resolveCheckoutValidation(
  response: unknown,
  metrics?: CheckoutValidationMetrics,
): CheckoutValidationVerdict {
  const emitMetric = (metricName: string, value: string): void => {
    metrics?.sendMetric?.(metricName, value, METRIC_DETAIL);
  };

  const emitEmptyFieldsOnSubmitMetric = (emptyFields: string): void => {
    // The emptyFields not should be normalized because
    // normalize method replace "email" value to "invalid_email_address_provided"
    // masking the real value of the field that is empty and causing the error.
    const shouldNormalizeError = false;

    emitMetric(VALIDATION_METRIC.BLOCKED, emptyFields);
    metrics?.errorOnSubmit?.(BLOCK_REASON.EMPTY_FIELDS, emptyFields, shouldNormalizeError);
  };

  const { checkoutType, metricValue } = readCheckoutType();
  const res = response as ValidationResponse | null | undefined;

  try {
    // Only the Super Token flow uses this layer; any other checkout type validates elsewhere.
    if (checkoutType !== SUPER_TOKEN_CHECKOUT_TYPE) {
      emitMetric(VALIDATION_METRIC.SKIPPED, metricValue);
      return { action: VALIDATION_ACTION.PROCEED };
    }

    if (res?.success && res?.data?.valid === true) {
      emitMetric(VALIDATION_METRIC.PASSED, 'valid');
      return { action: VALIDATION_ACTION.PROCEED };
    }

    if (res?.success && res?.data?.valid === false) {
      const errors = res.data.errors;
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

    emitMetric(VALIDATION_METRIC.UNEXPECTED_RESPONSE, res?.data?.error || 'unknown');

    return {
      action: VALIDATION_ACTION.FAIL_OPEN,
      reason: FAIL_OPEN_REASON.UNEXPECTED_RESPONSE,
      detail: res?.data?.error,
    };
  } catch (error) {
    const errorMessage = (error as Error)?.message || FAIL_OPEN_REASON.UNEXPECTED_ERROR;

    emitMetric(VALIDATION_METRIC.UNEXPECTED_ERROR, errorMessage);

    return {
      action: VALIDATION_ACTION.FAIL_OPEN,
      reason: FAIL_OPEN_REASON.UNEXPECTED_ERROR,
      detail: errorMessage,
    };
  }
}

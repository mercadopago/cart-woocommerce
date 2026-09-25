const { resolveCheckoutValidation } = require('@super-token/adapters/validation/checkoutValidationResolver');

describe('resolveCheckoutValidation (ported checkout-validation-resolver)', () => {
  let metrics;

  beforeEach(() => {
    document.body.innerHTML = '<input type="hidden" id="mp_checkout_type" value="super_token" />';
    document.body.className = '';
    metrics = { sendMetric: jest.fn(), errorOnSubmit: jest.fn() };
  });

  const invalid = (errors) => ({ success: true, data: { valid: false, errors } });
  const addFields = (html) => document.body.insertAdjacentHTML('beforeend', html);
  const setCheckoutType = (value) => { document.querySelector('#mp_checkout_type').value = value; };
  const resolve = (response) => resolveCheckoutValidation(response, metrics);

  // ===========================================================================
  // Conclusive valid verdict
  // ===========================================================================
  describe('Given a conclusive valid verdict', () => {
    it('When the route returns valid:true, Then should return PROCEED and emit the PASSED metric', () => {
      const verdict = resolve({ success: true, data: { valid: true, errors: [] } });

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_PASSED',
        'valid',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // False positive — field present, visible and filled outside the form
  // ===========================================================================
  describe('Given a flagged field that is visible and filled on screen', () => {
    it('When the route returns valid:false, Then should discard it, PROCEED and emit FALSE_POSITIVE', () => {
      addFields('<input name="billing_state" value="Buenos Aires" />');

      const verdict = resolve(invalid([{ field: 'billing_state', code: 'state', message: 'State is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_state',
        'validate_checkout_then_continue',
      );
      expect(metrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything(),
      );
    });
  });

  // ===========================================================================
  // Real error — visible but empty
  // ===========================================================================
  describe('Given a flagged field that is visible and empty', () => {
    it('When the route returns valid:false, Then should BLOCK and emit BLOCKED with the field', () => {
      addFields('<input name="billing_postcode" value="" />');
      const errors = [{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'BLOCK', errors });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        'billing_postcode',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Absent from the DOM — buyer cannot act here (managed server-side / prior funnel step)
  // ===========================================================================
  describe('Given a flagged field absent from the DOM', () => {
    it('When the route returns valid:false, Then should rescue it, PROCEED and not BLOCK', () => {
      const errors = [{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_postcode',
        'validate_checkout_then_continue',
      );
      expect(metrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything(),
      );
    });
  });

  // ===========================================================================
  // Funnel checkout (DPMedios): all flagged address fields absent from the final step
  // ===========================================================================
  describe('Given every flagged address field is absent from the DOM (server-side funnel)', () => {
    it('When the route returns valid:false for all of them, Then should rescue all and PROCEED', () => {
      const errors = ['billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode']
        .map((field) => ({ field, code: field, message: `${field} is required` }));

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_country/billing_address_1/billing_city/billing_state/billing_postcode',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Any flagged field absent from the DOM is rescued — buyer cannot interact with it here.
  // ===========================================================================
  describe('Given any flagged field absent from the DOM', () => {
    it('When the route returns valid:false for a server-side error code, Then should rescue and PROCEED', () => {
      const errors = [{ field: 'checkout_process', code: 'checkout_process', message: 'Anti-fraud check failed' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'checkout_process',
        'validate_checkout_then_continue',
      );
    });

    it('When the route flags a custom absent field, Then should rescue and PROCEED', () => {
      const errors = [{ field: 'custom_required_field', code: 'custom_required_field', message: 'Required' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
    });
  });

  // ===========================================================================
  // Fieldless error (field: null/undefined) — cannot verify visibility, rescue
  // ===========================================================================
  describe('Given an error with no field identifier (field: null)', () => {
    it('When the route returns valid:false, Then should rescue it and PROCEED (cannot verify DOM visibility)', () => {
      const errors = [{ field: null, code: 'generic', message: 'Generic error' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
    });
  });

  // ===========================================================================
  // Present but hidden — buyer cannot act here
  // ===========================================================================
  describe('Given a flagged field hidden by a display:none ancestor', () => {
    it('When the route returns valid:false, Then should rescue it and PROCEED', () => {
      addFields('<div style="display:none"><input name="billing_city" value="" /></div>');
      const errors = [{ field: 'billing_city', code: 'city', message: 'City is required' }];

      const verdict = resolve(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_city',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Mixed errors — block only the real one
  // ===========================================================================
  describe('Given mixed errors (one filled outside the form, one visible and empty)', () => {
    it('When the route returns valid:false, Then should BLOCK only the real one and emit both metrics', () => {
      addFields('<input name="billing_state" value="Buenos Aires" />'
        + '<input name="billing_city" value="" />');
      const stateError = { field: 'billing_state', code: 'state', message: 'State is required' };
      const cityError = { field: 'billing_city', code: 'city', message: 'City is required' };

      const verdict = resolve(invalid([stateError, cityError]));

      expect(verdict).toEqual({ action: 'BLOCK', errors: [cityError] });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_state',
        'validate_checkout_then_continue',
      );
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        'billing_city',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Funnel error event — errorOnSubmit fires so melidata registers the /error path (BigQuery).
  // ===========================================================================
  describe('Given a real empty field that blocks the checkout', () => {
    it('When the route returns valid:false, Then should call errorOnSubmit with EMPTY_FIELDS and the raw (un-normalized) field', () => {
      addFields('<input name="billing_postcode" value="" />');

      const verdict = resolve(invalid([{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }]));

      expect(verdict.action).toBe('BLOCK');
      expect(metrics.errorOnSubmit).toHaveBeenCalledWith('EMPTY_FIELDS', 'billing_postcode', false);
    });

    it('When the blocked field name contains "email", Then should NOT normalize it', () => {
      addFields('<input name="billing_email" value="" />');

      resolve(invalid([{ field: 'billing_email', code: 'email', message: 'Email is required' }]));

      expect(metrics.errorOnSubmit).toHaveBeenCalledWith('EMPTY_FIELDS', 'billing_email', false);
    });

    it('When multiple real fields are empty, Then should call errorOnSubmit with the joined field names', () => {
      addFields('<input name="billing_address_1" value="" />'
        + '<input name="billing_city" value="" />');
      const errors = [
        { field: 'billing_address_1', code: 'address', message: 'Address is required' },
        { field: 'billing_city', code: 'city', message: 'City is required' },
      ];

      resolve(invalid(errors));

      expect(metrics.errorOnSubmit).toHaveBeenCalledWith('EMPTY_FIELDS', 'billing_address_1/billing_city', false);
    });

    it('When some flagged fields are false positives, Then should call errorOnSubmit with only the real blocked field', () => {
      addFields('<input name="billing_state" value="Buenos Aires" />'
        + '<input name="billing_city" value="" />');
      const errors = [
        { field: 'billing_state', code: 'state', message: 'State is required' },
        { field: 'billing_city', code: 'city', message: 'City is required' },
      ];

      resolve(invalid(errors));

      expect(metrics.errorOnSubmit).toHaveBeenCalledWith('EMPTY_FIELDS', 'billing_city', false);
    });
  });

  // ===========================================================================
  // errorOnSubmit must NOT fire when nothing real blocks.
  // ===========================================================================
  describe('Given the checkout is not blocked by a real empty field', () => {
    it('When the verdict is valid:true (PASSED), Then should not call errorOnSubmit', () => {
      resolve({ success: true, data: { valid: true, errors: [] } });

      expect(metrics.errorOnSubmit).not.toHaveBeenCalled();
    });

    it('When every flagged field is a false positive (PROCEED), Then should not call errorOnSubmit', () => {
      addFields('<input name="billing_state" value="Buenos Aires" />');

      resolve(invalid([{ field: 'billing_state', code: 'state', message: 'State is required' }]));

      expect(metrics.errorOnSubmit).not.toHaveBeenCalled();
    });

    it('When all flagged fields are absent from the DOM (server-side funnel), Then should not call errorOnSubmit', () => {
      const errors = ['billing_country', 'billing_address_1', 'billing_city']
        .map((field) => ({ field, code: field, message: `${field} is required` }));

      resolve(invalid(errors));

      expect(metrics.errorOnSubmit).not.toHaveBeenCalled();
    });

    it('When the response is inconclusive (FAIL_OPEN), Then should not call errorOnSubmit', () => {
      resolve({ success: false, data: { error: 'unexpected_error' } });

      expect(metrics.errorOnSubmit).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // Resilience — errorOnSubmit absent must not break the block verdict.
  // ===========================================================================
  describe('Given the metrics sink exists but errorOnSubmit is undefined', () => {
    it('When a real empty field blocks, Then should still BLOCK without throwing', () => {
      metrics = { sendMetric: jest.fn() };
      addFields('<input name="billing_postcode" value="" />');

      let verdict;
      expect(() => {
        verdict = resolve(invalid([{ field: 'billing_postcode', code: 'postcode', message: 'x' }]));
      }).not.toThrow();
      expect(verdict.action).toBe('BLOCK');
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        'billing_postcode',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Radio group — one option checked counts as filled
  // ===========================================================================
  describe('Given a radio group with one option checked', () => {
    it('When the route flags the group, Then should treat it as a false positive', () => {
      addFields('<input type="radio" name="shipping_method" value="flat" />'
        + '<input type="radio" name="shipping_method" value="free" checked />');

      const verdict = resolve(invalid([{ field: 'shipping_method', code: 'shipping', message: 'Shipping is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'shipping_method',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Inconclusive response (success:false) — fail open, emit UNEXPECTED_RESPONSE carrying the cause
  // ===========================================================================
  describe('Given an inconclusive response (success:false)', () => {
    it('When resolved, Then should return FAIL_OPEN with UNEXPECTED_RESPONSE carrying the cause and emit the metric', () => {
      const verdict = resolve({ success: false, data: { error: 'unexpected_error' } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'UNEXPECTED_RESPONSE', detail: 'unexpected_error' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_RESPONSE',
        'unexpected_error',
        'validate_checkout_then_continue',
      );
      expect(metrics.errorOnSubmit).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // Unexpected throw inside the resolver — fail open, emit UNEXPECTED_ERROR carrying the cause
  // ===========================================================================
  describe('Given the resolver throws unexpectedly while reading the response', () => {
    it('When resolved, Then should return FAIL_OPEN with UNEXPECTED_ERROR and emit the metric', () => {
      const throwingResponse = { success: true, get data() { throw new Error('boom'); } };

      const verdict = resolve(throwingResponse);

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'UNEXPECTED_ERROR', detail: 'boom' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_ERROR',
        'boom',
        'validate_checkout_then_continue',
      );
    });

    it('When the thrown value carries no message, Then should fall back to the UNEXPECTED_ERROR reason, not the metric name', () => {
      const throwingResponse = { success: true, get data() { throw 'plain string'; } };

      const verdict = resolve(throwingResponse);

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'UNEXPECTED_ERROR', detail: 'UNEXPECTED_ERROR' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_UNEXPECTED_ERROR',
        'UNEXPECTED_ERROR',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // valid:false with empty or absent errors list — fail open (not PROCEED)
  // ===========================================================================
  describe('Given a valid:false response with an empty errors list', () => {
    it('When resolved, Then should return FAIL_OPEN with EMPTY_ERRORS and not PROCEED', () => {
      const verdict = resolve({ success: true, data: { valid: false, errors: [] } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'EMPTY_ERRORS' });
      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    it('When errors is absent from the response, Then should return FAIL_OPEN with EMPTY_ERRORS', () => {
      const verdict = resolve({ success: true, data: { valid: false } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'EMPTY_ERRORS' });
    });
  });

  // ===========================================================================
  // Resilience — metrics dependency absent
  // ===========================================================================
  describe('Given the metrics sink is absent', () => {
    it('When resolved, Then should not throw', () => {
      addFields('<input name="billing_postcode" value="" />');

      expect(() => resolveCheckoutValidation(invalid([{ field: 'billing_postcode', code: 'postcode', message: 'x' }]), undefined)).not.toThrow();
    });
  });

  // ===========================================================================
  // Early return — this layer blocks only the Super Token flow.
  // ===========================================================================
  describe('Given the checkout type is not super_token', () => {
    it('When the type is "custom" and the route flags a visible empty field, Then should PROCEED and emit SKIPPED("custom")', () => {
      setCheckoutType('custom');
      addFields('<input name="billing_city" value="" />');

      const verdict = resolve(invalid([{ field: 'billing_city', code: 'city', message: 'City is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
        'custom',
        'validate_checkout_then_continue',
      );
      expect(metrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything(),
      );
    });

    it('When #mp_checkout_type is absent from the DOM, Then should PROCEED and emit SKIPPED("absent")', () => {
      document.querySelector('#mp_checkout_type').remove();

      const verdict = resolve(invalid([{ field: 'billing_city', code: 'city', message: 'City is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
        'absent',
        'validate_checkout_then_continue',
      );
    });

    it('When #mp_checkout_type is present but empty, Then should PROCEED and emit SKIPPED("empty")', () => {
      setCheckoutType('');

      const verdict = resolve(invalid([{ field: 'billing_city', code: 'city', message: 'City is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
        'empty',
        'validate_checkout_then_continue',
      );
    });
  });

  // ===========================================================================
  // Super Token flow never emits SKIPPED — it stays on the PASSED/BLOCKED funnel.
  // ===========================================================================
  describe('Given the checkout type is super_token', () => {
    it('When a visible empty field blocks, Then should emit BLOCKED and never SKIPPED', () => {
      addFields('<input name="billing_postcode" value="" />');

      const verdict = resolve(invalid([{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }]));

      expect(verdict.action).toBe('BLOCK');
      expect(metrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_SKIPPED',
        expect.anything(),
        expect.anything(),
      );
    });
  });
});

const { resolveAlias } = require('../../../../helpers/path-resolver');
const { loadFile } = require('../../../../helpers/load-file');

const resolverPath = resolveAlias('assets/js/checkouts/super-token/shared/validators/checkout-validation-resolver.js');

describe('mpResolveCheckoutValidation (CDN - checkout-validation-resolver)', () => {
  beforeAll(() => {
    loadFile(resolverPath, 'window.mpResolveCheckoutValidation', {});
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
    global.window.mpSuperTokenMetrics = { sendMetric: jest.fn() };
  });

  const invalid = (errors) => ({ success: true, data: { valid: false, errors } });

  // ===========================================================================
  // Conclusive valid verdict
  // ===========================================================================
  describe('Given a conclusive valid verdict', () => {
    it('When the route returns valid:true, Then should return PROCEED and emit the PASSED metric', () => {
      const verdict = window.mpResolveCheckoutValidation({ success: true, data: { valid: true, errors: [] } });

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_PASSED',
        'valid',
        'validate_checkout_then_continue'
      );
    });
  });

  // ===========================================================================
  // False positive — field present, visible and filled outside the form
  // ===========================================================================
  describe('Given a flagged field that is visible and filled on screen', () => {
    it('When the route returns valid:false, Then should discard it, PROCEED and emit FALSE_POSITIVE', () => {
      document.body.innerHTML = '<input name="billing_state" value="Buenos Aires" />';

      const verdict = window.mpResolveCheckoutValidation(invalid([{ field: 'billing_state', code: 'state', message: 'State is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_state',
        'validate_checkout_then_continue'
      );
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything()
      );
    });
  });

  // ===========================================================================
  // Real error — visible but empty
  // ===========================================================================
  describe('Given a flagged field that is visible and empty', () => {
    it('When the route returns valid:false, Then should BLOCK and emit BLOCKED with the field', () => {
      document.body.innerHTML = '<input name="billing_postcode" value="" />';
      const errors = [{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'BLOCK', errors });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        'billing_postcode',
        'validate_checkout_then_continue'
      );
    });
  });

  // ===========================================================================
  // Absent from the DOM — buyer cannot act here (managed server-side / prior funnel step)
  // ===========================================================================
  describe('Given a flagged field absent from the DOM', () => {
    it('When the route returns valid:false, Then should rescue it, PROCEED and not BLOCK', () => {
      const errors = [{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_postcode',
        'validate_checkout_then_continue'
      );
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything()
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

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_country/billing_address_1/billing_city/billing_state/billing_postcode',
        'validate_checkout_then_continue'
      );
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        expect.anything(),
        expect.anything()
      );
    });
  });

  // ===========================================================================
  // Any flagged field absent from the DOM is rescued — buyer cannot interact with it here.
  // The real WooCommerce submit is the backstop. Fields that should block despite being
  // absent can be added later, driven by FALSE_POSITIVE metric data from production.
  // ===========================================================================
  describe('Given any flagged field absent from the DOM', () => {
    it('When the route returns valid:false for a server-side error code, Then should rescue and PROCEED', () => {
      const errors = [{ field: 'checkout_process', code: 'checkout_process', message: 'Anti-fraud check failed' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'checkout_process',
        'validate_checkout_then_continue'
      );
    });

    it('When the route flags a custom absent field, Then should rescue and PROCEED', () => {
      const errors = [{ field: 'custom_required_field', code: 'custom_required_field', message: 'Required' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
    });
  });

  // ===========================================================================
  // Fieldless error (field: null/undefined) — cannot verify visibility, rescue
  // ===========================================================================
  describe('Given an error with no field identifier (field: null)', () => {
    it('When the route returns valid:false, Then should rescue it and PROCEED (cannot verify DOM visibility)', () => {
      const errors = [{ field: null, code: 'generic', message: 'Generic error' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
    });
  });

  // ===========================================================================
  // Present but hidden — buyer cannot act here
  // ===========================================================================
  describe('Given a flagged field hidden by a display:none ancestor', () => {
    it('When the route returns valid:false, Then should rescue it and PROCEED', () => {
      document.body.innerHTML = '<div style="display:none"><input name="billing_city" value="" /></div>';
      const errors = [{ field: 'billing_city', code: 'city', message: 'City is required' }];

      const verdict = window.mpResolveCheckoutValidation(invalid(errors));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_city',
        'validate_checkout_then_continue'
      );
    });
  });

  // ===========================================================================
  // Mixed errors — block only the real one
  // ===========================================================================
  describe('Given mixed errors (one filled outside the form, one visible and empty)', () => {
    it('When the route returns valid:false, Then should BLOCK only the real one and emit both metrics', () => {
      document.body.innerHTML = '<input name="billing_state" value="Buenos Aires" />'
        + '<input name="billing_city" value="" />';
      const stateError = { field: 'billing_state', code: 'state', message: 'State is required' };
      const cityError = { field: 'billing_city', code: 'city', message: 'City is required' };

      const verdict = window.mpResolveCheckoutValidation(invalid([stateError, cityError]));

      expect(verdict).toEqual({ action: 'BLOCK', errors: [cityError] });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'billing_state',
        'validate_checkout_then_continue'
      );
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_BLOCKED',
        'billing_city',
        'validate_checkout_then_continue'
      );
    });
  });

  // ===========================================================================
  // Radio group — one option checked counts as filled
  // ===========================================================================
  describe('Given a radio group with one option checked', () => {
    it('When the route flags the group, Then should treat it as a false positive', () => {
      document.body.innerHTML = '<input type="radio" name="shipping_method" value="flat" />'
        + '<input type="radio" name="shipping_method" value="free" checked />';

      const verdict = window.mpResolveCheckoutValidation(invalid([{ field: 'shipping_method', code: 'shipping', message: 'Shipping is required' }]));

      expect(verdict).toEqual({ action: 'PROCEED' });
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CHECKOUT_AJAX_VALIDATION_FALSE_POSITIVE',
        'shipping_method',
        'validate_checkout_then_continue'
      );
    });
  });

  // ===========================================================================
  // Inconclusive response — fail open carrying the cause
  // ===========================================================================
  describe('Given an inconclusive response (success:false)', () => {
    it('When resolved, Then should return FAIL_OPEN with SERVER_ERROR carrying the cause and emit no verdict metric', () => {
      const verdict = window.mpResolveCheckoutValidation({ success: false, data: { error: 'unexpected_error' } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'SERVER_ERROR', detail: 'unexpected_error' });
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // valid:false with empty or absent errors list — fail open (not PROCEED)
  // ===========================================================================
  describe('Given a valid:false response with an empty errors list', () => {
    it('When resolved, Then should return FAIL_OPEN with EMPTY_ERRORS and not PROCEED', () => {
      const verdict = window.mpResolveCheckoutValidation({ success: true, data: { valid: false, errors: [] } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'EMPTY_ERRORS' });
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalled();
    });

    it('When errors is absent from the response, Then should return FAIL_OPEN with EMPTY_ERRORS', () => {
      const verdict = window.mpResolveCheckoutValidation({ success: true, data: { valid: false } });

      expect(verdict).toEqual({ action: 'FAIL_OPEN', reason: 'EMPTY_ERRORS' });
    });
  });

  // ===========================================================================
  // Resilience — metrics dependency absent
  // ===========================================================================
  describe('Given window.mpSuperTokenMetrics is absent', () => {
    it('When resolved, Then should not throw', () => {
      delete global.window.mpSuperTokenMetrics;
      document.body.innerHTML = '<input name="billing_postcode" value="" />';

      expect(() => window.mpResolveCheckoutValidation(invalid([{ field: 'billing_postcode', code: 'postcode', message: 'x' }]))).not.toThrow();
    });
  });
});

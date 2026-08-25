const { hasWooCommerceValidationErrors } = require('@super-token/adapters/validation/wooCommerceValidationErrors');

describe('hasWooCommerceValidationErrors (ported checkout-form-validator)', () => {
  let metrics;

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
    metrics = { sendMetric: jest.fn() };
  });

  const check = () => hasWooCommerceValidationErrors(metrics);

  // ===========================================================================
  // No validation errors
  // ===========================================================================
  describe('Given a checkout form with no validation errors', () => {
    it('When all required fields are filled, Then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="text" value="John Doe" /></div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When the form has no fields at all, Then should return false', () => {
      document.body.innerHTML = '<div class="woocommerce-checkout"></div>';

      expect(check()).toBe(false);
    });
  });

  // ===========================================================================
  // Empty required field
  // ===========================================================================
  describe('Given a visible empty required field', () => {
    it('When resolved, Then should return true and emit FORM_VALIDATION_ERROR with the field name', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="text" name="billing_first_name" value="" /></div>
        </div>`;

      expect(check()).toBe(true);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_first_name',
        'hasWooCommerceValidationErrors',
      );
    });

    it('When the required field is hidden by display:none, Then should ignore it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required" style="display:none"><input type="text" name="billing_first_name" value="" /></div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When the required field is hidden-type or disabled, Then should ignore it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="hidden" name="billing_hidden" value="" />
            <input type="text" name="billing_disabled" value="" disabled />
          </div>
        </div>`;

      expect(check()).toBe(false);
    });
  });

  // ===========================================================================
  // Visibility compatibility — shipping plugins and FunnelKit
  // ===========================================================================
  describe('Given required fields nested inside conditionally hidden containers', () => {
    it('When a display:none ancestor is several levels above, Then should ignore the field', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="display:none">
            <div><div><div class="validate-required"><input name="shipping_office" value="" /></div></div></div>
          </div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When a FunnelKit-like ancestor has opacity:0, Then should ignore the field', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="wfacp-sec-wrapper" style="opacity:0">
            <div><div><div><div class="validate-required"><input name="username" value="" /></div></div></div></div>
          </div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When opacity:0 comes from an external style rule, Then should ignore the field', () => {
      document.body.innerHTML = `
        <style>.wfacp-quickv-panel { opacity: 0; }</style>
        <div class="woocommerce-checkout">
          <div class="wfacp-quickv-panel">
            <div class="validate-required"><input name="username" value="" /></div>
          </div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When a registered WooCommerce field has a visibility:hidden ancestor, Then should ignore it', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility:hidden">
            <p class="form-row validate-required" id="billing_email_field">
              <input name="billing_email" value="" />
            </p>
          </div>
        </div>`;

      expect(check()).toBe(false);
    });

    it('When an unregistered custom field has a visibility:hidden ancestor, Then should fail closed', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility:hidden">
            <div class="validate-required"><input name="custom_required" value="" /></div>
          </div>
        </div>`;

      expect(check()).toBe(true);
    });
  });

  // ===========================================================================
  // Blocks compatibility and mixed containers
  // ===========================================================================
  describe('Given Blocks or mixed checkout containers', () => {
    it('When a pure Blocks form has no Classic wrapper, Then should return false gracefully', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form" class="wc-block-checkout__form">
          <input name="shipping_first_name" value="" required />
        </form>`;

      expect(check()).toBe(false);
    });

    it('When a visible Blocks-style container is marked invalid, Then should return true', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form">
          <div class="wc-block-components-text-input woocommerce-invalid">
            <input name="shipping_first_name" value="" />
          </div>
        </form>`;

      expect(check()).toBe(true);
    });

    it('When one stale container is filled and another invalid container is empty, Then should keep the real error', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid-required-field">
            <input name="billing_state" value="SP" />
          </div>
          <div class="woocommerce-invalid-required-field">
            <input name="billing_city" value="" />
          </div>
        </div>`;

      expect(check()).toBe(true);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_city',
        'hasWooCommerceValidationErrors',
      );
    });
  });

  // ===========================================================================
  // Terms checkbox — required only when unchecked
  // ===========================================================================
  describe('Given the terms acceptance checkbox', () => {
    it('When it is unchecked, Then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="checkbox" id="terms" name="terms" /></div>
        </div>`;

      expect(check()).toBe(true);
    });

    it('When it is checked, Then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="checkbox" id="terms" name="terms" checked /></div>
        </div>`;

      expect(check()).toBe(false);
    });
  });

  // ===========================================================================
  // Visible invalid fields (woocommerce-invalid without -required-field = format error)
  // ===========================================================================
  describe('Given a visible woocommerce-invalid container (format error)', () => {
    it('When the field is filled but flagged invalid, Then should NOT skip it and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <p class="woocommerce-invalid"><input type="email" name="billing_email" value="not-an-email" /></p>
        </div>`;

      expect(check()).toBe(true);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_email',
        'hasWooCommerceValidationErrors',
      );
    });
  });

  // ===========================================================================
  // Stale required-field container — all fields filled → skipped with metric
  // ===========================================================================
  describe('Given a stale woocommerce-invalid-required-field container whose fields are all filled', () => {
    it('When resolved, Then should skip it, emit the SKIPPED metric and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <p class="woocommerce-invalid-required-field"><input type="text" name="billing_city" value="Buenos Aires" /></p>
        </div>`;

      expect(check()).toBe(false);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        'billing_city',
        'visibleInvalidFields',
      );
    });

    it('When one of its fields is empty, Then should NOT skip it and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <p class="woocommerce-invalid-required-field"><input type="text" name="billing_city" value="" /></p>
        </div>`;

      expect(check()).toBe(true);
    });
  });

  // ===========================================================================
  // Order Pay page — validation scopes to #order_review
  // ===========================================================================
  describe('Given the Order Pay page', () => {
    it('When a required field inside #order_review is empty, Then should return true', () => {
      document.body.className = 'woocommerce-order-pay';
      document.body.innerHTML = `
        <div id="order_review">
          <div class="validate-required"><input type="text" name="billing_state" value="" /></div>
        </div>`;

      expect(check()).toBe(true);
    });

    it('When the empty required field is outside #order_review, Then should ignore it and return false', () => {
      document.body.className = 'woocommerce-order-pay';
      document.body.innerHTML = `
        <div id="order_review"></div>
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="text" name="billing_state" value="" /></div>
        </div>`;

      expect(check()).toBe(false);
    });
  });

  // ===========================================================================
  // Metric field names — joined and de-duplicated
  // ===========================================================================
  describe('Given several empty required fields', () => {
    it('When resolved, Then should emit FORM_VALIDATION_ERROR with the unique field names joined by "/"', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="text" name="billing_first_name" value="" /></div>
          <div class="validate-required"><input type="text" name="billing_last_name" value="" /></div>
        </div>`;

      expect(check()).toBe(true);
      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_first_name/billing_last_name',
        'hasWooCommerceValidationErrors',
      );
    });
  });

  // ===========================================================================
  // Resilience — metrics dependency absent
  // ===========================================================================
  describe('Given the metrics sink is absent', () => {
    it('When there are errors, Then should still return true without throwing', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required"><input type="text" name="billing_first_name" value="" /></div>
        </div>`;

      expect(() => hasWooCommerceValidationErrors(undefined)).not.toThrow();
      expect(hasWooCommerceValidationErrors(undefined)).toBe(true);
    });
  });
});

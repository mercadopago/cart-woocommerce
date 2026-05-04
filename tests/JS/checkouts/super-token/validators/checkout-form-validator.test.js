const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const validatorPath = resolveAlias('assets/js/checkouts/super-token/validators/checkout-form-validator.js');

describe('hasWooCommerceValidationErrors (CDN - checkout-form-validator)', () => {
  beforeAll(() => {
    loadFile(validatorPath, 'window.hasWooCommerceValidationErrors', {});
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
    global.window.mpSuperTokenMetrics = { sendMetric: jest.fn() };
  });

  // =========================================================================
  // No validation errors
  // =========================================================================
  describe('given a checkout form with no validation errors', () => {
    it('when all required fields are filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="John Doe" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the form has no fields at all, then should return false', () => {
      document.body.innerHTML = '<div class="woocommerce-checkout"></div>';

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Visible invalid fields (woocommerce-invalid classes)
  // =========================================================================
  describe('given visible fields with woocommerce-invalid classes', () => {
    it('when a field has the woocommerce-invalid class, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a field has the woocommerce-invalid-required-field class, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid-required-field">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the woocommerce-invalid field is inside a hidden container, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="display: none;">
            <div class="woocommerce-invalid">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Empty required fields
  // =========================================================================
  describe('given required fields in the checkout form', () => {
    it('when a required text input is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a required text input contains only whitespace, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="   " />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a required select has an empty value, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <select><option value="">Select</option></select>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when all required fields are filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="John" />
          </div>
          <div class="validate-required">
            <select><option value="BR">Brazil</option></select>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Hidden and disabled fields should be skipped
  // =========================================================================
  describe('given required fields that are hidden or disabled', () => {
    it('when a required field is of type hidden, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="hidden" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a required field is disabled, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" disabled />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Fields inside hidden containers — generic traversal
  // =========================================================================
  describe('given required fields inside hidden containers', () => {
    it('when the field is inside a direct parent with display:none, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="display: none;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the field is 3 levels deep inside a hidden container (kShipping Argentina pattern), then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <ul id="shipping_method">
            <li>
              <input type="radio" name="shipping_method[0]" value="kshippingargentina-97" checked />
            </li>
            <li>
              <input type="radio" name="shipping_method[0]" value="kshippingargentina-98" />
              <div class="custom-office_kshippingargentina" style="display: none;">
                <p class="form-row validate-required">
                  <select name="kshippingargentina_method_office[98]" aria-hidden="true" tabindex="-1">
                    <option value="">Elige uno...</option>
                  </select>
                </p>
              </div>
            </li>
          </ul>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the hidden container becomes visible and field is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="custom-office_kshippingargentina" style="display: flex;">
            <p class="form-row validate-required">
              <select name="kshippingargentina_method_office[98]" aria-hidden="true" tabindex="-1">
                <option value="">Elige uno...</option>
              </select>
            </p>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the hidden container is visible and field has a value, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="custom-office_kshippingargentina" style="display: flex;">
            <p class="form-row validate-required">
              <select name="kshippingargentina_method_office[98]">
                <option value="SMM#10252" selected>SAN MARTIN</option>
              </select>
            </p>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Terms and conditions checkbox
  // =========================================================================
  describe('given a terms and conditions checkbox with id="terms"', () => {
    it('when the terms checkbox is unchecked, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="checkbox" id="terms" name="terms" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the terms checkbox is checked, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="checkbox" id="terms" name="terms" checked />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Form scope — Order Pay vs Standard Checkout
  // =========================================================================
  describe('given different checkout page contexts', () => {
    it('when on the order pay page, then should scope required fields to #order_review', () => {
      document.body.innerHTML = `
        <form id="order_review">
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </form>
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;
      document.body.classList.add('woocommerce-order-pay');

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when on the standard checkout page, then should scope required fields to .woocommerce-checkout', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when on the order pay page, then should ignore empty fields outside #order_review', () => {
      document.body.innerHTML = `
        <form id="order_review">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </form>
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;
      document.body.classList.add('woocommerce-order-pay');

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Metric via mpSuperTokenMetrics
  // =========================================================================
  describe('given the validation result', () => {
    it('when a single empty required field exists, then metric value should contain its name', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" name="billing_first_name" value="" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_first_name',
        'hasWooCommerceValidationErrors'
      );
    });

    it('when multiple empty required fields exist, then metric value should contain all names comma-separated', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" name="billing_first_name" value="" />
          </div>
          <div class="validate-required">
            <input type="text" name="billing_phone" value="" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_first_name,billing_phone',
        'hasWooCommerceValidationErrors'
      );
    });

    it('when woocommerce-invalid container exists, then metric value should include the field name from inside it', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="billing_email_field" class="woocommerce-invalid">
            <input type="email" name="billing_email" value="invalid" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'billing_email',
        'hasWooCommerceValidationErrors'
      );
    });

    it('when field has no name, then metric value should fallback to id or type', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" id="custom_field" value="" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_FORM_VALIDATION_ERROR',
        'custom_field',
        'hasWooCommerceValidationErrors'
      );
    });

    it('when no validation errors, then should not emit metric', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalled();
    });

    it('when mpSuperTokenMetrics is not available, then should be a silent error and return the validation result', () => {
      delete window.mpSuperTokenMetrics;
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(() => window.hasWooCommerceValidationErrors()).not.toThrow();
      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });
  });
});

const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const validatorPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/validators/checkout-form-validator.js`);

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

    it('when a field has woocommerce-invalid-required-field class but the field is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid-required-field">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a field has woocommerce-invalid-required-field class but the field is now filled, then should return false (stale class — JS-populated without re-validation)', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid-required-field">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
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
  // Fields inside containers hidden by opacity:0 (e.g. FunnelKit login modal)
  // =========================================================================
  describe('given required fields inside containers hidden by opacity:0', () => {
    it('when a required field is inside a direct parent with opacity:0, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="opacity: 0;">
            <div class="validate-required">
              <input type="text" name="username" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a required field is 5 levels deep inside an opacity:0 ancestor (FunnelKit pattern), then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="wfacp-sec-wrapper" style="opacity: 0;">
            <div class="wfacp-quickv-panel">
              <div class="wfacp-login-form">
                <div class="form-row-wide">
                  <div class="validate-required">
                    <input type="text" name="username" value="" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a required field is inside a container with opacity:0.5 (partial transparency), then should treat as visible and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="opacity: 0.5;">
            <div class="validate-required">
              <input type="text" name="billing_first_name" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a required field is inside a container with opacity:1 (fully visible), then should return true (regression)', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="opacity: 1;">
            <div class="validate-required">
              <input type="text" name="billing_first_name" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a woocommerce-invalid container is inside an opacity:0 ancestor, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="opacity: 0;">
            <div class="woocommerce-invalid">
              <input type="text" name="username" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when opacity:0 is applied via external <style> tag (FunnelKit login-flow.css real pattern), then should skip the field and return false', () => {
      document.body.innerHTML = `
        <style>.wfacp-quickv-panel { opacity: 0; }</style>
        <div class="woocommerce-checkout">
          <div id="wfacp-sec-wrapper">
            <div class="wfacp-quickv-panel">
              <div class="validate-required">
                <input type="text" name="username" value="" />
              </div>
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Fields inside containers hidden by visibility:hidden
  // Conditional check — only applies to WooCommerce-registered fields (form-row id="*_field")
  // =========================================================================
  describe('given required fields inside containers hidden by visibility:hidden', () => {
    it('when a WooCommerce-registered field (form-row id="*_field") has visibility:hidden ancestor, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility: hidden;">
            <p class="form-row validate-required" id="billing_first_name_field">
              <input type="text" name="billing_first_name" value="" />
            </p>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a non-registered custom field (no form-row id="*_field") has visibility:hidden ancestor, then should NOT skip it and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility: hidden;">
            <div class="validate-required" id="wfacp-login-section">
              <input type="text" name="username" value="" />
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a WooCommerce-registered field has visibility:hidden 4 levels deep in an ancestor, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility: hidden;">
            <div class="wrapper-level-1">
              <div class="wrapper-level-2">
                <div class="wrapper-level-3">
                  <p class="form-row validate-required" id="billing_email_field">
                    <input type="email" name="billing_email" value="" />
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a woocommerce-invalid container (form-row id="*_field") has visibility:hidden ancestor, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div style="visibility: hidden;">
            <p class="form-row woocommerce-invalid" id="billing_email_field">
              <input type="email" name="billing_email" value="" />
            </p>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Blocks Checkout DOM compatibility
  // The validator is primarily designed for Classic checkout but must remain
  // safe when invoked in a Blocks-checkout context. In Blocks, fields do not
  // follow the `.validate-required` or `*_field` conventions, so the
  // visibility:hidden enhancement intentionally does NOT apply — preferring
  // to block submission over silently letting custom fields pass.
  // =========================================================================
  describe('given a Blocks checkout DOM structure', () => {
    it('when the form has no .woocommerce-checkout wrapper (pure Blocks), then should gracefully return false', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form" class="wc-block-components-form wc-block-checkout__form">
          <div class="wc-block-components-text-input wc-block-components-address-form__first_name is-active">
            <input type="text" id="shipping-first_name" name="shipping_first_name" value="" required />
            <label for="shipping-first_name">First name</label>
          </div>
        </form>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a Blocks-style container has woocommerce-invalid inside opacity:0 ancestor, then should filter it out and return false', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form">
          <div style="opacity: 0;">
            <div class="wc-block-components-text-input wc-block-components-address-form__first_name woocommerce-invalid">
              <input type="text" id="shipping-first_name" name="shipping_first_name" value="" />
            </div>
          </div>
        </form>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a Blocks-style container has woocommerce-invalid inside visibility:hidden ancestor (no *_field), then should NOT filter and return true (safety default)', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form">
          <div style="visibility: hidden;">
            <div class="wc-block-components-text-input wc-block-components-address-form__first_name woocommerce-invalid">
              <input type="text" id="shipping-first_name" name="shipping_first_name" value="" />
            </div>
          </div>
        </form>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a Blocks-style container has woocommerce-invalid inside display:none ancestor, then should filter it out (regression)', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form">
          <div style="display: none;">
            <div class="wc-block-components-text-input wc-block-components-address-form__first_name woocommerce-invalid">
              <input type="text" id="shipping-first_name" name="shipping_first_name" value="" />
            </div>
          </div>
        </form>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a Blocks-style visible container has woocommerce-invalid, then should NOT filter and return true (regression)', () => {
      document.body.innerHTML = `
        <form id="blocks_checkout_form">
          <div class="wc-block-components-text-input wc-block-components-address-form__first_name woocommerce-invalid">
            <input type="text" id="shipping-first_name" name="shipping_first_name" value="" />
          </div>
        </form>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
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

    it('when multiple empty required fields exist, then metric value should contain all names slash-separated', () => {
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
        'billing_first_name/billing_phone',
        'hasWooCommerceValidationErrors'
      );
    });

    it('when woocommerce-invalid container has an empty field, then metric value should include the field name', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="billing_email_field" class="woocommerce-invalid">
            <input type="email" name="billing_email" value="" />
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

    it('when woocommerce-invalid-required-field container has a filled field (stale class), then skip metric should fire instead', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="billing_email_field" class="woocommerce-invalid woocommerce-invalid-required-field">
            <input type="email" name="billing_email" value="user@example.com" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        'billing_email',
        'visibleInvalidFields'
      );
    });

    it('when woocommerce-invalid container (without required-field) has a format-invalid value (e.g. bad email), then should return true and NOT skip', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div id="billing_email_field" class="woocommerce-invalid">
            <input type="email" name="billing_email" value="notanemail" />
          </div>
        </div>
      `;

      const result = window.hasWooCommerceValidationErrors();

      expect(result).toBe(true);
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        expect.anything(),
        expect.anything()
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

  // =========================================================================
  // Stale woocommerce-invalid-required-field containers with JS-populated fields
  // Vector 2: store fills field via .val() without .trigger('change')/.trigger('input'),
  // leaving the woocommerce-invalid-required-field class on the container even
  // though the field has a value. Skip is restricted to this class — format errors
  // (just `woocommerce-invalid` without `-required-field`) must NOT be skipped.
  // =========================================================================
  describe('given stale woocommerce-invalid-required-field containers with JS-populated fields', () => {
    it('when container-wrapper has woocommerce-invalid-required-field and field is filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when container-wrapper has woocommerce-invalid-required-field and field is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when direct input element has woocommerce-invalid-required-field class and has value, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <input class="validate-required woocommerce-invalid woocommerce-invalid-required-field" type="text" name="_billing_state" value="Jalisco" />
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when direct input element has woocommerce-invalid-required-field class and is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <input class="validate-required woocommerce-invalid woocommerce-invalid-required-field" type="text" name="_billing_state" value="" />
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when select inside woocommerce-invalid-required-field container has a selected value, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <select name="_billing_state">
              <option value="">Select</option>
              <option value="CMX" selected>Ciudad de México</option>
            </select>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when textarea inside woocommerce-invalid-required-field container has a value, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <textarea name="custom_notes">some value filled via JS</textarea>
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when checkbox inside woocommerce-invalid-required-field container is unchecked, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="checkbox" name="custom_consent" value="1" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when checkbox inside woocommerce-invalid-required-field container is checked, then should return false (stale)', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="checkbox" name="custom_consent" value="1" checked />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when radio inside woocommerce-invalid-required-field container is unchecked, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="radio" name="custom_option" value="option_a" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when container has two fields and first is filled but second is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
            <input type="text" name="_billing_city" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when container has two fields and both are filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
            <input type="text" name="_billing_city" value="CDMX" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when field has only whitespace, then container should NOT be skipped and should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="   " />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when woocommerce-invalid-required-field container has only hidden inputs, then should return true (guard fields.length > 0)', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="hidden" name="nonce" value="abc123" />
          </div>
        </div>
      `;

      const result = window.hasWooCommerceValidationErrors();

      expect(result).toBe(true);
      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        expect.anything(),
        expect.anything()
      );
    });

    it('when multiple containers: one filled (stale) and one empty (valid error), then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
          </div>
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="billing_company" value="" />
          </div>
        </div>
      `;

      expect(window.hasWooCommerceValidationErrors()).toBe(true);
    });
  });

  // =========================================================================
  // MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED metric
  // =========================================================================
  describe('given the MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED metric', () => {
    it('when a stale container is skipped, then metric should fire with the field name', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        '_billing_state',
        'visibleInvalidFields'
      );
    });

    it('when field has no name, then metric should fallback to id', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" id="custom_state_field" value="Jalisco" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        'custom_state_field',
        'visibleInvalidFields'
      );
    });

    it('when field has no name and no id, then metric should fallback to unknown', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" value="Jalisco" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        'unknown',
        'visibleInvalidFields'
      );
    });

    it('when container is empty (not stale), then skip metric should not fire', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        expect.anything(),
        expect.anything()
      );
    });

    it('when mpSuperTokenMetrics is unavailable, then should not throw when skipping stale container', () => {
      delete window.mpSuperTokenMetrics;
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
          </div>
        </div>
      `;

      expect(() => window.hasWooCommerceValidationErrors()).not.toThrow();
      expect(window.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when stale container has multiple fields filled, then metric value should contain field names slash-separated', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row validate-required woocommerce-invalid woocommerce-invalid-required-field">
            <input type="text" name="_billing_state" value="Ciudad de México" />
            <input type="text" name="_billing_city" value="CDMX" />
          </div>
        </div>
      `;

      window.hasWooCommerceValidationErrors();

      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_INVALID_CONTAINER_WITH_VALUE_SKIPPED',
        '_billing_state/_billing_city',
        'visibleInvalidFields'
      );
    });
  });
});

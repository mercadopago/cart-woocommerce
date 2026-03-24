const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const eventHandlerPath = resolveAlias('assets/js/checkouts/custom/entities/event-handler.js');

describe('MPEventHandler - hasWooCommerceValidationErrors', () => {
  let handler;
  let MPEventHandler;

  beforeAll(() => {
    global.wc_mercadopago_custom_event_handler_params = {
      is_mobile: false,
    };

    global.jQuery = jest.fn(() => ({
      on: jest.fn(),
      submit: jest.fn(),
      block: jest.fn(),
      unblock: jest.fn(),
    }));

    global.MPSuperTokenErrorCodes = {};

    MPEventHandler = loadFile(eventHandlerPath, 'MPEventHandler', {
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';

    const cardForm = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
    };

    const threeDSHandler = {
      set3dsStatusValidationListener: jest.fn(),
    };

    handler = new MPEventHandler(cardForm, threeDSHandler);
  });

  // =========================================================================
  // No validation errors and no empty required fields
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the form has no fields at all, then should return false', () => {
      document.body.innerHTML = '<div class="woocommerce-checkout"></div>';

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a field has the woocommerce-invalid-required-field class, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid-required-field">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a field has both validate-required and woocommerce-invalid classes, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required woocommerce-invalid">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the woocommerce-invalid field is inside a hidden container, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="shipping_address" style="display: none;">
            <div class="woocommerce-invalid">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a required text input contains only whitespace, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="   " />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when a required select has an empty value, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <select><option value="">Select</option></select>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a required field is disabled, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" disabled />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Fields inside hidden containers
  // =========================================================================
  describe('given required fields inside hidden containers', () => {
    it('when the field is inside a hidden .shipping_address, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="shipping_address" style="display: none;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the field is inside a hidden .billing_address, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="billing_address" style="display: none;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the field is inside a hidden .create-account, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="create-account" style="display: none;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the field is inside a hidden .form-row, then should skip it and return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="form-row" style="display: none;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the field is inside a visible container, then should detect the empty field and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="shipping_address" style="display: block;">
            <div class="validate-required">
              <input type="text" value="" />
            </div>
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });
  });

  // =========================================================================
  // Terms and conditions checkbox (id="terms")
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the terms checkbox is checked, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="checkbox" id="terms" name="terms" checked />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a checkbox has a different id, then should not treat it as the terms checkbox', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="checkbox" id="other-checkbox" />
          </div>
        </div>
      `;

      // A checkbox without id="terms" is evaluated via field.value.trim()
      // checkbox.value defaults to "on", so it is not considered empty
      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when a checkbox has no id, then should not treat it as the terms checkbox', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="checkbox" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Form scope — Order Pay Page vs Standard Checkout
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when on the standard checkout page, then should scope required fields to .woocommerce-checkout', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when on the standard checkout page with an empty field, then should detect it and return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
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

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Combined conditions
  // =========================================================================
  describe('given multiple validation conditions at the same time', () => {
    it('when there is a visible invalid field but all required fields are filled, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid">
            <input type="text" value="filled" />
          </div>
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when there are no invalid fields but a required field is empty, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when there are both visible invalid fields and empty required fields, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="woocommerce-invalid">
            <input type="text" value="" />
          </div>
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when invalid fields are hidden and all required fields are filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="billing_address" style="display: none;">
            <div class="woocommerce-invalid">
              <input type="text" value="" />
            </div>
          </div>
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when hidden and disabled fields are skipped but a visible empty field exists, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="hidden" value="" />
          </div>
          <div class="validate-required">
            <input type="text" value="" disabled />
          </div>
          <div class="validate-required">
            <input type="text" value="" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when hidden and disabled fields are skipped and the remaining field is filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="hidden" value="" />
          </div>
          <div class="validate-required">
            <input type="text" value="" disabled />
          </div>
          <div class="validate-required">
            <input type="text" value="filled" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when the terms checkbox is unchecked but all other fields are filled, then should return true', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="John" />
          </div>
          <div class="validate-required">
            <input type="checkbox" id="terms" name="terms" />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the terms checkbox is checked and all other fields are filled, then should return false', () => {
      document.body.innerHTML = `
        <div class="woocommerce-checkout">
          <div class="validate-required">
            <input type="text" value="John" />
          </div>
          <div class="validate-required">
            <input type="checkbox" id="terms" checked />
          </div>
        </div>
      `;

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });
  });

  // =========================================================================
  // Order Pay Page with terms and conditions checkbox
  // =========================================================================
  describe('given the order pay page with a terms and conditions checkbox', () => {
    it('when the terms checkbox is unchecked, then should return true', () => {
      document.body.innerHTML = `
        <form id="order_review">
          <div class="validate-required">
            <input type="checkbox" id="terms" name="terms" />
          </div>
        </form>
      `;
      document.body.classList.add('woocommerce-order-pay');

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
    });

    it('when the terms checkbox is checked, then should return false', () => {
      document.body.innerHTML = `
        <form id="order_review">
          <div class="validate-required">
            <input type="checkbox" id="terms" checked />
          </div>
        </form>
      `;
      document.body.classList.add('woocommerce-order-pay');

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });
  });
});

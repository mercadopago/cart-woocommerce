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
    global.sendMetric = jest.fn();

    MPEventHandler = loadFile(eventHandlerPath, 'MPEventHandler', {
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      sendMetric: global.sendMetric,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
    global.sendMetric.mockClear();

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
  // hasWooCommerceValidationErrors — CDN wrapper
  // Implementation lives in window.hasWooCommerceValidationErrors (CDN bundle).
  // The plugin method is a thin wrapper: delegate if available, fallback otherwise.
  // =========================================================================
  describe('hasWooCommerceValidationErrors() CDN wrapper', () => {
    afterEach(() => {
      delete window.hasWooCommerceValidationErrors;
    });

    it('when CDN function is available and returns true, then should delegate and return true', () => {
      window.hasWooCommerceValidationErrors = jest.fn().mockReturnValue(true);

      expect(handler.hasWooCommerceValidationErrors()).toBe(true);
      expect(window.hasWooCommerceValidationErrors).toHaveBeenCalledTimes(1);
    });

    it('when CDN function is available and returns false, then should delegate and return false', () => {
      window.hasWooCommerceValidationErrors = jest.fn().mockReturnValue(false);

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
      expect(window.hasWooCommerceValidationErrors).toHaveBeenCalledTimes(1);
    });

    it('when CDN function is not available, then should return false and never block the checkout', () => {
      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when CDN function is not available, then should emit MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK metric', () => {
      handler.hasWooCommerceValidationErrors();

      expect(global.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK',
        'hasWooCommerceValidationErrors not available',
        'mp_custom_checkout_validation_cdn_fallback'
      );
    });

    it('when CDN function throws, then should return false and never block the checkout', () => {
      window.hasWooCommerceValidationErrors = jest.fn().mockImplementation(() => {
        throw new Error('unexpected CDN error');
      });

      expect(handler.hasWooCommerceValidationErrors()).toBe(false);
    });

    it('when CDN function throws, then should emit MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK metric with error message', () => {
      window.hasWooCommerceValidationErrors = jest.fn().mockImplementation(() => {
        throw new Error('unexpected CDN error');
      });

      handler.hasWooCommerceValidationErrors();

      expect(global.sendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK',
        'unexpected CDN error',
        'mp_custom_checkout_validation_cdn_fallback'
      );
    });

    it('when CDN function is available, then should not emit fallback metric', () => {
      window.hasWooCommerceValidationErrors = jest.fn().mockReturnValue(false);

      handler.hasWooCommerceValidationErrors();

      expect(global.sendMetric).not.toHaveBeenCalled();
    });
  });

  // =========================================================================
  // handlePaymentMethodSelected
  // =========================================================================
  describe('handlePaymentMethodSelected()', () => {
    let superTokenTriggerHandler;
    let superTokenPaymentMethods;

    beforeEach(() => {
      superTokenPaymentMethods = {
        getPaymentMethodsListElement: jest.fn().mockReturnValue(null),
        reset: jest.fn(),
      };
      superTokenTriggerHandler = {
        isSuperTokenPaymentMethodsLoaded: jest.fn().mockReturnValue(false),
        isFetchingPaymentMethods: false,
        loadSuperToken: jest.fn().mockResolvedValue(),
        cancelLoad: jest.fn(),
      };
      handler.getSuperTokenDeps = jest.fn().mockReturnValue({
        superTokenTriggerHandler,
        superTokenPaymentMethods,
      });
      handler.isCheckoutCustomPaymentMethodSelected = jest.fn().mockReturnValue(false);
      handler.cardForm.getAmount = jest.fn().mockReturnValue('100.00');
      handler.cardForm.amount = '100.00';
    });

    it('Given Super Token is mid-fetch (isFetchingPaymentMethods=true), When non-custom method selected, Then should call cancelLoad()', () => {
      superTokenTriggerHandler.isFetchingPaymentMethods = true;

      handler.handlePaymentMethodSelected();

      expect(superTokenTriggerHandler.cancelLoad).toHaveBeenCalledTimes(1);
    });

    it('Given Super Token already loaded (isSuperTokenPaymentMethodsLoaded=true), When non-custom method selected, Then should hide list and NOT call cancelLoad()', () => {
      superTokenTriggerHandler.isSuperTokenPaymentMethodsLoaded.mockReturnValue(true);
      const listElement = { style: { setProperty: jest.fn() } };
      superTokenPaymentMethods.getPaymentMethodsListElement.mockReturnValue(listElement);

      handler.handlePaymentMethodSelected();

      expect(listElement.style.setProperty).toHaveBeenCalledWith('display', 'none', 'important');
      expect(superTokenTriggerHandler.cancelLoad).not.toHaveBeenCalled();
    });

    it('Given Super Token not present (getSuperTokenDeps returns undefined), When non-custom method selected, Then should not throw', () => {
      handler.getSuperTokenDeps = jest.fn().mockReturnValue({
        superTokenTriggerHandler: undefined,
        superTokenPaymentMethods: undefined,
      });

      expect(() => handler.handlePaymentMethodSelected()).not.toThrow();
    });

    it('Given WooCommerce fires updated_checkout (Super Token starts loading) and user quickly switches to Pix, When handlePaymentMethodSelected() fires, Then should interrupt Super Token load and list must not be present in DOM', () => {
      // Step 1: WooCommerce fires updated_checkout → handleUpdatedCheckout triggers loadSuperToken
      handler.isCheckoutCustomPaymentMethodSelected.mockReturnValue(true);
      handler.handleUpdatedCheckout();
      // loadSuperToken is in-flight (as it would be in the real implementation after the call)
      superTokenTriggerHandler.isFetchingPaymentMethods = true;

      // Step 2: user quickly switches to Pix before Super Token finishes loading
      handler.isCheckoutCustomPaymentMethodSelected.mockReturnValue(false);
      handler.handlePaymentMethodSelected();

      expect(superTokenTriggerHandler.loadSuperToken).toHaveBeenCalledTimes(1);
      expect(superTokenTriggerHandler.cancelLoad).toHaveBeenCalledTimes(1);
      expect(document.querySelector('.mp-super-token-payment-methods-list')).toBeNull();
    });
  });
});

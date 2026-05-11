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

    global.MPSuperTokenErrorCodes = {
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
      SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
      AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
      AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
      UPDATE_SECURITY_CODE_ERROR: 'UPDATE_SECURITY_CODE_ERROR',
      SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
      SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
    };
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

  // =========================================================================
  // handleCheckoutError()
  // =========================================================================
  describe('handleCheckoutError()', () => {
    let mockSuperTokenTriggerHandler;

    beforeEach(() => {
      mockSuperTokenTriggerHandler = {
        resetSuperTokenOnError: jest.fn(),
      };

      handler.getSuperTokenDeps = jest.fn().mockReturnValue({
        superTokenTriggerHandler: mockSuperTokenTriggerHandler,
      });
    });

    it('when called, then should reset hasToken and mercado_pago_submit flags', () => {
      handler.hasToken = true;
      handler.mercado_pago_submit = true;

      handler.handleCheckoutError();

      expect(handler.hasToken).toBe(false);
      expect(handler.mercado_pago_submit).toBe(false);
    });

    it('when called with Super Token active, then should call resetSuperTokenOnError with preserveSelection=true', () => {
      handler.handleCheckoutError();

      expect(mockSuperTokenTriggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(true);
    });

    it('when superTokenTriggerHandler is undefined, then should not throw', () => {
      handler.getSuperTokenDeps = jest.fn().mockReturnValue({
        superTokenTriggerHandler: undefined,
      });

      expect(() => handler.handleCheckoutError()).not.toThrow();
    });
  });
});

// =============================================================================
// T05 — Instrumentação do createCardToken via callSdkWithMetrics (mp_api_error)
// =============================================================================
describe('MPEventHandler - createToken (T05 mp_api_error instrumentation)', () => {
  let MPEventHandlerForT05;
  let handler;
  let cardFormMock;
  let sendMetricMock;
  const sdkMetricsPath = resolveAlias('assets/js/checkouts/mp-sdk-metrics.js');

  beforeAll(() => {
    sendMetricMock = jest.fn();

    // Carrega o callSdkWithMetrics real, com window.sendMetric mocado
    const callSdkWithMetrics = loadFile(sdkMetricsPath, 'callSdkWithMetrics', {
      window: { sendMetric: sendMetricMock },
    });

    // Source do event-handler.js usa window.callSdkWithMetrics — populamos no global.window
    global.window.callSdkWithMetrics = callSdkWithMetrics;

    // jQuery e demais globais já foram setados pelo describe anterior;
    // aqui re-carregamos MPEventHandler com window.callSdkWithMetrics disponível via global.
    MPEventHandlerForT05 = loadFile(eventHandlerPath, 'MPEventHandler', {
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    delete global.CheckoutPage; // evita short-circuit do installmentsWasSelected
    sendMetricMock.mockClear();

    cardFormMock = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
      scrollToCardForm: jest.fn(),
      form: {
        createCardToken: jest.fn(),
      },
    };

    handler = new MPEventHandlerForT05(cardFormMock, {
      set3dsStatusValidationListener: jest.fn(),
    });
  });

  test('TC-EH-T05-01: SDK rejeita com erro estruturado → sendMetric com mp_api_error e details corretos', async () => {
    const sdkError = {
      message: 'invalid_parameter',
      status: 400,
      cause: [{ code: 'E301', description: 'Invalid card number' }],
    };
    cardFormMock.form.createCardToken.mockRejectedValue(sdkError);

    handler.createToken();

    // Aguarda a cadeia de promises liquidar (rejeição → wrapper.catch → sendMetric → re-throw → outer catch)
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(sendMetricMock).toHaveBeenCalledWith(
      '400',
      'invalid_parameter',
      'mp_api_error',
      { api_route: 'createCardToken' }
    );
  });

  test('TC-EH-T05-02: SDK rejeita com string ("Failed to fetch", cenário offline) → sendMetric com value="0"', async () => {
    cardFormMock.form.createCardToken.mockRejectedValue('Failed to fetch');

    handler.createToken();

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(sendMetricMock).toHaveBeenCalledWith(
      '0',
      'Failed to fetch',
      'mp_api_error',
      { api_route: 'createCardToken' }
    );
  });

  test('TC-EH-T05-03: UI recovery existente continua sendo chamada após falha do SDK', async () => {
    cardFormMock.form.createCardToken.mockRejectedValue({ message: 'err' });

    handler.createToken();

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(cardFormMock.scrollToCardForm).toHaveBeenCalled();
    expect(cardFormMock.removeLoadSpinner).toHaveBeenCalled();
  });
});

describe('MPEventHandler - handleWithSuperTokenSubmit', () => {
  const { resolveAlias } = require('../../../helpers/path-resolver');
  const { loadFile } = require('../../../helpers/load-file');
  const eventHandlerPath = resolveAlias('assets/js/checkouts/custom/entities/event-handler.js');

  let handler;
  let MPEventHandler;
  let cardForm;

  beforeAll(() => {
    global.wc_mercadopago_custom_event_handler_params = { is_mobile: false };

    global.jQuery = jest.fn(() => ({
      on: jest.fn(),
      submit: jest.fn(),
      block: jest.fn(),
      unblock: jest.fn(),
    }));

    global.MPSuperTokenErrorCodes = {
      SELECT_PAYMENT_METHOD_NOT_VALID: 'SELECT_PAYMENT_METHOD_NOT_VALID',
      SELECT_PAYMENT_METHOD_ERROR: 'SELECT_PAYMENT_METHOD_ERROR',
      SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND: 'SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND',
      SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND: 'SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND',
    };

    MPEventHandler = loadFile(eventHandlerPath, 'MPEventHandler', {
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';

    cardForm = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
    };

    handler = new MPEventHandler(cardForm, { set3dsStatusValidationListener: jest.fn() });
  });

  // =========================================================================
  // validateInstallmentSelection — installment not selected
  // =========================================================================
  describe('given a super token method with a valid payment method but no installment selected', () => {
    it('when validateInstallmentSelection returns false, should call removeLoadSpinner and return early without authorizing payment', async () => {
      const mockEvent = { preventDefault: jest.fn() };

      const superTokenPaymentMethods = {
        getActivePaymentMethod: jest.fn().mockReturnValue({ token: 'token-abc' }),
        isSelectedPaymentMethodValid: jest.fn().mockReturnValue(true),
        validateInstallmentSelection: jest.fn().mockReturnValue(false),
      };

      const superTokenAuthenticator = {
        authorizePayment: jest.fn(),
        setSuperTokenValidation: jest.fn(),
      };

      handler.setSuperTokenDependencies({
        triggerHandler: {},
        authenticator: superTokenAuthenticator,
        paymentMethods: superTokenPaymentMethods,
        metrics: { registerClickOnPlaceOrderButton: jest.fn() },
        errorHandler: {},
      });

      await handler.handleWithSuperTokenSubmit(mockEvent, {});

      expect(superTokenPaymentMethods.validateInstallmentSelection).toHaveBeenCalledTimes(1);
      expect(cardForm.removeLoadSpinner).toHaveBeenCalledTimes(1);
      expect(superTokenAuthenticator.authorizePayment).not.toHaveBeenCalled();
    });
  });

  // =========================================================================
  // validateInstallmentSelection throws — generic catch cleanup
  // =========================================================================
  describe('given validateInstallmentSelection throws an unexpected error', () => {
    it('when the error propagates to the generic catch, should call resetSuperTokenOnError as non-recoverable and not authorize payment', async () => {
      const mockEvent = { preventDefault: jest.fn() };
      const domError = new Error('unexpected DOM error');

      const superTokenPaymentMethods = {
        getActivePaymentMethod: jest.fn().mockReturnValue({ token: 'token-abc' }),
        isSelectedPaymentMethodValid: jest.fn().mockReturnValue(true),
        validateInstallmentSelection: jest.fn().mockImplementation(() => { throw domError; }),
      };

      const superTokenTriggerHandler = {
        resetSuperTokenOnError: jest.fn(),
        setLastException: jest.fn(),
      };

      const superTokenAuthenticator = {
        authorizePayment: jest.fn(),
        setSuperTokenValidation: jest.fn(),
      };

      handler.setSuperTokenDependencies({
        triggerHandler: superTokenTriggerHandler,
        authenticator: superTokenAuthenticator,
        paymentMethods: superTokenPaymentMethods,
        metrics: { registerClickOnPlaceOrderButton: jest.fn() },
        errorHandler: {},
      });

      await handler.handleWithSuperTokenSubmit(mockEvent, {});

      expect(superTokenTriggerHandler.resetSuperTokenOnError).toHaveBeenCalledWith(false);
      expect(superTokenTriggerHandler.setLastException).toHaveBeenCalledWith(domError);
      expect(superTokenAuthenticator.authorizePayment).not.toHaveBeenCalled();
    });
  });
});

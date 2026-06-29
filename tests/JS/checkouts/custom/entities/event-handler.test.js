const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
// Static require so Jest's module graph links this suite to the source file.
// The class is not exported (it is executed via loadFile below), but this edge
// is what lets `jest --findRelatedTests` / diff-coverage attribute coverage here.
require('assets/js/checkouts/custom/entities/event-handler.js');
const eventHandlerPath = resolveAlias('assets/js/checkouts/custom/entities/event-handler.js');

// No-op stub: MPEventHandler constructor uses MobileCheckoutClassicObserver as a default
// parameter. Tests instantiate the handler directly without going through bindEvents,
// so the observer never runs — a no-op class is sufficient to satisfy the reference.
class MobileCheckoutClassicObserverStub { constructor() {} }

describe('MPEventHandler - validateCheckoutThenContinue', () => {
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
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      AbortController: global.AbortController,
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
  // validateCheckoutThenContinue — server-side pre-validation (wc_ajax_mp_validate_checkout)
  // Replaces the legacy CSS check: no fallback, trust the endpoint 100%.
  // =========================================================================
  describe('validateCheckoutThenContinue()', () => {
    let onValid;
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

    beforeEach(() => {
      onValid = jest.fn();
      window.wc_mercadopago_checkout_update_params = {
        validationEndpoint: 'https://store.test/?wc-ajax=mp_validate_checkout',
      };
      handler.getCheckoutForm = jest.fn().mockReturnValue({
        serialize: () => 'billing_postcode=12345',
        length: 1,
        prepend: jest.fn(),
      });
      handler.isOrderPayPage = jest.fn().mockReturnValue(false);
      handler.showCheckoutClassicLoader = jest.fn();
      handler.hideCheckoutClassicLoader = jest.fn();
    });

    afterEach(() => {
      delete global.fetch;
      delete window.wc_mercadopago_checkout_update_params;
      delete window.mpResolveCheckoutValidation;
    });

    it('Given the resolver returns PROCEED, When the form validation resolves, Then should fetch once, call onValid and preventDefault', async () => {
      window.mpResolveCheckoutValidation = jest.fn().mockReturnValue({ action: 'PROCEED' });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: true, errors: [] } }),
      });
      const event = { preventDefault: jest.fn() };

      handler.validateCheckoutThenContinue(event, onValid);
      await flush();

      expect(global.fetch).toHaveBeenCalledTimes(1);
      expect(event.preventDefault).toHaveBeenCalledTimes(1);
      expect(onValid).toHaveBeenCalledTimes(1);
      // Regression guard: the validation overlay must be cleared before handing off to the
      // continuation, otherwise createToken's card-field failures leave the form stuck loading.
      expect(handler.hideCheckoutClassicLoader).toHaveBeenCalledTimes(1);
      // PROCEED is not a fail open — the plugin emits no metric (PASSED lives on the CDN resolver).
      expect(global.sendMetric).not.toHaveBeenCalled();
      expect(handler.isValidating).toBe(false);
    });

    it('Given the resolver returns BLOCK, When the form validation resolves, Then should show the errors and NOT continue tokenization', async () => {
      const errors = [{ field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' }];
      window.mpResolveCheckoutValidation = jest.fn().mockReturnValue({ action: 'BLOCK', errors });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: false, errors } }),
      });
      handler.displayCheckoutValidationErrors = jest.fn();

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).not.toHaveBeenCalled();
      expect(handler.displayCheckoutValidationErrors).toHaveBeenCalledWith(errors);
      expect(handler.hideCheckoutClassicLoader).toHaveBeenCalledTimes(1);
      expect(handler.isValidating).toBe(false);
    });

    it('Given the route fetch fails (network), When the buyer pays, Then should fail open carrying the cause and not block', async () => {
      global.fetch = jest.fn().mockRejectedValue(new Error('Failed to fetch'));
      handler.displayCheckoutValidationErrors = jest.fn();

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).toHaveBeenCalledTimes(1);
      expect(handler.displayCheckoutValidationErrors).not.toHaveBeenCalled();
      expect(handler.hideCheckoutClassicLoader).toHaveBeenCalledTimes(1);
      expect(handler.isValidating).toBe(false);
      // Migrated: value = reason, message = cause, target (Datadog event) = FAIL_OPEN metric.
      expect(global.sendMetric).toHaveBeenCalledWith(
        'NETWORK',
        'Failed to fetch',
        'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN'
      );
    });

    it('Given the resolver returns FAIL_OPEN with a cause, When validation resolves, Then should fail open propagating reason and cause', async () => {
      window.mpResolveCheckoutValidation = jest.fn().mockReturnValue({ action: 'FAIL_OPEN', reason: 'SERVER_ERROR', detail: 'unexpected_error' });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: false, data: { error: 'unexpected_error' } }),
      });

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).toHaveBeenCalledTimes(1);
      expect(global.sendMetric).toHaveBeenCalledWith(
        'SERVER_ERROR',
        'unexpected_error',
        'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN'
      );
      expect(handler.isValidating).toBe(false);
    });

    it('Given the CDN resolver is absent, When validation resolves, Then should fail open with CDN_UNAVAILABLE and not block', async () => {
      delete window.mpResolveCheckoutValidation;
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: false, errors: [{ field: 'billing_city', message: 'x' }] } }),
      });
      handler.displayCheckoutValidationErrors = jest.fn();

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).toHaveBeenCalledTimes(1);
      expect(handler.displayCheckoutValidationErrors).not.toHaveBeenCalled();
      expect(global.sendMetric).toHaveBeenCalledWith(
        'CDN_UNAVAILABLE',
        'validate_checkout_then_continue',
        'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN'
      );
      expect(handler.isValidating).toBe(false);
    });

    it('Given the CDN resolver throws, When validation resolves, Then should fail open with CDN_ERROR carrying the cause', async () => {
      window.mpResolveCheckoutValidation = jest.fn(() => { throw new Error('resolver boom'); });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: false, errors: [] } }),
      });

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).toHaveBeenCalledTimes(1);
      expect(global.sendMetric).toHaveBeenCalledWith(
        'CDN_ERROR',
        'resolver boom',
        'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN'
      );
      expect(handler.isValidating).toBe(false);
    });

    it('when a validation is already in progress, then a second call should be ignored (single fetch)', async () => {
      // Pending fetch keeps the lock held across the second call.
      global.fetch = jest.fn().mockReturnValue(new Promise(() => {}));
      const event = { preventDefault: jest.fn() };

      handler.validateCheckoutThenContinue(event, onValid);
      handler.validateCheckoutThenContinue(event, onValid);

      expect(global.fetch).toHaveBeenCalledTimes(1);
      expect(handler.isValidating).toBe(true);
    });

    it('Given the endpoint URL is missing, When the buyer pays, Then should fail open without blocking and emit the fail-open metric', () => {
      window.wc_mercadopago_checkout_update_params = {};
      global.fetch = jest.fn();
      const event = { preventDefault: jest.fn() };

      handler.validateCheckoutThenContinue(event, onValid);

      expect(global.fetch).not.toHaveBeenCalled();
      expect(event.preventDefault).not.toHaveBeenCalled();
      expect(onValid).toHaveBeenCalledTimes(1);
      expect(global.sendMetric).toHaveBeenCalledWith(
        'ENDPOINT_MISSING',
        'validate_checkout_then_continue',
        'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN'
      );
    });

    it('when on the order-pay page, then should skip pre-validation, not fetch and call onValid directly', () => {
      // order-pay posts #order_review (woocommerce-pay-nonce), which this endpoint cannot
      // validate; pre-validating would fail open with 'server_error' on every order-pay payment.
      handler.isOrderPayPage = jest.fn().mockReturnValue(true);
      global.fetch = jest.fn();
      const event = { preventDefault: jest.fn() };

      handler.validateCheckoutThenContinue(event, onValid);

      expect(global.fetch).not.toHaveBeenCalled();
      expect(event.preventDefault).not.toHaveBeenCalled();
      expect(onValid).toHaveBeenCalledTimes(1);
      // Not a fail open — it is a deliberate skip, so no fail-open metric is emitted.
      expect(global.sendMetric).not.toHaveBeenCalled();
      expect(handler.isValidating).toBe(false);
    });

    // ---- error rendering + loader visibility ----------------------------
    describe('error rendering and loaders', () => {
      beforeEach(() => {
        // jsdom does not implement scrollIntoView
        Element.prototype.scrollIntoView = jest.fn();
      });

      it('renders the errors as a list inside the WooCommerce notice group', () => {
        document.body.innerHTML = '<div class="woocommerce-NoticeGroup-checkout"></div>';

        handler.displayCheckoutValidationErrors([
          { field: 'billing_postcode', code: 'postcode', message: 'Postcode is required' },
          { field: 'billing_email', code: 'email', message: 'Invalid email' },
        ]);

        const list = document.querySelector('.woocommerce-NoticeGroup-checkout ul.woocommerce-error');
        expect(list).not.toBeNull();
        const items = list.querySelectorAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].textContent).toBe('Postcode is required');
        expect(list.getAttribute('role')).toBe('alert');
      });

      it('prepends the error list to the checkout form when there is no notice group', () => {
        const prepend = jest.fn();
        handler.getCheckoutForm = jest.fn().mockReturnValue({ length: 1, prepend });

        handler.displayCheckoutValidationErrors([{ field: 'x', code: 'x', message: 'Some error' }]);

        expect(prepend).toHaveBeenCalledTimes(1);
        const list = prepend.mock.calls[0][0];
        expect(list.className).toBe('woocommerce-error');
        expect(list.querySelectorAll('li')).toHaveLength(1);
      });

      it('does nothing when there are no error messages', () => {
        document.body.innerHTML = '<div class="woocommerce-NoticeGroup-checkout"></div>';

        handler.displayCheckoutValidationErrors([{ field: 'x', code: 'x' }]); // no message

        expect(document.querySelector('.woocommerce-error')).toBeNull();
      });

      it('uses the card-form spinner on the Order Pay page', () => {
        handler.isOrderPayPage = jest.fn().mockReturnValue(true);
        handler.cardForm.createLoadSpinner = jest.fn();
        handler.cardForm.removeLoadSpinner = jest.fn();

        handler.showValidationLoader();
        handler.hideValidationLoader();

        expect(handler.cardForm.createLoadSpinner).toHaveBeenCalledTimes(1);
        expect(handler.cardForm.removeLoadSpinner).toHaveBeenCalledTimes(1);
      });
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

    it('when called mid-validation, then should release the isValidating lock', () => {
      // Guards against a stuck buy button: if checkout_error fires while a validation fetch is
      // still pending, the lock must be released so the buyer can retry.
      handler.isValidating = true;

      handler.handleCheckoutError();

      expect(handler.isValidating).toBe(false);
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
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
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
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
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

// =============================================================================
// T06 — Gate de validação de documento antes de createCardToken (PSW-3990)
// verifyDocument() deve ser invocada antes de createCardToken() no Classic.
// CheckoutPage.verifyDocument é um método de mp-custom-page.js disponível
// globalmente — segue o mesmo padrão de guard do gate de installments.
//
// CheckoutPage precisa ser passado no contexto do loadFile (não via global)
// pois o vm.Script tem contexto próprio e não enxerga global.CheckoutPage
// atribuído após a criação do sandbox.
// =============================================================================
describe('MPEventHandler - createToken (T06 document validation gate)', () => {
  let MPEventHandlerForT06;
  let handler;
  let cardFormMock;
  let checkoutPageMock;
  let sendMetricMock;

  beforeAll(() => {
    sendMetricMock = jest.fn();

    // Objeto mutável passado como referência no contexto do vm.
    // As propriedades são atualizadas em cada teste — o módulo enxerga as mudanças
    // porque mantém referência ao mesmo objeto.
    checkoutPageMock = {
      verifyDocument: jest.fn(),
      scrollToCheckoutCustomContainer: jest.fn(),
    };

    MPEventHandlerForT06 = loadFile(eventHandlerPath, 'MPEventHandler', {
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      CheckoutPage: checkoutPageMock,
      sendMetric: sendMetricMock,
    });
  });

  beforeEach(() => {
    // #form-checkout__identificationNumber simula o hidden input com value vazio (campo não preenchido)
    document.body.innerHTML = '<input type="hidden" id="cardTokenId">' +
      '<input type="hidden" id="form-checkout__identificationNumber" value="">' +
      '<div id="mp-doc-div"><div id="form-checkout__identificationNumber-container"></div></div>';
    Element.prototype.scrollIntoView = jest.fn();
    sendMetricMock.mockClear();
    checkoutPageMock.verifyDocument = jest.fn();
    checkoutPageMock.scrollToCheckoutCustomContainer = jest.fn();
    checkoutPageMock.setDisplayOfError = jest.fn();
    checkoutPageMock.setDisplayOfInputHelper = jest.fn();

    cardFormMock = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
      scrollToCardForm: jest.fn(),
      form: {
        createCardToken: jest.fn().mockResolvedValue({ token: 'tok_test_123' }),
      },
    };

    handler = new MPEventHandlerForT06(cardFormMock, {
      set3dsStatusValidationListener: jest.fn(),
    });
  });

  test('TC-EH-DOC-01: verifyDocument() returns false → createCardToken is not called', () => {
    checkoutPageMock.verifyDocument.mockReturnValue(false);

    handler.createToken();

    expect(cardFormMock.form.createCardToken).not.toHaveBeenCalled();
  });

  test('TC-EH-DOC-02: verifyDocument() returns false → metric sent with reason empty_field', () => {
    // DOM tem #form-checkout__identificationNumber com value="" → reason = 'empty_field'
    checkoutPageMock.verifyDocument.mockReturnValue(false);

    handler.createToken();

    expect(cardFormMock.removeLoadSpinner).toHaveBeenCalled();
    expect(sendMetricMock).toHaveBeenCalledWith(
      'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
      'empty_field',
      'mp_custom_document_validation',
      { reason: 'empty_field' }
    );
  });

  test('TC-EH-DOC-03: verifyDocument() returns false → document error and input-helper are shown', () => {
    checkoutPageMock.setDisplayOfError = jest.fn();
    checkoutPageMock.setDisplayOfInputHelper = jest.fn();
    checkoutPageMock.verifyDocument.mockReturnValue(false);

    handler.createToken();

    expect(checkoutPageMock.setDisplayOfError).toHaveBeenCalledWith('fcIdentificationNumberContainer', 'add', 'mp-error');
    expect(checkoutPageMock.setDisplayOfInputHelper).toHaveBeenCalledWith('mp-doc-number', 'flex');
  });

  test('TC-EH-DOC-04: verifyDocument() returns true and no CSS error class → createCardToken is called, no metric', () => {
    checkoutPageMock.verifyDocument.mockReturnValue(true);

    handler.createToken();

    expect(cardFormMock.form.createCardToken).toHaveBeenCalled();
    expect(sendMetricMock).not.toHaveBeenCalled();
  });

  test('TC-EH-DOC-06: verifyDocument() returns true but mp-error on container → metric sent with reason invalid_format', () => {
    // Simula CPF inválido: container tem mp-error, input tem valor não-vazio
    checkoutPageMock.verifyDocument.mockReturnValue(true);
    document.querySelector('#form-checkout__identificationNumber').value = '12345678900';
    document.querySelector('#form-checkout__identificationNumber-container').classList.add('mp-error');

    handler.createToken();

    expect(cardFormMock.form.createCardToken).not.toHaveBeenCalled();
    expect(cardFormMock.removeLoadSpinner).toHaveBeenCalled();
    expect(sendMetricMock).toHaveBeenCalledWith(
      'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
      'invalid_format',
      'mp_custom_document_validation',
      { reason: 'invalid_format' }
    );
  });

  test('TC-EH-DOC-07: verifyDocument() returns true but mp-error-2px on second container (duplicate) → metric sent with reason invalid_format', () => {
    // Simula containers duplicados: primeiro stale, segundo ativo com mp-error-2px
    checkoutPageMock.verifyDocument.mockReturnValue(true);
    document.querySelector('#form-checkout__identificationNumber').value = '543634600';
    const activeContainer = document.createElement('div');
    activeContainer.id = 'form-checkout__identificationNumber-container';
    activeContainer.classList.add('mp-error-2px');
    document.body.appendChild(activeContainer);

    handler.createToken();

    expect(cardFormMock.form.createCardToken).not.toHaveBeenCalled();
    expect(sendMetricMock).toHaveBeenCalledWith(
      'MP_CUSTOM_CHECKOUT_DOCUMENT_VALIDATION_BLOCKED',
      'invalid_format',
      'mp_custom_document_validation',
      { reason: 'invalid_format' }
    );

    document.body.removeChild(activeContainer);
  });

  test('TC-EH-DOC-05: CheckoutPage.verifyDocument not defined → createCardToken is called, no metric', () => {
    // Simula método ausente: typeof CheckoutPage.verifyDocument === 'function' é false
    // beforeEach recria verifyDocument: jest.fn() antes de cada teste — restauração manual desnecessária
    delete checkoutPageMock.verifyDocument;

    handler.createToken();

    expect(cardFormMock.form.createCardToken).toHaveBeenCalled();
    expect(sendMetricMock).not.toHaveBeenCalled();
  });
});

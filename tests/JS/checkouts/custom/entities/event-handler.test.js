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

    it('after checkout_error without validation in-flight, the next validateCheckoutThenContinue() should still call onValid()', async () => {
      // Regression guard for the stuck-button scenario: checkout_error fires after server
      // refuses payment (no fetch in-flight), leaving _validationCancelled=true. The next
      // attempt must reset the flag so onValid() is not permanently suppressed.
      window.mpResolveCheckoutValidation = jest.fn().mockReturnValue({ action: 'PROCEED' });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: true, errors: [] } }),
      });

      handler._validationAbortController = null;
      handler.handleCheckoutError();
      expect(handler._validationCancelled).toBe(true);

      handler.validateCheckoutThenContinue({ preventDefault: jest.fn() }, onValid);
      await flush();

      expect(onValid).toHaveBeenCalledTimes(1);
    });

    it('when _validationCancelled is set before the buffered response resolves, then should not call onValid()', async () => {
      // Covers the race where abort() is called after the response is fully buffered:
      // no AbortError is thrown, so the .catch() guard never fires — the .then() guard
      // must catch this case to prevent ghost state.
      window.mpResolveCheckoutValidation = jest.fn().mockReturnValue({ action: 'PROCEED' });
      global.fetch = jest.fn().mockResolvedValue({
        json: () => Promise.resolve({ success: true, data: { valid: true, errors: [] } }),
      });
      const event = { preventDefault: jest.fn() };

      handler.validateCheckoutThenContinue(event, onValid);
      // Simulate handleCheckoutError() firing before the microtask queue drains
      handler._validationCancelled = true;
      await flush();

      expect(onValid).not.toHaveBeenCalled();
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
  // handleUpdatedCheckout() — Order Pay page ownership (PPSP-1592)
  // =========================================================================
  describe('handleUpdatedCheckout() — Order Pay page', () => {
    beforeEach(() => {
      handler.cardForm.getAmount = jest.fn().mockReturnValue('100.00');
      handler.cardForm.amount = '100.00';
      handler.getSuperTokenDeps = jest.fn().mockReturnValue({ superTokenTriggerHandler: null });
      handler.isCheckoutCustomPaymentMethodSelected = jest.fn().mockReturnValue(true);
    });

    it('Given Order Pay page, When handleUpdatedCheckout fires, Then it skips the mount path (owned by the dedicated Order Pay flow)', async () => {
      handler.isOrderPayPage = jest.fn().mockReturnValue(true);

      await handler.handleUpdatedCheckout();

      expect(handler.cardForm.createLoadSpinner).not.toHaveBeenCalled();
      expect(handler.cardForm.initCardForm).not.toHaveBeenCalled();
    });

    it('Given a regular (non Order Pay) checkout with custom selected, When handleUpdatedCheckout fires, Then it runs the mount path', async () => {
      handler.isOrderPayPage = jest.fn().mockReturnValue(false);

      await handler.handleUpdatedCheckout();

      expect(handler.cardForm.createLoadSpinner).toHaveBeenCalledTimes(1);
      expect(handler.cardForm.initCardForm).toHaveBeenCalledTimes(1);
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

    it('when called mid-validation with an in-flight fetch, then should abort it to prevent ghost state', () => {
      const mockAbort = jest.fn();
      handler._validationAbortController = { abort: mockAbort };

      handler.handleCheckoutError();

      expect(mockAbort).toHaveBeenCalledTimes(1);
      expect(handler._validationAbortController).toBeNull();
    });

    it('when called mid-validation, then should set _validationCancelled so the catch handler suppresses onValid()', () => {
      handler._validationAbortController = { abort: jest.fn() };
      handler._validationCancelled = false;

      handler.handleCheckoutError();

      expect(handler._validationCancelled).toBe(true);
    });

    it('when called with no in-flight fetch, then should not throw', () => {
      handler._validationAbortController = null;

      expect(() => handler.handleCheckoutError()).not.toThrow();
    });
  });

  describe('setPayerIdentificationInfo() — normalize document number for payload (classic checkout)', () => {
    // Sets up the visible field (#form-checkout__identificationNumber) and the hidden
    // (#payerDocNumber), runs the sync and returns the value that would go to the payload.
    function syncDocNumber(visibleValue, docType = 'CNPJ') {
      document.body.innerHTML =
        `<input id="form-checkout__identificationType" value="${docType}" />` +
        '<input id="payerDocType" type="hidden" />' +
        '<input id="form-checkout__identificationNumber" />' +
        '<input id="payerDocNumber" type="hidden" />';
      document.querySelector('#form-checkout__identificationNumber').value = visibleValue;
      handler.setPayerIdentificationInfo();
      return document.querySelector('#payerDocNumber').value;
    }

    // The submitted value must be raw (no mask) and uppercase.
    // Normalization applies to ALL document types (aligns classic to Blocks).
    const cases = [
      { visible: '12.ABC.345/01DE-35', type: 'CNPJ', expected: '12ABC34501DE35', desc: 'uppercase masked alphanumeric CNPJ → raw' },
      { visible: '12.abc.345/01de-35', type: 'CNPJ', expected: '12ABC34501DE35', desc: 'lowercase masked alphanumeric CNPJ → raw uppercase' },
      { visible: '12abc3450-1de35', type: 'CNPJ', expected: '12ABC34501DE35', desc: 'malformed CNPJ (spurious hyphen) → raw uppercase' },
      { visible: '11.222.333/0001-81', type: 'CNPJ', expected: '11222333000181', desc: 'legacy numeric CNPJ → raw (no regression)' },
      { visible: '123.456.789-01', type: 'CPF', expected: '12345678901', desc: 'masked CPF → raw (normalization applies to all doc types)' },
      { visible: '12.345.678-K', type: 'RUT', expected: '12345678K', desc: 'RUT with K check digit → K preserved, mask stripped' },
    ];

    test.each(cases)('#payerDocNumber receives "$expected" from "$visible" — $desc', ({ visible, type, expected }) => {
      expect(syncDocNumber(visible, type)).toBe(expected);
    });
  });

  describe('normalizeDocumentNumber()', () => {
    test('strips the mask and uppercases the document number (masked CPF → raw)', () => {
      expect(handler.normalizeDocumentNumber('123.456.789-01')).toBe('12345678901');
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
      removeBlockOverlay: jest.fn(),
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

// =============================================================================
// Observabilidade do token vazio — createCardToken resolve sem token
// =============================================================================
describe('MPEventHandler - createToken (empty token observability)', () => {
  let MPEventHandlerLocal;
  let handler;
  let cardFormMock;
  let checkoutPageMock;
  const sdkMetricsPath = resolveAlias('assets/js/checkouts/mp-sdk-metrics.js');

  beforeAll(() => {
    const callSdkWithMetrics = loadFile(sdkMetricsPath, 'callSdkWithMetrics', {
      window: { sendMetric: jest.fn() },
    });
    global.window.callSdkWithMetrics = callSdkWithMetrics;

    // A emissão da métrica passa pelo CheckoutPage.emitGateBlockedMetric (fonte única);
    // a emissão real de sendMetric é testada em mp-custom-page.test.js. Aqui só verificamos a delegação.
    checkoutPageMock = {
      runPreSubmitGates: jest.fn(),
      emitGateBlockedMetric: jest.fn(),
    };

    MPEventHandlerLocal = loadFile(eventHandlerPath, 'MPEventHandler', {
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      CheckoutPage: checkoutPageMock,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '<input type="hidden" id="cardTokenId">';
    checkoutPageMock.runPreSubmitGates = jest.fn().mockReturnValue({ passed: true });
    checkoutPageMock.emitGateBlockedMetric = jest.fn();

    cardFormMock = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
      scrollToCardForm: jest.fn(),
      removeBlockOverlay: jest.fn(),
      form: {
        createCardToken: jest.fn(),
      },
    };

    handler = new MPEventHandlerLocal(cardFormMock, {
      set3dsStatusValidationListener: jest.fn(),
    });
  });

  test('Given createCardToken resolves without a token, When createToken runs, Then delegates the empty_token metric to CheckoutPage and still recovers the UI', async () => {
    cardFormMock.form.createCardToken.mockResolvedValue({}); // resolve sem token

    handler.createToken();

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(checkoutPageMock.emitGateBlockedMetric).toHaveBeenCalledWith(
      'CARD',
      'mp_custom_card_validation',
      'empty_token'
    );
    expect(cardFormMock.scrollToCardForm).toHaveBeenCalled();
    expect(cardFormMock.removeLoadSpinner).toHaveBeenCalled();
    expect(cardFormMock.removeBlockOverlay).toHaveBeenCalled();
  });

  test('Given createCardToken resolves with a valid token, When createToken runs, Then does not emit the empty_token metric', async () => {
    cardFormMock.form.createCardToken.mockResolvedValue({ token: 'abc123' });

    handler.createToken();

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(checkoutPageMock.emitGateBlockedMetric).not.toHaveBeenCalled();
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

  // =========================================================================
  // Refactored bundle present — delegate to the unified finalization
  // =========================================================================
  describe('given the refactored finalization bundle is present', () => {
    afterEach(() => {
      delete window.mpSuperTokenFinalizeClassic;
      delete window.mpSuperTokenMetrics;
    });

    it('when window.mpSuperTokenFinalizeClassic exists, should delegate to it with the checkout callbacks and skip the legacy inline flow', async () => {
      const mockEvent = { preventDefault: jest.fn() };
      const finalizeClassic = jest.fn().mockResolvedValue(undefined);
      window.mpSuperTokenFinalizeClassic = finalizeClassic;

      const superTokenPaymentMethods = {
        getActivePaymentMethod: jest.fn(),
        isSelectedPaymentMethodValid: jest.fn(),
        validateInstallmentSelection: jest.fn(),
      };
      handler.setSuperTokenDependencies({
        triggerHandler: {},
        authenticator: {},
        paymentMethods: superTokenPaymentMethods,
        metrics: { registerClickOnPlaceOrderButton: jest.fn() },
        errorHandler: {},
      });

      await handler.handleWithSuperTokenSubmit(mockEvent, {});

      expect(finalizeClassic).toHaveBeenCalledTimes(1);
      const deps = finalizeClassic.mock.calls[0][0];
      expect(deps.paymentMethods).toBe(superTokenPaymentMethods);
      expect(typeof deps.isOrderPayPage).toBe('function');
      expect(typeof deps.submitCheckoutForm).toBe('function');
      expect(typeof deps.submitOrderPayForm).toBe('function');
      expect(typeof deps.markPaymentReady).toBe('function');
      expect(typeof deps.removeLoader).toBe('function');
      // The legacy inline flow must not run when delegation is active.
      expect(superTokenPaymentMethods.getActivePaymentMethod).not.toHaveBeenCalled();
    });

    it('when mpSuperTokenFinalizeClassic rejects unexpectedly, should remove the loader and report a metric instead of freezing the checkout', async () => {
      const mockEvent = { preventDefault: jest.fn() };
      window.mpSuperTokenFinalizeClassic = jest.fn().mockRejectedValue(new Error('boom'));
      window.mpSuperTokenMetrics = { sendMetric: jest.fn() };
      handler.hideCheckoutClassicLoader = jest.fn();
      handler.setSuperTokenDependencies({
        triggerHandler: {},
        authenticator: {},
        paymentMethods: {},
        metrics: { registerClickOnPlaceOrderButton: jest.fn() },
        errorHandler: {},
      });

      // The safety-net catch swallows the rejection so the promise resolves (checkout not frozen).
      await expect(handler.handleWithSuperTokenSubmit(mockEvent, {})).resolves.toBeUndefined();

      expect(handler.cardForm.removeLoadSpinner).toHaveBeenCalled();
      expect(handler.hideCheckoutClassicLoader).toHaveBeenCalled();
      expect(window.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_finalize_unexpected_error',
        'boom',
        expect.any(String),
      );
    });
  });
});

// =============================================================================
// createToken delega os gates pré-submit ao CheckoutPage.runPreSubmitGates (PSW-3963).
// A lógica dos gates (cartão/parcelas/documento — ordem, reason e métrica) é testada
// em mp-custom-page.test.js. Aqui verificamos apenas a delegação e o respeito ao
// veredito { passed }.
// =============================================================================
describe('MPEventHandler - createToken (pre-submit gate delegation)', () => {
  let MPEventHandlerForGate;
  let handler;
  let cardFormMock;
  let checkoutPageMock;

  beforeAll(() => {
    checkoutPageMock = {
      runPreSubmitGates: jest.fn(),
      emitGateBlockedMetric: jest.fn(),
    };

    MPEventHandlerForGate = loadFile(eventHandlerPath, 'MPEventHandler', {
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: global.jQuery,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      CheckoutPage: checkoutPageMock,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '<input type="hidden" id="cardTokenId">';
    checkoutPageMock.runPreSubmitGates = jest.fn();
    checkoutPageMock.emitGateBlockedMetric = jest.fn();

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

    handler = new MPEventHandlerForGate(cardFormMock, {
      set3dsStatusValidationListener: jest.fn(),
    });
  });

  test('Given a gate blocks (passed:false), When createToken runs, Then runPreSubmitGates is called with the cardForm, createCardToken is not called, and it returns false', () => {
    checkoutPageMock.runPreSubmitGates.mockReturnValue({ passed: false, gate: 'installments', reason: 'not_selected' });

    const result = handler.createToken();

    expect(checkoutPageMock.runPreSubmitGates).toHaveBeenCalledWith(cardFormMock);
    expect(cardFormMock.form.createCardToken).not.toHaveBeenCalled();
    expect(result).toBe(false);
  });

  test('Given all gates pass (passed:true), When createToken runs, Then createCardToken is called', () => {
    checkoutPageMock.runPreSubmitGates.mockReturnValue({ passed: true });

    handler.createToken();

    expect(checkoutPageMock.runPreSubmitGates).toHaveBeenCalledWith(cardFormMock);
    expect(cardFormMock.form.createCardToken).toHaveBeenCalled();
  });

  test('Given CheckoutPage.runPreSubmitGates is not a function, When createToken runs, Then the gate is skipped and createCardToken is called', () => {
    delete checkoutPageMock.runPreSubmitGates;

    handler.createToken();

    expect(cardFormMock.form.createCardToken).toHaveBeenCalled();
  });

  test('Given the gate passes on the Order Pay page, When the card token is created, Then it delegates to the Order Pay 3DS submission (not the standard checkout submit)', async () => {
    checkoutPageMock.runPreSubmitGates.mockReturnValue({ passed: true });
    handler.mpFormId = 'order_review';
    handler.handle3dsPayOrderFormSubmission = jest.fn();

    handler.createToken();
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(handler.handle3dsPayOrderFormSubmission).toHaveBeenCalled();
    expect(handler.hasToken).toBe(true);
  });
});

describe('MPEventHandler - Order Pay submit routing & SDK re-submit guard', () => {
  let MPEventHandlerForSubmit;
  let handler;
  let cardFormMock;
  let checkoutPageMock;

  // Local jQuery stub: mercadoPagoFormHandler reads jQuery('#mp_checkout_type').val();
  // the routing tests drive that value explicitly.
  const jqueryStub = jest.fn((selector) => ({
    val: jest.fn(() => (selector === '#mp_checkout_type' ? 'super_token' : '')),
    on: jest.fn(),
    submit: jest.fn(),
    block: jest.fn(),
    unblock: jest.fn(),
  }));

  beforeAll(() => {
    checkoutPageMock = { runPreSubmitGates: jest.fn(), emitGateBlockedMetric: jest.fn() };

    MPEventHandlerForSubmit = loadFile(eventHandlerPath, 'MPEventHandler', {
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: jqueryStub,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      CheckoutPage: checkoutPageMock,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    checkoutPageMock.runPreSubmitGates = jest.fn();
    cardFormMock = { formMounted: false, form: { unmount: jest.fn() } };
    handler = new MPEventHandlerForSubmit(cardFormMock, { set3dsStatusValidationListener: jest.fn() });
  });

  describe('handleOrderReviewSubmit() — SDK programmatic re-submit', () => {
    test('Given a re-submit with no submitter (the SDK requestSubmit re-fire) while the Custom method is selected, When handled, Then it stops the event (incl. WooCommerce\'s later same-form block handler) and does not route to mercadoPagoFormHandler', () => {
      jest.spyOn(handler, 'isCheckoutCustomPaymentMethodSelected').mockReturnValue(true);
      jest.spyOn(handler, 'mercadoPagoFormHandler');
      const event = { preventDefault: jest.fn(), stopImmediatePropagation: jest.fn(), originalEvent: { submitter: null } };

      const result = handler.handleOrderReviewSubmit(event);

      expect(event.stopImmediatePropagation).toHaveBeenCalled();
      expect(event.preventDefault).toHaveBeenCalled();
      expect(result).toBe(false);
      expect(handler.mercadoPagoFormHandler).not.toHaveBeenCalled();
    });

    test('Given a no-submitter re-submit but the Custom method is NOT selected, When handled, Then the guard does not fire (no preventDefault) and it does not route to the Custom gate', () => {
      jest.spyOn(handler, 'isCheckoutCustomPaymentMethodSelected').mockReturnValue(false);
      jest.spyOn(handler, 'mercadoPagoFormHandler');
      const event = { preventDefault: jest.fn(), originalEvent: { submitter: null } };

      handler.handleOrderReviewSubmit(event);

      expect(event.preventDefault).not.toHaveBeenCalled();
      expect(handler.mercadoPagoFormHandler).not.toHaveBeenCalled();
    });

    test('Given a user-initiated submit (submitter present), When handled, Then it routes to mercadoPagoFormHandler', () => {
      jest.spyOn(handler, 'isCheckoutCustomPaymentMethodSelected').mockReturnValue(true);
      jest.spyOn(handler, 'mercadoPagoFormHandler').mockReturnValue(false);
      const event = { preventDefault: jest.fn(), originalEvent: { submitter: document.createElement('button') } };

      handler.handleOrderReviewSubmit(event);

      expect(handler.mercadoPagoFormHandler).toHaveBeenCalledWith(event);
    });
  });

  describe('mercadoPagoFormHandler() — Super Token does not touch the Custom gate', () => {
    test('Given the checkout type is super_token on the Order Pay page, When mercadoPagoFormHandler runs, Then it routes to the Super Token flow and never calls the Custom pre-submit gate', () => {
      document.body.innerHTML = '<form id="order_review"></form>';
      jest.spyOn(handler, 'handleWithSuperTokenSubmit').mockImplementation(() => {});
      const event = { preventDefault: jest.fn() };

      const result = handler.mercadoPagoFormHandler(event);

      expect(checkoutPageMock.runPreSubmitGates).not.toHaveBeenCalled();
      expect(handler.handleWithSuperTokenSubmit).toHaveBeenCalled();
      expect(result).toBe(false);
    });
  });
});

// =============================================================================
// mercadoPagoFormHandler — roteamento de branch (PSW-4344)
// A pré-validação server-side (validateCheckoutThenContinue) roda APENAS no
// ramo super_token; o ramo custom (cartão) tokeniza direto, sem fetch.
// =============================================================================
describe('MPEventHandler - mercadoPagoFormHandler (branch routing)', () => {
  let MPEventHandlerLocal;
  let handler;
  let checkoutType;

  beforeAll(() => {
    // jQuery mock cujo .val() lê/escreve o checkoutType corrente, permitindo
    // controlar o valor de #mp_checkout_type por teste.
    const jQueryMock = jest.fn(() => ({
      on: jest.fn(),
      submit: jest.fn(),
      block: jest.fn(),
      unblock: jest.fn(),
      val: jest.fn((value) => {
        if (value !== undefined) {
          checkoutType = value;
          return undefined;
        }
        return checkoutType;
      }),
    }));

    MPEventHandlerLocal = loadFile(eventHandlerPath, 'MPEventHandler', {
      MobileCheckoutClassicObserver: MobileCheckoutClassicObserverStub,
      jQuery: jQueryMock,
      wc_mercadopago_custom_event_handler_params: global.wc_mercadopago_custom_event_handler_params,
      MPSuperTokenErrorCodes: global.MPSuperTokenErrorCodes,
      sendMetric: global.sendMetric,
    });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    checkoutType = '';

    const cardForm = {
      formMounted: false,
      initCardForm: jest.fn(),
      createLoadSpinner: jest.fn(),
      removeLoadSpinner: jest.fn(),
    };

    handler = new MPEventHandlerLocal(cardForm, { set3dsStatusValidationListener: jest.fn() });
    handler.setMercadoPagoSessionId = jest.fn();
    handler.getSuperTokenDeps = jest.fn().mockReturnValue({});
    jest.spyOn(handler, 'setPayerIdentificationInfo').mockImplementation(() => {});
    jest.spyOn(handler, 'createToken').mockReturnValue(false);
    jest.spyOn(handler, 'validateCheckoutThenContinue').mockImplementation(() => {});
    global.window.fetch = jest.fn();
  });

  afterEach(() => {
    delete global.window.fetch;
  });

  it('Given a custom (card) submit, When mercadoPagoFormHandler runs, Then it tokenizes directly without pre-validation and returns false', () => {
    checkoutType = 'card';
    const event = { preventDefault: jest.fn() };

    const result = handler.mercadoPagoFormHandler(event, {});

    expect(handler.validateCheckoutThenContinue).not.toHaveBeenCalled();
    expect(global.window.fetch).not.toHaveBeenCalled();
    expect(handler.setPayerIdentificationInfo).toHaveBeenCalledTimes(1);
    expect(handler.createToken).toHaveBeenCalledTimes(1);
    expect(result).toBe(false);
  });

  it('Given a super_token submit, When mercadoPagoFormHandler runs, Then it still runs the pre-validation and returns false (regression guard)', () => {
    checkoutType = 'super_token';
    const event = { preventDefault: jest.fn() };

    const result = handler.mercadoPagoFormHandler(event, {});

    expect(handler.validateCheckoutThenContinue).toHaveBeenCalledTimes(1);
    expect(handler.createToken).not.toHaveBeenCalled();
    expect(result).toBe(false);
  });

  it('Given the card already has a token, When mercadoPagoFormHandler runs the custom branch, Then it does not tokenize again and returns false', () => {
    checkoutType = 'card';
    handler.hasToken = true;
    const event = { preventDefault: jest.fn() };

    const result = handler.mercadoPagoFormHandler(event, {});

    expect(handler.validateCheckoutThenContinue).not.toHaveBeenCalled();
    expect(handler.setPayerIdentificationInfo).not.toHaveBeenCalled();
    expect(handler.createToken).not.toHaveBeenCalled();
    expect(result).toBe(false);
  });

  it('Given a submit already in progress (mercado_pago_submit=true), When mercadoPagoFormHandler runs, Then it returns true and neither validates nor tokenizes', () => {
    checkoutType = 'card';
    handler.mercado_pago_submit = true;
    const event = { preventDefault: jest.fn() };

    const result = handler.mercadoPagoFormHandler(event, {});

    expect(handler.validateCheckoutThenContinue).not.toHaveBeenCalled();
    expect(handler.createToken).not.toHaveBeenCalled();
    expect(result).toBe(true);
  });

  it('Given the Order Pay page with the custom method, When mercadoPagoFormHandler runs, Then it tokenizes directly without pre-validation (Order Pay behavior unchanged)', () => {
    checkoutType = 'card';
    handler.isOrderPayPage = jest.fn().mockReturnValue(true);
    const event = { preventDefault: jest.fn() };

    const result = handler.mercadoPagoFormHandler(event, {});

    expect(handler.validateCheckoutThenContinue).not.toHaveBeenCalled();
    expect(global.window.fetch).not.toHaveBeenCalled();
    expect(handler.createToken).toHaveBeenCalledTimes(1);
    expect(result).toBe(false);
  });
});

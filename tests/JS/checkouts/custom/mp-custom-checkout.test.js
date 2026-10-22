const { resolveAlias } = require('../../helpers/path-resolver');
const { loadFile } = require('../../helpers/load-file');
require('assets/js/checkouts/custom/mp-custom-checkout.js');
require('assets/js/checkouts/custom/mp-custom-checkout.min.js');

const customCheckoutPath = resolveAlias('assets/js/checkouts/custom/mp-custom-checkout.js');
const minifiedCustomCheckoutPath = resolveAlias('assets/js/checkouts/custom/mp-custom-checkout.min.js');
const eventHandlerPath = resolveAlias('assets/js/checkouts/custom/entities/event-handler.js');

describe('MPCustomCheckoutHandler - form discovery', () => {
  let MPCustomCheckoutHandler;
  let MPCustomCheckoutHandlerWithoutMutationObserver;

  beforeAll(() => {
    const documentProxy = {
      readyState: 'loading',
      addEventListener: jest.fn(),
      querySelector: global.document.querySelector.bind(global.document),
      documentElement: global.document.documentElement,
      get body() {
        return global.document.body;
      },
    };

    MPCustomCheckoutHandler = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: {},
      CheckoutElements: {},
      MercadoPago: class {},
      MutationObserver: global.MutationObserver,
      document: documentProxy,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });

    MPCustomCheckoutHandlerWithoutMutationObserver = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: {},
      CheckoutElements: {},
      MercadoPago: class {},
      MutationObserver: undefined,
      document: documentProxy,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });
  });

  beforeEach(() => {
    document.body.replaceChildren();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('waits for a Blocks checkout form rendered after the handler starts', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    let discoveredConfig;

    handler.getFormConfig().then((formConfig) => {
      discoveredConfig = formConfig;
    });

    const blocksForm = document.createElement('form');
    blocksForm.className = 'wc-block-components-form wc-block-checkout__form';
    document.body.append(blocksForm);

    await Promise.resolve();
    await Promise.resolve();

    expect(discoveredConfig).toEqual({
      element: blocksForm,
      formId: 'blocks_checkout_form',
    });
  });

  it('keeps the existing Classic checkout form behavior', async () => {
    const classicForm = document.createElement('form');
    classicForm.name = 'checkout';
    document.body.append(classicForm);

    const handler = Object.create(MPCustomCheckoutHandler.prototype);

    await expect(handler.getFormConfig()).resolves.toEqual({
      element: classicForm,
      formId: 'checkout',
    });
  });

  it('uses polling when MutationObserver is unavailable', async () => {
    const handler = Object.create(MPCustomCheckoutHandlerWithoutMutationObserver.prototype);
    const formPromise = handler.getFormConfig();
    const blocksForm = document.createElement('form');
    blocksForm.className = 'wc-block-components-form wc-block-checkout__form';
    document.body.append(blocksForm);

    jest.advanceTimersByTime(500);

    await expect(formPromise).resolves.toEqual({
      element: blocksForm,
      formId: 'blocks_checkout_form',
    });
  });

  it.each([
    ['Blocks', 'wc-block-components-form wc-block-checkout__form', 'blocks_checkout_form'],
    ['order pay', 'order-review', 'order_review'],
  ])('finds the %s checkout form', (_name, className, formId) => {
    const form = document.createElement('form');
    form.className = className;
    if (formId === 'order_review') form.id = 'order_review';
    document.body.append(form);
    const handler = Object.create(MPCustomCheckoutHandler.prototype);

    expect(handler.findFormConfig()).toEqual({ element: form, formId });
  });

  it('does not initialize outside of a checkout page', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    handler.setupFormConfiguration = jest.fn();
    document.body.className = 'woocommerce-cart';

    await handler.init();

    expect(handler.setupFormConfiguration).not.toHaveBeenCalled();
  });

  it('binds payment events after the form and jQuery are available', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    const eventHandler = { triggeredPaymentMethodSelectedEvent: true, bindEvents: jest.fn() };
    handler.eventHandler = eventHandler;
    handler.isCheckoutPage = jest.fn().mockReturnValue(true);
    handler.setupFormConfiguration = jest.fn().mockResolvedValue(true);
    handler.waitForJQuery = jest.fn().mockResolvedValue(true);

    await handler.init();

    expect(eventHandler.bindEvents).toHaveBeenCalledTimes(1);
  });

  it('binds payment events when a third-party form override returns undefined', async () => {
    const form = document.createElement('form');
    form.name = 'checkout';
    document.body.append(form);
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    const eventHandler = { triggeredPaymentMethodSelectedEvent: true, bindEvents: jest.fn() };
    handler.eventHandler = eventHandler;
    handler.cardForm = {};
    handler.isCheckoutPage = jest.fn().mockReturnValue(true);
    handler.setupFormConfiguration = async function () {
      const formConfig = await this.getFormConfig();
      formConfig.element.id = formConfig.formId;
      this.syncFormIds(formConfig.formId);
    };
    handler.waitForJQuery = jest.fn().mockResolvedValue(true);

    await handler.init();

    expect(form.id).toBe('checkout');
    expect(handler.cardForm.mpFormId).toBe('checkout');
    expect(handler.waitForJQuery).toHaveBeenCalledTimes(1);
    expect(eventHandler.bindEvents).toHaveBeenCalledTimes(1);
  });

  it.each([
    ['source', customCheckoutPath],
    ['minified release asset', minifiedCustomCheckoutPath],
  ])('registers the card submit event with the %s when FunnelKit returns nothing', async (_name, assetPath) => {
    const form = document.createElement('form');
    form.name = 'checkout';
    document.body.append(form);
    const CheckoutHandler = loadFile(assetPath, 'MPCustomCheckoutHandler', {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: {},
      CheckoutElements: {},
      MercadoPago: class {},
      document: {
        readyState: 'loading',
        addEventListener: jest.fn(),
        querySelector: global.document.querySelector.bind(global.document),
        documentElement: global.document.documentElement,
        body: global.document.body,
      },
      MutationObserver: global.MutationObserver,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });
    const checkoutEvents = new Map();
    const jQuery = (selector) => ({
      on: (eventName, callback) => {
        if (selector === 'form.checkout') checkoutEvents.set(eventName, callback);
      },
      submit: jest.fn(),
      ready: (callback) => callback(),
    });
    const MPEventHandler = loadFile(eventHandlerPath, 'MPEventHandler', {
      jQuery,
      wc_mercadopago_custom_event_handler_params: { is_mobile: false },
      MobileCheckoutClassicObserver: class {},
    });
    const cardForm = {};
    const threeDSHandler = { set3dsStatusValidationListener: jest.fn() };
    const eventHandler = new MPEventHandler(cardForm, threeDSHandler);
    eventHandler.triggeredPaymentMethodSelectedEvent = true;
    eventHandler.initCardFormWhenReady = jest.fn();

    const handler = Object.create(CheckoutHandler.prototype);
    handler.cardForm = cardForm;
    handler.eventHandler = eventHandler;
    handler.isCheckoutPage = jest.fn().mockReturnValue(true);
    handler.waitForJQuery = jest.fn().mockResolvedValue(true);
    handler.setupFormConfiguration = async function () {
      const formConfig = await this.getFormConfig();
      formConfig.element.id = formConfig.formId;
      this.syncFormIds(formConfig.formId);
    };

    await handler.init();

    const onSubmit = checkoutEvents.get('checkout_place_order_woo-mercado-pago-custom');
    expect(onSubmit).toEqual(expect.any(Function));
    const paymentHandler = jest.spyOn(eventHandler, 'mercadoPagoFormHandler').mockReturnValue(false);
    const event = { type: 'checkout_place_order_woo-mercado-pago-custom' };
    onSubmit(event, form);
    expect(paymentHandler).toHaveBeenCalledWith(event, form);
    expect(eventHandler.mpFormId).toBe('checkout');
  });

  it('does not bind payment events when form configuration explicitly fails', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    const eventHandler = { triggeredPaymentMethodSelectedEvent: true, bindEvents: jest.fn() };
    handler.eventHandler = eventHandler;
    handler.isCheckoutPage = jest.fn().mockReturnValue(true);
    handler.setupFormConfiguration = jest.fn().mockResolvedValue(false);
    handler.waitForJQuery = jest.fn();

    await handler.init();

    expect(handler.waitForJQuery).not.toHaveBeenCalled();
    expect(eventHandler.bindEvents).not.toHaveBeenCalled();
  });

  it('waits for Custom Checkout dependencies that become available later', async () => {
    const hasDependencies = jest.spyOn(MPCustomCheckoutHandler, 'hasDependencies')
      .mockReturnValueOnce(false)
      .mockReturnValueOnce(true);

    const dependenciesPromise = MPCustomCheckoutHandler.waitForDependencies();
    jest.advanceTimersByTime(500);

    await expect(dependenciesPromise).resolves.toBe(true);
    expect(hasDependencies).toHaveBeenCalledTimes(2);
    hasDependencies.mockRestore();
  });

  it('requires CheckoutPage and CheckoutElements before initializing Custom Checkout', () => {
    const documentProxy = {
      readyState: 'loading',
      addEventListener: jest.fn(),
      querySelector: global.document.querySelector.bind(global.document),
      documentElement: global.document.documentElement,
      get body() {
        return global.document.body;
      },
    };
    const handlerWithoutCheckoutPage = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: undefined,
      CheckoutElements: {},
      MercadoPago: class {},
      window: {},
      MutationObserver: global.MutationObserver,
      document: documentProxy,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });

    expect(handlerWithoutCheckoutPage.hasDependencies()).toBe(false);

    const handlerWithoutCheckoutElements = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: {},
      CheckoutElements: undefined,
      MercadoPago: class {},
      window: {},
      MutationObserver: global.MutationObserver,
      document: documentProxy,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });

    expect(handlerWithoutCheckoutElements.hasDependencies()).toBe(false);
  });

  it('requires a usable Mercado Pago SDK before initializing Custom Checkout', () => {
    const documentProxy = {
      readyState: 'loading',
      addEventListener: jest.fn(),
      querySelector: global.document.querySelector.bind(global.document),
      documentElement: global.document.documentElement,
      get body() {
        return global.document.body;
      },
    };
    const dependencies = {
      MPCardForm: class {},
      MPThreeDSHandler: class {},
      MPEventHandler: class {},
      CheckoutPage: {},
      CheckoutElements: {},
      MutationObserver: global.MutationObserver,
      document: documentProxy,
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    };
    const handlerWithoutSdk = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      ...dependencies,
      MercadoPago: undefined,
      window: {},
    });
    const handlerWithConstructor = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      ...dependencies,
      MercadoPago: class {},
      window: {},
    });
    const handlerWithInstance = loadFile(customCheckoutPath, 'MPCustomCheckoutHandler', {
      ...dependencies,
      MercadoPago: undefined,
      window: { mpSdkInstance: { cardForm: jest.fn() } },
    });

    expect(handlerWithoutSdk.hasDependencies()).toBe(false);
    expect(handlerWithConstructor.hasDependencies()).toBe(true);
    expect(handlerWithInstance.hasDependencies()).toBe(true);
  });

  it('initializes Custom Checkout only once when the bootstrap runs twice', async () => {
    document.body.className = 'woocommerce-checkout';
    const classicForm = document.createElement('form');
    classicForm.name = 'checkout';
    document.body.append(classicForm);

    const cardForm = jest.fn();
    const threeDSHandler = jest.fn();
    const eventHandler = jest.fn().mockImplementation(function () {
      this.bindEvents = jest.fn();
    });
    const documentProxy = {
      readyState: 'loading',
      addEventListener: jest.fn(),
      querySelector: global.document.querySelector.bind(global.document),
      documentElement: global.document.documentElement,
      get body() {
        return global.document.body;
      },
    };
    const initializeCustomCheckout = loadFile(customCheckoutPath, 'initializeCustomCheckout', {
      MPCardForm: cardForm,
      MPThreeDSHandler: threeDSHandler,
      MPEventHandler: eventHandler,
      CheckoutPage: {},
      CheckoutElements: {},
      MercadoPago: class {},
      MutationObserver: global.MutationObserver,
      document: documentProxy,
      jQuery: jest.fn().mockReturnValue({ trigger: jest.fn() }),
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
    });

    await Promise.all([initializeCustomCheckout(), initializeCustomCheckout()]);

    expect(cardForm).toHaveBeenCalledTimes(1);
    expect(threeDSHandler).toHaveBeenCalledTimes(1);
    expect(eventHandler).toHaveBeenCalledTimes(1);

    delete global.window.mpCustomCheckoutHandler;
    delete global.window.mpEventHandler;
    delete global.window.mpCustomCheckoutInitializationInProgress;
  });

  it('stops waiting when Custom Checkout dependencies time out', async () => {
    const hasDependencies = jest.spyOn(MPCustomCheckoutHandler, 'hasDependencies').mockReturnValue(false);
    const timeout = MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS;
    const interval = MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS;
    MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS = 10;
    MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS = 1;
    const dependenciesPromise = MPCustomCheckoutHandler.waitForDependencies();

    await expect(dependenciesPromise).resolves.toBe(false);
    MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS = timeout;
    MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS = interval;
    hasDependencies.mockRestore();
  });

  it('stops initialization and reports telemetry when jQuery times out', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    handler.eventHandler = { triggeredPaymentMethodSelectedEvent: false, bindEvents: jest.fn() };
    handler.isCheckoutPage = jest.fn().mockReturnValue(true);
    handler.setupFormConfiguration = jest.fn().mockResolvedValue(true);
    handler.waitForJQuery = jest.fn().mockResolvedValue(false);
    jest.spyOn(MPCustomCheckoutHandler, 'reportInitializationFailure').mockImplementation(() => {});
    jest.spyOn(console, 'error').mockImplementation(() => {});

    await handler.init();

    expect(MPCustomCheckoutHandler.reportInitializationFailure).toHaveBeenCalledWith('jquery_timeout');
    expect(handler.eventHandler.bindEvents).not.toHaveBeenCalled();
    MPCustomCheckoutHandler.reportInitializationFailure.mockRestore();
    console.error.mockRestore();
  });

  it('reports telemetry when form discovery times out', async () => {
    const handler = Object.create(MPCustomCheckoutHandler.prototype);
    handler.getFormConfig = jest.fn().mockRejectedValue(new Error('timed out'));
    jest.spyOn(MPCustomCheckoutHandler, 'reportInitializationFailure').mockImplementation(() => {});
    jest.spyOn(console, 'error').mockImplementation(() => {});

    await expect(handler.setupFormConfiguration()).resolves.toBe(false);
    expect(MPCustomCheckoutHandler.reportInitializationFailure).toHaveBeenCalledWith('form_discovery_timeout');
    MPCustomCheckoutHandler.reportInitializationFailure.mockRestore();
    console.error.mockRestore();
  });

  it('reports a stable metric when the checkout form times out', async () => {
    const sendMetric = jest.fn();
    global.window.sendMetric = sendMetric;

    MPCustomCheckoutHandler.reportInitializationFailure('form_discovery_timeout');

    expect(sendMetric).toHaveBeenCalledWith(
      'form_discovery_timeout',
      'custom_checkout_initialization',
      'mp_custom_checkout_initialization_error'
    );
    delete global.window.sendMetric;
  });
});

describe('MPCustomCheckoutHandler - remaining bootstrap contracts', () => {
  function loadRuntime({ readyState = 'loading', MPCardForm = jest.fn(() => ({})) } = {}) {
    const eventHandler = { triggeredPaymentMethodSelectedEvent: false, bindEvents: jest.fn() };
    const trigger = jest.fn();
    const runtimeWindow = {};
    const documentProxy = {
      readyState,
      addEventListener: jest.fn(),
      querySelector: document.querySelector.bind(document),
      documentElement: document.documentElement,
      body: document.body,
    };
    const runtime = loadFile(customCheckoutPath,
      '({ MPCustomCheckoutHandler, initializeCustomCheckout, globals: globalThis })', {
        MPCardForm,
        MPThreeDSHandler: jest.fn(() => ({})),
        MPEventHandler: jest.fn(() => eventHandler),
        CheckoutPage: {},
        CheckoutElements: {},
        MercadoPago: jest.fn(),
        MutationObserver,
        document: documentProxy,
        window: runtimeWindow,
        jQuery: jest.fn(() => ({ trigger })),
        setTimeout,
        clearTimeout,
      });

    return { ...runtime, runtimeWindow, documentProxy, eventHandler, trigger, MPCardForm };
  }

  beforeEach(() => {
    document.body.replaceChildren();
    document.body.className = 'woocommerce-checkout';
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.restoreAllMocks();
    jest.useRealTimers();
    document.body.replaceChildren();
    document.body.className = '';
  });

  test('rejects a real form discovery timeout and disconnects its observer', async () => {
    const disconnect = jest.spyOn(MutationObserver.prototype, 'disconnect');
    const runtime = loadRuntime();
    const handler = Object.create(runtime.MPCustomCheckoutHandler.prototype);
    const failure = expect(handler.getFormConfig()).rejects.toThrow('Checkout form discovery timed out.');

    await jest.advanceTimersByTimeAsync(runtime.MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS);
    await failure;

    expect(disconnect).toHaveBeenCalledTimes(1);
    expect(jest.getTimerCount()).toBe(0);
  });

  test('resolves the real jQuery wait when the global appears during a retry', async () => {
    const runtime = loadRuntime();
    runtime.globals.jQuery = undefined;
    const handler = Object.create(runtime.MPCustomCheckoutHandler.prototype);
    const available = handler.waitForJQuery();

    runtime.globals.jQuery = jest.fn();
    await jest.advanceTimersByTimeAsync(runtime.MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS);

    await expect(available).resolves.toBe(true);
    expect(jest.getTimerCount()).toBe(0);
  });

  test('starts immediately after DOM readiness and binds the existing checkout form', async () => {
    const form = document.createElement('form');
    form.name = 'checkout';
    document.body.append(form);

    const runtime = loadRuntime({ readyState: 'complete' });
    await jest.advanceTimersByTimeAsync(0);

    expect(runtime.documentProxy.addEventListener).not.toHaveBeenCalled();
    expect(runtime.MPCardForm).toHaveBeenCalledTimes(1);
    expect(form.id).toBe('checkout');
    expect(runtime.runtimeWindow.mpCustomCheckoutHandler.cardForm.mpFormId).toBe('checkout');
    expect(runtime.trigger).toHaveBeenCalledWith('payment_method_selected');
    expect(runtime.eventHandler.bindEvents).toHaveBeenCalledTimes(1);
    expect(runtime.runtimeWindow.mpCustomCheckoutInitializationInProgress).toBe(false);
  });

  test('releases the initialization guard after a constructor failure so a later attempt can succeed', async () => {
    const form = document.createElement('form');
    form.name = 'checkout';
    document.body.append(form);
    const MPCardForm = jest.fn()
      .mockImplementationOnce(() => { throw new Error('synthetic SDK constructor failure'); })
      .mockImplementation(() => ({}));
    const runtime = loadRuntime({ MPCardForm });

    await expect(runtime.initializeCustomCheckout()).rejects.toThrow('synthetic SDK constructor failure');
    expect(runtime.runtimeWindow.mpCustomCheckoutInitializationInProgress).toBe(false);
    expect(runtime.runtimeWindow.mpCustomCheckoutHandler).toBeUndefined();

    await runtime.initializeCustomCheckout();
    await jest.advanceTimersByTimeAsync(0);

    expect(MPCardForm).toHaveBeenCalledTimes(2);
    expect(runtime.runtimeWindow.mpCustomCheckoutHandler).toBeDefined();
    expect(runtime.eventHandler.bindEvents).toHaveBeenCalledTimes(1);
    expect(runtime.runtimeWindow.mpCustomCheckoutInitializationInProgress).toBe(false);
  });
});

const CLIENT_PATH = '../../../assets/js/melidata/melidata-client.js';
const CLIENT_ID = 'melidata_woocommerce_client';
const CLIENT_URL = 'https://http2.mlstatic.com/storage/v1/plugins/melidata/woocommerce.min.js';

// Load the actual browser IIFE through Jest, so V8 attributes coverage to the source.
function loadClient() {
  jest.isolateModules(() => require(CLIENT_PATH));
}

function startPageLoad() {
  window.dispatchEvent(new Event('load'));
}

function getScript() {
  return document.getElementById(CLIENT_ID);
}

function addBlocksForm(className = 'wc-block-checkout__form') {
  const form = document.createElement('form');
  form.className = className;
  document.body.appendChild(form);
  return form;
}

describe('MeliData client loader', () => {
  let loadListeners;
  let sendBeacon;
  let originalSendBeacon;

  beforeEach(() => {
    jest.useFakeTimers();
    document.body.replaceChildren();
    delete window.melidata;
    delete window.melidataReady;
    delete window.wc;
    delete global.wp;
    global.mercadopago_melidata_params = {
      type: 'buyer',
      site_id: 'MLB',
      plugin_version: '8.9.6',
      platform_version: '10.2.0',
      location: '/checkout',
      payment_method: 'credit_card',
    };
    // Only the external SDK constructor is a double; it implements no client behavior.
    global.MelidataClient = jest.fn();
    sendBeacon = jest.fn(() => true);
    originalSendBeacon = Object.getOwnPropertyDescriptor(navigator, 'sendBeacon');
    Object.defineProperty(navigator, 'sendBeacon', { configurable: true, value: sendBeacon });
    loadListeners = [];
    const addEventListener = window.addEventListener.bind(window);
    jest.spyOn(window, 'addEventListener').mockImplementation((type, listener, options) => {
      if (type === 'load') loadListeners.push(listener);
      return addEventListener(type, listener, options);
    });
    console.warn.mockClear();
  });

  afterEach(() => {
    loadListeners.forEach(listener => window.removeEventListener('load', listener));
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
    document.body.replaceChildren();
    window.history.replaceState(null, '', '/');
    delete window.melidata;
    delete window.melidataReady;
    delete window.wc;
    delete global.wp;
    delete global.MelidataClient;
    delete global.mercadopago_melidata_params;
    if (originalSendBeacon) Object.defineProperty(navigator, 'sendBeacon', originalSendBeacon);
    else delete navigator.sendBeacon;
  });

  test('creates the readiness promise before page load without injecting a script', () => {
    loadClient();

    expect(window.melidataReady).toBeInstanceOf(Promise);
    expect(getScript()).toBeNull();
    expect(MelidataClient).not.toHaveBeenCalled();
  });

  test.each(['MLA', 'MLB', 'MLC', 'MLM', 'MCO', 'MLU', 'MPE'])(
    'loads Classic analytics with the original configuration for %s', async siteID => {
      mercadopago_melidata_params.site_id = siteID;
      loadClient();
      startPageLoad();

      const script = getScript();
      expect(script).not.toBeNull();
      expect(script.tagName).toBe('SCRIPT');
      expect(script.src).toBe(CLIENT_URL);
      expect(script.async).toBe(true);
      expect(script.defer).toBe(true);
      expect(window.melidata).toBeNull();
      expect(MelidataClient).not.toHaveBeenCalled();

      script.onload();

      expect(MelidataClient).toHaveBeenCalledTimes(1);
      expect(MelidataClient).toHaveBeenCalledWith({
        type: 'buyer', siteID, pluginVersion: '8.9.6', platformVersion: '10.2.0',
        pageLocation: '/checkout', paymentMethod: 'credit_card',
      });
      const instance = MelidataClient.mock.instances[0];
      expect(window.melidata).toBe(instance);
      await expect(window.melidataReady).resolves.toBe(instance);
    }
  );

  test('preserves seller settings parameters when initializing the same external client', async () => {
    mercadopago_melidata_params.type = 'seller';
    mercadopago_melidata_params.location = '/settings';
    mercadopago_melidata_params.payment_method = '';
    loadClient();
    startPageLoad();
    getScript().onload();

    expect(MelidataClient).toHaveBeenCalledWith({
      type: 'seller', siteID: 'MLB', pluginVersion: '8.9.6', platformVersion: '10.2.0',
      pageLocation: '/settings', paymentMethod: '',
    });
    await expect(window.melidataReady).resolves.toBe(window.melidata);
  });

  test('sends the real diagnostic payload and rejects readiness when the CDN fails', async () => {
    window.history.replaceState(null, '', '/checkout?test=1');
    loadClient();
    const rejected = expect(window.melidataReady).rejects.toThrow('Unable to load melidata script on page');
    startPageLoad();
    getScript().onerror();

    expect(sendBeacon).toHaveBeenCalledTimes(1);
    expect(sendBeacon.mock.calls[0][0]).toBe('https://api.mercadopago.com/v1/plugins/melidata/errors');
    expect(JSON.parse(sendBeacon.mock.calls[0][1])).toEqual({
      name: 'ERR_CONNECTION_REFUSED',
      message: 'Unable to load melidata script on page',
      target: CLIENT_ID,
      plugin: { version: '8.9.6' },
      platform: { name: 'woocommerce', uri: '/checkout?test=1', version: '10.2.0', location: '/checkout' },
    });
    expect(window.melidata).toBeNull();
    expect(MelidataClient).not.toHaveBeenCalled();
    await rejected;
  });

  test.each(['createElement', 'appendChild'])(
    'rejects readiness and warns for a synchronous %s failure', async operation => {
      loadClient();
      const failure = new Error('DOM unavailable');
      const rejected = expect(window.melidataReady).rejects.toBe(failure);
      const target = operation === 'createElement' ? document : document.body;
      jest.spyOn(target, operation).mockImplementation(() => { throw failure; });

      expect(startPageLoad).not.toThrow();

      expect(console.warn).toHaveBeenCalledWith(failure);
      expect(MelidataClient).not.toHaveBeenCalled();
      await rejected;
    }
  );

  describe('Blocks readiness', () => {
    test.each([
      { className: 'wc-block-checkout__form' },
      { className: 'wc-block-components-form' },
      { blockName: 'woocommerce/checkout' },
    ])('recognizes the supported Blocks marker %o and waits for both delays', marker => {
      const form = addBlocksForm(marker.className || '');
      if (marker.blockName) form.dataset.blockName = marker.blockName;
      loadClient();
      startPageLoad();

      jest.advanceTimersByTime(999);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(999);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      expect(getScript()?.src).toBe(CLIENT_URL);
      jest.advanceTimersByTime(3000);
      expect(document.querySelectorAll(`#${CLIENT_ID}`)).toHaveLength(1);
    });

    test.each([
      { label: 'wp is absent', wp: undefined },
      { label: 'wp.data is absent', wp: {} },
      { label: 'payment store is absent', wp: { data: { select: jest.fn(() => null) } } },
    ])('proceeds when $label', fixture => {
      addBlocksForm();
      if (fixture.wp) global.wp = fixture.wp;
      loadClient();
      startPageLoad();
      jest.advanceTimersByTime(2000);

      expect(getScript()?.src).toBe(CLIENT_URL);
      if (fixture.wp?.data) expect(fixture.wp.data.select).toHaveBeenCalledWith('wc/store/payment');
    });

    test('proceeds for an empty registry even if the payment store is not initialized', () => {
      addBlocksForm();
      const initialized = jest.fn(() => false);
      global.wp = { data: { select: jest.fn(() => ({ paymentMethodsInitialized: initialized })) } };
      const getPaymentMethods = jest.fn(() => ({}));
      window.wc = { wcBlocksRegistry: { getPaymentMethods } };
      loadClient();
      startPageLoad();
      jest.advanceTimersByTime(2000);

      expect(getScript()?.src).toBe(CLIENT_URL);
      expect(getPaymentMethods).toHaveBeenCalledTimes(1);
      expect(initialized).not.toHaveBeenCalled();
    });

    test('waits for literal true and uses the registry captured before polling', async () => {
      addBlocksForm();
      const initialized = jest.fn().mockReturnValueOnce(1).mockReturnValueOnce(false).mockReturnValue(true);
      global.wp = { data: { select: jest.fn(() => ({ paymentMethodsInitialized: initialized })) } };
      const getPaymentMethods = jest.fn(() => ({ mercadopago: {} }));
      window.wc = { wcBlocksRegistry: { getPaymentMethods } };
      loadClient();
      const lateRegistryAccess = jest.fn(() => { throw new Error('registry accessed after load'); });
      Object.defineProperty(window.wc, 'wcBlocksRegistry', { configurable: true, get: lateRegistryAccess });
      startPageLoad();

      jest.advanceTimersByTime(2999);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(999);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      expect(getScript()?.src).toBe(CLIENT_URL);
      expect(initialized).toHaveBeenCalledTimes(3);
      expect(lateRegistryAccess).not.toHaveBeenCalled();
      getScript().onload();
      const instance = window.melidata;
      jest.advanceTimersByTime(20000);
      expect(initialized).toHaveBeenCalledTimes(3);
      expect(document.querySelectorAll(`#${CLIENT_ID}`)).toHaveLength(1);
      await expect(window.melidataReady).resolves.toBe(instance);
    });

    test.each([undefined, {}])('uses actual payment DOM children when registry has no readiness API (%o)', registry => {
      addBlocksForm();
      global.wp = { data: { select: jest.fn(() => ({})) } };
      if (registry) window.wc = { wcBlocksRegistry: registry };
      const container = document.createElement('div');
      container.id = 'blocks_checkout_form';
      const paymentMethod = document.createElement('div');
      paymentMethod.id = 'payment-method';
      container.appendChild(paymentMethod);
      document.body.appendChild(container);
      loadClient();
      startPageLoad();
      jest.advanceTimersByTime(1000);
      expect(getScript()).toBeNull();
      paymentMethod.appendChild(document.createElement('input'));
      jest.advanceTimersByTime(1999);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      expect(getScript()?.src).toBe(CLIENT_URL);
    });

    test('rejects at the ten-second deadline and stops polling when the store never becomes ready', async () => {
      addBlocksForm();
      const initialized = jest.fn(() => false);
      global.wp = { data: { select: jest.fn(() => ({ paymentMethodsInitialized: initialized })) } };
      window.wc = { wcBlocksRegistry: { getPaymentMethods: jest.fn(() => ({ mercadopago: {} })) } };
      loadClient();
      const rejected = expect(window.melidataReady).rejects.toThrow('Melidata client load timed out');
      startPageLoad();
      let settled = false;
      window.melidataReady.then(() => { settled = true; }, () => { settled = true; });
      jest.advanceTimersByTime(9999);
      await Promise.resolve();
      expect(settled).toBe(false);
      expect(getScript()).toBeNull();
      jest.advanceTimersByTime(1);
      await rejected;
      const callsAtDeadline = initialized.mock.calls.length;
      jest.advanceTimersByTime(30000);

      expect(initialized).toHaveBeenCalledTimes(callsAtDeadline);
      expect(getScript()).toBeNull();
      expect(MelidataClient).not.toHaveBeenCalled();
    });

    test('waits until the deadline when no payment-method fallback element exists', async () => {
      addBlocksForm();
      global.wp = { data: { select: jest.fn(() => ({})) } };
      loadClient();
      const rejected = expect(window.melidataReady).rejects.toThrow('Melidata client load timed out');
      startPageLoad();
      jest.advanceTimersByTime(10000);

      expect(getScript()).toBeNull();
      await rejected;
    });
  });
});

const { resolveAlias } = require('../helpers/path-resolver');
const { loadFile } = require('../helpers/load-file');

const sessionDataRegisterPath = resolveAlias('assets/js/checkouts/mp-checkout-session-data-register.js');

describe('MPCheckoutSessionDataRegister', () => {
  beforeAll(() => {
    global.wc_mercadopago_checkout_session_data_register_params = {
      public_key: 'TEST-PUBLIC-KEY',
      locale: 'pt-BR',
    };
  });

  beforeEach(() => {
    jest.clearAllMocks();
    window.mpSdkInstance = undefined;
  });

  afterEach(() => {
    window.mpSdkInstance = undefined;
  });

  // The MercadoPago global is captured into the script context at load time, so each test
  // configures it before loading the class.
  function loadRegister() {
    return loadFile(sessionDataRegisterPath, 'MPCheckoutSessionDataRegister', global);
  }

  describe('generateFlowId()', () => {
    // 'loading' makes the bootstrap defer to a listener, isolating these method-level tests from it.
    beforeEach(() => {
      Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    });

    afterEach(() => {
      Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });
    });

    test('Given the SDK instance does not exist and the MercadoPago SDK is available, When generateFlowId() runs, Then it creates the instance and dispatches mp_sdk_instance_ready', () => {
      global.MercadoPago = jest.fn().mockImplementation(() => ({
        getSDKInstanceId: () => 'sdk-instance-id',
      }));
      const Register = loadRegister();

      const sdkInstanceReadyListener = jest.fn();
      document.addEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);

      Register.generateFlowId();

      expect(global.MercadoPago).toHaveBeenCalledWith('TEST-PUBLIC-KEY', { locale: 'pt-BR' });
      expect(window.mpSdkInstance).toBeDefined();
      expect(sdkInstanceReadyListener).toHaveBeenCalledTimes(1);

      document.removeEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);
    });

    test('Given the SDK instance already exists, When generateFlowId() runs, Then it neither recreates the instance nor dispatches the event', () => {
      global.MercadoPago = jest.fn();
      const existingInstance = { getSDKInstanceId: () => 'existing' };
      window.mpSdkInstance = existingInstance;
      const Register = loadRegister();

      const sdkInstanceReadyListener = jest.fn();
      document.addEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);

      Register.generateFlowId();

      expect(global.MercadoPago).not.toHaveBeenCalled();
      expect(window.mpSdkInstance).toBe(existingInstance);
      expect(sdkInstanceReadyListener).not.toHaveBeenCalled();

      document.removeEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);
    });

    test('Given the MercadoPago SDK is not loaded, When generateFlowId() runs, Then it returns a uuid without creating an instance or dispatching the event', () => {
      global.MercadoPago = undefined;
      const Register = loadRegister();

      const sdkInstanceReadyListener = jest.fn();
      document.addEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);

      const flowId = Register.generateFlowId();

      expect(typeof flowId).toBe('string');
      expect(flowId.length).toBeGreaterThan(0);
      expect(window.mpSdkInstance).toBeUndefined();
      expect(sdkInstanceReadyListener).not.toHaveBeenCalled();

      document.removeEventListener('mp_sdk_instance_ready', sdkInstanceReadyListener);
    });
  });

  describe('registerFlowId()', () => {
    // WebKit on iOS, excluded by the old Safari UA gate but subject to the same failure modes.
    const CHROME_IOS_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1';

    function setUserAgent(userAgent) {
      Object.defineProperty(window.navigator, 'userAgent', { value: userAgent, configurable: true });
    }

    function setReadyState(readyState) {
      Object.defineProperty(document, 'readyState', { value: readyState, configurable: true });
    }

    beforeEach(() => {
      global.MercadoPago = undefined;
      sessionStorage.clear();
      setReadyState('complete');
      window.mpHiddenInputDataFromBlocksCheckout = undefined;
      document.body.innerHTML = '';
    });

    afterEach(() => {
      sessionStorage.clear();
      setReadyState('complete');
      window.mpHiddenInputDataFromBlocksCheckout = undefined;
      document.body.innerHTML = '';
    });

    test('Given the document already past loading, When registerFlowId() runs, Then it registers the flow id immediately', () => {
      const Register = loadRegister();

      Register.registerFlowId();

      expect(sessionStorage.getItem('_mp_flow_id')).toEqual(expect.any(String));
      expect(sessionStorage.getItem('_mp_flow_id').length).toBeGreaterThan(0);
    });

    test('Given an iOS WebKit browser the old Safari gate excluded (Chrome iOS), When registerFlowId() runs, Then it still registers the flow id', () => {
      setUserAgent(CHROME_IOS_UA);
      const Register = loadRegister();

      Register.registerFlowId();

      expect(sessionStorage.getItem('_mp_flow_id')).toEqual(expect.any(String));
      expect(sessionStorage.getItem('_mp_flow_id').length).toBeGreaterThan(0);
    });

    test('Given execute() already generated a flow id this page load, When registerFlowId() runs, Then it does not regenerate', () => {
      const Register = loadRegister();
      Register.FLOW_ID = 'set-by-execute';
      sessionStorage.setItem('_mp_flow_id', 'set-by-execute');

      Register.registerFlowId();

      expect(sessionStorage.getItem('_mp_flow_id')).toBe('set-by-execute');
    });

    test('Given a stale flow id from a previous page load, When registerFlowId() runs, Then it regenerates a fresh one', () => {
      sessionStorage.setItem('_mp_flow_id', 'stale-from-previous-load');
      const Register = loadRegister();
      Register.FLOW_ID = null;

      Register.registerFlowId();

      const flowId = sessionStorage.getItem('_mp_flow_id');
      expect(flowId).toEqual(expect.any(String));
      expect(flowId).not.toBe('stale-from-previous-load');
    });

    test('Given the document still loading, When DOMContentLoaded fires, Then it registers the flow id', () => {
      setReadyState('loading');
      const Register = loadRegister();

      Register.registerFlowId();
      expect(sessionStorage.getItem('_mp_flow_id')).toBeNull();

      document.dispatchEvent(new Event('DOMContentLoaded'));

      expect(sessionStorage.getItem('_mp_flow_id')).toEqual(expect.any(String));
    });

    test('Given Blocks (no form yet), When registerFlowId() runs, Then the Blocks payload carries the same flow id so the server persists it', () => {
      const Register = loadRegister();

      Register.registerFlowId();

      const flowId = sessionStorage.getItem('_mp_flow_id');
      expect(window.mpHiddenInputDataFromBlocksCheckout).toEqual({
        'mercadopago_checkout_session[_mp_flow_id]': flowId,
      });
    });

    test('Given a form already present, When registerFlowId() runs, Then it appends the hidden input with the flow id', () => {
      document.body.innerHTML = '<form name="checkout"></form>';
      const Register = loadRegister();

      Register.registerFlowId();

      const hiddenInput = document.getElementById('_mp_flow_id');
      expect(hiddenInput).not.toBeNull();
      expect(hiddenInput.getAttribute('value')).toBe(sessionStorage.getItem('_mp_flow_id'));
    });

    test('Given a deferred script (interactive) with a form, When the fallback runs at load and DOMContentLoaded fires after, Then execute() neither duplicates the hidden input nor churns the flow id', () => {
      document.body.innerHTML = '<form name="checkout"></form>';
      setReadyState('interactive');

      const Register = loadRegister();

      const flowIdAtLoad = sessionStorage.getItem('_mp_flow_id');
      expect(flowIdAtLoad).toEqual(expect.any(String));
      expect(document.querySelectorAll('#_mp_flow_id')).toHaveLength(1);

      document.dispatchEvent(new Event('DOMContentLoaded'));

      expect(Register.FLOW_ID).toBe(flowIdAtLoad);
      expect(sessionStorage.getItem('_mp_flow_id')).toBe(flowIdAtLoad);
      expect(
        document.querySelectorAll('input[name="mercadopago_checkout_session[_mp_flow_id]"]')
      ).toHaveLength(1);
    });
  });

  describe('flow_id lifecycle', () => {
    function setReadyState(readyState) {
      Object.defineProperty(document, 'readyState', { value: readyState, configurable: true });
    }

    beforeEach(() => {
      global.MercadoPago = undefined;
      sessionStorage.clear();
      setReadyState('complete');
      window.mpHiddenInputDataFromBlocksCheckout = undefined;
      document.body.innerHTML = '';
    });

    afterEach(() => {
      sessionStorage.clear();
      setReadyState('complete');
      window.mpHiddenInputDataFromBlocksCheckout = undefined;
      document.body.innerHTML = '';
    });

    test('Given two separate page loads, When the flow id is registered on each, Then each load gets its own distinct flow id', () => {
      loadRegister();
      const firstFlowId = sessionStorage.getItem('_mp_flow_id');

      // A fresh page load re-evaluates the script with FLOW_ID reset to null.
      const secondLoad = loadRegister();
      const secondFlowId = sessionStorage.getItem('_mp_flow_id');

      expect(firstFlowId).toEqual(expect.any(String));
      expect(secondFlowId).toEqual(expect.any(String));
      expect(secondFlowId).not.toBe(firstFlowId);
      expect(secondLoad.FLOW_ID).toBe(secondFlowId);
    });

    test('Given a single page load, When the flow id is registered across the fallback and execute(), Then every sink holds the same flow id', () => {
      document.body.innerHTML = '<form name="checkout"></form>';
      setReadyState('interactive');

      const Register = loadRegister();
      document.dispatchEvent(new Event('DOMContentLoaded'));

      const flowId = Register.FLOW_ID;
      const hiddenInputs = document.querySelectorAll('input[name="mercadopago_checkout_session[_mp_flow_id]"]');

      expect(flowId).toEqual(expect.any(String));
      expect(sessionStorage.getItem('_mp_flow_id')).toBe(flowId);
      expect(hiddenInputs).toHaveLength(1);
      expect(hiddenInputs[0].getAttribute('value')).toBe(flowId);
      expect(window.mpHiddenInputDataFromBlocksCheckout).toEqual({
        'mercadopago_checkout_session[_mp_flow_id]': flowId,
      });
    });
  });
});

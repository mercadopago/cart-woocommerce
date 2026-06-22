const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const observerPath = resolveAlias('assets/js/checkouts/custom/entities/mobile-checkout-classic-observer.js');

describe('MobileCheckoutClassicObserver — PSW-4054', () => {
  let MobileCheckoutClassicObserver;
  let sendMetricMock;
  let jQueryMock;
  let handlers;
  let cardForm;
  let isSelected;
  let onCheckoutUpdate;

  beforeAll(() => {
    jest.useFakeTimers();
    sendMetricMock = jest.fn();

    MobileCheckoutClassicObserver = loadFile(observerPath, 'MobileCheckoutClassicObserver', {
      jQuery: (...args) => jQueryMock(...args),
      setTimeout: global.setTimeout,
      clearTimeout: global.clearTimeout,
      sendMetric: sendMetricMock,
      navigator: {
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
      },
    });
  });

  afterAll(() => {
    jest.useRealTimers();
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    sendMetricMock.mockClear();
    jest.clearAllTimers();

    handlers = {};
    jQueryMock = jest.fn(() => ({
      on: jest.fn((event, handler) => {
        handlers[event] = handler;
      }),
      off: jest.fn(),
    }));

    cardForm      = { formMounted: false };
    isSelected    = jest.fn().mockReturnValue(true);
    onCheckoutUpdate = jest.fn();
  });

  // -------------------------------------------------------------------------
  // _getCheckoutStructureLabel
  // -------------------------------------------------------------------------
  describe('_getCheckoutStructureLabel()', () => {
    it('should return no-wc-form when woocommerce checkout form is absent', () => {
      const obs = new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(obs._structure).toBe('no-wc-form');
    });

    it('should return form-hidden when form exists but offsetParent is null', () => {
      document.body.innerHTML = '<form class="woocommerce-checkout"></form>';
      const obs = new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(obs._structure).toBe('form-hidden');
    });

    it('should return standard when form is inside woocommerce and has offsetParent', () => {
      document.body.innerHTML = '<div class="woocommerce"><form class="woocommerce-checkout"></form></div>';
      Object.defineProperty(
        document.querySelector('form.woocommerce-checkout'),
        'offsetParent',
        { value: document.body, configurable: true }
      );
      const obs = new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(obs._structure).toBe('standard');
    });

    it('should return form-wrapped when form is outside woocommerce and not direct body child', () => {
      document.body.innerHTML = '<div class="plugin-wrapper"><form class="woocommerce-checkout"></form></div>';
      Object.defineProperty(
        document.querySelector('form.woocommerce-checkout'),
        'offsetParent',
        { value: document.body, configurable: true }
      );
      const obs = new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(obs._structure).toBe('form-wrapped');
    });
  });

  // -------------------------------------------------------------------------
  // Constructor — listener registration and metric
  // -------------------------------------------------------------------------
  describe('constructor — listener registration and metric', () => {
    it('should register cfw_pre_updated_checkout listener on document body', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(handlers['cfw_pre_updated_checkout']).toBeDefined();
    });

    it('should register updated_checkout listener on document', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(handlers['updated_checkout']).toBeDefined();
    });

    it('should emit mp_custom_checkout_mobile_started on construction', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(sendMetricMock).toHaveBeenCalledWith(
        'ios_14.0',
        'no-wc-form',
        'mp_custom_checkout_mobile_started'
      );
    });

    it('should not throw when sendMetric is absent', () => {
      const MobileCheckoutClassicObserverNoMetric = loadFile(observerPath, 'MobileCheckoutClassicObserver', {
        jQuery: (...args) => jQueryMock(...args),
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
      });
      expect(() => new MobileCheckoutClassicObserverNoMetric(cardForm, isSelected, onCheckoutUpdate)).not.toThrow();
    });
  });

  // -------------------------------------------------------------------------
  // _initTimeout — failure detector
  // -------------------------------------------------------------------------
  describe('_initTimeout failure detector', () => {
    beforeEach(() => {
      document.body.innerHTML =
        '<input type="radio" id="payment_method_woo-mercado-pago-custom" checked>' +
        '<form class="woocommerce-checkout"></form>';
    });

    it('should emit timeout metric after 10s when form not mounted and custom is selected', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).toHaveBeenCalledWith(
        'ios_14.0',
        'form-hidden/event_received:no',
        'mp_custom_checkout_mobile_timeout'
      );
    });

    it('should cancel _initTimeout when updated_checkout fires', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      handlers['updated_checkout']();
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).not.toHaveBeenCalled();
    });

    it('should cancel _initTimeout when cfw_pre_updated_checkout fires', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      handlers['cfw_pre_updated_checkout']();
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).not.toHaveBeenCalled();
    });

    it('should not emit when form is already mounted', () => {
      cardForm.formMounted = true;
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).not.toHaveBeenCalled();
    });

    it('should not emit when custom checkout is not selected', () => {
      isSelected.mockReturnValue(false);
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).not.toHaveBeenCalled();
    });

    it('should not emit when form mounts after an update event fires', () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      handlers['updated_checkout']();
      cardForm.formMounted = true; // simulate successful init
      sendMetricMock.mockClear();

      jest.advanceTimersByTime(10000);

      expect(sendMetricMock).not.toHaveBeenCalled();
    });

  });

  // -------------------------------------------------------------------------
  // guardedUpdate — deduplication
  // -------------------------------------------------------------------------
  describe('guardedUpdate deduplication', () => {
    it('should call onCheckoutUpdate once when only cfw_pre fires', async () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      handlers['cfw_pre_updated_checkout']();
      await Promise.resolve();
      expect(onCheckoutUpdate).toHaveBeenCalledTimes(1);
    });

    it('should call onCheckoutUpdate once when only updated_checkout fires', async () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      handlers['updated_checkout']();
      await Promise.resolve();
      expect(onCheckoutUpdate).toHaveBeenCalledTimes(1);
    });

    it('should call onCheckoutUpdate once when both cfw_pre and updated_checkout fire in the same cycle', async () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);

      handlers['cfw_pre_updated_checkout'](); // sets inFlight=true, schedules microtask reset
      handlers['updated_checkout']();         // inFlight=true → blocked
      await Promise.resolve();

      expect(onCheckoutUpdate).toHaveBeenCalledTimes(1);
    });

    it('should allow a second call after first cycle completes when inFlight resets via microtask', async () => {
      new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);

      handlers['updated_checkout']();

      // flush microtasks so inFlight resets: Promise.resolve() → .then(calls fn) → .finally(resets)
      await Promise.resolve();
      await Promise.resolve();
      await Promise.resolve();
      expect(onCheckoutUpdate).toHaveBeenCalledTimes(1);

      handlers['updated_checkout']();
      await Promise.resolve();
      expect(onCheckoutUpdate).toHaveBeenCalledTimes(2);
    });

    it('should reset _updateInFlight when onCheckoutUpdate rejects so next event runs', async () => {
      const flushMicrotasks = async (n = 8) => { for (let i = 0; i < n; i++) await Promise.resolve(); };
      const failingUpdate = jest.fn().mockRejectedValue(new Error('update failed'));
      new MobileCheckoutClassicObserver(cardForm, isSelected, failingUpdate);
      handlers['updated_checkout']();
      await flushMicrotasks();
      handlers['updated_checkout']();
      await flushMicrotasks();
      expect(failingUpdate).toHaveBeenCalledTimes(2);
    });

    it('should emit mp_custom_checkout_mobile_update_error when onCheckoutUpdate rejects', async () => {
      const flushMicrotasks = async (n = 8) => { for (let i = 0; i < n; i++) await Promise.resolve(); };
      const failingUpdate = jest.fn().mockRejectedValue(new Error('update failed'));
      new MobileCheckoutClassicObserver(cardForm, isSelected, failingUpdate);
      sendMetricMock.mockClear();

      handlers['updated_checkout']();
      await flushMicrotasks();

      expect(sendMetricMock).toHaveBeenCalledWith(
        'ios_14.0',
        'no-wc-form/reason:update failed',
        'mp_custom_checkout_mobile_update_error'
      );
    });

    it('should not throw on rejection when sendMetric is absent', async () => {
      const flushMicrotasks = async (n = 8) => { for (let i = 0; i < n; i++) await Promise.resolve(); };
      const ObserverNoMetric = loadFile(observerPath, 'MobileCheckoutClassicObserver', {
        jQuery: (...args) => jQueryMock(...args),
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
      });
      const failingUpdate = jest.fn().mockRejectedValue(new Error('update failed'));
      new ObserverNoMetric(cardForm, isSelected, failingUpdate);

      handlers['updated_checkout']();
      await expect(flushMicrotasks()).resolves.toBeUndefined();
    });
  });

  // -------------------------------------------------------------------------
  // _getDeviceLabel
  // -------------------------------------------------------------------------
  describe('_getDeviceLabel()', () => {
    it('should return android label for Android UA', () => {
      const AndroidObserver = loadFile(observerPath, 'MobileCheckoutClassicObserver', {
        jQuery: (...args) => jQueryMock(...args),
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
        sendMetric: sendMetricMock,
        navigator: { userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36' },
      });
      sendMetricMock.mockClear();
      new AndroidObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(sendMetricMock).toHaveBeenCalledWith(
        expect.stringMatching(/^android_/),
        expect.any(String),
        'mp_custom_checkout_mobile_started'
      );
    });

    it('should return other for a non-mobile UA', () => {
      const OtherObserver = loadFile(observerPath, 'MobileCheckoutClassicObserver', {
        jQuery: (...args) => jQueryMock(...args),
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
        sendMetric: sendMetricMock,
        navigator: { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' },
      });
      sendMetricMock.mockClear();
      new OtherObserver(cardForm, isSelected, onCheckoutUpdate);
      expect(sendMetricMock).toHaveBeenCalledWith(
        'other',
        expect.any(String),
        'mp_custom_checkout_mobile_started'
      );
    });
  });

  // -------------------------------------------------------------------------
  // destroy()
  // -------------------------------------------------------------------------
  describe('destroy()', () => {
    it('should cancel _initTimeout and prevent timeout metric from firing', () => {
      const obs = new MobileCheckoutClassicObserver(cardForm, isSelected, onCheckoutUpdate);
      sendMetricMock.mockClear();
      obs.destroy();
      jest.advanceTimersByTime(10000);
      expect(sendMetricMock).not.toHaveBeenCalled();
    });
  });
});

const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const mpSuperTokenPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/mp-super-token.js`);

function loadCheckIfSuperTokenWasInitialized(windowOverrides = {}) {
  const fileContent = fs.readFileSync(mpSuperTokenPath, 'utf8');

  // Extract only the function, avoiding top-level side effects
  // (setInterval, setTimeout, addEventListener, constructor calls).
  const fnBody = fileContent.match(
    /async function checkIfSuperTokenWasInitialized[\s\S]*?^}/m
  );

  if (!fnBody) {
    throw new Error('Could not extract checkIfSuperTokenWasInitialized from source file');
  }

  const extractedCode = `${fnBody[0]}\ncheckIfSuperTokenWasInitialized;`;

  const mockWindow = {
    sendMetric: undefined,
    mpSdkInstance: undefined,
    mpSuperTokenMetrics: undefined,
    mpSuperTokenPaymentMethods: undefined,
    mpSuperTokenAuthenticator: undefined,
    mpSuperTokenErrorHandler: undefined,
    mpSuperTokenTriggerHandler: undefined,
    ...windowOverrides,
  };

  const mockSessionStorage = {
    _store: {},
    getItem(key) { return this._store[key] ?? null; },
    setItem(key, value) { this._store[key] = String(value); },
    clear() { this._store = {}; },
  };

  const context = {
    window: mockWindow,
    sessionStorage: mockSessionStorage,
    console: { warn: jest.fn(), error: jest.fn() },
    Promise: global.Promise,
  };

  const script = new vm.Script(extractedCode);
  const fn = script.runInNewContext(context);

  return { fn, context, mockSessionStorage };
}

const SESSION_KEY = 'mp_super_token_init_checked';

describe('checkIfSuperTokenWasInitialized', () => {
  // ---------------------------------------------------------------------------
  describe('early exit: sendMetric not available', () => {
    test('Given window.sendMetric is undefined, When called, Then should exit without sending metrics', async () => {
      const { fn, context } = loadCheckIfSuperTokenWasInitialized();

      await fn('test');

      expect(context.console.warn).toHaveBeenCalledWith('MP Send Metric is not available.');
    });

    test('Given window.sendMetric is undefined, When called, Then should NOT set sessionStorage flag', async () => {
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized();

      await fn('test');

      expect(mockSessionStorage.getItem(SESSION_KEY)).toBeNull();
    });
  });

  // ---------------------------------------------------------------------------
  describe('early exit: already checked', () => {
    test('Given sessionStorage flag is set, When called, Then should not send any metric', async () => {
      const sendMetric = jest.fn();
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
      });

      mockSessionStorage.setItem(SESSION_KEY, 'true');

      await fn('test');

      expect(sendMetric).not.toHaveBeenCalled();
    });
  });

  // ---------------------------------------------------------------------------
  describe('MP_SDK_INSTANCE_NOT_EXISTS', () => {
    test('Given mpSdkInstance is undefined, When called, Then should send MP_SDK_INSTANCE_NOT_EXISTS metric', async () => {
      const sendMetric = jest.fn();
      const { fn } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn('mp_card_form_mounted');

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_SDK_INSTANCE_NOT_EXISTS',
        expect.stringContaining('MP SDK instance did not load within the expected time'),
        'mp_super_token_init_error'
      );
    });

    test('Given mpSdkInstance is undefined, When called, Then metric message should include dispatchedFrom', async () => {
      const sendMetric = jest.fn();
      const { fn } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn('my_custom_trigger');

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_SDK_INSTANCE_NOT_EXISTS',
        expect.stringContaining('Dispatched from: my_custom_trigger'),
        'mp_super_token_init_error'
      );
    });

    test('Given mpSdkInstance is undefined and dispatchedFrom is null, When called, Then dispatchedFrom should fallback to "unknown"', async () => {
      const sendMetric = jest.fn();
      const { fn } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn(null);

      expect(sendMetric).toHaveBeenCalledWith(
        'MP_SDK_INSTANCE_NOT_EXISTS',
        expect.stringContaining('Dispatched from: unknown'),
        'mp_super_token_init_error'
      );
    });

    test('Given mpSdkInstance is undefined, When called, Then should set sessionStorage flag', async () => {
      const sendMetric = jest.fn();
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn('test');

      expect(mockSessionStorage.getItem(SESSION_KEY)).toBe('true');
    });
  });

  // ---------------------------------------------------------------------------
  describe('SUPER_TOKEN_CLASSES_NOT_EXISTS', () => {
    function buildWindowWithMissingClass(missingKey) {
      const allClasses = {
        mpSuperTokenMetrics: {},
        mpSuperTokenPaymentMethods: {},
        mpSuperTokenAuthenticator: {},
        mpSuperTokenErrorHandler: {},
        mpSuperTokenTriggerHandler: { isAlreadyListeningForm: true },
      };

      delete allClasses[missingKey];

      return {
        sendMetric: jest.fn(),
        mpSdkInstance: {},
        ...allClasses,
      };
    }

    test.each([
      ['mpSuperTokenMetrics', 'Metrics class did not load.'],
      ['mpSuperTokenPaymentMethods', 'Payment Methods class did not load.'],
      ['mpSuperTokenAuthenticator', 'Authenticator class did not load.'],
      ['mpSuperTokenErrorHandler', 'Error Handler class did not load.'],
      ['mpSuperTokenTriggerHandler', 'Trigger Handler class did not load.'],
    ])('Given %s is missing, When called, Then should send SUPER_TOKEN_CLASSES_NOT_EXISTS mentioning "%s"',
      async (missingKey, expectedFragment) => {
        const windowOverrides = buildWindowWithMissingClass(missingKey);
        const { fn } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

        await fn('test');

        expect(windowOverrides.sendMetric).toHaveBeenCalledWith(
          'SUPER_TOKEN_CLASSES_NOT_EXISTS',
          expect.stringContaining(expectedFragment),
          'mp_super_token_init_error'
        );
      }
    );
  });

  // ---------------------------------------------------------------------------
  describe('SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING', () => {
    function buildFullWindow(overrides = {}) {
      return {
        sendMetric: jest.fn(),
        mpSdkInstance: {},
        mpSuperTokenMetrics: {},
        mpSuperTokenPaymentMethods: {},
        mpSuperTokenAuthenticator: {},
        mpSuperTokenErrorHandler: {},
        mpSuperTokenTriggerHandler: { isAlreadyListeningForm: false },
        ...overrides,
      };
    }

    test('Given isAlreadyListeningForm is false, When called, Then should send SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING', async () => {
      const windowOverrides = buildFullWindow();
      const { fn } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('mp_card_form_mounted');

      expect(windowOverrides.sendMetric).toHaveBeenCalledWith(
        'SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING',
        expect.stringContaining('Trigger handler is not listening'),
        'mp_super_token_init_error'
      );
    });

    test('Given isAlreadyListeningForm is false, When called, Then should NOT send success metric', async () => {
      const windowOverrides = buildFullWindow();
      const { fn } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('test');

      expect(windowOverrides.sendMetric).not.toHaveBeenCalledWith(
        'SUPER_TOKEN_INITIALIZATION_SUCCESS',
        expect.any(String),
        expect.any(String)
      );
    });
  });

  // ---------------------------------------------------------------------------
  describe('SUPER_TOKEN_INITIALIZATION_SUCCESS', () => {
    function buildSuccessWindow() {
      return {
        sendMetric: jest.fn(),
        mpSdkInstance: {},
        mpSuperTokenMetrics: {},
        mpSuperTokenPaymentMethods: {},
        mpSuperTokenAuthenticator: {},
        mpSuperTokenErrorHandler: {},
        mpSuperTokenTriggerHandler: { isAlreadyListeningForm: true },
      };
    }

    test('Given all classes loaded and trigger handler is listening, When called, Then should send success metric', async () => {
      const windowOverrides = buildSuccessWindow();
      const { fn } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('mp_card_form_mounted');

      expect(windowOverrides.sendMetric).toHaveBeenCalledWith(
        'SUPER_TOKEN_INITIALIZATION_SUCCESS',
        expect.stringContaining('Super token was initialized successfully'),
        'mp_super_token_init_success'
      );
    });

    test('Given all classes loaded, When called, Then should set sessionStorage flag', async () => {
      const windowOverrides = buildSuccessWindow();
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('test');

      expect(mockSessionStorage.getItem(SESSION_KEY)).toBe('true');
    });

    test('Given success case, When called, Then should send exactly one metric', async () => {
      const windowOverrides = buildSuccessWindow();
      const { fn } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('test');

      expect(windowOverrides.sendMetric).toHaveBeenCalledTimes(1);
    });
  });

  // ---------------------------------------------------------------------------
  describe('SUPER_TOKEN_INITIALIZATION_ERROR (catch block)', () => {
    test('Given sendMetric throws inside try block, When called, Then should send SUPER_TOKEN_INITIALIZATION_ERROR', async () => {
      let callCount = 0;
      const sendMetric = jest.fn(() => {
        callCount++;
        if (callCount === 1) throw new Error('sendMetric exploded');
      });
      const { fn } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn('test');

      expect(sendMetric).toHaveBeenCalledWith(
        'SUPER_TOKEN_INITIALIZATION_ERROR',
        expect.stringContaining('sendMetric exploded'),
        'mp_super_token_init_error'
      );
    });

    test('Given error in catch, When called, Then sessionStorage flag should still be set (finally block)', async () => {
      let callCount = 0;
      const sendMetric = jest.fn(() => {
        callCount++;
        if (callCount === 1) throw new Error('boom');
      });
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized({
        sendMetric,
        mpSdkInstance: undefined,
      });

      await fn('test');

      expect(mockSessionStorage.getItem(SESSION_KEY)).toBe('true');
    });
  });

  // ---------------------------------------------------------------------------
  describe('sessionStorage deduplication (finally block)', () => {
    test('Given first call succeeds, When called again, Then second call should not send any metric', async () => {
      const sendMetric = jest.fn();
      const windowOverrides = {
        sendMetric,
        mpSdkInstance: {},
        mpSuperTokenMetrics: {},
        mpSuperTokenPaymentMethods: {},
        mpSuperTokenAuthenticator: {},
        mpSuperTokenErrorHandler: {},
        mpSuperTokenTriggerHandler: { isAlreadyListeningForm: true },
      };
      const { fn, mockSessionStorage } = loadCheckIfSuperTokenWasInitialized(windowOverrides);

      await fn('first_call');
      expect(sendMetric).toHaveBeenCalledTimes(1);
      expect(mockSessionStorage.getItem(SESSION_KEY)).toBe('true');

      sendMetric.mockClear();
      await fn('second_call');
      expect(sendMetric).not.toHaveBeenCalled();
    });
  });
});

describe('mp-super-token initialization', () => {
  const MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
  const LEGACY_POLL_WINDOW_MS = 15000;
  const SCRIPT_STARTED_AT = 1000;

  function runSuperTokenInit({ windowOverrides = {}, globalOverrides = {} } = {}) {
    const fileContent = fs.readFileSync(mpSuperTokenPath, 'utf8');

    const globalSendMetric = jest.fn();
    const superTokenMetricsSendMetric = jest.fn();
    const eventListeners = {};
    const clock = { now: SCRIPT_STARTED_AT };

    let fallbackPollTick = null;
    let fallbackPollExpiry = null;
    let intervalCleared = false;

    const context = {
      window: {
        mpSdkInstance: undefined,
        ...windowOverrides,
      },
      document: {
        addEventListener: (eventName, handler) => { eventListeners[eventName] = handler; },
      },
      setInterval: (callback) => { fallbackPollTick = callback; return 1; },
      clearInterval: () => { intervalCleared = true; },
      setTimeout: (callback) => { fallbackPollExpiry = callback; return 2; },
      Date: { now: () => clock.now },
      console: global.console,
      sendMetric: globalSendMetric,
      MPDebounce: jest.fn(() => ({})),
      WCEmailListener: jest.fn(() => ({})),
      MPSuperTokenMetrics: jest.fn(() => ({ sendMetric: superTokenMetricsSendMetric })),
      MPSuperTokenPaymentMethods: jest.fn(() => ({})),
      MPSuperTokenAuthenticator: jest.fn(() => ({})),
      MPSuperTokenErrorHandler: jest.fn(() => ({})),
      MPSuperTokenTriggerHandler: jest.fn(() => ({})),
      sessionStorage: { getItem: jest.fn().mockReturnValue(null), setItem: jest.fn() },
      ...globalOverrides,
    };

    new vm.Script(fileContent).runInNewContext(context);

    return {
      context,
      globalSendMetric,
      superTokenMetricsSendMetric,
      clock,
      isListeningForSdkInstanceReady: () => typeof eventListeners[MP_SDK_INSTANCE_READY_EVENT] === 'function',
      fireSdkInstanceReadyEvent: () => eventListeners[MP_SDK_INSTANCE_READY_EVENT]?.(),
      runFallbackPollTick: () => fallbackPollTick?.(),
      expireFallbackPoll: () => fallbackPollExpiry?.(),
      registeredFallbackPoll: () => fallbackPollTick !== null,
      wasFallbackPollCleared: () => intervalCleared,
    };
  }

  function lastInitSourceMetric(superTokenMetricsSendMetric) {
    const call = superTokenMetricsSendMetric.mock.calls.find(([metricName]) => metricName === 'super_token_init_source');
    return call ? call[1] : null;
  }

  describe('already-ready path: SDK instance present when the script runs', () => {
    test('Given the SDK instance already exists, When the script runs, Then it builds the classes immediately', () => {
      const { context, superTokenMetricsSendMetric } = runSuperTokenInit({ windowOverrides: { mpSdkInstance: {} } });

      expect(context.window.mpSuperTokenTriggerHandler).toBeDefined();
      expect(superTokenMetricsSendMetric).toHaveBeenCalledWith('super_token_sdk_loaded', 'true', '');
    });

    test('Given the SDK instance already exists, When the classes are built, Then init source is reported as "already_ready"', () => {
      const { superTokenMetricsSendMetric } = runSuperTokenInit({ windowOverrides: { mpSdkInstance: {} } });

      expect(lastInitSourceMetric(superTokenMetricsSendMetric)).toBe('already_ready');
    });

    test('Given the SDK instance already exists, When the script runs, Then it does not register a fallback poll', () => {
      const { registeredFallbackPoll } = runSuperTokenInit({ windowOverrides: { mpSdkInstance: {} } });

      expect(registeredFallbackPoll()).toBe(false);
    });

    test('Given mpCustomCheckoutHandler is absent, When the classes are built, Then it sends MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', () => {
      const { globalSendMetric } = runSuperTokenInit({ windowOverrides: { mpSdkInstance: {} } });

      expect(globalSendMetric).toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
        'mp_super_token_init',
        'mp_super_token_init_error'
      );
    });

    test('Given mpCustomCheckoutHandler is present, When the classes are built, Then it does not send MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', () => {
      const { globalSendMetric } = runSuperTokenInit({
        windowOverrides: { mpSdkInstance: {}, mpCustomCheckoutHandler: { cardForm: {} } },
      });

      expect(globalSendMetric).not.toHaveBeenCalledWith(
        'MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS',
        expect.any(String),
        expect.any(String)
      );
    });

    test('Given mpCustomCheckoutHandler is absent, When the classes are built, Then it does not call any flag-marking method on the trigger handler (split flag contract)', () => {
      const trackedMarkMethod = jest.fn();

      runSuperTokenInit({
        windowOverrides: { mpSdkInstance: {} },
        globalOverrides: {
          MPSuperTokenTriggerHandler: jest.fn(() => ({ markCustomHandlerMissingReported: trackedMarkMethod })),
        },
      });

      expect(trackedMarkMethod).not.toHaveBeenCalled();
    });
  });

  describe('event-driven path: SDK instance absent when the script runs', () => {
    test('Given the SDK instance is absent, When the script runs, Then it does not build the classes yet', () => {
      const { context, superTokenMetricsSendMetric } = runSuperTokenInit();

      expect(context.window.mpSuperTokenTriggerHandler).toBeUndefined();
      expect(superTokenMetricsSendMetric).not.toHaveBeenCalledWith('super_token_sdk_loaded', 'true', '');
    });

    test('Given the SDK instance is absent, When the script runs, Then it listens for the mp_sdk_instance_ready event', () => {
      const { isListeningForSdkInstanceReady } = runSuperTokenInit();

      expect(isListeningForSdkInstanceReady()).toBe(true);
    });

    test('Given the SDK instance arrives within the legacy window, When the event fires, Then it builds the classes with source "sdk_event"', () => {
      const harness = runSuperTokenInit();

      harness.context.window.mpSdkInstance = {};
      harness.clock.now = SCRIPT_STARTED_AT + 5000;
      harness.fireSdkInstanceReadyEvent();

      expect(harness.context.window.mpSuperTokenTriggerHandler).toBeDefined();
      expect(lastInitSourceMetric(harness.superTokenMetricsSendMetric)).toBe('sdk_event');
    });

    test('Given the SDK instance arrives AFTER the legacy 15s window, When the event fires, Then init source is reported as "sdk_event_recovered"', () => {
      const harness = runSuperTokenInit();

      harness.context.window.mpSdkInstance = {};
      harness.clock.now = SCRIPT_STARTED_AT + LEGACY_POLL_WINDOW_MS + 1;
      harness.fireSdkInstanceReadyEvent();

      expect(harness.context.window.mpSuperTokenTriggerHandler).toBeDefined();
      expect(lastInitSourceMetric(harness.superTokenMetricsSendMetric)).toBe('sdk_event_recovered');
    });
  });

  describe('fallback poll: resilience when the event is never emitted', () => {
    test('Given the SDK instance is absent, When the script runs, Then it registers a fallback poll', () => {
      const { registeredFallbackPoll } = runSuperTokenInit();

      expect(registeredFallbackPoll()).toBe(true);
    });

    test('Given the SDK instance appears, When the fallback poll ticks, Then it builds the classes with source "fallback_poll" and clears the poll', () => {
      const harness = runSuperTokenInit();

      harness.context.window.mpSdkInstance = {};
      harness.runFallbackPollTick();

      expect(harness.context.window.mpSuperTokenTriggerHandler).toBeDefined();
      expect(lastInitSourceMetric(harness.superTokenMetricsSendMetric)).toBe('fallback_poll');
      expect(harness.wasFallbackPollCleared()).toBe(true);
    });
  });

  describe('idempotency: the classes are built at most once', () => {
    test('Given the event already built the classes, When the fallback poll ticks afterwards, Then it does not rebuild', () => {
      const harness = runSuperTokenInit();

      harness.context.window.mpSdkInstance = {};
      harness.fireSdkInstanceReadyEvent();
      harness.runFallbackPollTick();

      const sdkLoadedCalls = harness.superTokenMetricsSendMetric.mock.calls
        .filter(([metricName]) => metricName === 'super_token_sdk_loaded');
      expect(sdkLoadedCalls).toHaveLength(1);
    });
  });
});

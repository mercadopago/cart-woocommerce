const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../helpers/path-resolver');

const mpSuperTokenPath = resolveAlias('assets/js/checkouts/super-token/mp-super-token.js');

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

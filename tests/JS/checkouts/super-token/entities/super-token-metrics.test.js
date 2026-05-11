const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../../helpers/path-resolver');
const superTokenErrorConstantsPath = resolveAlias('assets/js/checkouts/super-token/errors/super-token-error-constants.js');
const superTokenMetricsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-metrics.js');

const localStorageStore = {};
const mockLocalStorage = {
  getItem: (key) => localStorageStore[key] ?? null,
  setItem: (key, value) => { localStorageStore[key] = String(value); },
  removeItem: (key) => { delete localStorageStore[key]; },
  clear: () => { Object.keys(localStorageStore).forEach(k => delete localStorageStore[k]); },
};

describe('MPSuperTokenMetrics', () => {
  let metrics;
  let MPSuperTokenMetrics;
  let mockMpSdkInstance;
  let mockFetch;

  beforeAll(() => {
    mockFetch = jest.fn(() => Promise.resolve());
    const mockDispatchEvent = jest.fn();

    global.localStorage = mockLocalStorage;

    const context = {
      window: {
        location: { href: 'https://example.com/checkout' },
        fetch: mockFetch,
      },
      document: { dispatchEvent: mockDispatchEvent },
      console,
      fetch: mockFetch,
      localStorage: mockLocalStorage,
      CustomEvent: class CustomEvent {
        constructor(name, options) {
          this.name = name;
          this.detail = options?.detail;
        }
      },
      wc_mercadopago_supertoken_bundle_params: {
        plugin_version: '1.0.0',
        platform_version: '6.0.0',
        site_id: 'MLA',
        cust_id: 'test-cust-id',
        location: 'https://example.com',
      }
    };

    global.CustomEvent = jest.fn((name, options) => ({ type: name, detail: options?.detail }));

    // Create a shared fetch mock
    const sharedFetchMock = jest.fn(() => Promise.resolve());

    global.window = {
      location: {
        href: 'https://example.com/checkout',
      },
    };

    const errorConstantsCode = fs.readFileSync(superTokenErrorConstantsPath, 'utf8');
    const metricsCode = fs.readFileSync(superTokenMetricsPath, 'utf8');
    const combined = `${errorConstantsCode}\n${metricsCode}\nMPSuperTokenMetrics;`;

    const script = new vm.Script(combined);
    MPSuperTokenMetrics = script.runInNewContext(context);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    mockFetch.mockImplementation(() => Promise.resolve());

    mockMpSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-instance-id'),
    };

    metrics = new MPSuperTokenMetrics(mockMpSdkInstance);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('errorToGetAccountPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
    });

    test('Given error with regular message, When errorToGetAccountPaymentMethods() is called, Then should always call sendMetric', () => {
      const error = { message: 'Network error occurred' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Network error occurred'
      );
    });

    test('Given error with "Authenticator flow is not supported" message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric (no longer skipped)', () => {
      const error = { message: 'Authenticator flow is not supported' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Authenticator flow is not supported'
      );
    });

    test('Given error with "The site id mco is not supported" message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric (no longer skipped)', () => {
      const error = { message: 'The site id mco is not supported' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'The site id mco is not supported'
      );
    });

    test('Given error without message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with stringified error', () => {
      const error = {};

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        '{}'
      );
    });

    test('Given null error, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetAccountPaymentMethods(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Unknown error'
      );
    });

    test('Given undefined error, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetAccountPaymentMethods(undefined);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Unknown error'
      );
    });
  });

  describe('errorOnSubmit()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
    });

    test('Given error code and error, When errorOnSubmit() is called, Then should call sendMetric with error code', () => {
      const error = { message: 'Submit failed' };

      metrics.errorOnSubmit('SELECT_PAYMENT_METHOD_ERROR', error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_on_submit_super_token',
        'SELECT_PAYMENT_METHOD_ERROR',
        'Submit failed'
      );
    });
  });

  describe('hasEscNotExists()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given payment method identifier, When hasEscNotExists() is called, Then should send metric with identifier', () => {
      metrics.hasEscNotExists('visa_1234');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'has_esc_not_exists',
        'visa_1234',
        'has_esc attribute not found in payment method'
      );
    });

    test('Given null identifier, When hasEscNotExists() is called, Then should send metric with fallback value', () => {
      metrics.hasEscNotExists(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'has_esc_not_exists',
        'UNKNOWN_PAYMENT_METHOD',
        'has_esc attribute not found in payment method'
      );
    });
  });

  describe('getPaymentMethodFail()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
    });

    test('Given error and identifier, When getPaymentMethodFail() is called, Then should send metric with error message', () => {
      const error = { message: 'GET_PAYMENT_METHOD_TIMEOUT_ERROR' };

      metrics.getPaymentMethodFail(error, 'visa_1234');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'get_payment_method_fail',
        'visa_1234',
        'GET_PAYMENT_METHOD_TIMEOUT_ERROR'
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'GET_PAYMENT_METHOD_TIMEOUT_ERROR',
        metrics.CUSTOM_CHECKOUT_STEPS.SELECT_PAYMENT_METHOD
      );
    });

    test('Given null error, When getPaymentMethodFail() is called, Then should send metric with "Unknown error"', () => {
      metrics.getPaymentMethodFail(null, 'visa_1234');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'get_payment_method_fail',
        'visa_1234',
        'Unknown error'
      );
    });
  });

  describe('getPaymentMethodLoadingTime()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given identifier and duration in seconds, When getPaymentMethodLoadingTime() is called, Then should send metric with duration suffixed with s', () => {
      metrics.getPaymentMethodLoadingTime('visa_1234', 1.5);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'get_payment_method_loading_time',
        'visa_1234',
        '1.5s'
      );
    });
  });

  describe('fetchPaymentMethodSuccess()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given identifier and cvvIsMandatory, When fetchPaymentMethodSuccess() is called, Then should send metric with cvv_is_mandatory tag', () => {
      metrics.fetchPaymentMethodSuccess('visa_1234', true);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'fetch_payment_method_success',
        'visa_1234',
        'cvv_is_mandatory_true'
      );
    });
  });

  describe('fetchPaymentMethodSkipped()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given identifier and reason, When fetchPaymentMethodSkipped() is called, Then should send metric with reason as message', () => {
      metrics.fetchPaymentMethodSkipped('visa_1234', 'esc_disabled');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'fetch_payment_method_skipped',
        'visa_1234',
        'esc_disabled'
      );
    });

    test('Given null identifier, When fetchPaymentMethodSkipped() is called, Then should send metric with fallback value', () => {
      metrics.fetchPaymentMethodSkipped(null, 'not_card');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'fetch_payment_method_skipped',
        'UNKNOWN_PAYMENT_METHOD',
        'not_card'
      );
    });

    test('Given reason already_checked, When fetchPaymentMethodSkipped() is called, Then should send metric with already_checked reason', () => {
      metrics.fetchPaymentMethodSkipped('master_5678', 'already_checked');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'fetch_payment_method_skipped',
        'master_5678',
        'already_checked'
      );
    });

    test('Given reason security_code_not_required, When fetchPaymentMethodSkipped() is called, Then should send metric with security_code_not_required reason', () => {
      metrics.fetchPaymentMethodSkipped('visa_1234', 'security_code_not_required');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'fetch_payment_method_skipped',
        'visa_1234',
        'security_code_not_required'
      );
    });
  });

  describe('isNotSimplifiedAuth()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given any condition, When isNotSimplifiedAuth() is called, Then should send metric', () => {
      metrics.isNotSimplifiedAuth();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'is_not_simplified_auth',
        'true',
        ''
      );
    });
  });

  describe('cannotGetFastPaymentToken()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given any condition, When cannotGetFastPaymentToken() is called, Then should send metric', () => {
      metrics.cannotGetFastPaymentToken();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'cannot_get_fast_payment_token',
        'true',
        ''
      );
    });
  });

  describe('sendStaleCacheMetrics()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
      mockLocalStorage.clear();
    });

    test('Given already checked within 24h, When sendStaleCacheMetrics() is called, Then should not send metric', async () => {
      mockLocalStorage.setItem('mp_js_cache_age_checked', String(Date.now()));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given last check was over 24h ago, When sendStaleCacheMetrics() is called, Then should send metric', async () => {
      mockLocalStorage.setItem('mp_js_cache_age_checked', String(Date.now() - 86400001));

      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: (key) => ({ 'age': null, 'last-modified': 'Mon, 10 Mar 2026 10:00:00 GMT' })[key] || null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).toHaveBeenCalledTimes(4);
    });

    test('Given HEAD returns only age header (no last-modified), When sendStaleCacheMetrics() is called, Then should use age as fallback', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: (key) => ({ 'age': '1296000', 'last-modified': null })[key] || null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).toHaveBeenCalledTimes(4);

      const firstCall = metrics.sendMetric.mock.calls[0];
      expect(firstCall[0]).toBe('mp_js_cache_age');
      expect(firstCall[1]).toBe('15');
      expect(firstCall[2]).toContain('file : card-form');
      expect(firstCall[2]).toContain('age_days : 15');
    });

    test('Given HEAD returns 405, When sendStaleCacheMetrics() is called, Then should retry with GET Range and send metric', async () => {
      let callCount = 0;
      mockFetch.mockImplementation((url, options) => {
        callCount++;
        if (options.method === 'HEAD') {
          return Promise.resolve({ status: 405, headers: { get: () => null } });
        }
        return Promise.resolve({
          ok: true,
          status: 206,
          headers: { get: (key) => ({ 'age': '864000', 'last-modified': null })[key] || null }
        });
      });

      await metrics.sendStaleCacheMetrics();

      expect(callCount).toBe(8);
      expect(metrics.sendMetric).toHaveBeenCalledTimes(4);

      const firstCall = metrics.sendMetric.mock.calls[0];
      expect(firstCall[1]).toBe('10');
    });

    test('Given HEAD returns only last-modified header, When sendStaleCacheMetrics() is called, Then should calculate age_days from last-modified', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: (key) => ({ 'age': null, 'last-modified': new Date(Date.now() - 30 * 86400000).toUTCString() })[key] || null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).toHaveBeenCalledTimes(4);

      const firstCall = metrics.sendMetric.mock.calls[0];
      const ageDays = parseInt(firstCall[1], 10);
      expect(ageDays).toBeGreaterThanOrEqual(29);
      expect(ageDays).toBeLessThanOrEqual(31);
    });

    test('Given plugin_js_base_url is absent from params, When sendStaleCacheMetrics() is called, Then should fetch from the fallback hardcoded path', async () => {
      const capturedUrls = [];
      mockFetch.mockImplementation((url) => {
        capturedUrls.push(url);
        return Promise.resolve({
          status: 200,
          headers: { get: (key) => ({ 'age': '86400', 'last-modified': null })[key] || null }
        });
      });

      await metrics.sendStaleCacheMetrics();

      expect(capturedUrls[0]).toContain('/wp-content/plugins/woocommerce-mercadopago/assets/js/');
      expect(capturedUrls[0]).toContain('card-form.min.js');
    });

    test('Given response is not ok (404), When sendStaleCacheMetrics() is called, Then should not send metric', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: false,
        status: 404,
        headers: { get: () => null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given response has no cache headers, When sendStaleCacheMetrics() is called, Then should not send metric', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: () => null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given last-modified header is malformed, When sendStaleCacheMetrics() is called, Then should not send metric with NaN', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: (key) => ({ 'last-modified': 'invalid-date', 'age': null })[key] || null }
      }));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given fetch throws error, When sendStaleCacheMetrics() is called, Then should silently skip without calling sendMetric', async () => {
      mockFetch.mockImplementation(() => Promise.reject(new Error('CORS error')));

      await metrics.sendStaleCacheMetrics();

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given all headers present, When sendStaleCacheMetrics() is called, Then last_modified takes priority over age', async () => {
      mockFetch.mockImplementation(() => Promise.resolve({
        ok: true,
        status: 200,
        headers: { get: (key) => ({ 'age': '259200', 'last-modified': 'Wed, 09 Apr 2026 12:00:00 GMT' })[key] || null }
      }));

      await metrics.sendStaleCacheMetrics();

      const expectedFiles = ['card-form', 'event-handler', 'melidata-client', 'super-token-loader'];
      expect(metrics.sendMetric).toHaveBeenCalledTimes(4);

      metrics.sendMetric.mock.calls.forEach((call, index) => {
        expect(call[0]).toBe('mp_js_cache_age');
        // age_days is calculated from last-modified (not from Age header)
        const ageDays = parseInt(call[1], 10);
        expect(ageDays).toBeGreaterThanOrEqual(14);
        expect(call[2]).toContain(`file : ${expectedFiles[index]}`);
        expect(call[2]).toContain('last_modified : 2026-04-09');
      });
    });
  });

  describe('sendMetric()', () => {
    let sendMetricInstance;

    beforeEach(() => {
      sendMetricInstance = new MPSuperTokenMetrics(mockMpSdkInstance);
    });

    test('Given valid parameters, When sendMetric() is called, Then should execute without errors', () => {
      expect(() => {
        sendMetricInstance.sendMetric('test_metric', 'test_value', 'test_message');
      }).not.toThrow();
    });
  });

  describe('getSdkInstanceId()', () => {
    test('Given mpSdkInstance with getSDKInstanceId method, When getSdkInstanceId() is called, Then should return SDK instance ID', () => {
      const result = metrics.getSdkInstanceId();
      expect(result).toBe('test-sdk-instance-id');
    });

    test('Given mpSdkInstance without getSDKInstanceId method, When getSdkInstanceId() is called, Then should return "Unknown"', () => {
      metrics.mpSdkInstance = {};
      const result = metrics.getSdkInstanceId();
      expect(result).toBe('Unknown');
    });

    test('Given mpSdkInstance is null, When getSdkInstanceId() is called, Then should return "Unknown"', () => {
      metrics.mpSdkInstance = null;
      const result = metrics.getSdkInstanceId();
      expect(result).toBe('Unknown');
    });

    test('Given getSDKInstanceId throws error, When getSdkInstanceId() is called, Then should return "Unknown"', () => {
      metrics.mpSdkInstance = {
        getSDKInstanceId: jest.fn(() => {
          throw new Error('SDK error');
        }),
      };
      const result = metrics.getSdkInstanceId();
      expect(result).toBe('Unknown');
    });
  });

  describe('getEnvironment()', () => {
    test('Given any condition, When getEnvironment() is called, Then should return "prod"', () => {
      const result = metrics.getEnvironment();
      expect(result).toBe('prod');
    });
  });

  describe('errorToAuthorizePayment()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToAuthorizePayment() is called, Then should call sendMetric with error message', () => {
      metrics.errorToAuthorizePayment({ message: 'authorization failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_authorize_payment',
        'true',
        'authorization failed'
      );
    });

    test('Given null error, When errorToAuthorizePayment() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToAuthorizePayment(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_authorize_payment',
        'true',
        'Unknown error'
      );
    });
  });

  describe('errorToGetSimplifiedAuth()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToGetSimplifiedAuth() is called, Then should call sendMetric with error message', () => {
      metrics.errorToGetSimplifiedAuth({ message: 'simplified auth failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_simplified_auth',
        'true',
        'simplified auth failed'
      );
    });
  });

  describe('errorToGetFastPaymentToken()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToGetFastPaymentToken() is called, Then should call sendMetric with error message', () => {
      metrics.errorToGetFastPaymentToken({ message: 'token fetch failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_fast_payment_token',
        'true',
        'token fetch failed'
      );
    });
  });

  describe('errorToBuildAuthenticator()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToBuildAuthenticator() is called, Then should call sendMetric with error message', () => {
      metrics.errorToBuildAuthenticator({ message: 'build failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_build_authenticator',
        'true',
        'build failed'
      );
    });
  });

  describe('errorToMountCVVField()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given error and payment method with id, When errorToMountCVVField() is called, Then should call sendMetric with payment method id as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, { id: 'visa' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'visa',
        'mount failed'
      );
    });

    test('Given error and payment method without id, When errorToMountCVVField() is called, Then should use "unknown" as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, {});

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'unknown',
        'mount failed'
      );
    });

    test('Given error and null payment method, When errorToMountCVVField() is called, Then should use "unknown" as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'unknown',
        'mount failed'
      );
    });
  });

  describe('errorToUpdateSecurityCode()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToUpdateSecurityCode() is called, Then should call sendMetric with payment method token as value', () => {
      metrics.errorToUpdateSecurityCode({ message: 'security code update failed' }, { token: 'tok-123' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_update_security_code',
        'tok-123',
        'security code update failed'
      );
    });
  });

  describe('errorOnSubmit()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given error code and error, When errorOnSubmit() is called, Then should call sendMetric with error code and message', () => {
      metrics.errorOnSubmit('SOME_ERROR_CODE', { message: 'submit error' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_on_submit_super_token',
        'SOME_ERROR_CODE',
        'submit error'
      );
    });

    test('Given error code and string error, When errorOnSubmit() is called, Then should call sendMetric with error code and string', () => {
      metrics.errorOnSubmit('cancelled', 'cancelled');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_on_submit_super_token',
        'cancelled',
        'cancelled'
      );
    });
  });

  describe('errorToGetAccountPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with error message', () => {
      metrics.errorToGetAccountPaymentMethods({ message: 'fetch account methods failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'fetch account methods failed'
      );
    });
  });

  describe('errorToRenderAccountPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToRenderAccountPaymentMethods() is called, Then should call sendMetric with error message', () => {
      metrics.errorToRenderAccountPaymentMethods({ message: 'render methods failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_render_account_payment_methods',
        'true',
        'render methods failed'
      );
    });
  });

  describe('renderCreditsContract()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given success is true, When renderCreditsContract() is called, Then should call sendMetric and not dispatch melidata error event', () => {
      metrics.renderCreditsContract(true);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'render_credits_contract',
        true,
        ''
      );
      expect(metrics.dispatchMelidataErrorEvent).not.toHaveBeenCalled();
    });

    test('Given success is false with error, When renderCreditsContract() is called, Then should call sendMetric and dispatch melidata error event', () => {
      metrics.renderCreditsContract(false, new Error('contract render failed'));

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'render_credits_contract',
        false,
        'contract render failed'
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'contract render failed',
        'select_payment_method'
      );
    });

    test('Given success is false with null error, When renderCreditsContract() is called, Then should dispatch with "Unknown error"', () => {
      metrics.renderCreditsContract(false, null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'render_credits_contract',
        false,
        'Unknown error'
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'Unknown error',
        'select_payment_method'
      );
    });
  });

  describe('renderConsumerCreditsDetailsInnerHTML()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given success is true, When renderConsumerCreditsDetailsInnerHTML() is called, Then should call sendMetric and not dispatch melidata error event', () => {
      metrics.renderConsumerCreditsDetailsInnerHTML(true);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'render_consumer_credits_details_inner_html',
        true,
        ''
      );
      expect(metrics.dispatchMelidataErrorEvent).not.toHaveBeenCalled();
    });

    test('Given success is false, When renderConsumerCreditsDetailsInnerHTML() is called, Then should call sendMetric and dispatch melidata error event', () => {
      metrics.renderConsumerCreditsDetailsInnerHTML(false);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'render_consumer_credits_details_inner_html',
        false,
        ''
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'render_consumer_credits_details_inner_html_failed',
        'select_payment_method'
      );
    });
  });

  describe('installmentsFilled()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given credit_card type, When installmentsFilled() is called, Then should call sendMetric with credit_card as message', () => {
      metrics.installmentsFilled('credit_card');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_installments_filled',
        true,
        'credit_card'
      );
    });

    test('Given consumer_credits type, When installmentsFilled() is called, Then should call sendMetric with consumer_credits as message', () => {
      metrics.installmentsFilled('consumer_credits');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_installments_filled',
        true,
        'consumer_credits'
      );
    });
  });

  describe('errorToUpdateCreditsContract()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with message, When errorToUpdateCreditsContract() is called, Then should call sendMetric and dispatch melidata error event', () => {
      metrics.errorToUpdateCreditsContract(new Error('contract update failed'));

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_update_credits_contract',
        'true',
        'contract update failed'
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'contract update failed',
        'select_payment_method'
      );
    });

    test('Given null error, When errorToUpdateCreditsContract() is called, Then should dispatch with "Unknown error"', () => {
      metrics.errorToUpdateCreditsContract(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_update_credits_contract',
        'true',
        'Unknown error'
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'Unknown error',
        'select_payment_method'
      );
    });
  });

  describe('errorToSubmitWithoutInstallmentSelected()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('When errorToSubmitWithoutInstallmentSelected() is called without arguments, Then should call sendMetric with empty payment method type and dispatch melidata error event', () => {
      metrics.errorToSubmitWithoutInstallmentSelected();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_submit_without_installment_selected',
        'true',
        ''
      );
      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'no_installment_selected',
        'post_submit'
      );
    });

    test('When errorToSubmitWithoutInstallmentSelected() is called with consumer_credits, Then should forward the payment method type to sendMetric', () => {
      metrics.errorToSubmitWithoutInstallmentSelected('consumer_credits');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_submit_without_installment_selected',
        'true',
        'consumer_credits'
      );
    });

    test('When errorToSubmitWithoutInstallmentSelected() is called with credit_card, Then should forward the payment method type to sendMetric', () => {
      metrics.errorToSubmitWithoutInstallmentSelected('credit_card');

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_submit_without_installment_selected',
        'true',
        'credit_card'
      );
    });
  });

  describe('sendMetric() with extraDetails', () => {
    test('Given extraDetails object, When sendMetric() is called, Then should execute without errors', () => {
      expect(() => {
        metrics.sendMetric('test_metric', 'value', 'message', { expected_error: 'true' });
      }).not.toThrow();
    });

    test('Given no extraDetails, When sendMetric() is called, Then should execute without errors', () => {
      expect(() => {
        metrics.sendMetric('test_metric', 'value', 'message');
      }).not.toThrow();
    });
  });
});

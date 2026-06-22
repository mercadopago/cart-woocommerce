const vm = require('vm');
const fs = require('fs');
const { resolveAlias } = require('../../../../helpers/path-resolver');
const superTokenErrorConstantsPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/errors/super-token-error-constants.js`);
const superTokenMetricsPath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/entities/super-token-metrics.js`);

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

    test('Given error with errorCode, When errorToGetAccountPaymentMethods() is called, Then should forward SDK errorCode as 4th arg and preserve value', () => {
      const error = { errorCode: 'AUTH_FAILED', message: 'Network error occurred' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Network error occurred',
        'AUTH_FAILED'
      );
    });

    test('Given error with regular message, When errorToGetAccountPaymentMethods() is called, Then should always call sendMetric', () => {
      const error = { message: 'Network error occurred' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Network error occurred',
        'unknown'
      );
    });

    test('Given error with "Authenticator flow is not supported" message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric (no longer skipped)', () => {
      const error = { message: 'Authenticator flow is not supported' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Authenticator flow is not supported',
        'unknown'
      );
    });

    test('Given error with "The site id mco is not supported" message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric (no longer skipped)', () => {
      const error = { message: 'The site id mco is not supported' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'The site id mco is not supported',
        'unknown'
      );
    });

    test('Given error without message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with stringified error', () => {
      const error = {};

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        '{}',
        'unknown'
      );
    });

    test('Given null error, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetAccountPaymentMethods(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Unknown error',
        'unknown'
      );
    });

    test('Given undefined error, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetAccountPaymentMethods(undefined);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Unknown error',
        'unknown'
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

    test('Given an error with errorCode, When errorToAuthorizePayment() is called, Then should forward SDK errorCode as 4th arg and preserve value', () => {
      metrics.errorToAuthorizePayment({ errorCode: 'AUTH_FAILED', message: 'authorization failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_authorize_payment',
        'true',
        'authorization failed',
        'AUTH_FAILED'
      );
    });

    test('Given an error with message, When errorToAuthorizePayment() is called, Then should call sendMetric with error message', () => {
      metrics.errorToAuthorizePayment({ message: 'authorization failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_authorize_payment',
        'true',
        'authorization failed',
        'unknown'
      );
    });

    test('Given null error, When errorToAuthorizePayment() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToAuthorizePayment(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_authorize_payment',
        'true',
        'Unknown error',
        'unknown'
      );
    });
  });

  describe('errorToGetSimplifiedAuth()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with errorCode, When errorToGetSimplifiedAuth() is called, Then should forward SDK errorCode as 4th arg', () => {
      metrics.errorToGetSimplifiedAuth({ errorCode: 'AUTH_FAILED', message: 'simplified auth failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_simplified_auth',
        'true',
        'simplified auth failed',
        'AUTH_FAILED'
      );
    });

    test('Given an error with message, When errorToGetSimplifiedAuth() is called, Then should call sendMetric with error message', () => {
      metrics.errorToGetSimplifiedAuth({ message: 'simplified auth failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_simplified_auth',
        'true',
        'simplified auth failed',
        'unknown'
      );
    });
  });

  describe('errorToGetFastPaymentToken()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with errorCode, When errorToGetFastPaymentToken() is called, Then should forward SDK errorCode as 4th arg', () => {
      metrics.errorToGetFastPaymentToken({ errorCode: 'TIMEOUT_ERROR', message: 'token fetch failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_fast_payment_token',
        'true',
        'token fetch failed',
        'TIMEOUT_ERROR'
      );
    });

    test('Given an error with message, When errorToGetFastPaymentToken() is called, Then should call sendMetric with error message', () => {
      metrics.errorToGetFastPaymentToken({ message: 'token fetch failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_fast_payment_token',
        'true',
        'token fetch failed',
        'unknown'
      );
    });
  });

  describe('errorToBuildAuthenticator()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with errorCode, When errorToBuildAuthenticator() is called, Then should forward SDK errorCode as 4th arg and preserve value', () => {
      metrics.errorToBuildAuthenticator({ errorCode: 'TIMEOUT_ERROR', message: 'build failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_build_authenticator',
        'true',
        'build failed',
        'TIMEOUT_ERROR'
      );
    });

    test('Given an error without errorCode, When errorToBuildAuthenticator() is called, Then should call sendMetric with "unknown" as 4th arg', () => {
      metrics.errorToBuildAuthenticator({ message: 'build failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_build_authenticator',
        'true',
        'build failed',
        'unknown'
      );
    });

    test('Given null error, When errorToBuildAuthenticator() is called, Then should call sendMetric with "true" value, "Unknown error" message and "unknown" errorCode', () => {
      metrics.errorToBuildAuthenticator(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_build_authenticator',
        'true',
        'Unknown error',
        'unknown'
      );
    });
  });

  describe('errorToMountCVVField()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given error with errorCode, When errorToMountCVVField() is called, Then should keep payment method id as value and forward errorCode as 4th arg', () => {
      metrics.errorToMountCVVField({ errorCode: 'MOUNT_FAILED', message: 'mount failed' }, { id: 'visa' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'visa',
        'mount failed',
        'MOUNT_FAILED'
      );
    });

    test('Given error and payment method with id, When errorToMountCVVField() is called, Then should call sendMetric with payment method id as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, { id: 'visa' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'visa',
        'mount failed',
        'unknown'
      );
    });

    test('Given error and payment method without id, When errorToMountCVVField() is called, Then should use "unknown" as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, {});

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'unknown',
        'mount failed',
        'unknown'
      );
    });

    test('Given error and null payment method, When errorToMountCVVField() is called, Then should use "unknown" as value', () => {
      metrics.errorToMountCVVField({ message: 'mount failed' }, null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_mount_cvv_field',
        'unknown',
        'mount failed',
        'unknown'
      );
    });
  });

  describe('errorToUpdateSecurityCode()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given error with errorCode, When errorToUpdateSecurityCode() is called, Then should keep token as value and forward errorCode as 4th arg', () => {
      metrics.errorToUpdateSecurityCode({ errorCode: 'UPDATE_SECURITY_CODE_ERROR', message: 'security code update failed' }, { token: 'tok-123' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_update_security_code',
        'tok-123',
        'security code update failed',
        'UPDATE_SECURITY_CODE_ERROR'
      );
    });

    test('Given an error with message, When errorToUpdateSecurityCode() is called, Then should call sendMetric with payment method token as value', () => {
      metrics.errorToUpdateSecurityCode({ message: 'security code update failed' }, { token: 'tok-123' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_update_security_code',
        'tok-123',
        'security code update failed',
        'unknown'
      );
    });
  });

  describe('updateSecurityCodeGetCardIdSuccess()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('When called, Then should call sendMetric with update_security_code_get_card_id_success', () => {
      metrics.updateSecurityCodeGetCardIdSuccess();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'update_security_code_get_card_id_success',
        'true',
        ''
      );
    });
  });

  describe('updateSecurityCodeCardTokenCreated()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('When called, Then should call sendMetric with update_security_code_card_token_created', () => {
      metrics.updateSecurityCodeCardTokenCreated();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'update_security_code_card_token_created',
        'true',
        ''
      );
    });
  });

  describe('updateSecurityCodePseudotokenUpdated()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('When called, Then should call sendMetric with update_security_code_pseudotoken_updated', () => {
      metrics.updateSecurityCodePseudotokenUpdated();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'update_security_code_pseudotoken_updated',
        'true',
        ''
      );
    });
  });

  describe('updateSecurityCodeSuccess()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('When called, Then should call sendMetric with update_security_code_success', () => {
      metrics.updateSecurityCodeSuccess();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'update_security_code_success',
        'true',
        ''
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

    test('When errorOnSubmit() is called, Then should NOT pass an errorCode 4th arg (code already carried in value)', () => {
      metrics.errorOnSubmit('SOME_ERROR_CODE', { errorCode: 'IGNORED', message: 'submit error' });

      // Deliberate exclusion: errorOnSubmit already exposes the code in `value`,
      // and its 2nd param is a pre-processed message, not an SDK error object.
      expect(metrics.sendMetric.mock.calls[0]).toHaveLength(3);
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
        'fetch account methods failed',
        'unknown'
      );
    });
  });

  describe('errorToRenderAccountPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given an error with errorCode, When errorToRenderAccountPaymentMethods() is called, Then should forward SDK errorCode as 4th arg', () => {
      metrics.errorToRenderAccountPaymentMethods({ errorCode: 'RENDER_FAILED', message: 'render methods failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_render_account_payment_methods',
        'true',
        'render methods failed',
        'RENDER_FAILED'
      );
    });

    test('Given an error with message, When errorToRenderAccountPaymentMethods() is called, Then should call sendMetric with error message', () => {
      metrics.errorToRenderAccountPaymentMethods({ message: 'render methods failed' });

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_render_account_payment_methods',
        'true',
        'render methods failed',
        'unknown'
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
        'contract update failed',
        'unknown'
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
        'Unknown error',
        'unknown'
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

  describe('sendMetric() errorCode → details.event', () => {
    const getLastFetchBody = () => {
      const lastCall = mockFetch.mock.calls[mockFetch.mock.calls.length - 1];
      return JSON.parse(lastCall[1].body);
    };

    test('Given an errorCode, When sendMetric() is called, Then payload details.event carries the SDK errorCode and value is preserved', () => {
      metrics.sendMetric('error_to_build_authenticator', 'true', 'build failed', 'TIMEOUT_ERROR');

      const payload = getLastFetchBody();
      expect(payload.details.event).toBe('TIMEOUT_ERROR');
      expect(payload.value).toBe('true');
      expect(payload.message).toBe('build failed');
    });

    test('Given no errorCode, When sendMetric() is called, Then payload details has no event field', () => {
      metrics.sendMetric('test_metric', 'value', 'message');

      const payload = getLastFetchBody();
      expect(payload.details).not.toHaveProperty('event');
    });

    test('Given errorCode is "unknown", When sendMetric() is called, Then payload details.event is "unknown"', () => {
      metrics.sendMetric('error_to_get_simplified_auth', 'true', 'msg', 'unknown');

      const payload = getLastFetchBody();
      expect(payload.details.event).toBe('unknown');
    });

    test('Given an errorCode, When sendMetric() is called, Then the original details fields are preserved alongside event', () => {
      metrics.sendMetric('error_to_build_authenticator', 'true', 'build failed', 'TIMEOUT_ERROR');

      const payload = getLastFetchBody();
      expect(payload.details).toMatchObject({
        site_id: 'MLA',
        environment: 'prod',
        sdk_instance_id: 'test-sdk-instance-id',
        cust_id: 'test-cust-id',
        event: 'TIMEOUT_ERROR',
      });
    });

    test('Given a non-string errorCode, When sendMetric() is called, Then payload details.event is coerced to string', () => {
      metrics.sendMetric('error_to_build_authenticator', 'true', 'build failed', 1234);

      const payload = getLastFetchBody();
      expect(payload.details.event).toBe('1234');
    });

    test('Given an empty-string errorCode, When sendMetric() is called, Then payload details has no event field', () => {
      metrics.sendMetric('test_metric', 'value', 'message', '');

      const payload = getLastFetchBody();
      expect(payload.details).not.toHaveProperty('event');
    });

    test('Given valid parameters, When sendMetric() is called, Then should execute without errors', () => {
      expect(() => {
        metrics.sendMetric('test_metric', 'value', 'message');
      }).not.toThrow();
    });
  });
});

// =============================================================================
// MeliData Loading Validation (PSW-4050)
// =============================================================================
//
// These tests exercise the new waitForMelidata_() helper and the 5s timeout race
// in dispatchMelidataErrorEvent. The setup uses a richer vm context than the main
// describe block above because waitForMelidata_ accesses window.addEventListener,
// window.melidata, window.melidataReady, and document.readyState — none of which
// the original test harness provides.
// =============================================================================

describe('MPSuperTokenMetrics.waitForMelidata_() and timeout race (PSW-4050)', () => {
  let metrics;
  let MPSuperTokenMetricsClass;
  let testWindow;
  let testDocument;
  let dispatchedEvents;
  let fetchMock;
  let loadListeners;

  beforeEach(() => {
    dispatchedEvents = [];
    loadListeners = [];
    fetchMock = jest.fn(() => ({ catch: () => {} }));

    testWindow = {
      melidata: undefined,
      melidataReady: undefined,
      location: { href: 'https://example.com/checkout' },
      addEventListener: jest.fn((eventName, cb, opts) => {
        if (eventName === 'load') {
          loadListeners.push({ cb, opts });
        }
      }),
    };

    testDocument = {
      readyState: 'loading',
      dispatchEvent: jest.fn((event) => {
        dispatchedEvents.push(event);
        return true;
      }),
    };

    const context = {
      window: testWindow,
      document: testDocument,
      console,
      fetch: fetchMock,
      localStorage: mockLocalStorage,
      Promise,
      // Dynamic dispatch so jest.useFakeTimers() (which swaps the host's setTimeout)
      // is visible to code running inside the vm sandbox.
      setTimeout: (cb, ms) => global.setTimeout(cb, ms),
      clearTimeout: (id) => global.clearTimeout(id),
      CustomEvent: function CustomEvent(name, options) {
        return { type: name, detail: options?.detail };
      },
      wc_mercadopago_supertoken_bundle_params: {
        plugin_version: '1.0.0',
        platform_version: '6.0.0',
        site_id: 'MLA',
        cust_id: 'test-cust-id',
        location: 'https://example.com',
      },
    };

    const fileContent = fs.readFileSync(superTokenMetricsPath, 'utf8');
    const script = new vm.Script(`${fileContent}\nMPSuperTokenMetrics;`);
    MPSuperTokenMetricsClass = script.runInNewContext(context);

    metrics = new MPSuperTokenMetricsClass({ getSDKInstanceId: () => 'test-id' });
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Branch 1: window.melidata already loaded
  // ---------------------------------------------------------------------------
  test('TC-STM-WFM-01: resolves synchronously when window.melidata is set', async () => {
    testWindow.melidata = { track: jest.fn() };

    await expect(metrics.waitForMelidata_()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 2: window.melidataReady is a thenable Promise
  // ---------------------------------------------------------------------------
  test('TC-STM-WFM-02: chains onto window.melidataReady when it is a Promise', async () => {
    testWindow.melidataReady = Promise.resolve();

    await expect(metrics.waitForMelidata_()).resolves.toBeUndefined();
  });

  test('TC-STM-WFM-02b: a rejected melidataReady is absorbed by .catch(resolve)', async () => {
    testWindow.melidataReady = Promise.reject(new Error('cdn fail'));

    await expect(metrics.waitForMelidata_()).resolves.toBeInstanceOf(Error);
  });

  // ---------------------------------------------------------------------------
  // Branch 3: melidataReady is truthy but not thenable (third-party shim)
  // ---------------------------------------------------------------------------
  test('TC-STM-WFM-03: resolves when melidataReady is truthy non-thenable', async () => {
    testWindow.melidataReady = true;

    await expect(metrics.waitForMelidata_()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 4: document.readyState === 'complete'
  // ---------------------------------------------------------------------------
  test('TC-STM-WFM-04: resolves immediately when readyState is complete', async () => {
    testDocument.readyState = 'complete';

    await expect(metrics.waitForMelidata_()).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // Branch 5: load-event fallback
  // ---------------------------------------------------------------------------
  test('TC-STM-WFM-05: subscribes to window load event and resolves when fired', async () => {
    testDocument.readyState = 'loading';

    const promise = metrics.waitForMelidata_();

    expect(testWindow.addEventListener).toHaveBeenCalledWith('load', expect.any(Function), { once: true });
    // Simulate window 'load' firing
    loadListeners.forEach(({ cb }) => cb());

    await expect(promise).resolves.toBeUndefined();
  });

  // ---------------------------------------------------------------------------
  // dispatchMelidataErrorEvent: happy path (microtask dispatch)
  // ---------------------------------------------------------------------------
  test('TC-STM-DME-01: dispatches via microtask when window.melidata is present', async () => {
    testWindow.melidata = { track: jest.fn() };

    metrics.dispatchMelidataErrorEvent('card declined', 'post_submit');

    await Promise.resolve();
    await Promise.resolve();

    expect(testDocument.dispatchEvent).toHaveBeenCalledTimes(1);
    const evt = dispatchedEvents[0];
    expect(evt.type).toBe('mp_checkout_error');
    expect(evt.detail.message).toBe('card declined');
    expect(evt.detail.errorOrigin).toBe('post_submit_mercado_pago');
  });

  // ---------------------------------------------------------------------------
  // dispatchMelidataErrorEvent: timeout path — sendMetric AND dispatch
  // ---------------------------------------------------------------------------
  test('TC-STM-DME-02: after 5000ms timeout, sendMetric is called with mp_melidata_load_timeout and dispatch still occurs', async () => {
    jest.useFakeTimers();
    testDocument.readyState = 'loading'; // no readiness signal, no load event

    const sendMetricSpy = jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});

    metrics.dispatchMelidataErrorEvent('timeout case', 'post_submit');

    // Advance past the 5s timeout
    jest.advanceTimersByTime(5000);
    // Drain microtasks so Promise.race().then(dispatch) runs
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();

    expect(sendMetricSpy).toHaveBeenCalledTimes(1);
    expect(sendMetricSpy).toHaveBeenCalledWith('mp_melidata_load_timeout', 'true', 'timeout case');

    // Dispatch still happens (best-effort)
    expect(testDocument.dispatchEvent).toHaveBeenCalledTimes(1);
    expect(dispatchedEvents[0].type).toBe('mp_checkout_error');
    expect(dispatchedEvents[0].detail.message).toBe('timeout case');
  });

  // ---------------------------------------------------------------------------
  // TC-STM-DME-02b: timeout is cancelled when melidata loads before 5s — no false-positive metric
  // ---------------------------------------------------------------------------
  test('TC-STM-DME-02b: sendMetric is NOT called when melidata loads before the timeout', async () => {
    jest.useFakeTimers();
    testWindow.melidata = { track: jest.fn() };

    const sendMetricSpy = jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});

    metrics.dispatchMelidataErrorEvent('fast case', 'post_submit');

    await Promise.resolve();
    await Promise.resolve();

    // Advance past the timeout window — metric must NOT fire
    jest.advanceTimersByTime(5000);

    expect(sendMetricSpy).not.toHaveBeenCalled();
    expect(testDocument.dispatchEvent).toHaveBeenCalledTimes(1);
  });

  // ---------------------------------------------------------------------------
  // dispatchMelidataErrorEvent: cleanMessage strips '[mercado pago]:' prefix
  // ---------------------------------------------------------------------------
  test('TC-STM-DME-03: cleanMessage logic still strips [mercado pago]: prefix before dispatch', async () => {
    testWindow.melidata = {}; // ready

    metrics.dispatchMelidataErrorEvent('[mercado pago]: card declined', 'post_submit');

    await Promise.resolve();
    await Promise.resolve();

    expect(dispatchedEvents[0].detail.message).toBe('card declined');
  });
});

const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenMetricsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-metrics.js');

describe('MPSuperTokenMetrics', () => {
  let metrics;
  let MPSuperTokenMetrics;
  let mockMpSdkInstance;
  let mockFetch;

  beforeAll(() => {
    global.wc_mercadopago_supertoken_metrics_params = {
      plugin_version: '1.0.0',
      platform_version: '6.0.0',
      site_id: 'MLA',
      cust_id: 'test-cust-id',
      location: 'https://example.com',
    };

    // Create a shared fetch mock
    const sharedFetchMock = jest.fn(() => Promise.resolve());

    global.window = {
      location: {
        href: 'https://example.com/checkout',
      },
      fetch: sharedFetchMock,
    };

    // Mock fetch in both global and window
    global.fetch = sharedFetchMock;

    MPSuperTokenMetrics = loadFile(superTokenMetricsPath, 'MPSuperTokenMetrics', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    
    // Ensure fetch mock is available
    if (!global.fetch) {
      global.fetch = jest.fn(() => Promise.resolve());
    }
    
    mockFetch = global.fetch;

    mockMpSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-instance-id'),
    };

    metrics = new MPSuperTokenMetrics(mockMpSdkInstance);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('shouldSkipError()', () => {
    test.each([
      { value: null, description: 'null' },
      { value: undefined, description: 'undefined' },
      { value: '', description: 'empty string' },
    ])('Given errorMessage is $description, When shouldSkipError() is called, Then should return false', ({ value }) => {
      const result = metrics.shouldSkipError(value);
      expect(result).toBe(false);
    });

    test('Given errorMessage is EMPTY_PRELOADED_PAYMENT_METHODS, When shouldSkipError() is called, Then should return true', () => {
      const result = metrics.shouldSkipError(metrics.EMPTY_PRELOADED_PAYMENT_METHODS);
      expect(result).toBe(true);
    });

    test.each([
      { message: 'Authenticator flow is not supported', expected: true },
      { message: 'authenticator flow is not supported', expected: true },
      { message: 'AUTHENTICATOR FLOW IS NOT SUPPORTED', expected: true },
      { message: 'Error: Authenticator flow is not supported.', expected: true },
      { message: 'The authenticator flow is not supported for this site', expected: true },
      { message: 'Some other error', expected: false },
    ])('Given errorMessage is "$message", When shouldSkipError() is called, Then should return $expected', ({ message, expected }) => {
      const result = metrics.shouldSkipError(message);
      expect(result).toBe(expected);
    });

    test.each([
      { siteId: 'mco', expected: true },
      { siteId: 'mlc', expected: true },
      { siteId: 'mlu', expected: true },
      { siteId: 'mpe', expected: true },
    ])('Given errorMessage is "The site id $siteId is not supported", When shouldSkipError() is called, Then should return $expected', ({ siteId, expected }) => {
      const result = metrics.shouldSkipError(`The site id ${siteId} is not supported`);
      expect(result).toBe(expected);
    });

    test.each([
      { message: 'The site id MCO is not supported', expected: true },
      { message: 'the site id mlc is not supported', expected: true },
      { message: 'THE SITE ID MLU IS NOT SUPPORTED', expected: true },
      { message: 'Error: The site id mpe is not supported.', expected: true },
      { message: 'The site id XYZ is not supported', expected: true },
      { message: 'Site id is not supported', expected: false },
      { message: 'The site is not supported', expected: false },
    ])('Given errorMessage is "$message", When shouldSkipError() is called, Then should return $expected', ({ message, expected }) => {
      const result = metrics.shouldSkipError(message);
      expect(result).toBe(expected);
    });

    test('Given errorMessage is a regular error, When shouldSkipError() is called, Then should return false', () => {
      const result = metrics.shouldSkipError('Network error occurred');
      expect(result).toBe(false);
    });
  });

  describe('errorToGetPreloadedPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given error with message that should be skipped, When errorToGetPreloadedPaymentMethods() is called, Then should not call sendMetric', () => {
      const error = { message: metrics.EMPTY_PRELOADED_PAYMENT_METHODS };
      
      metrics.errorToGetPreloadedPaymentMethods(error);

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given error with "Authenticator flow is not supported" message, When errorToGetPreloadedPaymentMethods() is called, Then should not call sendMetric', () => {
      const error = { message: 'Authenticator flow is not supported' };
      
      metrics.errorToGetPreloadedPaymentMethods(error);

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given error with "The site id mco is not supported" message, When errorToGetPreloadedPaymentMethods() is called, Then should not call sendMetric', () => {
      const error = { message: 'The site id mco is not supported' };
      
      metrics.errorToGetPreloadedPaymentMethods(error);

      expect(metrics.sendMetric).not.toHaveBeenCalled();
    });

    test('Given error with regular error message, When errorToGetPreloadedPaymentMethods() is called, Then should call sendMetric with correct parameters', () => {
      const error = { message: 'Network error occurred' };
      
      metrics.errorToGetPreloadedPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_preloaded_payment_methods',
        'true',
        'Network error occurred'
      );
    });

    test('Given error without message, When errorToGetPreloadedPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      const error = {};
      
      metrics.errorToGetPreloadedPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_preloaded_payment_methods',
        'true',
        'Unknown error'
      );
    });

    test('Given null error, When errorToGetPreloadedPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetPreloadedPaymentMethods(null);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_preloaded_payment_methods',
        'true',
        'Unknown error'
      );
    });

    test('Given undefined error, When errorToGetPreloadedPaymentMethods() is called, Then should call sendMetric with "Unknown error"', () => {
      metrics.errorToGetPreloadedPaymentMethods(undefined);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_preloaded_payment_methods',
        'true',
        'Unknown error'
      );
    });
  });

  describe('sendMetric()', () => {
    let sendMetricInstance;

    beforeEach(() => {
      // Create a new instance for testing
      sendMetricInstance = new MPSuperTokenMetrics(mockMpSdkInstance);
    });

    test('Given valid parameters, When sendMetric() is called, Then should execute without errors', () => {
      // Simply verify the method can be called without throwing
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
});
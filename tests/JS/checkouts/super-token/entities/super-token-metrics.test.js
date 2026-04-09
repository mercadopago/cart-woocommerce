const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const superTokenMetricsPath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-metrics.js');

describe('MPSuperTokenMetrics', () => {
  let metrics;
  let MPSuperTokenMetrics;
  let mockMpSdkInstance;

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = {
      plugin_version: '1.0.0',
      platform_version: '6.0.0',
      site_id: 'MLA',
      cust_id: 'test-cust-id',
      location: 'https://example.com',
    };

    global.wc_mercadopago_supertoken_metrics_params = {
      plugin_version: '1.0.0',
      platform_version: '6.0.0',
      site_id: 'MLA',
      cust_id: 'test-cust-id',
      location: 'https://example.com',
    };

    const sharedFetchMock = jest.fn(() => Promise.resolve());

    global.window = {
      location: {
        href: 'https://example.com/checkout',
      },
      fetch: sharedFetchMock,
    };

    global.fetch = sharedFetchMock;

    MPSuperTokenMetrics = loadFile(superTokenMetricsPath, 'MPSuperTokenMetrics', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    if (!global.fetch) {
      global.fetch = jest.fn(() => Promise.resolve());
    }

    mockMpSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-instance-id'),
    };

    metrics = new MPSuperTokenMetrics(mockMpSdkInstance);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('normalizeErrorMessage()', () => {
    test('Given null error, When normalizeErrorMessage() is called, Then should return "Unknown error"', () => {
      const result = metrics.normalizeErrorMessage(null);
      expect(result).toBe('Unknown error');
    });

    test('Given undefined error, When normalizeErrorMessage() is called, Then should return "Unknown error"', () => {
      const result = metrics.normalizeErrorMessage(undefined);
      expect(result).toBe('Unknown error');
    });

    test('Given error with message, When normalizeErrorMessage() is called, Then should return the message', () => {
      const result = metrics.normalizeErrorMessage({ message: 'Network error' });
      expect(result).toBe('Network error');
    });

    test('Given error without message, When normalizeErrorMessage() is called, Then should return stringified error', () => {
      const result = metrics.normalizeErrorMessage({ code: 500 });
      expect(result).toBe('{"code":500}');
    });

    test('Given error with email in message, When normalizeErrorMessage() is called, Then should return sanitized message', () => {
      const result = metrics.normalizeErrorMessage({ message: 'Invalid email address' });
      expect(result).toBe('invalid_email_address_provided');
    });
  });

  describe('errorToGetAccountPaymentMethods()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
      jest.spyOn(metrics, 'dispatchMelidataErrorEvent').mockImplementation(() => {});
    });

    test('Given error with message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with correct parameters', () => {
      const error = { message: 'Network error occurred' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        'Network error occurred'
      );
    });

    test('Given error with message, When errorToGetAccountPaymentMethods() is called, Then should dispatch melidata error event', () => {
      const error = { message: 'Network error occurred' };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'Network error occurred',
        metrics.CUSTOM_CHECKOUT_STEPS.LOAD_SUPER_TOKEN
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

    test('Given error without message, When errorToGetAccountPaymentMethods() is called, Then should call sendMetric with stringified error', () => {
      const error = { code: 500 };

      metrics.errorToGetAccountPaymentMethods(error);

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'error_to_get_account_payment_methods',
        'true',
        '{"code":500}'
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

    test('Given error code and error, When errorOnSubmit() is called, Then should dispatch melidata error event for POST_SUBMIT step', () => {
      const error = { message: 'Submit failed' };

      metrics.errorOnSubmit('SELECT_PAYMENT_METHOD_ERROR', error);

      expect(metrics.dispatchMelidataErrorEvent).toHaveBeenCalledWith(
        'Submit failed',
        metrics.CUSTOM_CHECKOUT_STEPS.POST_SUBMIT
      );
    });
  });

  describe('sendMetric()', () => {
    test('Given valid parameters, When sendMetric() is called, Then should execute without errors', () => {
      const sendMetricInstance = new MPSuperTokenMetrics(mockMpSdkInstance);

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

  describe('registerClickOnPlaceOrderButton()', () => {
    beforeEach(() => {
      jest.spyOn(metrics, 'sendMetric').mockImplementation(() => {});
    });

    test('Given registerClickOnPlaceOrderButton() is called, Then should send metric with correct name', () => {
      metrics.registerClickOnPlaceOrderButton();

      expect(metrics.sendMetric).toHaveBeenCalledWith(
        'super_token_click_on_place_order_button',
        'true',
        ''
      );
    });
  });
});

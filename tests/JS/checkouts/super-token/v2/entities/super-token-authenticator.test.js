const { resolveAlias } = require('../../../../helpers/path-resolver');
const { loadFile } = require('../../../../helpers/load-file');
const filePath = resolveAlias(`assets/js/checkouts/super-token/${global.SUPER_TOKEN_VERSION}/entities/super-token-authenticator.js`);

describe('MPSuperTokenAuthenticator', () => {
  let MPSuperTokenAuthenticator;
  let authenticator;
  let mockSdkInstance;
  let mockPaymentMethods;
  let mockMetrics;

  beforeAll(() => {
    global.wc_mercadopago_supertoken_bundle_params = {
      platform_id: 'test-platform-id',
    };

    global.MPSuperTokenErrorCodes = {
      AUTHENTICATOR_NOT_FOUND: 'AUTHENTICATOR_NOT_FOUND',
      AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
      AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
      EMPTY_ACCOUNT_PAYMENT_METHODS: 'EMPTY_ACCOUNT_PAYMENT_METHODS',
    };

    MPSuperTokenAuthenticator = loadFile(filePath, 'MPSuperTokenAuthenticator', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();

    mockSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-id'),
      authenticator: jest.fn(),
    };

    mockPaymentMethods = {
      getAccountPaymentMethods: jest.fn(),
    };

    mockMetrics = {
      sendMetric: jest.fn(),
      errorToBuildAuthenticator: jest.fn(),
      errorToGetSimplifiedAuth: jest.fn(),
      errorToGetFastPaymentToken: jest.fn(),
      errorToAuthorizePayment: jest.fn(),
      errorToGetAccountPaymentMethods: jest.fn(),
      isNotSimplifiedAuth: jest.fn(),
      canUseSuperToken: jest.fn(),
      cannotGetFastPaymentToken: jest.fn(),
      registerAuthorizedPseudotoken: jest.fn(),
    };

    authenticator = new MPSuperTokenAuthenticator(mockSdkInstance, mockPaymentMethods, mockMetrics);
  });

  describe('getSimplifiedAuth()', () => {
    test('Given authenticator is null, When getSimplifiedAuth() is called, Then should send super_token_authenticator_null metric', async () => {
      await authenticator.getSimplifiedAuth(null);

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_null',
        'getSimplifiedAuth',
        ''
      );
    });

    test('Given authenticator is null, When getSimplifiedAuth() is called, Then should return false', async () => {
      const result = await authenticator.getSimplifiedAuth(null);

      expect(result).toBe(false);
    });

    test('Given authenticator is undefined, When getSimplifiedAuth() is called, Then should send super_token_authenticator_null metric', async () => {
      await authenticator.getSimplifiedAuth(undefined);

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_null',
        'getSimplifiedAuth',
        ''
      );
    });

    test('Given valid authenticator, When getSimplifiedAuth() is called, Then should not send authenticator_null metric', async () => {
      const mockAuth = { getSimplifiedAuth: jest.fn().mockResolvedValue(true) };

      await authenticator.getSimplifiedAuth(mockAuth);

      expect(mockMetrics.sendMetric).not.toHaveBeenCalledWith(
        'super_token_authenticator_null',
        expect.any(String),
        expect.any(String)
      );
    });

    test('Given valid authenticator, When getSimplifiedAuth() resolves, Then should return its value', async () => {
      const mockAuth = { getSimplifiedAuth: jest.fn().mockResolvedValue(true) };

      const result = await authenticator.getSimplifiedAuth(mockAuth);

      expect(result).toBe(true);
    });
  });

  describe('getFastPaymentToken()', () => {
    test('Given authenticator is null, When getFastPaymentToken() is called, Then should send super_token_authenticator_null metric', async () => {
      await authenticator.getFastPaymentToken(null);

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_null',
        'getFastPaymentToken',
        ''
      );
    });

    test('Given authenticator is null, When getFastPaymentToken() is called, Then should return null', async () => {
      const result = await authenticator.getFastPaymentToken(null);

      expect(result).toBeNull();
    });

    test('Given authenticator is undefined, When getFastPaymentToken() is called, Then should send super_token_authenticator_null metric', async () => {
      await authenticator.getFastPaymentToken(undefined);

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_null',
        'getFastPaymentToken',
        ''
      );
    });

    test('Given valid authenticator, When getFastPaymentToken() is called, Then should not send authenticator_null metric', async () => {
      const mockAuth = { getFastPaymentToken: jest.fn().mockResolvedValue('token-123') };

      await authenticator.getFastPaymentToken(mockAuth);

      expect(mockMetrics.sendMetric).not.toHaveBeenCalledWith(
        'super_token_authenticator_null',
        expect.any(String),
        expect.any(String)
      );
    });
  });

  describe('buildAuthenticator()', () => {
    test('Given SDK resolves null without throwing, When buildAuthenticator() is called, Then should send super_token_authenticator_falsy metric and return null', async () => {
      mockSdkInstance.authenticator.mockResolvedValue(null);

      const result = await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_falsy',
        'null',
        'typeof:object'
      );
      expect(result).toBeNull();
      expect(mockMetrics.errorToBuildAuthenticator).not.toHaveBeenCalled();
    });

    test('Given SDK resolves false without throwing, When buildAuthenticator() is called, Then should report the returned value and its type and return null', async () => {
      mockSdkInstance.authenticator.mockResolvedValue(false);

      const result = await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_authenticator_falsy',
        'false',
        'typeof:boolean'
      );
      expect(result).toBeNull();
      expect(mockMetrics.errorToBuildAuthenticator).not.toHaveBeenCalled();
    });

    test('Given SDK resolves a valid authenticator, When buildAuthenticator() is called, Then should not send super_token_authenticator_falsy metric and should return it', async () => {
      const mockAuth = { getSimplifiedAuth: jest.fn() };
      mockSdkInstance.authenticator.mockResolvedValue(mockAuth);

      const result = await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(mockMetrics.sendMetric).not.toHaveBeenCalledWith(
        'super_token_authenticator_falsy',
        expect.any(String),
        expect.any(String)
      );
      expect(result).toBe(mockAuth);
    });
  });

  describe('authorizePayment()', () => {
    test('Given stored authenticator returns no simplified auth, When authorizePayment() is called, Then should send super_token_auth_expired_on_submit metric', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(false),
        authorizePayment: jest.fn(),
      };
      authenticator.storeAuthenticator(mockAuth);

      await authenticator.authorizePayment('pseudotoken-123').catch(() => {});

      expect(mockMetrics.sendMetric).toHaveBeenCalledWith(
        'super_token_auth_expired_on_submit',
        'true',
        ''
      );
    });

    test('Given stored authenticator returns no simplified auth, When authorizePayment() is called, Then should return without calling authorizePayment on authenticator', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(false),
        authorizePayment: jest.fn(),
      };
      authenticator.storeAuthenticator(mockAuth);

      await authenticator.authorizePayment('pseudotoken-123').catch(() => {});

      expect(mockAuth.authorizePayment).not.toHaveBeenCalled();
    });

    test('Given valid simplified auth, When authorizePayment() is called, Then should not send auth_expired metric', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockResolvedValue(undefined),
      };
      authenticator.storeAuthenticator(mockAuth);

      document.body.innerHTML = '<input id="authorized_pseudotoken" />';
      await authenticator.authorizePayment('pseudotoken-123').catch(() => {});

      expect(mockMetrics.sendMetric).not.toHaveBeenCalledWith(
        'super_token_auth_expired_on_submit',
        expect.any(String),
        expect.any(String)
      );
      document.body.innerHTML = '';
    });
  });

  describe('getAccountPaymentMethods()', () => {
    test('Given simplified auth resolves to true, When getAccountPaymentMethods() is called, Then should not call isNotSimplifiedAuth metric', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        getFastPaymentToken: jest.fn().mockResolvedValue(null),
      };
      mockSdkInstance.authenticator.mockResolvedValue(mockAuth);

      await authenticator.getAccountPaymentMethods(100, 'test@example.com');

      expect(mockMetrics.isNotSimplifiedAuth).not.toHaveBeenCalled();
    });

    test('Given simplified auth resolves to false, When getAccountPaymentMethods() is called, Then should call isNotSimplifiedAuth metric', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(false),
      };
      mockSdkInstance.authenticator.mockResolvedValue(mockAuth);

      await authenticator.getAccountPaymentMethods(100, 'test@example.com');

      expect(mockMetrics.isNotSimplifiedAuth).toHaveBeenCalledTimes(1);
    });

    test('Given buildAuthenticator returns null, When getAccountPaymentMethods() is called, Then neither auth metric is called', async () => {
      mockSdkInstance.authenticator.mockResolvedValue(null);

      await authenticator.getAccountPaymentMethods(100, 'test@example.com');

      expect(mockMetrics.isNotSimplifiedAuth).not.toHaveBeenCalled();
    });
  });
});

// =============================================================================
// T09 — Instrumentação do callSdkWithMetrics em buildAuthenticator e authorizePayment
// =============================================================================
describe('MPSuperTokenAuthenticator - T09 mp_api_error instrumentation', () => {
  let MPSuperTokenAuthenticatorT09;
  let authenticator;
  let mockSdkInstance;
  let mockMetrics;
  let sendMetricMock;
  const sdkMetricsPath = resolveAlias('assets/js/checkouts/mp-sdk-metrics.js');

  beforeAll(() => {
    // Globals que o source file exige (independente do describe anterior, em caso de execução isolada)
    global.wc_mercadopago_supertoken_bundle_params = {
      platform_id: 'test-platform-id',
    };
    global.MPSuperTokenErrorCodes = {
      AUTHENTICATOR_NOT_FOUND: 'AUTHENTICATOR_NOT_FOUND',
      AUTHORIZE_PAYMENT_METHOD_ERROR: 'AUTHORIZE_PAYMENT_METHOD_ERROR',
      AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED: 'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
      EMPTY_ACCOUNT_PAYMENT_METHODS: 'EMPTY_ACCOUNT_PAYMENT_METHODS',
    };

    sendMetricMock = jest.fn();

    // Carrega callSdkWithMetrics real com window.sendMetric mocado
    const callSdkWithMetrics = loadFile(sdkMetricsPath, 'callSdkWithMetrics', {
      window: { sendMetric: sendMetricMock },
    });

    // O source file usa window.callSdkWithMetrics — populamos no global.window
    global.window.callSdkWithMetrics = callSdkWithMetrics;

    MPSuperTokenAuthenticatorT09 = loadFile(filePath, 'MPSuperTokenAuthenticator', global);
  });

  beforeEach(() => {
    sendMetricMock.mockClear();

    mockSdkInstance = {
      getSDKInstanceId: jest.fn(() => 'test-sdk-id'),
      authenticator: jest.fn(),
    };

    mockMetrics = {
      sendMetric: jest.fn(),
      errorToBuildAuthenticator: jest.fn(),
      errorToGetSimplifiedAuth: jest.fn(),
      errorToGetFastPaymentToken: jest.fn(),
      errorToAuthorizePayment: jest.fn(),
      errorToGetAccountPaymentMethods: jest.fn(),
      isNotSimplifiedAuth: jest.fn(),
      canUseSuperToken: jest.fn(),
      cannotGetFastPaymentToken: jest.fn(),
      registerAuthorizedPseudotoken: jest.fn(),
    };

    authenticator = new MPSuperTokenAuthenticatorT09(mockSdkInstance, {}, mockMetrics);
  });

  describe('buildAuthenticator (T09 wrap)', () => {
    test('TC-T09-01: SDK rejeita → sendMetric com api_route=buildAuthenticator e payment_method=supertoken', async () => {
      const sdkError = {
        message: 'No applications were detected on device',
        cause: [{ code: 'E001', description: 'No app' }],
      };
      mockSdkInstance.authenticator.mockRejectedValue(sdkError);

      await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(sendMetricMock).toHaveBeenCalledWith(
        '0',
        'No applications were detected on device',
        'mp_api_error',
        { api_route: 'buildAuthenticator' }
      );
    });

    test('TC-T09-02: errorToBuildAuthenticator legado continua sendo chamado (métricas complementares)', async () => {
      const sdkError = { message: 'fail' };
      mockSdkInstance.authenticator.mockRejectedValue(sdkError);

      await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(mockMetrics.errorToBuildAuthenticator).toHaveBeenCalledWith(sdkError);
    });

    test('TC-T09-03: SDK resolve com sucesso → sendMetric NÃO é chamado', async () => {
      const fakeAuth = { id: 'auth-123' };
      mockSdkInstance.authenticator.mockResolvedValue(fakeAuth);

      const result = await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(result).toBe(fakeAuth);
      expect(sendMetricMock).not.toHaveBeenCalled();
    });
  });

  describe('authorizePayment (T09 wrap)', () => {
    test('TC-T09-04: SDK rejeita → sendMetric com api_route=authorizePayment e payment_method=supertoken', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockRejectedValue({
          message: 'auth failed',
          status: 400,
        }),
      };
      authenticator.storeAuthenticator(mockAuth);

      await authenticator.authorizePayment('fake-pseudotoken').catch(() => {});

      expect(sendMetricMock).toHaveBeenCalledWith(
        '400',
        'auth failed',
        'mp_api_error',
        { api_route: 'authorizePayment' }
      );
    });

    test('TC-T09-05: errorToAuthorizePayment legado continua sendo chamado (métricas complementares)', async () => {
      const sdkError = { message: 'auth failed', status: 400 };
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockRejectedValue(sdkError),
      };
      authenticator.storeAuthenticator(mockAuth);

      await authenticator.authorizePayment('fake-pseudotoken').catch(() => {});

      expect(mockMetrics.errorToAuthorizePayment).toHaveBeenCalled();
    });

    test('TC-T09-06: pré-validação interna (AUTHENTICATOR_NOT_FOUND) NÃO dispara mp_api_error', async () => {
      // Sem authenticator armazenado → throw interno antes da chamada SDK
      await authenticator.authorizePayment('any').catch(() => {});

      // sendMetric do mp_api_error não foi chamado — wrapper só captura erros DO SDK call
      expect(sendMetricMock).not.toHaveBeenCalled();
    });
  });

  describe('fallback quando window.callSdkWithMetrics não está disponível', () => {
    test('TC-T09-07: buildAuthenticator executa SDK normalmente mesmo sem window.callSdkWithMetrics', async () => {
      const fakeAuth = { id: 'auth-fallback' };
      mockSdkInstance.authenticator.mockResolvedValue(fakeAuth);

      delete global.window.callSdkWithMetrics;

      const result = await authenticator.buildAuthenticator(100, 'test@example.com');

      expect(result).toBe(fakeAuth);
      expect(sendMetricMock).not.toHaveBeenCalled();

      global.window.callSdkWithMetrics = loadFile(sdkMetricsPath, 'callSdkWithMetrics', {
        window: { sendMetric: sendMetricMock },
      });
    });

    test('TC-T09-08: authorizePayment executa SDK normalmente mesmo sem window.callSdkWithMetrics', async () => {
      const mockAuth = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockResolvedValue(undefined),
      };
      authenticator.storeAuthenticator(mockAuth);
      document.body.innerHTML = '<input id="authorized_pseudotoken" />';

      delete global.window.callSdkWithMetrics;

      await authenticator.authorizePayment('fake-pseudotoken').catch(() => {});

      expect(mockAuth.authorizePayment).toHaveBeenCalled();
      expect(sendMetricMock).not.toHaveBeenCalled();

      global.window.callSdkWithMetrics = loadFile(sdkMetricsPath, 'callSdkWithMetrics', {
        window: { sendMetric: sendMetricMock },
      });
      document.body.innerHTML = '';
    });
  });
});

const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');
const filePath = resolveAlias('assets/js/checkouts/super-token/entities/super-token-authenticator.js');

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
});

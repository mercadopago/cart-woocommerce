const { SuperTokenAuthenticator } = require('@super-token/adapters/runtime/SuperTokenAuthenticator');

const PLATFORM_ID = 'BP1';

const buildMetrics = (overrides = {}) => ({
  sendMetric: jest.fn(),
  errorToBuildAuthenticator: jest.fn(),
  errorToGetSimplifiedAuth: jest.fn(),
  errorToGetFastPaymentToken: jest.fn(),
  registerAuthorizedPseudotoken: jest.fn(),
  isNotSimplifiedAuth: jest.fn(),
  canUseSuperToken: jest.fn(),
  cannotGetFastPaymentToken: jest.fn(),
  errorToGetAccountPaymentMethods: jest.fn(),
  errorToAuthorizePayment: jest.fn(),
  ...overrides,
});

const build = ({ sdk = {}, paymentMethods = {}, metrics = buildMetrics() } = {}) => {
  const authenticator = new SuperTokenAuthenticator(sdk, paymentMethods, metrics, PLATFORM_ID);
  return { authenticator, sdk, paymentMethods, metrics };
};

describe('SuperTokenAuthenticator', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    delete window.callSdkWithMetrics;
  });

  describe('formatAmount', () => {
    it.each([
      ['1.234,56', '1234.56'], // European: dot thousands, comma decimal
      ['1,234.56', '1234.56'], // US: comma thousands, dot decimal
      ['R$ 10,00', '10.00'], // strips currency symbol, comma decimal
      ['10.5', '10.50'], // plain dot decimal
      ['', null], // empty
      ['abc', null], // no digits
    ])('Given %p, When formatted, Then it returns %p', (input, expected) => {
      const { authenticator } = build();
      expect(authenticator.formatAmount(input)).toBe(expected);
    });

    it('Given no argument, When formatted, Then it returns null', () => {
      const { authenticator } = build();
      expect(authenticator.formatAmount()).toBeNull();
    });
  });

  describe('state', () => {
    it('Given a built authenticator, When amount and e-mail are read, Then they reflect the last build call', async () => {
      const sdk = { authenticator: jest.fn().mockResolvedValue({}) };
      const { authenticator } = build({ sdk });

      await authenticator.buildAuthenticator('10.00', 'buyer@example.com');

      expect(authenticator.getAmountUsed()).toBe('10.00');
      expect(authenticator.getEmailUsed()).toBe('buyer@example.com');
    });

    it('Given a stored handle, When reset, Then the handle and fast payment token are cleared', () => {
      const { authenticator } = build();
      authenticator.storeAuthenticator({ id: 'HANDLE' });

      authenticator.reset();

      expect(authenticator.getStoredAuthenticator()).toBeNull();
    });

    it('Given a stored handle, When read, Then it returns the handle unchanged', () => {
      const { authenticator } = build();
      const handle = { getSimplifiedAuth: jest.fn() };

      authenticator.storeAuthenticator(handle);

      expect(authenticator.getStoredAuthenticator()).toBe(handle);
    });
  });

  describe('setSuperTokenValidation', () => {
    it('Given the validation input exists, When set to true, Then its value becomes "true"', () => {
      document.body.innerHTML = '<input id="super_token_validation" value="" />';
      const { authenticator } = build();

      authenticator.setSuperTokenValidation(true);

      expect(document.getElementById('super_token_validation').value).toBe('true');
    });

    it('Given the validation input exists, When set to false, Then its value becomes "false"', () => {
      document.body.innerHTML = '<input id="super_token_validation" value="true" />';
      const { authenticator } = build();

      authenticator.setSuperTokenValidation(false);

      expect(document.getElementById('super_token_validation').value).toBe('false');
    });

    it('Given the validation input is absent, When set, Then it does not throw', () => {
      const { authenticator } = build();

      expect(() => authenticator.setSuperTokenValidation(true)).not.toThrow();
    });
  });

  describe('buildAuthenticator', () => {
    it('Given a valid SDK, When built, Then it calls the SDK with the injected platform id and version 2', async () => {
      const handle = { id: 'HANDLE' };
      const sdk = { authenticator: jest.fn().mockResolvedValue(handle) };
      const { authenticator } = build({ sdk });

      const result = await authenticator.buildAuthenticator('10.00', 'buyer@example.com');

      expect(sdk.authenticator).toHaveBeenCalledWith('10.00', 'buyer@example.com', {
        platformId: PLATFORM_ID,
        version: 2,
      });
      expect(result).toBe(handle);
    });

    it('Given callSdkWithMetrics is present, When built, Then the SDK call is wrapped with the metric label', async () => {
      window.callSdkWithMetrics = jest.fn((sdkCall) => sdkCall());
      const sdk = { authenticator: jest.fn().mockResolvedValue({}) };
      const { authenticator } = build({ sdk });

      await authenticator.buildAuthenticator('10.00', 'buyer@example.com');

      expect(window.callSdkWithMetrics).toHaveBeenCalledWith(expect.any(Function), 'buildAuthenticator');
    });

    it('Given the SDK returns a falsy authenticator, When built, Then it reports the falsy metric and returns null', async () => {
      const sdk = { authenticator: jest.fn().mockResolvedValue(null) };
      const metrics = buildMetrics();
      const { authenticator } = build({ sdk, metrics });

      const result = await authenticator.buildAuthenticator('10.00', 'buyer@example.com');

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_authenticator_falsy', 'null', 'typeof:object');
      expect(result).toBeNull();
    });

    it('Given the SDK throws, When built, Then it reports the build error metric and returns null', async () => {
      const error = new Error('sdk down');
      const sdk = { authenticator: jest.fn().mockRejectedValue(error) };
      const metrics = buildMetrics();
      const { authenticator } = build({ sdk, metrics });

      const result = await authenticator.buildAuthenticator('10.00', 'buyer@example.com');

      expect(metrics.errorToBuildAuthenticator).toHaveBeenCalledWith(error);
      expect(result).toBeNull();
    });
  });

  describe('getSimplifiedAuth', () => {
    it('Given a handle, When queried, Then it returns the SDK result', async () => {
      const handle = { getSimplifiedAuth: jest.fn().mockResolvedValue(true) };
      const { authenticator } = build();

      await expect(authenticator.getSimplifiedAuth(handle)).resolves.toBe(true);
    });

    it('Given the handle throws, When queried, Then it reports the metric and returns false', async () => {
      const error = new Error('boom');
      const handle = { getSimplifiedAuth: jest.fn().mockRejectedValue(error) };
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });

      const result = await authenticator.getSimplifiedAuth(handle);

      expect(metrics.errorToGetSimplifiedAuth).toHaveBeenCalledWith(error);
      expect(result).toBe(false);
    });
  });

  describe('getFastPaymentToken', () => {
    it('Given a handle, When queried, Then it returns the SDK token', async () => {
      const handle = { getFastPaymentToken: jest.fn().mockResolvedValue('FAST_1') };
      const { authenticator } = build();

      await expect(authenticator.getFastPaymentToken(handle)).resolves.toBe('FAST_1');
    });

    it('Given the handle throws, When queried, Then it reports the metric and returns null', async () => {
      const error = new Error('boom');
      const handle = { getFastPaymentToken: jest.fn().mockRejectedValue(error) };
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });

      const result = await authenticator.getFastPaymentToken(handle);

      expect(metrics.errorToGetFastPaymentToken).toHaveBeenCalledWith(error);
      expect(result).toBeNull();
    });
  });

  describe('storeAuthorizedPseudotoken', () => {
    it('Given the pseudotoken input exists, When stored, Then it writes the value and registers the metric as existing', () => {
      document.body.innerHTML = '<input id="authorized_pseudotoken" value="" />';
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });

      authenticator.storeAuthorizedPseudotoken('PSEUDO_1');

      expect(document.getElementById('authorized_pseudotoken').value).toBe('PSEUDO_1');
      expect(metrics.registerAuthorizedPseudotoken).toHaveBeenCalledWith(true);
    });

    it('Given the pseudotoken input is absent, When stored, Then it registers the metric as not existing and does not throw', () => {
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });

      expect(() => authenticator.storeAuthorizedPseudotoken('PSEUDO_1')).not.toThrow();
      expect(metrics.registerAuthorizedPseudotoken).toHaveBeenCalledWith(false);
    });
  });

  describe('getAccountPaymentMethods (load orchestration)', () => {
    const buildLoadSdk = () => {
      const handle = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        getFastPaymentToken: jest.fn().mockResolvedValue('FAST_1'),
      };
      return { authenticator: jest.fn().mockResolvedValue(handle), handle };
    };

    it('Given a full happy path, When loading, Then it stores the handle/token and returns the account methods', async () => {
      const sdk = buildLoadSdk();
      const methods = [{ token: 'PM_1' }];
      const paymentMethods = { getAccountPaymentMethods: jest.fn().mockResolvedValue({ data: methods }) };
      const metrics = buildMetrics();
      const { authenticator } = build({ sdk, paymentMethods, metrics });

      const result = await authenticator.getAccountPaymentMethods('10.00', 'buyer@example.com');

      expect(result).toEqual(methods);
      expect(authenticator.getStoredAuthenticator()).toBe(sdk.handle);
      expect(paymentMethods.getAccountPaymentMethods).toHaveBeenCalledWith('FAST_1');
      expect(metrics.canUseSuperToken).toHaveBeenCalledWith(true);
    });

    it('Given auth is not simplified, When loading, Then it reports the metric and returns null (fail-safe)', async () => {
      const handle = { getSimplifiedAuth: jest.fn().mockResolvedValue(false), getFastPaymentToken: jest.fn() };
      const sdk = { authenticator: jest.fn().mockResolvedValue(handle) };
      const metrics = buildMetrics();
      const { authenticator } = build({ sdk, metrics });

      const result = await authenticator.getAccountPaymentMethods('10.00', 'buyer@example.com');

      expect(metrics.isNotSimplifiedAuth).toHaveBeenCalledTimes(1);
      expect(result).toBeNull();
    });

    it('Given the account methods are empty, When loading, Then it reports the load error and returns null', async () => {
      const sdk = buildLoadSdk();
      const paymentMethods = { getAccountPaymentMethods: jest.fn().mockResolvedValue({ data: [] }) };
      const metrics = buildMetrics();
      const { authenticator } = build({ sdk, paymentMethods, metrics });

      const result = await authenticator.getAccountPaymentMethods('10.00', 'buyer@example.com');

      expect(metrics.errorToGetAccountPaymentMethods).toHaveBeenCalledTimes(1);
      expect(result).toBeNull();
    });
  });

  describe('authorizePayment (submit orchestration)', () => {
    it('Given a stored, still-simplified handle, When authorizing, Then it authorizes on the SDK and stores the pseudotoken', async () => {
      document.body.innerHTML = '<input id="authorized_pseudotoken" value="" />';
      const handle = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockResolvedValue(undefined),
      };
      const { authenticator } = build();
      authenticator.storeAuthenticator(handle);

      await authenticator.authorizePayment('PSEUDO_1');

      expect(handle.authorizePayment).toHaveBeenCalledWith('PSEUDO_1');
      expect(document.getElementById('authorized_pseudotoken').value).toBe('PSEUDO_1');
    });

    it('Given no stored handle, When authorizing, Then it throws the generic authorize error code', async () => {
      const { authenticator } = build();

      await expect(authenticator.authorizePayment('PSEUDO_1')).rejects.toThrow('AUTHORIZE_PAYMENT_METHOD_ERROR');
    });

    it('Given the SDK reports USER_CANCELLED, When authorizing, Then it throws the user-cancelled code', async () => {
      const handle = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(true),
        authorizePayment: jest.fn().mockRejectedValue(new Error('USER_CANCELLED')),
      };
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });
      authenticator.storeAuthenticator(handle);

      await expect(authenticator.authorizePayment('PSEUDO_1')).rejects.toThrow(
        'AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED',
      );
      expect(metrics.errorToAuthorizePayment).toHaveBeenCalledTimes(1);
    });

    it('Given the auth expired between load and submit, When authorizing, Then it reports the metric and does not authorize', async () => {
      const handle = {
        getSimplifiedAuth: jest.fn().mockResolvedValue(false),
        authorizePayment: jest.fn(),
      };
      const metrics = buildMetrics();
      const { authenticator } = build({ metrics });
      authenticator.storeAuthenticator(handle);

      await authenticator.authorizePayment('PSEUDO_1');

      expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_auth_expired_on_submit', 'true', '');
      expect(handle.authorizePayment).not.toHaveBeenCalled();
    });
  });
});

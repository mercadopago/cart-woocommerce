const { LegacyAuthenticatorSession } = require('@super-token/adapters/session/LegacyAuthenticatorSession');

const AUTHENTICATOR = { id: 'auth-handle' };

const buildAuthenticatorInstance = (overrides = {}) => ({
  buildAuthenticator: jest.fn(async () => AUTHENTICATOR),
  storeAuthenticator: jest.fn(),
  getSimplifiedAuth: jest.fn(async () => true),
  getFastPaymentToken: jest.fn(async () => 'fast-token'),
  storeFastPaymentToken: jest.fn(),
  getStoredAuthenticator: jest.fn(() => AUTHENTICATOR),
  storeAuthorizedPseudotoken: jest.fn(),
  ...overrides,
});

afterEach(() => {
  delete window.callSdkWithMetrics;
});

const buildPaymentMethods = (overrides = {}) => ({
  getAccountPaymentMethods: jest.fn(async () => ({ data: [] })),
  ...overrides,
});

describe('LegacyAuthenticatorSession', () => {
  it('Given an amount and e-mail, When building the authenticator, Then it forwards them to the legacy instance', async () => {
    const authenticator = buildAuthenticatorInstance();
    const session = new LegacyAuthenticatorSession(authenticator, buildPaymentMethods());

    const result = await session.buildAuthenticator('10.00', 'buyer@test.com');

    expect(authenticator.buildAuthenticator).toHaveBeenCalledWith('10.00', 'buyer@test.com');
    expect(result).toBe(AUTHENTICATOR);
  });

  it('Given a handle, When storing/verifying/tokenizing, Then it forwards each call to the legacy authenticator', async () => {
    const authenticator = buildAuthenticatorInstance();
    const session = new LegacyAuthenticatorSession(authenticator, buildPaymentMethods());

    session.storeAuthenticator(AUTHENTICATOR);
    await session.getSimplifiedAuth(AUTHENTICATOR);
    await session.getFastPaymentToken(AUTHENTICATOR);
    session.storeFastPaymentToken('fast-token');

    expect(authenticator.storeAuthenticator).toHaveBeenCalledWith(AUTHENTICATOR);
    expect(authenticator.getSimplifiedAuth).toHaveBeenCalledWith(AUTHENTICATOR);
    expect(authenticator.getFastPaymentToken).toHaveBeenCalledWith(AUTHENTICATOR);
    expect(authenticator.storeFastPaymentToken).toHaveBeenCalledWith('fast-token');
  });

  it('Given a token, When fetching account payment methods, Then it forwards to the legacy controller', async () => {
    const response = { data: [{ token: 'tok-1', type: 'credit_card' }] };
    const paymentMethods = buildPaymentMethods({ getAccountPaymentMethods: jest.fn(async () => response) });
    const session = new LegacyAuthenticatorSession(buildAuthenticatorInstance(), paymentMethods);

    const result = await session.fetchAccountPaymentMethods('fast-token');

    expect(paymentMethods.getAccountPaymentMethods).toHaveBeenCalledWith('fast-token');
    expect(result).toBe(response);
  });

  it('Given the behavior-tracking step, When notified, Then it dispatches the legacy init event on the document', () => {
    const session = new LegacyAuthenticatorSession(buildAuthenticatorInstance(), buildPaymentMethods());
    const listener = jest.fn();
    document.addEventListener('mp-behavior-tracking-super-token-init', listener);

    session.notifyBehaviorTrackingInit();

    expect(listener).toHaveBeenCalledTimes(1);
    document.removeEventListener('mp-behavior-tracking-super-token-init', listener);
  });

  it('Given the stored handle and an authorized pseudotoken, When forwarded, Then it reads/writes them on the legacy authenticator', () => {
    const authenticator = buildAuthenticatorInstance();
    const session = new LegacyAuthenticatorSession(authenticator, buildPaymentMethods());

    expect(session.getStoredAuthenticator()).toBe(AUTHENTICATOR);
    session.storeAuthorizedPseudotoken('pseudo-1');
    expect(authenticator.storeAuthorizedPseudotoken).toHaveBeenCalledWith('pseudo-1');
  });

  it('Given callSdkWithMetrics is available, When authorizing on the SDK, Then it wraps the authorize call with the legacy label', async () => {
    window.callSdkWithMetrics = jest.fn((sdkCall) => sdkCall());
    const handle = { authorizePayment: jest.fn(async () => 'ok') };
    const session = new LegacyAuthenticatorSession(buildAuthenticatorInstance(), buildPaymentMethods());

    const result = await session.authorizePaymentOnSdk(handle, 'pseudo-1');

    expect(window.callSdkWithMetrics).toHaveBeenCalledWith(expect.any(Function), 'authorizePayment');
    expect(handle.authorizePayment).toHaveBeenCalledWith('pseudo-1');
    expect(result).toBe('ok');
  });

  it('Given callSdkWithMetrics is absent, When authorizing on the SDK, Then it still calls the SDK authorize directly', async () => {
    const handle = { authorizePayment: jest.fn(async () => 'ok') };
    const session = new LegacyAuthenticatorSession(buildAuthenticatorInstance(), buildPaymentMethods());

    const result = await session.authorizePaymentOnSdk(handle, 'pseudo-1');

    expect(handle.authorizePayment).toHaveBeenCalledWith('pseudo-1');
    expect(result).toBe('ok');
  });
});

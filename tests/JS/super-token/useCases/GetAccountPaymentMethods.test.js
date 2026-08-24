const { GetAccountPaymentMethods } = require('@super-token/useCases/GetAccountPaymentMethods');

const AUTHENTICATOR = { id: 'auth-handle' };
const FAST_TOKEN = 'fast-token-1';
const METHODS = [{ token: 'tok-1', type: 'credit_card' }];

const buildSession = (overrides = {}) => ({
  buildAuthenticator: jest.fn(async () => AUTHENTICATOR),
  storeAuthenticator: jest.fn(),
  getSimplifiedAuth: jest.fn(async () => true),
  notifyBehaviorTrackingInit: jest.fn(),
  getFastPaymentToken: jest.fn(async () => FAST_TOKEN),
  storeFastPaymentToken: jest.fn(),
  fetchAccountPaymentMethods: jest.fn(async () => ({ data: METHODS })),
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  isNotSimplifiedAuth: jest.fn(),
  canUseSuperToken: jest.fn(),
  cannotGetFastPaymentToken: jest.fn(),
  errorToGetAccountPaymentMethods: jest.fn(),
  ...overrides,
});

const run = (session, metrics) =>
  new GetAccountPaymentMethods().execute({
    session,
    metrics,
    amount: '10.00',
    buyerEmail: 'buyer@test.com',
  });

describe('GetAccountPaymentMethods', () => {
  it('Given the full auth flow succeeds, When executed, Then it stores the handle and token and returns the methods', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(session.buildAuthenticator).toHaveBeenCalledWith('10.00', 'buyer@test.com');
    expect(session.storeAuthenticator).toHaveBeenCalledWith(AUTHENTICATOR);
    expect(session.notifyBehaviorTrackingInit).toHaveBeenCalledTimes(1);
    expect(metrics.canUseSuperToken).toHaveBeenCalledWith(true);
    expect(session.storeFastPaymentToken).toHaveBeenCalledWith(FAST_TOKEN);
    expect(session.fetchAccountPaymentMethods).toHaveBeenCalledWith(FAST_TOKEN);
    expect(result).toBe(METHODS);
    expect(metrics.errorToGetAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the authenticator cannot be built, When executed, Then it returns null and stores nothing', async () => {
    const session = buildSession({ buildAuthenticator: jest.fn(async () => null) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(session.storeAuthenticator).not.toHaveBeenCalled();
    expect(session.getSimplifiedAuth).not.toHaveBeenCalled();
  });

  it('Given the auth is not simplified, When executed, Then it reports the metric and returns null without proceeding', async () => {
    const session = buildSession({ getSimplifiedAuth: jest.fn(async () => false) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(metrics.isNotSimplifiedAuth).toHaveBeenCalledTimes(1);
    expect(session.notifyBehaviorTrackingInit).not.toHaveBeenCalled();
    expect(session.getFastPaymentToken).not.toHaveBeenCalled();
  });

  it('Given no fast payment token, When executed, Then it reports the metric and returns null without fetching', async () => {
    const session = buildSession({ getFastPaymentToken: jest.fn(async () => null) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(metrics.cannotGetFastPaymentToken).toHaveBeenCalledTimes(1);
    expect(session.storeFastPaymentToken).not.toHaveBeenCalled();
    expect(session.fetchAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given an empty account payment methods list, When executed, Then it reports the error metric and returns null', async () => {
    const session = buildSession({ fetchAccountPaymentMethods: jest.fn(async () => ({ data: [] })) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(metrics.errorToGetAccountPaymentMethods).toHaveBeenCalledTimes(1);
    const [error] = metrics.errorToGetAccountPaymentMethods.mock.calls[0];
    expect(error).toBeInstanceOf(Error);
    expect(error.message).toBe('EMPTY_ACCOUNT_PAYMENT_METHODS');
  });

  it('Given a fetch that resolves to null, When executed, Then it reports the error metric and returns null', async () => {
    const session = buildSession({ fetchAccountPaymentMethods: jest.fn(async () => null) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(metrics.errorToGetAccountPaymentMethods).toHaveBeenCalledTimes(1);
  });

  it('Given a primitive throws, When executed, Then it is fail-safe: reports the error metric and returns null', async () => {
    const failure = new Error('sdk exploded');
    const session = buildSession({ buildAuthenticator: jest.fn(async () => { throw failure; }) });
    const metrics = buildMetrics();

    const result = await run(session, metrics);

    expect(result).toBeNull();
    expect(metrics.errorToGetAccountPaymentMethods).toHaveBeenCalledWith(failure);
  });
});

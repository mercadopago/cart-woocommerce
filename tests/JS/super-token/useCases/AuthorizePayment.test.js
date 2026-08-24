const { AuthorizePayment } = require('@super-token/useCases/AuthorizePayment');

const AUTHENTICATOR = { id: 'auth-handle' };
const PSEUDOTOKEN = 'pseudo-1';

const buildSession = (overrides = {}) => ({
  getStoredAuthenticator: jest.fn(() => AUTHENTICATOR),
  getSimplifiedAuth: jest.fn(async () => true),
  authorizePaymentOnSdk: jest.fn(async () => undefined),
  storeAuthorizedPseudotoken: jest.fn(),
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  sendMetric: jest.fn(),
  errorToAuthorizePayment: jest.fn(),
  ...overrides,
});

const run = (session, metrics) =>
  new AuthorizePayment().execute({ session, metrics, pseudotoken: PSEUDOTOKEN });

describe('AuthorizePayment', () => {
  it('Given a valid stored authenticator, When executed, Then it authorizes on the SDK and stores the pseudotoken', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.authorizePaymentOnSdk).toHaveBeenCalledWith(AUTHENTICATOR, PSEUDOTOKEN);
    expect(session.storeAuthorizedPseudotoken).toHaveBeenCalledWith(PSEUDOTOKEN);
    expect(metrics.errorToAuthorizePayment).not.toHaveBeenCalled();
  });

  it('Given no stored authenticator, When executed, Then it reports the error and throws the generic authorize error', async () => {
    const session = buildSession({ getStoredAuthenticator: jest.fn(() => null) });
    const metrics = buildMetrics();

    await expect(run(session, metrics)).rejects.toThrow('AUTHORIZE_PAYMENT_METHOD_ERROR');
    expect(metrics.errorToAuthorizePayment).toHaveBeenCalledTimes(1);
    expect(session.authorizePaymentOnSdk).not.toHaveBeenCalled();
  });

  it('Given the auth expired between load and submit, When executed, Then it reports the metric and returns without authorizing', async () => {
    const session = buildSession({ getSimplifiedAuth: jest.fn(async () => false) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(metrics.sendMetric).toHaveBeenCalledWith('super_token_auth_expired_on_submit', 'true', '');
    expect(session.authorizePaymentOnSdk).not.toHaveBeenCalled();
    expect(session.storeAuthorizedPseudotoken).not.toHaveBeenCalled();
    expect(metrics.errorToAuthorizePayment).not.toHaveBeenCalled();
  });

  it('Given the buyer cancels the authorization, When executed, Then it reports the error and throws the user-cancelled code', async () => {
    const session = buildSession({
      authorizePaymentOnSdk: jest.fn(async () => { throw new Error('SDK: USER_CANCELLED the prompt'); }),
    });
    const metrics = buildMetrics();

    await expect(run(session, metrics)).rejects.toThrow('AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED');
    expect(metrics.errorToAuthorizePayment).toHaveBeenCalledTimes(1);
    expect(session.storeAuthorizedPseudotoken).not.toHaveBeenCalled();
  });

  it('Given the SDK rejects with a non-Error carrying USER_CANCELLED, When executed, Then it still throws the user-cancelled code', async () => {
    const session = buildSession({
      authorizePaymentOnSdk: jest.fn(async () => { throw { message: 'USER_CANCELLED' }; }),
    });
    const metrics = buildMetrics();

    await expect(run(session, metrics)).rejects.toThrow('AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED');
  });

  it('Given the SDK authorize fails for another reason, When executed, Then it reports the error and throws the generic authorize error', async () => {
    const failure = new Error('network down');
    const session = buildSession({ authorizePaymentOnSdk: jest.fn(async () => { throw failure; }) });
    const metrics = buildMetrics();

    await expect(run(session, metrics)).rejects.toThrow('AUTHORIZE_PAYMENT_METHOD_ERROR');
    expect(metrics.errorToAuthorizePayment).toHaveBeenCalledWith(failure);
  });
});

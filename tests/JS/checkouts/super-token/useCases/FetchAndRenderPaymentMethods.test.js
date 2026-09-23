const { FetchAndRenderPaymentMethods } = require('@super-token/useCases/FetchAndRenderPaymentMethods');

const AMOUNT = '100.00';
const EMAIL = 'buyer@test.com';
const METHODS = [{ token: 'tok-1', type: 'credit_card' }];

const buildSession = (overrides = {}) => ({
  getBuyerEmail: jest.fn(() => EMAIL),
  isValidEmail: jest.fn(() => true),
  setFetching: jest.fn(),
  getLoadGeneration: jest.fn(() => 0),
  currentAmount: jest.fn(() => AMOUNT),
  fetchAccountPaymentMethods: jest.fn(async () => METHODS),
  renderAccountPaymentMethods: jest.fn(),
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  skippedNoEmail: jest.fn(),
  skippedInvalidEmail: jest.fn(),
  emailCaptured: jest.fn(),
  ...overrides,
});

const run = (session, metrics) =>
  new FetchAndRenderPaymentMethods().execute({ session, metrics });

describe('FetchAndRenderPaymentMethods', () => {
  it('Given a valid e-mail and methods, When executed, Then it fetches with the current amount and renders the raw methods', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(metrics.emailCaptured).toHaveBeenCalledTimes(1);
    expect(session.setFetching).toHaveBeenNthCalledWith(1, true);
    expect(session.fetchAccountPaymentMethods).toHaveBeenCalledWith(AMOUNT, EMAIL);
    expect(session.setFetching).toHaveBeenNthCalledWith(2, false);
    expect(session.renderAccountPaymentMethods).toHaveBeenCalledWith(METHODS, AMOUNT);
  });

  it('Given no buyer e-mail, When executed, Then it reports the metric and never fetches', async () => {
    const session = buildSession({ getBuyerEmail: jest.fn(() => '') });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(metrics.skippedNoEmail).toHaveBeenCalledTimes(1);
    expect(session.setFetching).not.toHaveBeenCalled();
    expect(session.fetchAccountPaymentMethods).not.toHaveBeenCalled();
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given an invalid e-mail, When executed, Then it reports the metric and never fetches', async () => {
    const session = buildSession({ isValidEmail: jest.fn(() => false) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(metrics.skippedInvalidEmail).toHaveBeenCalledTimes(1);
    expect(metrics.emailCaptured).not.toHaveBeenCalled();
    expect(session.fetchAccountPaymentMethods).not.toHaveBeenCalled();
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the load generation changed while fetching, When executed, Then it drops the stale result without rendering', async () => {
    const getLoadGeneration = jest.fn().mockReturnValueOnce(0).mockReturnValueOnce(1);
    const session = buildSession({ getLoadGeneration });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.fetchAccountPaymentMethods).toHaveBeenCalledTimes(1);
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
    // Stale: it returns before clearing the fetching flag (only the toggle-on ran).
    expect(session.setFetching).toHaveBeenCalledTimes(1);
    expect(session.setFetching).toHaveBeenCalledWith(true);
  });

  it('Given the fetch rejects while we still own the load, When executed, Then it releases the fetching flag and renders nothing', async () => {
    const session = buildSession({
      fetchAccountPaymentMethods: jest.fn(async () => { throw new Error('network'); }),
    });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.setFetching).toHaveBeenNthCalledWith(1, true);
    expect(session.setFetching).toHaveBeenNthCalledWith(2, false);
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the fetch rejects but a newer generation superseded us, When executed, Then it leaves the flag to the newer load', async () => {
    const getLoadGeneration = jest.fn().mockReturnValueOnce(0).mockReturnValueOnce(1);
    const session = buildSession({
      getLoadGeneration,
      fetchAccountPaymentMethods: jest.fn(async () => { throw new Error('network'); }),
    });
    const metrics = buildMetrics();

    await run(session, metrics);

    // Only the toggle-on ran: the newer generation owns the flag, so we must not clear it here.
    expect(session.setFetching).toHaveBeenCalledTimes(1);
    expect(session.setFetching).toHaveBeenCalledWith(true);
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the fetch resolves to null, When executed, Then it clears the fetching flag and renders nothing', async () => {
    const session = buildSession({ fetchAccountPaymentMethods: jest.fn(async () => null) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.setFetching).toHaveBeenNthCalledWith(2, false);
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the fetch resolves to an empty list, When executed, Then it clears the fetching flag and renders nothing', async () => {
    const session = buildSession({ fetchAccountPaymentMethods: jest.fn(async () => []) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.setFetching).toHaveBeenNthCalledWith(2, false);
    expect(session.renderAccountPaymentMethods).not.toHaveBeenCalled();
  });

  it('Given the amount changes between fetch and render, When executed, Then each step reads the live current amount', async () => {
    const currentAmount = jest.fn().mockReturnValueOnce('10.00').mockReturnValueOnce('20.00');
    const session = buildSession({ currentAmount });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.fetchAccountPaymentMethods).toHaveBeenCalledWith('10.00', EMAIL);
    expect(session.renderAccountPaymentMethods).toHaveBeenCalledWith(METHODS, '20.00');
  });
});

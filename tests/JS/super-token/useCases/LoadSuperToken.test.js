const { LoadSuperToken } = require('@super-token/useCases/LoadSuperToken');

const buildSession = (overrides = {}) => ({
  formatAmount: jest.fn((amount) => `formatted:${amount}`),
  setCurrentAmount: jest.fn(),
  currentAmount: jest.fn(() => 'formatted:100.00'),
  isFetching: jest.fn(() => false),
  amountHasChanged: jest.fn(() => false),
  emailHasChanged: jest.fn(() => false),
  resetFlow: jest.fn(),
  isMethodsLoaded: jest.fn(() => false),
  renderStored: jest.fn(),
  ensureEmailListenerRegistered: jest.fn(),
  fetchAndRender: jest.fn(async () => {}),
  dispatchStaleCacheMetricsOnce: jest.fn(),
  ...overrides,
});

const buildMetrics = (overrides = {}) => ({
  resetOnAmountChange: jest.fn(),
  ...overrides,
});

const run = (session, metrics, currentAmount = '100.00') =>
  new LoadSuperToken().execute({ session, metrics, currentAmount });

describe('LoadSuperToken', () => {
  it('Given a fresh load, When executed, Then it formats+stores the amount, registers the listener, fetches+renders, and dispatches stale metrics', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.formatAmount).toHaveBeenCalledWith('100.00');
    expect(session.setCurrentAmount).toHaveBeenCalledWith('formatted:100.00');
    expect(session.ensureEmailListenerRegistered).toHaveBeenCalledTimes(1);
    expect(session.fetchAndRender).toHaveBeenCalledTimes(1);
    expect(session.dispatchStaleCacheMetricsOnce).toHaveBeenCalledTimes(1);
    expect(session.renderStored).not.toHaveBeenCalled();
  });

  it('Given a fetch in flight and no amount/e-mail change, When executed, Then it debounces without fetching', async () => {
    const session = buildSession({ isFetching: jest.fn(() => true) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.setCurrentAmount).toHaveBeenCalledTimes(1);
    expect(session.resetFlow).not.toHaveBeenCalled();
    expect(session.ensureEmailListenerRegistered).not.toHaveBeenCalled();
    expect(session.fetchAndRender).not.toHaveBeenCalled();
    expect(session.dispatchStaleCacheMetricsOnce).not.toHaveBeenCalled();
  });

  it('Given a fetch in flight but the amount changed, When executed, Then it proceeds past the debounce guard', async () => {
    const session = buildSession({ isFetching: jest.fn(() => true), amountHasChanged: jest.fn(() => true) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.resetFlow).toHaveBeenCalledTimes(1);
    expect(metrics.resetOnAmountChange).toHaveBeenCalledTimes(1);
    expect(session.fetchAndRender).toHaveBeenCalledTimes(1);
  });

  it('Given the amount changed, When executed, Then it resets the flow and reports the metric before fetching', async () => {
    const session = buildSession({ amountHasChanged: jest.fn(() => true) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.resetFlow).toHaveBeenCalledTimes(1);
    expect(metrics.resetOnAmountChange).toHaveBeenCalledTimes(1);
    expect(session.fetchAndRender).toHaveBeenCalledTimes(1);
  });

  it('Given methods already loaded, When executed, Then it re-renders the stored methods and short-circuits', async () => {
    const session = buildSession({ isMethodsLoaded: jest.fn(() => true) });
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.renderStored).toHaveBeenCalledWith('formatted:100.00');
    expect(session.ensureEmailListenerRegistered).not.toHaveBeenCalled();
    expect(session.fetchAndRender).not.toHaveBeenCalled();
    expect(session.dispatchStaleCacheMetricsOnce).not.toHaveBeenCalled();
  });

  it('Given no amount change, When executed, Then it neither resets nor reports the amount-change metric', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.resetFlow).not.toHaveBeenCalled();
    expect(metrics.resetOnAmountChange).not.toHaveBeenCalled();
  });
});

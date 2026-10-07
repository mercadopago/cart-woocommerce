const { LoadSuperToken } = require('@super-token/useCases/LoadSuperToken');

const buildSession = (overrides = {}) => ({
  formatAmount: jest.fn((amount) => amount),
  setCurrentAmount: jest.fn(),
  currentAmount: jest.fn(() => '100.00'),
  isFetching: jest.fn(() => false),
  amountHasChanged: jest.fn(() => false),
  emailHasChanged: jest.fn(() => false),
  cancelInvalidAmount: jest.fn(),
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
  invalidAmount: jest.fn(),
  ...overrides,
});

const run = (session, metrics, currentAmount = '100.00') =>
  new LoadSuperToken().execute({ session, metrics, currentAmount });

describe('LoadSuperToken', () => {
  it('Given a normalized MLC integer amount, When loaded, Then it fetches payment methods', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics, '1234');

    expect(session.setCurrentAmount).toHaveBeenCalledWith('1234');
    expect(session.fetchAndRender).toHaveBeenCalledTimes(1);
    expect(metrics.invalidAmount).not.toHaveBeenCalled();
  });

  it('Given a fresh load, When executed, Then it formats+stores the amount, registers the listener, fetches+renders, and dispatches stale metrics', async () => {
    const session = buildSession();
    const metrics = buildMetrics();

    await run(session, metrics);

    expect(session.formatAmount).toHaveBeenCalledWith('100.00');
    expect(session.setCurrentAmount).toHaveBeenCalledWith('100.00');
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

    expect(session.renderStored).toHaveBeenCalledWith('100.00');
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

  it.each([
    [null, null],
    ['', null],
    ['NaN', null],
    ['abc', null],
    ['Infinity', 'Infinity'],
    ['1e309', '1e309'],
  ])('Given %p formats to %p, When loaded, Then it cancels without fetching', async (input, formatted) => {
    const session = buildSession({ formatAmount: jest.fn(() => formatted), isFetching: jest.fn(() => true) });
    const metrics = buildMetrics();

    await run(session, metrics, input);

    expect(session.setCurrentAmount).toHaveBeenCalledWith(formatted);
    expect(session.cancelInvalidAmount).toHaveBeenCalledTimes(1);
    expect(metrics.invalidAmount).toHaveBeenCalledTimes(1);
    expect(session.fetchAndRender).not.toHaveBeenCalled();
    expect(session.renderStored).not.toHaveBeenCalled();
  });

  it('Given an invalid load, When a valid amount arrives, Then it fetches normally', async () => {
    const session = buildSession({ formatAmount: jest.fn().mockReturnValueOnce(null).mockReturnValueOnce('10.50') });
    const metrics = buildMetrics();

    await run(session, metrics, null);
    await run(session, metrics, '10.50');

    expect(session.cancelInvalidAmount).toHaveBeenCalledTimes(1);
    expect(metrics.invalidAmount).toHaveBeenCalledTimes(1);
    expect(session.fetchAndRender).toHaveBeenCalledTimes(1);
    expect(session.setCurrentAmount).toHaveBeenLastCalledWith('10.50');
  });
});

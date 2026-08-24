const mockWatcher = { start: jest.fn(), recoverIfSdkIsNowAvailable: jest.fn() };
const mockChecker = { check: jest.fn() };
const MockSdkReadinessWatcher = jest.fn(() => mockWatcher);
const MockInitializationHealthChecker = jest.fn(() => mockChecker);
// Single source of truth for the event name: the production constant is provided by the mocked
// platform barrel, so the mock and the test share this one value (mock-prefixed for jest hoisting).
const mockCardFormMountedEvent = 'mp_card_form_mounted';

jest.mock('@super-token/adapters/platform', () => ({
  CoreMonitorMetricsAdapter: jest.fn(),
  SdkReadinessWatcher: MockSdkReadinessWatcher,
  InitializationHealthChecker: MockInitializationHealthChecker,
  CARD_FORM_MOUNTED_EVENT: mockCardFormMountedEvent,
}));

const { startInitializationResilience } = require('@super-token/composition/initializationResilience');

describe('startInitializationResilience', () => {
  let metrics;
  let recompose;
  // The module registers a document-level listener on every call; jsdom's document persists across
  // tests in a file, so track and remove each test's listeners to keep the tests isolated.
  let addedListeners;

  beforeEach(() => {
    jest.clearAllMocks();
    metrics = { sendMetric: jest.fn() };
    recompose = { current: jest.fn() };
    addedListeners = [];
    const realAdd = document.addEventListener.bind(document);
    jest.spyOn(document, 'addEventListener').mockImplementation((type, cb, opts) => {
      addedListeners.push([type, cb, opts]);
      realAdd(type, cb, opts);
    });
  });

  afterEach(() => {
    addedListeners.forEach(([type, cb, opts]) => document.removeEventListener(type, cb, opts));
    document.addEventListener.mockRestore();
  });

  it('Given it starts, When wired, Then the watcher and checker are built with the metrics adapter', () => {
    startInitializationResilience(recompose, metrics);
    expect(MockSdkReadinessWatcher).toHaveBeenCalledWith({ metrics });
    expect(MockInitializationHealthChecker).toHaveBeenCalledWith(
      expect.objectContaining({ metrics, getInstances: expect.any(Function) }),
    );
    expect(mockWatcher.start).toHaveBeenCalledTimes(1);
  });

  it('Given the card form mounts, When the event fires, Then recovery runs before the health check', () => {
    startInitializationResilience(recompose, metrics);
    document.dispatchEvent(new Event(mockCardFormMountedEvent));

    expect(mockWatcher.recoverIfSdkIsNowAvailable).toHaveBeenCalledTimes(1);
    expect(mockChecker.check).toHaveBeenCalledWith(mockCardFormMountedEvent);
    const recoverOrder = mockWatcher.recoverIfSdkIsNowAvailable.mock.invocationCallOrder[0];
    const checkOrder = mockChecker.check.mock.invocationCallOrder[0];
    expect(recoverOrder).toBeLessThan(checkOrder);
  });

  it('Given the watcher recovery callback runs, When it succeeds, Then it recomposes and does not report a failure', () => {
    startInitializationResilience(recompose, metrics);
    const recoveryCallback = mockWatcher.start.mock.calls[0][0];

    recoveryCallback();

    expect(recompose.current).toHaveBeenCalledTimes(1);
    expect(metrics.sendMetric).not.toHaveBeenCalled();
  });

  it('Given recomposition throws, When the recovery callback runs, Then the failure is reported and not rethrown', () => {
    recompose.current = jest.fn(() => { throw new Error('compose boom'); });
    startInitializationResilience(recompose, metrics);
    const recoveryCallback = mockWatcher.start.mock.calls[0][0];

    expect(() => recoveryCallback()).not.toThrow();
    expect(metrics.sendMetric).toHaveBeenCalledWith(
      'super_token_recovery_compose_failed',
      'mp_super_token_init',
      'compose boom',
    );
  });
});

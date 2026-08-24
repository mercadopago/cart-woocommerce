const {
  LegacyLoadOrchestrationSession,
  createLoadSuperTokenMetrics,
} = require('@super-token/adapters/session/LegacyLoadOrchestrationSession');

const STORED = [{ token: 'tok-1', type: 'credit_card' }];

const buildTriggerHandler = (overrides = {}) => ({
  currentAmount: 'formatted:100.00',
  isFetchingPaymentMethods: true,
  amountHasChanged: jest.fn(() => false),
  emailHasChanged: jest.fn(() => true),
  resetFlow: jest.fn(),
  isSuperTokenPaymentMethodsLoaded: jest.fn(() => true),
  ensureEmailListenerRegistered: jest.fn(),
  fetchAndRenderSuperTokenPaymentMethods: jest.fn(async () => {}),
  dispatchStaleCacheMetricsOnce: jest.fn(),
  mpSuperTokenAuthenticator: { formatAmount: jest.fn((amount) => `formatted:${amount}`) },
  mpSuperTokenPaymentMethods: {
    getStoredPaymentMethods: jest.fn(() => STORED),
    renderAccountPaymentMethods: jest.fn(),
  },
  ...overrides,
});

describe('LegacyLoadOrchestrationSession', () => {
  it('Given an amount, When formatting, Then it forwards to the legacy authenticator', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    expect(session.formatAmount('100.00')).toBe('formatted:100.00');
    expect(triggerHandler.mpSuperTokenAuthenticator.formatAmount).toHaveBeenCalledWith('100.00');
  });

  it('Given the current amount, When set and read, Then it writes/reads the legacy state', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    session.setCurrentAmount('formatted:200.00');
    expect(triggerHandler.currentAmount).toBe('formatted:200.00');
    expect(session.currentAmount()).toBe('formatted:200.00');
  });

  it('Given the load guards, When read, Then each forwards to the legacy trigger handler', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    expect(session.isFetching()).toBe(true);
    expect(session.amountHasChanged()).toBe(false);
    expect(session.emailHasChanged()).toBe(true);
    expect(session.isMethodsLoaded()).toBe(true);
    expect(triggerHandler.amountHasChanged).toHaveBeenCalled();
    expect(triggerHandler.emailHasChanged).toHaveBeenCalled();
    expect(triggerHandler.isSuperTokenPaymentMethodsLoaded).toHaveBeenCalled();
  });

  it('Given the reset primitive, When called, Then it forwards to the legacy resetFlow', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    session.resetFlow();
    expect(triggerHandler.resetFlow).toHaveBeenCalledTimes(1);
  });

  it('Given the cache short-circuit, When rendering stored, Then it renders the stored methods at the given amount', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    session.renderStored('formatted:100.00');

    expect(triggerHandler.mpSuperTokenPaymentMethods.getStoredPaymentMethods).toHaveBeenCalledTimes(1);
    expect(triggerHandler.mpSuperTokenPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(
      STORED,
      'formatted:100.00',
    );
  });

  it('Given the forwarded legacy methods, When called, Then each reaches the legacy trigger handler', async () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyLoadOrchestrationSession(triggerHandler);

    session.ensureEmailListenerRegistered();
    await session.fetchAndRender();
    session.dispatchStaleCacheMetricsOnce();

    expect(triggerHandler.ensureEmailListenerRegistered).toHaveBeenCalledTimes(1);
    expect(triggerHandler.fetchAndRenderSuperTokenPaymentMethods).toHaveBeenCalledTimes(1);
    expect(triggerHandler.dispatchStaleCacheMetricsOnce).toHaveBeenCalledTimes(1);
  });
});

describe('createLoadSuperTokenMetrics', () => {
  it('Given the legacy metrics, When the amount-change reset reports, Then it maps to the legacy sendMetric name', () => {
    const legacy = { sendMetric: jest.fn() };
    const metrics = createLoadSuperTokenMetrics(legacy);

    metrics.resetOnAmountChange();

    expect(legacy.sendMetric).toHaveBeenCalledWith('super_token_reset_on_amount_change', 'true', '');
  });
});

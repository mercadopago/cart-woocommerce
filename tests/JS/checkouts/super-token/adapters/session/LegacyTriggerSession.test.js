const {
  LegacyTriggerSession,
  createFetchAndRenderMetrics,
} = require('@super-token/adapters/session/LegacyTriggerSession');

const METHODS = [{ token: 'tok-1', type: 'credit_card' }];

const buildTriggerHandler = (overrides = {}) => ({
  currentAmount: '100.00',
  isFetchingPaymentMethods: false,
  loadGeneration: 3,
  getBuyerEmail: jest.fn(() => 'buyer@test.com'),
  wcEmailListener: { isValid: jest.fn(() => true) },
  mpSuperTokenAuthenticator: { getAccountPaymentMethods: jest.fn(async () => METHODS) },
  mpSuperTokenPaymentMethods: { renderAccountPaymentMethods: jest.fn(), reset: jest.fn() },
  ...overrides,
});

describe('LegacyTriggerSession', () => {
  it('Given the buyer e-mail, When read, Then it forwards to the legacy trigger handler', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    expect(session.getBuyerEmail()).toBe('buyer@test.com');
    expect(triggerHandler.getBuyerEmail).toHaveBeenCalledTimes(1);
  });

  it('Given an e-mail, When validating, Then it forwards to the legacy wcEmailListener', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    expect(session.isValidEmail('buyer@test.com')).toBe(true);
    expect(triggerHandler.wcEmailListener.isValid).toHaveBeenCalledWith('buyer@test.com');
  });

  it('Given the fetching flag, When set, Then it mutates the legacy trigger handler state', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    session.setFetching(true);
    expect(triggerHandler.isFetchingPaymentMethods).toBe(true);
    session.setFetching(false);
    expect(triggerHandler.isFetchingPaymentMethods).toBe(false);
  });

  it('Given the load generation and current amount, When read, Then it reflects the live legacy state', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    expect(session.getLoadGeneration()).toBe(3);
    expect(session.currentAmount()).toBe('100.00');

    triggerHandler.loadGeneration = 4;
    triggerHandler.currentAmount = '200.00';
    expect(session.getLoadGeneration()).toBe(4);
    expect(session.currentAmount()).toBe('200.00');
  });

  it('Given an amount and e-mail, When fetching, Then it forwards to the legacy authenticator (the load seam)', async () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    const result = await session.fetchAccountPaymentMethods('100.00', 'buyer@test.com');

    expect(triggerHandler.mpSuperTokenAuthenticator.getAccountPaymentMethods).toHaveBeenCalledWith(
      '100.00',
      'buyer@test.com',
    );
    expect(result).toBe(METHODS);
  });

  it('Given the methods and amount, When rendering, Then it forwards to the legacy controller', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    session.renderAccountPaymentMethods(METHODS, '100.00');

    expect(triggerHandler.mpSuperTokenPaymentMethods.renderAccountPaymentMethods).toHaveBeenCalledWith(
      METHODS,
      '100.00',
    );
  });

  it('Given the cancel path, When bumping the load generation, Then it increments the legacy state so an awaiting fetch drops its stale result', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    session.bumpLoadGeneration();

    expect(triggerHandler.loadGeneration).toBe(4);
  });

  it('Given the cancel path, When resetting the payment methods, Then it forwards to the legacy controller', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyTriggerSession(triggerHandler);

    session.resetPaymentMethods();

    expect(triggerHandler.mpSuperTokenPaymentMethods.reset).toHaveBeenCalledTimes(1);
  });
});

describe('createFetchAndRenderMetrics', () => {
  it('Given the legacy metrics, When the e-mail gate reports, Then it maps each intention to the legacy sendMetric names', () => {
    const legacy = { sendMetric: jest.fn() };
    const metrics = createFetchAndRenderMetrics(legacy);

    metrics.skippedNoEmail();
    metrics.skippedInvalidEmail();
    metrics.emailCaptured();

    expect(legacy.sendMetric).toHaveBeenNthCalledWith(1, 'super_token_skipped_no_email', 'true', '');
    expect(legacy.sendMetric).toHaveBeenNthCalledWith(2, 'super_token_skipped_invalid_email', 'true', '');
    expect(legacy.sendMetric).toHaveBeenNthCalledWith(3, 'super_token_email_captured', 'true', '');
  });
});

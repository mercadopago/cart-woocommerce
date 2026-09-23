const {
  LegacyEmailListenerSession,
} = require('@super-token/adapters/session/LegacyEmailListenerSession');

const buildEmailListener = (overrides = {}) => ({
  onEmailChange: jest.fn(),
  setupEmailChangeHandlers: jest.fn(),
  ...overrides,
});

const buildMetrics = () => ({ sendMetric: jest.fn() });

const buildTriggerHandler = (overrides = {}) => ({
  isAlreadyListeningForm: false,
  currentAmount: '100.00',
  wcBuyerEmail: 'existing@test.com',
  isDifferentEmail: jest.fn(() => true),
  resetCustomCheckout: jest.fn(),
  wcEmailListener: buildEmailListener(),
  mpSuperTokenMetrics: buildMetrics(),
  ...overrides,
});

describe('LegacyEmailListenerSession', () => {
  it('Given the listener is already registered, When queried, Then it reports true', () => {
    const triggerHandler = buildTriggerHandler({ isAlreadyListeningForm: true });
    const session = new LegacyEmailListenerSession(triggerHandler);

    expect(session.isListening()).toBe(true);
  });

  it('Given the trigger handler currentAmount, When read, Then it returns the current amount', () => {
    const session = new LegacyEmailListenerSession(buildTriggerHandler({ currentAmount: '250.00' }));

    expect(session.currentAmount()).toBe('250.00');
  });

  it('Given the trigger handler wcBuyerEmail, When checked, Then isBuyerEmailKnown returns true for non-null and false for null', () => {
    const knownSession = new LegacyEmailListenerSession(
      buildTriggerHandler({ wcBuyerEmail: 'buyer@test.com' }),
    );
    const unknownSession = new LegacyEmailListenerSession(
      buildTriggerHandler({ wcBuyerEmail: null }),
    );

    expect(knownSession.isBuyerEmailKnown()).toBe(true);
    expect(unknownSession.isBuyerEmailKnown()).toBe(false);
  });

  it('Given a new e-mail, When set, Then it writes to the trigger handler wcBuyerEmail field', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyEmailListenerSession(triggerHandler);

    session.setBuyerEmail('new@test.com');

    expect(triggerHandler.wcBuyerEmail).toBe('new@test.com');
  });

  it('When checking isDifferentEmail, Then it forwards to the trigger handler method', () => {
    const isDifferentEmail = jest.fn(() => false);
    const session = new LegacyEmailListenerSession(buildTriggerHandler({ isDifferentEmail }));

    const result = session.isDifferentEmail('other@test.com');

    expect(isDifferentEmail).toHaveBeenCalledWith('other@test.com');
    expect(result).toBe(false);
  });

  it('When reporting the e-mail change metric, Then it calls sendMetric with the correct name and fixed value', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyEmailListenerSession(triggerHandler);

    session.reportEmailChangeMetric();

    expect(triggerHandler.mpSuperTokenMetrics.sendMetric).toHaveBeenCalledWith(
      'super_token_reset_on_email_change',
      'true',
      '',
    );
  });

  it('When triggering a reset, Then it forwards to resetCustomCheckout on the trigger handler', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyEmailListenerSession(triggerHandler);

    session.triggerReset();

    expect(triggerHandler.resetCustomCheckout).toHaveBeenCalledTimes(1);
  });

  it('When registering the callback, Then it forwards to wcEmailListener.onEmailChange', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyEmailListenerSession(triggerHandler);
    const callback = jest.fn();

    session.registerEmailChangeCallback(callback);

    expect(triggerHandler.wcEmailListener.onEmailChange).toHaveBeenCalledWith(callback);
  });

  it('When setting up handlers, Then it forwards to wcEmailListener.setupEmailChangeHandlers', () => {
    const triggerHandler = buildTriggerHandler();
    const session = new LegacyEmailListenerSession(triggerHandler);

    session.setupEmailChangeHandlers();

    expect(triggerHandler.wcEmailListener.setupEmailChangeHandlers).toHaveBeenCalledTimes(1);
  });

  it('When marking as listening, Then it sets isAlreadyListeningForm to true on the trigger handler', () => {
    const triggerHandler = buildTriggerHandler({ isAlreadyListeningForm: false });
    const session = new LegacyEmailListenerSession(triggerHandler);

    session.markAsListening();

    expect(triggerHandler.isAlreadyListeningForm).toBe(true);
  });
});

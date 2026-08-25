const {
  EnsureEmailListenerRegistered,
} = require('@super-token/useCases/EnsureEmailListenerRegistered');

const buildSession = (overrides = {}) => ({
  isListening: jest.fn(() => false),
  registerEmailChangeCallback: jest.fn(),
  currentAmount: jest.fn(() => '100.00'),
  isDifferentEmail: jest.fn(() => true),
  isBuyerEmailKnown: jest.fn(() => true),
  setBuyerEmail: jest.fn(),
  reportEmailChangeMetric: jest.fn(),
  triggerReset: jest.fn(),
  setupEmailChangeHandlers: jest.fn(),
  markAsListening: jest.fn(),
  ...overrides,
});

/** Captures the callback registered via registerEmailChangeCallback and calls it. */
const runCallback = async (session, email, isValid) => {
  const callback = session.registerEmailChangeCallback.mock.calls[0][0];
  await callback(email, isValid);
};

const run = (session) => new EnsureEmailListenerRegistered().execute({ session });

describe('EnsureEmailListenerRegistered', () => {
  it('Given the listener is already registered, When called again, Then it returns early without re-registering', () => {
    const session = buildSession({ isListening: jest.fn(() => true) });

    run(session);

    expect(session.registerEmailChangeCallback).not.toHaveBeenCalled();
    expect(session.markAsListening).not.toHaveBeenCalled();
  });

  it('Given the listener is not registered, When called, Then it registers the callback, sets up handlers and marks as listening in order', () => {
    const calls = [];
    const session = buildSession({
      registerEmailChangeCallback: jest.fn(() => calls.push('register')),
      setupEmailChangeHandlers: jest.fn(() => calls.push('setup')),
      markAsListening: jest.fn(() => calls.push('mark')),
    });

    run(session);

    expect(calls).toEqual(['register', 'setup', 'mark']);
  });

  it('Given an invalid e-mail, When the callback fires, Then it does not trigger a reset', async () => {
    const session = buildSession();
    run(session);

    await runCallback(session, 'buyer@test.com', false);

    expect(session.triggerReset).not.toHaveBeenCalled();
  });

  it('Given a valid e-mail but no current amount, When the callback fires, Then it does not trigger a reset', async () => {
    const session = buildSession({ currentAmount: jest.fn(() => null) });
    run(session);

    await runCallback(session, 'buyer@test.com', true);

    expect(session.triggerReset).not.toHaveBeenCalled();
  });

  it('Given the same e-mail (not different), When the callback fires, Then it does not trigger a reset', async () => {
    const session = buildSession({ isDifferentEmail: jest.fn(() => false) });
    run(session);

    await runCallback(session, 'same@test.com', true);

    expect(session.triggerReset).not.toHaveBeenCalled();
  });

  it('Given the buyer e-mail is unknown (null), When the callback fires, Then it does not trigger a reset', async () => {
    const session = buildSession({ isBuyerEmailKnown: jest.fn(() => false) });
    run(session);

    await runCallback(session, 'buyer@test.com', true);

    expect(session.triggerReset).not.toHaveBeenCalled();
  });

  it('Given a valid different e-mail with a known baseline, When the callback fires, Then it updates the e-mail, reports the metric and triggers a reset', async () => {
    const calls = [];
    const session = buildSession({
      setBuyerEmail: jest.fn(() => calls.push('setEmail')),
      reportEmailChangeMetric: jest.fn(() => calls.push('metric')),
      triggerReset: jest.fn(() => calls.push('reset')),
    });
    run(session);

    await runCallback(session, 'new@test.com', true);

    expect(session.setBuyerEmail).toHaveBeenCalledWith('new@test.com');
    expect(calls).toEqual(['setEmail', 'metric', 'reset']);
  });
});

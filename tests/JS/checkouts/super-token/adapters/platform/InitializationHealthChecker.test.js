const {
  InitializationHealthChecker,
  INIT_CHECK_SESSION_KEY,
} = require('@super-token/adapters/platform/InitializationHealthChecker');

function buildMetrics() {
  return {
    superTokenInitializationSuccess: jest.fn(),
    superTokenInitializationError: jest.fn(),
    superTokenClassesNotExist: jest.fn(),
    superTokenTriggerHandlerNotListening: jest.fn(),
    mpSdkInstanceNotExists: jest.fn(),
  };
}

function buildSession(initial = {}) {
  const store = { ...initial };
  return {
    getItem: jest.fn((key) => (key in store ? store[key] : null)),
    setItem: jest.fn((key, value) => { store[key] = String(value); }),
  };
}

function buildInstances(overrides = {}) {
  return {
    triggerHandler: { isAlreadyListeningForm: true },
    authenticator: {},
    paymentMethods: {},
    metrics: {},
    errorHandler: {},
    ...overrides,
  };
}

function buildChecker({ instances = buildInstances(), sdkPresent = true, session } = {}) {
  const metrics = buildMetrics();
  const s = session ?? buildSession();
  const checker = new InitializationHealthChecker({
    metrics,
    getInstances: () => instances,
    readSdkInstance: () => (sdkPresent ? {} : null),
    session: s,
  });
  return { checker, metrics, session: s };
}

describe('InitializationHealthChecker', () => {
  describe('registerFormMountedCheck — wires check() to the card-form-mounted event', () => {
    afterEach(() => jest.restoreAllMocks());

    it('Given the listener is registered, When mp_card_form_mounted fires, Then it calls check with that origin', () => {
      const { checker, metrics } = buildChecker();
      let handler;
      jest.spyOn(document, 'addEventListener').mockImplementation((type, fn) => {
        if (type === 'mp_card_form_mounted') handler = fn;
      });

      checker.registerFormMountedCheck();
      handler();

      expect(metrics.superTokenInitializationSuccess).toHaveBeenCalledWith('mp_card_form_mounted');
    });
  });

  describe('check — post-mount health validation', () => {
    it('Given the session flag is already set, When checked, Then it returns without reporting any metric', () => {
      const session = buildSession({ [INIT_CHECK_SESSION_KEY]: 'true' });
      const { checker, metrics } = buildChecker({ session });

      checker.check('mp_card_form_mounted');

      expect(metrics.superTokenInitializationSuccess).not.toHaveBeenCalled();
      expect(session.setItem).not.toHaveBeenCalled();
    });

    it('Given the SDK instance is missing, When checked, Then it reports mpSdkInstanceNotExists but does NOT set the dedup flag (non-conclusive)', () => {
      const session = buildSession();
      const { checker, metrics } = buildChecker({ sdkPresent: false, session });

      checker.check('mp_card_form_mounted');

      expect(metrics.mpSdkInstanceNotExists).toHaveBeenCalledWith('mp_card_form_mounted');
      expect(session.setItem).not.toHaveBeenCalled();
    });

    it('Given SDK was missing on the first check but available on the second, When checked twice, Then it reports success on the second call', () => {
      const session = buildSession();
      let sdkPresent = false;
      const checker = new InitializationHealthChecker({
        metrics: buildMetrics(),
        getInstances: () => buildInstances(),
        readSdkInstance: () => (sdkPresent ? {} : null),
        session,
      });

      checker.check('mp_card_form_mounted');
      expect(checker['metrics'].mpSdkInstanceNotExists).toHaveBeenCalledTimes(1);
      expect(session.setItem).not.toHaveBeenCalled();

      sdkPresent = true;
      checker.check('mp_card_form_mounted');
      expect(checker['metrics'].superTokenInitializationSuccess).toHaveBeenCalledTimes(1);
      expect(session.setItem).toHaveBeenCalledWith(INIT_CHECK_SESSION_KEY, 'true');
    });

    it('Given an empty dispatchedFrom, When checked, Then the origin falls back to "unknown"', () => {
      const { checker, metrics } = buildChecker({ sdkPresent: false });

      checker.check('');

      expect(metrics.mpSdkInstanceNotExists).toHaveBeenCalledWith('unknown');
    });

    it('Given the authenticator instance is missing, When checked, Then it reports superTokenClassesNotExist naming only the missing class', () => {
      const { checker, metrics } = buildChecker({
        instances: buildInstances({ authenticator: null }),
      });

      checker.check('mp_card_form_mounted');

      const [summary] = metrics.superTokenClassesNotExist.mock.calls[0];
      expect(summary).toContain('Authenticator class did not load.');
      expect(summary).not.toContain('Metrics class did not load.');
      expect(summary).not.toContain('Payment Methods class did not load.');
    });

    it('Given no instances were composed, When checked, Then the summary names every missing class', () => {
      const checker = new InitializationHealthChecker({
        metrics: buildMetrics(),
        getInstances: () => null,
        readSdkInstance: () => ({}),
        session: buildSession(),
      });

      checker.check('mp_card_form_mounted');

      const [summary] = checker['metrics'].superTokenClassesNotExist.mock.calls[0];
      expect(summary).toContain('Metrics class did not load.');
      expect(summary).toContain('Payment Methods class did not load.');
      expect(summary).toContain('Authenticator class did not load.');
      expect(summary).toContain('Error Handler class did not load.');
      expect(summary).toContain('Trigger Handler class did not load.');
    });

    it('Given the trigger handler is present but not listening, When checked, Then it reports superTokenTriggerHandlerNotListening and sets the dedup flag (conclusive)', () => {
      const session = buildSession();
      const { checker, metrics } = buildChecker({
        instances: buildInstances({ triggerHandler: { isAlreadyListeningForm: false } }),
        session,
      });

      checker.check('mp_card_form_mounted');

      expect(metrics.superTokenTriggerHandlerNotListening).toHaveBeenCalledWith('mp_card_form_mounted');
      expect(session.setItem).toHaveBeenCalledWith(INIT_CHECK_SESSION_KEY, 'true');
    });

    it('Given everything is correctly initialized, When checked, Then it reports superTokenInitializationSuccess and sets the flag', () => {
      const session = buildSession();
      const { checker, metrics } = buildChecker({ session });

      checker.check('mp_card_form_mounted');

      expect(metrics.superTokenInitializationSuccess).toHaveBeenCalledWith('mp_card_form_mounted');
      expect(session.setItem).toHaveBeenCalledWith(INIT_CHECK_SESSION_KEY, 'true');
    });

    it('Given getInstances throws an unexpected error, When checked, Then it reports superTokenInitializationError and still sets the flag', () => {
      const session = buildSession();
      const boom = new Error('unexpected');
      const checker = new InitializationHealthChecker({
        metrics: buildMetrics(),
        getInstances: () => { throw boom; },
        readSdkInstance: () => ({}),
        session,
      });

      checker.check('mp_card_form_mounted');

      expect(checker['metrics'].superTokenInitializationError).toHaveBeenCalledWith(boom, 'mp_card_form_mounted');
      expect(session.setItem).toHaveBeenCalledWith(INIT_CHECK_SESSION_KEY, 'true');
    });

    it('Given a successful first check, When checked a second time, Then it does not report again (session dedup)', () => {
      const { checker, metrics } = buildChecker();

      checker.check('mp_card_form_mounted');
      checker.check('mp_card_form_mounted');

      expect(metrics.superTokenInitializationSuccess).toHaveBeenCalledTimes(1);
    });
  });
});

const {
  SdkReadinessWatcher,
  INIT_SOURCE,
  FALLBACK_POLL_INTERVAL_MS,
  FALLBACK_POLL_MAX_WAIT_MS,
  MP_SDK_INSTANCE_READY_EVENT,
} = require('@super-token/adapters/platform/SdkReadinessWatcher');

function buildMetrics() {
  return {
    superTokenSdkLoaded: jest.fn(),
    reportInitSource: jest.fn(),
  };
}

function buildWatcher({ sdkPresent = false, clock = 0, metrics } = {}) {
  let sdkValue = sdkPresent;
  const currentClock = { value: clock };
  const m = metrics ?? buildMetrics();

  const watcher = new SdkReadinessWatcher({
    metrics: m,
    readSdkInstance: () => sdkValue,
    now: () => currentClock.value,
  });

  return {
    watcher,
    metrics: m,
    setSdkPresent: (v) => { sdkValue = v; },
    advanceClock: (ms) => { currentClock.value += ms; },
  };
}

describe('SdkReadinessWatcher', () => {
  let sdkEventListeners;

  beforeEach(() => {
    jest.useFakeTimers();
    sdkEventListeners = {};
    jest.spyOn(document, 'addEventListener').mockImplementation((type, handler, options) => {
      sdkEventListeners[type] = { handler, options };
    });
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  describe('start — three-tier SDK availability watch', () => {
    it('Given the SDK is already present at start, When started, Then it composes immediately with source already_ready', () => {
      const { watcher, metrics } = buildWatcher({ sdkPresent: true });
      const compose = jest.fn();

      watcher.start(compose);

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.superTokenSdkLoaded).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.ALREADY_READY, 0);
    });

    it('Given the SDK is already present, When started, Then it does not register a DOM event or poll', () => {
      const { watcher } = buildWatcher({ sdkPresent: true });
      watcher.start(jest.fn());

      expect(sdkEventListeners[MP_SDK_INSTANCE_READY_EVENT]).toBeUndefined();
    });

    it('Given the SDK is absent, When the mp_sdk_instance_ready event fires within 15s, Then it composes with source sdk_event', () => {
      const { watcher, metrics, setSdkPresent, advanceClock } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      advanceClock(500);
      setSdkPresent(true);
      sdkEventListeners[MP_SDK_INSTANCE_READY_EVENT].handler();

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.SDK_INSTANCE_EVENT, 500);
    });

    it('Given the SDK event fires after the 15s window, Then the source is reported as sdk_event_recovered', () => {
      const { watcher, metrics, setSdkPresent, advanceClock } = buildWatcher();

      watcher.start(jest.fn());
      advanceClock(FALLBACK_POLL_MAX_WAIT_MS + 1);
      setSdkPresent(true);
      sdkEventListeners[MP_SDK_INSTANCE_READY_EVENT].handler();

      expect(metrics.reportInitSource).toHaveBeenCalledWith(
        INIT_SOURCE.SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW,
        FALLBACK_POLL_MAX_WAIT_MS + 1,
      );
    });

    it('Given the SDK appears after some ticks, When the fallback poll fires, Then it composes with source fallback_poll', () => {
      const { watcher, metrics, setSdkPresent, advanceClock } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      advanceClock(1200);
      setSdkPresent(true);
      jest.advanceTimersByTime(FALLBACK_POLL_INTERVAL_MS);

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.FALLBACK_POLL, 1200);
    });

    it('Given the SDK never appears, When 15s elapse, Then the poll is cleared and nothing is composed', () => {
      const { watcher, metrics } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);

      expect(compose).not.toHaveBeenCalled();
      expect(metrics.superTokenSdkLoaded).not.toHaveBeenCalled();
    });

    it('Given both the event and the poll would fire, When they race, Then it composes exactly once', () => {
      const { watcher, metrics, setSdkPresent } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      setSdkPresent(true);
      sdkEventListeners[MP_SDK_INSTANCE_READY_EVENT].handler();
      jest.advanceTimersByTime(FALLBACK_POLL_INTERVAL_MS);

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.superTokenSdkLoaded).toHaveBeenCalledTimes(1);
    });
  });

  describe('registerFormMountedRecovery — wires recoverIfSdkIsNowAvailable to the card-form-mounted event', () => {
    it('Given the form mounts after the poll expired and the SDK is now available, When the listener fires, Then it composes with source card_form_recovery', () => {
      const { watcher, metrics, setSdkPresent, advanceClock } = buildWatcher();
      const compose = jest.fn();
      let formMountedHandler;
      document.addEventListener.mockImplementation((type, handler) => {
        if (type === 'mp_card_form_mounted') formMountedHandler = handler;
        else sdkEventListeners[type] = { handler };
      });

      watcher.start(compose);
      watcher.registerFormMountedRecovery();
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);
      advanceClock(FALLBACK_POLL_MAX_WAIT_MS);
      setSdkPresent(true);
      formMountedHandler();

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.CARD_FORM_RECOVERY, FALLBACK_POLL_MAX_WAIT_MS);
    });

    it('Given the form mounts but the SDK is still unavailable, When the listener fires, Then nothing is composed', () => {
      const { watcher, metrics } = buildWatcher();
      let formMountedHandler;
      document.addEventListener.mockImplementation((type, handler) => {
        if (type === 'mp_card_form_mounted') formMountedHandler = handler;
        else sdkEventListeners[type] = { handler };
      });

      watcher.start(jest.fn());
      watcher.registerFormMountedRecovery();
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);
      formMountedHandler();

      expect(metrics.superTokenSdkLoaded).not.toHaveBeenCalled();
    });
  });

  describe('start — idempotency guard prevents double-registration', () => {
    it('Given start was already called, When called a second time, Then it does not register a second listener or poll', () => {
      const { watcher } = buildWatcher();
      const compose1 = jest.fn();
      const compose2 = jest.fn();

      watcher.start(compose1);
      watcher.start(compose2);

      const listenerCount = Object.keys(sdkEventListeners).length;
      expect(listenerCount).toBe(1);
      expect(compose2).not.toHaveBeenCalled();
    });
  });

  describe('composeWith — initialized guard moves after compose() to allow recovery on failure', () => {
    it('Given compose throws on the first attempt, When recoverIfSdkIsNowAvailable is called, Then it retries and completes successfully', () => {
      let callCount = 0;
      const compose = jest.fn().mockImplementation(() => {
        callCount++;
        if (callCount === 1) throw new Error('temporary failure');
      });
      const { watcher, metrics } = buildWatcher({ sdkPresent: true });

      try { watcher.start(compose); } catch (_) { /* first attempt throws */ }

      watcher.recoverIfSdkIsNowAvailable();

      expect(compose).toHaveBeenCalledTimes(2);
      expect(metrics.superTokenSdkLoaded).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.CARD_FORM_RECOVERY, 0);
    });
  });

  describe('recoverIfSdkIsNowAvailable — second chance after the poll window', () => {
    it('Given the SDK is now available after the poll expired, When recovery is attempted, Then it composes with source card_form_recovery', () => {
      const { watcher, metrics, setSdkPresent, advanceClock } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);
      advanceClock(FALLBACK_POLL_MAX_WAIT_MS);
      setSdkPresent(true);
      watcher.recoverIfSdkIsNowAvailable();

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.reportInitSource).toHaveBeenCalledWith(INIT_SOURCE.CARD_FORM_RECOVERY, FALLBACK_POLL_MAX_WAIT_MS);
    });

    it('Given the SDK is still unavailable during recovery, When recovery is attempted, Then nothing is composed', () => {
      const { watcher, metrics } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);
      watcher.recoverIfSdkIsNowAvailable();

      expect(compose).not.toHaveBeenCalled();
      expect(metrics.superTokenSdkLoaded).not.toHaveBeenCalled();
    });

    it('Given the watcher already initialized, When recovery is attempted, Then it does not compose again', () => {
      const { watcher, metrics, setSdkPresent } = buildWatcher({ sdkPresent: true });
      const compose = jest.fn();

      watcher.start(compose);
      expect(compose).toHaveBeenCalledTimes(1);

      setSdkPresent(true);
      watcher.recoverIfSdkIsNowAvailable();

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.superTokenSdkLoaded).toHaveBeenCalledTimes(1);
    });

    it('Given recovery composed successfully, When recovery is attempted again, Then it does not compose twice', () => {
      const { watcher, metrics, setSdkPresent } = buildWatcher();
      const compose = jest.fn();

      watcher.start(compose);
      jest.advanceTimersByTime(FALLBACK_POLL_MAX_WAIT_MS);
      setSdkPresent(true);
      watcher.recoverIfSdkIsNowAvailable();
      watcher.recoverIfSdkIsNowAvailable();

      expect(compose).toHaveBeenCalledTimes(1);
      expect(metrics.superTokenSdkLoaded).toHaveBeenCalledTimes(1);
    });
  });
});

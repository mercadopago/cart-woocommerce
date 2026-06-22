/* globals MPDebounce, WCEmailListener, MPSuperTokenMetrics, MPSuperTokenAuthenticator, MPSuperTokenTriggerHandler, MPSuperTokenPaymentMethods, MPSuperTokenErrorHandler, sendMetric */
const SUPER_TOKEN_JS_VERSION = '1.2.0';
const MP_SDK_INSTANCE_READY_EVENT = 'mp_sdk_instance_ready';
const FALLBACK_POLL_INTERVAL_MS = 50;
const FALLBACK_POLL_MAX_WAIT_MS = 15000;

const INIT_SOURCE = {
  ALREADY_READY: 'already_ready',
  SDK_INSTANCE_EVENT: 'sdk_event',
  SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW: 'sdk_event_recovered',
  FALLBACK_POLL: 'fallback_poll',
};

const superTokenScriptStartedAt = Date.now();

function reportSuperTokenInitSource(mpSuperTokenMetrics, initSource) {
  const elapsedMsUntilSdkInstanceReady = Date.now() - superTokenScriptStartedAt;
  const sdkInstanceReadyAfterLegacyPollWindow =
    initSource === INIT_SOURCE.SDK_INSTANCE_EVENT
    && elapsedMsUntilSdkInstanceReady > FALLBACK_POLL_MAX_WAIT_MS;
  const reportedInitSource = sdkInstanceReadyAfterLegacyPollWindow
    ? INIT_SOURCE.SDK_INSTANCE_EVENT_AFTER_LEGACY_WINDOW
    : initSource;

  mpSuperTokenMetrics.sendMetric(
    'super_token_init_source',
    reportedInitSource,
    `elapsed_ms:${elapsedMsUntilSdkInstanceReady}`
  );
}

function buildSuperTokenClasses(initSource) {
  const superTokenAlreadyBuilt = Boolean(window.mpSuperTokenTriggerHandler);
  if (superTokenAlreadyBuilt || !window.mpSdkInstance) {
    return;
  }

  const mpSdkInstance = window.mpSdkInstance;
  const mpDebounce = new MPDebounce();
  const wcEmailListener = new WCEmailListener(mpDebounce);
  const mpSuperTokenMetrics = new MPSuperTokenMetrics(mpSdkInstance, SUPER_TOKEN_JS_VERSION);
  const mpSuperTokenPaymentMethods = new MPSuperTokenPaymentMethods(
    mpSdkInstance,
    mpSuperTokenMetrics,
  );
  const mpSuperTokenAuthenticator = new MPSuperTokenAuthenticator(
    mpSdkInstance,
    mpSuperTokenPaymentMethods,
    mpSuperTokenMetrics,
  );
  const mpSuperTokenErrorHandler = new MPSuperTokenErrorHandler(
    mpSuperTokenPaymentMethods,
    mpSuperTokenMetrics
  );

  window.mpSuperTokenMetrics = mpSuperTokenMetrics;
  window.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
  window.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
  window.mpSuperTokenErrorHandler = mpSuperTokenErrorHandler;
  window.mpSuperTokenTriggerHandler = new MPSuperTokenTriggerHandler(
    mpSuperTokenAuthenticator,
    wcEmailListener,
    mpSuperTokenPaymentMethods,
    mpSuperTokenErrorHandler,
    mpSuperTokenMetrics
  );

  mpSuperTokenMetrics.sendMetric('super_token_sdk_loaded', 'true', '');
  reportSuperTokenInitSource(mpSuperTokenMetrics, initSource);

  const customCheckoutHandlerMissing = !window.mpCustomCheckoutHandler;
  if (customCheckoutHandlerMissing && typeof sendMetric === 'function') {
    sendMetric('MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', 'mp_super_token_init', 'mp_super_token_init_error');
  }

  const eventHandlerAcceptsSuperTokenDependencies =
    window.mpEventHandler && typeof window.mpEventHandler.setSuperTokenDependencies === 'function';
  if (eventHandlerAcceptsSuperTokenDependencies) {
    window.mpEventHandler.setSuperTokenDependencies({
      triggerHandler: window.mpSuperTokenTriggerHandler,
      authenticator: window.mpSuperTokenAuthenticator,
      paymentMethods: window.mpSuperTokenPaymentMethods,
      metrics: window.mpSuperTokenMetrics,
      errorHandler: window.mpSuperTokenErrorHandler,
    });
  }
}

function initSuperTokenWhenSdkInstanceIsReady() {
  if (window.mpSdkInstance) {
    buildSuperTokenClasses(INIT_SOURCE.ALREADY_READY);
    return;
  }

  document.addEventListener(
    MP_SDK_INSTANCE_READY_EVENT,
    () => buildSuperTokenClasses(INIT_SOURCE.SDK_INSTANCE_EVENT),
    { once: true }
  );

  const fallbackPoll = setInterval(() => {
    if (window.mpSdkInstance) {
      clearInterval(fallbackPoll);
      buildSuperTokenClasses(INIT_SOURCE.FALLBACK_POLL);
    }
  }, FALLBACK_POLL_INTERVAL_MS);

  setTimeout(() => clearInterval(fallbackPoll), FALLBACK_POLL_MAX_WAIT_MS);
}

initSuperTokenWhenSdkInstanceIsReady();

async function checkIfSuperTokenWasInitialized(dispatchedFrom) {
  if (!window.sendMetric) {
    console.warn('MP Send Metric is not available.');
    return;
  }

  if (sessionStorage.getItem('mp_super_token_init_checked') === 'true') {
    return;
  }

  try {
    if (!window.mpSdkInstance) {
      window.sendMetric(
        'MP_SDK_INSTANCE_NOT_EXISTS',
        'MP SDK instance did not load within the expected time'
        + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
        'mp_super_token_init_error'
      );
      return;
    }

    if (!
      window.mpSuperTokenMetrics
      || !window.mpSuperTokenPaymentMethods
      || !window.mpSuperTokenAuthenticator
      || !window.mpSuperTokenErrorHandler
      || !window.mpSuperTokenTriggerHandler
    ) {
      window.sendMetric(
        'SUPER_TOKEN_CLASSES_NOT_EXISTS',
        `${window.mpSuperTokenMetrics ? '' : 'Metrics class did not load. '
        }${window.mpSuperTokenPaymentMethods ? '' : 'Payment Methods class did not load. '}${window.mpSuperTokenAuthenticator ? '' : 'Authenticator class did not load. '}${window.mpSuperTokenErrorHandler ? '' : 'Error Handler class did not load. '}${window.mpSuperTokenTriggerHandler ? '' : 'Trigger Handler class did not load.'}`
        + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
        'mp_super_token_init_error'
      );
      return;
    }

    if (!window.mpSuperTokenTriggerHandler?.isAlreadyListeningForm) {
      window.sendMetric(
        'SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING',
        'Trigger handler is not listening to the form after super token initialization'
        + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
        'mp_super_token_init_error'
      );
      return;
    }

    window.sendMetric(
      'SUPER_TOKEN_INITIALIZATION_SUCCESS',
      'Super token was initialized successfully and is listening to the form'
      + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
      'mp_super_token_init_success'
    );
  } catch (error) {
    window.sendMetric(
      'SUPER_TOKEN_INITIALIZATION_ERROR',
      `An error occurred while checking super token initialization: ${error.message}`
      + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
      'mp_super_token_init_error'
    );
  } finally {
    sessionStorage.setItem('mp_super_token_init_checked', 'true');
  }
}

document.addEventListener('mp_card_form_mounted', () => {
  checkIfSuperTokenWasInitialized('mp_card_form_mounted');
});

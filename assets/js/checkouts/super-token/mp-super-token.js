/* globals MPDebounce, WCEmailListener, MPSuperTokenMetrics, MPSuperTokenAuthenticator, MPSuperTokenTriggerHandler, MPSuperTokenPaymentMethods, MPSuperTokenErrorHandler */
const WAIT_MP_SDK_INSTANCE_LOAD_INTERVAL = 50;
const MAX_TIME_WAIT_FOR_MP_SDK_INSTANCE_LOAD_TIMEOUT = 15000;
const CHECK_IF_SUPER_TOKEN_CLASSES_IS_LOADED_TIMEOUT = 15000;

// Wait for the MP SDK instance to load super token
const waitMpSdkInstanceLoad = setInterval(() => {
  if (window.mpSdkInstance) {
    clearInterval(waitMpSdkInstanceLoad);
    const SUPER_TOKEN_JS_VERSION = '1.0.4';
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
      mpSuperTokenErrorHandler
    );
  }
}, WAIT_MP_SDK_INSTANCE_LOAD_INTERVAL)

function checkIfSuperTokenWasInitialized(dispatchedFrom) {
  if (!window.sendMetric) {
    console.warn('MP Send Metric is not available.');
    return;
  }

  if (!window.mpSdkInstance) {
    sendMetric(
      'MP_SDK_INSTANCE_NOT_EXISTS',
      'MP SDK instance did not load within the expected time'
      + ` after ${MAX_TIME_WAIT_FOR_MP_SDK_INSTANCE_LOAD_TIMEOUT}ms`
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
    sendMetric(
      'SUPER_TOKEN_CLASSES_NOT_EXISTS',
      `${window.mpSuperTokenMetrics ? '' : 'Metrics class did not load. '
      }${window.mpSuperTokenPaymentMethods ? '' : 'Payment Methods class did not load. '}${window.mpSuperTokenAuthenticator ? '' : 'Authenticator class did not load. '}${window.mpSuperTokenErrorHandler ? '' : 'Error Handler class did not load. '}${window.mpSuperTokenTriggerHandler ? '' : 'Trigger Handler class did not load.'}`
      + ` after ${CHECK_IF_SUPER_TOKEN_CLASSES_IS_LOADED_TIMEOUT}ms`
      + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
      'mp_super_token_init_error'
    );
    return;
  }

  if (!window.mpSuperTokenTriggerHandler?.isAlreadyListeningForm) {
    sendMetric(
      'SUPER_TOKEN_TRIGGER_HANDLER_NOT_LISTENING',
      'Trigger handler is not listening to the form after super token initialization' + ` after ${CHECK_IF_SUPER_TOKEN_CLASSES_IS_LOADED_TIMEOUT}ms`
      + ` Dispatched from: ${dispatchedFrom || 'unknown'}`,
      'mp_super_token_init_error'
    )
  }
}

// Clear the interval if the SDK instance did not load within the expected time
setTimeout(() => {
  clearInterval(waitMpSdkInstanceLoad);
}, MAX_TIME_WAIT_FOR_MP_SDK_INSTANCE_LOAD_TIMEOUT)

// Check if super token was initialized correctly
const checkSuperTokenInitializationTimeout = setTimeout(() => {
  checkIfSuperTokenWasInitialized('timeout');
}, CHECK_IF_SUPER_TOKEN_CLASSES_IS_LOADED_TIMEOUT);

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') {
    clearTimeout(checkSuperTokenInitializationTimeout);
    checkIfSuperTokenWasInitialized('visibilitychange');
  }
});

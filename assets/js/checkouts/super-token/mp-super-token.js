/* globals MPDebounce, WCEmailListener, MPSuperTokenMetrics, MPSuperTokenAuthenticator, MPSuperTokenTriggerHandler, MPSuperTokenPaymentMethods, MPSuperTokenErrorHandler */
const WAIT_MP_SDK_INSTANCE_LOAD_INTERVAL = 50;
const MAX_TIME_WAIT_FOR_MP_SDK_INSTANCE_LOAD = 15000;

// Wait for the MP SDK instance to load super token
const waitMpSdkInstanceLoad = setInterval(() => {
  if (window.mpSdkInstance) {
    clearInterval(waitMpSdkInstanceLoad);
    const SUPER_TOKEN_JS_VERSION = '1.1.2';
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

    if (window.mpEventHandler && typeof window.mpEventHandler.setSuperTokenDependencies === 'function') {
      window.mpEventHandler.setSuperTokenDependencies({
        triggerHandler: window.mpSuperTokenTriggerHandler,
        authenticator: window.mpSuperTokenAuthenticator,
        paymentMethods: window.mpSuperTokenPaymentMethods,
        metrics: window.mpSuperTokenMetrics,
        errorHandler: window.mpSuperTokenErrorHandler,
      });
    }
  }
}, WAIT_MP_SDK_INSTANCE_LOAD_INTERVAL)

// Clear the interval if the SDK instance did not load within the expected time
setTimeout(() => {
  clearInterval(waitMpSdkInstanceLoad);
}, MAX_TIME_WAIT_FOR_MP_SDK_INSTANCE_LOAD)

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

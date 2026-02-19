/* globals MPDebounce, WCEmailListener, MPSuperTokenMetrics, MPSuperTokenAuthenticator, MPSuperTokenTriggerHandler, MPSuperTokenPaymentMethods, MPSuperTokenErrorHandler */
document.addEventListener('DOMContentLoaded', () => {
    const WAIT_MP_SDK_INSTANCE_LOAD_INTERVAL = 50;

    const waitMpSdkInstanceLoad = setInterval(() => {
        if (window.mpSdkInstance) {
            clearInterval(waitMpSdkInstanceLoad);
            const SUPER_TOKEN_JS_VERSION = '1.0.0';
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
});

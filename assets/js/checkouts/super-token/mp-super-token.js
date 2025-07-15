/* globals MPDebounce, WCEmailListener, MPSuperTokenMetrics, MPSuperTokenAuthenticator, MPSuperTokenTriggerHandler, MPSuperTokenPaymentMethods */
document.addEventListener('DOMContentLoaded', () => {
    const waitMpSdkInstanceLoad = setInterval(() => {
        if (window.mpSdkInstance) {
            clearInterval(waitMpSdkInstanceLoad);

            const mpSdkInstance = window.mpSdkInstance;
            const mpDebounce = new MPDebounce();
            const wcEmailListener = new WCEmailListener(mpDebounce);
            const mpSuperTokenMetrics = new MPSuperTokenMetrics(mpSdkInstance);
            const mpSuperTokenPaymentMethods = new MPSuperTokenPaymentMethods(
                mpSdkInstance,
                mpSuperTokenMetrics,
            );
            const mpSuperTokenAuthenticator = new MPSuperTokenAuthenticator(
                mpSdkInstance,
                mpSuperTokenPaymentMethods,
                mpSuperTokenMetrics,
            );

            window.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
            window.mpSuperTokenTriggerHandler = new MPSuperTokenTriggerHandler(
                mpSuperTokenAuthenticator,
                wcEmailListener,
                mpSuperTokenPaymentMethods,
            );
        }
    }, 500)
});
/* globals jQuery */
class MPErrorMessageNormalizer {
    static DEFAULT_ERROR_MESSAGE = 'empty error message';

    static normalize(message) {
        if (!message) return this.DEFAULT_ERROR_MESSAGE;
        return message.replace(/[\t\n]/g, '').trim() || this.DEFAULT_ERROR_MESSAGE;
    }
}

class MPCustomEventDispatcher {
    static dispatch(eventName, detail) {
        document.dispatchEvent(
            new CustomEvent(eventName, { detail })
        );
    }
}

class MPDispatchedErrorTracker {
    static #dispatched = new Set();

    static track(message, errorOrigin) {
        const key = `${errorOrigin}::${message}`;
        if (this.#dispatched.has(key)) return false;
        this.#dispatched.add(key);
        return true;
    }

    static reset() {
        this.#dispatched.clear();
    }
}

class MPCheckoutErrorHandler {
    handle() {
        throw new Error('Method must be implemented');
    }
}

class MPClassicCheckoutErrorHandler extends MPCheckoutErrorHandler {
    #CLASSIC_CHECKOUT_ERROR_EVENT_NAME = 'checkout_error';
    #CLASSIC_FORM = 'form[name=checkout]';
    #CLASSIC_ELEMENT_ERROR = '.woocommerce-error';
    ERROR_EVENT_NAME = 'mp_checkout_error';

    handle() {
        if (!document.querySelector(this.#CLASSIC_FORM)) {
            return;
        }

        jQuery(document.body).on(this.#CLASSIC_CHECKOUT_ERROR_EVENT_NAME, (_, errorMessageAsHTML) => {
            const errorElement = document.querySelector(this.#CLASSIC_ELEMENT_ERROR);
            const errorMessage = errorElement?.textContent || errorMessageAsHTML;
            const normalizedMessage = MPErrorMessageNormalizer.normalize(errorMessage);

            const isPluginError = !!errorElement?.querySelector('li[data-mp-source="plugin"]');
            const errorOrigin = isPluginError ? 'post_submit_mercado_pago' : 'post_submit_woocommerce';

            MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
                message: normalizedMessage,
                errorOrigin: errorOrigin
            });
        });
    }
}

class MPOrderPayCheckoutErrorHandler extends MPCheckoutErrorHandler {
    #ORDER_PAY_FORM = 'form#order_review';
    #ERROR_SELECTORS = [
        '.woocommerce-notices-wrapper .woocommerce-error',
        '#order_review > .woocommerce-error'
    ];
    ERROR_EVENT_NAME = 'mp_checkout_error';

    handle() {
        const form = document.querySelector(this.#ORDER_PAY_FORM);
        if (!form) return;

        form.addEventListener('submit', () => MPDispatchedErrorTracker.reset());

        this.handlePageLoadErrors();
        this.observeForDynamicErrors(form);
    }

    findAllErrorElements() {
        const elements = [];
        for (const selector of this.#ERROR_SELECTORS) {
            elements.push(...document.querySelectorAll(selector));
        }
        return elements;
    }

    dispatchError(errorElement) {
        const normalizedMessage = MPErrorMessageNormalizer.normalize(errorElement.textContent);
        const isPluginError = !!errorElement.querySelector('li[data-mp-source="plugin"]');
        const errorOrigin = isPluginError ? 'post_submit_mercado_pago' : 'post_submit_woocommerce';

        if (!MPDispatchedErrorTracker.track(normalizedMessage, errorOrigin)) return;

        MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
            message: normalizedMessage,
            errorOrigin: errorOrigin
        });
    }

    dispatchAllErrors(errorElements) {
        errorElements.forEach(el => this.dispatchError(el));
    }

    handlePageLoadErrors() {
        const errorElements = this.findAllErrorElements();
        if (!errorElements.length) return;

        this.waitForMelidata().then(() => this.dispatchAllErrors(errorElements));
    }

    waitForMelidata() {
        return new Promise((resolve) => {
            if (window.melidata) {
                resolve();
                return;
            }

            if (window.melidataReady && typeof window.melidataReady.then === 'function') {
                window.melidataReady.then(resolve).catch(resolve);
                return;
            }

            if (window.melidataReady) {
                resolve();
                return;
            }

            if (document.readyState === 'complete') {
                resolve();
                return;
            }

            window.addEventListener('load', () => {
                if (window.melidataReady && typeof window.melidataReady.then === 'function') {
                    window.melidataReady.then(resolve).catch(resolve);
                } else {
                    resolve();
                }
            }, { once: true });
        });
    }

    observeForDynamicErrors(form) {
        const targets = [
            document.querySelector('.woocommerce-notices-wrapper'),
            form
        ].filter(Boolean);

        targets.forEach(target => {
            new MutationObserver((mutations) => {
                const hasAddedNodes = mutations.some(m => m.addedNodes.length > 0);
                if (!hasAddedNodes) return;

                const errorElements = this.findAllErrorElements();
                this.dispatchAllErrors(errorElements);
            }).observe(target, { childList: true });
        });
    }
}

class MPBlocksCheckoutErrorHandler extends MPCheckoutErrorHandler {
    ERROR_EVENT_NAME = 'mp_checkout_error';

    handle(checkoutResponse) {
        const message = checkoutResponse?.processingResponse?.paymentDetails?.message;
        const normalizedMessage = MPErrorMessageNormalizer.normalize(message);

        MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
            message: normalizedMessage,
            errorOrigin: 'post_submit_mercado_pago'
        });
    }
}

class MPCheckoutErrorDispatcher {
    constructor() {
        this.handlers = new Map([
            ['classic', new MPClassicCheckoutErrorHandler()],
            ['orderPay', new MPOrderPayCheckoutErrorHandler()],
            ['blocks', new MPBlocksCheckoutErrorHandler()]
        ]);
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.handlers.get('classic').handle();
            this.handlers.get('orderPay').handle();
        });
    }

    handleBlocksError(checkoutResponse) {
        this.handlers.get('blocks').handle(checkoutResponse);
    }

    static dispatchEventWhenBlocksCheckoutErrorOccurred(checkoutResponse) {
        const dispatcher = new MPCheckoutErrorDispatcher();
        dispatcher.handleBlocksError(checkoutResponse);
    }
}

new MPCheckoutErrorDispatcher().init();

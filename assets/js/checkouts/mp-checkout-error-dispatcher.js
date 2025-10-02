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

            MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
                message: normalizedMessage
            });
        });
    }
}

class MPOrderPayCheckoutErrorHandler extends MPCheckoutErrorHandler {
    #ORDER_PAY_FORM = 'form#order_review';
    #ORDER_PAY_ELEMENT_ERROR = '.woocommerce-notices-wrapper .woocommerce-error';
    #ORDER_PAY_DELAY = 1000;
    ERROR_EVENT_NAME = 'mp_checkout_error';

    handle() {
        if (!document.querySelector(this.#ORDER_PAY_FORM)) {
            return;
        }

        const errorElement = document.querySelector(this.#ORDER_PAY_ELEMENT_ERROR);
        if (!errorElement) return;

        const normalizedMessage = MPErrorMessageNormalizer.normalize(errorElement.textContent);

        setTimeout(() => {
            MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
                message: normalizedMessage
            });
        }, this.#ORDER_PAY_DELAY);
    }
}

class MPBlocksCheckoutErrorHandler extends MPCheckoutErrorHandler {
    ERROR_EVENT_NAME = 'mp_checkout_error';

    handle(checkoutResponse) {
        const message = checkoutResponse?.processingResponse?.paymentDetails?.message;
        const normalizedMessage = MPErrorMessageNormalizer.normalize(message);
        
        MPCustomEventDispatcher.dispatch(this.ERROR_EVENT_NAME, {
            message: normalizedMessage
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
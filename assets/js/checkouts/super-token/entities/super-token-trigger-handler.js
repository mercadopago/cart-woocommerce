/* globals wc_mercadopago_supertoken_trigger_handler_params, jQuery */
/* eslint-disable no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    FORM_CHECKOUT_SELECTOR = 'form.checkout, form#order_review';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    LOADING_ANIMATION_FINISH_DELAY = 500;
    AVOID_INSTANT_REMOVAL_LOADER_DELAY = 500;
    CURRENT_USER_EMAIL = wc_mercadopago_supertoken_bundle_params.current_user_email;

    // Attributes
    wcBuyerEmail = null;
    currentAmount = null;
    isAlreadyListeningForm = false;
    lastException = null;

    // Dependencies
    mpSuperTokenAuthenticator = null;
    wcEmailListener = null;
    mpSuperTokenPaymentMethods = null;
    mpSuperTokenErrorHandler = null;

    constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods, mpSuperTokenErrorHandler) {
        this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
        this.wcEmailListener = wcEmailListener;
        this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
        this.mpSuperTokenErrorHandler = mpSuperTokenErrorHandler;
    }

    hasLastException() {
        return !!this.getLastException();
    }

    getLastException() {
        return this.lastException;
    }

    setLastException(exception) {
        this.lastException = exception;
    }

    getBuyerEmail() {
        this.wcBuyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.CURRENT_USER_EMAIL;

        return this.wcBuyerEmail?.trim();
    }

    amountHasChanged() {
        return this.currentAmount != null
            && this.mpSuperTokenAuthenticator.getAmountUsed() != null
            && this.currentAmount !== this.mpSuperTokenAuthenticator.getAmountUsed();
    }

    isDifferentEmail(newEmail) {
        return this.wcBuyerEmail != newEmail;
    }

    getCustomCheckoutRadioElement() {
        return document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR)
            || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
    }

    isClassicCheckout() {
        return !!document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
    }

    customCheckoutIsEnable() {
        return !!this.getCustomCheckoutRadioElement();
    }

    customCheckoutIsActive() {
        return this.getCustomCheckoutRadioElement()?.checked;
    }

    resetFlow() {
        this.mpSuperTokenAuthenticator.reset();
        this.mpSuperTokenPaymentMethods.reset();
    }

    resetCustomCheckout(shouldClearCache = true) {
        window.mpSuperTokenPaymentMethods?.hideSuperTokenError();
        window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
        this.mpSuperTokenAuthenticator?.setSuperTokenValidation(false);

        if (this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
            this.mpSuperTokenPaymentMethods.unmountCardForm();
            this.mpSuperTokenPaymentMethods.mountCardForm();
        }

        if (shouldClearCache) {
            this.resetFlow();
        }

        this.loadSuperToken(this.currentAmount)
            .finally(() => {
                setTimeout(() => {
                    window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();

                    if (this.mpSuperTokenPaymentMethods.hasCheckoutError()) {
                      this.mpSuperTokenPaymentMethods.selectLastPaymentMethodChoosen();
                    }

                    if (this.hasLastException()) {
                      setTimeout(() => {
                        this.mpSuperTokenErrorHandler.handleError(this.getLastException());
                        this.setLastException(null);
                      }, this.LOADING_ANIMATION_FINISH_DELAY);
                    }
                }, this.AVOID_INSTANT_REMOVAL_LOADER_DELAY);
            });
    }

    resetSuperTokenOnError() {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
            const paymentMethodList = document.querySelector(`.${this.mpSuperTokenPaymentMethods?.SUPER_TOKEN_STYLES?.PAYMENT_METHOD_LIST}`);

            if (paymentMethodList) {
              paymentMethodList.scrollIntoView({ behavior: 'smooth' });
            }

            this.mpSuperTokenPaymentMethods.deselectAllPaymentMethods();
            this.mpSuperTokenPaymentMethods.hideAllPaymentMethodDetails();
            this.mpSuperTokenPaymentMethods.unmountActiveSecurityCodeInstance();
            this.mpSuperTokenPaymentMethods.activePaymentMethod = null;

            this.resetCustomCheckout(true);
        }
    }

    isSuperTokenPaymentMethodsLoaded() {
        return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()
    }

    async fetchAndRenderSuperTokenPaymentMethods() {
        const buyerEmail = this.getBuyerEmail();
        if (!buyerEmail) return;

        const paymentMethods = await this.mpSuperTokenAuthenticator.getAccountPaymentMethods(
            this.currentAmount,
            buyerEmail
        );

        if (!paymentMethods || !paymentMethods.length) return;

        await this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
            paymentMethods,
            this.currentAmount
        );
    }

    async loadSuperToken(currentAmount) {
        this.currentAmount = this.mpSuperTokenAuthenticator.formatAmount(currentAmount);

        if (this.amountHasChanged()) this.resetFlow();

        if (this.isSuperTokenPaymentMethodsLoaded()) {
            this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
                this.mpSuperTokenPaymentMethods.getStoredPaymentMethods(),
                this.currentAmount
            );

            return;
        }

        await this.fetchAndRenderSuperTokenPaymentMethods();

        if (!this.isAlreadyListeningForm) {
            this.wcEmailListener.onEmailChange(async (email, isValid) => {
                if (!isValid || !currentAmount) {
                    return;
                }

                if (this.isDifferentEmail(email) && this.wcBuyerEmail != null) {
                    this.wcBuyerEmail = email;
                    this.resetCustomCheckout();
                }
            });

            this.wcEmailListener.setupEmailChangeHandlers();

            this.isAlreadyListeningForm = true;
        }
    }
}

/* globals wc_mercadopago_supertoken_trigger_handler_params, jQuery */
/* eslint-disable no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    FORM_CHECKOUT_SELECTOR = 'form.checkout, form#order_review';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    CURRENT_USER_EMAIL = wc_mercadopago_supertoken_trigger_handler_params.current_user_email;

    // Attributes
    wcBuyerEmail = null;
    currentAmount = null;
    isAlreadyListeningForm = false;

    // Dependencies
    mpSuperTokenAuthenticator = null;
    wcEmailListener = null;
    mpSuperTokenPaymentMethods = null;

    constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods) {
        this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
        this.wcEmailListener = wcEmailListener;
        this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
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

        if (this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
            this.mpSuperTokenPaymentMethods.unmountCardForm();
            this.mpSuperTokenPaymentMethods.mountCardForm();
        }

        if (shouldClearCache) {
            this.resetFlow();
        }

        this.loadSuperToken(this.currentAmount)
            .finally(() => {
                // Apenas para não remover o loader tão rápido
                setTimeout(() => {
                    window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();

                    if (this.mpSuperTokenPaymentMethods.hasCheckoutError()) {
                      this.mpSuperTokenPaymentMethods.selectLastPaymentMethodChoosen();
                    }
                }, 500);
            });
    }

    resetSuperTokenOnError() {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
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

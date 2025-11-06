/* globals wc_mercadopago_supertoken_trigger_handler_params, jQuery */
/* eslint-disable no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    FORM_CHECKOUT_SELECTOR = 'form.checkout, form#order_review';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    WALLET_BUTTON_CONTAINER_SELECTOR = '.mp-wallet-button-container';
    WALLET_BUTTON_OPTION_VALUE = 'wallet_button';
    CURRENT_USER_EMAIL = wc_mercadopago_supertoken_trigger_handler_params.current_user_email;
    WALLET_BUTTON_ENABLED = wc_mercadopago_supertoken_trigger_handler_params.wallet_button_enabled;

    // Attributes
    wcBuyerEmail = null;
    currentAmount = null;
    isAlreadyListeningForm = false;

    // Dependencies
    mpSuperTokenAuthenticator = null;
    wcEmailListener = null;
    mpSuperTokenPaymentMethods = null;
    mpSuperTokenTriggerFieldsStrategy = null;

    constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods, mpSuperTokenTriggerFieldsStrategy) {
        this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
        this.wcEmailListener = wcEmailListener;
        this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
        this.mpSuperTokenTriggerFieldsStrategy = mpSuperTokenTriggerFieldsStrategy;
    }

    walletButtonIsActive() {
        return this.WALLET_BUTTON_ENABLED
    }

    getBuyerEmail() {
        this.wcBuyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.CURRENT_USER_EMAIL;

        return this.wcBuyerEmail;
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
        return !!document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_SELECTOR);
    }

    getAmount() {
        const cartTotalFromHTML = this.getCartTotalFromHTML();
        if (cartTotalFromHTML) {
            return cartTotalFromHTML;
        }

        const amountElement = document.getElementById('mp-amount');
        if (!amountElement) {
            return null;
        }

        return this.mpSuperTokenAuthenticator.formatAmount(amountElement.value);
    }

    getCartTotalFromHTML() {
        const cartTotalElement = document.querySelector('.wc-block-components-totals-wrapper .wc-block-components-totals-item__value .wc-block-components-formatted-money-amount');
        if (!cartTotalElement || !cartTotalElement?.textContent) {
            return null;
        }

        const cleaned = cartTotalElement.textContent.replace(/[^\d.,]/g, '');
        if (!/,\d{2}$/.test(cleaned)) {
            return null;
        }

        const amount = parseFloat(cleaned.replace(/\./g, '').replace(',', '.'));
        return amount?.toFixed(2);
    }


    customCheckoutIsEnable() {
        return !!this.getCustomCheckoutRadioElement();
    }

    customCheckoutIsActive() {
        return this.getCustomCheckoutRadioElement()?.checked;
    }

    onTriggerWalletButton(customSubmitFallback = null) {
        const useWalletButtonFlow = () => {
            document.dispatchEvent(new CustomEvent('mp_wallet_button_submitted'));

            jQuery(this.CHECKOUT_TYPE_SELECTOR).val(this.WALLET_BUTTON_OPTION_VALUE);

            if (customSubmitFallback) {
                customSubmitFallback();
                return;
            }

            jQuery(this.FORM_CHECKOUT_SELECTOR).submit();
        }

        const buyerEmail = this.getBuyerEmail();

        if (!buyerEmail) {
            useWalletButtonFlow();
            return;
        }

        if (this.mpSuperTokenAuthenticator.emailAlreadyVerified() && !this.mpSuperTokenAuthenticator.isAbleToUseSuperToken()) {
            useWalletButtonFlow();
            return;
        }

        this.mpSuperTokenAuthenticator.authenticate(this.currentAmount, buyerEmail, { confirmationLocation: 'app', skipAllUserConfirmation: true })
            .catch(() => {
                useWalletButtonFlow();
            });
    }

    resetFlow() {
        this.mpSuperTokenAuthenticator.reset();
        this.mpSuperTokenPaymentMethods.reset();
    }

    resetCustomCheckout() {
        window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();

        if (this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
            this.mpSuperTokenPaymentMethods.unmountCardForm();
            this.mpSuperTokenPaymentMethods.mountCardForm();
        }

        this.resetFlow();
        this.loadSuperToken(this.getAmount())
            .finally(() => {
                // Apenas para não remover o loader tão rápido
                setTimeout(() => {
                    window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();
                }, 500);
            });
    }

    resetSuperTokenOnError() {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
            this.resetCustomCheckout();
            document.querySelector('#mp_checkout_type').value = '';
        }
    }

    isSuperTokenPaymentMethodsLoaded() {
        return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()
    }

    onSelectPreloadedPaymentMethod = async (_paymentMethodElement, paymentMethod) => {
        this.mpSuperTokenPaymentMethods.hidePaymentMethodsList();
        window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
        this.mpSuperTokenPaymentMethods.reset();
        this.mpSuperTokenPaymentMethods.emitEventFromSelectPaymentMethod(paymentMethod);
        this.mpSuperTokenPaymentMethods.storeSelectedPreloadedPaymentMethod(paymentMethod);
        await this.mpSuperTokenAuthenticator.showAuthenticatorWithPreloadedPaymentMethods();
        window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();
        this.mpSuperTokenPaymentMethods.showPaymentMethodsList();
    }

    async _loadSuperTokenPaymentMethodsPreview() {
        const buyerEmail = this.getBuyerEmail();
        if (!buyerEmail) return;

        const preloadedPaymentMethods = await this.mpSuperTokenAuthenticator.getPreloadedPaymentMethods(this.currentAmount, buyerEmail);
        if (!preloadedPaymentMethods || !preloadedPaymentMethods.length) return;

        await this.mpSuperTokenPaymentMethods.renderPreloadedPaymentMethods(preloadedPaymentMethods, this.onSelectPreloadedPaymentMethod)
    }

    async loadSuperToken(currentAmount = this.getAmount()) {
        if (this.mpSuperTokenAuthenticator.isUserClosedModal()) {
            return;
        }

        this.currentAmount = this.mpSuperTokenAuthenticator.formatAmount(currentAmount);

        if (this.amountHasChanged() && !this.walletButtonIsActive()) this.resetFlow();

        if (this.isSuperTokenPaymentMethodsLoaded()) {
            this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
                this.mpSuperTokenPaymentMethods.getStoredPaymentMethods()
            );

            return;
        }

        if (this.walletButtonIsActive()) {
            await this._loadSuperTokenPaymentMethodsPreview();
        }

        if (!this.walletButtonIsActive()) {
          this.mpSuperTokenTriggerFieldsStrategy.createClickableAreaListeners(this.currentAmount, this.getBuyerEmail());
        }

        if (!this.isAlreadyListeningForm) {
            this.wcEmailListener.onEmailChange(async (email, isValid) => {
                if (!isValid || !currentAmount) {
                    return;
                }

                if (this.isDifferentEmail(email) && this.wcBuyerEmail != null) {
                    this.wcBuyerEmail = email;
                    this.resetCustomCheckout();
                }

                if (this.walletButtonIsActive()) return;

                await this.mpSuperTokenAuthenticator.canUseSuperTokenFlow(currentAmount, email);
            });

            this.wcEmailListener.setupEmailChangeHandlers();

            this.isAlreadyListeningForm = true;
        }
    }
}

/* globals wc_mercadopago_supertoken_trigger_handler_params, jQuery */
/* eslint-disable no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_method_woo-mercado-pago-custom';
    FORM_CHECKOUT_SELECTOR = 'form.checkout, form#order_review';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    WALLET_BUTTON_CONTAINER_SELECTOR = '.mp-wallet-button-container';
    CUSTOM_CHECKOUT_CONTAINER_ID = 'mp-custom-checkout-form-container';
    CLICKABLE_AREA_CLASS = 'mp-super-token-clickable-area';
    CARD_NUMBER_FIELD_ID = 'form-checkout__cardNumber-container';
    CARD_HOLDER_NAME_FIELD_ID = 'form-checkout__cardholderName';
    EXPIRATION_DATE_FIELD_ID = 'form-checkout__expirationDate-container';
    SECURITY_CODE_FIELD_ID = 'form-checkout__securityCode-container';
    WALLET_BUTTON_OPTION_VALUE = 'wallet_button';
    CURRENT_USER_EMAIL = wc_mercadopago_supertoken_trigger_handler_params.current_user_email;

    // Attributes
    wcBuyerEmail = null;
    currentAmount = null;
    isAlreadyListeningForm = false;
    isAuthenticating = false;

    // Dependencies
    mpSuperTokenAuthenticator = null;
    wcEmailListener = null;
    mpSuperTokenPaymentMethods = null;

    constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods) {
        this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
        this.wcEmailListener = wcEmailListener;
        this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
    }

    reset() {
        this.removeClickableAreas();
        this.isAuthenticating = false;
    }

    walletButtonIsActive() {
        return !!document.querySelector(this.WALLET_BUTTON_CONTAINER_SELECTOR);
    }

    getBuyerEmail() {
        return this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.CURRENT_USER_EMAIL;
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

        const amount = parseFloat(amountElement?.value?.replace(',', '.'));
        const normalizedAmount = amount.toFixed(2);

        return String(normalizedAmount);
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

    alreadyHasClickableArea() {
        return !!document.querySelector(`.${this.CLICKABLE_AREA_CLASS}`);
    }

    shouldCreateClickableArea() {
        return this.customCheckoutIsActive() && !this.alreadyHasClickableArea() && !this.walletButtonIsActive();
    }

    getMercadoPagoCustomCheckoutContainerElement() {
        return document.getElementById(this.CUSTOM_CHECKOUT_CONTAINER_ID)
    }

    getCreditCardFormFields() {
        return [
            document.getElementById(this.CARD_NUMBER_FIELD_ID),
            document.getElementById(this.CARD_HOLDER_NAME_FIELD_ID),
            document.getElementById(this.EXPIRATION_DATE_FIELD_ID),
            document.getElementById(this.SECURITY_CODE_FIELD_ID),
        ];
    }

    createClickableArea(element) {
        const clickableArea = element.parentElement;

        clickableArea.addEventListener('click', this.onTrigger.bind(this), { once: true });
        clickableArea.classList.add(this.CLICKABLE_AREA_CLASS);

        element.style.pointerEvents = 'none';
        element.classList.add('mp-pointer-events-none');
    }

    removeClickableAreas() {
        const clickableAreas = document.querySelectorAll(`.${this.CLICKABLE_AREA_CLASS}`);

        clickableAreas.forEach((clickableArea) => {
            const input = clickableArea.querySelector('.mp-pointer-events-none');
            input.style.pointerEvents = 'auto';
            input.classList.remove('mp-pointer-events-none');
            clickableArea.classList.remove(this.CLICKABLE_AREA_CLASS);
            clickableArea.removeEventListener('click', this.onTrigger.bind(this));
        });
    }

    onTrigger() {
        if (this.isAuthenticating || !this.alreadyHasClickableArea()) {
            return;
        }

        this.isAuthenticating = true;

        const buyerEmail = this.getBuyerEmail();
        if (!buyerEmail) {
            this.isAuthenticating = false;
            this.removeClickableAreas();
            return;
        }

        this.mpSuperTokenAuthenticator.authenticate(this.currentAmount, buyerEmail)
            .finally(() => {
                this.isAuthenticating = false;
                this.removeClickableAreas();
            });
    }

    onTriggerWalletButton() {
        const useWalletButtonFlow = () => {
            jQuery(this.CHECKOUT_TYPE_SELECTOR).val(this.WALLET_BUTTON_OPTION_VALUE);
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

        this.mpSuperTokenAuthenticator.authenticate(this.currentAmount, buyerEmail, { confirmationLocation: 'app' })
            .catch(() => {
                useWalletButtonFlow();
            });
    }

    resetFlow() {
        this.reset();
        this.mpSuperTokenAuthenticator.reset();
        this.mpSuperTokenPaymentMethods.reset();
    }

    resetCustomCheckout() {
        if (this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
            this.mpSuperTokenPaymentMethods.unmountCardForm();
            this.mpSuperTokenPaymentMethods.mountCardForm();
        }

        this.resetFlow();
        this.loadSuperToken(this.getAmount());
    }

    resetSuperTokenOnError() {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
            this.resetCustomCheckout();
            this.isAuthenticating = false;
            document.querySelector('#mp_checkout_type').value = '';
        }
    }

    isSuperTokenPaymentMethodsLoaded() {
        return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()
    }

    async loadSuperToken(currentAmount = this.getAmount()) {
        if (this.mpSuperTokenAuthenticator.isUserClosedModal()) {
            return;
        }

        this.currentAmount = currentAmount;

        if (this.amountHasChanged()) this.resetFlow();

        if (this.isSuperTokenPaymentMethodsLoaded()) {
            this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
                this.mpSuperTokenPaymentMethods.getStoredPaymentMethods()
            );

            return;
        }

        if (this.shouldCreateClickableArea()) {
            this.getCreditCardFormFields().forEach((elementField) => {
                this.createClickableArea(elementField);
            });
        }

        if (!this.isAlreadyListeningForm) {
            this.wcEmailListener.onEmailChange(async (email, isValid) => {
                if (!isValid || !currentAmount) {
                    return;
                }

                if (this.isDifferentEmail(email) && this.wcBuyerEmail != null) {
                    this.resetCustomCheckout();
                }
                
                this.wcBuyerEmail = email;

                const canUseSuperToken = await this.mpSuperTokenAuthenticator.canUseSuperTokenFlow(currentAmount, email);

                if (!canUseSuperToken) {
                    this.removeClickableAreas();
                }
            });
            
            this.wcEmailListener.setupEmailChangeHandlers();

            this.isAlreadyListeningForm = true;
        }
    }
}

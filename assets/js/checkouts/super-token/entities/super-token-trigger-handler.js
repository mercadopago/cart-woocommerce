/* eslint-disable no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_method_woo-mercado-pago-custom';
    CUSTOM_CHECKOUT_CONTAINER_ID = 'mp-custom-checkout-form-container';
    CLICKABLE_AREA_STARTS_ID = 'mp-super-token-clickable-area';
    CARD_NUMBER_FIELD_ID = 'form-checkout__cardNumber-container';
    CARD_HOLDER_NAME_FIELD_ID = 'form-checkout__cardholderName';
    EXPIRATION_DATE_FIELD_ID = 'form-checkout__expirationDate-container';
    SECURITY_CODE_FIELD_ID = 'form-checkout__securityCode-container';

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

    reset() {
        this.removeClickableAreas();
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
        return !!document.querySelector(`[id^=${this.CLICKABLE_AREA_STARTS_ID}]`);
    }

    shouldCreateClickableArea() {
        return this.customCheckoutIsActive() && !this.alreadyHasClickableArea();
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
        const clickableArea = document.createElement('div');

        clickableArea.id = this.CLICKABLE_AREA_STARTS_ID + element.id;
        clickableArea.addEventListener('click', this.onTrigger.bind(this), { once: true });

        element.style.pointerEvents = 'none';

        if (element.id.includes('holderName')) {
            element.style.width = '100%';
            element.style.boxSizing = 'border-box !important';
        }

        element.parentNode.replaceChild(clickableArea, element);
        clickableArea.appendChild(element);
    }

    removeClickableAreas() {
        const clickableAreas = document.querySelectorAll(`[id^=${this.CLICKABLE_AREA_STARTS_ID}]`);

        clickableAreas.forEach((clickableArea) => {
            clickableArea.firstChild.style.pointerEvents = 'auto';
            clickableArea.replaceWith(clickableArea.firstChild);
        });
    }

    onTrigger() {
        this.removeClickableAreas();

        const buyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail();

        this.mpSuperTokenAuthenticator.authenticate(this.currentAmount, buyerEmail);
    }

    resetFlow() {
        this.reset();
        this.mpSuperTokenPaymentMethods.reset();
    }

    resetCustomCheckout() {
        this.mpSuperTokenPaymentMethods.unmountCardForm();
        this.mpSuperTokenPaymentMethods.mountCardForm();
        this.resetFlow();
        this.loadSuperToken(this.getAmount());
        document.querySelector(this.mpSuperTokenPaymentMethods.CHECKOUT_CONTAINER_SELECTOR).style.height = 'auto';
    }

    resetSuperTokenOnError() {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
            console.log('Resetting ST flow due to checkout error');

            this.resetCustomCheckout();

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
                if (this.isDifferentEmail(email) && this.wcBuyerEmail != null) {
                    this.wcBuyerEmail = email;
                    this.resetCustomCheckout();
                    return;
                }

                this.wcBuyerEmail = email;

                if (isValid && currentAmount) {
                    const canUseSuperToken = await this.mpSuperTokenAuthenticator.canUseSuperTokenFlow(currentAmount, email);

                    if (!canUseSuperToken) {
                        this.removeClickableAreas();
                    }
                }
            });
            
            this.wcEmailListener.setupEmailChangeHandlers();

            this.isAlreadyListeningForm = true;
        }
    }
}

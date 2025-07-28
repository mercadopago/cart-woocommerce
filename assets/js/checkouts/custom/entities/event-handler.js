/* globals MP_DEVICE_SESSION_ID */
class MPEventHandler {
    constructor(cardForm, threeDSHandler) {
        this.cardForm = cardForm;
        this.threeDSHandler = threeDSHandler;
        this.triggeredPaymentMethodSelectedEvent = false;
        this.mercado_pago_submit = false;
        this.hasToken = false;
        this.mpFormId = 'checkout';
    }

    bindEvents() {
        jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', this.mercadoPagoFormHandler.bind(this));
        jQuery('body').on('payment_method_selected', this.handlePaymentMethodSelected.bind(this));
        jQuery('form#order_review').submit(this.handleOrderReviewSubmit.bind(this));
        jQuery(document.body).on('checkout_error', this.handleCheckoutError.bind(this));
        jQuery(document).on('updated_checkout', this.handleUpdatedCheckout.bind(this));
        jQuery(document).ready(() => {
            this.setCardFormLoadInterval();
            this.threeDSHandler.set3dsStatusValidationListener();
        });
    }

    mercadoPagoFormHandler() {
        this.setMercadoPagoSessionId();

        if (this.mercado_pago_submit) {
            return true;
        }

        if (jQuery('#mp_checkout_type').val() === 'wallet_button') {
            return true;
        }

        if (jQuery('#mp_checkout_type').val() === 'super_token') {
            if (!window.mpSuperTokenPaymentMethods) {
                return false;
            }

            if (!window.mpSuperTokenPaymentMethods.isSelectedPaymentMethodValid()) {
                window.mpSuperTokenPaymentMethods.forceShowValidationErrors();
                this.cardForm.removeLoadSpinner();
                return false;
            }

            window.mpSuperTokenPaymentMethods.updateSecurityCode();

            return true;
        }

        jQuery('#mp_checkout_type').val('custom');

        if (!this.hasToken) {
            this.setPayerIdentificationInfo();
            return this.createToken();
        }

        return false;
    }

    createToken() {
        this.cardForm.form
            .createCardToken()
            .then((cardToken) => {
                if (cardToken.token) {
                    if (this.hasToken) {
                        return;
                    }

                    document.querySelector('#cardTokenId').value = cardToken.token;
                    this.mercado_pago_submit = true;
                    this.hasToken = true;

                    if (this.mpFormId === 'order_review') {
                        this.handle3dsPayOrderFormSubmission();
                        return false;
                    }

                    jQuery('form.checkout').submit();
                } else {
                    throw new Error('cardToken is empty');
                }
            })
            .catch((error) => {
                console.warn('Token creation error: ', error);
                this.cardForm.removeLoadSpinner();
            });

        return false;
    }

    setMercadoPagoSessionId() {
        try {
            document.querySelector('#mpCardSessionId').value = MP_DEVICE_SESSION_ID;
        } catch (e) {
            console.warn(e);
        }
    }

    setCardFormLoadInterval() {
        var cardFormInterval = setInterval(() => {
            const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom') || 
                document.querySelector('input[value=woo-mercado-pago-custom]');
            const cardInput = document.getElementById('form-checkout__cardNumber-container');

            if (!checkoutCustomPaymentMethodElement || !checkoutCustomPaymentMethodElement.checked) {
                clearInterval(cardFormInterval);
                return;
            }

            if (cardInput && cardInput.childElementCount > 0) {
                clearInterval(cardFormInterval);
                return;
            }

            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }

            this.cardForm.initCardForm();
        }, 1500);
    }

    handlePaymentMethodSelected() {
        if (!this.triggeredPaymentMethodSelectedEvent) {
            this.cardForm.initCardForm();
        }
    }

    handleOrderReviewSubmit(event) {
        const selectPaymentMethod = document.getElementById('payment_method_woo-mercado-pago-custom') || 
            document.querySelector('input[value=woo-mercado-pago-custom]');

        if (selectPaymentMethod && selectPaymentMethod.checked) {
            event.preventDefault();
            return this.mercadoPagoFormHandler();
        } else {
            this.cardForm.initCardForm();
        }
    }

    handleCheckoutError() {
        this.hasToken = false;
        this.mercado_pago_submit = false;

        this.cardForm.removeLoadSpinner();
        window.mpSuperTokenTriggerHandler?.resetSuperTokenOnError();
    }

    handleUpdatedCheckout() {
        const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom') || 
            document.querySelector('input[value=woo-mercado-pago-custom]');

        if (checkoutCustomPaymentMethodElement && checkoutCustomPaymentMethodElement.checked) {
            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }
            this.cardForm.initCardForm();
        }
    }

    handle3dsPayOrderFormSubmission() {
        var serializedForm = jQuery('#order_review').serialize();

        jQuery
            .post('#', serializedForm)
            .done((response) => {
                if (response.three_ds_flow) {
                    this.threeDSHandler.load3DSFlow(response.last_four_digits);
                    return;
                }

                if (response.redirect) {
                    window.location.href = response.redirect;
                }

                window.location.reload();
            })
            .error(() => {
                window.location.reload();
            });
    }

    /**
     * Set payer identification info to hidden inputs
     * Replicates the same functionality from block mode
     * 
     * This function ensures that document type and number values are properly
     * synchronized between the visible form inputs and hidden inputs that are
     * sent to the server. This provides consistency between classic and block
     * checkout modes for the payer identification data.
     * 
     * @see custom.block.js - Similar functionality for block mode
     */
    setPayerIdentificationInfo() {
        const documentElements = [
            { selector: '#form-checkout__identificationType', hiddenInputId: '#payerDocType' },
            { selector: '#form-checkout__identificationNumber', hiddenInputId: '#payerDocNumber' }
        ];

        documentElements.forEach(({ selector, hiddenInputId }) => {
            const element = document.querySelector(selector);
            const hiddenInput = document.querySelector(hiddenInputId);
        
            if (element && hiddenInput && element.value) {
                hiddenInput.value = element.value;
            }
        });
    }
} 
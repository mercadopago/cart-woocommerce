/* eslint-disable no-unused-vars */
/* globals MP_DEVICE_SESSION_ID, jQuery, CheckoutPage */
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
        jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', (event, wc_checkout_form) => this.mercadoPagoFormHandler(event, wc_checkout_form));
        jQuery('body').on('payment_method_selected', this.handlePaymentMethodSelected.bind(this));
        jQuery('form#order_review').submit(this.handleOrderReviewSubmit.bind(this));
        jQuery(document.body).on('checkout_error', this.handleCheckoutError.bind(this));
        jQuery(document).on('updated_checkout', this.handleUpdatedCheckout.bind(this));
        jQuery(document).ready(() => {
            this.setCardFormLoadInterval();
            this.threeDSHandler.set3dsStatusValidationListener();
        });
    }

    showCheckoutClassicLoader() {
        jQuery('form.checkout')?.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    hideCheckoutClassicLoader() {
        jQuery('form.checkout')?.unblock();
    }

    mercadoPagoFormHandler(event, wc_checkout_form) {
        this.setMercadoPagoSessionId();

        if (this.mercado_pago_submit) {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'wallet_button') {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'super_token') {
            event.preventDefault();

            this.showCheckoutClassicLoader();

            if (this.mercado_pago_submit) {
                this.mercado_pago_submit = false;
                return true;
            }

            if (!window.mpSuperTokenPaymentMethods) {
                return false;
            }

            if (!window.mpSuperTokenPaymentMethods.isSelectedPaymentMethodValid()) {
                window.mpSuperTokenPaymentMethods.forceShowValidationErrors();
                this.cardForm.removeLoadSpinner();
                this.hideCheckoutClassicLoader();
                return false;
            }

            window.mpSuperTokenPaymentMethods.updateSecurityCode()
                .then(() => {
                    this.mercado_pago_submit = true;
                    wc_checkout_form.$checkout_form.trigger('submit');
                })
                .catch(() => {
                    this.cardForm.removeLoadSpinner();
                    this.hideCheckoutClassicLoader();
                    return false;
                });

            return false;
        } else {
            jQuery('#mp_checkout_type').val('custom');

            if (!this.hasToken) {
                this.setPayerIdentificationInfo();
                return this.createToken();
            }
    
            return false;
        }
    }

    createToken() {
        if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.verifyInstallmentsContainer === 'function') {
            if (!CheckoutPage.verifyInstallmentsContainer()) {
                this.cardForm.removeLoadSpinner();
                return false;
            }
        }
    
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
        if (typeof MP_DEVICE_SESSION_ID === 'undefined' || !MP_DEVICE_SESSION_ID) {
            return;
        }

        try {
            document.querySelector('#mpCardSessionId').value = MP_DEVICE_SESSION_ID;
        } catch (e) {
            console.warn(e);
        }
    }

    isCheckoutCustomPaymentMethodSelected() {
        const checkoutCustomPaymentMethodElement = document.getElementById('payment_method_woo-mercado-pago-custom') || 
            document.querySelector('input[value=woo-mercado-pago-custom]');

        return checkoutCustomPaymentMethodElement && checkoutCustomPaymentMethodElement.checked;
    }

    setCardFormLoadInterval() {
        var cardFormInterval = setInterval(() => {
            const cardInput = document.getElementById('form-checkout__cardNumber-container');

            if (!this.isCheckoutCustomPaymentMethodSelected()) {
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
        if (!this.isCheckoutCustomPaymentMethodSelected()) {
            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }
            return;
        }

        this.cardForm.createLoadSpinner();

        if (!this.triggeredPaymentMethodSelectedEvent) {
            this.cardForm.initCardForm();
        }
    }

    handleOrderReviewSubmit(event) {
        if (this.isCheckoutCustomPaymentMethodSelected()) {
            event.preventDefault();
            this.cardForm.createLoadSpinner();
            return this.mercadoPagoFormHandler();
        } else {
            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }
        }
    }

    handleCheckoutError() {
        this.hasToken = false;
        this.mercado_pago_submit = false;

        this.cardForm.removeLoadSpinner();
        window.mpSuperTokenTriggerHandler?.resetSuperTokenOnError();
    }

    handleUpdatedCheckout() {
        if (this.isCheckoutCustomPaymentMethodSelected()) {
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

                if (response.result === 'success' && response.redirect) {
                    window.location.href = response.redirect;
                    return;
                }

                if (response.result === 'fail') {
                    jQuery('#order_review .woocommerce-error, #order_review .woocommerce-message').remove();
                    
                    jQuery('#order_review').prepend(
                        '<div class="woocommerce-error">' + response.messages + '</div>'
                    );

                    this.cardForm.removeBlockOverlay();
                    this.cardForm.removeLoadSpinner();
                    this.hasToken = false;
                    this.mercado_pago_submit = false;
                    
                    return;
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
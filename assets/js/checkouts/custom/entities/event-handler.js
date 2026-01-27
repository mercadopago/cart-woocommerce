/* eslint-disable no-unused-vars */
/* globals wc_mercadopago_custom_event_handler_params, MP_DEVICE_SESSION_ID, jQuery, CheckoutPage, MPSuperTokenErrorCodes */
class MPEventHandler {
    REMOVE_LOAD_SPINNER_DELAY = 500;

    constructor(cardForm, threeDSHandler) {
        this.cardForm = cardForm;
        this.threeDSHandler = threeDSHandler;
        this.triggeredPaymentMethodSelectedEvent = false;
        this.mercado_pago_submit = false;
        this.hasToken = false;
        this.mpFormId = 'checkout';
    }

    bindEvents() {
        console.log('[MP DEBUG] bindEvents called');
        jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', (event, wc_checkout_form) => this.mercadoPagoFormHandler(event, wc_checkout_form));
        jQuery('body').on('payment_method_selected', this.handlePaymentMethodSelected.bind(this));
        jQuery('form#order_review').submit(this.handleOrderReviewSubmit.bind(this));
        jQuery(document.body).on('checkout_error', this.handleCheckoutError.bind(this));
        jQuery(document).on('updated_checkout', this.handleUpdatedCheckout.bind(this));
        jQuery(document).ready(() => {
            console.log('[MP DEBUG] Document ready, is_mobile:', wc_mercadopago_custom_event_handler_params.is_mobile);
            this.threeDSHandler.set3dsStatusValidationListener();
            if (!wc_mercadopago_custom_event_handler_params.is_mobile) {
                console.log('[MP DEBUG] Calling initCardFormWhenReady from document.ready');
                this.initCardFormWhenReady();
            }
        });
    }

    initCardFormWhenReady() {
        console.log('[MP DEBUG] initCardFormWhenReady called');
        const customContainer = document.querySelector('.mp-checkout-custom-container');
        const orderReviewForm = document.querySelector('form#order_review');

        console.log('[MP DEBUG] Context:', {
            hasOrderReviewForm: !!orderReviewForm,
            hasCustomContainer: !!customContainer
        });

        // On order pay page, verify if custom checkout is present and should be initialized
        if (orderReviewForm) {
            // Check if payment method radio exists and is selected
            const isRadioSelected = this.isCheckoutCustomPaymentMethodSelected();

            // If no radio exists but container is present, it means custom checkout is the only/pre-selected method
            const hasOnlyCustomCheckout = !document.querySelector('input[name="payment_method"]') && customContainer;

            console.log('[MP DEBUG] Order-pay page:', {
                isRadioSelected,
                hasOnlyCustomCheckout,
                willInitialize: isRadioSelected || hasOnlyCustomCheckout
            });

            if (isRadioSelected || hasOnlyCustomCheckout) {
                console.log('[MP DEBUG] Initializing card form on order-pay page');
                this.cardForm.initCardForm();
            }
            return;
        }

        // On regular checkout page, use the existing method to check if payment method is selected
        const isSelected = this.isCheckoutCustomPaymentMethodSelected();
        console.log('[MP DEBUG] Regular checkout page, isSelected:', isSelected);

        if (isSelected) {
            console.log('[MP DEBUG] Initializing card form on regular checkout');
            this.cardForm.initCardForm();
        }
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
        window.mpSuperTokenPaymentMethods?.hideSuperTokenError();

        if (this.mercado_pago_submit) {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'wallet_button') {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'super_token') {
          window.mpSuperTokenMetrics?.registerClickOnPlaceOrderButton();

          if (this.hasWooCommerceValidationErrors()) return true;

          this.handleWithSuperTokenSubmit(event, wc_checkout_form)

          // Return false to avoid the default behavior of the form submission
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

    async handleWithSuperTokenSubmit(event, wc_checkout_form) {
        try {
            event.preventDefault();
            this.showCheckoutClassicLoader();

            const superTokenPaymentMethods = window.mpSuperTokenPaymentMethods;
            const superTokenAuthenticator = window.mpSuperTokenAuthenticator;

            if (!superTokenPaymentMethods) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
            if (!superTokenAuthenticator) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);

            const activeMethod = superTokenPaymentMethods.getActivePaymentMethod();
            const isSuperTokenValid = activeMethod && superTokenPaymentMethods.isSelectedPaymentMethodValid();

            if (!activeMethod) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);
            if (!isSuperTokenValid) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);

            await superTokenAuthenticator.authorizePayment(activeMethod.token);

            await superTokenPaymentMethods.updateSecurityCode();

            this.mercado_pago_submit = true;
            wc_checkout_form.$checkout_form.trigger('submit');
        } catch(exception) {
            window.mpSuperTokenErrorHandler?.handleError(exception);
            this.cardForm.removeLoadSpinner();
            this.hideCheckoutClassicLoader();
        }
    }

    isInsideHiddenContainer(field) {
        const containersToCheck = [
            '.shipping_address',
            '.billing_address',
            '.woocommerce-shipping-fields',
            '.woocommerce-billing-fields',
            '.form-row',
            '.create-account'
        ];

        for (const selector of containersToCheck) {
            const container = field.closest(selector);
            if (!container) continue;

            if (window.getComputedStyle(container).display === 'none') {
                return true;
            }
        }

        return false;
    }

    hasWooCommerceValidationErrors() {
        const invalidFields = document.querySelectorAll(
            '.woocommerce-invalid, .woocommerce-invalid-required-field, .validate-required.woocommerce-invalid'
        );

        const visibleInvalidFields = Array.from(invalidFields).filter(field => {
            return !this.isInsideHiddenContainer(field);
        });

        const requiredFields = document.querySelectorAll(
            '.woocommerce-checkout .validate-required input, .woocommerce-checkout .validate-required select'
        );

        const hasEmptyRequired = Array.from(requiredFields).some(field => {
            if (field.type === 'hidden' || field.disabled) return false;

            if (this.isInsideHiddenContainer(field)) return false;

            return !field.value.trim();
        });

        return visibleInvalidFields.length > 0 || hasEmptyRequired;
    }

    createToken() {
        if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.installmentsWasSelected === 'function') {
            if (!CheckoutPage.installmentsWasSelected()) {
                CheckoutPage.setInstallmentsErrorState(true);
                CheckoutPage.scrollToCheckoutCustomContainer();
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
                this.cardForm.scrollToCardForm();
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

    handlePaymentMethodSelected() {
        console.log('[MP DEBUG] payment_method_selected event');
        const isSelected = this.isCheckoutCustomPaymentMethodSelected();
        console.log('[MP DEBUG] Custom checkout selected:', isSelected);

        if (!isSelected) {
            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }
            return;
        }

        // Initialize card form when payment method is selected
        const orderReviewForm = document.querySelector('form#order_review');
        if (orderReviewForm && !this.cardForm.formMounted) {
            console.log('[MP DEBUG] Initializing card form from payment_method_selected event');
            this.cardForm.initCardForm();
        }
    }

    handleOrderReviewSubmit(event) {
        if (this.isCheckoutCustomPaymentMethodSelected()) {
            event.preventDefault();
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
      console.log('[MP DEBUG] updated_checkout event');
      const isSelected = this.isCheckoutCustomPaymentMethodSelected();
      console.log('[MP DEBUG] Custom checkout selected:', isSelected);

      if (isSelected) {
        this.cardForm.createLoadSpinner();

        const newAmount = this.cardForm.getAmount();
        const currentAmount = this.cardForm.amount;
        const promises = [window.mpSuperTokenTriggerHandler?.loadSuperToken(newAmount)];

        if (this.cardForm.formMounted && newAmount !== currentAmount) {
          this.cardForm.form.unmount();
        }

        if (!this.cardForm.formMounted) {
          console.log('[MP DEBUG] Form not mounted, calling initCardForm');
          promises.push(this.cardForm.initCardForm());
        }

        Promise.all(promises)
          .finally(() => {
            setTimeout(() => this.cardForm.removeLoadSpinner(), this.REMOVE_LOAD_SPINNER_DELAY);
          });
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

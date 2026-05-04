/* eslint-disable no-unused-vars, @typescript-eslint/no-unused-vars */
/* globals wc_mercadopago_custom_event_handler_params, MP_DEVICE_SESSION_ID, jQuery, CheckoutPage, MPSuperTokenErrorCodes, sendMetric */
class MPEventHandler {
    REMOVE_LOAD_SPINNER_DELAY = 500;
    MAX_ORDER_PAY_RETRIES = 5;

    constructor(cardForm, threeDSHandler) {
        this.cardForm = cardForm;
        this.threeDSHandler = threeDSHandler;
        this.triggeredPaymentMethodSelectedEvent = false;
        this.mercado_pago_submit = false;
        this.hasToken = false;
        this.mpFormId = 'checkout';
        this.mpSuperTokenTriggerHandler = null;
        this.mpSuperTokenAuthenticator = null;
        this.mpSuperTokenPaymentMethods = null;
        this.mpSuperTokenMetrics = null;
        this.mpSuperTokenErrorHandler = null;
        this.loadSpinnerTimeout = null;
    }

    setSuperTokenDependencies({ triggerHandler, authenticator, paymentMethods, metrics, errorHandler }) {
        if (!triggerHandler || !authenticator || !paymentMethods || !metrics || !errorHandler) {
            if (typeof sendMetric === 'function') {
                sendMetric('MP_SUPER_TOKEN_DEPENDENCIES_NOT_SET', 'setSuperTokenDependencies', 'mp_super_token_init_error');
            }
            return;
        }

        this.mpSuperTokenTriggerHandler = triggerHandler;
        this.mpSuperTokenAuthenticator = authenticator;
        this.mpSuperTokenPaymentMethods = paymentMethods;
        this.mpSuperTokenMetrics = metrics;
        this.mpSuperTokenErrorHandler = errorHandler;
    }

    getSuperTokenDeps() {
        if ((!this.mpSuperTokenTriggerHandler || !this.mpSuperTokenAuthenticator) && window.mpSuperTokenTriggerHandler) {
            this.setSuperTokenDependencies({
                triggerHandler: window.mpSuperTokenTriggerHandler,
                authenticator: window.mpSuperTokenAuthenticator,
                paymentMethods: window.mpSuperTokenPaymentMethods,
                metrics: window.mpSuperTokenMetrics,
                errorHandler: window.mpSuperTokenErrorHandler,
            });
        }

        return {
            superTokenTriggerHandler: this.mpSuperTokenTriggerHandler,
            superTokenAuthenticator: this.mpSuperTokenAuthenticator,
            superTokenPaymentMethods: this.mpSuperTokenPaymentMethods,
            superTokenMetrics: this.mpSuperTokenMetrics,
            superTokenErrorHandler: this.mpSuperTokenErrorHandler,
        };
    }

    bindEvents() {
        jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', (event, wc_checkout_form) => this.mercadoPagoFormHandler(event, wc_checkout_form));
        jQuery('body').on('payment_method_selected', this.handlePaymentMethodSelected.bind(this));
        jQuery('form#order_review').submit(this.handleOrderReviewSubmit.bind(this));
        jQuery(document.body).on('checkout_error', this.handleCheckoutError.bind(this));
        jQuery(document).on('updated_checkout', this.handleUpdatedCheckout.bind(this));
        jQuery(document).ready(() => {
            this.threeDSHandler.set3dsStatusValidationListener();
            if (!wc_mercadopago_custom_event_handler_params.is_mobile) {
                this.initCardFormWhenReady();
            }
        });
    }

    initCardFormWhenReady() {
        const customContainer = document.querySelector('.mp-checkout-custom-container');
        const customRadio = document.querySelector('#payment_method_woo-mercado-pago-custom');
        const isOrderPayPage = this.isOrderPayPage();

        if (!customRadio?.checked) {
            return;
        }

        if (!customContainer) {
            if (isOrderPayPage) {
                this.scheduleOrderPayInitRetry();
            }
            return;
        }

        if (this.cardForm.formMounted) {
            return;
        }

        if (isOrderPayPage) {
            this.cardForm.createLoadSpinner();
            Promise.resolve(this.cardForm.initCardForm())
                .finally(() => this.cardForm.removeLoadSpinner());
            return;
        }

        if (customContainer.offsetParent === null) {
            return;
        }

        this.cardForm.initCardForm();
    }

    scheduleOrderPayInitRetry() {
        this.orderPayInitRetries = (this.orderPayInitRetries || 0) + 1;
        if (this.orderPayInitRetries > this.MAX_ORDER_PAY_RETRIES) {
            return;
        }

        setTimeout(() => this.initCardFormWhenReady(), 300);
    }

    isOrderPayPage() {
        const hasBodyClass = document.body?.classList?.contains('woocommerce-order-pay');
        const hasOrderReviewForm = !!document.querySelector('form#order_review');
        return hasBodyClass || hasOrderReviewForm;
    }

    getCheckoutForm() {
        return this.isOrderPayPage() ? jQuery('form#order_review') : jQuery('form.checkout');
    }

    showCheckoutClassicLoader() {
        this.getCheckoutForm()?.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    hideCheckoutClassicLoader() {
        this.getCheckoutForm()?.unblock();
    }

    mercadoPagoFormHandler(event, wc_checkout_form) {
        this.setMercadoPagoSessionId();
        const { superTokenPaymentMethods, superTokenMetrics } = this.getSuperTokenDeps();
        superTokenPaymentMethods?.hideSuperTokenError();

        if (this.mercado_pago_submit) {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'wallet_button') {
            return true;
        } else if (jQuery('#mp_checkout_type').val() === 'super_token') {
          superTokenMetrics?.registerClickOnPlaceOrderButton();

          if (this.hasWooCommerceValidationErrors()) {
            if (this.isOrderPayPage()) {
              document.getElementById('order_review').submit();
              return false;
            }

            return true;
          }

          this.handleWithSuperTokenSubmit(event, wc_checkout_form);

          // Return false to avoid the default behavior of the form submission
          return false;
        } else {
            jQuery('#mp_checkout_type').val('custom');

            if (this.hasWooCommerceValidationErrors() && this.isOrderPayPage()) {
                document.getElementById('order_review').submit();
                return false;
            }

            if (!this.hasToken) {
                this.setPayerIdentificationInfo();
                return this.createToken();
            }

            return false;
        }
    }

    async handleWithSuperTokenSubmit(event, wc_checkout_form) {
        const { superTokenTriggerHandler, superTokenAuthenticator, superTokenPaymentMethods, superTokenErrorHandler } = this.getSuperTokenDeps();

        try {
            event.preventDefault();
            this.showCheckoutClassicLoader();

            if (!superTokenPaymentMethods) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
            if (!superTokenAuthenticator) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);

            const activeMethod = superTokenPaymentMethods.getActivePaymentMethod();
            const isSuperTokenValid = activeMethod && superTokenPaymentMethods.isSelectedPaymentMethodValid();

            if (!activeMethod) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);
            if (!isSuperTokenValid) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);

            if (this.isOrderPayPage()) superTokenPaymentMethods.unmountCardForm();

            await superTokenPaymentMethods.updateSecurityCode();

            await superTokenAuthenticator.authorizePayment(activeMethod.token);

            this.mercado_pago_submit = true;
            superTokenAuthenticator?.setSuperTokenValidation(true);
            if (!this.isOrderPayPage()) {
                wc_checkout_form.$checkout_form.trigger('submit');
            } else {
                this.handle3dsPayOrderFormSubmission();
            }
        } catch(exception) {
            if (exception?.message === MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
                superTokenErrorHandler?.handleError(exception);
                this.cardForm.removeLoadSpinner();
                this.hideCheckoutClassicLoader();
                return;
            }

            superTokenTriggerHandler?.resetSuperTokenOnError();
            superTokenTriggerHandler?.setLastException(exception);
            superTokenAuthenticator?.setSuperTokenValidation(false);
        }
    }

    hasWooCommerceValidationErrors() {
        if (typeof window.hasWooCommerceValidationErrors === 'function') {
            try {
                return window.hasWooCommerceValidationErrors();
            } catch (error) {
                if (typeof sendMetric === 'function') {
                    sendMetric(
                        'MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK',
                        error?.message || error?.name || 'threw an error',
                        'mp_custom_checkout_validation_cdn_fallback'
                    );
                }
                return false;
            }
        }
        if (typeof sendMetric === 'function') {
            sendMetric(
                'MP_CUSTOM_CHECKOUT_VALIDATION_CDN_FALLBACK',
                'hasWooCommerceValidationErrors not available',
                'mp_custom_checkout_validation_cdn_fallback'
            );
        }
        return false;
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
      if (!this.isCheckoutCustomPaymentMethodSelected()) {
        if (this.cardForm.formMounted) {
          this.cardForm.form.unmount();
        }

        clearTimeout(this.loadSpinnerTimeout);

        const { superTokenTriggerHandler, superTokenPaymentMethods } = this.getSuperTokenDeps();
        if (superTokenTriggerHandler?.isSuperTokenPaymentMethodsLoaded()) {
          superTokenPaymentMethods?.getPaymentMethodsListElement()?.style.setProperty('display', 'none', 'important');
        } else if (superTokenTriggerHandler?.isFetchingPaymentMethods) {
          superTokenTriggerHandler.cancelLoad();
        }

        return;
      }

      if (this.isOrderPayPage()) {
        return this.onSelectCheckoutCustomInOrderPayPage();
      }
    }

    handleOrderReviewSubmit(event) {
        if (this.isCheckoutCustomPaymentMethodSelected()) {
            // Não deve ocorrer o event.preventDefault antes do método mercadoPagoFormHandler ser chamado,
            // pois em casos com mercado_pago_submit=true, por exemplo,
            // iremos utilizar a submissão tradicional do formulário,
            // e isso impediria o comportamento esperado.
            return this.mercadoPagoFormHandler(event);
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
        const { superTokenTriggerHandler } = this.getSuperTokenDeps();
        superTokenTriggerHandler?.resetSuperTokenOnError();
    }

    handleUpdatedCheckout() {
      if (this.isCheckoutCustomPaymentMethodSelected()) {
        clearTimeout(this.loadSpinnerTimeout);
        this.cardForm.createLoadSpinner();

        const newAmount = this.cardForm.getAmount();
        const currentAmount = this.cardForm.amount;
        const promises = [];
        const { superTokenTriggerHandler } = this.getSuperTokenDeps();

        if (superTokenTriggerHandler) {
          promises.push(superTokenTriggerHandler.loadSuperToken(newAmount));
        }

        const isCardFormDetached = this.cardForm.formMounted
          && ['form-checkout__cardNumber-container', 'form-checkout__expirationDate-container', 'form-checkout__securityCode-container']
            .every(containerId => {
              const container = document.getElementById(containerId);
              return !!container && !container.querySelector('iframe');
            });

        if (isCardFormDetached) {
          this.cardForm.form.unmount();
          this.cardForm.formMounted = false;
        } else if (this.cardForm.formMounted && newAmount !== currentAmount) {
          this.cardForm.form.unmount();
        }

        if (!this.cardForm.formMounted) promises.push(this.cardForm.initCardForm());

        Promise.all(promises)
          .finally(() => {
            this.loadSpinnerTimeout = setTimeout(() => this.cardForm.removeLoadSpinner(), this.REMOVE_LOAD_SPINNER_DELAY);
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

                    const errorMessageElement = document.querySelector('#order_review .woocommerce-error');
                    if (errorMessageElement) {
                        errorMessageElement.scrollIntoView({ behavior: 'smooth' });
                    }

                    this.cardForm.removeBlockOverlay();
                    this.cardForm.removeLoadSpinner();
                    this.cardForm.form.unmount();
                    this.cardForm.initCardForm();
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

    onSelectCheckoutCustomInOrderPayPage() {
        const MAX_RETRIES = 100;
        let retryCount = 0;

        this.cardForm.createLoadSpinner();

        const waitSuperTokenClassesInterval = setInterval(() => {
            const { superTokenTriggerHandler } = this.getSuperTokenDeps();
            if (++retryCount < MAX_RETRIES && !superTokenTriggerHandler) return;

            clearInterval(waitSuperTokenClassesInterval);

            const newAmount = this.cardForm.getAmount();
            const promises = [];

            if (superTokenTriggerHandler) {
                promises.push(superTokenTriggerHandler.loadSuperToken(newAmount));
            }
            if (!this.cardForm.formMounted) promises.push(this.cardForm.initCardForm());

            return Promise.all(promises)
                .finally(() => {
                    setTimeout(() => this.cardForm.removeLoadSpinner(), this.REMOVE_LOAD_SPINNER_DELAY);
                });
        }, 100);
    }
}

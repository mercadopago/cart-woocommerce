/* globals wc_mercadopago_custom_checkout_params, wc_mercadopago_custom_card_form_params, MercadoPago, CheckoutPage, jQuery, MPCheckoutFieldsDispatcher */
// eslint-disable-next-line no-unused-vars
class MPCardForm {
    TIMEOUT_TO_WAIT_INIT_CARD_FORM = 10000;

    constructor() {
        this.form = null;
        this.formMounted = false;
        this.mpFormId = 'checkout';
        this.amount = null;
        this.onReadyDebounce = null;
        this.fields = null;
        this.initCardFormTimeoutReference = null;
        this.isLoading = false;
    }

    async initCardForm(amount = this.getAmount()) {
        this.amount = amount;
        this.cardNumberFilledValidator = false;

        if (!window.mpSdkInstance) {
            const mp = new MercadoPago(wc_mercadopago_custom_checkout_params.public_key, {
                locale: wc_mercadopago_custom_checkout_params.locale,
            });

            window.mpSdkInstance = mp;
        }

        return new Promise((resolve, reject) => {
            this.createTimeoutToWaitInitCardForm(reject);

            this.form = window.mpSdkInstance.cardForm({
                amount: amount,
                iframe: true,
                form: this.getCardFormConfig(),
                callbacks: this.getCardFormCallbacks(resolve, reject)
            })
        }).then(() => {
            this.clearTimeoutToWaitInitCardForm();
            this.sendMetric('MP_CARDFORM_SUCCESS', 'Security fields loaded', 'mp_custom_checkout_security_fields_client');
            CheckoutPage.verifyCardholderNameOnFocus();
        })
        .catch((error) => {
            this.clearTimeoutToWaitInitCardForm();
            const parsedError = this.handleCardFormErrors(error);
            this.sendMetric('MP_CARDFORM_ERROR', parsedError, 'mp_custom_checkout_security_fields_client');
            console.error('Mercado Pago cardForm error: ', parsedError);
        });
    }

    createTimeoutToWaitInitCardForm(reject = () => {}) {
      this.initCardFormTimeoutReference = setTimeout(() => {
        this.removeLoadSpinner();
        reject(new Error('INIT_CARD_FORM_TIMEOUT'));
      }, this.TIMEOUT_TO_WAIT_INIT_CARD_FORM);
    }

    clearTimeoutToWaitInitCardForm() {
      clearTimeout(this.initCardFormTimeoutReference);
    }

    getCardFormConfig() {
        const baseStyle = {
            fontSize: '16px',
            height: '48px',
            padding: '14px',
            textAlign: 'left',
            fontFamily: 'Inter ',
            fontWeight: '400',
            placeholderColor: ' #0000008C',
        };

        const baseCustomFonts = {
            src: 'https://fonts.googleapis.com/css2?family=Inter'
        };

        return {
            id: this.mpFormId,
            cardNumber: {
                id: 'form-checkout__cardNumber-container',
                placeholder: '1234 1234 1234 1234',
                style: baseStyle,
                customFonts: [baseCustomFonts]
            },
            cardholderName: {
                id: 'form-checkout__cardholderName',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['cardholderName'],
            },
            cardExpirationDate: {
                id: 'form-checkout__expirationDate-container',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['cardExpirationDate'],
                mode: 'short',
                style: baseStyle,
                customFonts: [baseCustomFonts]
            },
            securityCode: {
                id: 'form-checkout__securityCode-container',
                placeholder: wc_mercadopago_custom_card_form_params.security_code_placeholder_text_3_digits,
                style: baseStyle,
                customFonts: [baseCustomFonts]
            },
            identificationType: {
                id: 'form-checkout__identificationType',
            },
            identificationNumber: {
                id: 'form-checkout__identificationNumber',
            },
            issuer: {
                id: 'form-checkout__issuer',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['issuer'],
            },
            installments: {
                id: 'form-checkout__installments',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['installments'],
            },
        };
    }

    getCardFormCallbacks(resolve, reject) {
        return {
            onReady: (fields) => {
                this.fields = fields;
                this.setupSecureFieldsStylesAndAddListeners();
                resolve();
            },
            onFormMounted: (error) => {
                this.formMounted = true;
                resolve();

                if (error) {
                    console.log('Callback to handle the error: creating the CardForm', error);
                    return;
                }
            },
            onFormUnmounted: (error) => {
                this.formMounted = false;
                CheckoutPage.clearInputs();
                resolve();

                if (error) {
                    console.log('Callback to handle the error: unmounting the CardForm', error);
                    return;
                }
            },
            onInstallmentsReceived: (error, installments) => {
                if (error) {
                    const messages = wc_mercadopago_custom_checkout_params.error_messages;
                    this.addErrorAlert(messages.installments[error.message] ?? messages.default);
                    console.warn('Installments handling error: ', error);
                    return;
                }

                CheckoutPage.setChangeEventOnInstallments(installments);
            },
            onCardTokenReceived: (error) => {
                if (error) {
                    console.error('Token handling error: ', error);
                    return;
                }
            },
            onPaymentMethodsReceived: (error, paymentMethods) => {
                if (error) {
                    console.error('Payment methods handling error: ', error);
                    return;
                }
                try {
                    if (paymentMethods) {
                        CheckoutPage.clearInputs();
                        const paymentMethod = paymentMethods[0];

                        CheckoutPage.setValueOn('paymentMethodId', paymentMethod.id);
                        CheckoutPage.setCvvConfig(paymentMethod.settings[0].security_code);
                        CheckoutPage.setImageCard(paymentMethod.secure_thumbnail || paymentMethod.thumbnail);
                        const additionalInfo = CheckoutPage.loadAdditionalInfo(paymentMethod.additional_info_needed);
                        CheckoutPage.additionalInfoHandler(additionalInfo);
                        CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'remove', 'mp-error');
                        CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'none');
                        CheckoutPage.setDisplayOfError('mpCardholderNameInputLabel', 'remove', 'mp-label-error');
                        CheckoutPage.setDisplayOfError('mpDocumentInputLabel', 'remove', 'mp-label-error');
                        CheckoutPage.setDisplayOfInputHelper('mp-card-holder-name', 'none');
                        CheckoutPage.setDisplayOfInputHelperInfo('mp-card-holder-name', 'flex');
                        CheckoutPage.shouldEnableInstallmentsComponent(paymentMethod.payment_type_id);
                    } else {
                        CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                        CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                    }
                } catch (error) {
                    if (error) {
                        console.error('Payment methods handling error: ', error);
                        return;
                    }
                    CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                    CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                }
            },
            onSubmit: (event) => {
                event.preventDefault();
            },
            onValidityChange: (error, field) => {
                if (field === 'cardNumber') {
                    this.cardNumberFilledValidator = true;
                }

                if (error) {
                    let helper_message = CheckoutPage.getHelperMessage(field);
                    let message = wc_mercadopago_custom_checkout_params.input_helper_message[field][error[0].code];

                    if (message) {
                        helper_message.innerHTML = message;
                    } else {
                        helper_message.innerHTML = wc_mercadopago_custom_checkout_params.input_helper_message[field]['invalid_length'];
                    }

                    if (field === 'cardNumber') {
                        if (error[0].code !== 'invalid_length') {
                            CheckoutPage.setBackground('fcCardNumberContainer', 'no-repeat #fff');
                            CheckoutPage.removeAdditionFields();
                            CheckoutPage.clearInputs();
                        }
                        CheckoutPage.setDisplayOfInputHelperInfo('mp-card-holder-name', 'flex');
                    }

                    let containerField = CheckoutPage.findContainerField(field);
                    CheckoutPage.setDisplayOfError(containerField, 'add', 'mp-error');

                    if (field === 'cardholderName') {
                        CheckoutPage.verifyCardholderName();
                    }

                    return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'flex');
                }

                if (field === 'cardholderName' && !CheckoutPage.verifyCardholderName()) {
                   return;
                }

                let containerField = CheckoutPage.findContainerField(field);
                CheckoutPage.setDisplayOfError(containerField, 'removed', 'mp-error');

                return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'none');
            },
            onError: (errors) => {
                CheckoutPage.verifyCardholderName();
                errors.forEach((error) => {
                    this.removeBlockOverlay();

                    if (error.message.includes('timed out')) {
                        return reject(error);
                    } else if (error.message.includes('cardNumber')) {
                        CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                    } else if (error.message.includes('cardholderName')) {
                        CheckoutPage.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
                        CheckoutPage.setDisplayOfInputHelperInfo('mp-card-holder-name', 'none');
                        CheckoutPage.setDisplayOfError('mpCardholderNameInputLabel', 'add', 'mp-label-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-card-holder-name', 'flex');
                    } else if (error.message.includes('expirationMonth') || error.message.includes('expirationYear')) {
                        CheckoutPage.setDisplayOfError('fcCardExpirationDateContainer', 'add', 'mp-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-expiration-date', 'flex');
                    } else if (error.message.includes('securityCode')) {
                        if (error.message.includes('should be a number') || error.message.includes('should be of length')) {
                            CheckoutPage.setDisplayOfInputHelperMessage('mp-security-code', wc_mercadopago_custom_checkout_params.input_helper_message.securityCode.invalid_length);
                        } else {
                            CheckoutPage.setDisplayOfInputHelperMessage('mp-security-code', wc_mercadopago_custom_checkout_params.input_helper_message.securityCode.invalid_type);
                        }
                        CheckoutPage.setDisplayOfError('fcSecurityNumberContainer', 'add', 'mp-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-security-code', 'flex');
                    } else if (error.message.includes('identificationNumber')) {
                        CheckoutPage.setDisplayOfError('fcIdentificationNumberContainer', 'add', 'mp-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-doc-number', 'flex');
                    } else {
                        return reject(error);
                    }
                });
            },
        };
    }

    scrollToCardForm() {
      const cardFormContainer = document.querySelector('#mp-checkout-custom-container.mp-checkout-container');
      if (!cardFormContainer) return;

      cardFormContainer.scrollIntoView({ behavior: 'smooth' });
    }

    getAmount() {
        const amount = parseFloat(document.getElementById('mp-amount').value.replace(',', '.'));
        return String(amount);
    }

    handleCardFormErrors(cardFormErrors) {
        if (cardFormErrors.length) {
            const errors = [];
            cardFormErrors.forEach((e) => {
                errors.push(e.description || e.message);
            });

            return errors.join(',');
        }

        return cardFormErrors.description || cardFormErrors.message;
    }

    sendMetric(action, label, target) {
        if (typeof window.mPmetrics !== 'undefined') {
            window.mPmetrics.push({
                action: action,
                label: label,
                target: target,
            });
        }
    }

    isClassicCheckout() {
        return !!document.querySelector('.payment_method_woo-mercado-pago-custom');
    }

    startLoadingOnClassicCheckout() {
        jQuery('form.checkout')?.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    stopLoadingOnClassicCheckout() {
        jQuery('form.checkout')?.unblock();
    }

    createLoadSpinner() {
        if (this.isLoading) return;

        const customContainer = document.querySelector('#mp-checkout-custom-container.mp-checkout-container');
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        document.dispatchEvent(new CustomEvent('mp_super_token_loading_start', { detail: { dateNowInMilliseconds: Date.now() } }));

        this.isLoading = true;
        customContainer?.classList.add('mp-hidden');
        customContainer?.classList.add('mp-display-none');
        loadSpinner?.classList.remove('mp-hidden');
        loadSpinner?.classList.remove('mp-display-none');
    }

    removeLoadSpinner() {
        if (!this.isLoading) return;

        const customContainer = document.querySelector('#mp-checkout-custom-container.mp-checkout-container');
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        document.dispatchEvent(new CustomEvent('mp_super_token_loading_end', { detail: { dateNowInMilliseconds: Date.now() } }));

        this.isLoading = false;
        const onTransitionEnd = () => {
          loadSpinner?.classList.add('mp-display-none');
          customContainer?.classList.remove('mp-hidden');
          customContainer?.classList.remove('mp-display-none');

          loadSpinner.removeEventListener('transitionend', onTransitionEnd);
        };

        loadSpinner?.addEventListener('transitionend', onTransitionEnd);
        loadSpinner?.classList.add('mp-hidden');
    }

    removeBlockOverlay() {
        if (jQuery('form#order_review').length > 0) {
            jQuery('.blockOverlay').css('display', 'none');
        }
    }

    addErrorAlert(message) {
        this.removeElementsByClass('woocommerce-NoticeGroup-checkout');
        jQuery(window.mpCheckoutForm).prepend(`
            <div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                <ul class="woocommerce-error" role="alert">
                    <li>${message}<li>
                </ul>
            </div>
        `);
        window.scrollTo(0, 0);
    }

    removeElementsByClass(className) {
        const elements = document.getElementsByClassName(className);
        while (elements.length > 0) {
            elements[0].parentNode.removeChild(elements[0]);
        }
    }

    setupSecureFieldsStylesAndAddListeners() {
        if (!this.fields) {
            return;
        }

        const secureFieldsConfiguration = [
            {
                field: this.fields.cardNumber,
                fieldName: 'cardNumber',
                containerId: 'form-checkout__cardNumber-container',
                focusEventName: 'card_number_focused',
                blurEventName: 'card_number_filled',
                validator: () => this.cardNumberFilledValidator
            },
            {
                field: this.fields.expirationDate,
                containerId: 'form-checkout__expirationDate-container'
            },
            {
                field: this.fields.securityCode,
                containerId: 'form-checkout__securityCode-container'
            }
        ];

        for (const config of secureFieldsConfiguration) {
            if (!config.field || typeof config.field.on !== 'function') {
              continue;
            }

            // sdk listener functions will only run once, so we need to do both things, add the css class and dispatch the events
            config.field.on('focus', () => {
                this.addOrRemoveCssClass(
                  config.containerId,
                  'mp-checkout-custom-card-form-focus',
                  'add'
                );

                if(config.focusEventName) {
                  MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
                    null,
                    "focus",
                    config.focusEventName,
                    {
                      onlyDispatch: true
                    }
                  );
                }
            });

            config.field.on('blur', () => {
                let isValid = false;
                this.addOrRemoveCssClass(
                  config.containerId,
                  'mp-checkout-custom-card-form-focus'
                );

                if(config.validator) {
                  if (typeof config.validator === 'function') {
                    isValid = config.validator();
                  } else {
                    isValid = config.validator;
                  }
                }

                if(config.blurEventName && isValid) {
                  MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
                    null,
                    "blur",
                    config.blurEventName,
                    {
                      onlyDispatch: true
                    }
                  );

                  if(config.fieldName) {
                    this.updateFieldValidator(config.fieldName);
                  }
                }
            });
        }
    }

    addOrRemoveCssClass(element, className, action = 'remove') {
      const input = document.getElementById(element);
      input?.classList[action === 'add' ? 'add' : 'remove'](className);
    }

    updateFieldValidator(fieldName) {
      if(fieldName !== 'cardNumber') return;
      this.cardNumberFilledValidator = false;
    }
}

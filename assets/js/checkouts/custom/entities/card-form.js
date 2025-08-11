/* globals wc_mercadopago_custom_checkout_params, wc_mercadopago_custom_card_form_params, MercadoPago, CheckoutPage, jQuery */
// eslint-disable-next-line no-unused-vars
class MPCardForm {
    TIMEOUT_TO_WAIT_CHECKOUT_AMOUNT_LOAD = 2500;
    
    constructor() {
        this.form = null;
        this.formMounted = false;
        this.mpFormId = 'checkout';
    }

    initCardForm(amount = this.getAmount()) {
        if (this.shouldCreateLoadSpinner()) {
            this.createLoadSpinner();
        }

        if (!window.mpSdkInstance) {
            const mp = new MercadoPago(wc_mercadopago_custom_checkout_params.public_key, {
                locale: wc_mercadopago_custom_checkout_params.locale,
            });

            window.mpSdkInstance = mp;
        }

        return new Promise((resolve, reject) => {
            this.form = window.mpSdkInstance.cardForm({
                amount: amount,
                iframe: true,
                form: this.getCardFormConfig(),
                callbacks: this.getCardFormCallbacks(resolve, reject)
            })
        }).then(() => {
            this.sendMetric('MP_CARDFORM_SUCCESS', 'Security fields loaded', 'mp_custom_checkout_security_fields_client');
        })
        .catch((error) => {
            const parsedError = this.handleCardFormErrors(error);
            this.sendMetric('MP_CARDFORM_ERROR', parsedError, 'mp_custom_checkout_security_fields_client');
            this.removeLoadSpinner();
            console.error('Mercado Pago cardForm error: ', parsedError);
        });
    }

    getCardFormConfig() {
        return {
            id: this.mpFormId,
            cardNumber: {
                id: 'form-checkout__cardNumber-container',
                placeholder: '0000 0000 0000 0000',
                style: {
                    'font-size': '16px',
                    height: '48px',
                    padding: '14px',
                },
            },
            cardholderName: {
                id: 'form-checkout__cardholderName',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['cardholderName'],
            },
            cardExpirationDate: {
                id: 'form-checkout__expirationDate-container',
                placeholder: wc_mercadopago_custom_checkout_params.placeholders['cardExpirationDate'],
                mode: 'short',
                style: {
                    'font-size': '16px',
                    height: '48px',
                    padding: '14px',
                },
            },
            securityCode: {
                id: 'form-checkout__securityCode-container',
                placeholder: wc_mercadopago_custom_card_form_params.security_code_placeholder_text_3_digits,
                style: {
                    'font-size': '16px',
                    height: '48px',
                    padding: '14px',
                },
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
            onReady: () => {
                if (this.shouldCreateLoadSpinner()) {
                    setTimeout(() => {
                        window.mpSuperTokenTriggerHandler?.loadSuperToken();
                        this.removeLoadSpinner();
                        resolve();
                    }, this.TIMEOUT_TO_WAIT_CHECKOUT_AMOUNT_LOAD);

                    return;
                }

                setTimeout(() => {
                    window.mpSuperTokenTriggerHandler?.loadSuperToken();
                    resolve();
                }, 200);
            },
            onFormMounted: (error) => {
                this.formMounted = true;

                if (error) {
                    console.log('Callback to handle the error: creating the CardForm', error);
                    return;
                }
            },
            onFormUnmounted: (error) => {
                this.formMounted = false;
                CheckoutPage.clearInputs();

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
                if (error) {
                    this.removeLoadSpinner();
                    
                    let helper_message = CheckoutPage.getHelperMessage(field);
                    let message = wc_mercadopago_custom_checkout_params.input_helper_message[field][error[0].code];

                    if (message) {
                        helper_message.innerHTML = message;
                    } else {
                        helper_message.innerHTML =
                            wc_mercadopago_custom_checkout_params.input_helper_message[field]['invalid_length'];
                    }

                    if (field === 'cardNumber') {
                        if (error[0].code !== 'invalid_length') {
                            CheckoutPage.setBackground('fcCardNumberContainer', 'no-repeat #fff');
                            CheckoutPage.removeAdditionFields();
                            CheckoutPage.clearInputs();
                        }
                    }

                    let containerField = CheckoutPage.findContainerField(field);
                    CheckoutPage.setDisplayOfError(containerField, 'add', 'mp-error');

                    return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'flex');
                }

                let containerField = CheckoutPage.findContainerField(field);
                CheckoutPage.setDisplayOfError(containerField, 'removed', 'mp-error');

                return CheckoutPage.setDisplayOfInputHelper(CheckoutPage.inputHelperName(field), 'none');
            },
            onError: (errors) => {
                this.removeLoadSpinner();
                CheckoutPage.verifyCardholderName();
                CheckoutPage.verifyInstallmentsContainer();
                
                errors.forEach((error) => {
                    this.removeBlockOverlay();

                    if (error.message.includes('timed out')) {
                        return reject(error);
                    } else if (error.message.includes('cardNumber')) {
                        CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                        return CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                    } else if (error.message.includes('cardholderName')) {
                        CheckoutPage.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
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

    isSuperTokenPaymentMethodsLoaded() {
        return window.mpSuperTokenTriggerHandler?.isSuperTokenPaymentMethodsLoaded() || false;
    }

    isClassicCheckout() {
        return !!document.querySelector('.payment_method_woo-mercado-pago-custom');
    }

    isSuperTokenPaymentMethodsListRendered() {
        return !!document.querySelector('.mp-super-token-payment-methods-list');
    }

    shouldCreateLoadSpinner() {
        return !this.isSuperTokenPaymentMethodsListRendered()
            && !this.isClassicCheckout()
            && !this.isSuperTokenPaymentMethodsLoaded();
    }

    createLoadSpinner() {        
        const customContainer = document.querySelector('.mp-checkout-custom-container');
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        if (customContainer) {
            customContainer.style.display = 'none';
        }

        if (loadSpinner) {
            loadSpinner.style.display = 'flex';
        }
    }

    removeLoadSpinner() {
        const customContainer = document.querySelector('.mp-checkout-custom-container')
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        if (customContainer) {
            customContainer.style.display = 'block';
        }

        if (loadSpinner) {
            loadSpinner.style.display = 'none';
        }
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
}

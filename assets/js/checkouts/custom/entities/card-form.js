/* globals wc_mercadopago_custom_checkout_params, wc_mercadopago_custom_card_form_params, MercadoPago, CheckoutPage, jQuery, MPCheckoutFieldsDispatcher, sendMetric, MPCardFormErrorCodes */
// eslint-disable-next-line no-unused-vars, @typescript-eslint/no-unused-vars
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
        this.dispatcherMissingReported = false;
        this.hasFiredCheckoutOpenedEvent = false;
        this.hasReportedAmountTrackingDropped = false;
        this.lastTrackedAmount = null;
        this.cardBinIsValid = true;
        this.cardNumberValidity = null;
        this.lastVerdictBin = null;
        this.currentBin = null;

        this.sendMelidataTimeToLoadMetric();
    }

    async initCardForm(amount = this.getAmount()) {
        this.amount = amount;
        this.cardNumberFilledValidator = false;

        this.dispatchCheckoutAmountEvent(amount);

        if (!window.mpSdkInstance) {
            const mp = new MercadoPago(wc_mercadopago_custom_checkout_params.public_key, {
                locale: wc_mercadopago_custom_checkout_params.locale,
            });

            window.mpSdkInstance = mp;
            document.dispatchEvent(new CustomEvent('mp_sdk_instance_ready'));
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
            CheckoutPage.clearDocumentLabelErrorOnInput();
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
        reject(new Error(MPCardFormErrorCodes.INIT_CARD_FORM_TIMEOUT));
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
                this.cardBinIsValid = true;
                this.cardNumberValidity = null;
                this.lastVerdictBin = null;
                this.currentBin = null;
                this.setupSecureFieldsStylesAndAddListeners();
                resolve();
            },
            onFormMounted: (error) => {
                this.formMounted = true;
                document.dispatchEvent(new CustomEvent('mp_card_form_mounted'));
                resolve();

                if (error) {
                    this.sendMetric('MP_CARDFORM_MOUNT_ERROR', error?.message || 'unknown', 'mp_custom_checkout_security_fields_client');
                }
            },
            onFormUnmounted: (error) => {
                this.formMounted = false;
                CheckoutPage.clearInputs();
                resolve();

                if (error) {
                    this.sendMetric('MP_CARDFORM_UNMOUNT_ERROR', error?.message || 'unknown', 'mp_custom_checkout_security_fields_client');
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
            onBinChange: (bin) => {
                // onBinChange fires ~200ms before onPaymentMethodsReceived, with the raw BIN string.
                // On a BIN change a fresh verdict is on the way, so clear the previous (now stale) card-number error and residual payment method id optimistically instead of waiting for it — mirrors the optimistic reset in onReady. Editing within the same BIN (or Super Token, which owns the shared field) keeps them. See docs/agent/traps.md.
                const value = typeof bin === 'string' ? bin : (bin && bin.bin) || '';
                this.currentBin = value;
                if (value && value !== this.lastVerdictBin) {
                    this.cardBinIsValid = true;
                    CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'remove', 'mp-error');
                    CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'none');
                    if (document.querySelector('#mp_checkout_type')?.value !== 'super_token') {
                        CheckoutPage.setValueOn('paymentMethodId', '');
                    }
                }
            },
            onPaymentMethodsReceived: (error, paymentMethods) => {
                // Record the BIN this verdict is for, so onBinChange can tell a real BIN change from an edit within the same BIN (which must keep the current error state — see traps.md).
                this.lastVerdictBin = this.currentBin;
                if (error) {
                    console.error('Payment methods handling error: ', error);
                    this.cardBinIsValid = false;
                    CheckoutPage.clearCardState();
                    const helperMsg = CheckoutPage.getHelperMessage('cardNumber');
                    if (helperMsg) {
                        const isInvalidBin = error?.message?.includes(MPCardFormErrorCodes.NO_PAYMENT_METHODS_FOUND)
                            || error?.toString?.().includes(MPCardFormErrorCodes.NO_PAYMENT_METHODS_FOUND);
                        helperMsg.innerHTML = isInvalidBin
                            ? wc_mercadopago_custom_checkout_params.input_helper_message?.cardNumber?.invalid_value
                                ?? wc_mercadopago_custom_checkout_params.input_helper_message?.cardNumber?.invalid_length
                                ?? ''
                            : wc_mercadopago_custom_checkout_params.input_helper_message?.cardNumber?.invalid_length
                                ?? '';
                    }
                    CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                    CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                    return;
                }
                try {
                    if (paymentMethods) {
                        this.cardBinIsValid = true;
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
                        this.cardBinIsValid = false;
                        CheckoutPage.clearCardState();
                        CheckoutPage.setDisplayOfError('fcCardNumberContainer', 'add', 'mp-error');
                        CheckoutPage.setDisplayOfInputHelper('mp-card-number', 'flex');
                    }
                } catch (err) {
                    if (err) {
                        console.error('Payment methods handling error: ', err);
                        return;
                    }
                    this.cardBinIsValid = false;
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
                    if (error && !error[0]) {
                        if (typeof sendMetric === 'function') {
                            sendMetric('MP_CUSTOM_CHECKOUT_CARD_VALIDATION_BLOCKED', 'unexpected_error_format',
                                'mp_custom_card_validation', { reason: 'unexpected_error_format' });
                        }
                        this.cardNumberValidity = null;
                        return;
                    }
                    this.cardNumberValidity = error ? error[0].code : null;
                }

                if (error) {
                    let helper_message = CheckoutPage.getHelperMessage(field);
                    let message = wc_mercadopago_custom_checkout_params.input_helper_message[field][error[0].code];

                    if (helper_message) {
                        if (message) {
                            helper_message.innerHTML = message;
                        } else {
                            helper_message.innerHTML = wc_mercadopago_custom_checkout_params.input_helper_message[field]['invalid_length'];
                        }
                    }

                    if (field === 'cardNumber') {
                        if (error[0].code !== 'invalid_length') {
                            const isSuperToken = document.querySelector('#mp_checkout_type')?.value === 'super_token';
                            CheckoutPage.setBackground('fcCardNumberContainer', 'no-repeat #fff');
                            CheckoutPage.removeAdditionFields(!isSuperToken);
                            CheckoutPage.clearInputs();
                        }
                        if (!CheckoutPage.cardholderNameHasError()) {
                            CheckoutPage.setDisplayOfInputHelperInfo('mp-card-holder-name', 'flex');
                        }
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

                if (field === 'cardNumber' && !this.cardBinIsValid) {
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

                    if (error.message.includes(MPCardFormErrorCodes.TIMED_OUT)) {
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
                        if (error.message.includes(MPCardFormErrorCodes.SECURITY_CODE_INVALID_NUMBER) || error.message.includes(MPCardFormErrorCodes.SECURITY_CODE_INVALID_LENGTH)) {
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
      const cardFormContainer = document.querySelector('#mp-checkout-custom-root.mp-checkout-container');
      if (!cardFormContainer) return;

      cardFormContainer.scrollIntoView({ behavior: 'smooth' });
    }

    getCardValidationReason() {
        if (this.cardNumberValidity === 'invalid_length') {
            return 'invalid_length';
        }
        if (this.cardNumberValidity === 'invalid_type') {
            return 'empty_field';
        }
        if (this.cardBinIsValid === false) {
            return 'invalid_bin';
        }
        if (!this.cardNumberValidity) {
            return 'empty_field';
        }
        return this.cardNumberValidity;
    }

    getAmount() {
        const amountElement = document.getElementById('mp-amount');

        if (!amountElement) {
            return this.amount;
        }

        const amount = parseFloat(amountElement.value.replace(',', '.'));
        return String(amount);
    }

    formatTrackingAmount(amount = '') {
        const rawValue = String(amount ?? '').replace(/[^\d.,]/g, '');
        if (!rawValue) return null;

        const lastCommaIndex = rawValue.lastIndexOf(',');
        const lastDotIndex = rawValue.lastIndexOf('.');

        const isEuropean = lastCommaIndex > lastDotIndex;
        const normalizedValue = rawValue.replace(/[.,]/g, (match) => {
            if (isEuropean) {
                return match === ',' ? '.' : '';
            }
            return match === '.' ? '.' : '';
        });

        const value = parseFloat(normalizedValue);
        return isNaN(value) ? null : value.toFixed(2);
    }

    reportAmountTrackingDroppedOnce(eventOrigin, error) {
        if (this.hasReportedAmountTrackingDropped) return;
        this.hasReportedAmountTrackingDropped = true;
        this.sendMetric(
            'true',
            `melidataReady rejected on ${eventOrigin}: ${error?.message || 'unknown error'}`,
            'mp_checkout_amount_tracking_dropped'
        );
    }

    emitWhenMelidataReady(eventName, detail) {
        const melidataReady = window.melidataReady;

        if (!melidataReady || typeof melidataReady.then !== 'function') {
            this.reportAmountTrackingDroppedOnce(eventName, new Error('melidataReady is not a Promise'));
            return;
        }

        melidataReady
            .then(() => document.dispatchEvent(new CustomEvent(eventName, { detail })))
            .catch((error) => this.reportAmountTrackingDroppedOnce(eventName, error));
    }

    dispatchCheckoutAmountEvent(rawAmount) {
        const amount = this.formatTrackingAmount(rawAmount);
        const previousAmount = this.lastTrackedAmount;

        if (!this.hasFiredCheckoutOpenedEvent) {
            this.hasFiredCheckoutOpenedEvent = true;
            this.lastTrackedAmount = amount;
            this.emitWhenMelidataReady('mp_checkout_opened', { amount });
        } else if (previousAmount && previousAmount !== amount) {
            this.lastTrackedAmount = amount;
            this.emitWhenMelidataReady('mp_amount_changed', { amount, oldAmount: previousAmount });
        }
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
        if (typeof window.sendMetric === 'function') {
            window.sendMetric(action, label, target);
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

    getMelidataReadyPromise() {
        if (!window.melidataReady) {
            return Promise.reject(new Error('melidataReady is undefined'));
        }
        return window.melidataReady;
    }

    createLoadSpinner() {
        if (this.isLoading) return;

        const customContainer = document.querySelector('#mp-checkout-custom-root.mp-checkout-container');
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        this.isLoading = true;
        customContainer?.classList.add('mp-hidden');
        customContainer?.classList.add('mp-display-none');
        loadSpinner?.classList.remove('mp-hidden');
        loadSpinner?.classList.remove('mp-display-none');

        const dateNowInMilliseconds = Date.now();
        const melidataWasReady = !!window.melidata;

        this.getMelidataReadyPromise()
          .then(() => {
            if (!melidataWasReady) {
              const durationInSeconds = Math.round((Date.now() - dateNowInMilliseconds) / 10) / 100;
              sendMetric(durationInSeconds, 'time in seconds waiting for melidata client at loading start', 'mp_custom_checkout_melidata_load_start_delay');
            }
            document.dispatchEvent(new CustomEvent('mp_super_token_loading_start', { detail: { dateNowInMilliseconds } }));
          })
          .catch((error) => {
            sendMetric('MP_MELIDATA_CLIENT_UNDEFINED', `Loading started but melidata client failed to load: ${error?.message || 'unknown error'}`, 'mp_custom_checkout_melidata_client_undefined');
          });
    }

    removeLoadSpinner() {
        const loadingElement = document.querySelector('.mp-checkout-custom-load');
        const loadingIsVisible = loadingElement && window.getComputedStyle(loadingElement).display !== 'none';

        if (!this.isLoading && !loadingIsVisible) return;

        const customContainer = document.querySelector('#mp-checkout-custom-root.mp-checkout-container');
        const loadSpinner = document.querySelector('.mp-checkout-custom-load');

        this.isLoading = false;
        const onTransitionEnd = () => {
          loadSpinner?.classList.add('mp-hidden');
          loadSpinner?.classList.add('mp-display-none');
          customContainer?.classList.remove('mp-hidden');
          customContainer?.classList.remove('mp-display-none');
        };

        if (window.mpSuperTokenTriggerHandler) {
          if (window.mpSuperTokenTriggerHandler.isSuperTokenPaymentMethodsLoaded()) {
            loadSpinner?.classList.add('mp-hidden');
          }
        } else if (typeof sendMetric === 'function') {
          sendMetric('MP_SUPER_TOKEN_TRIGGER_HANDLER_MISSING', 'removeLoadSpinner', 'mp_cardform_trigger_handler_missing');
        }

        setTimeout( () => {
          onTransitionEnd();
        }, 800);

        const dateNowInMilliseconds = Date.now();
        const melidataWasReady = !!window.melidata;

        this.getMelidataReadyPromise()
          .then(() => {
            if (!melidataWasReady) {
              const durationInSeconds = Math.round((Date.now() - dateNowInMilliseconds) / 10) / 100;
              sendMetric(durationInSeconds, 'time in seconds waiting for melidata client at loading end', 'mp_custom_checkout_melidata_load_end_delay');
            }
            document.dispatchEvent(new CustomEvent('mp_super_token_loading_end', { detail: { dateNowInMilliseconds } }));
          })
          .catch((error) => {
            sendMetric('MP_MELIDATA_CLIENT_UNDEFINED', `Loading ended but melidata client failed to load: ${error?.message || 'unknown error'}`, 'mp_custom_checkout_melidata_client_undefined');
          });

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

        if (typeof MPCheckoutFieldsDispatcher === 'undefined' && typeof sendMetric === 'function' && !this.dispatcherMissingReported) {
            sendMetric('MP_CHECKOUT_FIELDS_DISPATCHER_MISSING', 'setupSecureFieldsStylesAndAddListeners', 'mp_checkout_init_error');
            this.dispatcherMissingReported = true;
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

                if(config.focusEventName && typeof MPCheckoutFieldsDispatcher !== 'undefined') {
                  MPCheckoutFieldsDispatcher.addEventListenerDispatcher(
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

                if(config.blurEventName && isValid && typeof MPCheckoutFieldsDispatcher !== 'undefined') {
                  MPCheckoutFieldsDispatcher.addEventListenerDispatcher(
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

    sendMelidataTimeToLoadMetric() {
      const startTime = Date.now();

      const interval = setInterval(() => {
        if (window.melidata) {
          clearInterval(interval);

          const endTime = Date.now();
          const durationInSeconds = (endTime - startTime) / 1000;

          sendMetric(
            durationInSeconds,
            'time in seconds to load melidata client',
            'mp_custom_checkout_melidata_time_to_load',
          );
        }
      }, 100);

      setTimeout(() => {
        clearInterval(interval);
      }, 20000); // 20 seconds
    }
}

/* globals wc_mercadopago_supertoken_bundle_params, sendMetric */
/* eslint-disable no-unused-vars, @typescript-eslint/no-unused-vars */
class MPSuperTokenTriggerHandler {
    CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR = '[value=woo-mercado-pago-custom]';
    CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR = '#payment_method_woo-mercado-pago-custom';
    FORM_CHECKOUT_SELECTOR = 'form.checkout, form#order_review';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    LOADING_ANIMATION_FINISH_DELAY = 500;
    AVOID_INSTANT_REMOVAL_LOADER_DELAY = 500;
    CURRENT_USER_EMAIL = wc_mercadopago_supertoken_bundle_params.current_user_email;

    // Attributes
    wcBuyerEmail = null;
    currentAmount = null;
    isAlreadyListeningForm = false;
    lastException = null;
    isFetchingPaymentMethods = false;
    customHandlerMissingReportedOnReset = false;
    loadGeneration = 0;
    cacheMetricsDispatched = false;
    savedInstallments = null;

    // Dependencies
    mpSuperTokenAuthenticator = null;
    wcEmailListener = null;
    mpSuperTokenPaymentMethods = null;
    mpSuperTokenErrorHandler = null;
    mpSuperTokenMetrics = null;

    constructor(mpSuperTokenAuthenticator, wcEmailListener, mpSuperTokenPaymentMethods, mpSuperTokenErrorHandler, mpSuperTokenMetrics) {
        this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
        this.wcEmailListener = wcEmailListener;
        this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
        this.mpSuperTokenErrorHandler = mpSuperTokenErrorHandler;
        this.mpSuperTokenMetrics = mpSuperTokenMetrics;
    }

    hasLastException() {
        return !!this.getLastException();
    }

    getLastException() {
        return this.lastException;
    }

    setLastException(exception) {
        this.lastException = exception;
    }

    getBuyerEmail() {
        this.wcBuyerEmail = this.wcBuyerEmail || this.wcEmailListener.getEmail() || this.CURRENT_USER_EMAIL;

        return this.wcBuyerEmail?.trim();
    }

    amountHasChanged() {
        const currentAmount = this.currentAmount;
        const amountUsed = this.mpSuperTokenAuthenticator.getAmountUsed();

        return currentAmount != null
            && amountUsed != null
            && currentAmount !== amountUsed;
    }

    emailHasChanged() {
        const buyerEmail = this.getBuyerEmail();
        const emailUsed = this.mpSuperTokenAuthenticator.getEmailUsed();

        return buyerEmail != null
            && emailUsed != null
            && buyerEmail !== emailUsed;
    }

    isDifferentEmail(newEmail) {
        return this.wcBuyerEmail != newEmail;
    }

    getCustomCheckoutRadioElement() {
        return document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_RADIO_SELECTOR)
            || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
    }

    isClassicCheckout() {
        return !!document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_RADIO_SELECTOR);
    }

    customCheckoutIsEnable() {
        return !!this.getCustomCheckoutRadioElement();
    }

    customCheckoutIsActive() {
        return this.getCustomCheckoutRadioElement()?.checked;
    }

    resetFlow() {
        this.mpSuperTokenAuthenticator.reset();
        this.mpSuperTokenPaymentMethods.reset();
    }

    resetCustomCheckout(shouldClearCache = true) {
        this.mpSuperTokenPaymentMethods.hideSuperTokenError();

        if (!window.mpCustomCheckoutHandler && !this.customHandlerMissingReportedOnReset) {
            if (typeof sendMetric === 'function') {
                sendMetric('MP_CUSTOM_CHECKOUT_HANDLER_NOT_EXISTS', 'resetCustomCheckout', 'mp_super_token_init_error');
                this.customHandlerMissingReportedOnReset = true;
            }
        }

        window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
        this.mpSuperTokenAuthenticator.setSuperTokenValidation(false);

        if (this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
            this.mpSuperTokenPaymentMethods.unmountCardForm();
            this.mpSuperTokenPaymentMethods.mountCardForm();
        }

        if (shouldClearCache) {
            this.resetFlow();
        }

        this.loadSuperToken(this.currentAmount)
            .finally(() => {
                setTimeout(async () => {
                    window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner();
                    window.mpCustomCheckoutHandler?.eventHandler?.hideCheckoutClassicLoader();

                    try {
                        await this.restorePreloadedPaymentMethod();
                    } catch (e) {
                        if (this.mpSuperTokenMetrics) {
                            this.mpSuperTokenMetrics.sendMetric('super_token_restore_error', e?.message || 'unknown', 'mp_super_token_restore_error');
                        }
                    }

                    const lastException = this.getLastException();
                    if (lastException) {
                      setTimeout(() => {
                        this.mpSuperTokenErrorHandler.handleError(lastException);
                        this.setLastException(null);
                      }, this.LOADING_ANIMATION_FINISH_DELAY);
                    }
                }, this.AVOID_INSTANT_REMOVAL_LOADER_DELAY);
            });
    }

    async restorePreloadedPaymentMethod() {
        if (!this.mpSuperTokenPaymentMethods.getSelectedPreloadedPaymentMethod()) {
            if (this.mpSuperTokenPaymentMethods.hasCheckoutError()) {
                this.mpSuperTokenPaymentMethods.selectLastPaymentMethodChoosen();
            }
            return;
        }

        await this.mpSuperTokenPaymentMethods.selectPreloadedPaymentMethod();
        this.mpSuperTokenPaymentMethods.storeSelectedPreloadedPaymentMethod(null);

        const activeMethod = this.mpSuperTokenPaymentMethods.getActivePaymentMethod();
        const savedInstallments = this.savedInstallments;
        this.savedInstallments = null;

        if (!activeMethod) {
            this.mpSuperTokenMetrics.sendMetric('super_token_restore_active_method_not_set', 'true', 'mp_super_token_restore_error');
            return;
        }

        const element = this.mpSuperTokenPaymentMethods.getPaymentMethodElementFromDOM(activeMethod);
        if (!element) {
            this.mpSuperTokenMetrics.sendMetric('super_token_restore_element_not_found', 'true', 'mp_super_token_restore_error');
            return;
        }

        this.mpSuperTokenPaymentMethods.showPaymentMethodDetails(element);

        if (!savedInstallments) return;

        const installmentsId = `mp-super-token-installments-select-${this.mpSuperTokenPaymentMethods.paymentMethodIdentifier(activeMethod)}`;
        const dropdown = element.querySelector(`#${installmentsId}`);
        if (!dropdown) {
            this.mpSuperTokenMetrics.sendMetric('super_token_restore_installments_dropdown_not_found', 'true', 'mp_super_token_restore_error');
            return;
        }

        const optionExists = [...dropdown.options].some(o => o.value === savedInstallments);
        if (!optionExists) {
            this.mpSuperTokenMetrics.sendMetric('super_token_restore_installment_option_not_found', 'true', 'mp_super_token_restore_error');
            return;
        }

        dropdown.value = savedInstallments;
        const cardInstallments = document.getElementById('cardInstallments');
        if (cardInstallments) cardInstallments.value = savedInstallments;
        dropdown.dispatchEvent(new Event('change'));
    }

    resetSuperTokenOnError(preserveSelection = false) {
        if (document.querySelector('#mp_checkout_type')?.value === 'super_token') {
            const paymentMethodList = document.querySelector(`.${this.mpSuperTokenPaymentMethods.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);

            if (paymentMethodList) {
              paymentMethodList.scrollIntoView({ behavior: 'smooth' });
            }

            this.savedInstallments = null;
            let lastMethodToPreserve = null;
            if (preserveSelection) {
                lastMethodToPreserve = this.mpSuperTokenPaymentMethods.getLastPaymentMethodChoosen() || null;
                this.savedInstallments = document.getElementById('cardInstallments')?.value || null;
            }

            this.mpSuperTokenPaymentMethods.deselectAllPaymentMethods();
            this.mpSuperTokenPaymentMethods.hideAllPaymentMethodDetails();
            this.mpSuperTokenPaymentMethods.unmountActiveSecurityCodeInstance();
            this.mpSuperTokenPaymentMethods.clearActivePaymentMethod();

            this.resetCustomCheckout(true);

            if (lastMethodToPreserve) {
                this.mpSuperTokenPaymentMethods.storeSelectedPreloadedPaymentMethod(lastMethodToPreserve);
            }
        }
    }

    isSuperTokenPaymentMethodsLoaded() {
        return this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()
    }

    cancelLoad() {
        this.loadGeneration++;
        this.isFetchingPaymentMethods = false;
        this.mpSuperTokenPaymentMethods.reset();
    }

    async fetchAndRenderSuperTokenPaymentMethods() {
        const buyerEmail = this.getBuyerEmail();
        if (!buyerEmail) {
            this.mpSuperTokenMetrics.sendMetric('super_token_skipped_no_email', 'true', '');
            return;
        }

        // SDK rejects invalid emails — validate before calling to avoid invalid_email_address_provided errors
        if (!this.wcEmailListener.isValid(buyerEmail)) {
            this.mpSuperTokenMetrics.sendMetric('super_token_skipped_invalid_email', 'true', '');
            return;
        }

        this.mpSuperTokenMetrics.sendMetric('super_token_email_captured', 'true', '');
        this.isFetchingPaymentMethods = true;
        const currentGeneration = this.loadGeneration;
        const paymentMethods = await this.mpSuperTokenAuthenticator.getAccountPaymentMethods(
            this.currentAmount,
            buyerEmail
        );
        if (this.loadGeneration !== currentGeneration) {
            return;
        }

        this.isFetchingPaymentMethods = false;

        if (!paymentMethods || !paymentMethods.length) return;

        await this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
            paymentMethods,
            this.currentAmount
        );
    }

    async loadSuperToken(currentAmount) {
        this.currentAmount = this.mpSuperTokenAuthenticator.formatAmount(currentAmount);

        // Prevent unnecessary re-fetching of payment methods
        if (this.isFetchingPaymentMethods && !this.amountHasChanged() && !this.emailHasChanged()) return;

        if (this.amountHasChanged()) {
            this.resetFlow();
            this.mpSuperTokenMetrics.sendMetric('super_token_reset_on_amount_change', 'true', '');
        }

        if (this.isSuperTokenPaymentMethodsLoaded()) {
            this.mpSuperTokenPaymentMethods.renderAccountPaymentMethods(
                this.mpSuperTokenPaymentMethods.getStoredPaymentMethods(),
                this.currentAmount
            );

            return;
        }

        if (!this.isAlreadyListeningForm) {
            this.wcEmailListener.onEmailChange(async (email, isValid) => {
                if (!isValid || !this.currentAmount) {
                    return;
                }

                if (this.isDifferentEmail(email) && this.wcBuyerEmail != null) {
                    this.wcBuyerEmail = email;
                    this.mpSuperTokenMetrics.sendMetric('super_token_reset_on_email_change', 'true', '');
                    this.resetCustomCheckout();
                }
            });

            this.wcEmailListener.setupEmailChangeHandlers();

            this.isAlreadyListeningForm = true;
        }

        await this.fetchAndRenderSuperTokenPaymentMethods();

        if (!this.cacheMetricsDispatched) {
            this.cacheMetricsDispatched = true;
            this.mpSuperTokenMetrics.sendStaleCacheMetrics().catch(() => {}); // fire-and-forget: must not delay checkout flow
        }
    }
}

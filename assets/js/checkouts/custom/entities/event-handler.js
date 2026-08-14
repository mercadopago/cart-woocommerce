/* eslint-disable no-unused-vars, @typescript-eslint/no-unused-vars */
/* globals wc_mercadopago_custom_event_handler_params, MP_DEVICE_SESSION_ID, jQuery, CheckoutPage, MPSuperTokenErrorCodes, sendMetric, MobileCheckoutClassicObserver */

// Verdict actions returned by the CDN-hosted resolver (window.mpResolveCheckoutValidation).
// These VALUES are the cross-boundary contract — they must match the resolver's strings.
const VALIDATION_ACTION = { PROCEED: 'PROCEED', BLOCK: 'BLOCK', FAIL_OPEN: 'FAIL_OPEN' };

// Fail-open reasons owned by the plugin (route-level + CDN availability). Emitted as the
// metric value; the original cause travels in the metric message (detail).
const FAIL_OPEN_REASON = {
    TIMEOUT: 'TIMEOUT',
    NETWORK: 'NETWORK',
    ENDPOINT_MISSING: 'ENDPOINT_MISSING',
    CDN_UNAVAILABLE: 'CDN_UNAVAILABLE',
    CDN_ERROR: 'CDN_ERROR',
};

const FAIL_OPEN_METRIC = 'MP_CHECKOUT_AJAX_VALIDATION_FAIL_OPEN';

class MPEventHandler {
    REMOVE_LOAD_SPINNER_DELAY = 500;
    MAX_ORDER_PAY_RETRIES = 5;

    constructor(cardForm, threeDSHandler, mobileCheckoutClassicObserver = MobileCheckoutClassicObserver) {
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
        this._mobileObserver = null;
        this._MobileCheckoutClassicObserver = mobileCheckoutClassicObserver;
        this.isValidating = false;
        this._validationAbortController = null;
        this._validationCancelled = false;
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
        jQuery(document).ready(() => {
            this.threeDSHandler.set3dsStatusValidationListener();
            if (!wc_mercadopago_custom_event_handler_params.is_mobile) {
                jQuery(document).on('updated_checkout', this.handleUpdatedCheckout.bind(this));
                this.initCardFormWhenReady();
            } else {
                this._mobileObserver = new this._MobileCheckoutClassicObserver(
                    this.cardForm,
                    this.isCheckoutCustomPaymentMethodSelected.bind(this),
                    this.handleUpdatedCheckout.bind(this),
                    this.initCardFormWhenReady.bind(this)
                );
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

          this.validateCheckoutThenContinue(event, () => this.handleWithSuperTokenSubmit(event, wc_checkout_form));

          // Return false to avoid the default behavior of the form submission
          return false;
        } else {
            jQuery('#mp_checkout_type').val('custom');

            if (!this.hasToken) {
                this.setPayerIdentificationInfo();
                this.createToken();
            }

            return false;
        }
    }

    /**
     * Runs server-side checkout validation through the wc_ajax_mp_validate_checkout endpoint
     * before tokenization, then delegates the verdict to the CDN-hosted resolver
     * (resolveCheckoutValidation). On PROCEED runs `onValid`; on BLOCK shows the real errors and
     * never tokenizes; on FAIL_OPEN (route or resolver failure) proceeds without blocking — defense
     * in depth still happens on the real WooCommerce submit.
     *
     * @param {Event} event the checkout submit event
     * @param {Function} onValid continuation to run when the form is valid
     */
    validateCheckoutThenContinue(event, onValid) {
        if (this.isValidating) {
            return;
        }

        // Reset the cancellation flag so a previous checkout_error that fired without a
        // validation in-flight does not permanently suppress the next attempt's onValid().
        this._validationCancelled = false;

        // Order-pay (/checkout/order-pay/): billing data is stored in the existing order and is
        // not re-posted in #order_review, which carries woocommerce-pay-nonce — not the
        // woocommerce-process-checkout-nonce this endpoint validates. Pre-validating here would
        // always fail the nonce check and fail open with reason 'server_error' on every order-pay
        // payment, polluting the metric. Skip the pre-check; the real WC submit still validates.
        if (this.isOrderPayPage()) {
            onValid();
            return;
        }

        const params = window.wc_mercadopago_checkout_update_params;
        const validationEndpoint = params?.validationEndpoint;

        // Proceeds to tokenization despite NOT getting a conclusive verdict. This is the
        // fail-open path: never block the buyer on a best-effort pre-check (the real
        // WooCommerce submit still validates). `reason` lets us measure fail-open rate and
        // break it down; `detail` carries extra context (e.g. the network error message).
        const failOpenAndContinue = (reason, detail) => {
            if (typeof sendMetric === 'function') {
                // Stable Datadog event = FAIL_OPEN_METRIC; value = reason; message = the original cause.
                sendMetric(reason, detail || 'validate_checkout_then_continue', FAIL_OPEN_METRIC);
            }
            this.hideValidationLoader();
            onValid();
        };

        // Defensive: a new plugin version always localizes the endpoint. If it is missing,
        // do not block payment — fail open (no loader was shown yet).
        if (!validationEndpoint) {
            failOpenAndContinue(FAIL_OPEN_REASON.ENDPOINT_MISSING);
            return;
        }

        this.isValidating = true;
        event.preventDefault();
        this.showValidationLoader();

        const body = this.getCheckoutForm()?.serialize() ?? '';
        this._validationAbortController = new AbortController();
        const controller = this._validationAbortController;
        const timeoutId = setTimeout(() => controller.abort(), 8000);

        window.fetch(validationEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((response) => {
                // Guard against the race where abort() was called after the response was already
                // fully buffered — in that case no AbortError is thrown and the .catch() never
                // fires, so _validationCancelled must be checked here as well.
                if (this._validationCancelled) return;

                // The verdict (cross-check + funnel metrics) lives on the CDN resolver so it can be
                // hot-fixed without a plugin release. resolveCheckoutValidation falls open if the
                // resolver is absent or throws — the buyer is never blocked on a best-effort layer.
                const verdict = this.resolveCheckoutValidation(response);

                if (verdict.action === VALIDATION_ACTION.BLOCK) {
                    this.displayCheckoutValidationErrors(verdict.errors);
                    this.hideValidationLoader();
                    return;
                }

                if (verdict.action === VALIDATION_ACTION.FAIL_OPEN) {
                    failOpenAndContinue(verdict.reason, verdict.detail);
                    return;
                }

                // PROCEED: the continuation owns the loader lifecycle from here (createToken manages
                // the card-form spinner; handleWithSuperTokenSubmit re-shows its own loader), so we
                // clear our validation overlay before handing off to avoid a stuck overlay. On the
                // super_token path this means a brief hide-then-show flicker, intentional and accepted
                // so each step owns its own loader.
                this.hideValidationLoader();
                onValid();
            })
            .catch((error) => {
                // AbortError from handleCheckoutError() — checkout is already in error state,
                // do not proceed. AbortError from the 8s internal timeout falls through to
                // fail-open so the buyer is never blocked on a best-effort layer.
                if (error?.name === 'AbortError' && this._validationCancelled) {
                    return;
                }
                const reason = error?.name === 'AbortError' ? FAIL_OPEN_REASON.TIMEOUT : FAIL_OPEN_REASON.NETWORK;
                failOpenAndContinue(reason, error?.message || error?.name || reason);
            })
            .finally(() => {
                clearTimeout(timeoutId);
                this.isValidating = false;
                this._validationAbortController = null;
                this._validationCancelled = false;
            });
    }

    /**
     * Thin wrapper over the CDN-hosted validation resolver (window.mpResolveCheckoutValidation).
     * The cross-check and funnel metrics live on the CDN so they can be hot-fixed without a plugin
     * release. If the resolver is absent (bundle not yet loaded) or throws, fail open — never block
     * the buyer on this best-effort layer — and carry the cause so the metric records the root.
     *
     * @param {Object} response parsed JSON from the validation route
     * @returns {{ action: string, errors?: Array, reason?: string, detail?: string }}
     */
    resolveCheckoutValidation(response) {
        try {
            if (typeof window.mpResolveCheckoutValidation === 'function') {
                const verdict = window.mpResolveCheckoutValidation(response);
                if (verdict?.action) {
                    return verdict;
                }
            }
        } catch (error) {
            return { action: VALIDATION_ACTION.FAIL_OPEN, reason: FAIL_OPEN_REASON.CDN_ERROR, detail: error?.message || error?.name };
        }
        return { action: VALIDATION_ACTION.FAIL_OPEN, reason: FAIL_OPEN_REASON.CDN_UNAVAILABLE };
    }

    showValidationLoader() {
        if (this.isOrderPayPage()) {
            this.cardForm?.createLoadSpinner();
        } else {
            this.showCheckoutClassicLoader();
        }
    }

    hideValidationLoader() {
        if (this.isOrderPayPage()) {
            this.cardForm?.removeLoadSpinner();
        } else {
            this.hideCheckoutClassicLoader();
        }
    }

    /**
     * Renders checkout validation errors inside the standard WooCommerce notice group.
     * Uses textContent (no innerHTML) to avoid injecting unsanitized markup.
     *
     * @param {Array} errors list of { field, code, message }
     */
    displayCheckoutValidationErrors(errors) {
        const messages = (Array.isArray(errors) ? errors : [])
            .map((error) => error?.message)
            .filter(Boolean);

        if (messages.length === 0) {
            return;
        }

        const list = document.createElement('ul');
        list.className = 'woocommerce-error';
        list.setAttribute('role', 'alert');
        messages.forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            list.appendChild(item);
        });

        const form = this.getCheckoutForm();
        const noticeGroup = document.querySelector('.woocommerce-NoticeGroup-checkout');

        if (noticeGroup) {
            // replaceChildren clears existing notices and appends the new list in one call,
            // without innerHTML (forbidden by the Node security rules).
            noticeGroup.replaceChildren(list);
        } else if (form?.length) {
            form.prepend(list);
        }

        list.scrollIntoView({ behavior: 'smooth' });
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

            if (!superTokenPaymentMethods.validateInstallmentSelection()) {
                this.cardForm.removeLoadSpinner();
                this.hideCheckoutClassicLoader();
                return;
            }

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

            const recoverableErrors = [
                MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR,
                MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_ERROR,
                MPSuperTokenErrorCodes.AUTHORIZE_PAYMENT_METHOD_USER_CANCELLED,
            ];
            superTokenTriggerHandler?.resetSuperTokenOnError(recoverableErrors.includes(exception?.message));
            superTokenTriggerHandler?.setLastException(exception);
            superTokenAuthenticator?.setSuperTokenValidation(false);
        }
    }

    createToken() {
        if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.runPreSubmitGates === 'function') {
            const gate = CheckoutPage.runPreSubmitGates(this.cardForm);
            if (!gate.passed) {
                return false;
            }
        }

        const callFn = window.callSdkWithMetrics || ((fn) => fn());
        callFn(
            () => this.cardForm.form.createCardToken(),
            'createCardToken'
        )
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
                    if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.emitGateBlockedMetric === 'function') {
                        CheckoutPage.emitGateBlockedMetric('CARD', 'mp_custom_card_validation', 'empty_token');
                    }
                    throw new Error('cardToken is empty');
                }
            })
            .catch((error) => {
                console.warn('Token creation error: ', error);
                this.cardForm.scrollToCardForm();
                this.cardForm.removeLoadSpinner();
                this.cardForm.removeBlockOverlay();
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
            // Ignore the SDK cardForm's programmatic re-submit (requestSubmit → submitter === null), so the pre-submit gate runs once per user click. Scoped to the Custom flow so it never interferes with other payment methods sharing #order_review. See docs/agent/traps.md.
            if (event?.originalEvent && event.originalEvent.submitter === null) {
                event.stopImmediatePropagation();
                event.preventDefault();
                return false;
            }

            // Do not call event.preventDefault before mercadoPagoFormHandler runs: in cases like mercado_pago_submit=true we rely on the form's native submission, and preventing it here would break the expected behavior.
            return this.mercadoPagoFormHandler(event);
        } else {
            if (this.cardForm.formMounted) {
                this.cardForm.form.unmount();
            }
        }
    }

    handleCheckoutError() {
        // Release the validation lock in case checkout_error fired mid-validation (e.g. another
        // script triggered the event while our fetch was still pending). Otherwise the lock would
        // stay held until the request resolves, leaving the buy button unusable until then.
        this._validationCancelled = true;
        this._validationAbortController?.abort();
        this._validationAbortController = null;
        this.isValidating = false;
        this.hideValidationLoader();
        this.hasToken = false;
        this.mercado_pago_submit = false;

        this.cardForm.removeLoadSpinner();
        const { superTokenTriggerHandler } = this.getSuperTokenDeps();
        superTokenTriggerHandler?.resetSuperTokenOnError(true);
    }

    handleUpdatedCheckout() {
        // Order Pay has its own dedicated mounter (initCardFormWhenReady on init and
        // onSelectCheckoutCustomInOrderPayPage on selection). The mobile observer also
        // routes payment_method_selected here, so running this path on Order Pay is
        // redundant and races with that mounter over cardForm.formMounted — both do a
        // non-atomic check-then-act and formMounted only flips async in onFormMounted.
        // Skip it there so the dedicated flow owns the mount. (PPSP-1592)
        if (this.isOrderPayPage()) {
            return Promise.resolve();
        }

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

            return Promise.all(promises)
                .finally(() => {
                    this.loadSpinnerTimeout = setTimeout(() => this.cardForm.removeLoadSpinner(), this.REMOVE_LOAD_SPINNER_DELAY);
                });
        }
        return Promise.resolve();
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
    /**
     * Normalize a document number for the API payload: strip the mask and
     * uppercase it (raw value). Applied to every document type intentionally —
     * it aligns Classic checkout with Blocks, which already sent the raw value
     * for every document. The document type is an enum (CPF/CNPJ/CI/CC/CE/NIT...)
     * and is never passed through here, so per-type validation stays unchanged —
     * only the format of the value sent to the API changes (masked → raw).
     *
     * The Payments API accepts the raw, mask-free value for non-Brazilian
     * document types as well. Validated end-to-end on MLC (RUT): a value typed
     * with the mask in the checkout reaches the payload mask-free and the
     * payment is created successfully.
     *
     * @param {string} value - The masked value from the visible input.
     * @returns {string} Raw uppercase document number (only A-Z and 0-9).
     */
    normalizeDocumentNumber(value) {
        return value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    setPayerIdentificationInfo() {
        const documentElements = [
            { selector: '#form-checkout__identificationType', hiddenInputId: '#payerDocType' },
            { selector: '#form-checkout__identificationNumber', hiddenInputId: '#payerDocNumber' }
        ];

        documentElements.forEach(({ selector, hiddenInputId }) => {
            const element = document.querySelector(selector);
            const hiddenInput = document.querySelector(hiddenInputId);

            if (element && hiddenInput && element.value) {
                const isDocNumber = hiddenInputId === '#payerDocNumber';
                hiddenInput.value = isDocNumber
                    ? this.normalizeDocumentNumber(element.value)
                    : element.value;
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

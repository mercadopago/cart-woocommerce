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
        this.customTokenRequest = null;
        this.superTokenRequest = null;
        this.superTokenAuthorization = null;
        this.preparedSuperTokenContext = null;
        this.paymentSelectionGeneration = 0;
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
        if (!this.readinessEventsBound) {
            document.addEventListener('mp_super_token_payment_method_selected', () => this.invalidatePaymentPreparation());
            document.addEventListener('mp_card_form_mounted', () => {
                if (this.getCheckoutType() === 'custom') this.invalidatePaymentPreparation();
            });
            this.readinessEventsBound = true;
        }
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

    getCheckoutFieldValue(fields, field) {
        const matches = fields.filter(({ name }) => name === `mercadopago_custom[${field}]`);
        return matches.length === 1 && typeof matches[0].value === 'string'
            ? matches[0].value.trim() : '';
    }

    getCheckoutType(checkoutForm = this.getCheckoutForm()) {
        return this.getCheckoutFieldValue(checkoutForm.serializeArray(), 'checkout_type');
    }

    isCustomPaymentMethodSelectedInForm(checkoutForm) {
        const methods = checkoutForm.serializeArray().filter(({ name }) => name === 'payment_method');
        return methods.length === 1 && methods[0].value === 'woo-mercado-pago-custom';
    }

    hasCompleteCustomPaymentData(checkoutForm = this.getCheckoutForm()) {
        const fields = checkoutForm.serializeArray();
        if (this.getCheckoutFieldValue(fields, 'checkout_type') !== 'custom') {
            return false;
        }

        // This is a presence check. Zero-amount subscription routes remain server-owned.
        return ['token', 'amount', 'payment_method_id', 'installments'].every(
            (field) => this.getCheckoutFieldValue(fields, field) !== ''
        ) && this.getCheckoutFieldValue(fields, 'installments') !== '0';
    }

    hasCompleteSuperTokenPaymentData(checkoutForm = this.getCheckoutForm()) {
        const fields = checkoutForm.serializeArray();
        if (this.getCheckoutFieldValue(fields, 'checkout_type') !== 'super_token') return false;

        const complete = ['authorized_pseudotoken', 'amount', 'payment_method_id', 'payment_type_id']
            .every((field) => this.getCheckoutFieldValue(fields, field) !== '');
        const installments = this.getCheckoutFieldValue(fields, 'installments');
        const { superTokenPaymentMethods } = this.getSuperTokenDeps();
        const activeMethod = superTokenPaymentMethods && typeof superTokenPaymentMethods.getActivePaymentMethod === 'function'
            ? superTokenPaymentMethods.getActivePaymentMethod() : null;
        return complete && (!this.preparedSuperTokenContext || this.isCurrentSuperTokenContext(this.preparedSuperTokenContext))
            && !!activeMethod?.token
            && this.getCheckoutFieldValue(fields, 'authorized_pseudotoken') === activeMethod.token
            && (this.getCheckoutFieldValue(fields, 'payment_type_id') !== 'credit_card'
            || (installments !== '' && Number.isFinite(Number(installments)) && Number(installments) > 0));
    }

    invalidatePaymentPreparation() {
        // Selection events fire before the new method/flow is written. Only retire old work here.
        this.paymentSelectionGeneration++;
        if (this.superTokenRequest || this.customTokenRequest) this.removePaymentLoaders();
        this.customTokenRequest = null;
        this.superTokenRequest = null;
        this.resetPaymentReadiness();
    }

    resetPaymentReadiness() {
        this.mercado_pago_submit = false;
        this.hasToken = false;
        this.preparedSuperTokenContext = null;
    }

    handleIncompletePayment() {
        this.resetPaymentReadiness();
        const params = window.wc_mercadopago_custom_checkout_params;
        if (params && typeof params.error_messages?.default === 'string') {
            this.displayCheckoutValidationErrors([{ message: params.error_messages.default }]);
        } else {
            console.warn('Checkout payment data is incomplete.');
        }
        this.removePaymentLoaders();
    }

    removePaymentLoaders() {
        this.cardForm.removeLoadSpinner();
        this.cardForm.removeBlockOverlay();
        if (this.isOrderPayPage() && typeof CheckoutPage !== 'undefined'
            && typeof CheckoutPage.deferBlockOverlayRemoval === 'function') {
            CheckoutPage.deferBlockOverlayRemoval(this.cardForm);
        }
        this.hideCheckoutClassicLoader();
    }

    mercadoPagoFormHandler(event, wc_checkout_form) {
        this.setMercadoPagoSessionId();
        const { superTokenPaymentMethods, superTokenMetrics } = this.getSuperTokenDeps();
        superTokenPaymentMethods?.hideSuperTokenError();
        const checkoutForm = this.getCheckoutForm();
        const checkoutType = this.getCheckoutType(checkoutForm);

        if (this.mercado_pago_submit) {
            if (checkoutType === 'wallet_button'
                || (checkoutType === 'super_token' ? this.hasCompleteSuperTokenPaymentData(checkoutForm)
                    : this.hasCompleteCustomPaymentData(checkoutForm))) {
                return true;
            }
            this.resetPaymentReadiness();
        }

        if (checkoutType === 'wallet_button') {
            return true;
        } else if (checkoutType === 'super_token') {
          superTokenMetrics?.registerClickOnPlaceOrderButton();
          const context = this.captureSuperTokenContext();
          this.validateCheckoutThenContinue(event, () => {
              if (this.isCurrentSuperTokenContext(context)) this.handleWithSuperTokenSubmit(event, wc_checkout_form);
          });

          // Return false to avoid the default behavior of the form submission
          return false;
        } else {
            checkoutForm.find('[name="mercadopago_custom[checkout_type]"]').val('custom');

            // hasToken alone cannot hold an unprepared Custom attempt indefinitely.
            if (this.hasToken) {
                this.resetPaymentReadiness();
            }

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

    captureSuperTokenContext() {
        const checkoutForm = this.getCheckoutForm();
        const { superTokenPaymentMethods } = this.getSuperTokenDeps();
        const method = superTokenPaymentMethods && typeof superTokenPaymentMethods.getActivePaymentMethod === 'function'
            ? superTokenPaymentMethods.getActivePaymentMethod() : null;
        return {
            form: checkoutForm[0], generation: this.paymentSelectionGeneration,
            paymentMethods: superTokenPaymentMethods, methodToken: method?.token,
            fields: ['amount', 'payment_method_id', 'payment_type_id', 'installments']
                .map((field) => this.getCheckoutFieldValue(checkoutForm.serializeArray(), field)),
        };
    }

    isCurrentSuperTokenContext(context) {
        const current = this.captureSuperTokenContext();
        return !!context.form?.isConnected && current.form === context.form
            && current.generation === context.generation
            && current.paymentMethods === context.paymentMethods && current.methodToken === context.methodToken
            && current.fields.every((value, index) => value === context.fields[index])
            && this.isCustomPaymentMethodSelectedInForm(this.getCheckoutForm())
            && this.getCheckoutType() === 'super_token';
    }

    isCurrentSuperTokenRequest(request) {
        return this.superTokenRequest === request && this.isCurrentSuperTokenContext(request);
    }

    completeSuperTokenRequest(request) {
        if (!this.isCurrentSuperTokenRequest(request)) return false;
        const checkoutForm = this.getCheckoutForm();
        const proof = this.getCheckoutFieldValue(checkoutForm.serializeArray(), 'authorized_pseudotoken');
        if (!this.hasCompleteSuperTokenPaymentData(checkoutForm) || !request.methodToken || proof !== request.methodToken) {
            const { superTokenAuthenticator } = this.getSuperTokenDeps();
            superTokenAuthenticator?.setSuperTokenValidation(false);
            this.handleIncompletePayment();
            return false;
        }
        this.mercado_pago_submit = true;
        this.preparedSuperTokenContext = request;
        return true;
    }

    clearSuperTokenAuthorization(form) {
        Array.from(form?.elements || []).forEach((input) => {
            if (input.name === 'mercadopago_custom[authorized_pseudotoken]') input.value = '';
        });
    }

    authorizeSuperTokenPayment(request, authenticator, token) {
        const previous = this.superTokenAuthorization;
        const authorization = (async () => {
            // The shared authenticator writes to the DOM after awaiting the SDK. Do not let
            // an obsolete writer populate the proof while another attempt is authorizing.
            if (previous) await previous.catch(() => undefined);
            if (!this.isCurrentSuperTokenRequest(request)) return;
            this.clearSuperTokenAuthorization(request.form);
            await authenticator.authorizePayment(token);
        })();
        this.superTokenAuthorization = authorization;
        return authorization.finally(() => {
            if (this.superTokenAuthorization === authorization) this.superTokenAuthorization = null;
        });
    }

    async handleWithSuperTokenSubmit(event, wc_checkout_form) {
        event.preventDefault();
        if (this.superTokenRequest && this.isCurrentSuperTokenRequest(this.superTokenRequest)) return;
        const request = this.captureSuperTokenContext();
        this.superTokenRequest = request;
        this.resetPaymentReadiness();
        // A new authorization must produce its own proof; do not erase a complete retry shortcut.
        this.clearSuperTokenAuthorization(request.form);
        this.showCheckoutClassicLoader();

        // Prefer the unified refactored finalization published by the refactored bundle. Fall back
        // to the legacy inline flow if the bundle is absent, so the checkout never breaks when the
        // refactored entry failed to load.
        try {
            if (typeof window.mpSuperTokenFinalizeClassic === 'function') {
                return await this.finalizeSuperTokenWithRefactoredFlow(wc_checkout_form, request);
            }
            return await this.legacyHandleWithSuperTokenSubmit(wc_checkout_form, request);
        } finally {
            if (this.superTokenRequest === request) {
                if (!this.isCurrentSuperTokenContext(request)) {
                    this.resetPaymentReadiness();
                    this.removePaymentLoaders();
                }
                this.superTokenRequest = null;
            }
        }
    }

    finalizeSuperTokenWithRefactoredFlow(wc_checkout_form, request) {
        const { superTokenTriggerHandler, superTokenAuthenticator, superTokenPaymentMethods, superTokenErrorHandler, superTokenMetrics } = this.getSuperTokenDeps();
        const current = () => this.isCurrentSuperTokenRequest(request);
        let handlingCurrentError = false;
        const guardedCall = (target, method) => (...args) => {
            if (current()) return target[method](...args);
        };

        return Promise.resolve().then(() => window.mpSuperTokenFinalizeClassic({
            paymentMethods: superTokenPaymentMethods,
            authenticator: superTokenAuthenticator && {
                authorizePayment: (token) => this.authorizeSuperTokenPayment(request, superTokenAuthenticator, token),
                setSuperTokenValidation: guardedCall(superTokenAuthenticator, 'setSuperTokenValidation'),
            },
            triggerHandler: {
                resetSuperTokenOnError: (...args) => {
                    if (!current()) return;
                    handlingCurrentError = true;
                    superTokenTriggerHandler.resetSuperTokenOnError(...args);
                },
                setLastException: (...args) => {
                    // The adapter invokes this synchronously after its reset changes the flow.
                    if (handlingCurrentError) superTokenTriggerHandler.setLastException(...args);
                },
            },
            errorHandler: { handleError: guardedCall(superTokenErrorHandler, 'handleError') },
            isOrderPayPage: () => this.isOrderPayPage(),
            // The adapter calls submit after markPaymentReady regardless of its void return.
            markPaymentReady: () => this.completeSuperTokenRequest(request),
            submitCheckoutForm: () => {
                if (this.mercado_pago_submit && this.completeSuperTokenRequest(request)) {
                    wc_checkout_form.$checkout_form.trigger('submit');
                }
            },
            submitOrderPayForm: () => {
                if (this.mercado_pago_submit && this.completeSuperTokenRequest(request)) this.handle3dsPayOrderFormSubmission();
            },
            removeLoader: () => {
                if (!current()) return;
                this.removePaymentLoaders();
            },
        })).catch(() => {
            // Safety net mirroring the legacy try/catch: the use case is fail-safe and the
            // adapter's result branches own the loader, but if an unexpected exception still
            // escapes, never leave the classic checkout frozen on the loader — and surface it.
            if (!current()) return;
            if (superTokenMetrics && typeof superTokenMetrics.sendMetric === 'function') {
                superTokenMetrics.sendMetric(
                    'super_token_finalize_unexpected_error',
                    'unexpected_error',
                    'An unexpected exception escaped the refactored Classic finalization'
                );
            } else {
                console.warn('Super Token finalization metrics are unavailable.');
            }
            this.removePaymentLoaders();
            this.resetPaymentReadiness();
        });
    }

    async legacyHandleWithSuperTokenSubmit(wc_checkout_form, request) {
        const { superTokenTriggerHandler, superTokenAuthenticator, superTokenPaymentMethods, superTokenErrorHandler } = this.getSuperTokenDeps();

        try {
            if (!superTokenPaymentMethods) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_PAYMENT_METHODS_NOT_FOUND);
            if (!superTokenAuthenticator) throw new Error(MPSuperTokenErrorCodes.SUPER_TOKEN_AUTHENTICATOR_NOT_FOUND);

            const activeMethod = superTokenPaymentMethods.getActivePaymentMethod();
            const isSuperTokenValid = activeMethod && superTokenPaymentMethods.isSelectedPaymentMethodValid();

            if (!activeMethod) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_ERROR);
            if (!isSuperTokenValid) throw new Error(MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID);

            if (!superTokenPaymentMethods.validateInstallmentSelection()) {
                this.removePaymentLoaders();
                return;
            }

            if (this.isOrderPayPage()) superTokenPaymentMethods.unmountCardForm();

            await superTokenPaymentMethods.updateSecurityCode();
            if (!this.isCurrentSuperTokenRequest(request)) return;

            await this.authorizeSuperTokenPayment(request, superTokenAuthenticator, activeMethod.token);
            if (!this.completeSuperTokenRequest(request)) return;

            superTokenAuthenticator?.setSuperTokenValidation(true);
            if (!this.isOrderPayPage()) {
                wc_checkout_form.$checkout_form.trigger('submit');
            } else {
                this.handle3dsPayOrderFormSubmission();
            }
        } catch(exception) {
            if (!this.isCurrentSuperTokenRequest(request)) return;
            if (exception?.message === MPSuperTokenErrorCodes.SELECT_PAYMENT_METHOD_NOT_VALID) {
                superTokenErrorHandler?.handleError(exception);
                this.removePaymentLoaders();
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
            this.resetPaymentReadiness();
            this.removePaymentLoaders();
        }
    }

    createToken() {
        if (this.customTokenRequest && this.isCurrentCustomTokenRequest(this.customTokenRequest)) {
            return false;
        }
        this.customTokenRequest = null;

        const checkoutForm = this.getCheckoutForm();
        const form = checkoutForm[0];
        if (!form || this.getCheckoutType(checkoutForm) !== 'custom') {
            this.handleIncompletePayment();
            return false;
        }

        if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.runPreSubmitGates === 'function') {
            const gate = CheckoutPage.runPreSubmitGates(this.cardForm);
            if (!gate.passed) {
                return false;
            }
        }

        const request = { form, sdkForm: this.cardForm.form };
        this.customTokenRequest = request;
        const callFn = window.callSdkWithMetrics || ((fn) => fn());
        let tokenPromise;
        try {
            tokenPromise = callFn(() => request.sdkForm.createCardToken(), 'createCardToken');
        } catch {
            this.customTokenRequest = null;
            this.handleIncompletePayment();
            return false;
        }
        Promise.resolve(tokenPromise)
            .then((cardToken) => {
                // A late SDK completion must not prepare a replaced form or another flow.
                if (!this.isCurrentCustomTokenRequest(request)) {
                    return;
                }

                if (typeof cardToken?.token === 'string' && cardToken.token.trim()) {
                    const tokenInputs = Array.from(form.elements).filter((input) =>
                        input.name === 'mercadopago_custom[token]'
                        && input.tagName === 'INPUT'
                        && ['hidden', 'text'].includes(input.type)
                        && !input.matches(':disabled')
                    );
                    if (tokenInputs.length !== 1) {
                        this.handleIncompletePayment();
                        return;
                    }

                    tokenInputs[0].value = cardToken.token;
                    if (!this.hasCompleteCustomPaymentData(checkoutForm)) {
                        tokenInputs[0].value = '';
                        this.handleIncompletePayment();
                        return;
                    }
                    this.mercado_pago_submit = true;
                    this.hasToken = true;

                    if (this.mpFormId === 'order_review') {
                        this.handle3dsPayOrderFormSubmission();
                        return false;
                    }

                    checkoutForm.submit();
                } else {
                    if (typeof CheckoutPage !== 'undefined' && typeof CheckoutPage.emitGateBlockedMetric === 'function') {
                        CheckoutPage.emitGateBlockedMetric('CARD', 'mp_custom_card_validation', 'empty_token');
                    }
                    this.cardForm.scrollToCardForm();
                    this.handleIncompletePayment();
                }
            })
            .catch(() => {
                if (this.isCurrentCustomTokenRequest(request)) {
                    this.cardForm.scrollToCardForm();
                    this.handleIncompletePayment();
                }
            })
            .finally(() => {
                if (this.customTokenRequest === request) {
                    this.customTokenRequest = null;
                }
            });

        return false;
    }

    isCurrentCustomTokenRequest(request) {
        const checkoutForm = this.getCheckoutForm();
        return this.customTokenRequest === request
            && request.form.isConnected
            && checkoutForm[0] === request.form
            && this.cardForm.form === request.sdkForm
            && this.isCustomPaymentMethodSelectedInForm(checkoutForm)
            && this.getCheckoutType(checkoutForm) === 'custom';
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
      if (!this.isCustomPaymentMethodSelectedInForm(this.getCheckoutForm())) this.invalidatePaymentPreparation();
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
        this.customTokenRequest = null;
        this.superTokenRequest = null;
        this._validationAbortController?.abort();
        this._validationAbortController = null;
        this.isValidating = false;
        this.hideValidationLoader();
        this.resetPaymentReadiness();

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
        const checkoutForm = jQuery('#order_review');
        const checkoutType = this.getCheckoutType(checkoutForm);
        if (this.isCustomPaymentMethodSelectedInForm(checkoutForm)
            && checkoutType !== 'wallet_button'
            && !(checkoutType === 'super_token' ? this.hasCompleteSuperTokenPaymentData(checkoutForm)
                : this.hasCompleteCustomPaymentData(checkoutForm))) {
            this.handleIncompletePayment();
            return false;
        }
        const serializedForm = checkoutForm.serialize();

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
                    this.resetPaymentReadiness();

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

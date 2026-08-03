/* eslint-disable no-unused-vars, @typescript-eslint/no-unused-vars */
/* globals jQuery, sendMetric */
class MobileCheckoutClassicObserver {
    INIT_TIMEOUT_DELAY = 10000;

    constructor(cardForm, isChoCustomInitiallySelected, onCheckoutUpdate, onOrderPayInit = null) {
        this._cardForm                    = cardForm;
        this._isChoCustomInitiallySelected = isChoCustomInitiallySelected;
        this._onCheckoutUpdate            = onCheckoutUpdate;
        this._onOrderPayInit              = onOrderPayInit;
        this._updateInFlight              = false;
        this._recoveryInFlight            = false;
        this._checkoutUpdateReceived      = false;
        this._structure                   = this._getCheckoutStructureLabel();

        this._boundGuardedUpdate = this._guardedUpdate.bind(this);
        jQuery(document.body).on('cfw_pre_updated_checkout', this._boundGuardedUpdate);
        jQuery(document).on('updated_checkout', this._boundGuardedUpdate);
        jQuery(document.body).on('payment_method_selected', this._boundGuardedUpdate);

        this._emitCheckoutStartedMetric();
        this._scheduleInitFailureDetector();
        this._initIfOrderPayPage();
    }

    _guardedUpdate() {
        this._checkoutUpdateReceived = true;
        clearTimeout(this._initTimeout);
        if (this._updateInFlight) return;
        this._updateInFlight = true;
        const wasRecovery = this._recoveryInFlight;
        this._recoveryInFlight = false;
        let errored = false;
        Promise.resolve()
            .then(() => this._onCheckoutUpdate())
            .catch((error) => {
                errored = true;
                this._cardForm?.removeLoadSpinner?.();
                this._emitUpdateErrorMetric(error);
            })
            .finally(() => {
                this._updateInFlight = false;
                if (wasRecovery && !errored) {
                    this._emitRecoveredMetric(!!this._cardForm?.formMounted);
                }
            });
    }

    _emitCheckoutStartedMetric() {
        if (typeof sendMetric === 'function') {
            sendMetric(
                this._getDeviceLabel(),
                this._structure,
                'mp_custom_checkout_mobile_started',
            );
        }
    }

    _emitUpdateErrorMetric(error) {
        if (typeof sendMetric === 'function') {
            const reason = error?.message || error?.name || 'unknown';
            sendMetric(
                this._getDeviceLabel(),
                `${this._structure}/reason:${reason}`,
                'mp_custom_checkout_mobile_update_error',
            );
        }
    }

    _emitRecoveredMetric(mounted) {
        if (typeof sendMetric === 'function') {
            sendMetric(
                this._getDeviceLabel(),
                `${this._structure}/mounted:${mounted ? 'yes' : 'no'}`,
                'mp_custom_checkout_mobile_recovered',
            );
        }
    }

    _scheduleInitFailureDetector() {
        this._initTimeout = setTimeout(() => {
            if (!this._cardForm?.formMounted && this._isChoCustomInitiallySelected?.()) {
                if (typeof sendMetric === 'function') {
                    const checkoutUpdateLabel = this._checkoutUpdateReceived ? 'yes' : 'no';
                    sendMetric(
                        this._getDeviceLabel(),
                        `${this._structure}/event_received:${checkoutUpdateLabel}`,
                        'mp_custom_checkout_mobile_timeout'
                    );
                }
                if (!this._checkoutUpdateReceived) {
                    this._recoveryInFlight = true;
                    this._guardedUpdate();
                }
            }
        }, this.INIT_TIMEOUT_DELAY);
    }

    _getDeviceLabel() {
        const ua = navigator.userAgent;
        const ios = ua.match(/iPhone OS (\d+[_.]\d*)/);
        if (ios) return `ios_${ios[1].replace(/_/g, '.')}`;
        const android = ua.match(/Android (\d+\.?\d*)/);
        if (android) return `android_${android[1]}`;
        return 'other';
    }

    _getCheckoutStructureLabel() {
        try {
            const form = document.querySelector('form.woocommerce-checkout');
            if (!form)                                       return 'no-wc-form';
            if (this._isFormHiddenByMultiStepPlugin(form))   return 'form-hidden';
            if (this._isFormWrappedOutsideWooCommerce(form)) return 'form-wrapped';
            return 'standard';
        } catch (_) {
            return 'unknown';
        }
    }

    _isFormHiddenByMultiStepPlugin(form) {
        return form.offsetParent === null;
    }

    _isFormWrappedOutsideWooCommerce(form) {
        return !form.closest('.woocommerce') && form.parentElement !== document.body;
    }

    _initIfOrderPayPage() {
        const isOrderPay = document.body?.classList?.contains('woocommerce-order-pay') ||
                           !!document.querySelector('form#order_review');
        if (isOrderPay && typeof this._onOrderPayInit === 'function') {
            this._onOrderPayInit();
        }
    }

    destroy() {
        clearTimeout(this._initTimeout);
        jQuery(document.body).off('cfw_pre_updated_checkout', this._boundGuardedUpdate);
        jQuery(document).off('updated_checkout', this._boundGuardedUpdate);
        jQuery(document.body).off('payment_method_selected', this._boundGuardedUpdate);
    }
}

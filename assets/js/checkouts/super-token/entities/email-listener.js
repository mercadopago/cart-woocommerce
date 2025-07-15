/* globals jQuery */
/* eslint-disable no-unused-vars */
class WCEmailListener {
    EMAIL_FIELD_SELECTOR = 'form[name="checkout"] input[type="email"], #email';
    INTERVAL_TIME = 1500;

    // Attributes
    _callbacks = [];

    // Dependencies
    mpDebounce = null;

    constructor(mpDebounce) {
        this._callbacks = [];
        this.mpDebounce = mpDebounce;
    }

    isValid(email) {
        const result = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i.exec(email);
        return result !== null;
    }

    getEmail() {
        return jQuery(this.EMAIL_FIELD_SELECTOR).val();
    }

    onEmailChange(callback) {
        this._callbacks.push(callback);
        return this;
    }

    setupEmailChangeHandlers() {
        const handleEmailUpdate = () => {
            const email = jQuery(this.EMAIL_FIELD_SELECTOR).val();
            // Notifica todos os callbacks registrados
            if (email) {
                this._callbacks.forEach(callback => callback(email, this.isValid(email)));
            }
        };

        jQuery(document).on(
            'input',
            this.EMAIL_FIELD_SELECTOR,
            this.mpDebounce.inputDebounce(handleEmailUpdate)
        );

        setTimeout(() => handleEmailUpdate(), this.INTERVAL_TIME);
    }
}
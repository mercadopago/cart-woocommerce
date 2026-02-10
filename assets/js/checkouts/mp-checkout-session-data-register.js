/* eslint-disable no-unused-vars */
/* globals wc_mercadopago_checkout_session_data_register_params, MercadoPago */
class MPCheckoutSessionDataRegister {
    static FLOW_ID_KEY = '_mp_flow_id';
    static PUBLIC_KEY_KEY = '_mp_public_key';
    static FLOW_ID = null;
    static PUBLIC_KEY = wc_mercadopago_checkout_session_data_register_params.public_key;
    static LOCALE = wc_mercadopago_checkout_session_data_register_params.locale;
    static HIDDEN_INPUT_NAME_PREFIX = 'mercadopago_checkout_session';

    /**
     * Form selectors from blocks checkout, classic checkout and order pay
    */
    static FORM_SELECTORS = 'form#blocks_checkout_form, form[name=checkout], form.wc-block-components-form, form.wc-block-checkout__form, form#order_review';

    static generateFlowId() {
        const uuidV4 = this.generateUUIDV4();

        if (typeof MercadoPago === 'undefined') {
            return uuidV4
        }

        if (!window.mpSdkInstance) {
            const mp = new MercadoPago(this.PUBLIC_KEY, {
                locale: this.LOCALE,
            });

            window.mpSdkInstance = mp;
        }

        if (typeof window.mpSdkInstance?.getSDKInstanceId === 'function') {
            return window.mpSdkInstance.getSDKInstanceId();
        }

        return uuidV4;
    }

    static generateUUIDV4() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        // For compatibility with older browsers that don't have crypto.randomUUID (like Safari 12.1)
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            )
        }

        // For compatibility with older browsers that don't have crypto.getRandomValues (like IE11 or Safari 12.1)
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(substring) {
            const randomInteger = Math.random() * 16 | 0;
            const uuidV4Digit = substring === 'x' ? randomInteger : (randomInteger & 0x3 | 0x8);
            return uuidV4Digit.toString(16);
        });
    }

    static getDataToHiddenInput() {
        return [
            {
                id: this.FLOW_ID_KEY,
                value: this.FLOW_ID,
                name: `${this.HIDDEN_INPUT_NAME_PREFIX}[${this.FLOW_ID_KEY}]`,
            },
        ];
    }

    static getHiddenInputDataFromBlocksCheckout() {
        return this.getDataToHiddenInput().reduce((acc, data) => {
            acc[data.name] = data.value;
            return acc;
        }, {});
    }

    /**
     * Used to Melidata Client script on checkout funnel
    */
    static registerOnSessionStorage() {
        sessionStorage.setItem(this.FLOW_ID_KEY, this.FLOW_ID);
        sessionStorage.setItem(this.PUBLIC_KEY_KEY, this.PUBLIC_KEY);
    }

    /**
     * Used to send data to the server
    */
    static registerOnHiddenInput() {
        const createHiddenInputElement = (id, value, name) => {
            const input = document.createElement('input');
            input.style.display = 'none';
            input.setAttribute('type', 'hidden');
            input.setAttribute('id', id);
            input.setAttribute('name', name);
            input.setAttribute('value', value);
            return input;
        }

        this.getDataToHiddenInput().forEach(data => {
            document.querySelector(this.FORM_SELECTORS)
                .appendChild(createHiddenInputElement(data.id, data.value, data.name));
        });
    }

    static execute() {
        document.addEventListener('DOMContentLoaded', () => {
            if (!document.querySelector(this.FORM_SELECTORS)) {
                return;
            }

            this.FLOW_ID = this.generateFlowId();

            this.registerOnSessionStorage();
            this.registerOnHiddenInput();
            window.mpHiddenInputDataFromBlocksCheckout = this.getHiddenInputDataFromBlocksCheckout();
        })
    }
}

MPCheckoutSessionDataRegister.execute();

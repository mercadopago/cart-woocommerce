/* globals wc_mercadopago_supertoken_payment_methods_params, Intl */
/* eslint-disable no-unused-vars */
class MPSuperTokenPaymentMethods {
    SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
    CUSTOM_CHECKOUT_TYPE = 'custom';
    COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];
    CUSTOM_CHECKOUT_BLOCKS_SELECTOR = '#radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
    CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_box.payment_method_woo-mercado-pago-custom';
    WALLET_BUTTON_SELECTOR = '.mp-wallet-button-container';
    CHECKOUT_CUSTOM_CONTAINER_SELECTOR = '.mp-checkout-custom-container';
    CHECKOUT_CONTAINER_SELECTOR = '.mp-checkout-container';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    CLICKABLE_AREA_STARTS_ID = 'mp-super-token-clickable-area';
    COLOMBIA_ACCRONYM = 'MCO';
    MEXICO_ACCRONYM = 'MLM';
    SUPER_TOKEN_STYLES = {
        ACCORDION: 'mp-super-token-payment-method__accordion',
        ACCORDION_HEADER: 'mp-super-token-payment-method__accordion-header',
        ACCORDION_TITLE: 'mp-super-token-payment-method__accordion-title',
        ACCORDION_CONTENT: 'mp-super-token-payment-method__accordion-content',
        THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
        PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list',
        PAYMENT_METHOD: 'mp-super-token-payment-method',
        PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
        PAYMENT_METHOD_TITLE: 'mp-super-token-payment-method__title',
        PAYMENT_METHOD_DESCRIPTION: 'mp-super-token-payment-method__description',
        PAYMENT_METHOD_LAST_FOUR_DIGITS: 'mp-super-token-payment-method__last-four-digits',
        PAYMENT_METHOD_SECURITY_CODE_FIELDS: 'mp-super-token-payment-method__security-code-fields',
        PAYMENT_METHOD_SECURITY_CODE: 'mp-super-token-payment-method__security-code',
        PAYMENT_METHOD_EXPIRATION_DATE: 'mp-super-token-payment-method__expiration-date',
        PAYMENT_METHOD_SELECTED: 'mp-super-token-payment-method__selected',
        PAYMENT_METHOD_ACCORDION: 'mp-super-token-payment-method__accordion',
        PAYMENT_METHOD_THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
        PAYMENT_METHOD_ACCORDION_CONTENT_OPEN: 'mp-super-token-payment-method__accordion-content--open',
        PAYMENT_METHOD_VALUE_PROP: 'mp-super-token-payment-method__value-prop',
        PAYMENT_METHOD_DETAILS: 'mp-super-token-payment-method__details',
        PAYMENT_METHOD_HEADER: 'mp-super-token-payment-method__header',
        PAYMENT_METHOD_HIDE: 'mp-super-token-hide',
        REMOVE_BOX_SHADOW: 'mp-box-shadow-none'
    }
    YELLOW_WALLET_PATH = wc_mercadopago_supertoken_payment_methods_params.yellow_wallet_path;
    WHITE_CARD_PATH = wc_mercadopago_supertoken_payment_methods_params.white_card_path;
    PAYMENT_METHODS_LIST_TEXT = wc_mercadopago_supertoken_payment_methods_params.payment_methods_list_text;
    LAST_DIGITS_TEXT = wc_mercadopago_supertoken_payment_methods_params.last_digits_text;
    NEW_CARD_TEXT = wc_mercadopago_supertoken_payment_methods_params.new_card_text;
    ACCOUNT_MONEY_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_text;
    ACCOUNT_MONEY_INVESTED_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_invested_text;
    INTEREST_FREE_PART_ONE_TEXT = wc_mercadopago_supertoken_payment_methods_params.interest_free_part_one_text;
    INTEREST_FREE_PART_TWO_TEXT = wc_mercadopago_supertoken_payment_methods_params.interest_free_part_two_text;
    BANK_INTEREST_OPTION_TEXT = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.bank_interest_option_text;
    BANK_INTEREST_HINT_TEXT = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.bank_interest_hint_text;
    INSTALLMENTS_INPUT_TITLE = wc_mercadopago_supertoken_payment_methods_params.input_title.installments;
    INSTALLMENTS_PLACEHOLDER = wc_mercadopago_supertoken_payment_methods_params.placeholders.installments;
    INSTALLMENTS_REQUIRED_MESSAGE = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.required;
    INSTALLMENTS_HINT_TEXT = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.hint;
    INSTALLMENTS_INTEREST_FREE_OPTION_TEXT = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.interest_free_option_text;
    SECURITY_CODE_INPUT_TITLE_TEXT = wc_mercadopago_supertoken_payment_methods_params.security_code_input_title_text;
    SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS = wc_mercadopago_supertoken_payment_methods_params.security_code_placeholder_text_3_digits;
    SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS = wc_mercadopago_supertoken_payment_methods_params.security_code_placeholder_text_4_digits;
    SECURITY_CODE_ERROR_MESSAGES = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.securityCode;
    SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS = wc_mercadopago_supertoken_payment_methods_params.security_code_tooltip_text_3_digits;
    SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS = wc_mercadopago_supertoken_payment_methods_params.security_code_tooltip_text_4_digits;
    SITE_ID = wc_mercadopago_supertoken_payment_methods_params.site_id;
    CURRENCY = wc_mercadopago_supertoken_payment_methods_params.currency;
    INTL = wc_mercadopago_supertoken_payment_methods_params.intl;
    MERCADO_PAGO_CARD_NAME = wc_mercadopago_supertoken_payment_methods_params.mercado_pago_card_name;

    NEW_CARD_TYPE = 'new_card';
    CREDIT_CARD_TYPE = 'credit_card';
    DEBIT_CARD_TYPE = 'debit_card';
    ACCOUNT_MONEY_TYPE = 'account_money';
    PREPAID_CARD_TYPE = 'prepaid_card';

    PAYMENT_METHODS_ORDER = wc_mercadopago_supertoken_payment_methods_params.payment_methods_order;

    PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
    PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';

    PAYMENT_METHODS_THUMBNAILS = wc_mercadopago_supertoken_payment_methods_params.payment_methods_thumbnails;

    // Attributes
    paymentMethods = [];
    superToken = null;
    securityFieldsActiveInstance = null;
    activePaymentMethod = null;
    amount = null;

    // Dependencies
    mpSdkInstance = null;
    mpSuperTokenMetrics = null;

    constructor(mpSdkInstance, mpSuperTokenMetrics) {
        this.mpSdkInstance = mpSdkInstance;
        this.mpSuperTokenMetrics = mpSuperTokenMetrics;
    }

    reset() {
        this.paymentMethods = [];
        this.showWalletButton();
        this.removePaymentMethodElements();
        this.removeAccordion();
        this.deselectAllPaymentMethods();
        this.removePaymentMethodsListClasses();
    }

    storePaymentMethodsInMemory(accountPaymentMethods) {
        this.paymentMethods = accountPaymentMethods;
    }

    getStoredPaymentMethods() {
        return this.paymentMethods;
    }

    hasStoredPaymentMethods() {
        return this.paymentMethods.length > 0;
    }

    setSuperToken(token) {
        this.superToken = token;
    }

    getSuperToken() {
        return this.superToken;
    }

    paymentMethodsAreRendered() {
        return !!document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`);
    }

    removeClickableAreas() {
        const clickableAreas = document.querySelectorAll(`[id^=${this.CLICKABLE_AREA_STARTS_ID}]`);

        clickableAreas.forEach((clickableArea) => {
            clickableArea.firstChild.style.pointerEvents = 'auto';
            clickableArea.replaceWith(clickableArea.firstChild);
        });
    }

    async getAccountPaymentMethods(token) {
        this.setSuperToken(token);

        return this.mpSdkInstance.getAccountPaymentMethods(token);
    }

    getCustomCheckoutEntireElement() {
        return document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_SELECTOR)
            || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_SELECTOR);
    }

    getWalletButtonElement() {
        return document.querySelector(this.WALLET_BUTTON_SELECTOR);
    }

    hideWalletButton() {
        const walletButtonElement = this.getWalletButtonElement();

        if (!walletButtonElement) return;

        walletButtonElement.style.display = 'none';
    }

    showWalletButton() {
        const walletButtonElement = this.getWalletButtonElement();

        if (!walletButtonElement) return;

        walletButtonElement.style.display = 'flex';
    }

    removeAccordion() {
        const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);
        const accordionHeader = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);

        accordionElement?.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR).classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
        accordionElement?.classList.remove(this.SUPER_TOKEN_STYLES.ACCORDION);
        accordionHeader?.remove();
    }

    removePaymentMethodsListClasses() {
        const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();

        customCheckoutEntireElement?.parentElement?.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
        customCheckoutEntireElement?.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        customCheckoutEntireElement?.parentElement.classList.remove(this.SUPER_TOKEN_STYLES.REMOVE_BOX_SHADOW);
        customCheckoutEntireElement?.removeAttribute('role');
        customCheckoutEntireElement?.removeAttribute('aria-label');
        customCheckoutEntireElement?.removeAttribute('tabindex');
    }

    removePaymentMethodElements() {
        document
            .querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD}`)
            .forEach(element => element.remove());
    }

    closeAccordion() {
        const accordionContent = document.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR);
        const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION}`);

        if (accordionContent) {
            accordionContent.classList.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);
        }

        if (accordionElement) {
            accordionElement.style.height = '48px';
        }
    }

    deselectAllPaymentMethods() {
        document
            .querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`)
            .forEach(element => {
                element
                    .classList
                    .remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);

                element
                    .setAttribute('aria-selected', 'false');
            });
    }

    selectNewCardAccordion() {
        const accordionElement = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION}`);
        const accordionContent = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT}`);
        const accordionHeader = document.querySelector(`.${this.SUPER_TOKEN_STYLES.ACCORDION_HEADER}`);

        if (!accordionElement || !accordionContent || !accordionHeader) {
            console.warn('Accordion elements not found');
            return;
        }

        accordionElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
        accordionElement.style.height = '48px';
        accordionHeader.setAttribute('aria-selected', 'true');

        setTimeout(() => {
            accordionContent.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_ACCORDION_CONTENT_OPEN);

            requestAnimationFrame(() => {
                accordionElement.style.height = 'auto';
                accordionElement.style.overflow = 'visible';
            });
        }, 10);
    }

    selectPaymentMethod(paymentMethodElement) {
        paymentMethodElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
        paymentMethodElement.setAttribute('aria-selected', 'true');
    }

    setCheckoutType(type) {
        document.querySelector(this.CHECKOUT_TYPE_SELECTOR).value = type;
    }

    showPaymentMethodDetails(paymentMethodElement) {
        paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
            ?.classList
            ?.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);
    }

    hideAllPaymentMethodDetails() {
        document
            .querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
            ?.forEach(element => {
                element
                    ?.classList
                    ?.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);
            });
    }

    fillCardTokenFields(paymentMethod) {
        document.getElementById('paymentMethodId').value = paymentMethod.id;
        document.getElementById('paymentTypeId').value = paymentMethod.type;
        document.getElementById('cardTokenId').value = paymentMethod.token;
    }

    paymentMethodAlreadySelected(paymentMethod) {
        return this.activePaymentMethod?.id === paymentMethod.id;
    }

    storeActivePaymentMethod(paymentMethod) {
        this.activePaymentMethod = paymentMethod;
    }

    onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod) {
        if (this.paymentMethodAlreadySelected(paymentMethod)) {
            return;
        }

        this.storeActivePaymentMethod(paymentMethod);
        this.hideAllPaymentMethodDetails();
        this.closeAccordion();
        this.deselectAllPaymentMethods();
        this.selectPaymentMethod(paymentMethodElement);
        this.fillCardTokenFields(paymentMethod);
        this.setCheckoutType(this.SUPER_TOKEN_CHECKOUT_TYPE);
        this.showPaymentMethodDetails(paymentMethodElement);
        this.mountSecurityCodeField(paymentMethod);

        setTimeout(() => {
          document.dispatchEvent(
            this.selectedSupertokenMethodEvent(false)
          )
        }, 50);
    }

    onSelectNewCardPaymentMethod() {
        if (this.paymentMethodAlreadySelected({ id: this.NEW_CARD_TYPE })) {
            return;
        }

        this.removeClickableAreas();
        this.storeActivePaymentMethod({ id: this.NEW_CARD_TYPE });
        this.deselectAllPaymentMethods();
        this.hideAllPaymentMethodDetails();
        this.unmountActiveSecurityCodeInstance();
        this.selectNewCardAccordion();
        this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);

        setTimeout(() => {
            this.mountCardForm();
        }, 50);

        setTimeout(() => {
          document.dispatchEvent(
            this.selectedSupertokenMethodEvent(true)
          )
        }, 50);
    }

    selectedSupertokenMethodEvent = (isNewCardSelected) => {
      return  new CustomEvent('supertoken_payment_method_selected',
        { detail: {
          new_card_selected: isNewCardSelected,
          }
        }
      )
    }

    isCreditCard(paymentMethod) {
        return paymentMethod?.type === this.CREDIT_CARD_TYPE;
    }

    isDebitCard(paymentMethod) {
        return paymentMethod?.type === this.DEBIT_CARD_TYPE;
    }

    isAccountMoney(paymentMethod) {
        return paymentMethod?.type === this.ACCOUNT_MONEY_TYPE;
    }

    isPrepaidCard(paymentMethod) {
        return paymentMethod?.type === this.PREPAID_CARD_TYPE;
    }

    isMercadoPagoCard(paymentMethod) {
        return paymentMethod?.type === this.PREPAID_CARD_TYPE && paymentMethod?.issuer?.name?.toLowerCase()?.includes('mercado pago');
    }

    userHasAccountMoney(accountMoneyPaymentMethod) {
        return accountMoneyPaymentMethod.has_account_money;
    }

    userHasAccountMoneyInvested(accountMoneyPaymentMethod) {
        return accountMoneyPaymentMethod.has_account_money_invested;
    }

    buildAccountMoneyName(accountMoneyPaymentMethod) {
        if (this.getSiteId() !== this.MEXICO_ACCRONYM) {
            return this.ACCOUNT_MONEY_TEXT;
        }

        if (this.userHasAccountMoney(accountMoneyPaymentMethod)) {
            if (this.userHasAccountMoneyInvested(accountMoneyPaymentMethod)) {
                return this.ACCOUNT_MONEY_TEXT.concat(` + ${this.ACCOUNT_MONEY_INVESTED_TEXT}`);
            }

            return this.ACCOUNT_MONEY_TEXT;
        }

        return this.ACCOUNT_MONEY_INVESTED_TEXT;
    }

    numberOfInstallmentsWithoutFee(paymentMethod) {
        if (!this.isCreditCard(paymentMethod) || !paymentMethod.installments?.length) {
            return 0;
        }

        const installmentsWithoutFee = paymentMethod.installments.filter(installment => installment.installment_rate === 0);
        return installmentsWithoutFee[installmentsWithoutFee.length - 1].installments;
    }

    buildPaymentMethodAriaLabel(paymentMethod, lastFourDigits, installmentsWithoutFee) {
        const name = paymentMethod.name;
        const lastFourDigitsText = lastFourDigits ? ` ${this.LAST_DIGITS_TEXT} ${lastFourDigits}` : '';
        const installmentsWithoutFeeText = installmentsWithoutFee
            ? ` ${this.INTEREST_FREE_PART_ONE_TEXT} ${installmentsWithoutFee}${this.getSiteId() === 'MLB' ? 'x' : ''} ${this.INTEREST_FREE_PART_TWO_TEXT}`
            : '';

        return `${name}${lastFourDigitsText}${installmentsWithoutFeeText}`;
    }

    getSiteId() {
        return this.SITE_ID?.toUpperCase();
    }

    needsBankInterestDisclaimer() {
        return this.COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER.includes(this.getSiteId());
    }

    formatCurrency(value) {
        const formatter = new Intl.NumberFormat(this.INTL, {
          currency: this.CURRENCY,
          style: 'currency',
          currencyDisplay: 'narrowSymbol',
        });

        let formattedValue = formatter.format(value);

        if (this.getSiteId() === 'MLM') {
            formattedValue = formattedValue.replace(/^(\D+)/, '$1 ');
        }

        return formattedValue;
    }

    buildInstallmentTitle(installment) {
        const installmentNumber = installment.installments;
        const installmentAmount = this.formatCurrency(installment.installment_amount);
        const installmentRate = installment.installment_rate !== 0;
        const totalAmount = this.formatCurrency(installment.total_amount);
        const bankInterestDisclaimer = this.needsBankInterestDisclaimer()
            ? ` + ${this.BANK_INTEREST_OPTION_TEXT}`
            : '';

        if (installmentNumber === 1) {
            return `${installmentNumber}x ${totalAmount}`;
        }

        if (installmentRate && !bankInterestDisclaimer) {
            return `${installmentNumber}x ${installmentAmount} (${totalAmount})`;
        }

        if (bankInterestDisclaimer) {
            return `${installmentNumber}x ${installmentAmount} (${totalAmount})${bankInterestDisclaimer}`;
        }

        return `${installmentNumber}x ${installmentAmount} ${this.INSTALLMENTS_INTEREST_FREE_OPTION_TEXT}`;
    }

    getInstallmentsLimit(installments) {
        return this.getSiteId() === this.COLOMBIA_ACCRONYM
            ? installments.slice(0, Math.min(6, installments.length))
            : installments;
    }

    normalizeInstallments(installments) {
        return this.getInstallmentsLimit(installments)
            .map((installment) => {
                const item = {
                    value: `${installment.installments}`,
                    title: this.buildInstallmentTitle(installment),
                };

                if (this.getSiteId() === 'MLA' && installment.labels) {
                    const taxInfo = this.parseTaxInfo(installment.labels);
                    if (taxInfo) {
                        item.taxInfo = taxInfo;
                    }
                }

                return item;
            });
    }

    parseTaxInfo(labels) {
        if (!labels || !Array.isArray(labels)) return null;

        const taxInfo = {
            cft: null,
            tna: null,
            tea: null
        };

        const validateAndCleanNumberFromLabel = (value) => {
            if (!value) return null;
            const cleaned = value.replace('%', '').trim();
            const numberPattern = /^\d+([,.]\d+)?$/;
            return numberPattern.test(cleaned) ? cleaned : null;
        };

        labels.forEach(label => {
            if (typeof label !== 'string') return;

            const parts = label.split('|');
            parts.forEach(part => {
                if (part.includes('CFT_')) {
                    const splitResult = part.split('CFT_');
                    if (splitResult.length > 1 && splitResult[1]) {
                        const validatedValue = validateAndCleanNumberFromLabel(splitResult[1]);
                        if (validatedValue) {
                            taxInfo.cft = validatedValue;
                        }
                    }
                } else if (part.includes('TEA_')) {
                    const splitResult = part.split('TEA_');
                    if (splitResult.length > 1 && splitResult[1]) {
                        const validatedValue = validateAndCleanNumberFromLabel(splitResult[1]);
                        if (validatedValue) {
                            taxInfo.tea = validatedValue;
                        }
                    }
                } else if (part.includes('TNA_')) {
                    const splitResult = part.split('TNA_');
                    if (splitResult.length > 1 && splitResult[1]) {
                        const validatedValue = validateAndCleanNumberFromLabel(splitResult[1]);
                        if (validatedValue) {
                            taxInfo.tna = validatedValue;
                        }
                    }
                }
            });
        });

        return taxInfo;
    }

    getInstallmentsDropdownValue(paymentMethod) {
        return paymentMethod.installments.find(installment => installment.installments === paymentMethod.installments).installments;
    }

    securityCodeIsRequired(securityCodeSettings) {
        if (!securityCodeSettings) {
            return false;
        }

        return securityCodeSettings?.mode === 'mandatory';
    }

    addDropdownEventListener(dropdownElement) {
        dropdownElement?.addEventListener('mp-open-dropdown', () => {
            const checkoutContainer = document.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)

            if (!checkoutContainer) {
                return;
            }

            checkoutContainer.style.zIndex = '9999 !important';
        });

        dropdownElement?.addEventListener('mp-close-dropdown', () => {
            const checkoutContainer = document.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR)

            if (!checkoutContainer) {
                return;
            }

            checkoutContainer.style.zIndex = '1';
        });
    }

    buildSecurityCodeInnerHTML(paymentMethod) {
        if (!this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
            return '';
        }

        const securityCodeTooltipText = paymentMethod?.security_code_settings?.length === 3
            ? this.SECURITY_CODE_TOOLTIP_TEXT_3_DIGITS
            : this.SECURITY_CODE_TOOLTIP_TEXT_4_DIGITS;

        return `
            <div
                id="mp-super-token-security-code-container-${paymentMethod.token}"
                class="mp-super-token-security-code-container"
            >
                <label
                    tabindex="0"
                    class="mp-super-token-security-code-label"
                >${this.SECURITY_CODE_INPUT_TITLE_TEXT}</label>
                <div
                    id="mp-super-token-security-code-input-${paymentMethod.token}"
                    class="mp-super-token-security-code-input"
                ></div>
                <span
                    tabindex="0"
                    aria-label="${securityCodeTooltipText}"
                    class="mp-super-token-security-code-tooltip"
                    role="tooltip"
                    data-tooltip="${securityCodeTooltipText}"
                >?</span>
                <div
                    id="mp-input-with-tooltip-helper-error"
                    tabindex="0"
                    class="mp-input-with-tooltip-helper-error"
                >
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="12" height="12" rx="6" fill="#CC1818"/>
                        <path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>
                        <path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>
                    </svg>
                    <span id="mp-super-token-security-code-error-message"></span>
                </div>
            </div>
        `
    }

    buildCreditCardDetailsInnerHTML(paymentMethod) {
        if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod) && !this.isPrepaidCard(paymentMethod)) {
            return '';
        }

        const section = document.createElement('section');
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS);
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

        if (this.isCreditCard(paymentMethod)) {
            const installments = this.normalizeInstallments(paymentMethod.installments);

            section.innerHTML = `
                <andes-dropdown
                    label="${this.INSTALLMENTS_INPUT_TITLE}"
                    placeholder="${this.INSTALLMENTS_PLACEHOLDER}"
                    required-message="${this.INSTALLMENTS_REQUIRED_MESSAGE}"
                    items='${JSON.stringify(installments)}'
                    site-id="${this.getSiteId()}"
                    ${this.needsBankInterestDisclaimer() ? `hint="${this.INSTALLMENTS_HINT_TEXT}"` : ''}
                ></andes-dropdown>
            `;
        }

        section.innerHTML += this.buildSecurityCodeInnerHTML(paymentMethod);

        return section.outerHTML;
    }

    mountCardForm() {
        if (window.mpCustomCheckoutHandler.cardForm.formMounted) {
            return;
        }

        window.mpCustomCheckoutHandler.cardForm.initCardForm(this.getAmount());
    }

    unmountCardForm() {
        if (window.mpCustomCheckoutHandler.cardForm.formMounted) {
            window.mpCustomCheckoutHandler.cardForm.form.unmount();
        }
    }

    unmountActiveSecurityCodeInstance() {
        if (this.securityFieldsActiveInstance) {
            this.securityFieldsActiveInstance.unmount();
            this.securityFieldsActiveInstance = null;
        }
    }

    storeActiveSecurityCodeInstance(securityCodeInstance) {
        this.securityFieldsActiveInstance = securityCodeInstance;
    }

    storeAmount(amount) {
        this.amount = amount;
    }

    getAmount() {
        return this.amount;
    }

    async updateSecurityCode() {
        const paymentMethod = this.activePaymentMethod;

        if (!paymentMethod || !this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
            return;
        }

        try {
            const { card_id } = await this.mpSdkInstance.getCardId(this.getSuperToken(), paymentMethod.token);

            const { id } = await this.mpSdkInstance.fields.createCardToken({ cardId: card_id });

            await this.mpSdkInstance.updatePseudotoken(this.getSuperToken(), paymentMethod.token, id);
        } catch (error) {
            this.mpSuperTokenMetrics.errorToUpdateSecurityCode(error, paymentMethod);
        }
    }

    toggleSecurityCodeErrorMessage(errorMessage, paymentMethod) {
        const securityCodeContainerElement = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
        if (!securityCodeContainerElement) {
            return;
        }

        const securityCodeLabelElement = securityCodeContainerElement.querySelector('label');
        const securityCodeErrorMessageElement = securityCodeContainerElement.querySelector('#mp-super-token-security-code-error-message');
        const helperErrorElement = securityCodeContainerElement.querySelector('#mp-input-with-tooltip-helper-error');
        const securityCodeInputElement = securityCodeContainerElement.querySelector('.mp-super-token-security-code-input');

        // Clean up
        securityCodeLabelElement.classList.remove('error');
        securityCodeContainerElement.classList.remove('error');
        securityCodeInputElement.classList.remove('error');
        helperErrorElement.style.display = 'none';
        securityCodeErrorMessageElement.innerHTML = '';

        if (!errorMessage) {
            return;
        }

        // Set error
        securityCodeLabelElement.classList.add('error');
        securityCodeContainerElement.classList.add('error');
        securityCodeInputElement.classList.add('error');

        const displayMessage = this.SECURITY_CODE_ERROR_MESSAGES[errorMessage] ?? errorMessage;
        securityCodeErrorMessageElement.innerHTML = displayMessage;
        helperErrorElement.style.display = 'flex';
    }

    mountSecurityCodeField(paymentMethod) {
        if (!this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
            return;
        }

        this.unmountCardForm();
        this.unmountActiveSecurityCodeInstance();

        const waitSecurityCodeFieldMountInterval = setInterval(() => {
            if (document.getElementById(`mp-super-token-security-code-input-${paymentMethod.token}`)) {
                clearInterval(waitSecurityCodeFieldMountInterval);

                const securityCodePlaceholderText = paymentMethod?.security_code_settings?.length === 3
                    ? this.SECURITY_CODE_PLACEHOLDER_TEXT_3_DIGITS
                    : this.SECURITY_CODE_PLACEHOLDER_TEXT_4_DIGITS;

                const securityCodeField = this.mpSdkInstance.fields.create('securityCode', {
                    placeholder: securityCodePlaceholderText,
                    ariaRequired: true,
                    style: {
                        'font-size': '16px',
                        height: '48px',
                        padding: '14px',
                    }
                })
                    .mount(`mp-super-token-security-code-input-${paymentMethod.token}`)
                    .on('error', (e) => this.mpSuperTokenMetrics.errorToMountCVVField(e, paymentMethod))
                    .on('ready', () => {
                        securityCodeField.update({
                            settings: paymentMethod?.security_code_settings
                        })

                        this.storeActiveSecurityCodeInstance(securityCodeField);
                    })
                    .on('validityChange', (e) => {
                        if (e.errorMessages.length === 0) {
                            this.toggleSecurityCodeErrorMessage('', paymentMethod);
                        }

                        const errorMessage = e.errorMessages[0].cause;

                        this.toggleSecurityCodeErrorMessage(errorMessage, paymentMethod);
                    })
            }
        }, 200)
    }

    createPaymentMethodElement(paymentMethod) {
        const lastFourDigits = paymentMethod?.card?.card_number?.last_four_digits;
        const paymentMethodElement = document.createElement('article');
        const installmentsWithoutFee = this.numberOfInstallmentsWithoutFee(paymentMethod);
        const ariaLabel = this.buildPaymentMethodAriaLabel(paymentMethod, lastFourDigits, installmentsWithoutFee);

        paymentMethodElement.id = paymentMethod.token;
        paymentMethodElement.dataset.type = paymentMethod.type;
        paymentMethodElement.dataset.id = paymentMethod.id;
        paymentMethodElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD);
        paymentMethodElement.setAttribute('aria-label', ariaLabel);
        paymentMethodElement.setAttribute('tabindex', '0');
        paymentMethodElement.setAttribute('role', 'option');
        paymentMethodElement.setAttribute('aria-selected', 'false');
        paymentMethodElement.innerHTML = `
            <section class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HEADER}">
                <section>
                    <figure class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_THUMBNAIL}">
                        <img src="${paymentMethod.thumbnail}" alt="${paymentMethod.name}">
                    </figure>
                    <article class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_CONTENT}">
                        <span class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_TITLE}">${paymentMethod.name}</span>
                        ${this.isCreditCard(paymentMethod) && installmentsWithoutFee > 1 ?
                            `<span
                                aria-hidden="true"
                                class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_VALUE_PROP}">
                                ${this.INTEREST_FREE_PART_ONE_TEXT} ${installmentsWithoutFee}${this.getSiteId() === 'MLB' ? 'x' : ''} ${this.INTEREST_FREE_PART_TWO_TEXT}
                            </span>`
                            : ''
                        }
                    </article>
                </section>
                ${lastFourDigits
                    ? `
                        <section class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}">
                            <span>**** ${lastFourDigits}</span>
                        </section>
                    `
                    : ''
                }
            </section>
            ${this.buildCreditCardDetailsInnerHTML(paymentMethod)}
        `;

        paymentMethodElement.addEventListener('click', this.onSelectSuperTokenPaymentMethod.bind(this, paymentMethodElement, paymentMethod));
        paymentMethodElement.addEventListener('keydown', (e) => {
            if (e.code == 'Space' || e.key == 'Enter') {
                e.preventDefault();
                this.onSelectSuperTokenPaymentMethod.call(this, paymentMethodElement, paymentMethod)
            }
        });

        if (this.isCreditCard(paymentMethod)) {
            const dropdownElement = paymentMethodElement.querySelector('andes-dropdown');

            this.addDropdownEventListener(dropdownElement);

            dropdownElement?.addEventListener('change', (event) => {
                const selectedItem = event.detail;
                if (selectedItem) {
                    dropdownElement.value = selectedItem.value;
                    document.getElementById('cardInstallments').value = selectedItem.value;
                }
            });
        }

        return paymentMethodElement;
    }

    convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement) {
        const creditCardFormElement = customCheckoutEntireElement.querySelector(this.CHECKOUT_CONTAINER_SELECTOR);

        const createAccordionHeader = () => {
            const accordionHeader = document.createElement('section');
            accordionHeader.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_HEADER);
            accordionHeader.setAttribute('aria-label', this.NEW_CARD_TEXT);
            accordionHeader.setAttribute('tabindex', '0');
            accordionHeader.setAttribute('role', 'option');
            accordionHeader.setAttribute('aria-selected', 'false');
            accordionHeader.innerHTML = `
                <img src="${this.WHITE_CARD_PATH}">
                <span class="${this.SUPER_TOKEN_STYLES.ACCORDION_TITLE}">${this.NEW_CARD_TEXT}</span>
            `;
            accordionHeader.addEventListener('click', this.onSelectNewCardPaymentMethod.bind(this));
            accordionHeader.addEventListener('keydown', (e) => {
                if (e.code == 'Space' || e.key == 'Enter') {
                    e.preventDefault();
                    this.onSelectNewCardPaymentMethod.call(this)
                }
            });

            return accordionHeader;
        }

        const addAccordionClasses = (accordionElement) => {
            accordionElement.classList.add(this.SUPER_TOKEN_STYLES.ACCORDION);
            accordionElement.querySelector(this.CHECKOUT_CUSTOM_CONTAINER_SELECTOR).classList.add(this.SUPER_TOKEN_STYLES.ACCORDION_CONTENT);
        }

        addAccordionClasses(creditCardFormElement);
        const accordionHeader = createAccordionHeader();
        creditCardFormElement.addEventListener('keyup', (e) => {
            if (e.key == 'Tab') {
                accordionHeader.focus();
            }
        });

        creditCardFormElement.appendChild(accordionHeader);
        return creditCardFormElement;
    }

    convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement) {
        customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        customCheckoutEntireElement.setAttribute('role', 'listbox');
        customCheckoutEntireElement.setAttribute('aria-label', this.PAYMENT_METHODS_LIST_TEXT);
        customCheckoutEntireElement.setAttribute('tabindex', '0');
        customCheckoutEntireElement.parentElement.classList.add('mp-box-shadow-none');
    }

    reorderAccountPaymentMethods(accountPaymentMethods) {
        const MAX_CREDIT_CARDS = 3;
        
        const cardOptions = accountPaymentMethods.filter(pm => this.isCreditCard(pm) || this.isDebitCard(pm) || this.isPrepaidCard(pm));
        const accountMoneyOption = accountPaymentMethods.find(pm => this.isAccountMoney(pm));

        const limitedCardsOptions = cardOptions.slice(0, MAX_CREDIT_CARDS);
        const orderPreference = this.PAYMENT_METHODS_ORDER || this.PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST;
        const isAccountMoneyFirst = orderPreference === this.PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST && accountMoneyOption;

        return {
            beforeNewCardForm: isAccountMoneyFirst ? [accountMoneyOption] : limitedCardsOptions,
            afterNewCardForm: isAccountMoneyFirst ? limitedCardsOptions : [accountMoneyOption],
        };
    }

    normalizeAccountPaymentMethods(accountPaymentMethods) {
        return accountPaymentMethods.map(paymentMethod => {
            if (this.isAccountMoney(paymentMethod)) {
                paymentMethod.thumbnail = this.YELLOW_WALLET_PATH;
                paymentMethod.name = this.buildAccountMoneyName(paymentMethod);
            }

            if (this.isMercadoPagoCard(paymentMethod)) {
                paymentMethod.name = this.MERCADO_PAGO_CARD_NAME;
            }

            if (this.isPrepaidCard(paymentMethod)) {
                paymentMethod.thumbnail = this.PAYMENT_METHODS_THUMBNAILS[paymentMethod.id] || this.WHITE_CARD_PATH;
            }

            if (this.isCreditCard(paymentMethod) || this.isDebitCard(paymentMethod)) {
                paymentMethod.thumbnail = this.PAYMENT_METHODS_THUMBNAILS[paymentMethod.id] || this.WHITE_CARD_PATH;
                paymentMethod.name = (paymentMethod.issuer.name ?? paymentMethod.name) + ' ' + (this.isCreditCard(paymentMethod) ? 'Crédito' : 'Débito');
            }

            return paymentMethod;
        })
    }

    onCustomCheckoutWasRendered(customCheckoutEntireElement, accountPaymentMethods) {
        this.hideWalletButton();
        this.convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement);
        this.convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement);

        const reorderedAccountPaymentMethods = this.reorderAccountPaymentMethods(accountPaymentMethods);

        const beforeNewCardForm = reorderedAccountPaymentMethods.beforeNewCardForm;
        const afterNewCardForm = reorderedAccountPaymentMethods.afterNewCardForm;

        const normalizedBeforeNewCardForm = this.normalizeAccountPaymentMethods(beforeNewCardForm);
        const normalizedAfterNewCardForm = this.normalizeAccountPaymentMethods(afterNewCardForm);

        normalizedBeforeNewCardForm.forEach((paymentMethod) => {
            customCheckoutEntireElement.insertBefore(
                this.createPaymentMethodElement(paymentMethod),
                customCheckoutEntireElement.firstChild
            );
        });

        normalizedAfterNewCardForm.forEach((paymentMethod) => {
            customCheckoutEntireElement.appendChild(
                this.createPaymentMethodElement(paymentMethod)
            );
        });

        customCheckoutEntireElement.focus();
    }

    forceSecurityCodeValidation(paymentMethod) {
        const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${paymentMethod.token}`);
        if (!securityCodeContainer) {
            return;
        }

        if (!this.securityFieldsActiveInstance) {
            this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
            return;
        }

        this.securityFieldsActiveInstance.focus();
        setTimeout(() => {
            this.securityFieldsActiveInstance.blur();
            setTimeout(() => {
                const hasError = securityCodeContainer.classList.contains('error');
                if (!hasError) {
                    this.toggleSecurityCodeErrorMessage('invalid_type', paymentMethod);
                }
            }, 100);
        }, 50);
    }

    forceShowValidationErrors() {
        window.mpCustomCheckoutHandler.cardForm.removeLoadSpinner();
        
        if (!this.activePaymentMethod) {
            return;
        }

        if (!this.isCreditCard(this.activePaymentMethod)) {
            return;
        }

        const paymentMethodElement = document.getElementById(this.activePaymentMethod.token);
        if (!paymentMethodElement) {
            return;
        }

        const installmentsDropdown = paymentMethodElement.querySelector('andes-dropdown');
        if (installmentsDropdown) {
            installmentsDropdown.state.hasInteracted = true;
            installmentsDropdown.validate();
        }

        if (this.securityCodeIsRequired(this.activePaymentMethod?.security_code_settings)) {
            this.forceSecurityCodeValidation(this.activePaymentMethod);
        }
    }

    isSelectedPaymentMethodValid() {
        try {
            if (this.isAccountMoney(this.activePaymentMethod) || this.isDebitCard(this.activePaymentMethod) || this.isPrepaidCard(this.activePaymentMethod)) {
                return true;
            }

            if (this.activePaymentMethod.id === this.NEW_CARD_TYPE) {
                return true;
            }

            const paymentMethodElement = document.getElementById(this.activePaymentMethod.token);
            if (!paymentMethodElement) {
                return false;
            }

            const installmentsDropdown = paymentMethodElement.querySelector('andes-dropdown');
            if (!installmentsDropdown) {
                return false;
            }

            installmentsDropdown.state.hasInteracted = true;
            const isInstallmentsValid = installmentsDropdown.validate();
            if (!isInstallmentsValid) {
                return false;
            }

            if (!this.securityCodeIsRequired(this.activePaymentMethod?.security_code_settings)) {
                return true;
            }

            if (!this.securityFieldsActiveInstance) {
                return false;
            }

            const securityCodeContainer = document.getElementById(`mp-super-token-security-code-container-${this.activePaymentMethod.token}`);
            if (!securityCodeContainer) {
                return false;
            }

            const hasError = securityCodeContainer.classList.contains('error');
            const helperError = securityCodeContainer.querySelector('#mp-input-with-tooltip-helper-error');
            const isErrorVisible = helperError && helperError.style.display === 'flex';

            if (hasError || isErrorVisible) {
                return false;
            }

            return true;
        } catch (error) {
            console.error('Error validating selected payment method after submit: ', error);
            return false;
        }
    }

    renderAccountPaymentMethods(accountPaymentMethods, amount) {
        this.storeAmount(amount);
        this.storeActivePaymentMethod(null);

        if (this.paymentMethodsAreRendered()) return;

        if (!this.hasStoredPaymentMethods()) this.storePaymentMethodsInMemory(accountPaymentMethods);

        const waitCustomCheckoutEntireElementInterval = setInterval(
            () => {
                const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();

                if (!customCheckoutEntireElement) return;

                clearInterval(waitCustomCheckoutEntireElementInterval);

                this.onCustomCheckoutWasRendered(
                    customCheckoutEntireElement,
                    accountPaymentMethods
                );
            },
            100
        )

        setTimeout(() => {
          const sdkInstanceId = this.mpSuperTokenMetrics.getSdkInstanceId();
          document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId } }));
        }, 500);
    }
}
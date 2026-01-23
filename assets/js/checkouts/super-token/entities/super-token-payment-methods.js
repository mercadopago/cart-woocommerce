/* globals wc_mercadopago_supertoken_payment_methods_params, Intl, MPCheckoutFieldsDispatcher, MPSuperTokenErrorCodes */
/* eslint-disable no-unused-vars */
class MPSuperTokenPaymentMethods {
    SUPER_TOKEN_CHECKOUT_TYPE = 'super_token';
    CUSTOM_CHECKOUT_TYPE = 'custom';
    COUNTRIES_WITH_BANK_INTEREST_DISCLAIMER = ['MCO', 'MPE', 'MLC'];
    CUSTOM_CHECKOUT_BLOCKS_SELECTOR = '#radio-control-wc-payment-method-options-woo-mercado-pago-custom__content';
    CUSTOM_CHECKOUT_CLASSIC_SELECTOR = '.payment_box.payment_method_woo-mercado-pago-custom';
    CARD_FLAGS_SELECTOR = '.mp-checkout-custom-card-flags';
    CHECKOUT_CUSTOM_CONTAINER_SELECTOR = '.mp-checkout-custom-container';
    CHECKOUT_CONTAINER_SELECTOR = '#mp-checkout-custom-container.mp-checkout-container';
    CHECKOUT_TYPE_SELECTOR = '#mp_checkout_type';
    COLOMBIA_ACCRONYM = 'MCO';
    MEXICO_ACCRONYM = 'MLM';
    BRAZIL_ACCRONYM = 'MLB';
    CHECKOUT_CUSTOM_LOAD_SELECTOR = '.mp-checkout-custom-load';
    SELECTED_SUPERTOKEN_METHOD_EVENT = 'mp_super_token_payment_method_selected';
    WALLET_BUTTON_SELECTOR = '.mp-wallet-button-container-wrapper';
    SUPER_TOKEN_STYLES = {
        ACCORDION: 'mp-super-token-payment-method__accordion',
        ACCORDION_HEADER: 'mp-super-token-payment-method__accordion-header',
        ACCORDION_TITLE: 'mp-super-token-payment-method__accordion-title',
        ACCORDION_CONTENT: 'mp-super-token-payment-method__accordion-content',
        THUMBNAIL: 'mp-super-token-payment-method__thumbnail',
        PAYMENT_METHOD_LIST: 'mp-super-token-payment-methods-list',
        PAYMENT_METHOD: 'mp-super-token-payment-method',
        PAYMENT_METHOD_CONTENT: 'mp-super-token-payment-method__content',
        PAYMENT_METHOD_CONTENT_TITLE: 'mp-super-token-payment-method__content-title',
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
        REMOVE_BOX_SHADOW: 'mp-box-shadow-none',
        MERCADO_PAGO_PRIVACY_POLICY_FOOTER: 'mp-privacy-policy-footer',
        PAYMENT_METHODS_LIST_HEADER: 'mp-payment-methods-header',
        PAYMENT_METHODS_LIST_HORIZONTAL_ROW: 'mp-payment-methods-list-horizontal-row',
        PAYMENT_METHODS_LIST_HEADER_LOGO: 'mp-payment-methods-header-logo',
        ANIMATION_CLASS: 'mp-initial-state'
    }
    YELLOW_WALLET_PATH = wc_mercadopago_supertoken_payment_methods_params.yellow_wallet_path;
    YELLOW_MONEY_PATH = wc_mercadopago_supertoken_payment_methods_params.yellow_money_path;
    WHITE_CARD_PATH = wc_mercadopago_supertoken_payment_methods_params.white_card_path;
    PAYMENT_METHODS_LIST_TEXT = wc_mercadopago_supertoken_payment_methods_params.payment_methods_list_text;
    PAYMENT_METHODS_LIST_ALT_TEXT = wc_mercadopago_supertoken_payment_methods_params.payment_methods_list_alt_text;
    LAST_DIGITS_TEXT = wc_mercadopago_supertoken_payment_methods_params.last_digits_text;
    NEW_CARD_TEXT = wc_mercadopago_supertoken_payment_methods_params.new_card_text;
    ACCOUNT_MONEY_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_text;
    ACCOUNT_MONEY_WALLET_WITH_INVESTMENT_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_wallet_with_investment_text;
    ACCOUNT_MONEY_WALLET_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_wallet_text;
    ACCOUNT_MONEY_INVESTMENT_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_investment_text;
    ACCOUNT_MONEY_AVAILABLE_TEXT = wc_mercadopago_supertoken_payment_methods_params.account_money_available_text;
    INTEREST_FREE_PART_ONE_TEXT = wc_mercadopago_supertoken_payment_methods_params.interest_free_part_one_text;
    INTEREST_FREE_PART_TWO_TEXT = wc_mercadopago_supertoken_payment_methods_params.interest_free_part_two_text;
    BANK_INTEREST_HINT_TEXT = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.bank_interest_hint_text;
    INSTALLMENTS_INPUT_TITLE = wc_mercadopago_supertoken_payment_methods_params.input_title.installments;
    INSTALLMENTS_PLACEHOLDER = wc_mercadopago_supertoken_payment_methods_params.placeholders.installments;
    INSTALLMENTS_REQUIRED_MESSAGE = wc_mercadopago_supertoken_payment_methods_params.input_helper_message.installments.required;
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
    CONSUMER_CREDITS_DUE_DATE = wc_mercadopago_supertoken_payment_methods_params.consumer_credits_due_date;
    MLB_INSTALLMENT_DEBIT_AUTO_TEXT = wc_mercadopago_supertoken_payment_methods_params.mlb_installment_debit_auto_text;
    INTEREST_RATE_MLB_TEXT = wc_mercadopago_supertoken_payment_methods_params.interest_rate_mlb_text;
    EFFECTIVE_TOTAL_COST_MLB_TEXT = wc_mercadopago_supertoken_payment_methods_params.effective_total_cost_mlb_text;
    IOF_MLB_TEXT = wc_mercadopago_supertoken_payment_methods_params.iof_mlb_text;
    BORROWED_AMOUNT_MLB_TEXT = wc_mercadopago_supertoken_payment_methods_params.borrowed_amount_mlb_text;
    PER_MONTH = wc_mercadopago_supertoken_payment_methods_params.per_month;
    PER_YEAR = wc_mercadopago_supertoken_payment_methods_params.per_year;
    CAT_MLM_TEXT = wc_mercadopago_supertoken_payment_methods_params.cat_mlm_text;
    NO_IVA_TEXT = wc_mercadopago_supertoken_payment_methods_params.no_iva_text;
    TNA_MLM_TEXT = wc_mercadopago_supertoken_payment_methods_params.tna_mlm_text;
    SYSTEM_AMORTIZATION_MLM_TEXT = wc_mercadopago_supertoken_payment_methods_params.system_amortization_mlm_text;
    CFTEA_MLA_TEXT = wc_mercadopago_supertoken_payment_methods_params.cftea_mla_text;
    TNA_MLA_TEXT = wc_mercadopago_supertoken_payment_methods_params.tna_mla_text;
    TEA_MLA_TEXT = wc_mercadopago_supertoken_payment_methods_params.tea_mla_text;
    FIXED_RATE_TEXT = wc_mercadopago_supertoken_payment_methods_params.fixed_rate_text;
    MERCADO_PAGO_PRIVACY_POLICY = wc_mercadopago_supertoken_payment_methods_params.mercadopago_privacy_policy;
    NEW_MP_LOGO_PATH = wc_mercadopago_supertoken_payment_methods_params.new_mp_logo_path;
    PAYMENT_METHODS_THUMBNAILS = wc_mercadopago_supertoken_payment_methods_params.payment_methods_thumbnails;
    PAYMENT_METHODS_ORDER = wc_mercadopago_supertoken_payment_methods_params.payment_methods_order;
    UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.update_security_code_with_retry_error_text;
    UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.update_security_code_no_retry_error_text;
    AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.authorize_payment_method_with_retry_error_text;
    AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.authorize_payment_method_no_retry_error_text;
    SELECT_PAYMENT_METHOD_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.select_payment_method_error_text;
    // We use the update_security_code_with_retry_error_text because it's the same message for the generic error
    SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT = wc_mercadopago_supertoken_payment_methods_params.update_security_code_with_retry_error_text;

    NEW_CARD_TYPE = 'new_card';
    CREDIT_CARD_TYPE = 'credit_card';
    DEBIT_CARD_TYPE = 'debit_card';
    ACCOUNT_MONEY_TYPE = 'account_money';
    PREPAID_CARD_TYPE = 'prepaid_card';
    CONSUMER_CREDITS_TYPE = 'digital_currency';
    PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST = 'cards_first';
    PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST = 'account_money_first';
    MAX_ATTEMPTS_BY_ERROR_CODE = 3;

    // Attributes
    paymentMethods = [];
    superToken = null;
    securityFieldsActiveInstance = null;
    activePaymentMethod = null;
    amount = null;
    selectedPreloadedPaymentMethod = null;
    securityCodeReferences = {};
    lastPaymentMethodChoosen = null; // Should not be resetted
    attemptsByErrorCode = {};

    // Dependencies
    mpSdkInstance = null;
    mpSuperTokenMetrics = null;

    constructor(mpSdkInstance, mpSuperTokenMetrics) {
        this.mpSdkInstance = mpSdkInstance;
        this.mpSuperTokenMetrics = mpSuperTokenMetrics;
    }

    reset() {
        this.paymentMethods = [];
        this.attemptsByErrorCode = {};
        this.securityCodeReferences = {};
        this.activePaymentMethod = null;

        this.unmountActiveSecurityCodeInstance();
        this.hideAllPaymentMethodDetails();
        this.showWalletButton();
        this.showCardFlags();
        this.removePaymentMethodElements();
        this.removePaymentMethodsListHeader();
        this.removeAccordion();
        this.deselectAllPaymentMethods();
        this.removePaymentMethodsListClasses();
        this.removeMercadoPagoPrivacyPolicyFooter();
        this.removeHorizontalRow();
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

    storeSelectedPreloadedPaymentMethod(paymentMethod) {
      this.selectedPreloadedPaymentMethod = paymentMethod;
    }

    getSelectedPreloadedPaymentMethod() {
        return this.selectedPreloadedPaymentMethod;
    }

    getSelectedPreloadedPaymentMethodFromActivePaymentMethods() {
        return this.paymentMethods.find(paymentMethod => this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier(this.selectedPreloadedPaymentMethod));
    }

    paymentMethodIdentifier(paymentMethod) {
        if (!paymentMethod) return '';

        return `${paymentMethod?.id}${paymentMethod?.card?.card_number?.last_four_digits || ''}`;
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

    async getAccountPaymentMethods(token) {
        this.setSuperToken(token);

        return await this.mpSdkInstance.getAccountPaymentMethods(token);
    }

    getAttemptByErrorCode(errorCode) {
      return Math.min(this.attemptsByErrorCode[errorCode] || 0, this.MAX_ATTEMPTS_BY_ERROR_CODE);
    }

    shouldAllowRetry(attempt) {
      return attempt < this.MAX_ATTEMPTS_BY_ERROR_CODE;
    }

    storeAttemptByErrorCode(errorCode) {
      this.attemptsByErrorCode[errorCode] = (this.attemptsByErrorCode[errorCode] || 0) + 1;
    }

    convertErrorCodeToErrorMessage(errorCode) {
      this.storeAttemptByErrorCode(errorCode);

      const errorMessages = {
        'UPDATE_SECURITY_CODE_ERROR': {
          withRetry: this.UPDATE_SECURITY_CODE_WITH_RETRY_ERROR_TEXT,
          withoutRetry: this.UPDATE_SECURITY_CODE_NO_RETRY_ERROR_TEXT
        },
        'AUTHORIZE_PAYMENT_METHOD_ERROR': {
          withRetry: this.AUTHORIZE_PAYMENT_METHOD_WITH_RETRY_ERROR_TEXT,
          withoutRetry: this.AUTHORIZE_PAYMENT_METHOD_NO_RETRY_ERROR_TEXT
        },
        'SELECT_PAYMENT_METHOD_ERROR': {
          withRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT,
          withoutRetry: this.SELECT_PAYMENT_METHOD_ERROR_TEXT
        },
      };

      const errorConfig = errorMessages[errorCode];

      if (!errorConfig) {
          return this.SUBMIT_SUPER_TOKEN_GENERIC_ERROR_TEXT;
      }

      return this.shouldAllowRetry(this.getAttemptByErrorCode(errorCode))
        ? errorConfig.withRetry
        : errorConfig.withoutRetry;
    }

    showSuperTokenError(errorMessage) {
        const paymentMethodList = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST}`);
        if (!paymentMethodList) return;

        const andesNotice = document.createElement('andes-notice');
        andesNotice.id = 'mp-fast-payments-error';
        andesNotice.setAttribute('type', 'warning');
        andesNotice.setAttribute('description', errorMessage);

        paymentMethodList.insertBefore(andesNotice, paymentMethodList.firstChild);
        andesNotice.scrollIntoView({ behavior: 'smooth' });
    }

    hideSuperTokenError() {
        const andesNotice = document.getElementById('mp-fast-payments-error');
        if (!andesNotice) return;

        andesNotice.remove();
    }

    getCustomCheckoutEntireElement() {
        return document.querySelector(this.CUSTOM_CHECKOUT_BLOCKS_SELECTOR)
            || document.querySelector(this.CUSTOM_CHECKOUT_CLASSIC_SELECTOR);
    }

    getWalletButtonElement() {
        return document.querySelector(this.WALLET_BUTTON_SELECTOR);
    }

    getCardFlagsElement() {
        return document.querySelector(this.CARD_FLAGS_SELECTOR);
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

    hideCardFlags() {
        const cardFlagsElement = this.getCardFlagsElement();

        if (!cardFlagsElement) return;

        cardFlagsElement.style.display = 'none';
    }

    showCardFlags() {
        const cardFlagsElement = this.getCardFlagsElement();

        if (!cardFlagsElement) return;

        cardFlagsElement.style.display = 'flex';
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
        const checkoutContainer = customCheckoutEntireElement?.querySelector(this.CHECKOUT_CONTAINER_SELECTOR);

        if (checkoutContainer) {
            checkoutContainer.style.height = 'auto';
        }

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

    getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods) {
        const paymentMethodSelected = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED}`) || null;
        if (!paymentMethodSelected) return null;

        return accountPaymentMethods.find(paymentMethod => this.paymentMethodIdentifier(paymentMethod) === paymentMethodSelected.id);
    }

    getPaymentMethodElementFromDOM(paymentMethod) {
        return document.getElementById(this.paymentMethodIdentifier(paymentMethod));
    }

    selectPreloadedPaymentMethod() {
      this.closeAccordion();

      const paymentMethod = this.getSelectedPreloadedPaymentMethodFromActivePaymentMethods();
      if (!paymentMethod) {
          return;
      }

      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      if (!paymentMethodElement) {
          return;
      }

      this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
    }

    setCheckoutType(type) {
        document.querySelector(this.CHECKOUT_TYPE_SELECTOR).value = type;
    }

    setPaymentMethodChildrenAriaVisible(paymentMethodElement) {
      const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
      if (securityCodeContainer) {
        const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
        const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
        const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');

        securityCodeLabel?.setAttribute('aria-hidden', 'false');
        securityCodeLabel?.setAttribute('tabindex', '0');
        securityCodeInput?.setAttribute('aria-hidden', 'false');
        securityCodeInput?.setAttribute('tabindex', '0');
        securityCodeTooltip?.setAttribute('aria-hidden', 'false');
        securityCodeTooltip?.setAttribute('tabindex', '0');
      }

      const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement)}`);

      if (!installmentsDropdown) return;

      installmentsDropdown.setAttribute('aria-hidden', 'false');
      installmentsDropdown.setAttribute('tabindex', '0');
    }

    setPaymentMethodChildrenAriaHidden(paymentMethodElement) {
      const securityCodeContainer = paymentMethodElement.querySelector('.mp-super-token-security-code-container');
      if (securityCodeContainer) {
        const securityCodeLabel = securityCodeContainer.querySelector('.mp-super-token-security-code-label');
        const securityCodeInput = securityCodeContainer.querySelector('.mp-super-token-security-code-input');
        const securityCodeTooltip = securityCodeContainer.querySelector('.mp-super-token-security-code-tooltip');

        securityCodeLabel?.setAttribute('aria-hidden', 'true');
        securityCodeLabel?.setAttribute('tabindex', '-1');
        securityCodeInput?.setAttribute('aria-hidden', 'true');
        securityCodeInput?.setAttribute('tabindex', '-1');
        securityCodeTooltip?.setAttribute('aria-hidden', 'true');
        securityCodeTooltip?.setAttribute('tabindex', '-1');
      }

      const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethodElement)}`);

      if (!installmentsDropdown) return;

      installmentsDropdown.setAttribute('aria-hidden', 'true');
      installmentsDropdown.setAttribute('tabindex', '-1');
    }

    showPaymentMethodDetails(paymentMethodElement) {
        paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
            ?.classList
            ?.remove(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

        this.setPaymentMethodChildrenAriaVisible(paymentMethodElement);
    }

    hideAllPaymentMethodDetails() {
        document
            .querySelectorAll(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS}`)
            ?.forEach(element => {
                element
                    ?.classList
                    ?.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

                this.setPaymentMethodChildrenAriaHidden(element);
            });
    }

    fillCardTokenFields(paymentMethod) {
        document.getElementById('paymentMethodId').value = paymentMethod.id;
        document.getElementById('paymentTypeId').value = paymentMethod.type;
        document.getElementById('cardTokenId').value = paymentMethod.token;
    }

    paymentMethodAlreadySelected(paymentMethod) {
        const paymentMethodElement = this.getPaymentMethodElementFromDOM(paymentMethod);
        if (!paymentMethodElement) return false;

        return paymentMethodElement.classList.contains(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_SELECTED);
    }

    getActivePaymentMethod() {
        return this.activePaymentMethod;
    }

    storeActivePaymentMethod(paymentMethod) {
        this.activePaymentMethod = paymentMethod;
        this.lastPaymentMethodChoosen = paymentMethod || this.lastPaymentMethodChoosen;
    }

    getLastPaymentMethodChoosen() {
        return this.lastPaymentMethodChoosen;
    }

    selectLastPaymentMethodChoosen() {
      this.closeAccordion();

      const paymentMethod = this.getLastPaymentMethodChoosen();
      if (!paymentMethod) return;

      const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(paymentMethod));
      if (!paymentMethodElement) {
          return;
      }

      this.onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod);
    }

    hasCheckoutError() {
      return !!document.querySelector('#mp-fast-payments-error');
    }

    formatSelectedPaymentMethodName(paymentMethod) {
        if (this.paymentMethodIdentifier(paymentMethod) === this.paymentMethodIdentifier({ id: this.NEW_CARD_TYPE })) {
            return 'new_credit_card';
        }

        return `${paymentMethod?.id || 'none'} ${paymentMethod?.type || 'none'}`.toLowerCase();
    }

    emitEventFromSelectPaymentMethod(paymentMethod) {
        const formattedPaymentMethodName = this.formatSelectedPaymentMethodName(paymentMethod);

        document.dispatchEvent(new CustomEvent(this.SELECTED_SUPERTOKEN_METHOD_EVENT, { detail: { payment_method: formattedPaymentMethodName } }))
    }

    onSelectSuperTokenPaymentMethod(paymentMethodElement, paymentMethod) {
        if (this.paymentMethodAlreadySelected(paymentMethod)) {
            return;
        }

        this.emitEventFromSelectPaymentMethod(paymentMethod);
        this.storeActivePaymentMethod(paymentMethod);
        this.hideAllPaymentMethodDetails();
        this.closeAccordion();
        this.deselectAllPaymentMethods();
        this.selectPaymentMethod(paymentMethodElement);
        this.fillCardTokenFields(paymentMethod);
        this.setCheckoutType(this.SUPER_TOKEN_CHECKOUT_TYPE);
        this.showPaymentMethodDetails(paymentMethodElement);
        this.mountSecurityCodeField(paymentMethod);
        this.handleInstallmentsWithoutFeePillVisibility(paymentMethod);

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

        this.emitEventFromSelectPaymentMethod({ id: this.NEW_CARD_TYPE });
        this.storeActivePaymentMethod({ id: this.NEW_CARD_TYPE });
        this.deselectAllPaymentMethods();
        this.hideAllPaymentMethodDetails();
        this.unmountActiveSecurityCodeInstance();
        this.selectNewCardAccordion();
        this.setCheckoutType(this.CUSTOM_CHECKOUT_TYPE);
        this.handleInstallmentsWithoutFeePillVisibility({ type: 'credit_card' });

        setTimeout(() => {
            this.unmountCardForm();
            this.mountCardForm();
        }, 50);

        setTimeout(() => {
          document.dispatchEvent(
            this.selectedSupertokenMethodEvent(true)
          )
        }, 50);
    }

    selectedSupertokenMethodEvent = (isNewCardSelected) => {
        return new CustomEvent('supertoken_payment_method_selected',
            {
                detail: {
                    new_card_selected: isNewCardSelected,
                    checkout_type: document.querySelector('#mp_checkout_type')?.value,
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

    isConsumerCredits(paymentMethod) {
        return paymentMethod?.type === this.CONSUMER_CREDITS_TYPE;
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

        if (this.userHasAccountMoney(accountMoneyPaymentMethod) &&
            this.userHasAccountMoneyInvested(accountMoneyPaymentMethod)) {
            return this.ACCOUNT_MONEY_WALLET_WITH_INVESTMENT_TEXT;
        }

        if (this.userHasAccountMoney(accountMoneyPaymentMethod)) {
            return this.ACCOUNT_MONEY_WALLET_TEXT;
        }

        if (this.userHasAccountMoneyInvested(accountMoneyPaymentMethod)) {
            return this.ACCOUNT_MONEY_INVESTMENT_TEXT;
        }

        return this.ACCOUNT_MONEY_AVAILABLE_TEXT;
    }

    buildConsumerCreditsName() {
        switch (this.getSiteId()) {
            case this.MEXICO_ACCRONYM:
                return 'Meses sin Tarjeta con Mercado&nbsp;Pago';
            case this.BRAZIL_ACCRONYM:
                return 'Linha de Crédito Mercado&nbsp;Pago';
            default:
                return 'Cuotas sin Tarjeta con Mercado&nbsp;Pago';
        }
    }

    numberOfInstallmentsWithoutFee(paymentMethod) {
        if (!this.isCreditCard(paymentMethod) && !this.isConsumerCredits(paymentMethod)) {
            return 0;
        }

        if (!paymentMethod.installments || !paymentMethod.installments.length) {
            return 0;
        }

        if (this.isConsumerCredits(paymentMethod)) {
            // TODO: this is a temporary way to get the number of installments without fee for the consumer credits payment method
            // we can improve this or even check the installment_rate_collector, but for now it is not present in the API
            const installmentsWithoutFee = paymentMethod.installments.filter(installment => installment.installment_rate === 0);
            return installmentsWithoutFee.length > 0 ? installmentsWithoutFee[installmentsWithoutFee.length - 1].installments : 0;
        }

        const installmentsWithoutFee = paymentMethod.installments.filter(installment => installment.installment_rate === 0 && installment.installment_rate_collector.includes('MERCADOPAGO'));
        return installmentsWithoutFee[installmentsWithoutFee.length - 1].installments;
    }

    buildPaymentMethodAriaLabel(paymentMethod, lastFourDigits, installmentsWithoutFee) {
        const name = paymentMethod?.name;
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

    formatDateToDayAndMonth(isoDate) {
        if (!isoDate) {
            return '';
        }

        const dateParts = isoDate.split('-');
        if (dateParts.length !== 3) {
            return isoDate;
        }

        const monthsMapping = {
            '01': 'jan',
            '02': 'feb',
            '03': 'mar',
            '04': 'apr',
            '05': 'may',
            '06': 'jun',
            '07': 'jul',
            '08': 'aug',
            '09': 'sep',
            '10': 'oct',
            '11': 'nov',
            '12': 'dec'
        };

        const [_, month, day] = dateParts;
        const monthKey = monthsMapping[month];
        const monthText = (wc_mercadopago_supertoken_payment_methods_params.months_abbreviated &&
                          wc_mercadopago_supertoken_payment_methods_params.months_abbreviated[monthKey])
                          ? wc_mercadopago_supertoken_payment_methods_params.months_abbreviated[monthKey]
                          : month;

        return `${parseInt(day)}/${monthText}`;
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
        const installmentRateThirdParty = installment.installment_rate_collector.includes('THIRD_PARTY');
        const totalAmount = this.formatCurrency(installment.total_amount);

        if (installmentNumber === 1) {
            return `${installmentNumber}x ${totalAmount}`;
        }

        if (installmentRate) {
            return `${installmentNumber}x ${installmentAmount} (${totalAmount})`;
        }

        if (this.needsBankInterestDisclaimer() && installmentRateThirdParty && !installmentRate) {
            return `${installmentNumber}x ${installmentAmount} (${totalAmount})*`;
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
            ?.map((installment) => {
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
                    role="alert"
                >
                    <svg aria-hidden="true" tabindex="-1" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="12" height="12" rx="6" fill="#CC1818"/>
                        <path d="M6.72725 2.90918H5.27271L5.45452 6.90918H6.54543L6.72725 2.90918Z" fill="white"/>
                        <path d="M5.99998 7.63645C6.40164 7.63645 6.72725 7.96206 6.72725 8.36373C6.72725 8.76539 6.40164 9.091 5.99998 9.091C5.59832 9.091 5.27271 8.76539 5.27271 8.36373C5.27271 7.96206 5.59832 7.63645 5.99998 7.63645Z" fill="white"/>
                    </svg>
                    <span
                      id="mp-super-token-security-code-error-message"
                      class="mp-super-token-security-code-error-message"
                      aria-hidden="true"
                      tabindex="-1"
                    ></span>
                </div>
            </div>
        `
    }

    buildConsumerCreditsHint(installment) {
        const labels = installment?.labels.find(label => label.toLowerCase().includes('|'))?.toLowerCase() ?? '';
        const conditions = labels.split('|').reduce((acc, label) => {
          const [key, value] = label.split('_');
          acc[key] = value;
          return acc;
        }, {});

        if (!installment?.consumer_credits?.conditions) {
            return '';
        }

        const siteId = this.getSiteId();

        switch (siteId) {
            case this.BRAZIL_ACCRONYM:
                const parts = [];

                if (conditions.tem && conditions.tea) {
                    parts.push(`${this.INTEREST_RATE_MLB_TEXT}: ${conditions.tem} ${this.PER_MONTH} ${conditions.tea} ${this.PER_YEAR}`);
                }

                if (conditions.cetm && conditions.ceta) {
                    parts.push(`${this.EFFECTIVE_TOTAL_COST_MLB_TEXT}: ${conditions.cetm} ${this.PER_MONTH} ${conditions.ceta} ${this.PER_YEAR}`);
                }

                if (conditions.iof) {
                    const iofAmount = installment.installment_iof_amount || 0;
                    if (iofAmount > 0) {
                      const iofAmountFormatted = iofAmount.toFixed(2).replace('.', ',');
                      parts.push(`${this.IOF_MLB_TEXT}: R$ ${iofAmountFormatted} (${conditions.iof})`);
                    }
                }

                const borrowedAmount = installment.total_amount - (installment.installment_iof_amount || 0);
                parts.push(`${this.BORROWED_AMOUNT_MLB_TEXT}: R$ ${borrowedAmount.toFixed(2).replace('.', ',')}`);

                return parts.join('. ') + '.';

            case this.MEXICO_ACCRONYM:
                const mexParts = [];

                if (conditions.cat) {
                    mexParts.push(`${this.CAT_MLM_TEXT}: ${conditions.cat} ${this.NO_IVA_TEXT}`);
                }

                if (conditions.tna) {
                    mexParts.push(`${this.TNA_MLM_TEXT}: ${conditions.tna}`);
                }

                if (mexParts.length > 0) {
                    mexParts.push(`${this.SYSTEM_AMORTIZATION_MLM_TEXT}`);
                    return `${mexParts.join('. ')}.`;
                }

                return '';

            default:
                const argParts = [];

                if (conditions.cftea) {
                    argParts.push(`<strong>${this.CFTEA_MLA_TEXT}: ${conditions.cftea}</strong>`);
                }

                if (conditions.tna) {
                    argParts.push(`${this.TNA_MLA_TEXT}: ${conditions.tna}`);
                }

                if (conditions.tea) {
                    argParts.push(`${this.TEA_MLA_TEXT}: ${conditions.tea}`);
                }

                if (argParts.length > 0) {
                    return `${argParts.join(' - ')}. ${this.FIXED_RATE_TEXT}`;
                }

                return '';
        }
    }

    buildConsumerCreditsDetailsInnerHTML(paymentMethod) {
        if (!this.isConsumerCredits(paymentMethod)) {
            return '';
        }

        const section = document.createElement('section');
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS);
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

        const installments = paymentMethod?.installments?.map(installment => ({
            value: `${installment.installments}`,
            title: this.buildInstallmentTitle(installment),
        }));

        section.innerHTML = `
            <andes-dropdown
                label="${this.INSTALLMENTS_INPUT_TITLE}"
                placeholder="${this.INSTALLMENTS_PLACEHOLDER}"
                required-message="${this.INSTALLMENTS_REQUIRED_MESSAGE}"
                items='${JSON.stringify(installments || [])}'
                site-id="${this.getSiteId()}"
            ></andes-dropdown>

            <div class="mp-consumer-credits-due-date" id="mp-consumer-credits-due-date" style="display: none;">
            </div>

            <div class="mp-consumer-credits-debit-auto-text" id="mp-consumer-credits-debit-auto-text" style="display: none; text-align: center;"></div>

            <div id="mp-consumer-credits-legal-text" style="display: none;"></div>
        `;

        return section.outerHTML;
    }

    buildConsumerCreditsDetailsDueDate(paymentMethod) {
        if (!this.isConsumerCredits(paymentMethod)) {
            return '';
        }

        const element = document.getElementById('mp-consumer-credits-due-date');
        if (!element) {
            return;
        }

        element.innerHTML = `
            <span style="font-weight: 400 !important;">${this.CONSUMER_CREDITS_DUE_DATE}
            <b style="font-weight: 600 !important;">${this.formatDateToDayAndMonth(paymentMethod.next_due_date)}</b>.
            </span>
        `;

        return element.outerHTML;
    }

    buildCreditCardDetailsInnerHTML(paymentMethod) {
        if (!this.isCreditCard(paymentMethod) && !this.isDebitCard(paymentMethod) && !this.isPrepaidCard(paymentMethod)) {
            return '';
        }

        const section = document.createElement('section');
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_DETAILS);
        section.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HIDE);

        if (this.isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
            const defaultOption = {
              value: '',
              title: this.INSTALLMENTS_PLACEHOLDER,
              disabled: true,
              selected: true,
            };
            const installments = [defaultOption, ...this.normalizeInstallments(paymentMethod.installments)];

            section.innerHTML = `
              <div class="mp-checkout-custom-installments-select-container">
                <label for="mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}" class="mp-input-label">
                  ${this.INSTALLMENTS_INPUT_TITLE}
                </label>
                <select
                  data-checkout="installments"
                  name="installments"
                  id="mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}"
                  class="mp-custom-checkout-select-input"
                >
                  ${installments.map(installment => `<option ${installment.disabled ? 'disabled' : ''} ${installment.selected ? 'selected' : ''} value="${installment.value}">${installment.title}</option>`).join('')}
                </select>
                <input-helper
                  isVisible=false
                  type="error"
                  message="${this.INSTALLMENTS_REQUIRED_MESSAGE}"
                  input-id="mp-super-token-installments-error-${this.paymentMethodIdentifier(paymentMethod)}"
                >
                </input-helper>
                <div id="mp-super-token-installments-tax-info-${this.paymentMethodIdentifier(paymentMethod)}" class="mp-installments-tax-info" style="display: none;"></div>
              </div>
            `;
        }

        section.innerHTML += this.buildSecurityCodeInnerHTML(paymentMethod);

        return section.outerHTML;
    }

    installmentsWasSelected(paymentMethod) {
      const installmentsSelect = document.getElementById(`mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`);

      return !!installmentsSelect?.value;
    }

    setInstallmentsErrorState(paymentMethod, hasError) {
      const paymentMethodIdentifier = this.paymentMethodIdentifier(paymentMethod);
      const installmentsSelect = document.getElementById(`mp-super-token-installments-select-${paymentMethodIdentifier}`);
      const installmentsLabel = document.querySelector(`label[for="mp-super-token-installments-select-${paymentMethodIdentifier}"]`);
      const installmentsErrorHelper = document.querySelector(`#mp-super-token-installments-error-${paymentMethodIdentifier}`);

      if (!installmentsSelect || !installmentsLabel || !installmentsErrorHelper) return;

      if (hasError) {
        installmentsErrorHelper.style.display = 'flex';
        installmentsSelect.classList.add('mp-super-token-error');
        installmentsLabel.classList.add('mp-super-token-label-error');
      } else {
        installmentsErrorHelper.style.display = 'none';
        installmentsSelect.classList.remove('mp-super-token-error');
        installmentsLabel.classList.remove('mp-super-token-label-error');
      }
    }

    buildMLBConsumerCreditsLegalText() {
        const element = document.getElementById('mp-consumer-credits-debit-auto-text');
        if (!element) {
            return;
        }

        element.innerHTML = `
            <span>
                ${this.MLB_INSTALLMENT_DEBIT_AUTO_TEXT}
            </span>
        `;
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

    getCheckoutLoaderElement() {
        return document.querySelector('.mp-checkout-custom-load');
    }

    moveCheckoutLoaderToPaymentMethodsList() {
        const checkoutLoaderElement = this.getCheckoutLoaderElement();
        const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        if (!checkoutLoaderElement || !paymentMethodsListElement) {
            return;
        }

        paymentMethodsListElement.parentElement.appendChild(checkoutLoaderElement);
    }

    removeCheckoutLoaderFromPaymentMethodsList() {
        const checkoutLoaderElement = this.getCheckoutLoaderElement();
        const checkoutEntireElement = this.getCustomCheckoutEntireElement();
        if (!checkoutLoaderElement || !checkoutEntireElement) {
            return;
        }

        checkoutEntireElement.appendChild(checkoutLoaderElement);
    }

    hidePaymentMethodsList() {
        const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        if (!paymentMethodsListElement) {
            return;
        }

        paymentMethodsListElement.style.display = 'none';
    }

    showPaymentMethodsList() {
        const paymentMethodsListElement = document.querySelector(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        if (!paymentMethodsListElement) {
            return;
        }

        paymentMethodsListElement.style.display = 'flex';
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
            throw new Error(MPSuperTokenErrorCodes.UPDATE_SECURITY_CODE_ERROR);
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
        helperErrorElement.setAttribute('aria-label', displayMessage);
        helperErrorElement.style.display = 'flex';
    }

    verifyIsSecurityCodeReferenceTrue(paymentMethod) {
        return this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] === true;
    }

    setSecurityCodeReferenceFalse(paymentMethod) {
        this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = false;
    }

    setSecurityCodeReferenceTrue(paymentMethod) {
        this.securityCodeReferences[this.paymentMethodIdentifier(paymentMethod)] = true;
    }

    mountSecurityCodeField(paymentMethod) {
        if (!this.securityCodeIsRequired(paymentMethod?.security_code_settings)) {
            return;
        }

        this.unmountCardForm();
        this.unmountActiveSecurityCodeInstance();
        this.setSecurityCodeReferenceFalse(paymentMethod);

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
                      this.setSecurityCodeReferenceTrue(paymentMethod);

                        if (e.errorMessages.length === 0) {
                            if (typeof MPCheckoutFieldsDispatcher !== 'undefined') {
                                MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
                                    null,
                                    "focusout",
                                    "super_token_cvv_filled",
                                    {
                                        onlyDispatch: true
                                    }
                                );
                            }

                            this.toggleSecurityCodeErrorMessage('', paymentMethod);
                        } else {
                          this.setSecurityCodeReferenceFalse(paymentMethod);
                        }

                        const errorMessage = e.errorMessages[0]?.cause ?? '';

                        this.toggleSecurityCodeErrorMessage(errorMessage, paymentMethod);
                    })
            }
        }, 200)
    }

    handleInstallmentsWithoutFeePillVisibility(selectedPaymentMethod) {
        const allPaymentMethods = document.querySelectorAll('.mp-super-token-payment-method');

        allPaymentMethods.forEach(paymentMethodElement => {
            const valuePropPill = paymentMethodElement.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_VALUE_PROP}`);
            if (!valuePropPill) {
                return;
            }

                valuePropPill.style.display = 'flex';
        });
    }

    createPaymentMethodElement(paymentMethod) {
        const lastFourDigits = paymentMethod?.card?.card_number?.last_four_digits;
        const paymentMethodElement = document.createElement('article');
        const installmentsWithoutFee = this.numberOfInstallmentsWithoutFee(paymentMethod);
        const ariaLabel = this.buildPaymentMethodAriaLabel(paymentMethod, lastFourDigits, installmentsWithoutFee);
        const temporaryId = Math.random().toString(36).substring(2, 15);
        const paymentMethodId = paymentMethod?.id ? this.paymentMethodIdentifier(paymentMethod) : temporaryId;
        const shouldShowValueProp = (this.isCreditCard(paymentMethod) || this.isConsumerCredits(paymentMethod))  && installmentsWithoutFee > 1;

        paymentMethodElement.id = paymentMethodId;
        paymentMethodElement.dataset.type = paymentMethod?.type;
        paymentMethodElement.dataset.id = paymentMethodId;
        paymentMethodElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD);
        paymentMethodElement.setAttribute('aria-label', ariaLabel);
        paymentMethodElement.setAttribute('tabindex', '0');
        paymentMethodElement.setAttribute('role', 'option');
        paymentMethodElement.setAttribute('aria-selected', 'false');
        paymentMethodElement.innerHTML = `
            <section class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_HEADER}">
                <figure class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_THUMBNAIL}">
                        <img src="${paymentMethod?.thumbnail}" alt="${paymentMethod?.name}">
                </figure>
                    <article class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_CONTENT}">
                    <section class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_CONTENT_TITLE}">
                        <span class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_TITLE}">${paymentMethod?.name}</span>
                        ${lastFourDigits
                            ? `<span class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LAST_FOUR_DIGITS}">**** ${lastFourDigits}</span>`
                            : ''
                        }
                    </section>
                    ${shouldShowValueProp ?
                            `<span
                                aria-hidden="true"
                                class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_VALUE_PROP}">
                                ${this.INTEREST_FREE_PART_ONE_TEXT} ${installmentsWithoutFee}${this.getSiteId() === 'MLB' ? 'x' : ''} ${this.INTEREST_FREE_PART_TWO_TEXT}
                            </span>`
                            : ''
                        }
                    </article>
            </section>
            ${this.buildCreditCardDetailsInnerHTML(paymentMethod)}
            ${this.buildConsumerCreditsDetailsInnerHTML(paymentMethod)}
        `;

        paymentMethodElement.addEventListener('click', () => {
            this.onSelectSuperTokenPaymentMethod.call(this, paymentMethodElement, paymentMethod);
        });
        paymentMethodElement.addEventListener('keydown', (e) => {
            if (e.code == 'Space' || e.key == 'Enter') {
                e.preventDefault();
                this.onSelectSuperTokenPaymentMethod.call(this, paymentMethodElement, paymentMethod);
            }
        });

        if (this.isCreditCard(paymentMethod) && paymentMethod.installments?.length) {
            const dropdownElement = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(paymentMethod)}`);

            this.addDropdownEventListener(dropdownElement);

            dropdownElement?.addEventListener('change', (event) => {
                const selectedItem = event.target.value;
                if (selectedItem) {
                    if (typeof MPCheckoutFieldsDispatcher !== 'undefined') {
                        MPCheckoutFieldsDispatcher?.addEventListenerDispatcher(
                            null,
                            "focusout",
                            "super_token_installments_filled",
                            {
                                onlyDispatch: true
                            }
                        );
                    }

                    this.setInstallmentsErrorState(paymentMethod, false);
                    CheckoutPage.updateTaxInfoForSelect(selectedItem, `mp-super-token-installments-tax-info-${this.paymentMethodIdentifier(paymentMethod)}`, paymentMethod?.installments);

                    if (selectedItem) {
                      dropdownElement.value = selectedItem;
                      const cardInstallments = document.getElementById('cardInstallments');
                      if (cardInstallments) {
                          cardInstallments.value = selectedItem;
                      }
                    }
                }
            });

            dropdownElement?.addEventListener('blur', () => {
                if (this.installmentsWasSelected(paymentMethod)) {
                    this.setInstallmentsErrorState(paymentMethod, false);
                } else {
                    this.setInstallmentsErrorState(paymentMethod, true);
                }
            });
        }

        if (this.isConsumerCredits(paymentMethod) && paymentMethod?.installments?.length) {
            const dropdownElement = paymentMethodElement.querySelector('andes-dropdown');
            this.addDropdownEventListener(dropdownElement);

            const parameters = {
                fastPaymentToken: this.getSuperToken(),
                pricingId: paymentMethod.credits_pricing_id,
                pseudotoken: paymentMethod.token,
                customization: {
                    textColor: "#000000",
                    textSize: "13px",
                    linkColor: "#3483FA"
                }
            }

            this.mpSdkInstance.renderCreditsContract("mp-consumer-credits-legal-text", parameters)
            .then((contractController) => {
              dropdownElement?.addEventListener('change', (event) => {
                  if (this.getSiteId() === this.BRAZIL_ACCRONYM) {
                      this.buildMLBConsumerCreditsLegalText();
                      document.getElementById('mp-consumer-credits-debit-auto-text').style.display = 'block';
                  };
                  const selectedItem = event?.detail;
                  if (selectedItem && selectedItem?.value) {
                      dropdownElement.value = selectedItem.value;

                      document.getElementById('cardInstallments').value = parseInt(selectedItem.value);

                      const selectedInstallment = paymentMethod.installments.find(installment =>
                          installment.installments === parseInt(selectedItem.value)
                      );

                      if (selectedInstallment) {
                          dropdownElement.setAttribute('hint', this.buildConsumerCreditsHint(selectedInstallment));
                          dropdownElement.setAttribute('due-date', this.buildConsumerCreditsDetailsDueDate(paymentMethod));
                          document.getElementById('mp-consumer-credits-due-date').style.display = 'block';
                          document.getElementById('mp-consumer-credits-legal-text').style.display = 'block';
                      }

                      contractController.update({ installments: selectedItem.value });
                  }
                });
            })
            .catch((error) => {
                console.error(error);
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
            accordionHeader.addEventListener('click', () => {
                this.onSelectNewCardPaymentMethod.call(this);
            });
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
    }

    convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement) {
        customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHOD_LIST);
        customCheckoutEntireElement.setAttribute('role', 'listbox');
        customCheckoutEntireElement.setAttribute('aria-label', this.PAYMENT_METHODS_LIST_ALT_TEXT);
        customCheckoutEntireElement.setAttribute('tabindex', '0');
        customCheckoutEntireElement.parentElement.classList.add('mp-box-shadow-none');
        customCheckoutEntireElement.classList.add(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
    }

    reorderAccountPaymentMethods(accountPaymentMethods) {
        const MAX_CREDIT_CARDS = 3;

        const cardOptions = accountPaymentMethods.filter(pm => this.isCreditCard(pm) || this.isDebitCard(pm) || this.isPrepaidCard(pm));
        const accountMoneyOption = accountPaymentMethods.find(pm => this.isAccountMoney(pm));
        const consumerCreditsOption = accountPaymentMethods.find(pm => this.isConsumerCredits(pm));

        const limitedCardsOptions = cardOptions.slice(0, MAX_CREDIT_CARDS);
        const orderPreference = this.PAYMENT_METHODS_ORDER || this.PAYMENT_METHODS_ORDER_TYPE_CARDS_FIRST;
        const isAccountMoneyFirst = orderPreference === this.PAYMENT_METHODS_ORDER_TYPE_ACCOUNT_MONEY_FIRST && accountMoneyOption;

        const moneySpecializedOptions = [accountMoneyOption, consumerCreditsOption].filter(Boolean);
        const result = isAccountMoneyFirst ? [moneySpecializedOptions, ...limitedCardsOptions] : [...limitedCardsOptions, moneySpecializedOptions];
        return result.flat();
    }

    normalizeAccountPaymentMethods(accountPaymentMethods) {
        return accountPaymentMethods.map(paymentMethod => {
            if (this.isAccountMoney(paymentMethod)) {
                paymentMethod.thumbnail = this.YELLOW_WALLET_PATH;
                paymentMethod.name = this.buildAccountMoneyName(paymentMethod);
            }

            if (this.isConsumerCredits(paymentMethod)) {
                paymentMethod.thumbnail = this.YELLOW_MONEY_PATH;
                paymentMethod.name = this.buildConsumerCreditsName();
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
        });
    }

    focusFirstPaymentMethod() {
        const firstPaymentMethod = this.getCustomCheckoutEntireElement().querySelector('article');
        if (firstPaymentMethod) {
            firstPaymentMethod.focus();
            return;
        }

        const firstAccordion = this.getCustomCheckoutEntireElement().querySelector('section');
        if (firstAccordion) {
            firstAccordion.focus();
        }
    }

    organizePaymentMethodsElements(paymentMethods) {
        const reorderedAccountPaymentMethods = this.reorderAccountPaymentMethods(paymentMethods);
        const onlyAcceptedPaymentMethods = reorderedAccountPaymentMethods.filter(pm => !this.isConsumerCredits(pm));
        const normalizedPaymentMethods = this.normalizeAccountPaymentMethods(onlyAcceptedPaymentMethods);

        normalizedPaymentMethods.reverse().forEach((paymentMethod) => {
            this.getCustomCheckoutEntireElement().insertBefore(
                this.createPaymentMethodElement(paymentMethod),
                this.getCustomCheckoutEntireElement().firstChild
            );
        });
    }

    addMercadoPagoPrivacyPolicyFooter() {
        const footer = document.createElement('footer');
        footer.classList.add(this.SUPER_TOKEN_STYLES.MERCADO_PAGO_PRIVACY_POLICY_FOOTER);
        footer.id = 'mp-super-token-privacy-policy-footer';
        footer.innerHTML = `<span>${this.MERCADO_PAGO_PRIVACY_POLICY}</span>`;

        this.getCustomCheckoutEntireElement().insertBefore(
            footer,
            this.getCustomCheckoutEntireElement().firstChild
        );
    }

    removeMercadoPagoPrivacyPolicyFooter() {
        const footer = this.getCustomCheckoutEntireElement().querySelector('#mp-super-token-privacy-policy-footer');
        if (!footer) {
            return;
        }

        footer.remove();
    }

    addPaymentMethodsListHeader(customCheckoutEntireElement) {
        const header = document.createElement('header');
        header.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HEADER);
        header.innerHTML = `
            <span>${this.PAYMENT_METHODS_LIST_TEXT}</span>
            <img alt="Mercado Pago" class="${this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HEADER_LOGO}" src="${this.NEW_MP_LOGO_PATH}">
        `;
        customCheckoutEntireElement.insertBefore(header, customCheckoutEntireElement.firstChild);
    }

    removePaymentMethodsListHeader() {
        const header = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HEADER}`);
        if (!header) {
            return;
        }

        header.remove();
    }

    addHorizontalRow() {
        const horizontalRow = document.createElement('hr');
        horizontalRow.classList.add(this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW);

        this.getCustomCheckoutEntireElement().insertBefore(
            horizontalRow,
            this.getCustomCheckoutEntireElement().firstChild
        );
    }

    removeHorizontalRow() {
        const horizontalRow = document.querySelector(`.${this.SUPER_TOKEN_STYLES.PAYMENT_METHODS_LIST_HORIZONTAL_ROW}`);
        if (!horizontalRow) {
            return;
        }
        horizontalRow.remove();
    }

    onCustomCheckoutWasRendered(customCheckoutEntireElement, paymentMethods) {
        this.hideWalletButton();
        this.hideCardFlags();
        this.convertCustomCheckoutAreaToPaymentMethodList(customCheckoutEntireElement);
        this.addHorizontalRow();
        this.addMercadoPagoPrivacyPolicyFooter();
        this.organizePaymentMethodsElements(paymentMethods);
        this.addPaymentMethodsListHeader(customCheckoutEntireElement);
        this.convertCreditCardFormToPaymentMethodElement(customCheckoutEntireElement);
        this.focusFirstPaymentMethod();
        this.selectPreloadedPaymentMethod();
        this.removeAnimationInitialState();
        this.hideAllPaymentMethodDetails();
        // After loading the payment methods, set the checkout type to super_token
        this.setCheckoutType(this.SUPER_TOKEN_CHECKOUT_TYPE);
    }

    removeAnimationInitialState() {
      const ANIMATION_DELAY = 750;

      const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();
      if (!customCheckoutEntireElement) return;

      setTimeout(() => {
        customCheckoutEntireElement.classList.remove(this.SUPER_TOKEN_STYLES.ANIMATION_CLASS);
      }, ANIMATION_DELAY);
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
        if (!this.activePaymentMethod) {
            return;
        }

        if (!this.isCreditCard(this.activePaymentMethod) && !this.isDebitCard(this.activePaymentMethod)) {
            return;
        }

        const paymentMethodElement = document.getElementById(`${this.activePaymentMethod?.id}${this.activePaymentMethod.card?.card_number?.last_four_digits}`);
        if (!paymentMethodElement) {
            return;
        }

        if (
          this.securityCodeIsRequired(this.activePaymentMethod?.security_code_settings) &&
          !this.verifyIsSecurityCodeReferenceTrue(this.activePaymentMethod)
        ) {
            this.forceSecurityCodeValidation(this.activePaymentMethod);
        }

        if (this.isCreditCard(this.activePaymentMethod) && !this.installmentsWasSelected(this.activePaymentMethod)) {
            this.setInstallmentsErrorState(this.activePaymentMethod, true);
        }
    }

    isSelectedPaymentMethodValid() {
        try {
            if (this.isAccountMoney(this.activePaymentMethod) || this.isPrepaidCard(this.activePaymentMethod)) {
                return true;
            }

            if (this.activePaymentMethod.id === this.NEW_CARD_TYPE) {
                return true;
            }

            const paymentMethodElement = document.getElementById(this.paymentMethodIdentifier(this.activePaymentMethod));
            if (!paymentMethodElement) {
                return false;
            }

            const installmentsDropdown = paymentMethodElement.querySelector(`#mp-super-token-installments-select-${this.paymentMethodIdentifier(this.activePaymentMethod)}`);
            if (!installmentsDropdown && this.isCreditCard(this.activePaymentMethod) && !this.installmentsWasSelected(this.activePaymentMethod)) {
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

            if (!this.verifyIsSecurityCodeReferenceTrue(this.activePaymentMethod)) {
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
        this.storeActivePaymentMethod(this.getPaymentMethodSelectedFromDOMToAccountPaymentMethods(accountPaymentMethods));

        if (this.paymentMethodsAreRendered()) return;

        if (!this.hasStoredPaymentMethods()) this.storePaymentMethodsInMemory(accountPaymentMethods);

        const customCheckoutEntireElement = this.getCustomCheckoutEntireElement();

        if (!customCheckoutEntireElement) throw new Error(MPSuperTokenErrorCodes.CUSTOM_CHECKOUT_ENTIRE_ELEMENT_NOT_FOUND);

        this.onCustomCheckoutWasRendered(
            customCheckoutEntireElement,
            accountPaymentMethods
        );

        setTimeout(() => {
          const sdkInstanceId = this.mpSuperTokenMetrics.getSdkInstanceId();
          document.dispatchEvent(new CustomEvent('supertoken_loaded', { detail: { sdkInstanceId } }));
        }, 500);
    }
}

/* globals jQuery, wc_mercadopago_ticket_checkout_params, CheckoutTicketElements, CheckoutTicketPage */

class CheckoutTicketPageController {
  static getPaymentMethodElements() {
    return document.querySelectorAll(
      '#payment_method_woo-mercado-pago-ticket, ' +
      '#radio-control-wc-payment-method-options-woo-mercado-pago-ticket, ' +
      'input[value="woo-mercado-pago-ticket"]'
    );
  }

  constructor(params) {
    this.siteId = params.site_id;
    this.errorMessages = params.error_messages;
    this.maskedDocNumberBeforeSubmit = null;
    this.boundEvents = new WeakMap();
    this.initElements();
    this.addEventListeners();
  }

  initElements() {
    const documentInput = document.querySelector('input[name="mercadopago_ticket[doc_number]"]');
    if (this.documentInput !== documentInput) {
      // A replacement may carry our normalized value; never copy a previous document
      // into a field the buyer changed or cleared.
      if (!this.canRestoreDocumentMask(documentInput)) {
        this.maskedDocNumberBeforeSubmit = null;
      }
      this.documentInput = documentInput;
    }
    this.checkboxUseShippingData = document.getElementById('form-checkout__address_checkbox');
    this.addressElements = {
      state: document.getElementById("form-checkout__address_federal_unit"),
      city: document.getElementById("form-checkout__address_city"),
      zipCode: document.getElementById("form-checkout__address_zip_code"),
      streetName: document.getElementById("form-checkout__address_street_name"),
      number: document.getElementById("form-checkout__address_street_number"),
      numberDiv: document.getElementsByClassName('mp-checkout-ticket-billing-input-number')[0],
      neighborhood: document.getElementById("form-checkout__address_neighborhood"),
      numberToggle: document.getElementById("form-checkout__address_number_toggle"),
      complement: document.getElementById("form-checkout__address_complement")
    };
    this.errorContainers = {
      zipCode: document.getElementById('form-checkout__address_zip_code_error'),
      state: document.getElementById('form-checkout__address_federal_unit_error'),
      city: document.getElementById('form-checkout__address_city_error'),
      neighborhood: document.getElementById('form-checkout__address_neighborhood_error'),
      streetName: document.getElementById('form-checkout__address_street_name_error'),
      streetNumber: document.getElementById('form-checkout__address_street_number_error')
    };
  }

  addEventListeners() {
    this.bindNativeEvent(this.checkboxUseShippingData, 'input', 'fillTicketAddressFieldsFromWoocommerce');
    this.bindNativeEvent(this.addressElements.zipCode, 'input', 'handleZipCodeInput');
    this.bindNativeEvent(this.addressElements.zipCode, 'focusout', 'validateZipCodeError');
    this.bindNativeEvent(this.addressElements.state, 'input', 'handleStateInput');
    this.bindNativeEvent(this.addressElements.state, 'focusout', 'validateStateError');
    this.bindNativeEvent(this.addressElements.city, 'input', 'handleCityInput');
    this.bindNativeEvent(this.addressElements.city, 'focusout', 'validateCityError');
    this.bindNativeEvent(this.addressElements.neighborhood, 'input', 'handleNeighborhoodInput');
    this.bindNativeEvent(this.addressElements.neighborhood, 'focusout', 'validateNeighborhoodError');
    this.bindNativeEvent(this.addressElements.streetName, 'input', 'handleStreetNameInput');
    this.bindNativeEvent(this.addressElements.streetName, 'focusout', 'validateStreetNameError');
    this.bindNativeEvent(this.addressElements.number, 'input', 'handleStreetNumberInput');
    this.bindNativeEvent(this.addressElements.number, 'focus', 'handleStreetNumberFocus');
    this.bindNativeEvent(this.addressElements.number, 'focusout', 'validateStreetNumberError');
    this.bindNativeEvent(this.addressElements.numberToggle, 'click', 'handleNumberToggle');
    this.bindNativeEvent(this.addressElements.complement, 'input', 'removeCheckedFromShippingDataCheckbox');
    this.handleSubmitEvents();
    this.listenToRemoveErrorFromPaymentMethod();
  }

  refresh() {
    this.initElements();
    this.addEventListeners();
  }

  registerEvent(element, key) {
    if (!element) {
      return false;
    }
    const events = this.boundEvents.get(element) ?? new Set();
    if (events.has(key)) {
      return false;
    }
    events.add(key);
    this.boundEvents.set(element, events);
    return true;
  }

  bindNativeEvent(element, eventName, methodName) {
    if (this.registerEvent(element, `native:${eventName}:${methodName}`)) {
      element.addEventListener(eventName, (event) => {
        if (element.isConnected) {
          this.initElements();
          this[methodName](event);
        }
      });
    }
  }

  bindJQueryEvent(element, eventName, methodName) {
    if (this.registerEvent(element, `jquery:${eventName}:${methodName}`)) {
      jQuery(element).on(eventName, () => this[methodName]());
    }
  }

  bindOrderPaySubmitGate(element) {
    if (this.registerEvent(element, 'jquery:submit:orderPaySubmitGate')) {
      jQuery(element).on('submit', event => this.orderPaySubmitGate(event));
    }
  }

  orderPaySubmitGate(event) {
    if (this.shouldSubmitTicketForm()) {
      return true;
    }

    // WooCommerce binds its order-pay submit handler to this form after this gate. That handler blocks the form,
    // and the block adds the overlay this gate has just removed, leaving the buyer on an invalid form. Stop it here.
    event.stopImmediatePropagation();
    return false;
  }

  fillTicketAddressFieldsFromWoocommerce() {
    if (!this.checkboxUseShippingData ||
        !['city', 'zipCode', 'streetName', 'state'].every(name => this.addressElements[name])) {
      return;
    }
    if (!this.checkboxUseShippingData.checked) {
      Object.values(this.addressElements).forEach(element => {
        if (element && 'value' in element) {
          element.value = '';
        }
      });
      return;
    }

    const getWooCommerceFieldValue = (fieldIds) => {
      for (const id of fieldIds) {
        const element = document.getElementById(id);
        if (element && element.value) {
          return element.value;
        }
      }
      return '';
    };

    this.addressElements.city.value = getWooCommerceFieldValue(["billing-city", "billing_city", "shipping-city", "shipping_city"]);
    this.addressElements.zipCode.value = this.validateZipCodeMask(
      getWooCommerceFieldValue(["billing-postcode", "billing_postcode", "shipping-postcode", "shipping_postcode"])
    );
    this.addressElements.streetName.value = getWooCommerceFieldValue(["billing-address_1", "billing_address_1", "shipping-address_1", "shipping_address_1"]);
    if (this.addressElements.complement) {
      this.addressElements.complement.value = getWooCommerceFieldValue(["billing-address_2", "billing_address_2", "shipping-address_2", "shipping_address_2"]);
    }

    const wooAddressStateAcronym = getWooCommerceFieldValue(["billing-state", "billing_state", "shipping-state", "shipping_state"]);
    const wooAddressStateText = getWooCommerceFieldValue(["components-form-token-input-1", "select2-billing_state-container"]);

    if (wooAddressStateAcronym) {
      this.addressElements.state.value = wooAddressStateAcronym;
    } else if (wooAddressStateText) {
      const valueForFederalUnit = Array.from(this.addressElements.state.options).find(option => option.textContent.includes(wooAddressStateText))?.value ?? '';
      this.addressElements.state.value = valueForFederalUnit;
    }

    this.addressFieldsFromTicketRowAreValid();
    this.validateDocument();
  }

  removeCheckedFromShippingDataCheckbox() {
    if (this.checkboxUseShippingData?.checked) {
      this.checkboxUseShippingData.checked = false;
    }
  }

  validateDocument() {
    // MLB/MLU render a required document. Missing markup must not bypass that guard.
    const documentElement = document.querySelector('.mp-checkout-ticket-container');
    if (documentElement?.querySelector('.mp-document')?.value) {
      return true;
    }
    documentElement?.querySelector('.mp-input')?.classList.add('mp-error');
    const helper = documentElement?.querySelector('input-document input-helper .mp-helper');
    if (helper) {
      helper.style.display = 'flex';
    }
    return false;
  }

  normalizeDocumentNumber() {
    const docInput = document.querySelector('input[name="mercadopago_ticket[doc_number]"]');
    if (docInput && docInput.value) {
      // Keep the masked value so it can be restored for display if the payment fails.
      // The ticket input-document has no hidden field, so we mutate the visible input;
      // a structural fix (hidden field, like the card checkout) is tracked in PSW-4175.
      if (!this.canRestoreDocumentMask(docInput)) {
        this.maskedDocNumberBeforeSubmit = docInput.value;
      }
      docInput.value = this.normalizedDocumentValue(docInput.value);
    }
  }

  normalizedDocumentValue(value) {
    return value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  canRestoreDocumentMask(docInput) {
    return Boolean(this.maskedDocNumberBeforeSubmit && docInput?.value &&
      docInput.value === this.normalizedDocumentValue(this.maskedDocNumberBeforeSubmit));
  }

  restoreDocumentMaskOnFailure() {
    this.initElements();
    const docInput = document.querySelector('input[name="mercadopago_ticket[doc_number]"]');
    if (this.canRestoreDocumentMask(docInput)) {
      docInput.value = this.maskedDocNumberBeforeSubmit;
    }
  }

  validateZipCodeMask(zipCode) {
    return zipCode.replace(/\D/g, '').replace(/(\d{5})(\d)/, '$1-$2');
  }

  handleZipCodeInput() {
    this.removeCheckedFromShippingDataCheckbox();
    this.addressElements.zipCode.value = this.validateZipCodeMask(this.addressElements.zipCode.value);
    this.validateZipCodeError(false);
  }

  validateZipCodeError(canChangeErrorState = true) {
    const zipCodeValue = this.addressElements.zipCode?.value.replace('-', '') ?? '';
    if (!zipCodeValue) {
      canChangeErrorState &&
        this.showError(this.addressElements.zipCode, this.errorContainers.zipCode, this.errorMessages.postalcode_error_empty);
      return false;
    }
    if (parseInt(zipCodeValue, 10) === 0) {
      canChangeErrorState &&
        this.showError(this.addressElements.zipCode, this.errorContainers.zipCode, this.errorMessages.postalcode_error_invalid);
      return false;
    }
    if (zipCodeValue.length < 8) {
      canChangeErrorState &&
        this.showError(this.addressElements.zipCode, this.errorContainers.zipCode, this.errorMessages.postalcode_error_partial);
      return false;
    }
    this.clearError(this.addressElements.zipCode, this.errorContainers.zipCode);
    return true;
  }

  handleStateInput() {
    this.removeCheckedFromShippingDataCheckbox();
    this.validateStateError(false);
  }

  validateStateError(canChangeErrorState = true) {
    if (!this.addressElements.state?.value) {
      canChangeErrorState &&
        this.showError(this.addressElements.state, this.errorContainers.state, this.errorMessages.state_error_unselected);
      return false;
    }
    this.clearError(this.addressElements.state, this.errorContainers.state);
    return true;
  }

  handleCityInput() {
    this.removeCheckedFromShippingDataCheckbox();
    this.validateCityError(false);
  }

  validateCityError(canChangeErrorState = true) {
    if (!this.addressElements.city?.value) {
      canChangeErrorState &&
        this.showError(this.addressElements.city, this.errorContainers.city, this.errorMessages.city_error_empty);
      return false;
    }
    if (this.addressElements.city.value.length < 3) {
      canChangeErrorState &&
        this.showError(this.addressElements.city, this.errorContainers.city, this.errorMessages.city_error_invalid);
      return false;
    }
    this.clearError(this.addressElements.city, this.errorContainers.city);
    return true;
  }

  handleNeighborhoodInput() {
    this.validateNeighborhoodError(false);
  }

  validateNeighborhoodError(canChangeErrorState = true) {
    if (!this.addressElements.neighborhood?.value) {
      canChangeErrorState &&
        this.showError(this.addressElements.neighborhood, this.errorContainers.neighborhood, this.errorMessages.neighborhood_error_empty);
      return false;
    }
    if (this.addressElements.neighborhood.value.length < 2) {
      canChangeErrorState &&
        this.showError(this.addressElements.neighborhood, this.errorContainers.neighborhood, this.errorMessages.neighborhood_error_invalid);
      return false;
    }
    this.clearError(this.addressElements.neighborhood, this.errorContainers.neighborhood);
    return true;
  }

  handleStreetNameInput() {
    this.removeCheckedFromShippingDataCheckbox();
    this.validateStreetNameError(false);
  }

  validateStreetNameError(canChangeErrorState = true) {
    if (!this.addressElements.streetName?.value) {
      canChangeErrorState &&
        this.showError(this.addressElements.streetName, this.errorContainers.streetName, this.errorMessages.address_error_empty);
      return false;
    }
    if (this.addressElements.streetName.value.length < 3) {
      canChangeErrorState &&
        this.showError(this.addressElements.streetName, this.errorContainers.streetName, this.errorMessages.address_error_invalid);
      return false;
    }
    this.clearError(this.addressElements.streetName, this.errorContainers.streetName);
    return true;
  }

  handleStreetNumberInput() {
    if (this.validateStreetNumberError(false)) {
      this.clearError(this.addressElements.numberDiv, this.errorContainers.streetNumber, false);
      this.addressElements.numberDiv.classList.add('mp-checkout-ticket-billing-input-number-focused');
    }
  }

  validateStreetNumberError(canChangeErrorState = true) {
    if (!this.addressElements.number || !this.addressElements.numberDiv || !this.addressElements.numberToggle) {
      return false;
    }
    if (canChangeErrorState &&
        this.addressElements.numberDiv.classList.contains('mp-checkout-ticket-billing-input-number-focused-error-on-focus')) {
      this.addressElements.numberDiv.classList.remove('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
    }

    if (this.addressElements.numberToggle.checked) {
      this.clearError(this.addressElements.numberDiv, this.errorContainers.streetNumber, false);
      return true;
    }
    if (!this.addressElements.number.value) {
      canChangeErrorState &&
        this.showError(this.addressElements.numberDiv, this.errorContainers.streetNumber, this.errorMessages.number_error_empty);
      return false;
    }
    if (parseInt(this.addressElements.number.value, 10) === 0) {
      canChangeErrorState &&
        this.showError(this.addressElements.numberDiv, this.errorContainers.streetNumber, this.errorMessages.number_error_invalid);
      return false;
    }

    canChangeErrorState && this.clearError(this.addressElements.numberDiv, this.errorContainers.streetNumber, false);
    canChangeErrorState && this.toggleFocusedClass(this.addressElements.numberDiv, false);
    return true;
  }

  handleStreetNumberFocus() {
    this.toggleFocusedClass(this.addressElements.numberDiv, true);
  }

  handleNumberToggle() {
    if (!this.addressElements.number || !this.addressElements.numberToggle || !this.addressElements.numberDiv) {
      return;
    }
    this.addressElements.number.disabled = this.addressElements.numberToggle.checked;
    if (this.addressElements.number.disabled) {
      this.addressElements.number.value = '';
      this.addressElements.numberDiv.classList.remove('mp-checkout-andes-input');
      this.addressElements.numberDiv.classList.add('mp-checkout-ticket-billing-input-number-disabled');
      this.toggleFocusedClass(this.addressElements.numberDiv, false);
      this.clearError(this.addressElements.numberDiv, this.errorContainers.streetNumber, false);
    } else {
      this.addressElements.numberDiv.classList.remove('mp-checkout-ticket-billing-input-number-disabled');
      this.addressElements.numberDiv.classList.add('mp-checkout-andes-input');
    }
  }

  toggleFocusedClass(element, add) {
    if (!element) {
      return;
    }
    if (add) {
      if (element.classList.contains('mp-checkout-ticket-billing-input-number-focused-error')) {
        element.classList.add('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
      } else if (element.classList.contains('mp-checkout-andes-input-error')) {
        element.classList.add('mp-checkout-ticket-billing-input-number-focused-error');
      } else {
        element.classList.add('mp-checkout-ticket-billing-input-number-focused');
      }
    } else {
      element.classList.remove('mp-checkout-ticket-billing-input-number-focused');
    }
  }

  showError(element, errorContainer, message) {
    if (!element || !errorContainer) {
      return;
    }
    element.classList.remove('mp-checkout-andes-input');
    element.classList.add('mp-checkout-andes-input-error');
    const errorHelperMessage = errorContainer.childNodes[1];
    if (errorHelperMessage) {
      errorHelperMessage.textContent = message;
    }
    errorContainer.style.display = 'flex';
  }

  clearError(element, errorContainer, addAndesInputClass = true) {
    if (!element || !errorContainer) {
      return;
    }
    addAndesInputClass && element.classList.add('mp-checkout-andes-input');
    element.classList.remove('mp-checkout-andes-input-error');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused-error');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
    errorContainer.style.display = 'none';
  }

  handleSubmitEvents() {
    document.querySelectorAll('form.checkout').forEach(form => {
      this.bindJQueryEvent(form, 'checkout_place_order_woo-mercado-pago-ticket', 'shouldSubmitTicketForm');
    });

    // If payment fail, retry on next checkout page
    document.querySelectorAll('form#order_review').forEach(form => {
      this.bindOrderPaySubmitGate(form);
    });

    // Restore our normalized value for display, including equivalent replacement inputs.
    // Never overwrite a document changed or cleared since submit. PSW-4175 tracks the
    // structural fix: a hidden field like the card checkout.
    this.bindJQueryEvent(document.body, 'checkout_error', 'restoreDocumentMaskOnFailure');
  }

  shouldSubmitTicketForm() {
    if (![...CheckoutTicketPageController.getPaymentMethodElements()].some(element => element.checked)) {
      return true;
    }

    this.initElements();
    const documentIsValid = !['MLB', 'MLU'].includes(this.siteId) || this.validateDocument();
    const paymentMethodIsValid = this.verifyPaymentMethods();

    const errorOnAddressFields = this.siteId === 'MLB' && !this.addressFieldsFromTicketRowAreValid();
    const hasError = !documentIsValid || !paymentMethodIsValid || this.checkForErrors() || errorOnAddressFields;
    hasError && this.removeBlockOverlay();

    if (!hasError && this.siteId === 'MLB') {
      this.normalizeDocumentNumber();
    }

    return !hasError;
  }

  checkForErrors() {
    let hasError = false;
    const ticketContainer = document.querySelector('.mp-checkout-ticket-container');

    if (!ticketContainer) {
      return false;
    }

    ticketContainer.querySelectorAll('input-helper').forEach((item) => {
      const inputHelper = item.querySelector('div');
      if (!inputHelper || inputHelper.style.display !== 'none') {
        hasError = true;
      }
    });

    return hasError;
  }

  verifyPaymentMethods() {
    let paymentOptionSelected = false;
    const documentElement = document.querySelector('.mp-checkout-ticket-container');
    if (!documentElement) {
      return false;
    }

    documentElement
      .querySelectorAll('.mp-input-radio-radio')
      .forEach((item) => {
        if (item.checked) {
          paymentOptionSelected = true;
        }
    });

    if (!paymentOptionSelected) {
      const hiddenPaymentMethod = documentElement.querySelector('input[name="mercadopago_ticket[payment_method_id]"][type="hidden"]');
      if (hiddenPaymentMethod && hiddenPaymentMethod.value) {
        paymentOptionSelected = true;
      }
    }

    this.setPaymentMethodError(!paymentOptionSelected);
    return paymentOptionSelected;
  }

  setPaymentMethodError(hasError) {
    const ticketContent = document.querySelector(CheckoutTicketElements.ticketContent);
    if (!ticketContent) {
      return;
    }
    CheckoutTicketPage.setDisplayOfError('fcInputTableContainer', hasError ? 'add' : 'remove', 'mp-error', 'ticketContent');
    if (ticketContent.querySelector('input-helper[input-id="mp-payment-method-helper"] div')) {
      CheckoutTicketPage.setDisplayOfInputHelper('mp-payment-method', hasError ? 'flex' : 'none', 'ticketContent');
    }
  }

  clearPaymentMethodError() {
    this.setPaymentMethodError(false);
  }

  listenToRemoveErrorFromPaymentMethod() {
    const ticketContent = document.querySelector(CheckoutTicketElements.ticketContent);
    ticketContent?.querySelectorAll('.mp-input-table-label')?.forEach((item) => {
      this.bindNativeEvent(item, 'click', 'clearPaymentMethodError');
    });
  }

  removeBlockOverlay() {
    if (document.querySelector('form#order_review')) {
      const overlay = document.querySelector('.blockOverlay');
      if (overlay) {
        overlay.style.display = 'none';
      }
    }
  }

  addressFieldsFromTicketRowAreValid() {
    this.initElements();
    return [
      this.validateZipCodeError(),
      this.validateStateError(),
      this.validateCityError(),
      this.validateNeighborhoodError(),
      this.validateStreetNameError(),
      this.validateStreetNumberError()
    ].every(Boolean);
  }
}

(() => {
  let checkoutTicketPageController = null;
  const refresh = () => {
    if (!CheckoutTicketPageController.getPaymentMethodElements().length || !document.querySelector(CheckoutTicketElements.ticketContent)) {
      return;
    }
    if (checkoutTicketPageController) {
      checkoutTicketPageController.refresh();
    } else {
      checkoutTicketPageController = new CheckoutTicketPageController(wc_mercadopago_ticket_checkout_params);
      // Define a global function for ticket.block.js to be able to call the addressFieldsFromTicketRowAreValid method
      window.addressFieldsFromTicketRowAreValid = checkoutTicketPageController.addressFieldsFromTicketRowAreValid.bind(checkoutTicketPageController);
    }
  };

  const start = () => {
    jQuery(document.body).on('updated_checkout', refresh);
    const mountingSelector = 'form.checkout, form#order_review, .mp-checkout-ticket-container, input[value="woo-mercado-pago-ticket"]';
    const observer = new MutationObserver(records => {
      const ticketChanged = records.some(record =>
        record.target.closest?.('.mp-checkout-ticket-container') ||
        [...record.addedNodes, ...record.removedNodes].some(node =>
          node.nodeType === 1 && (node.matches(mountingSelector) || node.querySelector(mountingSelector))
        )
      );
      if (ticketChanged) {
        refresh();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    refresh();
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();

/* globals wc_mercadopago_ticket_checkout_params, CheckoutTicketElements, CheckoutTicketPage */

class CheckoutTicketPageController {
  constructor(params) {
    this.siteId = params.site_id;
    this.errorMessages = params.error_messages;
    this.initElements();
    this.addEventListeners();
  }

  initElements() {
    this.checkboxUseShippingData = document.getElementById('form-checkout__address_checkbox');
    this.document = {
      content: document.querySelector(CheckoutTicketElements.ticketContent),
      helper: document.querySelector('input-document')?.querySelector('input-helper')
    }
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
    this.checkboxUseShippingData?.addEventListener('input', this.fillTicketAddressFieldsFromWoocommerce.bind(this));
    this.addressElements?.zipCode?.addEventListener('input', this.handleZipCodeInput.bind(this));
    this.addressElements?.zipCode?.addEventListener('focusout', this.validateZipCodeError.bind(this));
    this.addressElements?.state?.addEventListener('input', this.handleStateInput.bind(this));
    this.addressElements?.state?.addEventListener('focusout', this.validateStateError.bind(this));
    this.addressElements?.city?.addEventListener('input', this.handleCityInput.bind(this));
    this.addressElements?.city?.addEventListener('focusout', this.validateCityError.bind(this));
    this.addressElements?.neighborhood?.addEventListener('input', this.handleNeighborhoodInput.bind(this));
    this.addressElements?.neighborhood?.addEventListener('focusout', this.validateNeighborhoodError.bind(this));
    this.addressElements?.streetName?.addEventListener('input', this.handleStreetNameInput.bind(this));
    this.addressElements?.streetName?.addEventListener('focusout', this.validateStreetNameError.bind(this));
    this.addressElements?.number?.addEventListener('input', this.handleStreetNumberInput.bind(this));
    this.addressElements?.number?.addEventListener('focus', this.handleStreetNumberFocus.bind(this));
    this.addressElements?.number?.addEventListener('focusout', this.validateStreetNumberError.bind(this));
    this.addressElements?.numberToggle?.addEventListener('click', this.handleNumberToggle.bind(this));
    this.addressElements?.complement?.addEventListener('input', this.removeCheckedFromShippingDataCheckbox.bind(this));
    this.handleSubmitEvents();
    this.listenToRemoveErrorFromPaymentMethod();
  }

  fillTicketAddressFieldsFromWoocommerce() {
    if (!this.checkboxUseShippingData.checked) {
      Object.values(this.addressElements).forEach(element => element.value = '');
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
    this.addressElements.complement.value = getWooCommerceFieldValue(["billing-address_2", "billing_address_2", "shipping-address_2", "shipping_address_2"]);

    const wooAddressStateAcronym = getWooCommerceFieldValue(["billing-state", "billing_state", "shipping-state", "shipping_state"]);
    const wooAddressStateText = getWooCommerceFieldValue(["components-form-token-input-1", "select2-billing_state-container"]);

    if (wooAddressStateAcronym) {
      this.addressElements.state.value = wooAddressStateAcronym;
    } else if (wooAddressStateText) {
      const valueForFederalUnit = Array.from(this.addressElements.state.options).find(option => option.textContent.includes(wooAddressStateText)).value;
      this.addressElements.state.value = valueForFederalUnit;
    }

    this.addressFieldsFromTicketRowAreValid();
    this.validateDocument();
  }

  removeCheckedFromShippingDataCheckbox() {
    if (this.checkboxUseShippingData.checked) {
      this.checkboxUseShippingData.checked = false;
    }
  }

  validateDocument() {
    let documentElement = this.document.content;

    if (documentElement.querySelector('.mp-document')?.value === '') {
      documentElement.querySelector('.mp-input').classList.add('mp-error');
      documentElement.querySelector('input-document').querySelector('input-helper').querySelector('.mp-helper').style.display = 'flex';
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
    const zipCodeValue = this.addressElements.zipCode.value.replace('-', '');
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
    if (!this.addressElements.state.value) {
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
    if (!this.addressElements.city.value) {
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
    if (!this.addressElements.neighborhood.value) {
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
    if (!this.addressElements.streetName.value) {
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
    element.classList.remove('mp-checkout-andes-input');
    element.classList.add('mp-checkout-andes-input-error');
    const errorMessageElement = errorContainer.querySelector('.mp-helper-message');
    if (errorMessageElement) {
      errorMessageElement.textContent = message;
    }
    errorContainer.style.display = 'flex';
  }

  clearError(element, errorContainer, addAndesInputClass = true) {
    addAndesInputClass && element.classList.add('mp-checkout-andes-input');
    element.classList.remove('mp-checkout-andes-input-error');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused-error');
    element.classList.remove('mp-checkout-ticket-billing-input-number-focused-error-on-focus');
    errorContainer.style.display = 'none';
  }

  handleSubmitEvents() {
    jQuery('form.checkout').on('checkout_place_order_woo-mercado-pago-ticket', () => this.shouldSubmitTicketForm())
    
    // If payment fail, retry on next checkout page
    jQuery('form#order_review').submit(() => this.shouldSubmitTicketForm());
  }

  shouldSubmitTicketForm() {
    if (!document.getElementById('payment_method_woo-mercado-pago-ticket')?.checked) {
      return true;
    }

    if (this.siteId === 'MLB' || this.siteId === 'MLU') {
      this.validateDocument();
    }
    this.verifyPaymentMethods();

    const errorOnAddressFields = this.siteId === 'MLB' && !this.addressFieldsFromTicketRowAreValid();
    const hasError = this.checkForErrors() || errorOnAddressFields;
    hasError && this.removeBlockOverlay();
    return !hasError;
  }

  checkForErrors() {
    let hasError = false;

    document.querySelectorAll('input-helper').forEach((item) => {
      let inputHelper = item.querySelector('div');
      if (inputHelper.style.display !== 'none') {
        hasError = true;
      }
    });

    return hasError;
  }

  verifyPaymentMethods() {
    let paymentOptionSelected = false;
    this.document.content.querySelectorAll('.mp-input-radio-radio').forEach((item) => {
      if (item.checked) {
        paymentOptionSelected = true;
      }
    });

    if (paymentOptionSelected === false) {
      CheckoutTicketPage.setDisplayOfError('fcInputTableContainer', 'add', 'mp-error', 'ticketContent');
      CheckoutTicketPage.setDisplayOfInputHelper('mp-payment-method', 'flex', 'ticketContent');
    }
  }

  listenToRemoveErrorFromPaymentMethod() {
    let ticketContent = document.querySelector(CheckoutTicketElements.ticketContent);
    ticketContent?.querySelectorAll('.mp-input-table-label')?.forEach((item) => {
      item.addEventListener('click', () => {
        CheckoutTicketPage.setDisplayOfError('fcInputTableContainer', 'remove', 'mp-error', 'ticketContent');
        CheckoutTicketPage.setDisplayOfInputHelper('mp-payment-method', 'none', 'ticketContent');
      });
    });
  }

  removeBlockOverlay() {
    if (document.querySelector('form#order_review')) {
      document.querySelector('.blockOverlay').style.display = 'none';
    }
  }

  addressFieldsFromTicketRowAreValid() {
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

document.addEventListener('DOMContentLoaded', function () {
  const ticketFormLoadInterval = setInterval(function () {
    const checkoutTicketPaymentMethodElement = 
      document.getElementById('payment_method_woo-mercado-pago-ticket') ?? 
      document.getElementById('radio-control-wc-payment-method-options-woo-mercado-pago-ticket') ??
      document.querySelectorAll("input[value=woo-mercado-pago-ticket]")[0];

    if (!checkoutTicketPaymentMethodElement) {
      clearInterval(ticketFormLoadInterval);
      return;
    }

    const checkoutTicketPageController = new CheckoutTicketPageController(wc_mercadopago_ticket_checkout_params);
    // Define a global function for ticket.block.js to be able to call the addressFieldsFromTicketRowAreValid method
    window.addressFieldsFromTicketRowAreValid = checkoutTicketPageController.addressFieldsFromTicketRowAreValid.bind(checkoutTicketPageController);
  }, 1000);
});

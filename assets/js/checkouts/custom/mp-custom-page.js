/* globals wc_mercadopago_custom_checkout_params, CheckoutElements, cardForm */

const CheckoutPage = {
  setElementDisplay(element, operator) {
    document.querySelector(CheckoutElements[element]).style.display = operator;
  },

  setText(element, text) {
    document.querySelector(CheckoutElements[element]).innerHTML = text;
  },

  setValue(element, value) {
    document.querySelector(CheckoutElements[element]).value = value;
  },

  setBackground(element, background) {
    document.querySelector(CheckoutElements[element]).style.setProperty('background', background, 'important');
  },

  setImageCard(secureThumbnail) {
    this.setBackground('fcCardNumberContainer', 'url(' + secureThumbnail + ') 98% 50% no-repeat #fff');
    document
      .querySelector(CheckoutElements.fcCardNumberContainer)
      .style.setProperty('background-size', 'auto 32px', 'important');
  },

  findContainerField(field) {
    let id = field === 'cardholderName' ? `#form-checkout__${field}` : `#form-checkout__${field}-container`;

    return Object.keys(CheckoutElements).find((key) => CheckoutElements[key] === id);
  },

  setDisplayOfError(elementName, operator, className, checkoutSelector = 'customContent') {
    let checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    let element = checkoutContent.querySelector(CheckoutElements[elementName]);

    if (element) {
      if (operator === 'add') {
        element.classList.add(`${className}`);
      } else {
        element.classList.remove(`${className}`);
      }
    }
  },

  setDisplayOfInputHelper(elementName, operator, checkoutSelector = 'customContent') {
    let checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    let divInputHelper = checkoutContent.querySelector(`input-helper[input-id=${elementName}-helper]`);

    if (divInputHelper) {
      let inputHelper = divInputHelper.querySelector('div');
      inputHelper.style.display = operator;
    }
  },

  setCvvHint(securityCode) {
    var cvvText = wc_mercadopago_custom_checkout_params.cvvText;
    cvvText = `${securityCode.length} ${cvvText} `;
    cvvText += this.cvvLocationTranslate(securityCode.card_location);
    this.setText('mpSecurityCodeInfo', cvvText);
  },

  cvvLocationTranslate(location) {
    let cvvFront = wc_mercadopago_custom_checkout_params.cvvHint['front'];
    let cvvBack = wc_mercadopago_custom_checkout_params.cvvHint['back'];
    return location === 'back' ? cvvBack : cvvFront;
  },

  additionalInfoHandler(additionalInfoNeeded) {
    if (additionalInfoNeeded.cardholder_name) {
      this.setElementDisplay('fcCardholderName', 'block');
    } else {
      this.setElementDisplay('fcCardholderName', 'none');
    }

    if (additionalInfoNeeded.issuer) {
      this.setElementDisplay('mpIssuerContainer', 'block');
    } else {
      this.setElementDisplay('mpIssuerContainer', 'none');
    }

    if (additionalInfoNeeded.cardholder_identification_type && additionalInfoNeeded.cardholder_identification_number) {
      this.setElementDisplay('mpDocumentContainer', 'block');
    } else {
      this.setElementDisplay('mpDocumentContainer', 'none');
    }
  },

  getCountry() {
    return wc_mercadopago_custom_checkout_params.site_id;
  },

  changeCvvPlaceHolder(cvvLength) {
    let text = '';

    for (let index = 0; index < cvvLength; index++) {
      text += index + 1;
    }

    cardForm.update('securityCode', { placeholder: text });
  },

  clearTax() {
    this.setElementDisplay('mpInputTaxCft', 'none');
    this.setText('mpTaxCftText', '');
    this.setText('mpTaxTeaText', '');
  },

  installment_amount(paymentTypeId) {
    let element = document.querySelector(CheckoutElements.fcInstallments);

    if (paymentTypeId === 'debit_card') {
      element.setAttribute('disabled', 'disabled');
    } else {
      element.removeAttribute('disabled');
    }
  },

  formatCurrency(value) {
    const formatter = new Intl.NumberFormat(wc_mercadopago_custom_checkout_params.intl, {
      currency: wc_mercadopago_custom_checkout_params.currency,
      style: 'currency',
      currencyDisplay: 'narrowSymbol',
    });

    return formatter.format(value);
  },

  inputHelperName(field) {
    let inputHelperName = {
      cardNumber: CheckoutElements.mpCardNumber,
      cardholderName: CheckoutElements.mpCardholderName,
      expirationDate: CheckoutElements.mpExpirationDate,
      securityCode: CheckoutElements.mpSecurityCode,
      identificationNumber: CheckoutElements.mpIdentificationNumber,
    };

    return inputHelperName[field];
  },

  removeAdditionFields() {
    this.setElementDisplay('mpDocumentContainer', 'none');
    this.setElementDisplay('mpInstallments', 'none');
    this.setElementDisplay('mpIssuerContainer', 'none');
    this.setDisplayOfInputHelper('installments', 'none');
    this.setValue('cardInstallments', '');
  },

  clearInstallmentsComponent() {
    const selectorInstallments = document.querySelector(CheckoutElements.mpInstallmentsContainer);

    selectorInstallments.classList.remove(CheckoutElements.mpInstallmentsContainer);

    if (selectorInstallments.firstElementChild) {
      selectorInstallments.removeChild(selectorInstallments.firstElementChild);
    }
  },

  showInstallmentsComponent(child) {
    const selectorInstallments = document.querySelector(CheckoutElements.mpInstallmentsContainer);

    selectorInstallments.classList.add(CheckoutElements.mpInstallmentsContainer);

    selectorInstallments.appendChild(child);
  },

  getHelperMessage(field) {
    let query = 'input-helper[input-id=' + this.inputHelperName(field) + '-helper]';
    let divInputHelper = document.querySelector(query);

    return divInputHelper.querySelector('div[class=mp-helper-message]');
  },

  argentinaResolution(payerCosts) {
    let dataInput = '';

    if (this.getCountry() === 'MLA') {
      for (let l = 0; l < payerCosts.length; l++) {
        if (payerCosts[l].indexOf('CFT_') !== -1) {
          dataInput = payerCosts[l];
        }
      }
    }

    return dataInput;
  },

  hideErrors() {
    let customContent = document.querySelector('.mp-checkout-custom-container');
    let inputHelpers = customContent.querySelectorAll('input-helper');

    inputHelpers.forEach((inputHelper) => {
      inputHelper.querySelector('div').style.display = 'none';
    });
  },

  clearInputs() {
    this.hideErrors();
    this.setBackground('fcCardNumberContainer', 'no-repeat #fff');
    this.setValue('fcCardholderName', '');
    this.setDisplayOfError('fcCardholderName', 'removed', 'mp-error');

    this.setValue('fcCardExpirationDateContainer', '');
    this.setDisplayOfError('fcCardExpirationDateContainer', 'removed', 'mp-error');

    this.setValue('fcSecurityNumberContainer', '');
    this.setDisplayOfError('fcSecurityNumberContainer', 'removed', 'mp-error');

    this.setValue('fcIdentificationNumber', '');
    this.setElementDisplay('mpDocumentContainer', 'none');
    this.setDisplayOfError('fcIdentificationNumberContainer', 'removed', 'mp-error');

    this.clearInstallmentsComponent();
    this.setElementDisplay('mpInstallments', 'none');

    document.querySelector('input[data-cy=input-document]').value = '';
  },

  verifyDocument() {
    let input = document.querySelector(CheckoutElements.fcIdentificationNumber);
    let inputContainer = document.querySelector(CheckoutElements.mpDocumentContainer);

    if (inputContainer.style.display === 'none' || inputContainer.style.display === '') {
      return true;
    }

    if (input.value === '-1' || input.value === '') {
      return false;
    }

    let inputHelper = document.querySelector('input-helper[input-id=mp-doc-number-helper]');

    return inputHelper.querySelector('div').style.display !== 'flex';
  },

  loadAdditionalInfo(sdkAdditionalInfoNeeded) {
    const additionalInfoNeeded = {
      issuer: false,
      cardholder_name: false,
      cardholder_identification_type: false,
      cardholder_identification_number: false,
    };

    for (let i = 0; i < sdkAdditionalInfoNeeded.length; i++) {
      if (sdkAdditionalInfoNeeded[i] === 'issuer_id') {
        additionalInfoNeeded.issuer = true;
      }

      if (sdkAdditionalInfoNeeded[i] === 'cardholder_name') {
        additionalInfoNeeded.cardholder_name = true;
      }

      if (sdkAdditionalInfoNeeded[i] === 'cardholder_identification_type') {
        additionalInfoNeeded.cardholder_identification_type = true;
      }

      if (sdkAdditionalInfoNeeded[i] === 'cardholder_identification_number') {
        additionalInfoNeeded.cardholder_identification_number = true;
      }
    }
    return additionalInfoNeeded;
  },

  verifyInstallments() {
    if (document.querySelector(CheckoutElements.cardInstallments).value === '') {
      CheckoutPage.setDisplayOfError('fcInputTableContainer', 'add', 'mp-error');
      this.setDisplayOfInputHelper('mp-installments', 'flex');
      return false;
    }

    CheckoutPage.setDisplayOfError('fcInputTableContainer', 'remove', 'mp-error');
    this.setDisplayOfInputHelper('mp-installments', 'none');

    return true;
  },

  validateInputsCreateToken() {
    let isInstallmentsValid = this.verifyInstallments();
    let isDocumentValid = this.verifyDocument();

    return isInstallmentsValid && isDocumentValid;
  },

  showTaxes() {
    let choCustomContent = document.querySelector('.mp-checkout-custom-container');

    const selectorInstallments = choCustomContent.querySelectorAll(CheckoutElements.mpInputRadio);

    let tax = null;
    let display = 'block';

    selectorInstallments.forEach((installment) => {
      if (installment.checked) {
        tax = installment.getAttribute('datarate');
      }
    });

    let cft = '';
    let tea = '';

    if (tax != null) {
      const tax_split = tax.split('|');

      cft = tax_split[0].replace('_', ' ');
      tea = tax_split[1].replace('_', ' ');

      if (cft === 'CFT 0,00%' && tea === 'TEA 0,00%') {
        display = 'none';
        cft = '';
        tea = '';
      }
    }

    document.querySelector(CheckoutElements.mpInputTaxCft).style.display = display;
    document.querySelector(CheckoutElements.mpTaxCftText).innerHTML = cft;
    document.querySelector(CheckoutElements.mpTaxTeaText).innerHTML = tea;
  },

  setupTaxEvents() {
    const choCustomContent = document.querySelector(CheckoutElements.customContent);
    const taxesElements = choCustomContent.getElementsByClassName('mp-input-table-label');

    for (var i = 0; i < taxesElements.length; i++) {
      let installmentValue = taxesElements[i].getElementsByTagName('input')[0].value;

      if (wc_mercadopago_custom_checkout_params.site_id === 'mla') {
        taxesElements[i].addEventListener('click', this.showTaxes);
      }

      taxesElements[i].addEventListener('click', () => {
        CheckoutPage.setDisplayOfError('fcInputTableContainer', 'remove', 'mp-error');
        this.setDisplayOfInputHelper('mp-installments', 'none');
        this.setValue('fcInstallments', installmentValue);
        this.setValue('cardInstallments', installmentValue);
      });
    }
  },

  getBankInterestDisclaimerCountries(siteId) {
    return siteId === 'mlc' || siteId === 'mco' || siteId === 'mpe';
  },

  getInstallments(response, bankInterestDisclaimer) {
    let payerCosts = [];
    const installments = [];

    this.clearInstallmentsComponent();
    payerCosts = response.payer_costs;
    if (payerCosts) {
      this.setElementDisplay('mpInstallments', 'block');
    }

    for (let j = 0; j < payerCosts.length; j++) {
      const installment = payerCosts[j].installments;
      const installmentRate = payerCosts[j].installment_rate === 0;
      const installmentRateCollector = payerCosts[j].installment_rate_collector.includes('MERCADOPAGO');
      const installmentTotalAmount = this.formatCurrency(payerCosts[j].total_amount);

      const backInterestText = bankInterestDisclaimer
        ? `${installmentTotalAmount} + ${wc_mercadopago_custom_checkout_params.interestText}`
        : installmentTotalAmount;

      installments.push({
        id: `installment-${installment}`,
        value: installment,
        highlight: installmentRate && installmentRateCollector ? 'true' : '',
        dataRate: this.argentinaResolution(payerCosts[j].labels),
        rowText: payerCosts[j].recommended_message.split('(')[0],
        rowObs:
          installmentRate && installmentRateCollector
            ? wc_mercadopago_custom_checkout_params.installmentObsFee
            : backInterestText,
      });
    }

    return installments;
  },

  setChangeEventOnInstallments(siteId, response) {
    const bankInterestDisclaimer = this.getBankInterestDisclaimerCountries(siteId);
    const installments = this.getInstallments(response, bankInterestDisclaimer);

    const inputTable = document.createElement('input-table');
    inputTable.setAttribute('name', 'mp-installments');
    inputTable.setAttribute('button-name', wc_mercadopago_custom_checkout_params.installmentButton);
    inputTable.setAttribute('columns', JSON.stringify(installments));

    if (bankInterestDisclaimer) {
      inputTable.setAttribute('bank-interest-text', wc_mercadopago_custom_checkout_params.bankInterestText);
    }

    this.setElementDisplay('mpInstallments', 'block');
    this.showInstallmentsComponent(inputTable);
    this.setupTaxEvents();

    let customContent = document.querySelector('.mp-checkout-custom-container');
    customContent.querySelector('#more-options').addEventListener('click', () => {
      setTimeout(() => {
        this.setupTaxEvents();
      }, 300);
    });

    if (siteId === 'mla') {
      this.clearTax();
    }
  },
};

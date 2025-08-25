/* globals wc_mercadopago_custom_checkout_params, wc_mercadopago_custom_page_params CheckoutElements */

const CheckoutPage = {
  installmentsEnabled: false,

  setElementDisplay(element, operator) {
    document.querySelector(CheckoutElements[element]).style.display = operator;
  },

  setText(element, text) {
    document.querySelector(CheckoutElements[element]).innerHTML = text;
  },

  // Public method
  setValueOn(element, value) {
    document.querySelector(CheckoutElements[element]).value = value;
  },

  // Public method
  setBackground(element, background) {
    document.querySelector(CheckoutElements[element]).style.setProperty('background', background, 'important');
  },

  // Public method
  setImageCard(secureThumbnail) {
    this.setBackground('fcCardNumberContainer', 'url(' + secureThumbnail + ') 98% 50% no-repeat #fff');
    document
      .querySelector(CheckoutElements.fcCardNumberContainer)
      .style.setProperty('background-size', 'auto', 'important');
  },

  // Public method
  findContainerField(field) {
    let id = field === 'cardholderName' ? `#form-checkout__${field}` : `#form-checkout__${field}-container`;

    return Object.keys(CheckoutElements).find((key) => CheckoutElements[key] === id);
  },

  // Public method
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

  // Public method
  setDisplayOfInputHelper(elementName, operator, checkoutSelector = 'customContent') {
    let checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    let divInputHelper = checkoutContent.querySelector(`input-helper[input-id=${elementName}-helper]`);

    if (divInputHelper) {
      let inputHelper = divInputHelper.querySelector('div');
      inputHelper.style.display = operator;
    }
  },

  // Public method
  setDisplayOfInputHelperMessage(elementName, message, checkoutSelector = 'customContent') {
    let checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    let divInputHelper = checkoutContent.querySelector(`input-helper[input-id=${elementName}-helper]`);

    if (divInputHelper) {
      let inputHelperMessage = divInputHelper.querySelector('div').childNodes[1];
      inputHelperMessage.innerHTML = message;
    }
  },

  setCvvConfig(securityCode) {
    this.setCvvHint(securityCode.length);
    this.changeCvvPlaceHolder(securityCode.length);
  },

  changeCvvPlaceHolder(cvvLength) {
    if (cvvLength === 3) {
      window.mpCustomCheckoutHandler.cardForm.form.update('securityCode', { placeholder: wc_mercadopago_custom_page_params.security_code_placeholder_text_3_digits });
    } else {
      window.mpCustomCheckoutHandler.cardForm.form.update('securityCode', { placeholder: wc_mercadopago_custom_page_params.security_code_placeholder_text_4_digits });
    }
  },

  setCvvHint(cvvLength) {
    if (cvvLength === 3) {
      document.querySelector(CheckoutElements.mpSecurityCodeInfo).setAttribute('data-tooltip', wc_mercadopago_custom_page_params.security_code_tooltip_text_3_digits);
    } else {
      document.querySelector(CheckoutElements.mpSecurityCodeInfo).setAttribute('data-tooltip', wc_mercadopago_custom_page_params.security_code_tooltip_text_4_digits);
    }
  },

  // Public method
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

  formatCurrency(value) {
    const formatter = new Intl.NumberFormat(wc_mercadopago_custom_checkout_params.intl, {
      currency: wc_mercadopago_custom_checkout_params.currency,
      style: 'currency',
      currencyDisplay: 'narrowSymbol',
    });

    let formattedValue = formatter.format(value);

    if (this.getCountry() === 'MLM') {
      formattedValue = formattedValue.replace(/^(\D+)/, '$1 ');
    }

    return formattedValue;
  },

  // Public method
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

  // Public method
  removeAdditionFields() {
    this.setElementDisplay('mpDocumentContainer', 'none');
    this.setElementDisplay('mpInstallmentsCard', 'none');
    this.setElementDisplay('mpIssuerContainer', 'none');
    this.setValueOn('cardInstallments', '');
  },

  // Public method
  getHelperMessage(field) {
    let query = 'input-helper[input-id=' + this.inputHelperName(field) + '-helper]';
    let divInputHelper = document.querySelector(query);
    let helper = divInputHelper.querySelector('div[class=mp-helper]');
    return helper.childNodes[1];
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

  // Public method
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

  // Public method
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

  shouldEnableInstallmentsComponent(paymentTypeId) {
    if (paymentTypeId === 'debit_card') {
      this.clearInstallmentsComponent();
      this.installmentsEnabled = false;
      return;
    }
    this.installmentsEnabled = true;
  },

  hideErrors() {
    let customContent = document.querySelector('.mp-checkout-custom-container');
    let inputHelpers = customContent.querySelectorAll('input-helper');

    inputHelpers.forEach((inputHelper) => {
      inputHelper.querySelector('div').style.display = 'none';
    });
  },

  // Public method
  clearInputs() {
    this.hideErrors();
    this.setBackground('fcCardNumberContainer', 'no-repeat #fff');
    this.setValueOn('fcCardholderName', '');
    this.setDisplayOfError('fcCardholderName', 'removed', 'mp-error');

    this.setValueOn('fcCardExpirationDateContainer', '');
    this.setDisplayOfError('fcCardExpirationDateContainer', 'removed', 'mp-error');

    this.setValueOn('fcSecurityNumberContainer', '');
    this.setDisplayOfError('fcSecurityNumberContainer', 'removed', 'mp-error');

    this.setValueOn('fcIdentificationNumber', '');
    this.setElementDisplay('mpDocumentContainer', 'none');
    this.setDisplayOfError('fcIdentificationNumberContainer', 'removed', 'mp-error');

    this.clearInstallmentsComponent();
    this.setElementDisplay('mpInstallmentsCard', 'none');

    document.querySelector('input[data-cy=input-document]').value = '';
  },

  needsBankInterestDisclaimer() {
    const siteId = this.getCountry();
    return siteId.toUpperCase() === 'MLC' || siteId.toUpperCase() === 'MCO' || siteId.toUpperCase() === 'MPE';
  },

  getInstallments(response) {
    let payerCosts = [];
    const installments = [];

    this.clearInstallmentsComponent();
    payerCosts = this.getCountry() === 'MCO' ? response.payer_costs.slice(0, Math.min(6, response.payer_costs.length)) : response.payer_costs;
    if (payerCosts) {
      this.setElementDisplay('mpInstallmentsCard', 'block');
    }

    payerCosts.forEach((payerCost) => {
      const installment = payerCost.installments;
      const installmentAmount = this.formatCurrency(payerCost.installment_amount);
      const installmentRate = payerCost.installment_rate !== 0;
      const installmentRateThirdParty = payerCost.installment_rate_collector.includes('THIRD_PARTY');
      const totalAmount = this.formatCurrency(payerCost.total_amount);

      let title = `${installment.toString()}x `;

      if (installment == 1) {
        title += totalAmount;
      } else if (installmentRate) {
        title += `${installmentAmount} (${totalAmount})`;
      } else if (this.needsBankInterestDisclaimer() && installmentRateThirdParty && !installmentRate) {
        title += `${installmentAmount} (${totalAmount})*`;
      } else {
        title += `${installmentAmount} ${wc_mercadopago_custom_checkout_params.input_helper_message.installments.interest_free_option_text}`;
      }

      const item = {
        value: installment.toString(),
        title: title,
      };

      if (this.getCountry() === 'MLA' && payerCost.labels) {
        const taxInfo = this.parseTaxInfo(payerCost.labels);
        if (taxInfo) {
          item.taxInfo = taxInfo;
        }
      }

      installments.push(item);
    });

    return installments;
  },

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
  },

  verifyInstallmentsContainer() {
    try {
      const installmentsContainer = document.querySelector(CheckoutElements.mpInstallmentsContainer);
      if (installmentsContainer) {
        if (installmentsContainer.firstElementChild) {
          installmentsContainer.firstElementChild.state.hasInteracted = true;
          return installmentsContainer.firstElementChild.validate();
        }
      }
    } catch (error) {
      console.error('Error verifying installments container', error);
      return false;
    }
  },

  verifyCardholderName() {
    const cardholderNameInput = document.querySelector(CheckoutElements.fcCardholderName);
    const cardholderNameHelper = document.querySelector('#mp-card-holder-name-helper');
    
    if (!cardholderNameInput) {
      return true;
    }

    const isValid = cardholderNameInput.value && cardholderNameInput.value.trim().length > 0;
    if (!isValid) {
      if (cardholderNameHelper) {
        cardholderNameHelper.style.display = 'flex';
      }
      this.setDisplayOfError('fcCardholderName', 'add', 'mp-error');
    } else {
      if (cardholderNameHelper) {
        cardholderNameHelper.style.display = 'none';
      }
      this.setDisplayOfError('fcCardholderName', 'remove', 'mp-error');
    }

    return isValid;
  },

  // Public method
  setChangeEventOnInstallments(response) {
    this.clearInstallmentsComponent();
    const installments = this.getInstallments(response);
    const sdkSelect = document.getElementById('form-checkout__installments');

    if (!this.installmentsEnabled) {
      CheckoutPage.setValueOn('cardInstallments', '1');
      sdkSelect.value = '1';
      return;
    }

    const andesDropdown = document.createElement('andes-dropdown');
    andesDropdown.setAttribute('id', CheckoutElements.mpInstallments);
    andesDropdown.setAttribute('label', wc_mercadopago_custom_checkout_params.input_title.installments);
    andesDropdown.setAttribute('placeholder', wc_mercadopago_custom_checkout_params.placeholders['installments']);
    andesDropdown.setAttribute('items', JSON.stringify(installments));
    andesDropdown.setAttribute('required-message', wc_mercadopago_custom_checkout_params.input_helper_message.installments.required);
    andesDropdown.setAttribute('site-id', this.getCountry());
    
    if (this.needsBankInterestDisclaimer()) {
      andesDropdown.setAttribute('hint', '*' + wc_mercadopago_custom_checkout_params.input_helper_message.installments.bank_interest_hint_text);
    }

    andesDropdown.addEventListener('change', (event) => {
      const selectedItem = event.detail;
      if (selectedItem) {
        this.setValueOn('cardInstallments', selectedItem.value);
        sdkSelect.value = selectedItem.value;
      }
    });

    this.showInstallmentsComponent(andesDropdown);
    this.setElementDisplay('mpInstallmentsCard', 'block');
  },
};

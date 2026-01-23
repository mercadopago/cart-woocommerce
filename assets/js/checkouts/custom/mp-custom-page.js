/* globals wc_mercadopago_custom_checkout_params, wc_mercadopago_custom_page_params CheckoutElements */

const CheckoutPage = {
  installmentsEnabled: false,
  INSTALLMENTS_SELECT_PLACHOLDER_TEXT: wc_mercadopago_custom_page_params.installments_select_placeholder_text,
  TAX_TYPES: {
    cft: { key: 'CFT_', label: 'CFTEA', bold: true },
    tna: { key: 'TNA_', label: 'TNA', bold: false },
    tea: { key: 'TEA_', label: 'TEA', bold: false },
  },

  setElementDisplay(element, operator) {
    const elementToSet = document.querySelector(CheckoutElements[element]);
    if (!elementToSet) return;

    elementToSet.style.display = operator;
  },

  setText(element, text) {
    const elementToSet = document.querySelector(CheckoutElements[element]);
    if (!elementToSet) return;

    elementToSet.innerHTML = text;
  },

  setValueOn(element, value) {
    const elementToSet = document.querySelector(CheckoutElements[element]);
    if (!elementToSet) return;

    elementToSet.value = value;
  },

  setBackground(element, background) {
    const elementToSet = document.querySelector(CheckoutElements[element]);
    if (!elementToSet) return;

    elementToSet.style.setProperty('background', background, 'important');
  },

  setImageCard(secureThumbnail) {
    const cardNumberContainer = document.querySelector(CheckoutElements.fcCardNumberContainer);
    if (!cardNumberContainer) return;

    cardNumberContainer.style.setProperty('--card-brand-icon', `url(${secureThumbnail})`, 'important');
    cardNumberContainer.classList.add('mp-card-icon-detected');
  },

  findContainerField(field) {
    let id = field === 'cardholderName' ? `#form-checkout__${field}` : `#form-checkout__${field}-container`;

    return Object.keys(CheckoutElements).find((key) => CheckoutElements[key] === id);
  },

  setDisplayOfError(elementName, operator, className, checkoutSelector = 'customContent') {
    const checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    if (!checkoutContent) return;

    const element = checkoutContent.querySelector(CheckoutElements[elementName]);

    if (element) {
      if (operator === 'add') {
        element.classList.add(`${className}`);
      } else {
        element.classList.remove(`${className}`);
      }
    }
  },

  getInputHelperElement(elementName, helperType, checkoutSelector = 'customContent') {
    const checkoutContent = document.querySelector(CheckoutElements[checkoutSelector]);
    if (!checkoutContent) return;

    return checkoutContent.querySelector(`input-helper[input-id=${elementName}-${helperType}]`);
  },

  setDisplayOfInputHelperInfo(elementName, operator, checkoutSelector = 'customContent') {
    let divInputHelper = this.getInputHelperElement(elementName, 'helper-info', checkoutSelector);

    if(divInputHelper) {
      divInputHelper.childNodes[1].style.display = operator;
    }
  },

  setDisplayOfInputHelper(elementName, operator, checkoutSelector = 'customContent') {
    let divInputHelper = this.getInputHelperElement(elementName, 'helper', checkoutSelector);

    if (divInputHelper) {
      let inputHelper = divInputHelper.querySelector('div');
      inputHelper.style.display = operator;
    }
  },

  setDisplayOfInputHelperMessage(elementName, message, checkoutSelector = 'customContent') {
    let divInputHelper = this.getInputHelperElement(elementName, 'helper', checkoutSelector);

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
      currency: wc_mercadopago_custom_checkout_params.currency_code,
      style: 'currency',
      currencyDisplay: 'narrowSymbol',
    });

    let formattedValue = formatter.format(value);

    if (this.getCountry() === 'MLM') {
      formattedValue = formattedValue.replace(/^(\D+)/, '$1 ');
    }

    return formattedValue;
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
    this.setElementDisplay('mpInstallmentsCard', 'none');
    this.setElementDisplay('mpIssuerContainer', 'none');
    this.setValueOn('cardInstallments', '');
  },

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

  clearInstallmentsComponent() {
    const selectorInstallments = document.querySelector(CheckoutElements.mpInstallmentsContainer);

    selectorInstallments?.classList?.remove(CheckoutElements.mpInstallmentsContainer);

    if (selectorInstallments?.firstElementChild) {
      selectorInstallments?.removeChild(selectorInstallments?.firstElementChild);
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
    const customContent = document.querySelector('.mp-checkout-custom-container');
    if (!customContent) return;

    const inputHelpers = customContent.querySelectorAll('input-helper');
    if (!inputHelpers) return;

    inputHelpers.forEach((inputHelper) => {
      inputHelper.querySelector('div').style.display = 'none';
    });
  },

  clearInputs() {
    this.hideErrors();
    this.setBackground('fcCardNumberContainer', 'no-repeat #fff');

    const cardNumberContainer = document.querySelector(CheckoutElements.fcCardNumberContainer);

    if (cardNumberContainer) {
      cardNumberContainer.classList.remove('mp-card-icon-detected');
      this.setValueOn('fcCardNumberContainer', '');
      this.setDisplayOfError('fcCardNumberContainer', 'removed', 'mp-error');
    }

    this.setValueOn('fcCardholderName', '');
    this.setDisplayOfError('fcCardholderName', 'removed', 'mp-error');

    this.setValueOn('fcCardExpirationDateContainer', '');
    this.setDisplayOfError('fcCardExpirationDateContainer', 'removed', 'mp-error');

    this.setValueOn('fcSecurityNumberContainer', '');
    this.setDisplayOfError('fcSecurityNumberContainer', 'removed', 'mp-error');

    this.setValueOn('fcIdentificationNumber', '');
    this.setElementDisplay('mpDocumentContainer', 'none');

    this.setDisplayOfError('fcIdentificationNumberContainer', 'removed', 'mp-error');

    this.setDisplayOfError('mpCardholderNameInputLabel', 'removed', 'mp-label-error');

    this.setDisplayOfInputHelper('mp-card-holder-name', 'none');

    this.setDisplayOfError('mpCardholderNameInputLabel', 'removed', 'mp-label-error-2x');

    this.clearInstallmentsComponent();
    this.setElementDisplay('mpInstallmentsCard', 'none');

    const inputDocument = document.querySelector('input[data-cy=input-document]');
    if (inputDocument) {
      inputDocument.value = '';
    }
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

  verifyCardholderName() {
    const cardholderNameInput = document.querySelector(CheckoutElements.fcCardholderName);

    if (!cardholderNameInput) {
      return true;
    }

    const value = cardholderNameInput.value.trim();
    const regex = /[^a-zA-ZÀ-ÿ0-9 ]/;
    const isValid = value.length > 2 && !regex.test(value);

    this.updateCardholderNameState(cardholderNameInput, isValid);

    return isValid;
  },

  updateCardholderNameState(input, isValid) {
    const cardholderNameHelper = document.querySelector(CheckoutElements.mpCardHolderNameHelper);
    const cardholderNameLabel = document.querySelector(CheckoutElements.mpCardholderNameInputLabel);
    const cardholderNameHelperInfo = document.querySelector(CheckoutElements.mpCardHolderNameHelperInfo);


    if (isValid) {
      input.classList.remove('mp-error', 'mp-error-2px');
      cardholderNameLabel?.classList.remove('mp-label-error');
      if (cardholderNameHelper) {
        cardholderNameHelper.style.display = 'none';
      }
      if (document.activeElement === input) {
        input.classList.add('mp-focus');
        cardholderNameHelperInfo.style.display = 'flex';
      }
    } else {
      input.classList.remove('mp-focus');
      if (cardholderNameHelper) {
        cardholderNameHelper.style.display = 'flex';
        cardholderNameHelperInfo.style.display = 'none';
      }
      if (cardholderNameLabel) {
        cardholderNameLabel.classList.add('mp-label-error');
        cardholderNameHelperInfo.style.display = 'none';
      }
      if (document.activeElement === input) {
        input.classList.remove('mp-error');
        input.classList.add('mp-error-2px');
      } else {
        input.classList.add('mp-error');
      }
    }
  },

  verifyCardholderNameOnFocus() {
    const cardholderNameInput = document.querySelector(CheckoutElements.fcCardholderName);
    const cardholderNameHelper = document.querySelector(CheckoutElements.mpCardHolderNameHelper);
    const cardholderNameInputLabel = document.querySelector(CheckoutElements.mpCardholderNameInputLabel);
    const cardholderNameHelperInfo = document.querySelector(CheckoutElements.mpCardHolderNameHelperInfo);

    if (!cardholderNameInput) {
      return;
    }

    cardholderNameInput.addEventListener('focus', () => {
      if (!cardholderNameInput.classList.contains('mp-error') && !cardholderNameInput.classList.contains('mp-error-2px')) {
        cardholderNameInput.classList.add('mp-focus');
        cardholderNameHelperInfo.style.display = 'flex';
      } else if (cardholderNameInput.classList.contains('mp-error')) {
        cardholderNameInput.classList.remove('mp-error');
        cardholderNameInput.classList.add('mp-error-2px');
        cardholderNameHelperInfo.style.display = 'none';
      }
    });

    cardholderNameInput.addEventListener('blur', () => {
      cardholderNameInput.classList.remove('mp-focus');
      cardholderNameInput.classList.remove('mp-error-2px');

      if (cardholderNameHelper) {
        cardholderNameHelper.style.display = 'none';
      }

      cardholderNameInputLabel?.classList.remove('mp-label-error');
      cardholderNameHelperInfo.style.display = 'flex';

      if (cardholderNameInput.value.trim() !== '') {
        this.verifyCardholderName();
      }
    });

    cardholderNameInput.addEventListener('input', () => {
      if (cardholderNameInput.value.trim() !== '') {
        this.verifyCardholderName();
      } else {
        this.updateCardholderNameState(cardholderNameInput, true);
      }
    });
  },

  addDefaultOptionOnInstallmentsSelect(installmentsSelect) {
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.disabled = true;
    defaultOption.selected = true;
    defaultOption.innerHTML = this.INSTALLMENTS_SELECT_PLACHOLDER_TEXT;

    installmentsSelect.insertBefore(defaultOption, installmentsSelect.firstChild);
  },

  setChangeEventOnInstallments(installmentsData) {
    this.clearInstallmentsComponent();
    const installmentsSelect = document.getElementById('form-checkout__installments');

    if (!installmentsSelect) return;

    this.installmentsItemsData = installmentsData || [];

    this.addDefaultOptionOnInstallmentsSelect(installmentsSelect);

    if (!this.installmentsEnabled) {
      CheckoutPage.setValueOn('cardInstallments', '1');
      installmentsSelect.value = '1';
      return;
    }

    installmentsSelect.addEventListener('change', (event) => {
      const selectedValue = event.target.value;
      if (selectedValue) {
        CheckoutPage.setValueOn('cardInstallments', selectedValue);
        installmentsSelect.value = selectedValue;

        this.updateTaxInfoForSelect(selectedValue, 'mp-installments-tax-info');
        this.setInstallmentsErrorState(false);
      }
    });

    installmentsSelect.addEventListener('blur', () => {
      if (this.installmentsWasSelected()) {
        this.setInstallmentsErrorState(false);
      } else {
        this.setInstallmentsErrorState(true);
      }
    });

    this.setElementDisplay('mpInstallmentsCard', 'block');
  },

  installmentsWasSelected() {
    const installmentsSelect = document.getElementById('form-checkout__installments');

    return !!installmentsSelect?.value;
  },

  scrollToCheckoutCustomContainer() {
    const checkoutCustomContainer = document.querySelector('.mp-checkout-custom-container');
    if (!checkoutCustomContainer) return;

    checkoutCustomContainer.scrollIntoView({ behavior: 'smooth' });
  },

  setInstallmentsErrorState(hasError) {
    const installmentsSelect = document.getElementById('form-checkout__installments');
    const installmentsLabel = document.querySelector('.mp-checkout-custom-installments-select-container .mp-input-label');
    const installmentsErrorHelper = document.querySelector('#mp-installments-error');

    if (!installmentsSelect || !installmentsLabel || !installmentsErrorHelper) return;

    if (hasError) {
    installmentsErrorHelper.style.display = 'flex';
      installmentsSelect.classList.add('mp-error');
      installmentsLabel.classList.add('mp-label-error');
    } else {
      installmentsErrorHelper.style.display = 'none';
      installmentsSelect.classList.remove('mp-error');
      installmentsLabel.classList.remove('mp-label-error');
    }
  },

  updateTaxInfoForSelect(selectedValue, containerId, payerCosts) {
    const currentPayerCosts = payerCosts || this.installmentsItemsData?.payer_costs;
    const taxInfoContainer = document.getElementById(containerId);

    if (!taxInfoContainer) return;

    const shouldHide =
      this.getCountry() !== 'MLA' ||
      parseInt(selectedValue, 10) < 2;
    if (shouldHide) {
      taxInfoContainer.style.display = 'none';
      return;
    }

    const selectedItem = currentPayerCosts?.find(
      item => `${item.installments}` == `${selectedValue}`
    );
    const taxInfo = this.parseTaxInfo(selectedItem?.labels);
    const taxes = this.formatTaxDisplay(taxInfo);
    taxInfoContainer.style.display = taxes ? 'block' : 'none';
    if (taxes) taxInfoContainer.innerHTML = taxes;
  },

  parseTaxInfo(labels) {
    if (!Array.isArray(labels)) return {};

    const cleanNumber = (value) => {
      const cleaned = value?.replace('%', '').trim();
      return /^\d+([,.]\d+)?$/.test(cleaned) ? cleaned : null;
    };

    return labels
      .filter(label => typeof label === 'string')
      .flatMap(label => label.split('|'))
      .reduce((acc, part) => {
        for (const [type, { key }] of Object.entries(this.TAX_TYPES)) {
          if (part.includes(key)) {
            const value = cleanNumber(part.split(key)[1]);
            if (value) acc[type] = value;
          }
        }
        return acc;
      }, {});
  },

  formatTaxDisplay(taxInfo) {
    if (!taxInfo) return null;

    const taxes = Object.entries(this.TAX_TYPES)
      .filter(([type]) => taxInfo[type])
      .map(([type, { label, bold }]) => {
        const text = `${label}: ${taxInfo[type]}%`;
        return bold ? `<b>${text}</b>` : text;
      });

    return taxes.length ? `${taxes.join(' - ')}. Tasa fija.` : null;
  },
};

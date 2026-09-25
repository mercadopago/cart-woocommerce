// eslint-disable-next-line no-unused-vars
class MPSuperTokenTriggerFieldsStrategy {
  CLICKABLE_AREA_CLASS_NAME = 'mp-checkout-clickable-area';
  CHECKOUT_CUSTOM_FIELDS_SELECTORS = [
    '#form-checkout__cardNumber-container',
    '#form-checkout__cardholderName',
    '#form-checkout__expirationDate-container',
    '#form-checkout__securityCode-container',
  ];
  FIELD_SELECTOR_TO_CHECKOUT_FIELD_MAP = {
    '#form-checkout__cardNumber-container': {sdkFieldName: 'cardNumber', isSdkField: true, selector: '#form-checkout__cardNumber-container'},
    '#form-checkout__cardholderName': {sdkFieldName: null, isSdkField: false, selector: '#form-checkout__cardholderName'},
    '#form-checkout__expirationDate-container': {sdkFieldName: 'expirationDate', isSdkField: true, selector: '#form-checkout__expirationDate-container'},
    '#form-checkout__securityCode-container': {sdkFieldName: 'securityCode', isSdkField: true, selector: '#form-checkout__securityCode-container'},
  };

  // Attributes
  clickableAreaClickListenerReferences = {
    '#form-checkout__cardNumber-container': null,
    '#form-checkout__cardholderName': null,
    '#form-checkout__expirationDate-container': null,
    '#form-checkout__securityCode-container': null,
  };
  buyerEmail = null;
  amount = null;

  // Dependencies
  mpSuperTokenAuthenticator = null;
  mpSuperTokenPaymentMethods = null;

  constructor(mpSuperTokenAuthenticator, mpSuperTokenPaymentMethods) {
    this.mpSuperTokenAuthenticator = mpSuperTokenAuthenticator;
    this.mpSuperTokenPaymentMethods = mpSuperTokenPaymentMethods;
  }

  createClickableAreaListeners(amount, buyerEmail) {
    this.buyerEmail = buyerEmail;
    this.amount = amount;

    if (!buyerEmail || !amount) return;

    this.CHECKOUT_CUSTOM_FIELDS_SELECTORS.forEach(fieldSelector => {
      const fieldElement = document.querySelector(fieldSelector);
      if (!fieldElement || fieldElement.classList.contains(this.CLICKABLE_AREA_CLASS_NAME)) {
        return;
      }

      fieldElement.style.pointerEvents = 'none';
      fieldElement.classList.add(this.CLICKABLE_AREA_CLASS_NAME);
      this.clickableAreaClickListenerReferences[fieldSelector] = fieldElement.parentElement
        .addEventListener('click', () => this.onClickOnClickableArea.call(this, fieldSelector), { once: true });
    });
  }

  removeAllClickableAreaListeners() {
    this.CHECKOUT_CUSTOM_FIELDS_SELECTORS.forEach(fieldSelector => {
      const fieldElement = document.querySelector(fieldSelector);
      if (!fieldElement || !fieldElement.classList.contains(this.CLICKABLE_AREA_CLASS_NAME)) {
        return;
      }

      fieldElement.style.pointerEvents = 'auto';
      fieldElement.classList.remove(this.CLICKABLE_AREA_CLASS_NAME);
      this.clickableAreaClickListenerReferences[fieldSelector]?.removeEventListener('click', () => this.onClickOnClickableArea.call(this), { once: true });
    });
  }

  focusOnField(fieldSelector) {
    if (!fieldSelector) {
      return;
    }

    const fieldMap = this.FIELD_SELECTOR_TO_CHECKOUT_FIELD_MAP[fieldSelector];
    if (!fieldMap) {
      return;
    }

    if (fieldMap.isSdkField) {
      window.mpCustomCheckoutHandler?.cardForm?.fields?.[fieldMap.sdkFieldName]?.focus();
    } else {
      document.querySelector(fieldMap.selector)?.focus();
    }
  }

  async onClickOnClickableArea(fieldSelector) {
    try {
      if (this.mpSuperTokenAuthenticator.isUserClosedModal() || this.mpSuperTokenPaymentMethods.hasStoredPaymentMethods()) {
        this.removeAllClickableAreaListeners();
        return;
      }

      if (!this.buyerEmail || !this.amount || !fieldSelector) {
        return;
      }

      await this.mpSuperTokenAuthenticator.authenticate(this.amount, this.buyerEmail);
    } finally {
      this.removeAllClickableAreaListeners();

      this.focusOnField(fieldSelector);
    }
  }
}

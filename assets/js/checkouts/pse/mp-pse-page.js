/* globals CheckoutPseElements */

const CheckoutPsePage = {
  setDisplayOfError(elementName, operator, className, checkoutSelector = 'pseContent') {
    let checkoutContent = document.querySelector(CheckoutPseElements[checkoutSelector]);
    let element = checkoutContent.querySelector(CheckoutPseElements[elementName]);

    if (element) {
      if (operator === 'add') {
        element.classList.add(`${className}`);
      } else {
        element.classList.remove(`${className}`);
      }
    }
  },

  setDisplayOfInputHelper(elementName, operator, checkoutSelector = 'pseContent') {
    let checkoutContent = document.querySelector(CheckoutPseElements[checkoutSelector]);
    let divInputHelper = checkoutContent.querySelector(`input-helper[input-id=${elementName}-helper]`);

    if (divInputHelper) {
      let inputHelper = divInputHelper.querySelector('div');
      inputHelper.style.display = operator;
    }
  },
};

/* globals CheckoutTicketElements */

const CheckoutTicketPage = {
  setDisplayOfError(elementName, operator, className, checkoutSelector = 'ticketContent') {
    let checkoutContent = document.querySelector(CheckoutTicketElements[checkoutSelector]);
    let element = checkoutContent.querySelector(CheckoutTicketElements[elementName]);

    if (element) {
      if (operator === 'add') {
        element.classList.add(`${className}`);
      } else {
        element.classList.remove(`${className}`);
      }
    }
  },

  setDisplayOfInputHelper(elementName, operator, checkoutSelector = 'ticketContent') {
    let checkoutContent = document.querySelector(CheckoutTicketElements[checkoutSelector]);
    let divInputHelper = checkoutContent.querySelector(`input-helper[input-id=${elementName}-helper]`);

    if (divInputHelper) {
      let inputHelper = divInputHelper.querySelector('div');
      inputHelper.style.display = operator;
    }
  },
};

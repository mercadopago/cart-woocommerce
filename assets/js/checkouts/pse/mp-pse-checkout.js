/* jshint es3: false */
/* globals wc_mercadopago_pse_checkout_params, CheckoutPseElements */
(function ($) {
  'use strict';

  $(function () {

    // Handler form submit
    function mercadoPagoFormHandlerPse() {
      if (!document.getElementById('payment_method_woo-mercado-pago-pse').checked) {
        return true;
      }

      let pseContent = document.querySelector(CheckoutPseElements.pseContent);

      verifyDocument(pseContent);
      verifyFinancial(pseContent);

      if (checkForErrors(pseContent.querySelectorAll('input-helper'))) {
       return  false;
      }

      return true;
    }

    function verifyDocument(pseContent) {
      let documentElement = pseContent.querySelector('.mp-document');

      if (documentElement.value === '') {
        pseContent.querySelector('.mp-input').classList.add('mp-error');
        let pseHelpers = pseContent.querySelector('.mp-input-document').querySelector('input-helper');
        let child = pseHelpers.querySelector('div');
        child.style.display = 'flex';
      }

    }

    function verifyFinancial(pseContent) {
      let documentElement = pseContent.querySelector('#mercadopago_pse\\[bank\\]');
      let pseHelpers =  pseContent.querySelector('.mp-checkout-pse-bank').querySelector('input-helper');
      if (documentElement.value === '' || wc_mercadopago_pse_checkout_params.financial_placeholder === documentElement.value ) {
        documentElement.parentElement.classList.add('mp-error');
        let child = pseHelpers.querySelector('div');
        child.style.display = 'flex';
      }

        documentElement.addEventListener('change', () => {
        documentElement.parentElement.classList.remove('mp-error');
        pseHelpers.querySelector('div').style.display = 'none';
      });
    }

    function checkForErrors(pseHelpers) {
      let hasError = false;

      pseHelpers.forEach((item) => {
        let inputHelper = item.querySelector('div');
        if (inputHelper.style.display !== 'none') {
          hasError = true;
        }
      });

      return hasError;
    }

    // Process when submit the checkout form
    $('form.checkout').on('checkout_place_order_woo-mercado-pago-pse', function () {
      return mercadoPagoFormHandlerPse();

    });

    // If payment fail, retry on next checkout page
    $('form#order_review').submit(function () {
      return mercadoPagoFormHandlerPse();
    });

  });
})(jQuery);

/* jshint es3: false */
/* globals wc_mercadopago_ticket_checkout_params, CheckoutTicketElements, CheckoutTicketPage */
(function ($) {
  'use strict';

  $(function () {
    var mercado_pago_submit_ticket = false;

    // Handler form submit
    function mercadoPagoFormHandlerTicket() {
      if (!document.getElementById('payment_method_woo-mercado-pago-ticket').checked) {
        return true;
      }

      let ticketContent = document.querySelector(CheckoutTicketElements.ticketContent);
      let ticketHelpers = ticketContent.querySelectorAll('input-helper');

      if (wc_mercadopago_ticket_checkout_params.site_id === 'MLB' || wc_mercadopago_ticket_checkout_params.site_id === 'MLU') {
        verifyDocument(ticketContent, ticketHelpers);
      }

      verifyPaymentMethods(ticketContent);

      if (checkForErrors(ticketHelpers)) {
        removeBlockOverlay();
      } else {
        mercado_pago_submit_ticket = true;
      }

      return mercado_pago_submit_ticket;
    }

    function checkForErrors(ticketHelpers) {
      let hasError = false;

      ticketHelpers.forEach((item) => {
        let inputHelper = item.querySelector('div');
        if (inputHelper.style.display !== 'none') {
          hasError = true;
        }
      });

      return hasError;
    }

    function verifyDocument(ticketContent, ticketHelpers) {
      let documentElement = ticketContent.querySelector('.mp-document');

      if (documentElement.value === '') {
        ticketContent.querySelector('.mp-input').classList.add('mp-error');
        let child = ticketHelpers[0].querySelector('div');
        child.style.display = 'flex';
      }
    }

    function verifyPaymentMethods(ticketContent) {
      ticketContent.querySelector('#more-options').addEventListener('click', () => {
        setTimeout(() => {
          removeErrorFromInputTableContainer(ticketContent);
        }, 300);
      });

      let paymentOptionSelected = false;
      ticketContent.querySelectorAll('.mp-input-radio-radio').forEach((item) => {
        if (item.checked) {
          paymentOptionSelected = true;
        }
      });

      if (paymentOptionSelected === false) {
        CheckoutTicketPage.setDisplayOfError('fcInputTableContainer', 'add', 'mp-error', 'ticketContent');
        CheckoutTicketPage.setDisplayOfInputHelper('mp-payment-method', 'flex', 'ticketContent');
      }

      removeErrorFromInputTableContainer(ticketContent);
    }

    function removeErrorFromInputTableContainer(ticketContent) {
      ticketContent.querySelectorAll('.mp-input-table-label').forEach((item) => {
        item.addEventListener('click', () => {
          CheckoutTicketPage.setDisplayOfError('fcInputTableContainer', 'remove', 'mp-error', 'ticketContent');
          CheckoutTicketPage.setDisplayOfInputHelper('mp-payment-method', 'none', 'ticketContent');
        });
      });
    }

    // Process when submit the checkout form
    $('form.checkout').on('checkout_place_order_woo-mercado-pago-ticket', function () {
      return mercadoPagoFormHandlerTicket();
    });

    // If payment fail, retry on next checkout page
    $('form#order_review').submit(function () {
      return mercadoPagoFormHandlerTicket();
    });

    // Remove Block Overlay from Order Review page
    function removeBlockOverlay() {
      if ($('form#order_review').length > 0) {
        $('.blockOverlay').css('display', 'none');
      }
    }
  });
})(jQuery);

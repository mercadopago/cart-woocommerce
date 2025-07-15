/* globals wc_mercadopago_custom_checkout_params, MercadoPago, CheckoutPage, MP_DEVICE_SESSION_ID */

class MPCustomCheckoutHandler {
  constructor(cardForm, threeDSHandler, eventHandler) {
    this.cardForm = cardForm;
    this.threeDSHandler = threeDSHandler;
    this.eventHandler = eventHandler;

    this.init();
  }

  init() {
    const mpCheckoutForm = document.querySelector('form[name=checkout]');
    if (mpCheckoutForm) {
      mpCheckoutForm.id = this.eventHandler.mpFormId;
    } else {
      this.eventHandler.mpFormId = 'order_review';
    }

    if (!this.eventHandler.triggeredPaymentMethodSelectedEvent) {
      jQuery('body').trigger('payment_method_selected');
    }

    this.eventHandler.bindEvents();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    const cardForm = new MPCardForm();
    const threeDSHandler = new MPThreeDSHandler();
    const eventHandler = new MPEventHandler(cardForm, threeDSHandler);
  
    const mpCustomCheckoutHandler = new MPCustomCheckoutHandler(cardForm, threeDSHandler, eventHandler);
    window.mpCustomCheckoutHandler = mpCustomCheckoutHandler;
    window.mpEventHandler = eventHandler;
  }, 1000);
});

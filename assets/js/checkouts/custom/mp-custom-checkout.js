/* globals wc_mercadopago_custom_checkout_params, MercadoPago, CheckoutPage, MP_DEVICE_SESSION_ID */

class MPCustomCheckoutHandler {
  static FORM_SELECTORS = {
    CLASSIC: 'form[name=checkout]',
    BLOCKS: '.wc-block-components-form.wc-block-checkout__form'
  };

  static FORM_IDS = {
    CLASSIC_CHECKOUT: 'checkout',
    BLOCKS_CHECKOUT: 'blocks_checkout_form',
    PAY_FOR_ORDER: 'order_review'
  };

  constructor(cardForm, threeDSHandler, eventHandler) {
    this.cardForm = cardForm;
    this.threeDSHandler = threeDSHandler;
    this.eventHandler = eventHandler;

    this.init();
  }

  init() {
    this.setupFormConfiguration();

    if (!this.eventHandler.triggeredPaymentMethodSelectedEvent) {
      jQuery('body').trigger('payment_method_selected');
    }

    this.eventHandler.bindEvents();
  }

  setupFormConfiguration() {
    const formConfig = this.getFormConfig();
    
    if (formConfig.element) {
      formConfig.element.id = formConfig.formId;
    }

    this.syncFormIds(formConfig.formId);
  }

  getFormConfig() {
    const classicForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.CLASSIC);
    const blocksForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.BLOCKS);

    if (classicForm) {
      return {
        element: classicForm,
        formId: MPCustomCheckoutHandler.FORM_IDS.CLASSIC_CHECKOUT,
      };
    }

    if (blocksForm) {
      return {
        element: blocksForm,
        formId: MPCustomCheckoutHandler.FORM_IDS.BLOCKS_CHECKOUT,
      };
    }

    return {
      element: null,
      formId: MPCustomCheckoutHandler.FORM_IDS.PAY_FOR_ORDER,
    };
  }

  syncFormIds(formId) {
    this.eventHandler.mpFormId = formId;
    this.cardForm.mpFormId = formId;
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

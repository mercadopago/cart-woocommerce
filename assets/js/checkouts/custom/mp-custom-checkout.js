/* globals wc_mercadopago_custom_checkout_params, MercadoPago, CheckoutPage, MP_DEVICE_SESSION_ID */

class MPCustomCheckoutHandler {
  static FORM_SELECTORS = {
    CLASSIC: 'form[name=checkout]',
    BLOCKS: '.wc-block-components-form.wc-block-checkout__form',
    ORDER_REVIEW: 'form#order_review',
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

  async init() {
    await this.setupFormConfiguration();

    if (!this.eventHandler.triggeredPaymentMethodSelectedEvent) {
      jQuery('body').trigger('payment_method_selected');
    }

    this.eventHandler.bindEvents();
  }

  async setupFormConfiguration() {
    try {
      const formConfig = await this.getFormConfig();

      if (formConfig.element) {
        formConfig.element.id = formConfig.formId;
      }

      this.syncFormIds(formConfig.formId);
    } catch (error) {
      console.error('Failed to configure checkout form:', error);
    }
  }

  getFormConfig() {
    return new Promise((resolve, reject) => {
      const maxTries = 10;
      const intervalMs = 500;
      let tries = 0;

      const tryFindForm = () => {
        tries++;

        const classicForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.CLASSIC);
        const blocksForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.BLOCKS);
        const orderReviewForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.ORDER_REVIEW);

        if (classicForm) {
          resolve({
            element: classicForm,
            formId: MPCustomCheckoutHandler.FORM_IDS.CLASSIC_CHECKOUT,
          });
          return;
        }

        if (blocksForm) {
          resolve({
            element: blocksForm,
            formId: MPCustomCheckoutHandler.FORM_IDS.BLOCKS_CHECKOUT,
          });
          return;
        }

        if (orderReviewForm) {
          resolve({
            element: orderReviewForm,
            formId: MPCustomCheckoutHandler.FORM_IDS.PAY_FOR_ORDER,
          });
          return;
        }

        if (tries >= maxTries) {
          reject(new Error(`No checkout form found after ${maxTries} attempts`));
          return;
        }

        setTimeout(tryFindForm, intervalMs);
      };

      tryFindForm();
    });
  }

  syncFormIds(formId) {
    this.eventHandler.mpFormId = formId;
    this.cardForm.mpFormId = formId;
  }
}

document.addEventListener('DOMContentLoaded', function () {
    const cardForm = new MPCardForm();
    const threeDSHandler = new MPThreeDSHandler();
    const eventHandler = new MPEventHandler(cardForm, threeDSHandler);

    const mpCustomCheckoutHandler = new MPCustomCheckoutHandler(cardForm, threeDSHandler, eventHandler);
    window.mpCustomCheckoutHandler = mpCustomCheckoutHandler;
    window.mpEventHandler = eventHandler;
});

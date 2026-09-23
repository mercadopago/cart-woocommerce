/* globals jQuery, MercadoPago, MPCardForm, MPThreeDSHandler, MPEventHandler, CheckoutPage, CheckoutElements */

function isCustomCheckoutPage() {
  const bodyClasses = document.body?.classList;

  return bodyClasses?.contains('woocommerce-checkout') ||
    bodyClasses?.contains('woocommerce-order-pay') ||
    bodyClasses?.contains('woocommerce-add-payment-method');
}

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

  static FORM_DISCOVERY_TIMEOUT_MS = 30000;

  static FORM_DISCOVERY_RETRY_INTERVAL_MS = 500;

  static hasDependencies() {
    return typeof MPCardForm === 'function' &&
      typeof MPThreeDSHandler === 'function' &&
      typeof MPEventHandler === 'function' &&
      typeof CheckoutPage === 'object' &&
      typeof CheckoutElements === 'object' &&
      this.hasSdkDependency();
  }

  static hasSdkDependency() {
    return typeof window.mpSdkInstance?.cardForm === 'function' || typeof MercadoPago === 'function';
  }

  static waitForDependencies() {
    if (this.hasDependencies()) {
      return Promise.resolve(true);
    }

    return new Promise((resolve) => {
      let elapsedTime = 0;

      const waitForDependencies = () => {
        if (this.hasDependencies()) {
          resolve(true);
          return;
        }

        elapsedTime += this.FORM_DISCOVERY_RETRY_INTERVAL_MS;

        if (elapsedTime >= this.FORM_DISCOVERY_TIMEOUT_MS) {
          resolve(false);
          return;
        }

        setTimeout(waitForDependencies, this.FORM_DISCOVERY_RETRY_INTERVAL_MS);
      };

      waitForDependencies();
    });
  }

  static reportInitializationFailure(reason) {
    if (typeof window.sendMetric !== 'function') {
      return;
    }

    window.sendMetric(reason, 'custom_checkout_initialization', 'mp_custom_checkout_initialization_error');
  }

  constructor(cardForm, threeDSHandler, eventHandler) {
    this.cardForm = cardForm;
    this.threeDSHandler = threeDSHandler;
    this.eventHandler = eventHandler;

    this.init();
  }

  async init() {
    if (!this.isCheckoutPage()) {
      return;
    }

    const formConfigured = await this.setupFormConfiguration();

    if (!formConfigured) {
      return;
    }

    const jQueryAvailable = await this.waitForJQuery();

    if (!jQueryAvailable) {
      MPCustomCheckoutHandler.reportInitializationFailure('jquery_timeout');
      console.error('Mercado Pago checkout dependencies did not become available in time.');
      return;
    }

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
      return true;
    } catch (error) {
      MPCustomCheckoutHandler.reportInitializationFailure('form_discovery_timeout');
      console.error('Mercado Pago checkout form was not rendered in time.');
      return false;
    }
  }

  getFormConfig() {
    const formConfig = this.findFormConfig();

    if (formConfig) {
      return Promise.resolve(formConfig);
    }

    return new Promise((resolve, reject) => {
      let observer;
      let retryTimeout;
      let timeout;

      const cleanup = () => {
        if (observer) {
          observer.disconnect();
        }

        clearTimeout(retryTimeout);
        clearTimeout(timeout);
      };

      const resolveWhenFormIsAvailable = () => {
        const availableFormConfig = this.findFormConfig();

        if (!availableFormConfig) {
          return false;
        }

        cleanup();
        resolve(availableFormConfig);
        return true;
      };

      const retryWhenMutationObserverIsUnavailable = () => {
        if (!resolveWhenFormIsAvailable()) {
          retryTimeout = setTimeout(
            retryWhenMutationObserverIsUnavailable,
            MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS
          );
        }
      };

      timeout = setTimeout(() => {
        cleanup();
        reject(new Error('Checkout form discovery timed out.'));
      }, MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS);

      if (typeof MutationObserver !== 'undefined') {
        observer = new MutationObserver(resolveWhenFormIsAvailable);
        observer.observe(document.documentElement, { childList: true, subtree: true });
        resolveWhenFormIsAvailable();
        return;
      }

      retryWhenMutationObserverIsUnavailable();
    });
  }

  isCheckoutPage() {
    return isCustomCheckoutPage();
  }

  findFormConfig() {
    const classicForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.CLASSIC);
    const blocksForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.BLOCKS);
    const orderReviewForm = document.querySelector(MPCustomCheckoutHandler.FORM_SELECTORS.ORDER_REVIEW);

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

    if (orderReviewForm) {
      return {
        element: orderReviewForm,
        formId: MPCustomCheckoutHandler.FORM_IDS.PAY_FOR_ORDER,
      };
    }

    return null;
  }

  waitForJQuery() {
    if (typeof jQuery === 'function') {
      return Promise.resolve(true);
    }

    return new Promise((resolve) => {
      let elapsedTime = 0;

      const waitForJQuery = () => {
        if (typeof jQuery === 'function') {
          resolve(true);
          return;
        }

        elapsedTime += MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS;

        if (elapsedTime >= MPCustomCheckoutHandler.FORM_DISCOVERY_TIMEOUT_MS) {
          resolve(false);
          return;
        }

        setTimeout(waitForJQuery, MPCustomCheckoutHandler.FORM_DISCOVERY_RETRY_INTERVAL_MS);
      };

      waitForJQuery();
    });
  }

  syncFormIds(formId) {
    this.eventHandler.mpFormId = formId;
    this.cardForm.mpFormId = formId;
  }
}

async function initializeCustomCheckout() {
  if (!isCustomCheckoutPage() || window.mpCustomCheckoutHandler || window.mpCustomCheckoutInitializationInProgress) {
    return;
  }

  window.mpCustomCheckoutInitializationInProgress = true;

  try {
    const dependenciesAvailable = await MPCustomCheckoutHandler.waitForDependencies();

    if (!dependenciesAvailable) {
      MPCustomCheckoutHandler.reportInitializationFailure('dependencies_timeout');
      console.error('Mercado Pago checkout dependencies did not become available in time.');
      return;
    }

    const cardForm = new MPCardForm();
    const threeDSHandler = new MPThreeDSHandler();
    const eventHandler = new MPEventHandler(cardForm, threeDSHandler);

    const mpCustomCheckoutHandler = new MPCustomCheckoutHandler(cardForm, threeDSHandler, eventHandler);
    window.mpCustomCheckoutHandler = mpCustomCheckoutHandler;
    window.mpEventHandler = eventHandler;
  } finally {
    window.mpCustomCheckoutInitializationInProgress = false;
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeCustomCheckout, { once: true });
} else {
  initializeCustomCheckout();
}

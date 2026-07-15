/* globals sendMetric */
const namespace = 'mercadopago_blocks_update_cart';
const REMOVE_LOAD_SPINNER_DELAY = 500;

let _cartUpdateInProgress = false;
let _pendingCartUpdate = null;

const addDiscountAndCommission = (callback, paymentMethodName) => {
  return callback({
    namespace,
    data: {
      action: 'add',
      gateway: paymentMethodName,
    },
  });
};

const removeDiscountAndCommission = (callback, paymentMethodName) => {
  return callback({
    namespace,
    data: {
      action: 'remove',
      gateway: paymentMethodName,
    },
  });
};

function formatCurrency(value, currency) {
  if (!Number.isInteger(value) || typeof currency !== 'object') {
    throw new Error('Invalid input');
  }
  const formattedValue = (value / Math.pow(10, currency.minorUnit)).toFixed(currency.minorUnit);
  return formattedValue.split('.').join('.');
}

async function waitForHandler(getHandler, metric) {
  const WAIT_HANDLER_INTERVAL = 100;
  const MAX_WAIT_HANDLER = 15000;
  const startedWaitingAt = Date.now();
  let handler = getHandler();
  while (!handler) {
    if (Date.now() - startedWaitingAt >= MAX_WAIT_HANDLER) {
      if (typeof sendMetric === 'function') {
        sendMetric(metric.name, metric.message, metric.errorType);
      }
      return null;
    }
    await new Promise(resolve => setTimeout(resolve, WAIT_HANDLER_INTERVAL));
    handler = getHandler();
  }

  return handler;
}

async function handleCartTotalChange(value, currency, currencyRatio = 1) {
  if (_cartUpdateInProgress) {
    _pendingCartUpdate = { value, currency, currencyRatio };
    return;
  }

  _cartUpdateInProgress = true;

  try {
    window.mpSuperTokenPaymentMethods?.hideSuperTokenError();

    if (
      window.mpCustomCheckoutHandler?.cardForm
      && typeof window.mpCustomCheckoutHandler?.cardForm.createLoadSpinner === 'function'
    ) {
      window.mpCustomCheckoutHandler?.cardForm?.createLoadSpinner();
    }

    const mpCustomCheckoutHandler = await waitForHandler(
      () => window.mpCustomCheckoutHandler,
      {
        name: 'MP_CUSTOM_CHECKOUT_HANDLER_NOT_READY_ON_CART_UPDATE',
        message: 'Custom checkout handler did not load within the expected time on cart total change',
        errorType: 'mp_cart_total_change_error',
      }
    );
    if (!mpCustomCheckoutHandler) return;
    
    const isSubmitting = !!document.querySelector('.wc-block-components-spinner');

    if (mpCustomCheckoutHandler.cardForm.formMounted && !isSubmitting) {
      mpCustomCheckoutHandler.cardForm.form.unmount();
    }

    let updatedAmount = formatCurrency(value, currency);

    const ratio = parseFloat(currencyRatio);
    if (ratio > 0) {
      updatedAmount = String(parseFloat(updatedAmount) * ratio);
    }

    // Sequential on purpose: initCardForm() boots the Mercado Pago JS SDK, which
    // loadSuperToken() currently depends on. They are not parallelizable until that
    // coupling is removed (planned refactor, Q3). Do not switch back to Promise.all.
    await mpCustomCheckoutHandler.cardForm.initCardForm(updatedAmount);

    const superTokenTriggerHandler = await waitForHandler(
      () => window.mpSuperTokenTriggerHandler,
      {
        name: 'MP_SUPER_TOKEN_HANDLER_NOT_READY_ON_CART_UPDATE',
        message: 'Trigger handler did not load within the expected time on cart total change',
        errorType: 'mp_super_token_init_error',
      }
    );
    await superTokenTriggerHandler?.loadSuperToken(updatedAmount);
  } catch (e) {
    if (typeof sendMetric === 'function') {
      sendMetric('error_to_update_cart_total', e?.message || 'Unknown error', "mp_cart_total_change_error");
    }
  } finally {
    _cartUpdateInProgress = false;

    if (_pendingCartUpdate !== null) {
      const next = _pendingCartUpdate;
      _pendingCartUpdate = null;
      handleCartTotalChange(next.value, next.currency, next.currencyRatio);
    } else {
      setTimeout(() => window.mpCustomCheckoutHandler?.cardForm?.removeLoadSpinner(), REMOVE_LOAD_SPINNER_DELAY);
    }
  }
}

export { addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission };

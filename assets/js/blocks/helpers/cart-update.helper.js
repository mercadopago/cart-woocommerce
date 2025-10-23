const namespace = 'mercadopago_blocks_update_cart';

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

async function handleCartTotalChange(value, currency) {
  while (!window.mpCustomCheckoutHandler) {
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  const mpCustomCheckoutHandler = window.mpCustomCheckoutHandler;
  const isSubmitting = !!document.querySelector('.wc-block-components-spinner');

  if (mpCustomCheckoutHandler.cardForm.formMounted && !isSubmitting) {
    mpCustomCheckoutHandler.cardForm.form.unmount();
  }
  const updatedAmount = formatCurrency(value, currency);

  await mpCustomCheckoutHandler.cardForm.initCardForm(updatedAmount);
}

export { addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission };

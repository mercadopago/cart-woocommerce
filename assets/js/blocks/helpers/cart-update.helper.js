const namespace = 'mercadopago_blocks_update_cart';

const addDiscountAndCommission = (callback, paymentMethodName) => {
  callback({
    namespace,
    data: {
      action: 'add',
      gateway: paymentMethodName,
    },
  });
};

const removeDiscountAndCommission = (callback, paymentMethodName) => {
  callback({
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
  const parts = formattedValue.split('.');
  const integerPart = parts[0]
  const decimalPart = parts[1];
  return `${integerPart}.${decimalPart}`;
}

function handleCartTotalChange(value, currency){
  if (cardFormMounted) {
    cardForm.unmount();
  }
  initCardForm(formatCurrency(value, currency));
}

export { addDiscountAndCommission, handleCartTotalChange, removeDiscountAndCommission };


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

export { addDiscountAndCommission, removeDiscountAndCommission };

/* globals wc_mercadopago_pix_pooling_params */
document.addEventListener('DOMContentLoaded', function () {

    let orderId = null;
    let orderKey = null;
    let nonce = null;
    let ajaxUrl = null;

    if (typeof wc_mercadopago_pix_pooling_params !== 'undefined') {
      orderId = wc_mercadopago_pix_pooling_params.order_id;
      orderKey = wc_mercadopago_pix_pooling_params.order_key;
      nonce = wc_mercadopago_pix_pooling_params.nonce;
      ajaxUrl = wc_mercadopago_pix_pooling_params.ajax_url || '/?wc-ajax=mp_pix_payment_status';
    }

    if (!orderId || !orderKey || !nonce || !ajaxUrl) {
      return;
    }

    let pollingInterval;
    let attempts = 0;
    const maxAttempts = 60;
    const pollingDelay = 5000;
    const timeBeforePollingStarts = 20000;

    function checkPaymentStatus() {
        attempts++;

        if (attempts > maxAttempts) {
          clearInterval(pollingInterval);
          return;
        }

        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('order_key', orderKey);
        formData.append('nonce', nonce);

        fetch(ajaxUrl, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(response => {
            if (!response.success) {
                clearInterval(pollingInterval);
                return;
            }
            if (response.data && response.data.status === 'approved') {
                clearInterval(pollingInterval);
                window.location.reload();
            }
        })
        .catch((error) => { console.debug('PIX Polling: network/parse error', error); });
    }

    setTimeout(() => {
      checkPaymentStatus();
      pollingInterval = setInterval(checkPaymentStatus, pollingDelay);
    }, timeBeforePollingStarts);
});

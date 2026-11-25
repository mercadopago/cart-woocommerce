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
    // Every interval tick, including the ones skipped because a request is still in flight.
    let ticks = 0;
    // A request is "in flight" from fetch() until its JSON body is parsed (or it fails).
    // Ticks that arrive meanwhile are skipped and do not consume an attempt.
    let inFlight = false;
    // Terminal state is independent of inFlight: a late response must not restart polling
    // or reload the page a second time.
    let finished = false;
    const maxAttempts = 60;
    // Skipped ticks do not consume attempts (so a slow backend never burns the request budget), but they
    // do count here: the page stops polling after maxTicks * pollingDelay = 10 min whatever the backend does.
    const maxTicks = maxAttempts * 2;
    const pollingDelay = 5000;
    const requestTimeout = 15000;
    const timeBeforePollingStarts = 20000;

    function stopPolling() {
        finished = true;
        clearInterval(pollingInterval);
    }

    function checkPaymentStatus() {
        if (finished) {
          return;
        }

        ticks++;
        if (inFlight) {
          if (ticks >= maxTicks) {
            stopPolling();
          }
          return;
        }

        if (attempts >= maxAttempts || ticks > maxTicks) {
          stopPolling();
          return;
        }

        attempts++;
        inFlight = true;

        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('order_key', orderKey);
        formData.append('nonce', nonce);

        // A request that never answers must not hold inFlight forever (that would stop the polling silently):
        // abort it after requestTimeout so the .finally below releases the next tick.
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timeoutId = controller ? setTimeout(() => controller.abort(), requestTimeout) : null;

        const request = { method: 'POST', body: formData };
        if (controller) {
          request.signal = controller.signal;
        }

        fetch(ajaxUrl, request)
        .then(response => response.json())
        .then(response => {
            if (finished) {
                return;
            }
            if (!response.success) {
                // The backend marks recoverable failures (e.g. order being processed): keep polling.
                if (response.data && response.data.retry === true) {
                    return;
                }
                stopPolling();
                return;
            }
            if (response.data && response.data.status === 'approved') {
                stopPolling();
                window.location.reload();
            }
        })
        .catch((error) => { console.debug('PIX Polling: network/parse error', error); })
        .finally(() => {
            if (timeoutId !== null) {
                clearTimeout(timeoutId);
            }
            inFlight = false;
        });
    }

    setTimeout(() => {
      checkPaymentStatus();
      pollingInterval = setInterval(checkPaymentStatus, pollingDelay);
    }, timeBeforePollingStarts);
});

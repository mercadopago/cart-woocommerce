/* globals jQuery, ajaxurl, mp_payment_status_metabox_vars */
(function () {
  let can_update_order = true;

  window.addEventListener('load', () => {
    const syncPaymentStatusButton = document.getElementById('mp-sync-payment-status-button');

    if (syncPaymentStatusButton) {
      syncPaymentStatusButton.onclick = () => update_order();
    }

    const orderDataElement = document.getElementById('woocommerce-order-data');
    const paymentStatusMetaboxElement = document.getElementById('mp-payment-status-metabox');
    const paymentStatusMetaboxTitle = document.querySelector('#mp-payment-status-metabox > div.postbox-header > h2');

    if (orderDataElement && paymentStatusMetaboxElement) {
      orderDataElement.after(paymentStatusMetaboxElement);
      paymentStatusMetaboxTitle.style.fontFamily = '"Lato", sans-serif';
      paymentStatusMetaboxTitle.style.fontSize = '18px';
    }
  });

  function update_order() {
    canShowLoader(true);

    if (can_update_order) {
      can_update_order = false;

      jQuery
        .post(
          ajaxurl,
          {
            action: 'mp_sync_payment_status',
            nonce: mp_payment_status_metabox_vars.nonce,
            order_id: mp_payment_status_metabox_vars.order_id,
          },
          function () {
          }
        )
        .done(function (response) {
          show_message(response.data, 'success');
        })
        .fail(function (error) {
          show_message(error.responseJSON.data, 'error');
        });
    }
  }

  function show_message(message, type) {
    const div = document.createElement('div');

    const container = document.getElementById('mp-payment-status-container');
    const content = document.getElementById('mp-payment-status-content');

    const isTypeError = type === 'error';

    isTypeError
      ? (div.className = 'mp-alert mp-alert-danger mp-text-center mp-card-body')
      : (div.className = 'mp-alert mp-alert-success mp-text-center mp-card-body');

    div.appendChild(document.createTextNode(message));
    container.insertBefore(div, content);

    if (isTypeError) {
      canShowLoader(false);
      setTimeout(clearMessage, 3000, type);
      can_update_order = true;
      return;
    }

    location.reload();
  }

  function clearMessage() {
      document.querySelector('.mp-alert').remove();
  }

  function canShowLoader(flag) {
    const text = document.querySelector('#mp-sync-payment-status-button span');
    const loader = document.querySelector('.mp-small-loader');

    if (text && loader) {
      if (flag) {
        text.style.display = 'none';
        loader.style.display = 'block';
      } else {
        text.style.display = 'block';
        loader.style.display = 'none';
      }
    }
  }

})();


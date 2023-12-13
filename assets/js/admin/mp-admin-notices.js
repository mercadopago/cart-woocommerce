/* globals ajaxurl, woocommerce_mercadopago_admin_notice_js_params */
jQuery(document).ready(function ($) {
  $(document).on('click', '.mp-rating-notice', function () {
    $.post(ajaxurl, {
      action: 'mp_review_notice_dismiss',
      nonce: woocommerce_mercadopago_admin_notice_js_params.nonce,
    });
  });
});

jQuery(document).ready(function ($) {
  $(document).on('click', '#saved-cards-notice', function () {
    $.post(ajaxurl, {
      action: 'mp_saved_cards_notice_dismiss',
      nonce: woocommerce_mercadopago_admin_notice_js_params.nonce,
    });
  });
});
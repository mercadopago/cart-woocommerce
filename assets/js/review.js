/* globals ajaxurl, woocommerce_mercadopago_admin_notice_review_vars */
jQuery(document).ready(function ($) {
  $(document).on('click', '.mp-rating-notice button', function () {
    $.post(ajaxurl, {
      action: 'mercadopago_review_dismiss',
      nonce: woocommerce_mercadopago_admin_notice_review_vars.nonce,
    });
  });
});

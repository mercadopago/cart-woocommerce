/* globals ajaxurl, woocommerce_mercadopago_admin_saved_cards_vars */
jQuery(document).ready(function ($) {
  $(document).on('click', '#saved-cards-notice', function () {
    $.post(ajaxurl, {
      action: 'mercadopago_saved_cards_notice_dismiss',
      nonce: woocommerce_mercadopago_admin_saved_cards_vars.nonce,
    });
  });
});

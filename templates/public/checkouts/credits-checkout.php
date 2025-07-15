<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $checkout_benefits_title
 * @var string $checkout_benefits_items
 * @var string $checkout_redirect_title
 * @var string $checkout_redirect_description
 * @var string $checkout_redirect_src
 * @var string $checkout_redirect_alt
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 * @var string $amount
 * @var string $message_error_amount
 *
 * @see \MercadoPago\Woocommerce\Gateways\CreditsGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<?php Template::render('public/checkouts/credits-checkout-container', $args) ?>

<script type="text/javascript">
    if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
        jQuery("form.checkout").on("checkout_place_order_woo-mercado-pago-basic", function () {
            window.mpEventHandler.setCardFormLoadInterval();
        });
    }
</script>

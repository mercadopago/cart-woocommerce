<?php

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $checkout_benefits_title
 * @var string $checkout_benefits_items
 * @var string $checkout_redirect_text
 * @var string $checkout_redirect_src
 * @var string $checkout_redirect_alt
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 *
 * @see \MercadoPago\Woocommerce\Gateways\CreditsGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-pro-container">
        <div class="mp-checkout-pro-content">
            <?php if ($test_mode) : ?>
                <div class="mp-checkout-pro-test-mode">
                    <test-mode
                        title="<?= esc_html($test_mode_title) ?>"
                        description="<?= esc_html($test_mode_description) ?>"
                        link-text="<?= esc_html($test_mode_link_text) ?>"
                        link-src="<?= esc_html($test_mode_link_src) ?>"
                    >
                    </test-mode>
                </div>
            <?php endif; ?>

            <checkout-benefits
                title="<?= esc_html($checkout_benefits_title) ?>"
                title-align="left"
                items="<?= esc_html($checkout_benefits_items) ?>"
                list-mode='count'
            >
            </checkout-benefits>

            <div class="mp-checkout-pro-redirect">
                <checkout-redirect-v2
                    text="<?= esc_html($checkout_redirect_text) ?>"
                    src="<?= esc_html($checkout_redirect_src) ?>"
                    alt="<?= esc_html($checkout_redirect_alt) ?>"
                >
                </checkout-redirect-v2>
            </div>
        </div>
    </div>

    <div class="mp-checkout-pro-terms-and-conditions">
        <terms-and-conditions
            description="<?= esc_html($terms_and_conditions_description) ?>"
            link-text="<?= esc_html($terms_and_conditions_link_text) ?>"
            link-src="<?= esc_html($terms_and_conditions_link_src) ?>"
        >
        </terms-and-conditions>
    </div>
</div>

<script type="text/javascript">
    if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
        jQuery("form.checkout").on("checkout_place_order_woo-mercado-pago-basic", function () {
            cardFormLoad();
        });
    }
</script>

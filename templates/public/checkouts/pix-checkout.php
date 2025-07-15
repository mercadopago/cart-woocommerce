<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $pix_template_title
 * @var string $pix_template_subtitle
 * @var string $pix_template_alt
 * @var string $pix_template_src
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 * @var string $amount
 * @var string $message_error_amount
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class='mp-checkout-container'>
    <?php if ($amount === null) : ?>
        <?php Template::render('public/checkouts/alert-message', ['message' => $message_error_amount]) ?>
    <?php else : ?> 
        <div class="mp-checkout-pix-container">
            <?php if ($test_mode) : ?>
                <div class="mp-checkout-pix-test-mode">
                    <test-mode
                        title="<?= esc_html($test_mode_title) ?>"
                        description="<?= esc_html($test_mode_description) ?>">
                    </test-mode>
                </div>
            <?php endif; ?>

            <pix-template
                title="<?= esc_html($pix_template_title) ?>"
                subtitle="<?= esc_html($pix_template_subtitle) ?>"
                alt="<?= esc_html($pix_template_alt) ?>"
                src="<?= esc_html($pix_template_src) ?>">
            </pix-template>

            <div class="mp-checkout-pix-terms-and-conditions">
                <terms-and-conditions
                    description="<?= esc_html($terms_and_conditions_description) ?>"
                    link-text="<?= esc_html($terms_and_conditions_link_text) ?>"
                    link-src="<?= esc_html($terms_and_conditions_link_src) ?>">
                </terms-and-conditions>
            </div>
        </div>
    <?php endif; ?> 
</div>

<script type="text/javascript">
    if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
        jQuery("form.checkout").on("checkout_place_order_woo-mercado-pago-pix", function() {
            window.mpEventHandler.setCardFormLoadInterval();
        });
    }
</script>

<?php

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 * @var string $checkout_notice_icon_one
 * @var string $checkout_notice_icon_two
 * @var string $checkout_notice_message
 * @var string $input_field_label
 * @var string $input_code_icon
 * @var string $yape_title
 * @var string $yape_subtitle
 * @var string $input_code_label
 * @var string $footer_text
 * @var string $yape_tooltip_text
 * @var string $yape_input_code_error_message1
 * @var string $yape_input_code_error_message2
 * @var string $yape_phone_number_error_message1
 * @var string $yape_phone_number_error_message2
 *
 * @see \MercadoPago\Woocommerce\Gateways\YapeGateway
 */

if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="mp-checkout-container">
    <div class="mp-checkout-yape-container">
        <div class="mp-checkout-yape-content">
            <div class="mp-checkout-yape-test-mode">
                <?php if ($test_mode) : ?>
                    <div class="mp-checkout-yape-test-mode">
                        <test-mode
                            title="<?= esc_html($test_mode_title) ?>"
                            description="<?= esc_html($test_mode_description) ?>">
                            link-text="<?= esc_html($terms_and_conditions_link_text) ?>"
                            link-src="<?= esc_html($terms_and_conditions_link_src) ?>">
                        </test-mode>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mp-checkout-yape-title-container">
                <h1 class="mp-checkout-yape-title"><?= esc_html($yape_title) ?></h1>
                <p class="mp-checkout-yape-subtitle"><?= esc_html($yape_subtitle) ?></p>
            </div>
            <div class="mp-checkout-yape-inputs">
                <input-field label-message="<?= esc_html($input_field_label) ?>"
                             empty-error-message="<?= esc_html($yape_phone_number_error_message1) ?>"
                             invalid-error-message="<?= esc_html($yape_phone_number_error_message2) ?>"></input-field>
                <input-code label="<?= esc_html($input_code_label) ?>"
                            src="<?= esc_html($input_code_icon) ?>"
                            empty-error-message="<?= esc_html($yape_input_code_error_message1) ?>"
                            invalid-error-message="<?= esc_html($yape_input_code_error_message2) ?>"
                            tooltip-text="<?= esc_html($yape_tooltip_text) ?>"
                ></input-code>
            </div>
            <checkout-notice
                message="<?= esc_html($checkout_notice_message) ?>"
                src="<?= esc_html($checkout_notice_icon_one) ?>"
                icon="<?= esc_html($checkout_notice_icon_two) ?>"
                footer-text="<?= esc_html($footer_text) ?>"
            ></checkout-notice>
        </div>

        <div class="mp-checkout-yape-terms-and-conditions">
            <terms-and-conditions
                description="<?= esc_html($terms_and_conditions_description) ?>"
                link-text="<?= esc_html($terms_and_conditions_link_text) ?>"
                link-src="<?= esc_html($terms_and_conditions_link_src) ?>">
            </terms-and-conditions>
        </div>
    </div>

</div>

<div id="mercadopago-utilities" style="display:none;">
    <input type="hidden" id="yapeToken" name="mercadopago_yape[token]" />
</div>

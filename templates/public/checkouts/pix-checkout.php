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
 * @var string|null $smart_pix_amount
 * @var string $message_error_amount
 * @var string $smart_pix_mp_icon_src
 * @var string $smart_pix_pix_icon_src
 * @var string $smart_pix_bb_icon_src
 * @var string $smart_pix_nubank_icon_src
 * @var string $smart_pix_selected_account_text
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
        <div id="mp-checkout-pix-root" class="mp-checkout-pix-container" data-mp-smart-pix-bootstrap="pending">
            <div data-mp-smart-pix-initial-loader hidden aria-hidden="true">
                <svg viewBox="0 0 50 50" focusable="false">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="3"></circle>
                </svg>
            </div>
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

            <div
                id="mp-smart-pix-root"
                class="mp-smart-pix"
                data-smart-pix-state="unavailable"
                data-smart-pix-amount="<?= esc_attr($smart_pix_amount ?? '') ?>"
                data-smart-pix-mp-icon-src="<?= esc_url($smart_pix_mp_icon_src) ?>"
                data-smart-pix-pix-icon-src="<?= esc_url($smart_pix_pix_icon_src) ?>"
                data-smart-pix-banco-do-brasil-icon-src="<?= esc_url($smart_pix_bb_icon_src) ?>"
                data-smart-pix-nubank-icon-src="<?= esc_url($smart_pix_nubank_icon_src) ?>"
                data-smart-pix-selected-account-copy="<?= esc_attr($smart_pix_selected_account_text) ?>"
                aria-busy="false"
                hidden>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php

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
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-pix-container">
        <?php if (true === $test_mode) : ?>
            <div class="mp-checkout-pix-test-mode">
                <test-mode
                    title="<?= $test_mode_title ?>"
                    description="<?= $test_mode_description ?>"
                >
                </test-mode>
            </div>
        <?php endif; ?>

        <pix-template
            title="<?= $pix_template_title ?>"
            subtitle="<?= $pix_template_subtitle ?>"
            alt="<?= $pix_template_alt ?>"
            src="<?= $pix_template_src ?>"
        >
        </pix-template>

        <div class="mp-checkout-pix-terms-and-conditions">
            <terms-and-conditions
                description="<?= $terms_and_conditions_description ?>"
                link-text="<?= $terms_and_conditions_link_text ?>"
                link-src="<?= $terms_and_conditions_link_src ?>"
            >
            </terms-and-conditions>
        </div>
    </div>
</div>

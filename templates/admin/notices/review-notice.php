<?php

/**
 * @var string $minilogo
 * @var string $activateLink
 * @var string $installLink
 * @var string $missWoocommerceAction
 * @var array $translations
 *
 * @see \MercadoPago\Woocommerce\Helpers\Notices
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="message" class="notice is-dismissible mp-rating-notice">
    <div class="mp-rating-frame">
        <div class="mp-left-rating">
            <div>
                <img src="<?= esc_url($minilogo) ?>" alt="Mercado Pago logo" >
            </div>
            <div class="mp-left-rating-text">
                <p class="mp-rating-title">
                    <?= wp_kses_post($title) ?>
                </p>
                <p class="mp-rating-subtitle">
                    <?= wp_kses_post($subtitle) ?>
                </p>
            </div>
        </div>
        <div class="mp-right-rating">
            <a
                class="mp-rating-link"
                href="<?= esc_url($buttonLink) ?>" target="blank"
            >
                <?= wp_kses_post($buttonText) ?>
            </a>
        </div>
    </div>
</div>

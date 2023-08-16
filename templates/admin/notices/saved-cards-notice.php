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

<div id="saved-cards-notice" class="notice is-dismissible mp-saved-cards-notice">
    <div class="mp-left-saved-cards">
        <div>
            <img src="<?= esc_url($cardIcon) ?>">
        </div>
        <div class="mp-left-saved-cards-text">
            <p class="mp-saved-cards-title">
                <?= wp_kses_post($title) ?>
            </p>
            <p class="mp-saved-cards-subtitle">
                <?= wp_kses_post($subtitle) ?>
            </p>
        </div>
    </div>
    <div class="mp-right-saved-cards">
        <a
            class="mp-saved-cards-link"
            href="<?= esc_url($buttonLink) ?>"
        >
            <?= wp_kses_post($buttonText) ?>
        </a>
    </div>
</div>

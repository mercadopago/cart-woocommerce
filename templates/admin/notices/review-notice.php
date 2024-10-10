<?php

/**
 * @var string $title
 * @var string $subtitle
 * @var string $buttonLink
 * @var string $buttonText
 *
 * @see \MercadoPago\Woocommerce\Helpers\Notices
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div id="mp-rating-message" class="mp-rating-review notice is-dismissible mp-rating-notice">
    <button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>
    <div class="mp-alert-color-success"></div>

    <div class="mp-review-body">
        <div class="mp-icon-badge-info"></div>
        <div>
            <span class="mp-text-title"><b>
                    <?= wp_kses_post($title) ?>
                </b></span>
            <span class="mp-text-subtitle"><?= wp_kses_post($subtitle) ?></span>
            <a class="mp-review-button-a" target="_blank" href="<?= esc_url($buttonLink) ?>">
                <button type="button" class="mp-review-button"><?= wp_kses_post($buttonText) ?></button>
            </a>
        </div>
    </div>
</div>


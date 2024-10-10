<?php

/**
 * @var string $minilogo
 * @var string $activateLink
 * @var string $installLink
 * @var string $missWoocommerceAction
 * @var array $translations
 *
 * @see  \MercadoPago\Woocommerce\WoocommerceMercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<script>
    window.addEventListener("load", function() {
        clearInfo();
    });
</script>

<div id="message-feedback-info" class="mp-feedback-info-notice">
    <div class="mp-feedback-info-frame">
        <div class="mp-feedback-info-icon">
            <img src="<?= esc_url($minilogo) ?>" alt="Feedback de ação logo">
        </div>
        <div class="mp-right-alert">
            <p class="mp-feedback-info-title">
                <?= wp_kses_post($title) ?>
            </p>
            <p class="mp-feedback-info-subtitle">
                <?= wp_kses_post($subtitle) ?>
            </p>
        </div>
    </div>
</div>

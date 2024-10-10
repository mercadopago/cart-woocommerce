<?php

/**
 * @var string $minilogo
 * @var string $activateLink
 * @var string $installLink
 * @var string $missWoocommerceAction
 * @var array $translations
 * @var array $allowedHtmlTags
 *
 * @see \MercadoPago\Woocommerce\WoocommerceMercadoPago
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="message" class="notice notice-error">
    <div class="mp-alert-frame">
        <div class="mp-left-alert">
            <img src="<?= esc_url($minilogo) ?>" alt="Mercado Pago mini logo" />
        </div>

        <div class="mp-right-alert">
            <p><?= wp_kses($translations['miss_woocommerce'], $allowedHtmlTags) ?></p>

            <p>
                <?php if ($missWoocommerceAction === 'active') : ?>
                    <a class="button button-primary" href="<?= esc_html($activateLink) ?>">
                        <?= wp_kses($translations['activate_woocommerce'], $allowedHtmlTags) ?>
                    </a>
                <?php elseif ($missWoocommerceAction === 'install') : ?>
                    <a class="button button-primary" href="<?= esc_html($installLink) ?>">
                        <?= wp_kses($translations['install_woocommerce'], $allowedHtmlTags) ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary" href="https://wordpress.org/plugins/woocommerce/">
                        <?= wp_kses($translations['see_woocommerce'], $allowedHtmlTags) ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

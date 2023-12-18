<?php

/**
 * @var string $minilogo
 * @var string $activateLink
 * @var string $installLink
 * @var string $missWoocommerceAction
 * @var array $translations
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
            <p><?= $translations['miss_woocommerce'] ?></p>

            <p>
                <?php if ($missWoocommerceAction === 'active') : ?>
                    <a class="button button-primary" href="<?= esc_html($activateLink) ?>">
                        <?= $translations['activate_woocommerce'] ?>
                    </a>
                <?php elseif ($missWoocommerceAction === 'install') : ?>
                    <a class="button button-primary" href="<?= esc_html($installLink) ?>">
                        <?= $translations['install_woocommerce'] ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary" href="https://wordpress.org/plugins/woocommerce/">
                        <?= $translations['see_woocommerce'] ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

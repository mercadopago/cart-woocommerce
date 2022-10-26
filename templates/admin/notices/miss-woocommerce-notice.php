<?php

/**
 * @var string $minilogo
 * @var string $missWoocommerceAction
 *
 * @see \MercadoPago\Woocommerce\Admin\Notices
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
            <p>
                <?=
                sprintf(
                    'The Mercado Pago module needs an active version of %s in order to work!',
                    '<a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
                );
                ?>
            </p>

            <p>
                <?php if ($missWoocommerceAction === 'active') : ?>
                    <a
                        class="button button-primary"
                        href="<?= wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=all'), 'activate-plugin_woocommerce/woocommerce.php') ?>">
                        <?= __('Activate WooCommerce', 'woocommerce-mercadopago') ?>
                    </a>
                <?php elseif ($missWoocommerceAction === 'install') : ?>
                    <a class="button button-primary" href="<?= wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=woocommerce'), 'install-plugin_woocommerce') ?>">
                        <?= __('Install WooCommerce', 'woocommerce-mercadopago') ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary" href="https://wordpress.org/plugins/woocommerce/">
                        <?= __('See WooCommerce', 'woocommerce-mercadopago') ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

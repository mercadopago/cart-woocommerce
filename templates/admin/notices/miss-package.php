<?php

/**
 * @var string $package
 * @see \MercadoPago\Woocommerce\Packages
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="notice notice-error">
    <p>
        <b>Missing the Mercado Pago <code> <?= esc_html($package) ?></code> package.</b>
    </p>
    <p>Your installation of Mercado Pago is incomplete.</p>
</div>

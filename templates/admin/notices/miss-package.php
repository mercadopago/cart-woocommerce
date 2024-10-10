<?php

/**
 * @var string $path
 * @see \MercadoPago\Woocommerce\Startup
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="notice notice-error">
    <p>
        <b>Missing the Mercado Pago <code> <?= esc_html($path) ?></code> package.</b>
    </p>
    <p>Your installation of Mercado Pago is incomplete.</p>
</div>

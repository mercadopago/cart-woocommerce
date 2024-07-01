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
        <b>Unable to find composer autoloader on <code><?= esc_html($path) ?></code></b>
    </p>
    <p>Your installation of Mercado Pago is incomplete.</p>
</div>

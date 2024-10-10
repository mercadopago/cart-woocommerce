<?php

/**
 * @var string $file
 * @see \MercadoPago\Woocommerce\Startup
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="notice notice-error">
    <p>
        <b>Unable to find composer autoloader on <code><?= esc_html($file) ?></code></b>
    </p>
    <p>Your installation of Mercado Pago is incomplete.</p>
</div>

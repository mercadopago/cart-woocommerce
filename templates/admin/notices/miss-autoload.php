<?php

/**
 * @var string $autoloader
 * @see \MercadoPago\Woocommerce\Autoloader
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="notice notice-error">
    <p>
        <b>Unable to find composer autoloader on <code><?= esc_html($autoloader) ?></code></b>
    </p>
    <p>Your installation of Mercado Pago is incomplete.</p>
</div>

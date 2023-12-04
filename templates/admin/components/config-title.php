<?php

/**
 * @var array $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="row mp-pt-20">
    <div class="mp-col-md-12 mp-subtitle-header">
        <?= esc_html($settings['title']) ?>
    </div>

    <div class="mp-col-md-12">
        <p class="mp-text-checkout-body mp-mb-0">
            <?= esc_html($settings['description']) ?>
        </p>
    </div>
</div>

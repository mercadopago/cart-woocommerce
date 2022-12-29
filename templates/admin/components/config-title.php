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

<div class="row">
    <div class="mp-col-md-12 mp-subtitle-header">
        <?= $settings['title'] ?>
    </div>

    <div class="mp-col-md-12">
        <p class="mp-text-checkout-body mp-mb-0">
            <?= $settings['description'] ?>
        </p>
    </div>
</div>

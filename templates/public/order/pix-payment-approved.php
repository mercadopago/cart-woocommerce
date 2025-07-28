<?php

/**
 * @var string $approved_template_title
 * @var string $approved_template_description
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="mp-pix-approved-container">
    <div class="mp-pix-approved-card">
        <div class="mp-pix-approved-header">
            <p class="mp-pix-approved-title"><?php echo esc_html($approved_template_title); ?></p>
            <p class="mp-pix-approved-subtitle"><?php echo esc_html($approved_template_description); ?></p>
        </div>
    </div>
</div>


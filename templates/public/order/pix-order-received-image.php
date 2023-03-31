<?php

/**
 * @var string $qr_code
 * @var string $expiration_date
 * @var string $expiration_date_text
 * @var string $qr_code_image
 *
 * @see \MercadoPago\Woocommerce\Gateways\PixGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="mp-pix-image-container">
    <div>
        <img class="mp-pix-image" src="<?= esc_html($qr_code_image) ?>" alt="pix" />
    </div>

    <div class="mp-pix-image-date-expiration">
        <small>
            <?php esc_html_e($expiration_date_text) . esc_html_e($expiration_date, 'woocommerce-mercadopago'); ?>
        </small>
    </div>

    <div class="mp-pix-image-qr-code">
        <p>
            <?= esc_html($qr_code) ?>
        </p>
    </div>
</div>


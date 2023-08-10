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

<div class="mp-card-info">
    <div class="<?= esc_html($settings['value']['color_card']); ?>"></div>

    <div class="mp-card-body-payments <?= esc_html($settings['value']['size_card']); ?>">
        <div class="<?= esc_html($settings['value']['icon']); ?>"></div>
        <div>
            <span class="mp-text-title"><b><?= esc_html($settings['value']['title']); ?></b></span>
            <span class="mp-text-subtitle"><?= wp_kses($settings['value']['subtitle'], 'b'); ?></span>
            <a class="mp-button-payments-a" target="<?= esc_html($settings['value']['target']); ?>"
               href="<?= esc_html($settings['value']['button_url']); ?>">
                <button type="button"
                        class="mp-button-payments"><?= esc_html($settings['value']['button_text']); ?></button>
            </a>
        </div>
    </div>
</div>

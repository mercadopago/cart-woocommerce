<?php

/**
 * @var string $field_key
 * @var string $field_value
 * @var array $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<span id='<?= esc_attr($field_key); ?>' value='<?= esc_attr($field_value); ?>'></span>

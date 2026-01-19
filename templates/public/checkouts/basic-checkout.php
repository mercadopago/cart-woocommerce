<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var array $i18n
 * @var string $site_id
 * @var array $payment_methods
 * @var ?float $amount
 * @var \MercadoPago\Woocommerce\Helpers\Url $url
 *
 * @see \MercadoPago\Woocommerce\Gateways\BasicGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

Template::render('public/checkouts/basic-checkout-container', $args);

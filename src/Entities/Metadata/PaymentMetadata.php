<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadata
{
    public string $platform;

    public string $platform_version;

    public string $module_version;

    public string $php_version;

    public string $site_id;

    public string $sponsor_id;

    public string $collector;

    public string $test_mode;

    public string $details;

    public array $settings;

    public string $seller_website;

    public string $checkout;

    public string $checkout_type;

    public string $payment_option_id;

    public PaymentMetadataAddress $billing_address;

    public PaymentMetadataUser $user;

    public PaymentMetadataCpp $cpp_extra;

    public string $blocks_payment;

    public bool $auto_update;
}

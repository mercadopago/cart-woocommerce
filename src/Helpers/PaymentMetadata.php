<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadata
{
    public $platform;
    public $platform_version;
    public $module_version;
    public $php_version;
    public $site_id;
    public $sponsor_id;
    public $collector;
    public $test_mode;
    public $details;
    public $basic_settings;
    public $custom_settings;
    public $ticket_settings;
    public $pix_settings;
    public $credits_settings;
    public $wallet_button_settings;
    public $seller_website;
    public $billing_address;
    public $user;
    public $cpp_extra;

    /**
     * Convert PaymentMetadata object to an array.
     *
     * @param PaymentMetadata $paymentMetadata The PaymentMetadata object to convert.
     *
     * @return array The converted array representation of PaymentMetadata.
     */
    public function metadataToArray($paymentMetadata): array {
        return (array) $paymentMetadata;
    }
}

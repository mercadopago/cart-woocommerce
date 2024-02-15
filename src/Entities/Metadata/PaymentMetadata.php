<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadata
{
    /**
     * @var string
     */
    public $platform;

    /**
     * @var string
     */
    public $platform_version;

    /**
     * @var string
     */
    public $module_version;

    /**
     * @var string
     */
    public $php_version;

    /**
     * @var string
     */
    public $site_id;

    /**
     * @var string
     */
    public $sponsor_id;

    /**
     * @var string
     */
    public $collector;

    /**
     * @var string
     */
    public $test_mode;

    /**
     * @var string
     */
    public $details;

    /**
     * @var string
     */
    public $settings;

    /**
     * @var string
     */
    public $seller_website;

    /**
     * @var string
     */
    public $checkout;

    /**
     * @var string
     */
    public $checkout_type;

    /**
     * @var string
     */
    public $payment_option_id;

    /**
     * @var PaymentMetadataAddress
     */
    public $billing_address;

    /**
     * @var PaymentMetadataUser
     */
    public $user;

    /**
     * @var PaymentMetadataCpp
     */
    public $cpp_extra;

    /**
     * @var string
     */
    public $blocks_payment;
}

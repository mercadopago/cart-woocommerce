<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataCpp
{
    /**
     * @var string
     */
    public $platform_version;

    /**
     * @var string
     */
    public $module_version;
}

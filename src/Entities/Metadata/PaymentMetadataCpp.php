<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

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

<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataCpp
{
    public string $platform_version;

    public string $module_version;
}

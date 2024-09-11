<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataAddress
{
    public string $zip_code;

    public string $street_name;

    public string $city_name;

    public string $state_name;

    public string $country_name;
}

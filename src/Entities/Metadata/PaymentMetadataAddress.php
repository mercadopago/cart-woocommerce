<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataAddress
{
    /**
     * @var string
     */
    public $zip_code;

    /**
     * @var string
     */
    public $street_name;

    /**
     * @var string
     */
    public $city_name;

    /**
     * @var string
     */
    public $state_name;

    /**
     * @var string
     */
    public $country_name;
}

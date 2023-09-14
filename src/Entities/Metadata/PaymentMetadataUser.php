<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataUser
{
    /**
     * @var string
     */
    public $registered_user;

    /**
     * @var string
     */
    public $user_email;

    /**
     * @var string
     */
    public $user_registration_date;
}

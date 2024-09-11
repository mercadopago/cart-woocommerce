<?php

namespace MercadoPago\Woocommerce\Entities\Metadata;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentMetadataUser
{
    public ?string $registered_user = null;

    public ?string $user_email = null;

    public ?string $user_registration_date = null;
}

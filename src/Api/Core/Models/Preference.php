<?php

namespace MercadoPago\Woocommerce\Api\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Preference extends ApiModel
{
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}

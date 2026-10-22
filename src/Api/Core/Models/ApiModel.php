<?php

namespace MercadoPago\Woocommerce\Api\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

abstract class ApiModel
{
    protected array $data;

    protected function __construct(array $data)
    {
        $this->data = $data;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}

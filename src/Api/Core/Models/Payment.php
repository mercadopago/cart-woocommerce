<?php

namespace MercadoPago\Woocommerce\Api\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Payment extends ApiModel
{
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getStatus(): ?string
    {
        return isset($this->data['status']) && is_string($this->data['status'])
            ? $this->data['status']
            : null;
    }
}

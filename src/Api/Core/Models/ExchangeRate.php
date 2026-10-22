<?php

namespace MercadoPago\Woocommerce\Api\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ExchangeRate extends ApiModel
{
    public static function fromArray(array $data): self
    {
        if (!isset($data['rate']) || !is_numeric($data['rate'])) {
            throw new \UnexpectedValueException('Core exchange response does not contain a valid rate.');
        }

        return new self($data);
    }

    public function getRate(): float
    {
        return (float) $this->data['rate'];
    }
}

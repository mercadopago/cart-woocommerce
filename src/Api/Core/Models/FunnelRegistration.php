<?php

namespace MercadoPago\Woocommerce\Api\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class FunnelRegistration extends ApiModel
{
    private string $cppToken;

    public function __construct(array $data, string $cppToken)
    {
        parent::__construct($data);

        if (!isset($data['id']) || !is_scalar($data['id']) || (string) $data['id'] === '') {
            throw new \UnexpectedValueException('Core funnel response does not contain an integration id.');
        }

        if ($cppToken === '') {
            throw new \UnexpectedValueException('Core funnel response does not contain a CPP token.');
        }

        $this->cppToken = $cppToken;
    }

    public function getId(): string
    {
        return (string) $this->data['id'];
    }

    public function getCppToken(): string
    {
        return $this->cppToken;
    }
}

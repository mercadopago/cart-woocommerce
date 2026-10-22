<?php

namespace MercadoPago\Woocommerce\Api\Http;

if (!defined('ABSPATH')) {
    exit;
}

class TransportException extends \RuntimeException
{
    private string $transportCode;

    public function __construct(string $transportCode)
    {
        parent::__construct('Unable to connect to Mercado Pago API.');
        $this->transportCode = $transportCode;
    }

    public function getTransportCode(): string
    {
        return $this->transportCode;
    }
}

<?php

namespace MercadoPago\Woocommerce\Api\Http;

if (!defined('ABSPATH')) {
    exit;
}

class TransportException extends \RuntimeException
{
    private string $transportCode;

    private string $diagnosticMessage;

    public function __construct(string $transportCode, string $diagnosticMessage = '')
    {
        parent::__construct('Unable to connect to Mercado Pago API.');
        $this->transportCode = $transportCode;
        $this->diagnosticMessage = $diagnosticMessage;
    }

    public function getTransportCode(): string
    {
        return $this->transportCode;
    }

    /** Internal troubleshooting only; getMessage() remains safe for existing checkout callers. */
    public function getDiagnosticMessage(): string
    {
        return $this->diagnosticMessage !== '' ? $this->diagnosticMessage : $this->getMessage();
    }
}

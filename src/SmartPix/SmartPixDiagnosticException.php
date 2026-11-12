<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/** Keeps the closed control-flow reason separate from server-only troubleshooting details. */
final class SmartPixDiagnosticException extends RuntimeException
{
    private string $diagnosticMessage;

    public function __construct(string $reason, string $diagnosticMessage)
    {
        parent::__construct($reason);
        $this->diagnosticMessage = $diagnosticMessage;
    }

    public function diagnosticMessage(): string
    {
        return $this->diagnosticMessage;
    }
}

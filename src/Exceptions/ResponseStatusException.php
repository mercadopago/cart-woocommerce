<?php

namespace MercadoPago\Woocommerce\Exceptions;

use Exception;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class ResponseStatusException extends Exception
{
    public function __construct($message = "Payment processing failed", $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

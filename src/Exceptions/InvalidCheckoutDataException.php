<?php

namespace MercadoPago\Woocommerce\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class InvalidCheckoutDataException extends \Exception
{
    public function __construct($message = "Invalid checkout data", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

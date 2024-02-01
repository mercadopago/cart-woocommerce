<?php

namespace MercadoPago\Woocommerce\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class RejectedPaymentException extends \Exception
{
    public function __construct($message = "Payment processing rejected", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

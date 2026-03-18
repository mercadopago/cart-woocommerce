<?php

namespace MercadoPago\Woocommerce\Exceptions;

use Exception;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class RejectedPaymentException extends Exception
{
    /**
     * @var string|null
     */
    private ?string $statusDetail;

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param string|null $statusDetail
     */
    public function __construct(
        $message = "Payment processing rejected",
        $code = 0,
        ?Throwable $previous = null,
        ?string $statusDetail = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusDetail = $statusDetail;
    }

    /**
     * @return string|null
     */
    public function getStatusDetail(): ?string
    {
        return $this->statusDetail;
    }
}

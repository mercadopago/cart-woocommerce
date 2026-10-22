<?php

namespace MercadoPago\Woocommerce\Api\Core\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class ApiException extends \Exception
{
    private ?string $errorCode;

    private ?int $apiStatus;

    private ?string $originalMessage;

    private int $httpStatus;

    public function __construct(
        string $message,
        ?string $errorCode = null,
        ?int $apiStatus = null,
        ?string $originalMessage = null,
        int $httpStatus = 0
    ) {
        parent::__construct($message, $httpStatus);
        $this->errorCode       = $errorCode;
        $this->apiStatus       = $apiStatus;
        $this->originalMessage = $originalMessage;
        $this->httpStatus      = $httpStatus;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getApiStatus(): ?int
    {
        return $this->apiStatus;
    }

    public function getOriginalMessage(): ?string
    {
        return $this->originalMessage;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

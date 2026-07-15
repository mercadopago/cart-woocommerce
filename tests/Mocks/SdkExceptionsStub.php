<?php

namespace MercadoPago\PP\Sdk\Exceptions;

if (!class_exists(ApiException::class)) {
    class ApiException extends \RuntimeException
    {
        private ?string $errorCode;
        private ?string $originalMessage;
        private int $httpStatus;

        public function __construct(
            string $message = '',
            ?string $errorCode = null,
            int $httpStatus = 0,
            ?string $originalMessage = null
        ) {
            parent::__construct($message);
            $this->errorCode       = $errorCode;
            $this->httpStatus      = $httpStatus;
            $this->originalMessage = $originalMessage;
        }

        public function getErrorCode(): ?string
        {
            return $this->errorCode;
        }

        public function getHttpStatus(): int
        {
            return $this->httpStatus;
        }

        public function getOriginalMessage(): ?string
        {
            return $this->originalMessage;
        }
    }
}

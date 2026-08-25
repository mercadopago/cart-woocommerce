<?php

namespace MercadoPago\Woocommerce\Exceptions;

use Exception;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class RefundException extends Exception
{
    public const TYPE_VALIDATION = 'request validation';
    public const TYPE_UNAUTHORIZED = 'unauthorized';
    public const TYPE_NOT_FOUND = 'not_found';
    public const TYPE_SERVER_ERROR = 'server_error';
    public const TYPE_UNKNOWN = 'unknown';
    public const TYPE_NO_PERMISSION = 'no_permission';

    protected string $errorType;
    protected ?string $paymentId = null;
    protected ?int $orderId = null;
    protected ?int $httpStatusCode = null;
    protected array $context = [];

    public function __construct(
        string $message = "Refund processing failed",
        string $errorType = self::TYPE_UNKNOWN,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $paymentId = null,
        ?int $orderId = null,
        ?int $httpStatusCode = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorType = $errorType;
        $this->paymentId = $paymentId;
        $this->orderId = $orderId;
        $this->httpStatusCode = $httpStatusCode;
        $this->context = $context;
    }

    /**
     * Get HTTP status code
     *
     * @return int|null
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get formatted context for logging
     *
     * @return array
     */
    public function getLoggingContext(): array
    {
        return array_filter([
            'error_type' => $this->errorType,
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'http_status_code' => $this->httpStatusCode,
            'error_message' => $this->getMessage(),
            'context' => $this->getSanitizedContext()
        ]);
    }

    /**
     * Keep the API response available for user-facing error handling while limiting
     * logs to stable diagnostic fields that cannot carry payment credentials.
     */
    private function getSanitizedContext(): array
    {
        $context = $this->context;
        $responseData = $context['response_data'] ?? null;

        if (!is_array($responseData)) {
            return $context;
        }

        $sanitizedResponse = array_intersect_key($responseData, array_flip(['status', 'error']));
        if (isset($responseData['cause']) && is_array($responseData['cause'])) {
            $sanitizedResponse['cause'] = array_values(array_filter(array_map(
                static function ($cause): array {
                    return is_array($cause) && isset($cause['code'])
                        ? ['code' => $cause['code']]
                        : [];
                },
                $responseData['cause']
            )));
        }

        $context['response_data'] = $sanitizedResponse;
        return $context;
    }

    /**
     * Get response data from context
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->context['response_data'] ?? [];
    }
}

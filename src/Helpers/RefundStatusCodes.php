<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Exceptions\RefundException;
use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class RefundStatusCodes
{
    private AdminTranslations $adminTranslations;

    // Success codes
    public const OK = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;

    // Client error codes
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;

    // Server error codes
    public const INTERNAL_SERVER_ERROR = 500;
    public const SERVICE_UNAVAILABLE = 503;

    /**
     * RefundStatusCodes constructor
     *
     * @param AdminTranslations $adminTranslations
     */
    public function __construct(AdminTranslations $adminTranslations)
    {
        $this->adminTranslations = $adminTranslations;
    }

    /**
     * Status code configurations
     */
    private function getStatusConfig(): array
    {
        return [
            self::BAD_REQUEST => [
                'message' => 'Invalid Request',
                'type' => RefundException::TYPE_VALIDATION,
                'user_message' => $this->adminTranslations->refund['invalid_request']
            ],
            self::UNAUTHORIZED => [
                'message' => 'Unauthorized',
                'type' => RefundException::TYPE_UNAUTHORIZED,
                'user_message' => $this->adminTranslations->refund['unauthorized']
            ],
            self::FORBIDDEN => [
                'message' => 'Forbidden',
                'type' => RefundException::TYPE_UNAUTHORIZED,
                'user_message' => $this->adminTranslations->refund['forbidden']
            ],
            self::NOT_FOUND => [
                'message' => 'Not Found',
                'type' => RefundException::TYPE_NOT_FOUND,
                'user_message' => $this->adminTranslations->refund['payment_not_found']
            ],
            self::INTERNAL_SERVER_ERROR => [
                'message' => 'Internal server error',
                'type' => RefundException::TYPE_SERVER_ERROR,
                'user_message' => $this->adminTranslations->refund['internal_server_error']
            ]
        ];
    }

    /**
     * Check if status code indicates success
     *
     * @param int $code
     *
     * @return bool
     */
    public function isSuccessful(int $code): bool
    {
        return in_array($code, [
            self::OK,
            self::CREATED,
            self::ACCEPTED
        ]);
    }

    /**
     * Get status code description
     *
     * @param int $code
     *
     * @return string
     */
    private function getDescription(int $code): string
    {
        $descriptions = [
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::ACCEPTED => 'Accepted',
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable'
        ];

        return $descriptions[$code] ?? 'Unknown Status Code';
    }

    /**
     * Get base error information for status code
     *
     * @param int $statusCode
     * @return array|null
     */
    private function getStatusConfigForCode(int $statusCode): ?array
    {
        $statusConfig = $this->getStatusConfig();
        return $statusConfig[$statusCode] ?? null;
    }

    /**
     * Get error message for logs and debug
     *
     * @param int $code
     * @param array $responseData
     *
     * @return string
     */
    public function getErrorMessage(int $code, array $responseData = []): string
    {
        $config = $this->getStatusConfigForCode($code);

        if (!$config) {
            return $this->adminTranslations->refund['unknown_error'];
        }

        $httpStatus = $config['message'];

        if (!empty($responseData['message'])) {
            return "$httpStatus: " . $responseData['message'];
        }

        return $httpStatus;
    }

    /**
     * Get user-friendly error message
     *
     * @param int $statusCode
     * @param array $responseData
     *
     * @return string
     */
    public function getUserMessage(int $statusCode, array $responseData = []): string
    {
        $config = $this->getStatusConfigForCode($statusCode);

        if (!$config) {
            return $this->adminTranslations->refund['unknown_error'];
        }

        if ($statusCode === self::BAD_REQUEST) {
            $refundValidationError = new RefundValidationError($this->adminTranslations);

            $causeMessage = $refundValidationError->processCauseMessage($responseData);
            if ($causeMessage) {
                return $causeMessage;
            }

            $originalMessage = $refundValidationError->processOriginalMessage($responseData);
            if ($originalMessage) {
                return $originalMessage;
            }
        }

        return $config['user_message'];
    }

    /**
     * Get error type for status code
     *
     * @param int $statusCode
     *
     * @return string
     */
    private function getErrorType(int $statusCode): string
    {
        $config = $this->getStatusConfigForCode($statusCode);

        return $config['type'] ?? RefundException::TYPE_UNKNOWN;
    }

    /**
     * Create RefundException for status code
     *
     * @param int $statusCode
     * @param array $responseData
     * @param string|null $paymentId
     * @param int|null $orderId
     * @param array $context
     *
     * @return RefundException
     */
    public function createException(
        int $statusCode,
        array $responseData = [],
        ?string $paymentId = null,
        ?int $orderId = null,
        array $context = []
    ): RefundException {
        $message = $this->getErrorMessage($statusCode, $responseData);
        $errorType = $this->getErrorType($statusCode);

        $fullContext = array_merge($context, [
            'response_data' => $responseData,
            'status_description' => $this->getDescription($statusCode)
        ]);

        return new RefundException(
            $message,
            $errorType,
            $statusCode,
            null,
            $paymentId,
            $orderId,
            $statusCode,
            $fullContext
        );
    }
}

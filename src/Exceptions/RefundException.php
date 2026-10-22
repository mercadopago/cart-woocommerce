<?php

namespace MercadoPago\Woocommerce\Exceptions;

use Exception;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class RefundException extends Exception
{
    private const MAX_DIAGNOSTIC_MESSAGE_LENGTH = 2000;

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

        if (isset($responseData['code']) && is_scalar($responseData['code'])) {
            $sanitizedResponse['code'] = (string) $responseData['code'];
        }

        if (isset($responseData['original_message'])) {
            $originalMessage = $this->sanitizeDiagnosticMessage($responseData['original_message']);
            if ($originalMessage !== null && $originalMessage !== '') {
                $sanitizedResponse['original_message'] = $originalMessage;
            }
        }

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
     * Preserve backend diagnostics while removing common credential and PII formats.
     *
     * @param mixed $message
     */
    private function sanitizeDiagnosticMessage($message): ?string
    {
        if (!is_scalar($message)) {
            return null;
        }

        $boundedMessage = substr((string) $message, 0, self::MAX_DIAGNOSTIC_MESSAGE_LENGTH);
        $boundedMessage = $this->sanitizeQuotedCredentials($boundedMessage);

        $patterns = [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '/(\bAuthorization\b\s*[:=]\s*)(?:Bearer\s+)?[^\s,;}\]]+/i',
            '/(\b(?:access_token|test_access_token|token|pseudotoken|authorized_pseudotoken|client_secret|password)\b\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;}\]]+)/i',
            '/\b(?:APP_USR|TEST)-[A-Z0-9-]+\b/i',
            '/(\b(?:Cookie|Set-Cookie)\b\s*[:=]\s*)[^\r\n]+/i',
            '/\beyJ[A-Z0-9_-]*\.[A-Z0-9_-]+\.[A-Z0-9_-]+\b/i',
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '/\b\d{7,19}\b/',
        ];
        $replacements = [
            '[redacted_email]',
            '$1[redacted]',
            '$1[redacted]',
            '[redacted_credential]',
            '$1[redacted]',
            '[redacted_jwt]',
            '[redacted_ip]',
            '[redacted_number]',
        ];

        $sanitizedMessage = preg_replace($patterns, $replacements, $boundedMessage);
        if ($sanitizedMessage === null) {
            return null;
        }

        return $sanitizedMessage;
    }

    private function sanitizeQuotedCredentials(string $message): string
    {
        $pattern = '/\b(?:Authorization|Cookie|Set-Cookie|access_token|test_access_token|token|pseudotoken|authorized_pseudotoken|client_secret|password)\b\\\\?"\s*:\s*\\\\?"/i';
        $searchOffset = 0;

        while (preg_match($pattern, $message, $match, PREG_OFFSET_CAPTURE, $searchOffset) === 1) {
            $prefix = $match[0][0];
            $valueStart = $match[0][1] + strlen($prefix);
            $escapedJson = substr($prefix, -2) === '\\"';
            $valueEnd = $this->findQuotedCredentialEnd($message, $valueStart, $escapedJson);

            $message = substr_replace($message, '[redacted]', $valueStart, $valueEnd - $valueStart);
            $searchOffset = $valueStart + strlen('[redacted]');
        }

        return $message;
    }

    private function findQuotedCredentialEnd(string $message, int $valueStart, bool $escapedJson): int
    {
        $backslashCount = 0;
        $length = strlen($message);

        for ($index = $valueStart; $index < $length; $index++) {
            if ($message[$index] === '\\') {
                $backslashCount++;
                continue;
            }

            if ($message[$index] === '"') {
                $isClosingQuote = $escapedJson
                    ? $backslashCount % 4 === 1
                    : $backslashCount % 2 === 0;

                if ($isClosingQuote) {
                    return $escapedJson ? $index - 1 : $index;
                }
            }

            $backslashCount = 0;
        }

        return $length;
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

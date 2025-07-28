<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class RefundValidationError
{
    private AdminTranslations $adminTranslations;

    public const AMOUNT_MUST_BE_POSITIVE = 4040;
    public const PAYMENT_TOO_OLD = 2024;
    public const INVALID_PAYMENT_STATUS = 2063;
    public const PAYMENT_NOT_FOUND = 404;
    public const INVALID_REFUND_AMOUNT = 2017;
    public const INSUFFICIENT_FUNDS = 2031;

    /**
     * RefundValidationError constructor
     *
     * @param AdminTranslations $adminTranslations
     */
    public function __construct(AdminTranslations $adminTranslations)
    {
        $this->adminTranslations = $adminTranslations;
    }

    /**
     * Mapping of codes to user-friendly messages
     */
    private function getUserMessages(): array
    {
        return [
            self::AMOUNT_MUST_BE_POSITIVE => $this->adminTranslations->refund['amount_must_be_positive'],
            self::PAYMENT_TOO_OLD => $this->adminTranslations->refund['payment_too_old'],
            self::INVALID_PAYMENT_STATUS => $this->adminTranslations->refund['invalid_payment_status'],
            self::PAYMENT_NOT_FOUND => $this->adminTranslations->refund['payment_not_found'],
            self::INVALID_REFUND_AMOUNT => $this->adminTranslations->refund['invalid_refund_amount'],
            self::INSUFFICIENT_FUNDS => $this->adminTranslations->refund['insufficient_funds']
        ];
    }

    /**
     * Check if "cause" field exists in response and process user message
     *
     * @param array $responseData
     * @return string|null
     */
    public function processCauseMessage(array $responseData): ?string
    {
        if (!self::hasCause($responseData)) {
            return null;
        }

        $cause = $responseData['cause'];
        $cause = reset($cause);

        if (!$cause || !isset($cause['code'])) {
            return null;
        }

        $causeCode = (int) $cause['code'];
        $causeDescription = $cause['description'] ?? '';

        return $this->getUserMessage($causeCode, $causeDescription);
    }

    /**
     * Check if "original_message" field exists in response and process user message
     *
     * @param array $responseData
     * @return string|null
     */
    public function processOriginalMessage(array $responseData): ?string
    {
        if (!self::hasOriginalMessage($responseData)) {
            return null;
        }

        $originalMessage = $responseData['original_message'];
        $userMessages = $this->getUserMessages();

        foreach ($userMessages as $causeCode => $causeDescription) {
            $causeCodeString = strval($causeCode);
            if (strpos($originalMessage, $causeCodeString) !== false) {
                return $this->getUserMessage($causeCode, $causeDescription);
            }
        }

        return null;
    }

    /**
     * Check if response contains "cause" field
     *
     * @param array $responseData
     * @return bool
     */
    private static function hasCause(array $responseData): bool
    {
        return !empty($responseData['cause']) && is_array($responseData['cause']);
    }

    /**
     * Check if response contains "original_message" field
     *
     * @param array $responseData
     * @return bool
     */
    private static function hasOriginalMessage(array $responseData): bool
    {
        return !empty($responseData['original_message']) && is_string($responseData['original_message']);
    }

    /**
     * Get user-friendly message
     *
     * @param int $causeCode
     * @param string $fallbackMessage
     * @return string
     */
    private function getUserMessage(int $causeCode, string $fallbackMessage = ''): string
    {
        $userMessages = $this->getUserMessages();

        if (empty($fallbackMessage)) {
            $fallbackMessage = $this->adminTranslations->refund['unknown_error'];
        }

        return $userMessages[$causeCode] ?? $fallbackMessage;
    }
}

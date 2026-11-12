<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stable buyer-facing Smart Pix error codes shared with the Smart Pix bundle.
 * Upstream status details never leave the server; only these codes do.
 */
final class SmartPixBuyerError
{
    public const PROCESSING_FAILED = 'smart_pix_processing_failed';

    public const REJECTED = 'smart_pix_rejected';

    public const INSUFFICIENT_BALANCE = 'smart_pix_insufficient_balance';

    public const LIMIT_EXCEEDED = 'smart_pix_limit_exceeded';

    public const NOTICE_DATA_KEY = 'mp-smart-pix-error';

    public const PAYMENT_DETAILS_KEY = 'smart_pix_error';

    private const CODES = [
        self::PROCESSING_FAILED,
        self::REJECTED,
        self::INSUFFICIENT_BALANCE,
        self::LIMIT_EXCEEDED,
    ];

    private const REJECTION_CODES = [
        self::REJECTED,
        self::INSUFFICIENT_BALANCE,
        self::LIMIT_EXCEEDED,
    ];

    private const REJECTION_CODES_BY_STATUS_DETAIL = [
        'insufficient_amount'   => self::INSUFFICIENT_BALANCE,
        'rejected_cap_exceeded' => self::LIMIT_EXCEEDED,
    ];

    public static function isCode(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    /** A rejection is an expected buyer outcome, unlike a processing failure. */
    public static function isRejection(string $code): bool
    {
        return in_array($code, self::REJECTION_CODES, true);
    }

    public static function fromRejectionStatusDetail(?string $statusDetail): string
    {
        return self::REJECTION_CODES_BY_STATUS_DETAIL[$statusDetail ?? ''] ?? self::REJECTED;
    }
}

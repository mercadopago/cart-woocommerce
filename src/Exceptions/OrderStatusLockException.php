<?php

namespace MercadoPago\Woocommerce\Exceptions;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recoverable failure to obtain (or keep) exclusive access to a Pix order status update.
 * The message is fixed on purpose: it can reach HTTP responses, so it never carries SQL or credentials.
 */
class OrderStatusLockException extends RuntimeException
{
    public const REASON_BUSY        = 'busy';
    public const REASON_UNAVAILABLE = 'unavailable';
    public const REASON_LOST        = 'lost';

    private const MESSAGES = [
        self::REASON_BUSY        => 'Pix order status is being processed',
        self::REASON_UNAVAILABLE => 'Pix order status lock is unavailable',
        self::REASON_LOST        => 'Pix order status lock was lost',
    ];

    private string $reason;

    public function __construct(string $reason)
    {
        if (!isset(self::MESSAGES[$reason])) {
            $reason = self::REASON_UNAVAILABLE;
        }

        $this->reason = $reason;
        parent::__construct(self::MESSAGES[$reason]);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

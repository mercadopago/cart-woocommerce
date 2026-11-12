<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Notification;

use InvalidArgumentException;
use JsonException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads and decodes a bounded Smart Pix notification body without effects.
 *
 * Intended order, so nothing is interpreted before the sender is authenticated:
 * 1. readRaw(): bounded read of the exact raw bytes from a read-only stream;
 * 2. the caller's authenticator verifies those exact raw bytes;
 * 3. decode(): only then parses the authenticated bytes as a JSON object.
 *
 * The raw body must never be logged or exposed; it exists only to be authenticated and decoded.
 * Authentication and linkage remain the caller's responsibility.
 *
 * Return shapes (closed):
 * - readRaw accepted: ['accepted' => true, 'category' => null, 'raw_body' => string]
 * - readRaw rejected: ['accepted' => false, 'category' => string, 'raw_body' => null]
 * - decode accepted:  ['accepted' => true, 'category' => null, 'payload' => array<string, mixed>]
 * - decode rejected:  ['accepted' => false, 'category' => string, 'payload' => null]
 *
 * Rejection categories:
 * - body_too_large:  declared or actual size exceeds the byte limit;
 * - invalid_payload: malformed declared length, invalid JSON or a non-object top level;
 * - unreadable_body: the stream could not be opened or read.
 */
final class SmartPixNotificationBody
{
    /** Provisional limit pending the real notification payload contract. */
    public const DEFAULT_MAX_BYTES = 16384;

    public const CATEGORY_BODY_TOO_LARGE = 'body_too_large';

    public const CATEGORY_INVALID_PAYLOAD = 'invalid_payload';

    public const CATEGORY_UNREADABLE_BODY = 'unreadable_body';

    private const JSON_DEPTH = 32;

    private int $maxBytes;

    /** @var callable(): mixed */
    private $opener;

    /** @param (callable(): mixed)|null $opener Must return a readable stream resource. */
    public function __construct(int $maxBytes = self::DEFAULT_MAX_BYTES, ?callable $opener = null)
    {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException('The notification body limit must be positive.');
        }
        $this->maxBytes = $maxBytes;
        $this->opener = $opener ?? static function () {
            return fopen('php://input', 'rb');
        };
    }

    /**
     * Reads the raw body without interpreting it.
     *
     * @param string|null $contentLength Raw declared length, e.g. from $_SERVER['CONTENT_LENGTH'].
     * @return array{accepted: bool, category: string|null, raw_body: string|null}
     */
    public function readRaw(?string $contentLength = null): array
    {
        if ($contentLength !== null) {
            if (preg_match('/^(0|[1-9][0-9]*)$/D', $contentLength) !== 1) {
                return $this->rejectedRaw(self::CATEGORY_INVALID_PAYLOAD);
            }
            // Compare as strings first so oversized declarations never overflow int.
            if (strlen($contentLength) > strlen((string) $this->maxBytes) || (int) $contentLength > $this->maxBytes) {
                return $this->rejectedRaw(self::CATEGORY_BODY_TOO_LARGE);
            }
        }

        $body = $this->readBounded();
        if ($body === null) {
            return $this->rejectedRaw(self::CATEGORY_UNREADABLE_BODY);
        }
        if (strlen($body) > $this->maxBytes) {
            return $this->rejectedRaw(self::CATEGORY_BODY_TOO_LARGE);
        }

        return ['accepted' => true, 'category' => null, 'raw_body' => $body];
    }

    /**
     * Decodes an already authenticated raw body; never call it before authentication.
     *
     * @return array{accepted: bool, category: string|null, payload: array<string, mixed>|null}
     */
    public function decode(string $rawBody): array
    {
        // Enforced again so arbitrary input can never bypass the byte limit.
        if (strlen($rawBody) > $this->maxBytes) {
            return $this->rejected(self::CATEGORY_BODY_TOO_LARGE);
        }
        // Lists also decode to arrays; only a JSON object is a valid notification.
        if (strpos(ltrim($rawBody, " \t\n\r"), '{') !== 0) {
            return $this->rejected(self::CATEGORY_INVALID_PAYLOAD);
        }

        try {
            $payload = json_decode($rawBody, true, self::JSON_DEPTH, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return $this->rejected(self::CATEGORY_INVALID_PAYLOAD);
        }
        if (!is_array($payload)) {
            return $this->rejected(self::CATEGORY_INVALID_PAYLOAD);
        }

        return ['accepted' => true, 'category' => null, 'payload' => $payload];
    }

    /** Reads at most limit + 1 bytes so an oversized body is detected without buffering it. */
    private function readBounded(): ?string
    {
        $stream = ($this->opener)();
        if (!is_resource($stream)) {
            return null;
        }

        try {
            $body = '';
            $budget = $this->maxBytes + 1;
            while (strlen($body) < $budget && !feof($stream)) {
                $chunk = fread($stream, $budget - strlen($body));
                if ($chunk === false) {
                    return null;
                }
                if ($chunk === '') {
                    break;
                }
                $body .= $chunk;
            }
            return $body;
        } finally {
            fclose($stream);
        }
    }

    /** @return array{accepted: bool, category: string|null, payload: array<string, mixed>|null} */
    private function rejected(string $category): array
    {
        return ['accepted' => false, 'category' => $category, 'payload' => null];
    }

    /** @return array{accepted: bool, category: string|null, raw_body: string|null} */
    private function rejectedRaw(string $category): array
    {
        return ['accepted' => false, 'category' => $category, 'raw_body' => null];
    }
}

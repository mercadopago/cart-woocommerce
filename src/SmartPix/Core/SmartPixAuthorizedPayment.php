<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Core;

use InvalidArgumentException;
use LogicException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Holds sensitive Smart Pix payment material only while an Orders request is built.
 */
final class SmartPixAuthorizedPayment
{
    private string $authorizedPseudotoken;

    private string $openFinanceAccountId;

    private string $idempotencyKey;

    public function __construct(string $authorizedPseudotoken, string $openFinanceAccountId, string $idempotencyKey)
    {
        if (
            trim($authorizedPseudotoken) === '' || trim($idempotencyKey) === ''
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $openFinanceAccountId) !== 1
        ) {
            throw new InvalidArgumentException('Invalid Smart Pix authorization material.');
        }
        $this->authorizedPseudotoken = $authorizedPseudotoken;
        $this->openFinanceAccountId = $openFinanceAccountId;
        $this->idempotencyKey       = $idempotencyKey;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['authorization' => '[redacted]'];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new LogicException('Smart Pix authorization material cannot be serialized.');
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Smart Pix authorization material cannot be unserialized.');
    }

    public function getAuthorizedPseudotoken(): string
    {
        return $this->authorizedPseudotoken;
    }

    public function getOpenFinanceAccountId(): string
    {
        return $this->openFinanceAccountId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}

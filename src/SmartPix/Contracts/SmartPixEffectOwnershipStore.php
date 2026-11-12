<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Internal admission port; never an authorization or native-effect delivery guarantee. */
interface SmartPixEffectOwnershipStore
{
    /** @return array{outcome: string, owner: string|null} */
    public function acquire(int $orderId, string $attemptKey, string $effect): array;

    public function start(int $orderId, string $attemptKey, string $effect, string $owner): bool;

    public function complete(int $orderId, string $attemptKey, string $effect, string $owner): bool;

    public function markUncertain(int $orderId, string $attemptKey, string $effect, string $owner): bool;

    public function releaseReserved(int $orderId, string $attemptKey, string $effect, string $owner): bool;
}

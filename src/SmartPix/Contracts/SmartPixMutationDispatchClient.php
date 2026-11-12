<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Optional server-only evidence; normalized responses and existing clients remain compatible. */
interface SmartPixMutationDispatchClient extends SmartPixOrderClient
{
    /**
     * Invoke onDispatch immediately before handing the POST to the transport, never during reads.
     * Once invoked, a failed or unconfirmed result must be treated as possibly dispatched.
     *
     * @param array<string, mixed> $request
     * @param callable(): void|null $onDispatch
     * @return array<string, mixed>
     */
    public function cancel(array $request, ?callable $onDispatch = null): array;

    /**
     * Invoke onDispatch immediately before handing the POST to the transport, never during reads.
     * Once invoked, a failed or unconfirmed result must be treated as possibly dispatched.
     *
     * @param array<string, mixed> $request
     * @param callable(): void|null $onDispatch
     * @return array<string, mixed>
     */
    public function refund(array $request, ?callable $onDispatch = null): array;
}

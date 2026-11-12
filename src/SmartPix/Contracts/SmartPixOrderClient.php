<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;

if (!defined('ABSPATH')) {
    exit;
}

interface SmartPixOrderClient
{
    /**
     * Create a Smart Pix order from a normalized request.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null): array;

    /**
     * Query a Smart Pix order from a normalized request.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function query(array $request): array;

    /**
     * Cancel a Smart Pix order from a normalized request.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function cancel(array $request): array;

    /**
     * Refund a Smart Pix order from a normalized request.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function refund(array $request): array;
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Server-only references from the latest validated create response; never an authorization proof. */
interface SmartPixCreationReferences
{
    /**
     * @return array{order_reference: string, payment_reference: string,
     *     provider_order_reference?: string, transaction_intent_reference?: string}|null
     */
    public function creationReferences(): ?array;
}

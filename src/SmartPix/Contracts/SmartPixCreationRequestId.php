<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Optional observation of the Mercado Pago Orders create response. */
interface SmartPixCreationRequestId
{
    /** Null means the create POST had no usable x-request-id response header. */
    public function creationRequestId(): ?string;
}

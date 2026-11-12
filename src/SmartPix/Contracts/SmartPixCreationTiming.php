<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Optional observation of the latest creation transport, excluding metric delivery. */
interface SmartPixCreationTiming
{
    /** Null means no transport ran or its duration could not be measured. */
    public function creationDurationSeconds(): ?float;
}

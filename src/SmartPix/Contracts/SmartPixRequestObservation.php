<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Diagnostic projection of the latest Orders call; independent of financial contracts. */
interface SmartPixRequestObservation
{
    /** @return array<string, string> */
    public function requestDetails(): array;
}

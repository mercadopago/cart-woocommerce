<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/** Transport boundary for bounded count events and numeric duration distributions. */
interface SmartPixMetricSink
{
    /**
     * @param array<string, string> $details
     * @param string $message Closed schema message; empty unless declared for the counter value.
     */
    public function count(string $name, string $value, array $details, string $message = ''): void;

    /** @param array<string, string> $details */
    public function distribution(string $name, float $seconds, array $details): void;
}

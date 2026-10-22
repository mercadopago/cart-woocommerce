<?php

namespace MercadoPago\Woocommerce\Api\Core;

if (!defined('ABSPATH')) {
    exit;
}

class PayloadNormalizer
{
    public static function preserveEmptyObjects(array $payload, array $objectPaths): array
    {
        foreach ($objectPaths as $objectPath) {
            $segments = explode('.', $objectPath);
            $value = &$payload;

            foreach ($segments as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    unset($value);
                    continue 2;
                }

                $value = &$value[$segment];
            }

            if ($value === []) {
                $value = new \stdClass();
            }

            unset($value);
        }

        return $payload;
    }
}

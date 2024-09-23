<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Arrays
{
    /**
     * Filter array elements then join them.
     *
     * @param array $array The array to iterate over
     * @param string $separator String between elements
     * @param callable|null $callback The callback function to use If no `callback` is supplied, all empty entries of `array` will be removed. See empty() for how PHP defines empty in this case.
     * @param int $mode Flag determining what arguments are sent to `callback`:
     *                  - `ARRAY_FILTER_USE_KEY` - pass key as the only argument to `callback` instead of the value
     *
     *                  - `ARRAY_FILTER_USE_BOTH` - pass both value and key as arguments to `callback` instead of the value
     *                   Default is `0` which will pass value as the only argument to `callback` instead.
     */
    public static function filterJoin(array $array, string $separator = " ", ?callable $callback = null, int $mode = 0): string
    {
        return join($separator, array_filter($array, $callback ?? fn($element) => !!$element, $mode));
    }
}

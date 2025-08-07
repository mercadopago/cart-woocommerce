<?php

namespace MercadoPago\Woocommerce\Helpers;

use Closure;

if (!defined('ABSPATH')) {
    exit;
}

class Arrays
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

    /**
     * Checks if any element in $array satisfies a given condition.
     *
     * @param \Closure $callback function(mixed $element): bool
     */
    public static function any(array $array, Closure $callback): bool
    {
        foreach ($array as $element) {
            if ($callback($element)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if $array contains any empty element
     */
    public static function anyEmpty(array $array): bool
    {
        return static::any($array, fn($element): bool => empty($element));
    }
}

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
     *
     * @param ?array $keys Array of specific keys to check. If null, checks all elements
     */
    public static function anyEmpty(array $array, ?array $keys = null): bool
    {
        if (isset($keys)) {
            $array = static::only($array, $keys);
            return array_diff_key(array_flip($keys), $array) || static::anyEmpty($array);
        }
        return static::any($array, fn($element): bool => empty($element));
    }

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * Returns a new array containing only the specified key(s) from $array.
     *
     * @param array|mixed $keys The key(s) to filter from $array
     */
    public static function only(array $array, $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Returns a new array containing all $array elements except the specified by $keys.
     *
     * @param string|array $keys The key(s) to exclude from $array
     */
    public static function except(array $array, $keys): array
    {
        return static::only($array, array_diff(array_keys($array), $keys));
    }

    /**
     * Returns $array last element.
     */
    public static function last(array $array)
    {
        return end($array);
    }
}

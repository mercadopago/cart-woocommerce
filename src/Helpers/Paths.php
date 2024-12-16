<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Paths
{
    public static string $basePath;

    /**
     * Path to the plugin folder
     */
    public static function basePath(string $path = ''): string
    {
        return static::join(dirname(MP_PLUGIN_FILE), $path);
    }

    /**
     * Path to the build folder
     */
    public static function buildPath(string $path = ''): string
    {
        return static::basePath(static::join('build', $path));
    }

    /**
     * Path to the templates folder
     */
    public static function templatesPath(string $path = ''): string
    {
        return static::basePath(static::join('templates', $path));
    }

    /**
     * Join the given paths together
     */
    public static function join(string $start, string $end): string
    {
        if (in_array('', [$start, $end], true)) {
            return "$start$end";
        }

        return rtrim($start, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($end, DIRECTORY_SEPARATOR);
    }

    /**
     * Add $extension to $path case it doesn't have one
     */
    public static function addExtension(string $path, string $extension): string
    {
        return strpos(basename($path), '.') ? $path : "$path.$extension";
    }
}

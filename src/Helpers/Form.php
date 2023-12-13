<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Form
{
    /**
     * Get data from $_POST method with sanitize for text field
     *
     * @param string $key
     *
     * @return string
     */
    public static function sanitizeTextFromPost(string $key): string
    {
        return sanitize_text_field($_POST[$key] ?? null);
    }

    /**
     * Get data from $_GET method with sanitize for text field
     *
     * @param string $key
     *
     * @return string
     */
    public static function sanitizeTextFromGet(string $key): string
    {
        return sanitize_text_field($_GET[$key] ?? null);
    }

    /**
     * Get data and sanitize for text field
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function sanitizeFromData($data)
    {
        return map_deep($data, function ($value) {
            return sanitize_text_field($value ?? null);
        });
    }
}

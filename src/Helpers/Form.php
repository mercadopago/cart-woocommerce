<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Form
{
    public static function requestMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';

        return is_string($method) ? strtoupper($method) : '';
    }

    /**
     * Sanitizes $_GET object or otherwise sanitizes an $_GET[$key] object/data
     *
     * @param string $key
     *
     * @return object|array|string
     */
    public static function sanitizedGetData(?string $key = null)
    {
        $data = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS);
        if (isset($key)) {
            $data = $data[$key] ?? null;
        }
        return self::sanitizedData($data);
    }

    /**
     * Sanitizes $_POST object or otherwise sanitizes an $_POST[$key] object/data
     *
     * @return object|array|string
     */
    public static function sanitizedPostData(?string $key = null)
    {
        $data = sanitize_post($_POST);

        return self::sanitizedData(isset($key) ? $data[$key] : $data);
    }

    /**
     * Sanitize form fields without adding WordPress post-object defaults.
     * Non-string values retain their shape for the caller's schema validation.
     */
    public static function sanitizedPostFields(): array
    {
        $fields = $_POST;
        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $fields[$key] = sanitize_text_field(wp_unslash($value));
            }
        }

        return $fields;
    }

    /**
     * @param object|array|string $data
     *
     * @return object|array|string
     */
    private static function sanitizedData($data)
    {
        if (is_object($data) || is_array($data)) {
            return map_deep($data, function ($value) {
                return sanitize_text_field($value ?? null);
            });
        }

        return sanitize_text_field($data ?? null);
    }
}

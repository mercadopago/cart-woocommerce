<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Form
{
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
     * @param string $key
     *
     * @return object|array|string
     */
    public static function sanitizedPostData(string $key = "")
    {
        $data = sanitize_post($_POST);
        if ($key != "") {
            $data = $data[$key];
        }

        return self::sanitizedData($data);
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

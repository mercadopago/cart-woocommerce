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
    public static function getSanitizeTextFromPost(string $key): string
    {
        return sanitize_text_field($_POST[$key] ?? '');
    }
}

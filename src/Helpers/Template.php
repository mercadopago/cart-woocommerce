<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class Template
{
    /**
     * Output $template on response
     */
    public static function render(string $template, array $args = []): void
    {
        wc_get_template(static::templateName($template), $args, null, Paths::templatesPath());
    }

    /**
     * Get $template html
     */
    public static function html(string $template, array $args = []): string
    {
        return wc_get_template_html(static::templateName($template), $args, null, Paths::templatesPath());
    }

    private static function templateName(string $template): string
    {
        return Paths::addExtension("/$template", 'php');
    }
}

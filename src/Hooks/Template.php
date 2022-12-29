<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Template
{
    /**
     * Get woocommerce template
     *
     * @param string $name
     * @param string $path
     * @param array  $variables
     *
     * @return void
     */
    public function getWoocommerceTemplate(string $name, string $path, array $variables = []): void
    {
        wc_get_template($name, $variables, null, $path);
    }

    /**
     * Get woocommerce template html
     *
     * @param string $name
     * @param string $path
     * @param array  $variables
     *
     * @return string
     */
    public function getWoocommerceTemplateHtml(string $name, string $path, array $variables = []): string
    {
        return wc_get_template_html($name, $variables, null, $path);
    }
}

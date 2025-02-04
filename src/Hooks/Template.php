<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Template as TemplateHelper;

if (!defined('ABSPATH')) {
    exit;
}

class Template
{
    /**
     * Get woocommerce template
     *
     * @param string $name
     * @param array $variables
     *
     * @return void
     */
    public function getWoocommerceTemplate(string $name, array $variables = []): void
    {
        TemplateHelper::render($name, $variables);
    }

    /**
     * Get woocommerce template html
     *
     * @param string $name
     * @param array $variables
     *
     * @return string
     */
    public function getWoocommerceTemplateHtml(string $name, array $variables = []): string
    {
        return TemplateHelper::html($name, $variables);
    }
}

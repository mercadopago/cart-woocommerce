<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Template
{
    /**
     * @var String
     */
    public $path;

    /**
     * Template constructor
     */
    public function __construct()
    {
        $this->path = plugin_dir_path(__FILE__) . '../../templates/';
    }

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
        wc_get_template($name, $variables, null, $this->path);
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
        return wc_get_template_html($name, $variables, null, $this->path);
    }
}

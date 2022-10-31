<?php

namespace MercadoPago\Woocommerce\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

class Scripts
{
    /**
     * @var string
     */
    private $suffix = '_params';

    /**
     * @var Scripts
     */
    private static $instance = null;

    public static function getInstance(): Scripts
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerStyle($name, $file): void
    {
        wp_register_style($name, $file, false, MP_VERSION);
        wp_enqueue_style($name);
    }

    public function registerScript($name, $file, $variables = []): void
    {
        wp_enqueue_script($name, $file, array(), MP_VERSION, true);

        if ($variables) {
            wp_localize_script($name, $name . $this->suffix, $variables);
        }
    }
}

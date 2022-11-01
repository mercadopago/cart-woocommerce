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
    protected $suffix = '_params';

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

    public function registerAdminStyle(string $name, string $file): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
    }

    public function registerAdminScript(string $name, string $file, array $variables = []): void
    {
        add_action('admin_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
        });
    }

    public function registerStoreStyle(string $name, string $file): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file) {
            $this->registerStyle($name, $file);
        });
    }

    public function registerStoreScript(string $name, string $file, array $variables = []): void
    {
        add_action('wp_enqueue_scripts', function () use ($name, $file, $variables) {
            $this->registerScript($name, $file, $variables);
        });
    }

    private function registerStyle(string $name, string $file): void
    {
        wp_register_style($name, $file, false, MP_VERSION);
        wp_enqueue_style($name);
    }

    private function registerScript(string $name, string $file, array $variables = []): void
    {
        wp_enqueue_script($name, $file, array(), MP_VERSION, true);

        if ($variables) {
            wp_localize_script($name, $name . $this->suffix, $variables);
        }
    }
}

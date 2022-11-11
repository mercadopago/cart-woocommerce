<?php

namespace MercadoPago\Woocommerce\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class MetaBoxes
{
    /**
     * @var MetaBoxes
     */
    private static $instance = null;

    /**
     * MetaBoxes constructor
     */
    private function __construct()
    {
    }

    /**
     * Get MetaBoxes instance
     *
     * @return MetaBoxes
     */
    public static function getInstance(): MetaBoxes
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Adds a meta box to screen
     *
     * @param string $id
     * @param string $title
     * @param string $name
     * @param array $args
     * @param string $path
     *
     * @return void
     */
    public function addMetaBox(string $id, string $title, string $name, array $args, string $path) {
        add_meta_box($id, $title, function () use ($name, $args, $path) {
            $this->addMetaboxContent($name, $args, $path);
        });
    }

    /**
     * Add content template to meta box
     *
     * @param string $name
     * @param array $args
     * @param string $path
     *
     * @return void
     */
    public function addMetaBoxContent(string $name, array $args, string $path) {
        wc_get_template(
            $name,
            $args,
            $path
        );
    }
}

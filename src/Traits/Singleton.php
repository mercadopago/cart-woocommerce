<?php

namespace MercadoPago\Woocommerce\Traits;

trait Singleton
{
    private static $instance;

    /**
     * Get singleton instance
     *
     * @return static
     */
    final public static function instance()
    {
        return static::$instance ??= new static();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing
     */
    final public function __wakeup()
    {
        die();
    }
}

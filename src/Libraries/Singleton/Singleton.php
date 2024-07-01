<?php

namespace MercadoPago\Woocommerce\Libraries\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

class Singleton
{
    private static $instances = [];

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * The method you use to get the Singleton's instance.
     *
     * @return Singleton Returns the subclass extending from Singleton class
     */
    public static function getInstance(): Singleton
    {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new $subclass();
        }
        return self::$instances[$subclass];
    }
}

<?php

namespace MercadoPago\Woocommerce\Libraries\Singleton;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class Singleton
{
    private static array $instances = [];

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * The method you use to get the Singleton's instance.
     *
     * @return static subclass extending from Singleton class
     */
    public static function getInstance()
    {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new $subclass();
        }
        return self::$instances[$subclass];
    }
}

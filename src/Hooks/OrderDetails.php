<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Admin\Translations;

if (!defined('ABSPATH')) {
    exit;
}

class OrderDetails
{
    /**
     * @var Translations
     */
    protected $translations;

    /**
     * @var OrderDetails
     */
    private static $instance = null;

    /**
     * OrderDetails constructor
     */
    public function __construct()
    {
        $this->translations = Translations::getInstance();
    }

    /**
     * Get OrderDetails Hooks instance
     *
     * @return OrderDetails
     */
    public static function getInstance(): OrderDetails
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add actions to meta box
     *
     * @param array $actions
     *
     * @return array
     */
    public function addOrderMetaBoxActions(array $actions): array
    {
        $actions['cancel_order'] = $this->translations->order['cancel_order'];
        return $actions;
    }
}

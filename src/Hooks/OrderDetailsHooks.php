<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Admin\Translations;

if (!defined('ABSPATH')) {
    exit;
}

class OrderDetailsHooks
{
    /**
     * @var Translations
     */
    public Translations $translations;

    /**
     * @var ?OrderDetailsHooks
     */
    private static ?OrderDetailsHooks $instance = null;

    /**
     * OrderDetailsHooks constructor
     */
    private function __construct()
    {
        $this->translations = Translations::getInstance();
    }

    /**
     * Get a OrderDetailsHook instance
     *
     * @return OrderDetailsHooks
     */
    public static function getInstance(): OrderDetailsHooks
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
        $actions['cancel_order'] = $this->translations->orderSettings['cancel_order'];
        return $actions;
    }
}

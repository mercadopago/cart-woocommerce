<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Admin\Translations;

if (!defined('ABSPATH')) {
    exit;
}

class OrderDetailsHooks
{
    public \WC_Order $order;

    private static ?OrderDetailsHooks $instance = null;

    private function __construct()
    {
        $this->paymentStatusMetabox();
        $this->paymentStatusMetaboxScript();
    }

    public static function getInstance(): OrderDetailsHooks
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function paymentStatusMetaBox(): void
    {
        add_action( 'add_meta_boxes_shop_order', 'paymentStatusMetaBox' );
    }

    public function paymentStatusMetaBoxScript(): void
    {
        add_action( 'admin_enqueue_scripts', 'paymentStatusMetaBoxScript' );
    }

    public function addOrderMetaBoxActions(array $actions): array
    {
        $actions['cancel_order'] = Translations::$orderSettings['cancel_order'];
        return $actions;
    }
}

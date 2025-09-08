<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use Mockery;
use WP_Mock;

trait WoocommerceMock
{
    /**
     * @before
     */
    public function woocommerceSetUp(): void
    {
        WP_Mock::setUp();

        Mockery::mock('WC_Payment_Gateway');
        Mockery::mock('WC_Product');
        Mockery::mock('WC_Product_Simple');
        Mockery::mock('WC_Product_External');
        Mockery::mock('WC_Product_Grouped');
        Mockery::mock('WC_Product_Variable');
        Mockery::mock('WC_Product_Attribute');
        Mockery::mock('WC_Tax');
        Mockery::mock('WC_Shipping_Rate');
        Mockery::mock('WC_Order_Item_Product');
        Mockery::mock('WC_Order_Item_Shipping');
    }

    /**
     * @after
     */
    public function woocommerceTearDown()
    {
        WP_Mock::tearDown();
    }
}

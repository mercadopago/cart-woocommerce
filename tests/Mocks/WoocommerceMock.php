<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use Mockery;
use WP_Mock;

trait WoocommerceMock
{
    public function setUp(): void
    {
        // All content on woocommerceSetUp() to simplify extending setUp()
        $this->woocommerceSetUp();
    }

    public function tearDown(): void
    {
        // All content on woocommerceTearDown() to simplify extending tearDown()
        $this->woocommerceTearDown();
    }

    private function woocommerceSetUp(): void
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

    private function woocommerceTearDown()
    {
        WP_Mock::tearDown();
    }
}

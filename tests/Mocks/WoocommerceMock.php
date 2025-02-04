<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use Mockery;

class WoocommerceMock
{
    static function setupClassMocks()
    {
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
}

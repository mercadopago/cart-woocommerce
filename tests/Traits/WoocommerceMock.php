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

        // Mock WordPress functions used by Form helper (only if not already mocked)
        if (!function_exists('sanitize_post')) {
            WP_Mock::userFunction('sanitize_post', [
                'return' => function ($data) {
                    return $data;
                }
            ]);
        }

        if (!function_exists('map_deep')) {
            WP_Mock::userFunction('map_deep', [
                'return' => function ($data, $callback) {
                    return is_array($data) ? array_map($callback, $data) : $callback($data);
                }
            ]);
        }

        if (!function_exists('sanitize_text_field')) {
            WP_Mock::userFunction('sanitize_text_field', [
                'return' => function ($data) {
                    return $data;
                }
            ]);
        }

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

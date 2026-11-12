<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use Mockery;
use Mockery\MockInterface;
use WC_Order_Item_Product;

/**
 * Synthetic buyer and cart facts shared by Smart Pix creation tests. No real personal data.
 */
trait SmartPixOrderDetailsFixture
{
    /** Valid synthetic CPF (check digits included), stored with the usual mask. */
    private static string $syntheticMaskedCpf = '123.456.789-09';

    private static string $syntheticCpf = '12345678909';

    /**
     * Stub a single product line whose total equals the order total.
     * @param MockInterface $order
     */
    private function stubOrderDetails($order, string $total = '4.00'): void
    {
        $item = Mockery::mock(WC_Order_Item_Product::class);
        $item->shouldReceive('get_name')->andReturn('Synthetic product');
        $item->shouldReceive('get_quantity')->andReturn(1);
        $item->shouldReceive('get_product_id')->andReturn(10);
        $item->shouldReceive('get_total')->andReturn($total);
        $item->shouldReceive('get_total_tax')->andReturn('0');
        $order->shouldReceive('get_items')->andReturn([$item]);
        $order->shouldReceive('get_shipping_total')->andReturn('0');
        $order->shouldReceive('get_shipping_tax')->andReturn('0');
        $order->shouldReceive('get_fees')->andReturn([]);
        $order->shouldReceive('get_billing_email')->andReturn('buyer@example.test');
        $order->shouldReceive('get_billing_first_name')->andReturn('Synthetic');
        $order->shouldReceive('get_billing_last_name')->andReturn('Buyer');
        $order->shouldReceive('get_customer_ip_address')->andReturn('203.0.113.10');
        $order->shouldReceive('get_meta')->with('_billing_cpf', true)->andReturn(self::$syntheticMaskedCpf);
    }

    /**
     * Stub the same facts as stubOrderDetails(), replacing any of them by key. Null values are
     * kept, so stored data that does not match the stub types can be exercised.
     * Keys: items (full list), name, quantity, line_total, line_tax, shipping_total,
     * shipping_tax, shipping_method, fees, email, first_name, last_name, ip, billing_cpf.
     *
     * @param MockInterface        $order
     * @param array<string, mixed> $changes
     */
    private function stubOrderDetailsWith($order, array $changes): void
    {
        $order->shouldReceive('get_id')->andReturn(321)->byDefault();
        $value = static function (string $key, $default) use ($changes) {
            return array_key_exists($key, $changes) ? $changes[$key] : $default;
        };
        if (array_key_exists('items', $changes)) {
            $items = $changes['items'];
        } else {
            $item = Mockery::mock(WC_Order_Item_Product::class);
            $item->shouldReceive('get_name')->andReturn($value('name', 'Synthetic product'));
            $item->shouldReceive('get_quantity')->andReturn($value('quantity', 1));
            $item->shouldReceive('get_product_id')->andReturn(10);
            $item->shouldReceive('get_total')->andReturn($value('line_total', '4.00'));
            $item->shouldReceive('get_total_tax')->andReturn($value('line_tax', '0'));
            $items = [$item];
        }
        $order->shouldReceive('get_items')->andReturn($items);
        $order->shouldReceive('get_shipping_total')->andReturn($value('shipping_total', '0'));
        $order->shouldReceive('get_shipping_tax')->andReturn($value('shipping_tax', '0'));
        $order->shouldReceive('get_shipping_method')->andReturn($value('shipping_method', ''));
        $order->shouldReceive('get_fees')->andReturn($value('fees', []));
        $order->shouldReceive('get_billing_email')->andReturn($value('email', 'buyer@example.test'));
        $order->shouldReceive('get_billing_first_name')->andReturn($value('first_name', 'Synthetic'));
        $order->shouldReceive('get_billing_last_name')->andReturn($value('last_name', 'Buyer'));
        $order->shouldReceive('get_customer_ip_address')->andReturn($value('ip', '203.0.113.10'));
        $order->shouldReceive('get_meta')->with('_billing_cpf', true)->andReturn($value('billing_cpf', self::$syntheticMaskedCpf));
    }

    /** @return array<string, mixed> */
    private function expectedOrderDetails(string $total = '4.00'): array
    {
        return [
            'description' => 'Synthetic product x 1',
            'ip_address'  => '203.0.113.10',
            'payer'       => [
                'email'          => 'buyer@example.test',
                'first_name'     => 'Synthetic',
                'last_name'      => 'Buyer',
                'identification' => ['type' => 'CPF', 'number' => self::$syntheticCpf],
            ],
            'items'       => [[
                'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => $total, 'quantity' => 1, 'type' => 'others',
            ]],
        ];
    }

}

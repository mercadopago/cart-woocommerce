<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixOrderDetails;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WP_Mock;

class SmartPixOrderDetailsTest extends TestCase
{
    use SmartPixOrderDetailsFixture;

    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mercadopago']);
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testProductsShippingTaxesAndFeesAreConsolidatedIntoTheFinalOrderTotal(): void
    {
        $details = (new SmartPixOrderDetails())->build($this->order(), '27.00');

        $this->assertSame('Synthetic shirt x 2', $details['description']);
        $this->assertSame([
            ['id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '27.00', 'quantity' => 1, 'type' => 'others'],
        ], $details['items']);
    }

    /** @dataProvider configuredCategoryProvider */
    public function testConfiguredStoreCategoryIsPreservedOnTheConsolidatedItem(string $amount): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreCategory')->with('others')->andReturn('electronics');

        $details = (new SmartPixOrderDetails(null, $store))->build($this->order(), $amount);

        $this->assertCount(1, $details['items']);
        $this->assertSame(['electronics'], array_column($details['items'], 'type'));
    }

    public static function configuredCategoryProvider(): array
    {
        return ['matching line totals' => ['27.00'], 'different line totals' => ['30.00']];
    }

    /** @dataProvider discountedOrderTotalProvider */
    public function testDiscountsAreNeverAllocatedToProducts(string $amount): void
    {
        $products = [];
        foreach (['Synthetic shirt', 'Synthetic book'] as $name) {
            $product = Mockery::mock(WC_Order_Item_Product::class);
            $product->shouldReceive('get_name')->andReturn($name);
            $product->shouldReceive('get_quantity')->andReturn(2);
            $product->shouldNotReceive('get_total');
            $product->shouldNotReceive('get_total_tax');
            $products[] = $product;
        }
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['items' => $products]);

        $details = (new SmartPixOrderDetails())->build($order, $amount);

        $this->assertSame('Synthetic shirt x 2, Synthetic book x 2', $details['description']);
        $this->assertSame([[
            'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => $amount, 'quantity' => 1, 'type' => 'others',
        ]], $details['items']);
    }

    public static function discountedOrderTotalProvider(): array
    {
        return [
            'percentage discount' => ['80.00'],
            'fixed discount with indivisible cents' => ['79.99'],
            'discount with shipping and taxes' => ['95.50'],
            'exact total beyond float precision' => ['9999999999999999.99'],
        ];
    }

    /**
     * @dataProvider unavailableBillingCpfProvider
     * @param mixed $cpf
     */
    public function testAbsentOrUnusableBillingCpfIsNotSentAndDoesNotBlockPayment($cpf): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['billing_cpf' => $cpf]);

        $payer = (new SmartPixOrderDetails())->build($order, '4.00')['payer'];

        $this->assertSame([
            'email' => 'buyer@example.test', 'first_name' => 'Synthetic', 'last_name' => 'Buyer',
        ], $payer);
    }

    public static function unavailableBillingCpfProvider(): array
    {
        return [
            'absent' => [''], 'null' => [null], 'array' => [[self::$syntheticCpf]],
            'integer' => [(int) self::$syntheticCpf], 'invalid' => ['11111111111'],
        ];
    }

    /**
     * @dataProvider invalidEmailProvider
     * @param mixed $email
     */
    public function testInvalidEmailFailsClosedWithoutPersonalData($email): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['email' => $email]);

        try {
            (new SmartPixOrderDetails())->build($order, '4.00');
            $this->fail('Expected the order details to be rejected.');
        } catch (InvalidArgumentException $failure) {
            $this->assertSame('Invalid Smart Pix payer.', $failure->getMessage());
            $this->assertStringNotContainsString(self::$syntheticCpf, $failure->getMessage());
        }
    }

    public static function invalidEmailProvider(): array
    {
        return ['invalid email' => ['not-an-email'], 'empty email' => [''], 'missing email' => [null]];
    }

    /**
     * @dataProvider unvalidatedCartFactProvider
     * @param array<string, mixed> $changes
     */
    public function testCartAndBuyerFactsNeverBlockThePaymentLikeTheOtherPaymentMethods(array $changes, string $description, string $firstName): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, $changes);

        $details = (new SmartPixOrderDetails())->build($order, '4.00');

        $this->assertSame($description, $details['description']);
        $this->assertSame($firstName, $details['payer']['first_name']);
        $this->assertSame([
            ['id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '4.00', 'quantity' => 1, 'type' => 'others'],
        ], $details['items']);
    }

    public static function unvalidatedCartFactProvider(): array
    {
        return [
            'no product lines' => [['items' => []], 'Consolidated Items', 'Synthetic'],
            'empty name' => [['name' => ''], ' x 1', 'Synthetic'],
            'zero quantity' => [['quantity' => 0], 'Synthetic product x 0', 'Synthetic'],
            'non-numeric string quantity' => [['quantity' => 'abc'], 'Synthetic product x abc', 'Synthetic'],
            'missing quantity' => [['quantity' => null], 'Synthetic product x ', 'Synthetic'],
            'empty first name' => [['first_name' => ''], 'Synthetic product x 1', ''],
            'blank first name' => [['first_name' => '   '], 'Synthetic product x 1', ''],
        ];
    }

    /**
     * @dataProvider orderAddressProvider
     * @param mixed $orderAddress
     */
    public function testServerAddressIsSentLikeTheOtherPaymentMethodsWhateverTheOrderAddress($orderAddress): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['ip' => $orderAddress]);

        $details = $this->withServerAddress('198.51.100.20')->build($order, '4.00');

        $this->assertSame('198.51.100.20', $details['ip_address']);
    }

    public static function orderAddressProvider(): array
    {
        return [
            'different order address' => ['203.0.113.10'],
            'invalid order address' => ['203.0.113.300'],
            'empty order address' => [''],
            'missing order address' => [null],
        ];
    }

    public function testPluginServerAddressIsTheDefault(): void
    {
        $url = Mockery::mock(Url::class);
        $url->shouldReceive('getServerAddress')->andReturn('127.0.0.1');
        $helpers = Mockery::mock(Helpers::class);
        $helpers->url = $url;
        $plugin = Mockery::mock(WoocommerceMercadoPago::class);
        $plugin->helpers = $helpers;
        $plugin->storeConfig = Mockery::mock(Store::class, ['getStoreCategory' => 'others']);
        $GLOBALS['mercadopago'] = $plugin;
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $this->assertSame('127.0.0.1', (new SmartPixOrderDetails())->build($order, '4.00')['ip_address']);
    }

    public function testPluginWithoutHelpersSendsAnEmptyAddressInsteadOfFailing(): void
    {
        $plugin = Mockery::mock(WoocommerceMercadoPago::class);
        $plugin->storeConfig = Mockery::mock(Store::class, ['getStoreCategory' => 'others']);
        $GLOBALS['mercadopago'] = $plugin;
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $this->assertSame('', (new SmartPixOrderDetails())->build($order, '4.00')['ip_address']);
    }

    /**
     * @dataProvider nonIntegerQuantityProvider
     * @param mixed $quantity
     */
    public function testNonIntegerQuantityIsAcceptedInTheDescriptionOfTheConsolidatedItem($quantity, string $label): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['quantity' => $quantity, 'line_total' => '4.00']);

        $details = (new SmartPixOrderDetails())->build($order, '4.00');

        $this->assertSame('Synthetic product x ' . $label, $details['description']);
        $this->assertSame([
            ['id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '4.00', 'quantity' => 1, 'type' => 'others'],
        ], $details['items']);
    }

    public static function nonIntegerQuantityProvider(): array
    {
        return [
            'whole float quantity' => [2.0, '2'],
            'fractional quantity' => [1.5, '1.5'],
            'fraction below one' => [0.5, '0.5'],
            'numeric string quantity' => ['2', '2'],
            'fractional numeric string quantity' => ['1.5', '1.5'],
        ];
    }

    /**
     * @dataProvider lastNameFallbackProvider
     * @param array<string, mixed> $changes
     */
    public function testLastNameFollowsTheBillingFallbackOfTheOtherGateways(array $changes, string $firstName, string $lastName): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, $changes);

        $payer = (new SmartPixOrderDetails())->build($order, '4.00')['payer'];

        $this->assertSame($firstName, $payer['first_name']);
        $this->assertSame($lastName, $payer['last_name']);
    }

    public static function lastNameFallbackProvider(): array
    {
        return [
            // A single-word first name leaves no last name to derive.
            'empty last name' => [['last_name' => ''], 'Synthetic', ''],
            'blank last name' => [['last_name' => '   '], 'Synthetic', ''],
            'derived from the first name' => [['first_name' => 'Synthetic Second Buyer', 'last_name' => ''], 'Synthetic Second Buyer', 'Second'],
        ];
    }

    /** @dataProvider nonProductItemProvider */
    public function testNonProductOrderItemIsLeftOutOfTheDescription(string $class): void
    {
        $item = Mockery::mock($class);
        $item->shouldReceive('get_name')->andReturn('Synthetic extra');
        $item->shouldReceive('get_quantity')->andReturn(1);
        $item->shouldReceive('get_total')->andReturn('4.00');
        $item->shouldReceive('get_total_tax')->andReturn('0');
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['items' => [$item]]);

        $this->assertSame('Consolidated Items', (new SmartPixOrderDetails())->build($order, '4.00')['description']);
    }

    public static function nonProductItemProvider(): array
    {
        return [[WC_Order_Item_Fee::class], [WC_Order_Item_Shipping::class]];
    }

    public function testIndivisibleLineTotalKeepsTheExactOrderAmountAsOneConsolidatedItem(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['quantity' => 3, 'line_total' => '10.00']);

        $details = (new SmartPixOrderDetails())->build($order, '10.00');

        $this->assertSame('Synthetic product x 3', $details['description']);
        $this->assertSame([
            ['id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '10.00', 'quantity' => 1, 'type' => 'others'],
        ], $details['items']);
    }

    /**
     * @dataProvider nonReconcilableComponentProvider
     * @param array<string, mixed> $changes
     */
    public function testInexactComponentSendsOneConsolidatedItemWithTheAuthoritativeTotal(array $changes, string $amount): void
    {
        if (isset($changes['fees'])) {
            $changes['fees'] = array_map(function (array $fee): WC_Order_Item_Fee {
                return $this->fee(...$fee);
            }, $changes['fees']);
        }
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, $changes);

        $details = (new SmartPixOrderDetails())->build($order, $amount);

        $this->assertSame([[
            'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => $amount, 'quantity' => 1, 'type' => 'others',
        ]], $details['items']);
    }

    public static function nonReconcilableComponentProvider(): array
    {
        // Fees are described as [name, total, tax] and built in the test.
        return [
            'subcent line total' => [['line_total' => '3.995', 'line_tax' => '0.005'], '4.00'],
            'float line total' => [['line_total' => 4.0], '4.00'],
            'negative line total' => [['line_total' => '-4.00'], '4.00'],
            'subcent line tax' => [['line_tax' => '0.001'], '4.00'],
            'subcent shipping' => [['shipping_total' => '1.005', 'shipping_method' => 'Synthetic shipping'], '5.00'],
            'float shipping tax' => [['shipping_total' => '1.00', 'shipping_tax' => 0.5], '5.50'],
            'subcent fee' => [['fees' => [['Synthetic fee', '0.015', '']]], '4.02'],
            'float fee' => [['fees' => [['Synthetic fee', 1.5, '']]], '5.50'],
            'negative fee' => [['fees' => [['Synthetic discount', '-1.00', '']]], '3.00'],
            'fee without name' => [['fees' => [['   ', '1.00', '']]], '5.00'],
            'items above the total' => [[], '3.99'],
        ];
    }

    public function testShippingWithoutMethodNameIsIncludedInTheConsolidatedTotal(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, ['shipping_total' => '1.00', 'shipping_tax' => '', 'shipping_method' => '   ']);

        $details = (new SmartPixOrderDetails())->build($order, '5.00');

        $this->assertSame([[
            'id' => '321', 'title' => 'Consolidated Items', 'unit_price' => '5.00', 'quantity' => 1, 'type' => 'others',
        ]], $details['items']);
    }

    /** @dataProvider unusableCategoryProvider */
    public function testUnusableConfiguredCategoryFallsBackToOthers(string $category): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreCategory')->with('others')->andReturn($category);
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $details = (new SmartPixOrderDetails(null, $store))->build($order, '4.00');

        $this->assertSame(['others'], array_column($details['items'], 'type'));
    }

    public static function unusableCategoryProvider(): array
    {
        return [[''], ['   '], ["\t\n"]];
    }

    public function testConfiguredCategoryIsTrimmed(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreCategory')->with('others')->andReturn('  electronics  ');
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $details = (new SmartPixOrderDetails(null, $store))->build($order, '4.00');

        $this->assertSame(['electronics'], array_column($details['items'], 'type'));
    }

    public function testPluginStoreCategoryIsUsedWithoutAnInjectedStore(): void
    {
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreCategory')->with('others')->andReturn('books');
        $plugin = Mockery::mock(WoocommerceMercadoPago::class);
        $plugin->storeConfig = $store;
        $GLOBALS['mercadopago'] = $plugin;
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $details = (new SmartPixOrderDetails())->build($order, '4.00');

        $this->assertSame(['books'], array_column($details['items'], 'type'));
    }

    /** @dataProvider foreignGlobalProvider */
    public function testForeignPluginGlobalFallsBackToOthers($global): void
    {
        $GLOBALS['mercadopago'] = $global;
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $details = (new SmartPixOrderDetails())->build($order, '4.00');

        $this->assertSame(['others'], array_column($details['items'], 'type'));
    }

    public static function foreignGlobalProvider(): array
    {
        $foreign = new \stdClass();
        $foreign->storeConfig = null;
        return [[null], ['mercadopago'], [$foreign]];
    }

    public function testOversizedCategoryIsPassedThroughForTheClientToRefuse(): void
    {
        $category = str_repeat('c', SmartPixOrderDetails::MAX_ITEM_TYPE_LENGTH + 1);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getStoreCategory')->with('others')->andReturn($category);
        $order = Mockery::mock(WC_Order::class);
        $this->stubOrderDetailsWith($order, []);

        $details = (new SmartPixOrderDetails(null, $store))->build($order, '4.00');

        $this->assertSame([$category], array_column($details['items'], 'type'));
    }

    /**
     * @param mixed $total
     * @param mixed $tax
     * @return WC_Order_Item_Fee&MockInterface
     */
    private function fee(string $name, $total, $tax): WC_Order_Item_Fee
    {
        $fee = Mockery::mock(WC_Order_Item_Fee::class);
        $fee->shouldReceive('get_name')->andReturn($name);
        $fee->shouldReceive('get_total')->andReturn($total);
        $fee->shouldReceive('get_total_tax')->andReturn($tax);

        return $fee;
    }

    /** @param mixed $requestAddress */
    private function withServerAddress(string $serverAddress): SmartPixOrderDetails
    {
        return new SmartPixOrderDetails(null, null, static function () use ($serverAddress): string {
            return $serverAddress;
        });
    }

    /** @return WC_Order&MockInterface */
    private function order(): WC_Order
    {
        $line = Mockery::mock(WC_Order_Item_Product::class);
        $line->shouldReceive('get_name')->andReturn('Synthetic shirt');
        $line->shouldReceive('get_quantity')->andReturn(2);
        $line->shouldReceive('get_product_id')->andReturn(42);
        // Discounted line total plus tax, as WooCommerce stores them.
        $line->shouldReceive('get_total')->andReturn('18.000000');
        $line->shouldReceive('get_total_tax')->andReturn('2.00');
        $fee = Mockery::mock(WC_Order_Item_Fee::class);
        $fee->shouldReceive('get_name')->andReturn('Synthetic fee');
        $fee->shouldReceive('get_total')->andReturn('1.50');
        $fee->shouldReceive('get_total_tax')->andReturn('');
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_items')->andReturn([$line]);
        $order->shouldReceive('get_shipping_total')->andReturn('5.00');
        $order->shouldReceive('get_shipping_tax')->andReturn('0.50');
        $order->shouldReceive('get_shipping_method')->andReturn('Synthetic shipping');
        $order->shouldReceive('get_fees')->andReturn([$fee]);
        $order->shouldReceive('get_billing_email')->andReturn('buyer@example.test');
        $order->shouldReceive('get_billing_first_name')->andReturn('Synthetic');
        $order->shouldReceive('get_billing_last_name')->andReturn('Buyer');
        $order->shouldReceive('get_customer_ip_address')->andReturn('203.0.113.10');
        $order->shouldReceive('get_meta')->with('_billing_cpf', true)->andReturn(self::$syntheticMaskedCpf);

        return $order;
    }
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Http;

use Closure;
use InvalidArgumentException;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Order\OrderBilling;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WC_Order;
use WC_Order_Item_Product;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixCpf;

if (!defined('ABSPATH')) {
    // @codeCoverageIgnoreStart
    exit;
    // @codeCoverageIgnoreEnd
}

/**
 * Builds the buyer and cart facts of an Orders creation from the trusted WooCommerce order.
 * The result contains personal data (payer and CPF): never log, persist or expose it.
 */
final class SmartPixOrderDetails
{
    /** Store category sent by the other payment methods when none is configured. */
    public const DEFAULT_ITEM_TYPE = 'others';

    public const MAX_ITEM_TYPE_LENGTH = 256;

    public const CONSOLIDATED_ITEMS = 'Consolidated Items';

    private OrderBilling $billing;

    private ?Store $store;

    private Closure $serverAddress;

    /**
     * @param Store|null    $store         Defaults to the plugin store configuration, like the other payment methods.
     * @param callable|null $serverAddress Defaults to the address the other payment methods send.
     */
    public function __construct(?OrderBilling $billing = null, ?Store $store = null, ?callable $serverAddress = null)
    {
        $this->billing       = $billing ?? new OrderBilling();
        $this->store         = $store;
        $this->serverAddress = $serverAddress === null
            ? static function (): string {
                $plugin = $GLOBALS['mercadopago'] ?? null;
                return $plugin instanceof WoocommerceMercadoPago && isset($plugin->helpers->url)
                    ? $plugin->helpers->url->getServerAddress() : '';
            }
            : Closure::fromCallable($serverAddress);
    }

    /**
     * @param string $amount Authoritative canonical order total; items never change it.
     * @return array<string, mixed>
     * @throws InvalidArgumentException When the buyer e-mail is invalid.
     */
    public function build(WC_Order $order, string $amount): array
    {
        return [
            'description' => $this->productDescription($order),
            'ip_address'  => (string) ($this->serverAddress)(),
            'payer'       => $this->payer($order),
            // Use the validated server total without allocating discounts or rounding item prices.
            'items'       => [[
                'id'         => (string) $order->get_id(),
                'title'      => self::CONSOLIDATED_ITEMS,
                'unit_price' => $amount,
                'quantity'   => 1,
                'type'       => $this->itemType(),
            ]],
        ];
    }

    /**
     * The consolidated item carries the same store category as the other payment methods.
     */
    private function itemType(): string
    {
        $store = $this->store;
        if ($store === null) {
            $plugin = $GLOBALS['mercadopago'] ?? null;
            $store  = $plugin instanceof WoocommerceMercadoPago ? $plugin->storeConfig : null;
        }
        $category = $store === null ? self::DEFAULT_ITEM_TYPE : trim($store->getStoreCategory(self::DEFAULT_ITEM_TYPE));

        return $category === '' ? self::DEFAULT_ITEM_TYPE : $category;
    }

    /** @return array<string, mixed> */
    private function payer(WC_Order $order): array
    {
        $email = $this->billing->getEmail($order);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid Smart Pix payer.');
        }
        // Like the other gateways, an underivable name is sent empty.
        $firstName = trim($this->billing->getFirstName($order));
        $lastName  = trim($this->billing->getLastName($order));

        $payer = [
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ];
        // Reuse a valid billing CPF when available; Smart Transfer collects no document.
        $documentNumber = SmartPixCpf::normalize($order->get_meta('_billing_cpf', true));
        if ($documentNumber !== null) {
            $payer['identification'] = ['type' => SmartPixCpf::TYPE, 'number' => $documentNumber];
        }

        return $payer;
    }

    /** Same "name x quantity" list as the other payment methods; the cart never blocks the payment. */
    private function productDescription(WC_Order $order): string
    {
        $lines = [];
        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $lines[] = trim((string) $item->get_name()) . ' x ' . $item->get_quantity();
            }
        }

        return $lines === [] ? self::CONSOLIDATED_ITEMS : implode(', ', $lines);
    }
}

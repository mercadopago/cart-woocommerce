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
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;

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

    private OrderBilling $billing;

    private ?Store $store;

    private Closure $requestIpAddress;

    /**
     * @param Store|null    $store            Defaults to the plugin store configuration, like the other payment methods.
     * @param callable|null $requestIpAddress Returns the address of the current request. Defaults to the
     *                                        WooCommerce geolocation address, the browser that is paying now.
     */
    public function __construct(?OrderBilling $billing = null, ?Store $store = null, ?callable $requestIpAddress = null)
    {
        $this->billing          = $billing ?? new OrderBilling();
        $this->store            = $store;
        $this->requestIpAddress = $requestIpAddress === null
            ? static function (): string {
                return class_exists('\WC_Geolocation') ? (string) \WC_Geolocation::get_ip_address() : '';
            }
            : Closure::fromCallable($requestIpAddress);
    }

    /**
     * @param string $amount Authoritative canonical order total; items never change it.
     * @return array<string, mixed>
     * @throws InvalidArgumentException When a mandatory buyer or cart fact is missing.
     */
    public function build(WC_Order $order, string $amount): array
    {
        return [
            'description' => $this->productDescription($order),
            'ip_address'  => $this->ipAddress($order),
            'payer'       => $this->payer($order),
            // Use the validated server total without allocating discounts or rounding item prices.
            'items'       => [[
                'id'         => (string) $order->get_id(),
                'title'      => 'Consolidated Items',
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
        $email     = $this->billing->getEmail($order);
        $firstName = trim($this->billing->getFirstName($order));
        // Like the other gateways, an underivable last name is sent empty.
        $lastName = trim($this->billing->getLastName($order));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $firstName === '') {
            throw new InvalidArgumentException('Invalid Smart Pix payer.');
        }

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

    /**
     * The buyer paying now may differ from the one stored on the order (an order created in the
     * admin and paid on the order-pay page), so the current request address wins. The stored
     * order address is the fallback; the server address is never sent.
     */
    private function ipAddress(WC_Order $order): string
    {
        $ipAddress = $this->validIpAddress(($this->requestIpAddress)())
            ?? $this->validIpAddress($order->get_customer_ip_address());
        if ($ipAddress === null) {
            throw new InvalidArgumentException('Invalid Smart Pix buyer address.');
        }

        return $ipAddress;
    }

    /**
     * Request and stored order data are not trusted to match the stub types.
     * @param mixed $ipAddress
     */
    private function validIpAddress($ipAddress): ?string
    {
        if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ipAddress;
    }

    /**
     * Any positive finite quantity is accepted, including decimal quantities from quantity plugins.
     * @param mixed $quantity
     */
    private function isQuantity($quantity): bool
    {
        if (!is_int($quantity) && !is_float($quantity) && !is_string($quantity)) {
            return false;
        }
        if (!is_numeric($quantity)) {
            return false;
        }
        $value = (float) $quantity;

        return is_finite($value) && $value > 0 && $value <= SmartPixDecimal::MAX_FACTOR;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function productDescription(WC_Order $order): string
    {
        $lines = [];
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                throw new InvalidArgumentException('Invalid Smart Pix order items.');
            }
            $name     = trim((string) $item->get_name());
            $quantity = $item->get_quantity();
            if ($name === '' || !$this->isQuantity($quantity)) {
                throw new InvalidArgumentException('Invalid Smart Pix order items.');
            }
            $lines[] = $name . ' x ' . $quantity;
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Invalid Smart Pix order items.');
        }

        return implode(', ', $lines);
    }
}

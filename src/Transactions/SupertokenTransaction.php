<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\PP\Sdk\Entity\Payment\Item;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Numbers;
use WC_Order;

class SupertokenTransaction extends AbstractPaymentTransaction
{
    public const ID = 'super_token';

    private string $superToken;

    private string $paymentTypeId;

    /**
     * Custom Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param WC_Order $order
     * @param array $checkout
     *
     * @throws Exception
     */
    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        // validate payment method id and payment type id
        if (empty($this->checkout['payment_method_id']) || empty($this->checkout['payment_type_id'])) {
            throw new Exception('Payment method id and payment type id are required');
        }

        $this->transaction->payment_method_id = $this->checkout['payment_method_id'];
        if (
            $this->checkout['payment_type_id'] === 'credit_card' ||
             $this->checkout['payment_type_id'] === 'digital_currency'
        ) {
            $this->transaction->installments = (int) $this->checkout['installments'];
        }

        $this->superToken = $this->checkout['token'];
        $this->paymentTypeId = $this->checkout['payment_type_id'];
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout = 'custom';
        $internalMetadata->checkout_type = self::ID;
    }

    /**
     * Create Payment
     *
     * @return array
     * @throws Exception
     */
    public function createPayment()
    {
        $this->updateTransactionItems();

        $data = $this->transaction->saveWithSuperToken($this->superToken, $this->paymentTypeId);
        $this->mercadopago->logs->file->info('Payment created', $this->gateway::LOG_SOURCE, $data);
        return $data;
    }

    /**
     * Update transaction items
     *
     * @return void
     */
    public function updateTransactionItems()
    {
        $currentItems = $this->transaction->additional_info->items->collection;
        $newItems = $this->consolidateItems($currentItems, $this->order->get_id());

        $this->transaction->additional_info->items->collection = $newItems;
    }

    /**
     * Consolidate items into a single item by combining values
     *
     * @description This method was created specifically to handle items with negative values.
     * To avoid future issues, we're consolidating all items into a single item.
     *
     * @param Item[] $items
     * @param int $orderId
     * @return Item[]
     */
    private function consolidateItems(array $items, int $orderId): array
    {
        if (empty($items)) {
            return [];
        }

        $totalValue = 0;

        foreach ($items as $item) {
            $unitPrice = Numbers::makesValueSafe($item->unit_price);
            $quantity = (int) $item->quantity;

            $totalValue += $unitPrice * $quantity;
        }

        $consolidatedItem = [
            'id' => $orderId,
            'title' => "Consolidated Items",
            'description' => "Consolidated Items",
            'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
            'quantity' => 1,
            'unit_price' => Numbers::format($totalValue),
        ];

        return [$consolidatedItem];
    }
}

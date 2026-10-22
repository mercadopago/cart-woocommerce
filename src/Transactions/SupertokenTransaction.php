<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\PaymentsClient;
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

        $this->transaction['payment_method_id'] = $this->checkout['payment_method_id'];
        if (
            $this->checkout['payment_type_id'] === 'credit_card' ||
             $this->checkout['payment_type_id'] === 'digital_currency'
        ) {
            $this->transaction['installments'] = (int) $this->checkout['installments'];
        }

        $this->superToken = $this->checkout['authorized_pseudotoken'];
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

        $apiRoute = PaymentsClient::CREATE_PATH;

        try {
            $data = $this->mercadopago->coreApi->payments
                ->create(
                    $this->transaction,
                    $this->getCoreRequestContext(),
                    null,
                    $this->superToken,
                    $this->paymentTypeId
                )
                ->toArray();
            $this->mercadopago->logs->file->info('Payment created', $this->gateway::LOG_SOURCE, [
                'id' => isset($data['id']) ? (string) $data['id'] : '',
                'status' => (string) ($data['status'] ?? ''),
                'status_detail' => (string) ($data['status_detail'] ?? ''),
                'payment_method_id' => (string) ($data['payment_method_id'] ?? ''),
                'payment_type_id' => (string) ($data['payment_type_id'] ?? ''),
            ]);
            $this->sendPaymentCreateResultMetric($apiRoute, null, $data);
            return $data;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($apiRoute, $e);
            $this->sendPaymentCreateResultMetric($apiRoute, $e);
            throw $e;
        }
    }

    /**
     * Update transaction items
     *
     * @return void
     */
    public function updateTransactionItems()
    {
        $currentItems = $this->transaction['additional_info']['items'];
        $newItems = $this->consolidateItems($currentItems, $this->order->get_id());

        $this->transaction['additional_info']['items'] = $newItems;
    }

    /**
     * Consolidate items into a single item by combining values
     *
     * @description This method was created specifically to handle items with negative values.
     * To avoid future issues, we're consolidating all items into a single item.
     *
     * @param array[] $items
     * @param int $orderId
     * @return array[]
     */
    private function consolidateItems(array $items, int $orderId): array
    {
        if (empty($items)) {
            return [];
        }

        $totalValue = 0;

        foreach ($items as $item) {
            $unitPrice = Numbers::makesValueSafe($item['unit_price']);
            $quantity = (int) $item['quantity'];

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

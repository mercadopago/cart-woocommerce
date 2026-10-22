<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\PaymentsClient;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Numbers;
use WC_Order;

abstract class AbstractPaymentTransaction extends AbstractTransaction
{
    /**
     * Payment Transaction constructor
     * @throws Exception
     */
    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transactionType = 'Payment';
        $this->transaction = [
            'payer' => [
                'address' => [],
                'identification' => [],
                'phone' => [],
            ],
            'additional_info' => [
                'items' => [],
                'shipments' => ['receiver_address' => []],
                'payer' => [
                    'phone' => [],
                    'mobile' => [],
                    'address' => [],
                    'identification' => [],
                ],
                'seller' => [],
            ],
            'transaction_details' => [],
            'point_of_interaction' => ['transaction_data' => []],
        ];

        $this->setCommonTransaction();
        $this->setPayerTransaction();
        $this->setAdditionalInfoTransaction();

        $this->transaction['description']        = implode(', ', $this->listOfItems);
        $this->transaction['transaction_amount'] = Numbers::format($this->orderTotal);
    }

    /**
     * Create Payment
     *
     * @return array
     * @throws Exception
     */
    public function createPayment()
    {
        $this->logTransactionPayload();
        $sessionId = !empty($this->checkout['session_id']) ? (string) $this->checkout['session_id'] : null;
        $apiRoute = PaymentsClient::CREATE_PATH;

        try {
            $data = $this->mercadopago->coreApi->payments
                ->create($this->transaction, $this->getCoreRequestContext(), $sessionId)
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
     * Set payer transaction
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        $this->transaction['payer']['email'] = $this->mercadopago->orderBilling->getEmail($this->order);
        $this->transaction['payer']['first_name'] = $this->mercadopago->orderBilling->getFirstName($this->order);
        $this->transaction['payer']['last_name'] = $this->mercadopago->orderBilling->getLastName($this->order);

        $this->setPayerAddressInfo();
    }

    private function setPayerAddressInfo(): void
    {
        $this->transaction['payer']['address']['city'] = $this->mercadopago->orderBilling->getCity($this->order);
        $this->transaction['payer']['address']['federal_unit'] = $this->mercadopago->orderBilling->getState($this->order);
        $this->transaction['payer']['address']['zip_code'] = $this->mercadopago->orderBilling->getZipcode($this->order);
        $this->transaction['payer']['address']['street_name'] = $this->mercadopago->orderBilling->getFullAddress($this->order);
    }
}

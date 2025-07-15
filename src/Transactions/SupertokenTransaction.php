<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
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

        $this->transaction->payment_method_id = $this->checkout['payment_method_id'];
        if ($this->checkout['payment_type_id'] === 'credit_card') {
            $this->transaction->installments = (int) $this->checkout['installments'];
        }

        $this->superToken = $this->checkout['token'];
        $this->paymentTypeId = $this->checkout['payment_type_id'];
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout = 'custom';
        $internalMetadata->checkout_type = self::ID;

        return $internalMetadata;
    }

    /**
     * Create Payment
     *
     * @return array
     * @throws Exception
     */
    public function createPayment()
    {
        $data = $this->transaction->saveWithSuperToken($this->superToken, $this->paymentTypeId);
        $this->mercadopago->logs->file->info('Payment created', $this->gateway::LOG_SOURCE, $data);
        return $data;
    }
}

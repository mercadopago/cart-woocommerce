<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class CustomTransaction extends AbstractPaymentTransaction
{
    public const ID = 'credit_card';

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

        $this->transaction->payment_method_id   = $this->checkout['payment_method_id'];
        $this->transaction->installments        = (int) $this->checkout['installments'];
        $this->transaction->three_d_secure_mode = 'optional';

        $this->setTokenTransaction();
        $this->setPayerIdentificationInfo();
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;

        return $internalMetadata;
    }

    /**
     * Set token transaction
     *
     * @return void
     */
    public function setTokenTransaction(): void
    {
        if (array_key_exists('token', $this->checkout)) {
            $this->transaction->token = $this->checkout['token'];

            if (isset($this->checkout['customer_id'])) {
                $this->transaction->payer->id = $this->checkout['customer_id'];
            }

            if (isset($this->checkout['issuer'])) {
                $this->transaction->issuer_id = $this->checkout['issuer'];
            }
        }
    }

    /**
     * Set payer identification info
     * Implementation similar to TicketTransaction
     *
     * @return void
     */
    private function setPayerIdentificationInfo(): void
    {
        if (!empty($this->checkout['doc_type']) && !empty($this->checkout['doc_number'])) {
            $this->transaction->payer->identification->type   = $this->checkout['doc_type'];
            $this->transaction->payer->identification->number = $this->checkout['doc_number'];
        }
    }
}

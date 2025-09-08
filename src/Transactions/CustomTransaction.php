<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Arrays;
use WC_Order;

class CustomTransaction extends AbstractPaymentTransaction
{
    public const ID = 'credit_card';

    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->payment_method_id   = $this->checkout['payment_method_id'];
        $this->transaction->installments        = (int) $this->checkout['installments'];
        $this->transaction->three_d_secure_mode = 'optional';

        $this->setTokenTransaction();
        $this->setPayerIdentificationInfo();
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;
    }

    public function setTokenTransaction(): void
    {
        if (!empty($this->checkout['token'])) {
            $this->transaction->token = $this->checkout['token'];

            if (isset($this->checkout['customer_id'])) {
                $this->transaction->payer->id = $this->checkout['customer_id'];
            }

            if (isset($this->checkout['issuer'])) {
                $this->transaction->issuer_id = $this->checkout['issuer'];
            }
        }
    }

    private function setPayerIdentificationInfo(): void
    {
        if (
            !Arrays::anyEmpty($this->checkout, [
                'doc_type',
                'doc_number'
            ])
        ) {
            $this->transaction->payer->identification->type   = $this->checkout['doc_type'];
            $this->transaction->payer->identification->number = $this->checkout['doc_number'];
        }
    }
}

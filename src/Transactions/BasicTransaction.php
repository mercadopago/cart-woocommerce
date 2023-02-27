<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\BasicGateway;

class BasicTransaction extends AbstractPreferenceTransaction
{
    /**
     * @const
     */
    public const ID = 'basic';

    /**
     * Basic Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order)
    {
        parent::__construct($gateway, $order);

        $this->setPaymentMethodsTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']       = 'smart';
        $internalMetadata['checkout_type']  =
            $this->mercadopago->options->getMercadoPago($this->gateway, 'method', 'redirect');
        $internalMetadata['basic_settings'] = $this->mercadopago->analytics->getGatewaySettings($this->gateway::ID);

        return $internalMetadata;
    }

    /**
     * Set payment methods
     */
    public function setPaymentMethodsTransaction()
    {
        $this->setInstallmentsTransaction();
        $this->setExcludedPaymentMethodsTransaction();
    }

    /**
     * Set installments
     */
    public function setInstallmentsTransaction()
    {
        $installments = (int) $this->mercadopago->options->getMercadoPago($this->gateway, 'installments', '24');

        $this->transaction->payment_methods->installments = (0 === $installments) ? 12 : $installments;
    }

    /**
     * Set excluded payment methods
     */
    public function setExcludedPaymentMethodsTransaction()
    {
        $exPayments = $this->mercadopago->seller->getExPayments($this->gateway);

        if (count($exPayments) !== 0) {
            foreach ($exPayments as $excluded) {
                $entity = [
                    'id' => $excluded,
                ];

                $this->transaction->payment_methods->excluded_payment_methods->add($entity);
            }
        }
    }
}

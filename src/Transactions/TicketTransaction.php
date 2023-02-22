<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

class TicketTransaction extends AbstractPaymentTransaction
{
    /**
     * @const
     */
    public const ID = 'ticket';

    /**
     * Payment method id
     *
     * @var string
     */
    private $paymentMethodId;

    /**
     * Payment place id
     *
     * @var string
     */
    private $paymentPlaceId;

    /**
     * Ticket Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order, $checkout)
    {
        parent::constructor($gateway, $order, $checkout);

        $this->paymentMethodId = $this->checkout['paymentMethodId'];
        $this->paymentPlaceId  = $this->mercadopago->compositeId->getPaymentPlaceId($this->paymentMethodId);
        $this->paymentMethodId = $this->mercadopago->compositeId->getPaymentMethodId($this->paymentMethodId);

        $this->transaction->payment_method_id  = $this->paymentMethodId;
        $this->transaction->external_reference = $this->getExternalReference();
        $this->transaction->date_of_expiration = Date::format($this->getExpirationDate());

        $this->setWebpayPropertiesTransaction();
        $this->setPayerTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata['checkout']         = 'custom';
        $internalMetadata['checkout_type']    = self::ID;
        $internalMetadata['ticket_settings']  = $this->mercadopago->analytics->getGatewaySettings($this->gateway::ID);

        if ($this->paymentPlaceId) {
            $internalMetadata['payment_option_id'] = $this->paymentPlaceId;
        }

        return $internalMetadata;
    }

    /**
     * Set webpay properties transaction
     */
    public function setWebpayPropertiesTransaction()
    {
        if ('webpay' === $this->checkout['paymentMethodId']) {
            $this->transaction->transaction_details->financial_institution = '1234';
            $this->transaction->callback_url                               = get_site_url();
            $this->transaction->additional_info->ip_address                = '127.0.0.1';
            $this->transaction->payer->identification->type                = 'RUT';
            $this->transaction->payer->identification->number              = '0';
            $this->transaction->payer->entity_type                         = 'individual';
        }
    }

    /**
     * Get expiration date
     */
    public function getExpirationDate(): string
    {
        return $this->mercadopago->options->getMercadoPago(
            $this->gateway,
            'date_expiration',
            MP_TICKET_DATE_EXPIRATION
        );
    }

    /**
     * Set payer transaction
     */
    public function setPayerTransaction()
    {
        parent::setPayerTransaction();

        $payer         = $this->transaction->payer;

        if ('BRL' === $this->countryConfig['currency']) {
            $payer->identification->type   = 14 === strlen($this->checkout['docNumber']) ? 'CPF' : 'CNPJ';
            $payer->identification->number = $this->checkout['docNumber'];
        }

        if ('UYU' === $this->countryConfig['currency']) {
            $payer->identification->type   = $this->checkout['docType'];
            $payer->identification->number = $this->checkout['docNumber'];
        }
    }
}

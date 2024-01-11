<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;

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
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param array $checkout
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->paymentMethodId = $this->checkout['payment_method_id'];
        $this->paymentPlaceId  = $this->mercadopago->helpers->paymentMethods->getPaymentPlaceId($this->paymentMethodId);
        $this->paymentMethodId = $this->mercadopago->helpers->paymentMethods->getPaymentMethodId($this->paymentMethodId);

        $this->transaction->installments = 1;
        $this->transaction->payment_method_id  = $this->paymentMethodId;
        $this->transaction->external_reference = $this->getExternalReference();
        $this->transaction->date_of_expiration = $this->getExpirationDate();

        $this->setWebpayPropertiesTransaction();
        $this->setPayerTransaction();
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $internalMetadata = parent::getInternalMetadata();

        $internalMetadata->checkout        = 'custom';
        $internalMetadata->checkout_type   = self::ID;

        if (!empty($this->paymentPlaceId)) {
            $internalMetadata->payment_option_id = $this->paymentPlaceId;
        }

        return $internalMetadata;
    }

    /**
     * Set webpay properties transaction
     *
     * @return void
     */
    public function setWebpayPropertiesTransaction(): void
    {
        if ($this->checkout['payment_method_id'] === 'webpay') {
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
     *
     * @return string
     */
    public function getExpirationDate(): string
    {
        $expirationDate = $this->mercadopago->hooks->options->getGatewayOption(
            $this->gateway,
            'date_expiration',
            MP_TICKET_DATE_EXPIRATION
        );

        return Date::sumToNowDate($expirationDate . ' days');
    }

    /**
     * Set payer transaction
     *
     * @return void
     */
    public function setPayerTransaction(): void
    {
        parent::setPayerTransaction();

        $payer    = $this->transaction->payer;
        $currency = $this->countryConfigs['currency'];

        if ($currency === 'BRL' || $currency === 'UYU') {
            $payer->identification->type   = $this->checkout['doc_type'];
            $payer->identification->number = $this->checkout['doc_number'];
        }
    }
}

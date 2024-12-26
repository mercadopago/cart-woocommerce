<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class TicketTransaction extends AbstractPaymentTransaction
{
    public const ID = 'ticket';

    private string $paymentMethodId;

    private string $paymentPlaceId;

    /**
     * Ticket Transaction constructor
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

        $this->paymentMethodId = $this->checkout['payment_method_id'];
        $this->paymentPlaceId  = $this->mercadopago->helpers->paymentMethods->getPaymentPlaceId($this->paymentMethodId);
        if ($this->countryConfigs['site_id'] !== 'MPE') {
            $this->paymentMethodId = $this->mercadopago->helpers->paymentMethods->getPaymentMethodId($this->paymentMethodId);
        }


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

        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;

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

        if ($this->countryConfigs['site_id'] === 'MLB' || $this->countryConfigs['site_id'] === 'MLU') {
            $this->setPayerIdentificationInfo();
        }
        if ($this->countryConfigs['site_id'] === 'MLB') {
            $this->setPayerAddressInfoFromCheckout();
        }
    }

    private function setPayerIdentificationInfo(): void
    {
        $this->transaction->payer->identification->type   = $this->checkout['doc_type'];
        $this->transaction->payer->identification->number = $this->checkout['doc_number'];
    }

    private function setPayerAddressInfoFromCheckout(): void
    {
        $streetNumber = $this->checkout['address_street_number'] ?: 'S/N';

        $this->transaction->payer->address->city          = $this->transaction->additional_info->payer->address->city          = $this->checkout['address_city'];
        $this->transaction->payer->address->federal_unit  = $this->transaction->additional_info->payer->address->federal_unit  = $this->checkout['address_federal_unit'];
        $this->transaction->payer->address->zip_code      = $this->transaction->additional_info->payer->address->zip_code      = $this->checkout['address_zip_code'];
        $this->transaction->payer->address->street_name   = $this->transaction->additional_info->payer->address->street_name   = $this->checkout['address_street_name'];
        $this->transaction->payer->address->neighborhood  = $this->transaction->additional_info->payer->address->neighborhood  = $this->checkout['address_neighborhood'];
        $this->transaction->payer->address->street_number = $this->transaction->additional_info->payer->address->street_number = $streetNumber;
    }
}

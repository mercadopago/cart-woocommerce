<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Gateways\TicketGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

class TicketTransaction extends AbstractPaymentTransaction
{
    public const ID = 'ticket';

    /**
     * @var TicketGateway
     */
    public AbstractGateway $gateway;

    public function __construct(AbstractGateway $gateway, WC_Order $order, array $checkout)
    {
        parent::__construct($gateway, $order, $checkout);

        $this->transaction->installments       = 1;
        $this->transaction->date_of_expiration = Date::sumToNowDate($this->gateway->getCheckoutExpirationDate() . ' days');
        $this->transaction->payment_method_id  = $this->countryConfigs['site_id'] === 'MPE'
            ? $this->checkout['payment_method_id']
            : $this->mercadopago->helpers->paymentMethods->getPaymentMethodId($this->checkout['payment_method_id']);

        $this->setWebpayPropertiesTransaction();
        $this->updatePayerTransaction();
    }

    public function extendInternalMetadata(PaymentMetadata $internalMetadata): void
    {
        $internalMetadata->checkout      = 'custom';
        $internalMetadata->checkout_type = self::ID;
        $paymentPlaceId                  = $this->mercadopago->helpers->paymentMethods->getPaymentPlaceId($this->checkout['payment_method_id']);
        if (!empty($paymentPlaceId)) {
            $internalMetadata->payment_option_id = $paymentPlaceId;
        }
    }

    public function setWebpayPropertiesTransaction(): void
    {
        if ($this->checkout['payment_method_id'] !== 'webpay') {
            return;
        }

        $this->transaction->transaction_details->financial_institution = '1234';
        $this->transaction->callback_url                               = get_site_url();
        $this->transaction->additional_info->ip_address                = '127.0.0.1';
        $this->transaction->payer->identification->type                = 'RUT';
        $this->transaction->payer->identification->number              = '0';
        $this->transaction->payer->entity_type                         = 'individual';
    }

    public function updatePayerTransaction(): void
    {
        if (in_array($this->countryConfigs['site_id'], ['MLB', 'MLU'])) {
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
        foreach (
            [
            $this->transaction->payer->address,
            $this->transaction->additional_info->payer->address
            ] as $address
        ) {
            $address->city          = $this->checkout['address_city'];
            $address->federal_unit  = $this->checkout['address_federal_unit'];
            $address->zip_code      = $this->checkout['address_zip_code'];
            $address->street_name   = $this->checkout['address_street_name'];
            $address->neighborhood  = $this->checkout['address_neighborhood'];
            $address->street_number = $this->checkout['address_street_number'] ?: 'S/N';
        }
    }
}

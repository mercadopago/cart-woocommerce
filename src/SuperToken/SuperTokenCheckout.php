<?php

namespace MercadoPago\Woocommerce\SuperToken;

if (!defined('ABSPATH')) {
    exit;
}

class SuperTokenCheckout
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getAuthorizedPseudotoken(): ?string
    {
        return $this->data['authorized_pseudotoken'] ?? null;
    }

    public function getAmount(): ?string
    {
        return $this->data['amount'] ?? null;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->data['payment_method_id'] ?? null;
    }

    public function getPaymentTypeId(): ?string
    {
        return $this->data['payment_type_id'] ?? null;
    }

    /**
     * @return mixed
     */
    public function getInstallments()
    {
        return $this->data['installments'] ?? null;
    }

    public function getToken(): ?string
    {
        return $this->data['token'] ?? null;
    }

    /**
     * @return mixed
     */
    public function getSuperTokenValidation()
    {
        return $this->data['super_token_validation'] ?? false;
    }

    public function isCreditCard(): bool
    {
        return $this->getPaymentTypeId() === 'credit_card';
    }

    public function toArray(): array
    {
        $data = $this->data;
        $data['super_token_validation'] = $data['super_token_validation'] ?? false;

        return $data;
    }
}

<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

class PaymentV21 extends Payment
{
    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array
    {
        return array(
            'post' => '/ppcore/prod/transaction/v21/payments',
        );
    }
}

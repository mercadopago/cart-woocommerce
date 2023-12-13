<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

class PaymentV2 extends Payment
{
    /**
     * Get uris.
     *
     * @return array
     */
    public function getUris(): array
    {
        return array(
            'post' => '/v2/asgard/payments',
        );
    }
}

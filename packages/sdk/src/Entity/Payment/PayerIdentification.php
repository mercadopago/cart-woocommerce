<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PayerIdentification
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class PayerIdentification extends AbstractEntity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $number;
}

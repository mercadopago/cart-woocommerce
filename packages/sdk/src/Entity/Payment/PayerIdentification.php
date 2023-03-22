<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class PayerIdentification
 *
 * @property string $type
 * @property string $number
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

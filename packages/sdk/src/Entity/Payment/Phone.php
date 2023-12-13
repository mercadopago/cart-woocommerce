<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Phone
 *
 * @property string $number
 * @property string $area_code
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Phone extends AbstractEntity
{
    /**
     * @var string
     */
    protected $number;

    /**
     * @var string
     */
    protected $area_code;
}

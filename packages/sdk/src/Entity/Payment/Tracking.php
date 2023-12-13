<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Tracking
 *
 * @property string $code
 * @property string $status
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Tracking extends AbstractEntity
{
    /**
     * @var string
     */
    protected $code;

     /**
     * @var string
     */
    protected $status;
}

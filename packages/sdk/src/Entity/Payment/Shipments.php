<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Shipments
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Shipments extends AbstractEntity
{
    /**
     * @var ReceiverAddress
     */
    protected $receiver_address;

    public function __construct()
    {
        $this->receiver_address = new ReceiverAddress();
    }
}

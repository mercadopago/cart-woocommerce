<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Shipment
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Shipment extends AbstractEntity
{
    /**
     * @var float
     */
    protected $cost;

    /**
     * @var int
     */
    protected $default_shipping_method;

    /**
     * @var string
     */
    protected $dimensions;

    /**
     * @var FreeMethodList
     */
    protected $free_methods;

    /**
     * @var boolean
     */
    protected $free_shipping;

    /**
     * @var boolean
     */
    protected $local_pickup;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var ReceiverAddress
     */
    protected $receiver_address;

    public function __construct()
    {
        $this->free_methods = new FreeMethodList();
        $this->receiver_address = new ReceiverAddress();
    }
}

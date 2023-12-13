<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Shipment
 *
 * @property string $default_shipping_method
 * @property ReceiverAddress $receiver_address
 * @property float $cost
 * @property string $dimensions
 * @property FreeMethodList $free_methods
 * @property boolean $free_shipping
 * @property boolean $local_pickup
 * @property string $mode
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Shipment extends AbstractEntity
{
    /**
     * @var string
     */
    protected $default_shipping_method;

    /**
     * @var ReceiverAddress
     */
    protected $receiver_address;

    /**
     * @var float
     */
    protected $cost;

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
     * Shipment constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->free_methods = new FreeMethodList($manager);
        $this->receiver_address = new ReceiverAddress($manager);
    }
}

<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Shipment
 *
 * @property float $cost
 * @property int $default_shipping_method
 * @property string $dimensions
 * @property FreeMethodList $free_methods
 * @property boolean $free_shipping
 * @property boolean $local_pickup
 * @property string $mode
 * @property ReceiverAddress $receiver_address
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

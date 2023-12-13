<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class Shipments
 *
 * @property string $delivery_promise
 * @property string $drop_shipping
 * @property string $local_pickup
 * @property string $express_shipment
 * @property string $safety
 * @property boolean $withdrawn
 * @property Tracking $tracking
 * @property AdditionalInfoAddress $receiver_address
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Shipments extends AbstractEntity
{
    /**
     * @var string
     */
    protected $delivery_promise;

     /**
     * @var string
     */
    protected $drop_shipping;

     /**
     * @var string
     */
    protected $local_pickup;

     /**
     * @var string
     */
    protected $express_shipment;

    /**
     * @var string
     */
    protected $safety;

    /**
     * @var boolean
     */
    protected $withdrawn;

     /**
     * @var Tracking
     */
    protected $tracking;

     /**
     * @var AdditionalInfoAddress
     */
    protected $receiver_address;

    /**
     * Shipments constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->tracking = new Tracking($manager);
        $this->receiver_address = new AdditionalInfoAddress($manager);
    }
}

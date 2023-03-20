<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class AdditionalInfo
 *
 * @property string $ip_address
 * @property ItemList $items
 * @property AdditionalInfoPayer $payer
 * @property Shipments $shipments
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class AdditionalInfo extends AbstractEntity
{
    /**
     * @var string
     */
    protected $ip_address;

    /**
     * @var ItemList
     */
    protected $items;

    /**
     * @var AdditionalInfoPayer
     */
    protected $payer;

    /**
     * @var Shipments
     */
    protected $shipments;

    /**
     * AdditionalInfo constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct($manager)
    {
        parent::__construct($manager);
        $this->items     = new ItemList($manager);
        $this->payer     = new AdditionalInfoPayer($manager);
        $this->shipments = new Shipments($manager);
    }
}

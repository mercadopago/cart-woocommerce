<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class AdditionalInfo
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

    public function __construct()
    {
        $this->items     = new ItemList();
        $this->payer     = new AdditionalInfoPayer();
        $this->shipments = new Shipments();
    }
}

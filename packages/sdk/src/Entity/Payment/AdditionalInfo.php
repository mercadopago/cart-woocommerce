<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\Manager;

/**
 * Class AdditionalInfo
 *
 * @property string $ip_address
 * @property string $referral_url
 * @property boolean $drop_shipping
 * @property string $delivery_promise
 * @property string $contrated_plan
 * @property ItemList $items
 * @property AdditionalInfoPayer $payer
 * @property Seller $seller
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
     * @var string
     */
    protected $referral_url;

    /**
     * @var boolean
     */
    protected $drop_shipping;

    /**
     * @var string
     */
    protected $delivery_promise;

    /**
     * @var string
     */
    protected $contrated_plan;

    /**
     * @var ItemList
     */
    protected $items;

    /**
     * @var AdditionalInfoPayer
     */
    protected $payer;

    /**
     * @var Seller
     */
    protected $seller;

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
        $this->seller    = new Seller($manager);
        $this->shipments = new Shipments($manager);
    }
}

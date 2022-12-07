<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Item
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class Item extends AbstractEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $picture_url;

    /**
     * @var string
     */
    protected $category_id;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $unit_price;
}

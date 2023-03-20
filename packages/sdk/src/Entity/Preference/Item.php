<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Item
 *
 * @property string $category_id
 * @property string $currency_id
 * @property string $description
 * @property string $id
 * @property string $picture_url
 * @property int $quantity
 * @property string $title
 * @property float $unit_price
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class Item extends AbstractEntity
{
    /**
     * @var string
     */
    protected $category_id;

    /**
     * @var string
     */
    protected $currency_id;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $picture_url;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var float
     */
    protected $unit_price;
}

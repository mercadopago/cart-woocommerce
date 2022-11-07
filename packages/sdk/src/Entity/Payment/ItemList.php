<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class ItemList
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class ItemList extends AbstractCollection
{
    public function add($entity, $key = null)
    {
        $item = new Item();
        $item->setEntity($entity);
        parent::add($item, $key);
    }
}

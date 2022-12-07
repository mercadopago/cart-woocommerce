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
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $item = new Item($this->manager);
        $item->setEntity($entity);
        parent::addEntity($item, $key);
    }
}

<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class FreeMethodList
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class FreeMethodList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $freeMethod = new FreeMethod($this->manager);
        $freeMethod->setEntity($entity);
        parent::addEntity($freeMethod, $key);
    }
}

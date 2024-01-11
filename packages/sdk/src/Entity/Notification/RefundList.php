<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class RefundList
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class RefundList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $refund = new Refund($this->manager);
        $refund->setEntity($entity);
        parent::addEntity($refund, $key);
    }

    /**
     * Add multiple entities to collection
     *
     * @param $entities
     */
    public function setEntity($entities)
    {
        if (is_array($entities) || is_object($entities)) {
            foreach ($entities as $value) {
                $this->add($value, $value["id"]);
            }
        }
    }
}

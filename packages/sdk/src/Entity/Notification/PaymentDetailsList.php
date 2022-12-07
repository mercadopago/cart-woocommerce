<?php

namespace MercadoPago\PP\Sdk\Entity\Notification;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class PaymentDetailsList
 *
 * @package MercadoPago\PP\Sdk\Entity\Notification
 */
class PaymentDetailsList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $item = new PaymentDetails($this->manager);
        $item->setEntity($entity);
        parent::addEntity($item, $key);
    }
}

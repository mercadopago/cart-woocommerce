<?php

namespace MercadoPago\PP\Sdk\Entity\PaymentMethods;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class PaymentMethodsList
 *
 * @package MercadoPago\PP\Sdk\Entity\PaymentMethods
 */
class PaymentMethodsList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $item = new PaymentMethod($this->manager);
        $item->setEntity($entity);
        parent::addEntity($item, $key);
    }
}

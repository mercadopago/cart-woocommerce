<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class ExcludedPaymentMethodList
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class ExcludedPaymentMethodList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $excludedPaymentMethod = new ExcludedPaymentMethod($this->manager);
        $excludedPaymentMethod->setEntity($entity);
        parent::addEntity($excludedPaymentMethod, $key);
    }
}

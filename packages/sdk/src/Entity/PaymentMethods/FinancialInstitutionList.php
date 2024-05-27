<?php

namespace MercadoPago\PP\Sdk\Entity\PaymentMethods;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class FinancialInstitutionList
 *
 * @package MercadoPago\PP\Sdk\Entity\PaymentMethods
 */
class FinancialInstitutionList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $item = new FinancialInstitution($this->manager);
        $item->setEntity($entity);
        parent::addEntity($item, $key);
    }
}

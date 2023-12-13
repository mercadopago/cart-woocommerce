<?php

namespace MercadoPago\PP\Sdk\Entity\Payment;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class TransactionInfoList
 *
 * @package MercadoPago\PP\Sdk\Entity\Payment
 */
class TransactionInfoList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $transactionInfo = new TransactionInfo($this->manager);
        $transactionInfo->setEntity($entity);
        parent::addEntity($transactionInfo, $key);
    }
}

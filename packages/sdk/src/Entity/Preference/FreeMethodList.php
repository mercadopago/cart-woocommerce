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
    public function add($entity, $key = null)
    {
        $freeMethod = new FreeMethod();
        $freeMethod->setEntity($entity);
        parent::add($freeMethod, $key);
    }
}

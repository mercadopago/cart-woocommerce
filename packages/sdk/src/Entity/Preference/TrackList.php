<?php

namespace MercadoPago\PP\Sdk\Entity\Preference;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

/**
 * Class TrackList
 *
 * @package MercadoPago\PP\Sdk\Entity\Preference
 */
class TrackList extends AbstractCollection
{
    /**
     * Add entity to collection
     *
     * @param array $entity
     * @param string|null $key
     */
    public function add(array $entity, string $key = null)
    {
        $track = new Track($this->manager);
        $track->setEntity($entity);
        parent::addEntity($track, $key);
    }
}

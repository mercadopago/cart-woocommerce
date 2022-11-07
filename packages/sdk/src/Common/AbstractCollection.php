<?php

namespace MercadoPago\PP\Sdk\Common;

use Iterator;

/**
 * Class AbstractCollection
 *
 * @package MercadoPago\PP\Sdk\Common
 */
abstract class AbstractCollection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var array
     */
    public $collection = [];

    /**
     * Add entity to collection
     *
     * @param mixed $entity
     * @param string|null $key
     */
    public function add($entity, $key = null)
    {
        if (is_null($key)) {
            $this->collection[] = $entity;
        } else {
            $this->collection[$key] = $entity;
        }
    }

    /**
     * Add multiple entities to collection
     *
     * @param mixed $entity
     */
    public function setEntity($entityArray)
    {
        if (is_array($entityArray) || is_object($entityArray)) {
            foreach ($entityArray as $value) {
                $this->add($value);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Iterator
    {
        return new \ArrayIterator($this->collection);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->collection);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->collection;
    }
}

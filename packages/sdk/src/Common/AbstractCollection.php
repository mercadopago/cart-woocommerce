<?php

namespace MercadoPago\PP\Sdk\Common;

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
     * @var Manager
     */
    protected $manager;

    /**
     * AbstractCollection constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct(Manager $manager = null)
    {
        $this->manager = $manager;
    }

    /**
     * Add entity to collection
     *
     * @param AbstractEntity $entity
     * @param string|null $key
     */
    public function addEntity(AbstractEntity $entity, string $key = null)
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
     * @param $entities
     */
    public function setEntity($entities)
    {
        if (is_array($entities) || is_object($entities)) {
            foreach ($entities as $value) {
                $this->add($value);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->collection);
    }

    /**
     * @return int|null
     */
    public function count(): int
    {
        return count($this->collection);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->collection;
    }
}

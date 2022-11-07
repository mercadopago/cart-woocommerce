<?php

namespace MercadoPago\PP\Sdk\Common;

/**
 * Class AbstractEntity
 *
 * @package MercadoPago\PP\Sdk\Common
 */
abstract class AbstractEntity implements \JsonSerializable
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * AbstractEntity constructor.
     *
     * @param Manager $manager
     */
    public function __construct($manager = null)
    {
        $this->manager = $manager;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->{$name};
    }

    /**
     * @param string $name
     * @param        $value
     */
    public function __set($name, $value)
    {
        if (!property_exists($this, $name)) {
            return;
        }

        if (is_subclass_of($this->{$name}, AbstractEntity::class)
            || is_subclass_of($this->{$name}, AbstractCollection::class)) {
            $this->{$name}->setEntity($value);
        } else {
            $this->{$name} = $value;
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __isset($name)
    {
        return isset($this->{$name});
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->{$name});
    }


    /**
     * Set values for an entity's attributes.
     *
     * @param array $data
     */
    public function setEntity($data)
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $this->__set($key, $value);
            }
        }
    }

    /**
     * @codeCoverageIgnore
     * Get the properties of the given object.
     *
     * @return mixed
     */
    public function getProperties()
    {
        return get_object_vars($this);
    }

    /**
     * Get an array from an object.
     *
     * @return array
     */
    public function toArray()
    {
        $properties = $this->getProperties();

        $data = [];
        foreach ($properties as $property => $value) {
            if ($property === 'manager') {
                continue;
            }

            if ($value instanceof self) {
                $data[$property] = $value->toArray();
                continue;
            }

            if (($value instanceof \IteratorAggregate) || (is_array($value) && count($value))) {
                foreach ($value as $index => $item) {
                    if ($item instanceof self) {
                        $data[$property][$index] = $item->toArray();
                    } else {
                        $data[$property][$index] = $item;
                    }
                }
                continue;
            }

            $data[$property] = $this->$property;
        }

        return $data;
    }

    /**
     * Read method (GET).
     *
     * @param array $params
     *
     * @return mixed
     */
    public function read($params = [])
    {
        $method = 'get';
        $class = get_called_class();
        $entity = new $class($this->manager);

        $uri = $this->manager->getEntityUri($entity, $method, $params);
        $header = $this->manager->getHeader();
        $response = $this->manager->execute($entity, $uri, $method, $header);

        return $this->manager->handleResponse($response, $method, $entity);
    }

    /**
     * Save method (POST).
     *
     * @return mixed
     */
    public function save()
    {
        $method = 'post';

        $uri = $this->manager->getEntityUri($this, $method);
        $header = $this->manager->getHeader();
        $response = $this->manager->execute($this, $uri, $method, $header);

        return $this->manager->handleResponse($response, $method);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}

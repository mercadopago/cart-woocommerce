<?php

namespace MercadoPago\PP\Sdk\Common;

use MercadoPago\PP\Sdk\Interfaces\EntityInterface;
use MercadoPago\PP\Sdk\Sdk;

/**
 * Class AbstractEntity
 *
 * @package MercadoPago\PP\Sdk\Common
 */
abstract class AbstractEntity implements \JsonSerializable, EntityInterface
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var array
     */
    protected $excluded_properties;

    /**
     * AbstractEntity constructor.
     *
     * @param Manager|null $manager
     */
    public function __construct(Manager $manager = null)
    {
        $this->manager = $manager;
        $this->setExcludedProperties();
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->{$name};
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, $value)
    {
        if (!property_exists($this, $name)) {
            return;
        }

        if (is_subclass_of($this->{$name}, AbstractEntity::class) ||
            is_subclass_of($this->{$name}, AbstractCollection::class)
        ) {
            $this->{$name}->setEntity($value);
        } else {
            $this->{$name} = $value;
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset(string $name)
    {
        return isset($this->{$name});
    }

    /**
     * @param string $name
     */
    public function __unset(string $name)
    {
        unset($this->{$name});
    }


    /**
     * Set values for an entity's attributes.
     *
     * @param $data
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
     * @return array
     */
    public function getProperties(): array
    {
        return get_object_vars($this);
    }

    /**
     * Get an array from an object.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data                    = [];
        $properties              = $this->getProperties();
        $excludedPropertiesCount = count($this->excluded_properties);

        foreach ($properties as $property => $value) {
            if ($property === 'manager' || $property === 'excluded_properties') {
                continue;
            }

            if ($excludedPropertiesCount !== 0 && in_array($property, $this->excluded_properties)) {
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
     * @throws \Exception
     */
    public function read(array $params = [])
    {
        $method = 'get';
        $class  = get_called_class();
        $entity = new $class($this->manager);

        $customHeaders = $this->getHeaders()['read'];
        $header        = $this->manager->getHeader($customHeaders);

        $uri      = $this->manager->getEntityUri($entity, $method, $params);
        $response = $this->manager->execute($entity, $uri, $method, $header);
        $this->obfuscateAuthorizationHeader($header);
        return $this->manager->handleResponse($response, $method, $entity);
    }

    /**
     * Save method (POST).
     *
     * @return mixed
     * @throws \Exception
     */
    public function save()
    {
        $method = 'post';

        $customHeaders = $this->getHeaders()['save'];
        $header        = $this->manager->getHeader($customHeaders);

        $uri      = $this->manager->getEntityUri($this, $method);
        $response = $this->manager->execute($this, $uri, $method, $header);
        $this->obfuscateAuthorizationHeader($header);
        return $this->manager->handleResponse($response, $method);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties()
    {
        $this->excluded_properties = [];
    }

    /**
     * Obfuscate Authorization Header.
     *
     * @return void
     */
    public function obfuscateAuthorizationHeader(array $headers)
    {
        Sdk::$cache['last_headers'] = preg_replace('/(Authorization: Bearer) (.*)/i', '$1 xxx', $headers);
    }

    /**
     * Get last Headers.
     *
     * @return array
     */
    public function getLastHeaders(): array
    {
        if (isset(Sdk::$cache['last_headers'])) {
            return Sdk::$cache['last_headers'];
        }
        return [];
    }
}

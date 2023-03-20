<?php

namespace MercadoPago\PP\Sdk\Interfaces;

interface EntityInterface
{
    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name);

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, $value);

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset(string $name);

    /**
     * @param string $name
     */
    public function __unset(string $name);

    /**
     * Set values for an entity's attributes.
     *
     * @param $data
     */
    public function setEntity($data);

    /**
     * Get the properties of the given object.
     *
     * @return array
     */
    public function getProperties(): array;

    /**
     * Get an array from an object.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Exclude properties from entity building.
     *
     * @return void
     */
    public function setExcludedProperties();
}

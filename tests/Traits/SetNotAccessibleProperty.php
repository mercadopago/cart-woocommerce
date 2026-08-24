<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

trait SetNotAccessibleProperty
{
    private function setNotAccessibleProperty($object, string $property, $value)
    {
        $reflection = new \ReflectionClass($object);
        // Private properties of parent classes are not visible from child class reflection,
        // so traverse the hierarchy until the declaring class is found.
        while ($reflection !== false) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($object, $value);
                return;
            }
            $reflection = $reflection->getParentClass();
        }
    }
}

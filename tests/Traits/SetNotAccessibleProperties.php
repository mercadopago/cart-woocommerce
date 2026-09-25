<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

trait SetNotAccessibleProperties
{
    private function setNotAccessibleProperty($object, string $property, $value)
    {
        $reflection = new \ReflectionClass($object);
        $linksProperty = $reflection->getProperty($property);
        $linksProperty->setAccessible(true);
        $linksProperty->setValue($object, $value);
    }
}

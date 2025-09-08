<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

trait AssertObject
{
    use AssertArrayMap;

    private function assertObjectEqualsArray(array $expected, object $object)
    {
        $this->assertEquals($expected, $this->objectToArray($object));
    }

    /**
     * Recursively asserts that $object have $expected structure and values.
     *
     * @see AssertArrayMap::assertArrayMap()
     */
    private function assertObjectSchema(array $expected, object $object)
    {
        $this->assertArrayMap($expected, $this->objectToArray($object));
    }

    /**
     * Converts $object to array.
     */
    private function objectToArray(object $object): array
    {
        return json_decode(json_encode($object), true);
    }
}

<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Arrays;

class ArraysTest extends TestCase
{
    // Test filterJoin with default callback (removing empty elements)
    public function testFilterJoinWithDefaultCallback()
    {
        $array = ['a', '', 'b', null, 'c', false, 'd'];
        $separator = ', ';
        $expected = 'a, b, c, d';

        $result = Arrays::filterJoin($array, $separator);

        $this->assertEquals($expected, $result);
    }

    // Test filterJoin with custom callback
    public function testFilterJoinWithCustomCallback()
    {
        $array = ['a', 'b', 'c', 'd'];
        $separator = '-';
        $callback = fn($element) => $element !== 'b';
        $expected = 'a-c-d';

        $result = Arrays::filterJoin($array, $separator, $callback);

        $this->assertEquals($expected, $result);
    }

    // Test filterJoin with ARRAY_FILTER_USE_KEY mode
    public function testFilterJoinWithUseKeyMode()
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $separator = ', ';
        $callback = fn($key) => $key !== 'b';
        $expected = '1, 3';

        $result = Arrays::filterJoin($array, $separator, $callback, ARRAY_FILTER_USE_KEY);

        $this->assertEquals($expected, $result);
    }

    // Test filterJoin with ARRAY_FILTER_USE_BOTH mode
    public function testFilterJoinWithUseBothMode()
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $separator = ', ';
        $callback = fn($value, $key) => $key !== 'b' && $value !== 3;
        $expected = '1';

        $result = Arrays::filterJoin($array, $separator, $callback, ARRAY_FILTER_USE_BOTH);

        $this->assertEquals($expected, $result);
    }
}

<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Arrays;

class ArraysTest extends TestCase
{
    public function testFilterJoinWithDefaultCallback()
    {
        $array = ['a', '', 'b', null, 'c', false, 'd'];
        $separator = ', ';
        $expected = 'a, b, c, d';

        $result = Arrays::filterJoin($array, $separator);

        $this->assertEquals($expected, $result);
    }

    public function testFilterJoinWithCustomCallback()
    {
        $array = ['a', 'b', 'c', 'd'];
        $separator = '-';
        $callback = fn($element) => $element !== 'b';
        $expected = 'a-c-d';

        $result = Arrays::filterJoin($array, $separator, $callback);

        $this->assertEquals($expected, $result);
    }

    public function testFilterJoinWithUseKeyMode()
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $separator = ', ';
        $callback = fn($key) => $key !== 'b';
        $expected = '1, 3';

        $result = Arrays::filterJoin($array, $separator, $callback, ARRAY_FILTER_USE_KEY);

        $this->assertEquals($expected, $result);
    }

    public function testFilterJoinWithUseBothMode()
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $separator = ', ';
        $callback = fn($value, $key) => $key !== 'b' && $value !== 3;
        $expected = '1';

        $result = Arrays::filterJoin($array, $separator, $callback, ARRAY_FILTER_USE_BOTH);

        $this->assertEquals($expected, $result);
    }

    public function testAny()
    {
        $this->assertTrue(Arrays::any(['a', 'b'], fn($element) => $element == 'a'));
        $this->assertFalse(Arrays::any(['a', 'b'], fn($element) => $element == 'y'));
    }

    public function testAnyEmpty()
    {
        $this->assertFalse(Arrays::anyEmpty(['a', 'b']));
        $this->assertTrue(Arrays::anyEmpty(['a', '']));
        $this->assertTrue(Arrays::anyEmpty(['a', false]));
        $this->assertTrue(Arrays::anyEmpty(['a', 0]));
    }
}

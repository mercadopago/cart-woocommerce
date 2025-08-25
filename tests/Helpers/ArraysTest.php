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

        $array = ['a' => 1, 'b' => true, 'c' => false, 'd' => ''];

        $this->assertFalse(Arrays::anyEmpty($array, ['a', 'b']));
        $this->assertFalse(Arrays::anyEmpty($array, ['b', 'a']));
        $this->assertFalse(Arrays::anyEmpty($array, ['b']));
        $this->assertTrue(Arrays::anyEmpty($array, ['c', 'b', 'a']));
        $this->assertTrue(Arrays::anyEmpty($array, ['a', 'c']));
        $this->assertTrue(Arrays::anyEmpty($array, ['b', 'd']));
        $this->assertTrue(Arrays::anyEmpty($array, ['a', 'e']));
        $this->assertTrue(Arrays::anyEmpty($array, ['e']));
    }

    public function testOnly()
    {
        $array = ['a' => 1, 'b' => true, 'c' => false, 'd' => ''];

        $this->assertEquals(['a' => 1, 'b' => true], Arrays::only($array, ['a', 'b']));
        $this->assertEquals(['b' => true, 'd' => ''], Arrays::only($array, ['b', 'd']));
        $this->assertEquals(['b' => true], Arrays::only($array, 'b'));
        $this->assertEquals([], Arrays::only($array, 'e'));
        $this->assertEquals([], Arrays::only($array, ['e']));
    }

    public function testExcept()
    {
        $array = ['a' => 1, 'b' => true, 'c' => false, 'd' => ''];

        $this->assertEquals(['a' => 1, 'b' => true], Arrays::except($array, ['c', 'd']));
        $this->assertEquals(['a' => 1, 'c' => false], Arrays::except($array, ['b', 'd']));
        $this->assertEquals(['a' => 1, 'c' => false, 'd' => ''], Arrays::except($array, ['x', 'b']));
        $this->assertEquals($array, Arrays::except($array, []));
        $this->assertEquals([], Arrays::except($array, ['a', 'b', 'c', 'd']));
    }

    public function testLast()
    {
        $this->assertEquals(3, Arrays::last([1, 2, 3]));
        $this->assertEquals('c', Arrays::last(['a', 'b', 'c']));
        $this->assertEquals('a', Arrays::last(['a']));
    }
}

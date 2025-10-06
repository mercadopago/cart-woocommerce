<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Strings;
use PHPUnit\Framework\TestCase;

class StringsTest extends TestCase
{
    public function testContains()
    {
        $this->assertTrue(Strings::contains('abc', 'bc'));
        $this->assertTrue(Strings::contains('abc', 'ab'));
        $this->assertTrue(Strings::contains('abc', 'b'));
        $this->assertFalse(Strings::contains('abc', 'n'));
        $this->assertFalse(Strings::contains('abc', 'cba'));
        $this->assertFalse(Strings::contains('abc', 'cb'));
        $this->assertTrue(Strings::contains('', ''));
        $this->assertFalse(Strings::contains('', 'a'));
    }
}

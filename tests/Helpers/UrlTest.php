<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testIsValid()
    {
        $this->assertFalse(Url::isValid(''));
        $this->assertFalse(Url::isValid('l'));
        $this->assertFalse(Url::isValid('l.com'));
        $this->assertTrue(Url::isValid('http://localhost'));
        $this->assertTrue(Url::isValid('https://localhost.com'));
    }
}

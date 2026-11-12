<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use PHPUnit\Framework\TestCase;

class SmartPixMoneyTest extends TestCase
{
    public function testExactArithmeticBeyondIntegerRange(): void
    {
        $this->assertSame('99999999999999999999.99', SmartPixMoney::subtract('100000000000000000000.00', '0.01'));
        $this->assertSame('100000000000000000000.00', SmartPixMoney::add('99999999999999999999.99', '0.01'));
        $this->assertSame('0.00', SmartPixMoney::add('0.00', '0.00'));
        $this->assertSame('100.00', SmartPixMoney::subtract('125.50', '25.50'));
        $this->assertSame('0.00', SmartPixMoney::subtract('125.50', '125.50'));
        $this->assertNull(SmartPixMoney::subtract('25.50', '125.50'));
    }

    /** @dataProvider decimalProvider */
    public function testCanonicalDecimals($input, ?string $expected): void
    {
        $this->assertSame($expected, SmartPixMoney::normalize($input));
    }

    public static function decimalProvider(): array
    {
        return [['125.5000', '125.50'], ['25.5', '25.50'], ['0', '0.00'], ['01.00', null],
            ['25.501', null], [25.5, null], ['1e2', null], ['-25.50', null], [null, null]];
    }
}

<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Numbers;
use PHPUnit\Framework\TestCase;

/**
 * Covers the currency-aware decimal helpers.
 *
 * @covers \MercadoPago\Woocommerce\Helpers\Numbers
 */
class NumbersTest extends TestCase
{
    public function testGetDecimalsForZeroDecimalCurrencies(): void
    {
        $this->assertSame(0, Numbers::getDecimalsForCurrency('CLP'));
        $this->assertSame(0, Numbers::getDecimalsForCurrency('COP'));
    }

    public function testGetDecimalsForStandardCurrencies(): void
    {
        $this->assertSame(2, Numbers::getDecimalsForCurrency('BRL'));
        $this->assertSame(2, Numbers::getDecimalsForCurrency('MXN'));
        $this->assertSame(2, Numbers::getDecimalsForCurrency('ARS'));
    }

    public function testFormatByCurrencyKeepsTwoDecimalsWithTrailingZero(): void
    {
        $this->assertSame('9.90', Numbers::formatByCurrency('BRL', 9.9));
        $this->assertSame('5.00', Numbers::formatByCurrency('BRL', 5.0));
        $this->assertSame('1234.56', Numbers::formatByCurrency('MXN', 1234.56));
    }

    public function testFormatByCurrencyUsesNoDecimalsForZeroDecimalCurrencies(): void
    {
        $this->assertSame('1234', Numbers::formatByCurrency('CLP', 1234.0));
        $this->assertSame('1000', Numbers::formatByCurrency('COP', 1000.0));
    }

    public function testFormatByCurrencyUsesDotAndNoThousandsSeparator(): void
    {
        $this->assertSame('1234567.89', Numbers::formatByCurrency('BRL', 1234567.89));
    }
}

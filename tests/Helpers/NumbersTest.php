<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Numbers;
use PHPUnit\Framework\TestCase;

/**
 * Covers monetary formatting and calculations with canonical numeric inputs.
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

    /**
     * @dataProvider roundingValues
     */
    public function testFormatRoundsWithRequestedDecimalPlaces(float $value, int $decimals, float $expected): void
    {
        $this->assertSame($expected, Numbers::format($value, $decimals));
    }

    public function roundingValues(): array
    {
        return [
            'round down' => [12.344, 2, 12.34],
            'half rounds up' => [12.345, 2, 12.35],
            'negative half' => [-12.345, 2, -12.35],
            'zero' => [0.0, 2, 0.0],
            'no decimal places' => [10.5, 0, 11.0],
        ];
    }

    /**
     * @dataProvider canonicalValues
     */
    public function testMakesValueSafePreservesCanonicalAmount($value, float $expected): void
    {
        $this->assertSame($expected, Numbers::makesValueSafe($value));
    }

    public function canonicalValues(): array
    {
        return [
            'numeric string' => ['19.95', 19.95],
            'currency prefix with dot decimal' => ['R$ 19.95', 19.95],
            'integer' => [19, 19.0],
            'negative number' => [-19.95, -19.95],
            'zero number' => [0, 0.0],
            'zero string' => ['0', 0.0],
            'empty string' => ['', 0.0],
            'null' => [null, 0.0],
        ];
    }

    public function testRemoveNonNumericPrefixKeepsCanonicalDecimalDigits(): void
    {
        $this->assertSame('123.45', Numbers::removeNonNumericPrefix('R$ 123.45'));
    }

    /**
     * @dataProvider symbolValues
     */
    public function testFormatWithCurrencySymbolUsesCommaAndNoThousandsSeparator(
        string $symbol,
        float $value,
        int $decimals,
        string $expected
    ): void {
        $this->assertSame($expected, Numbers::formatWithCurrencySymbol($symbol, $value, $decimals));
    }

    public function symbolValues(): array
    {
        return [
            'two decimals' => ['R$', 12.3, 2, 'R$ 12,30'],
            'zero' => ['R$', 0.0, 2, 'R$ 0,00'],
            'negative rounding' => ['R$', -12.345, 2, 'R$ -12,35'],
            'large amount' => ['$', 1234567.89, 2, '$ 1234567,89'],
            'integer currency' => ['$', 10.5, 0, '$ 11'],
        ];
    }

    /**
     * @dataProvider convertedValues
     */
    public function testCalculateByCurrencyAppliesRatioAndCurrencyDecimals(
        string $currency,
        float $value,
        float $ratio,
        float $expected
    ): void {
        $this->assertSame($expected, Numbers::calculateByCurrency($currency, $value, $ratio));
    }

    public function convertedValues(): array
    {
        return [
            'two decimal amount' => ['BRL', 12.34, 1.5, 18.51],
            'preserves intermediate precision' => ['BRL', 1.23456, 1.0, 1.2346],
            'negative numeric amount' => ['BRL', -12.34, 2.0, -24.68],
            'zero amount' => ['BRL', 0.0, 1.5, 0.0],
            'CLP rounds converted half' => ['CLP', 5.25, 2.0, 11.0],
            'COP rounds converted half' => ['COP', 5.25, 2.0, 11.0],
            'CLP rounds down' => ['CLP', 10.4, 1.0, 10.0],
        ];
    }

    /**
     * @dataProvider currencyRoundingValues
     */
    public function testFormatByCurrencyRoundsFinalAmount(string $currency, float $value, string $expected): void
    {
        $this->assertSame($expected, Numbers::formatByCurrency($currency, $value));
    }

    public function currencyRoundingValues(): array
    {
        return [
            'standard positive half' => ['BRL', 12.345, '12.35'],
            'standard negative half' => ['BRL', -12.345, '-12.35'],
            'standard zero' => ['BRL', 0.0, '0.00'],
            'CLP half' => ['CLP', 10.5, '11'],
            'COP negative half' => ['COP', -10.5, '-11'],
            'CLP zero' => ['CLP', 0.0, '0'],
        ];
    }

    /**
     * @dataProvider percentageValues
     */
    public function testGetPercentageUsesPartialPlusPaidTotalAndRounds(
        float $partial,
        float $paid,
        float $expected
    ): void {
        $this->assertSame($expected, Numbers::getPercentageFromParcialValue($partial, $paid));
    }

    public function percentageValues(): array
    {
        return [
            'ten percent of combined total' => [10.0, 90.0, 10.0],
            'recurring decimal' => [1.0, 2.0, 33.33],
            'zero partial' => [0.0, 100.0, 0.0],
            'only partial paid' => [100.0, 0.0, 100.0],
            'negative partial and positive total' => [-10.0, 110.0, -10.0],
        ];
    }
}

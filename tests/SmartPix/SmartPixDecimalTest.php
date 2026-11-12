<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;
use PHPUnit\Framework\TestCase;

class SmartPixDecimalTest extends TestCase
{
    /**
     * @dataProvider canonicalProvider
     * @param mixed $value
     */
    public function testExactValuesBecomeCanonicalCents($value, string $expected): void
    {
        $this->assertSame($expected, SmartPixDecimal::canonical($value));
    }

    public static function canonicalProvider(): array
    {
        return [
            'zero integer' => [0, '0.00'],
            'integer' => [5, '5.00'],
            'integer string' => ['5', '5.00'],
            'one decimal' => ['5.5', '5.50'],
            'two decimals' => ['5.05', '5.05'],
            'trailing zeros' => ['5.500000', '5.50'],
            'zero with zeros' => ['0.000', '0.00'],
            'one cent' => ['0.01', '0.01'],
            'beyond integer precision' => ['9007199254740993.01', '9007199254740993.01'],
            'maximum length' => [str_repeat('9', 61) . '.00', str_repeat('9', 61) . '.00'],
        ];
    }

    /**
     * @dataProvider rejectedValueProvider
     * @param mixed $value
     */
    public function testInexactOrMalformedValuesAreRejectedInsteadOfRounded($value): void
    {
        $this->assertNull(SmartPixDecimal::canonical($value));
    }

    public static function rejectedValueProvider(): array
    {
        return [
            'null' => [null],
            'boolean' => [true],
            'array' => [['1.00']],
            'float' => [1.5],
            'integral float' => [4.0],
            'negative integer' => [-1],
            'negative string' => ['-1.00'],
            'positive sign' => ['+1.00'],
            'exponent' => ['1e2'],
            'decimal exponent' => ['1.0E+2'],
            'subcent' => ['1.005'],
            'nonzero third decimal' => ['1.001'],
            'leading zero' => ['01.00'],
            'double zero' => ['00'],
            'missing units' => ['.50'],
            'missing cents' => ['1.'],
            'comma separator' => ['1,00'],
            'thousands separator' => ['1,000.00'],
            'leading space' => [' 1.00'],
            'trailing newline' => ["1.00\n"],
            'empty' => [''],
            'letters' => ['1a.00'],
            'oversized' => [str_repeat('1', 65)],
        ];
    }

    /**
     * @dataProvider nonCanonicalProvider
     * @param mixed $value
     */
    public function testOnlyTwoDecimalStringsAreCanonical($value): void
    {
        $this->assertFalse(SmartPixDecimal::isCanonical($value));
    }

    public static function nonCanonicalProvider(): array
    {
        return [
            'integer' => [1], 'float' => [1.0], 'null' => [null], 'units only' => ['1'], 'one decimal' => ['1.0'],
            'three decimals' => ['1.000'], 'leading zero' => ['01.00'], 'negative' => ['-1.00'],
            'trailing newline' => ["1.00\n"], 'oversized' => [str_repeat('9', 62) . '.00'],
        ];
    }

    public function testCanonicalLengthLimitIsInclusive(): void
    {
        $this->assertTrue(SmartPixDecimal::isCanonical(str_repeat('9', 61) . '.00'));
    }

    public function testAdditionCarriesAcrossUnitsWithoutFloatRounding(): void
    {
        $this->assertSame('0.00', SmartPixDecimal::add('0.00', '0.00'));
        $this->assertSame('1.00', SmartPixDecimal::add('0.99', '0.01'));
        $this->assertSame('0.30', SmartPixDecimal::add('0.10', '0.20'));
        $this->assertSame('10000000000000000.00', SmartPixDecimal::add('9999999999999999.99', '0.01'));
    }

    /** @dataProvider invalidFactorProvider */
    public function testMultiplicationRefusesFactorsOutsideTheBound(int $factor): void
    {
        $this->expectException(InvalidArgumentException::class);
        SmartPixDecimal::multiply('1.00', $factor);
    }

    public static function invalidFactorProvider(): array
    {
        return [[0], [-1], [SmartPixDecimal::MAX_FACTOR + 1], [PHP_INT_MAX]];
    }

    public function testMultiplicationIsExactAtTheFactorBound(): void
    {
        $this->assertSame('9999.99', SmartPixDecimal::multiply('0.01', SmartPixDecimal::MAX_FACTOR));
        $this->assertSame('0.00', SmartPixDecimal::multiply('0.00', 5));
        $this->assertSame('9007199254740993.01', SmartPixDecimal::multiply('9007199254740993.01', 1));
    }

    /** @dataProvider inexactDivisionProvider */
    public function testInexactOrUnboundedDivisionReturnsNull(string $value, int $divisor): void
    {
        $this->assertNull(SmartPixDecimal::divideExact($value, $divisor));
    }

    public static function inexactDivisionProvider(): array
    {
        return [
            'zero divisor' => ['1.00', 0],
            'negative divisor' => ['1.00', -1],
            'divisor above the bound' => ['1.00', SmartPixDecimal::MAX_FACTOR + 1],
            'remainder' => ['10.00', 3],
            'subcent quotient' => ['0.01', 2],
        ];
    }

    public function testExactDivisionKeepsCents(): void
    {
        $this->assertSame('3.33', SmartPixDecimal::divideExact('9.99', 3));
        $this->assertSame('0.00', SmartPixDecimal::divideExact('0.00', 7));
        $this->assertSame('0.01', SmartPixDecimal::divideExact('9999.99', SmartPixDecimal::MAX_FACTOR));
    }
}

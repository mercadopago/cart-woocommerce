<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixCpf;
use PHPUnit\Framework\TestCase;

/**
 * Synthetic CPF values only; 123.456.789-09 is a check-digit-valid test document.
 */
class SmartPixCpfTest extends TestCase
{
    /** @dataProvider acceptedCpfProvider */
    public function testUsualMasksAreStrippedToDigits(string $value): void
    {
        $this->assertSame('12345678909', SmartPixCpf::normalize($value));
    }

    public static function acceptedCpfProvider(): array
    {
        return [
            'digits' => ['12345678909'],
            'masked' => ['123.456.789-09'],
            'spaced' => [' 123 456 789 09 '],
            'mixed separators' => ['123.456 789-09'],
        ];
    }

    /**
     * @dataProvider rejectedCpfProvider
     * @param mixed $value
     */
    public function testInvalidDocumentIsRejected($value): void
    {
        $this->assertNull(SmartPixCpf::normalize($value));
    }

    public static function rejectedCpfProvider(): array
    {
        return [
            'null' => [null],
            'integer' => [12345678909],
            'float' => [12345678909.0],
            'array' => [['12345678909']],
            'boolean' => [true],
            'empty' => [''],
            'mask only' => ['...--'],
            'ten digits' => ['5299822472'],
            'twelve digits' => ['123456789090'],
            'all zeros' => ['00000000000'],
            'all equal digits' => ['11111111111'],
            'all equal masked digits' => ['999.999.999-99'],
            'invalid first check digit' => ['52998224715'],
            'invalid second check digit' => ['52998224724'],
            'letter' => ['5299822472A'],
            'slash mask' => ['529/982/247-25'],
            'comma mask' => ['529,982,247-25'],
            'underscore mask' => ['529_982_247_25'],
            'parentheses' => ['(123)45678909'],
            'plus sign' => ['+12345678909'],
            'nonascii digit' => ["5299822472\u{0665}"],
            'oversized mask' => [str_repeat(' ', 22) . '12345678909'],
        ];
    }

    public function testMaskLengthLimitIsInclusive(): void
    {
        $this->assertSame('12345678909', SmartPixCpf::normalize(str_repeat(' ', 21) . '12345678909'));
    }
}

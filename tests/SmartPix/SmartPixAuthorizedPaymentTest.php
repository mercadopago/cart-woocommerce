<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use PHPUnit\Framework\TestCase;

class SmartPixAuthorizedPaymentTest extends TestCase
{
    public function testValidMaterialIsAvailableOnlyThroughExplicitGetters(): void
    {
        $payment = new SmartPixAuthorizedPayment('SYNTHETIC_PROOF', '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_KEY');
        $this->assertSame('SYNTHETIC_PROOF', $payment->getAuthorizedPseudotoken());
        $this->assertSame('00000000-0000-4000-8000-000000000000', $payment->getOpenFinanceAccountId());
        $this->assertSame('SYNTHETIC_KEY', $payment->getIdempotencyKey());
    }

    /** @dataProvider invalidMaterialProvider */
    public function testInvalidMaterialCannotBeConstructed(string $token, string $account, string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Smart Pix authorization material.');
        new SmartPixAuthorizedPayment($token, $account, $key);
    }

    public static function invalidMaterialProvider(): array
    {
        $uuid = '00000000-0000-4000-8000-000000000000';
        return [['', $uuid, 'KEY'], [' ', $uuid, 'KEY'], ['PROOF', $uuid, ''],
            ['PROOF', $uuid, ' '], ['PROOF', 'invalid', 'KEY'], ['PROOF', $uuid . "\n", 'KEY']];
    }

    public function testDebugOutputRedactsMaterial(): void
    {
        $payment = new SmartPixAuthorizedPayment('SYNTHETIC_PRIVATE_PROOF', '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_PRIVATE_KEY');
        ob_start();
        var_dump($payment);
        $debug = ob_get_clean();
        $this->assertStringNotContainsString('SYNTHETIC_PRIVATE', $debug);
        $this->assertStringNotContainsString('00000000', $debug);
        $this->assertSame('{}', json_encode($payment));
    }

    public function testSerializationIsForbidden(): void
    {
        $payment = new SmartPixAuthorizedPayment('SYNTHETIC_PRIVATE_PROOF', '00000000-0000-4000-8000-000000000000', 'SYNTHETIC_PRIVATE_KEY');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Smart Pix authorization material cannot be serialized.');
        serialize($payment);
    }

    public function testUnserializationIsForbidden(): void
    {
        $payment = new SmartPixAuthorizedPayment('PROOF', '00000000-0000-4000-8000-000000000000', 'KEY');
        $this->expectException(\LogicException::class);
        $payment->__unserialize([]);
    }
}

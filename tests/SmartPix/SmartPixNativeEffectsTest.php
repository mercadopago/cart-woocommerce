<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixNativeEffects;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;

class SmartPixNativeEffectsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testScopeAppliesOnlyToTheExactValidatedObjectAndIsCleared(): void
    {
        $effects = new SmartPixNativeEffects();
        $order = Mockery::mock(WC_Order::class);
        $other = Mockery::mock(WC_Order::class);
        $this->assertFalse($effects->isApplying($order));
        $this->assertTrue($effects->cancel($order, function () use ($effects, $order, $other): bool {
            $this->assertTrue($effects->isApplying($order));
            $this->assertFalse($effects->isApplying($other));
            return true;
        }));
        $this->assertFalse($effects->isApplying($order));
    }

    public function testExceptionCannotLeaveAnAmbientSavePermission(): void
    {
        $effects = new SmartPixNativeEffects();
        $order = Mockery::mock(WC_Order::class);
        try {
            $effects->cancel($order, static function (): void {
                throw new RuntimeException('SYNTHETIC_NATIVE_FAILURE');
            });
            $this->fail('Expected native failure.');
        } catch (RuntimeException $exception) {
            $this->assertFalse($effects->isApplying($order));
        }
    }
}

<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixPaidStatus;
use PHPUnit\Framework\TestCase;

class SmartPixPaidStatusTest extends TestCase
{
    /** @dataProvider paidProvider */
    public function testPaidFactMustAgreeWithTheStatus(string $status, bool $isPaid, bool $expected): void
    {
        $this->assertSame($expected, SmartPixPaidStatus::isPaid($status, $isPaid));
    }

    public static function paidProvider(): array
    {
        return [
            'processing' => ['processing', true, true],
            'completed' => ['completed', true, true],
            'store paid status' => ['paid-by-store', true, true],
            'store status not reported paid' => ['paid-by-store', false, false],
            'processing not reported paid' => ['processing', false, false],
            'pending reported paid' => ['pending', true, false],
            'on hold reported paid' => ['on-hold', true, false],
            'failed reported paid' => ['failed', true, false],
            'cancelled reported paid' => ['cancelled', true, false],
            'refunded reported paid' => ['refunded', true, false],
        ];
    }
}

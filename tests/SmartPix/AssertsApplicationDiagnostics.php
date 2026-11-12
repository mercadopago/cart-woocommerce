<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;

/** Assert the additional diagnostic before checking the existing financial event sequence. */
trait AssertsApplicationDiagnostics
{
    private function assertApplicationDiagnostic(array &$events, bool $withKind = true, string $category = 'persistence'): void
    {
        $event = array_shift($events);
        $offset = $withKind ? 1 : 0;
        if ($withKind) {
            $this->assertSame('count', $event[0]);
        }
        $this->assertSame(SmartPixMetricSchema::ERROR, $event[$offset]);
        $this->assertSame('true', $event[$offset + 1]);
        $this->assertSame('apply', $event[$offset + 2]['operation']);
        $this->assertSame($category, $event[$offset + 2]['category']);
        $this->assertNotEmpty($event[$offset + 3]);
    }
}

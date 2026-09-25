<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use PHPUnit\Framework\Constraint\IsType;

trait AssertArraySchema
{
    public function assertArraySchema(array $expected, array $actual, array $trace = [])
    {
        $expectedKeys = array_keys($expected);
        $actualKeys = array_keys($actual);
        sort($expectedKeys);
        sort($actualKeys);

        $traceMessage = fn($trace): string => $trace ? ' Trace: `' . join('.', $trace) . '`' : '';

        $this->assertEquals($expectedKeys, $actualKeys, "Failed asserting expected keys. {$traceMessage($trace)}");

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertIsArray($actual[$key], $traceMessage([...$trace, $key]));
                $this->assertArraySchema($value, $actual[$key], [...$trace, $key]);
            } else {
                $this->assertThat(
                    $actual[$key],
                    is_string($value) ? new IsType($value) : $value,
                    $traceMessage([...$trace, $key])
                );
            }
        }
    }
}

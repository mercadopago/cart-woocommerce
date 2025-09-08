<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use PHPUnit\Framework\Constraint\IsType;

trait AssertArrayMap
{
    /**
     * Recursively asserts that two arrays have the same structure and matching values.
     *
     * This method compares two arrays by checking if they have identical keys and then verifies
     * that the corresponding values either match the expected constraint (for scalar values) or have
     * the same structure (for nested arrays).
     *
     * @param array $expected The expected array structure, scalar values been PHPUnit constraints or base types ('string', 'int', etc...)
     * @param array $actual The actual array to validate against the expected structure
     * @param array $trace Internal parameter used to track the recursive path for error messages
     *
     * Example usage:
     *
     * ```php
     * use PHPUnit\Framework\Constraint\IsType;
     * use PHPUnit\Framework\Constraint\IsEmpty;
     *
     * $this->assertArrayMap([
     *     'expiration_date' => [
     *         'description' => IsType::TYPE_STRING,
     *         'default' => new IsEmpty(),
     *     ],
     *     'currency_conversion' => [
     *         'descriptions' => [
     *             'enabled' => $this->equalTo('lorem'),
     *             'disabled' => $this->equalTo('ipsum'),
     *         ],
     *     ],
     * ], $actual);
     * ```
     */
    public function assertArrayMap(array $expected, array $actual, array $trace = [])
    {
        $traceMessage = fn($trace): string => $trace ? ' Trace: `' . join('.', $trace) . '`' : '';

        $expectedKeys = array_keys($expected);
        $actualKeys = array_keys($actual);
        sort($expectedKeys);
        sort($actualKeys);

        $this->assertEquals($expectedKeys, $actualKeys, "Failed asserting expected keys. {$traceMessage($trace)}");

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertIsArray($actual[$key], $traceMessage([...$trace, $key]));
                $this->assertArrayMap($value, $actual[$key], [...$trace, $key]);
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

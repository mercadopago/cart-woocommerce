<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Environment;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class EnvironmentTest extends TestCase
{
    public function setUp(): void
    {
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider developmentEnvironments */
    public function testDevelopmentClassification(string $environment, bool $expected): void
    {
        WP_Mock::userFunction('wp_get_environment_type')->once()->andReturn($environment);

        $this->assertSame($expected, Environment::isDevelopmentEnvironment());
    }

    public function developmentEnvironments(): array
    {
        return [
            'local enables development assets' => ['local', true],
            'development enables development assets' => ['development', true],
            'staging keeps release assets' => ['staging', false],
            'production keeps release assets' => ['production', false],
            'unknown environment is not development' => ['custom', false],
        ];
    }

    /** @dataProvider environmentComparisons */
    public function testEnvironmentTypeRequiresAnExactMatch(string $requested, bool $expected): void
    {
        WP_Mock::userFunction('wp_get_environment_type')->once()->andReturn('production');

        $this->assertSame($expected, Environment::isEnvironmentType($requested));
    }

    public function environmentComparisons(): array
    {
        return [
            'matching type' => ['production', true],
            'another environment' => ['staging', false],
            'case mismatch' => ['Production', false],
        ];
    }
}

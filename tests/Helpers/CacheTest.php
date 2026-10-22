<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Cache;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class CacheTest extends TestCase
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

    /** @dataProvider cachedValues */
    public function testGetPreservesWordPressValueUsingHashedKey($value): void
    {
        WP_Mock::userFunction('get_transient')
            ->once()
            ->with(sha1('seller-configuration'))
            ->andReturn($value);

        $this->assertSame($value, (new Cache())->getCache('seller-configuration'));
    }

    public function cachedValues(): array
    {
        return [
            'cached array' => [['methods' => ['visa', 'master']]],
            'cached object' => [(object) ['rate' => '1.25']],
            'cache miss' => [false],
            'zero value' => [0],
            'empty string' => [''],
            'empty array' => [[]],
        ];
    }

    /** @dataProvider explicitTtls */
    public function testSetPreservesValueAndExplicitTtl(int $ttl): void
    {
        $value = (object) ['methods' => ['visa'], 'available' => false];
        $received = null;
        WP_Mock::userFunction('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $cachedValue, $expiration) use (&$received): void {
                $received = [$key, $cachedValue, $expiration];
            });

        (new Cache())->setCache('payment-methods', $value, $ttl);

        $this->assertSame([sha1('payment-methods'), $value, $ttl], $received);
    }

    public function explicitTtls(): array
    {
        return ['one hour' => [3600], 'no expiration delegated' => [0]];
    }

    public function testSetDefaultsToSixtySeconds(): void
    {
        $received = null;
        WP_Mock::userFunction('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$received): void {
                $received = [$key, $value, $ttl];
            });

        (new Cache())->setCache('exchange-rate', '1.25');

        $this->assertSame([sha1('exchange-rate'), '1.25', 60], $received);
    }

    public function testDeleteInvalidatesTheHashedKey(): void
    {
        $receivedKey = null;
        WP_Mock::userFunction('delete_transient')
            ->once()
            ->andReturnUsing(function ($key) use (&$receivedKey): void {
                $receivedKey = $key;
            });

        (new Cache())->deleteCache('seller-configuration');

        $this->assertSame(sha1('seller-configuration'), $receivedKey);
    }
}

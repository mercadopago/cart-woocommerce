<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Session;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @covers \MercadoPago\Woocommerce\Helpers\Session
 */
class SessionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Session $session;

    private $storage;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->session = new Session();
        $this->storage = Mockery::mock();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    private function useAvailableSession(): void
    {
        WP_Mock::userFunction('WC')->andReturn((object) ['session' => $this->storage]);
    }

    public function testIsAvailableWhenWooCommerceHasSession(): void
    {
        $this->useAvailableSession();

        $this->assertTrue($this->session->isAvailable());
    }

    public function testIsUnavailableWhenWooCommerceSessionIsNull(): void
    {
        WP_Mock::userFunction('WC')->andReturn((object) ['session' => null]);

        $this->assertFalse($this->session->isAvailable());
    }

    /**
     * @dataProvider storedValues
     */
    public function testGetPreservesStoredValue($value): void
    {
        $this->useAvailableSession();
        $this->storage->shouldReceive('get')->once()->with('mp_checkout_session_42')->andReturn($value);

        $this->assertSame($value, $this->session->getSession('mp_checkout_session_42'));
    }

    /**
     * @dataProvider storedValues
     */
    public function testSetPassesOriginalKeyAndValueToStorage($value): void
    {
        $this->useAvailableSession();
        $this->storage->shouldReceive('set')->once()->with('mp_checkout_session_42', Mockery::on(function ($actual) use ($value) {
            return $actual === $value;
        }))->andReturnNull();

        $this->session->setSession('mp_checkout_session_42', $value);
    }

    public function storedValues(): array
    {
        return [
            'string' => ['checkout-flow'],
            'empty string' => [''],
            'zero integer' => [0],
            'zero float' => [0.0],
            'false' => [false],
            'null or missing key' => [null],
            'nested array' => [['_mp_flow_id' => 'flow-42', 'selection' => ['method' => 'card']]],
            'object' => [(object) ['payment_id' => 42]],
        ];
    }

    public function testDeleteClearsOriginalKeyWithNull(): void
    {
        $this->useAvailableSession();
        $this->storage->shouldReceive('set')->once()->with('mp_order_id', null)->andReturnNull();
        $this->storage->shouldNotReceive('delete');

        $this->session->deleteSession('mp_order_id');
    }

    public function testUnavailableSessionReturnsNullAndDoesNotReadOrWriteStorage(): void
    {
        WP_Mock::userFunction('WC')->andReturn((object) ['session' => null]);
        $this->storage->shouldNotReceive('get');
        $this->storage->shouldNotReceive('set');

        $this->assertNull($this->session->getSession('mp_order_id'));
        $this->session->setSession('mp_order_id', 42);
        $this->session->deleteSession('mp_order_id');
    }
}

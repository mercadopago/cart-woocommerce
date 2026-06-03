<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Gateways;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Lightweight gateway stubs used to drive Helpers\Gateways without booting WC.
 * The helper instantiates each entry via `new $gateway()`, so we only need
 * public `id`, `settings` and the `isMissingCredentials()` contract.
 */
class GatewaysTestEnabledWithCredentialsStub
{
    public string $id = 'gateway-enabled-with-credentials';
    public array $settings = ['enabled' => 'yes'];

    public function isMissingCredentials(): bool
    {
        return false;
    }
}

class GatewaysTestEnabledWithoutCredentialsStub
{
    public string $id = 'gateway-enabled-without-credentials';
    public array $settings = ['enabled' => 'yes'];

    public function isMissingCredentials(): bool
    {
        return true;
    }
}

class GatewaysTestDisabledStub
{
    public string $id = 'gateway-disabled';
    public array $settings = ['enabled' => 'no'];

    public function isMissingCredentials(): bool
    {
        return false;
    }
}

class GatewaysTestLegacyWithoutCredentialsCheckStub
{
    public string $id = 'gateway-legacy';
    public array $settings = ['enabled' => 'yes'];
}

class GatewaysTest extends TestCase
{
    private Store $storeMock;
    private Gateways $helper;

    public function setUp(): void
    {
        $this->storeMock = Mockery::mock(Store::class);
        $this->helper    = new Gateways($this->storeMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testReturnsEmptyArrayWhenNoGatewaysRegistered(): void
    {
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([]);

        $this->assertSame([], $this->helper->getEnabledPaymentGateways());
    }

    public function testIncludesEnabledGatewayWithCredentials(): void
    {
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([
            GatewaysTestEnabledWithCredentialsStub::class,
        ]);

        $this->assertSame(
            ['gateway-enabled-with-credentials'],
            $this->helper->getEnabledPaymentGateways()
        );
    }

    public function testExcludesDisabledGateway(): void
    {
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([
            GatewaysTestDisabledStub::class,
        ]);

        $this->assertSame([], $this->helper->getEnabledPaymentGateways());
    }

    public function testExcludesEnabledGatewayWithoutCredentials(): void
    {
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([
            GatewaysTestEnabledWithoutCredentialsStub::class,
        ]);

        $this->assertSame([], $this->helper->getEnabledPaymentGateways());
    }

    public function testIncludesLegacyGatewayWithoutCredentialsCheck(): void
    {
        // Defensive: if a registered gateway predates AbstractGateway::isMissingCredentials,
        // we fall back to the enabled flag only — never throw.
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([
            GatewaysTestLegacyWithoutCredentialsCheckStub::class,
        ]);

        $this->assertSame(['gateway-legacy'], $this->helper->getEnabledPaymentGateways());
    }

    public function testFiltersMixedGateways(): void
    {
        $this->storeMock->shouldReceive('getAvailablePaymentGateways')->once()->andReturn([
            GatewaysTestEnabledWithCredentialsStub::class,
            GatewaysTestEnabledWithoutCredentialsStub::class,
            GatewaysTestDisabledStub::class,
        ]);

        $this->assertSame(
            ['gateway-enabled-with-credentials'],
            $this->helper->getEnabledPaymentGateways()
        );
    }
}

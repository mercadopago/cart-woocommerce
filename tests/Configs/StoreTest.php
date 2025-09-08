<?php

use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Hooks\Options;

class StoreTest extends TestCase
{
    use WoocommerceMock;

    private Store $store;
    private Options $options;

    protected function setUp(): void
    {
        $this->options = $this->createMock(Options::class);
        $this->store = new Store($this->options);
    }

    public function testSetCodeChallenge(): void
    {
        $codeChallenge = 'test_code_challenge';

        $this->options->expects($this->once())
            ->method('set')
            ->with($this->equalTo('_mp_integration_code_challenge'), $this->equalTo($codeChallenge));

        $this->store->setCodeChallenge($codeChallenge);
    }

    public function testSetCodeChallengeAndVerifier(): void
    {
        $this->options->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                [$this->equalTo('_mp_integration_code_verifier'), $this->isType('string')],
                [$this->equalTo('_mp_integration_code_challenge'), $this->isType('string')]
            );

        [$codeChallenge, $codeVerifier] = $this->store->setCodeChallengeAndVerifier();

        $this->assertIsString($codeChallenge);
        $this->assertIsString($codeVerifier);
    }

    public function testGetCodeChallenge(): void
    {
        $expectedCodeChallenge = 'test_code_challenge';

        $this->options->expects($this->once())
            ->method('get')
            ->with($this->equalTo('_mp_integration_code_challenge'), $this->equalTo(''))
            ->willReturn($expectedCodeChallenge);

        $this->assertEquals($expectedCodeChallenge, $this->store->getCodeChallenge());
    }

    public function testGetCodeVerifier(): void
    {
        $expectedCodeVerifier = 'test_code_verifier';

        $this->options->expects($this->once())
            ->method('get')
            ->with($this->equalTo('_mp_integration_code_verifier'), $this->equalTo(''))
            ->willReturn($expectedCodeVerifier);

        $this->assertEquals($expectedCodeVerifier, $this->store->getCodeVerifier());
    }

    public function testGetGatewayTitle(): void
    {
        $gatewayMock = Mockery::mock(AbstractGateway::class);
        $defaultTitle = 'Default Gateway Title';
        $expectedTitle = 'Custom Gateway Title';

        $this->options->expects($this->once())
            ->method('getGatewayOption')
            ->with($this->equalTo($gatewayMock), $this->equalTo('title'), $this->equalTo($defaultTitle))
            ->willReturn($expectedTitle);

        $this->assertEquals($expectedTitle, $this->store->getGatewayTitle($gatewayMock, $defaultTitle));
    }
}

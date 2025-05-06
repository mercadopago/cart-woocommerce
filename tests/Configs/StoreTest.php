<?php
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Hooks\Options;


class StoreTest extends TestCase
{
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
}

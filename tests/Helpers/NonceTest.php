<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @covers \MercadoPago\Woocommerce\Helpers\Nonce
 */
class NonceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Nonce $nonce;

    private $file;

    protected function setUp(): void
    {
        WP_Mock::setUp();

        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getDebugMode')->once()->andReturn('yes');
        $this->file = Mockery::mock(File::class);
        $logs = new Logs($this->file, Mockery::mock(Remote::class));

        $this->nonce = new Nonce($logs, $store);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testGenerateNonceReturnsWordPressNonceForRequestedAction(): void
    {
        WP_Mock::userFunction('wp_create_nonce')->once()->with('checkout-action')->andReturn('fixture-nonce');
        $this->file->shouldNotReceive('error');

        $this->assertSame('fixture-nonce', $this->nonce->generateNonce('checkout-action'));
    }

    public function testGenerateNonceLogsFailureAndReturnsEmptyString(): void
    {
        WP_Mock::userFunction('wp_create_nonce')->once()->with('checkout-action')->andReturn(false);
        $this->file->shouldReceive('error')->once()->with(
            'Security nonce checkout-action creation failed.',
            Nonce::class
        );

        $this->assertSame('', $this->nonce->generateNonce('checkout-action'));
    }

    public function testGenerateNonceFieldPreservesActionNameAndResult(): void
    {
        $field = '<input type="hidden" name="checkout-field" value="fixture-nonce" />';
        WP_Mock::userFunction('wp_nonce_field')->once()->with('checkout-action', 'checkout-field')->andReturn($field);
        $this->file->shouldNotReceive('error');

        $this->assertSame($field, $this->nonce->generateNonceField('checkout-action', 'checkout-field'));
    }

    /**
     * @dataProvider validNonceResults
     */
    public function testValidateNonceAcceptsBothWordPressValidityWindows(int $result): void
    {
        WP_Mock::userFunction('wp_verify_nonce')->once()->with('fixture-nonce', 'checkout-action')->andReturn($result);
        WP_Mock::userFunction('wp_send_json_error')->never();
        $this->file->shouldNotReceive('error');

        $this->nonce->validateNonce('checkout-action', 'fixture-nonce');
    }

    public function validNonceResults(): array
    {
        return [
            'current window' => [1],
            'previous window' => [2],
        ];
    }

    public function testValidateNonceLogsAndSendsForbiddenForInvalidNonce(): void
    {
        WP_Mock::userFunction('wp_verify_nonce')->once()->with('invalid-fixture', 'checkout-action')->andReturn(false);
        $this->file->shouldReceive('error')->once()->with(
            'Security nonce checkout-action check failed. Nonce: invalid-fixture',
            Nonce::class
        );
        WP_Mock::userFunction('wp_send_json_error')->once()->with('Forbidden', 403)->andReturnNull();

        $this->nonce->validateNonce('checkout-action', 'invalid-fixture');
    }
}

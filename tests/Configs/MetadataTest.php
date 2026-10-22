<?php

namespace MercadoPago\Woocommerce\Tests\Configs;

use MercadoPago\Woocommerce\Configs\Metadata;
use MercadoPago\Woocommerce\Hooks\Options;
use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase
{
    public function testGetGatewaySettingsExcludesAllCredentials(): void
    {
        $options = $this->createMock(Options::class);
        $options->expects($this->once())
            ->method('get')
            ->with('woocommerce_woo-mercado-pago-custom_settings', [])
            ->willReturn([
                'enabled' => 'yes',
                '_mp_public_key_prod' => 'legacy-public-key',
                '_mp_access_token_test' => 'legacy-access-token',
                'subscriptions_public_key_prod' => 'subscriptions-public-key',
                'subscriptions_access_token_test' => 'subscriptions-access-token',
            ]);

        $metadata = new Metadata($options);

        $this->assertSame(
            ['enabled' => 'yes'],
            $metadata->getGatewaySettings('woo-mercado-pago-custom')
        );
    }
}

<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Url;
use Mockery;
use PHPUnit\Framework\TestCase;

class LinksTest extends TestCase
{
    private Links $links;
    private Country $countryMock;
    private Url $urlMock;

    public function setUp(): void
    {
        $this->countryMock = Mockery::mock(Country::class);
        $this->urlMock = Mockery::mock(Url::class);
        $this->links = new Links($this->countryMock, $this->urlMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @dataProvider privacyPolicyLinksProvider
     */
    public function testGetPrivacyPolicyLinkReturnsCorrectUrlForEachSiteId(string $siteId, string $expectedUrl)
    {
        $actualUrl = $this->links->getPrivacyPolicyLink($siteId);

        $this->assertEquals($expectedUrl, $actualUrl);
    }

    /**
     * Provider de dados para os testes de links de privacidade
     *
     * @return array
     */
    public function privacyPolicyLinksProvider(): array
    {
        return [
            'Argentina (MLA)' => [
                'siteId' => 'MLA',
                'expectedUrl' => 'https://www.mercadopago.com.ar/privacidad'
            ],
            'Brasil (MLB)' => [
                'siteId' => 'MLB',
                'expectedUrl' => 'https://www.mercadopago.com.br/privacidade'
            ],
            'México (MLM)' => [
                'siteId' => 'MLM',
                'expectedUrl' => 'https://www.mercadopago.com.mx/privacidad'
            ],
            'Chile (MLC)' => [
                'siteId' => 'MLC',
                'expectedUrl' => 'https://www.mercadopago.cl/privacidad'
            ],
            'Colômbia (MCO)' => [
                'siteId' => 'MCO',
                'expectedUrl' => 'https://www.mercadopago.com.co/privacidad'
            ],
            'Peru (MPE)' => [
                'siteId' => 'MPE',
                'expectedUrl' => 'https://www.mercadopago.com.pe/privacidad'
            ],
            'Uruguai (MLU)' => [
                'siteId' => 'MLU',
                'expectedUrl' => 'https://www.mercadopago.com.uy/privacidad'
            ],
            'Default' => [
                'siteId' => 'UNKNOWN',
                'expectedUrl' => 'https://www.mercadopago.com/privacy'
            ],
            'Empty' => [
                'siteId' => '',
                'expectedUrl' => 'https://www.mercadopago.com/privacy'
            ],
            'Lowercase' => [
                'siteId' => 'mla',
                'expectedUrl' => 'https://www.mercadopago.com.ar/privacidad'
            ],
        ];
    }
}

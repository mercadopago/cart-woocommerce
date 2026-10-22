<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\SdkLocale;
use PHPUnit\Framework\TestCase;

class SdkLocaleTest extends TestCase
{
    /**
     * @dataProvider storeLocaleProvider
     */
    public function testGivenStoreLocaleWhenMappedThenReturnsSdkLocale(string $storeLocale, string $expected): void
    {
        $this->assertSame($expected, SdkLocale::fromStoreLocale($storeLocale));
    }

    public function storeLocaleProvider(): array
    {
        return [
            'Argentina keeps its region'           => ['es_AR', 'es-AR'],
            'Chile keeps its region'               => ['es_CL', 'es-CL'],
            'Colombia keeps its region'            => ['es_CO', 'es-CO'],
            'Mexico keeps its region'              => ['es_MX', 'es-MX'],
            'Peru keeps its region'                => ['es_PE', 'es-PE'],
            'Uruguay keeps its region'             => ['es_UY', 'es-UY'],
            'Venezuela keeps its region'           => ['es_VE', 'es-VE'],
            'Spain falls back to neutral Spanish'  => ['es_ES', 'es-MX'],
            'Formal variant ignores the suffix'    => ['es_ES_formal', 'es-MX'],
            'Spanish without region is neutral'    => ['es', 'es-MX'],
            'Any Portuguese uses Brazil'           => ['pt_PT', 'pt-BR'],
            'Brazil'                               => ['pt_BR', 'pt-BR'],
            'English'                              => ['en_US', 'en-US'],
            'Unsupported language uses English'    => ['fr_FR', 'en-US'],
        ];
    }
}

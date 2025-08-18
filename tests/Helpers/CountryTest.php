<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Country;
use Mockery;
use PHPUnit\Framework\TestCase;

class CountryTest extends TestCase
{
    // private Country $country;

    // public function setUp(): void
    // {
    //     $this->country = new Country(Mockery::mock(Seller::class));
    // }

    /**
     * @dataProvider siteIdCountryCodeList
     */
    public function testSiteIdToCountry(string $siteId, string $countryCode)
    {
        $this->assertEquals(
            Country::siteIdToCountry($siteId),
            $countryCode
        );
    }

    public function testSiteIdToCountryDefault()
    {
        $this->assertEquals(
            Country::siteIdToCountry('FK'),
            Country::COUNTRY_CODE_MLA
        );
    }

    /**
     * @dataProvider siteIdCountryCodeList
     */
    public function testCountryToSiteId(string $siteId, string $countryCode)
    {
        $this->assertEquals(
            Country::countryToSiteId($countryCode),
            $siteId
        );
    }

    public function testCountryToSiteIdDefault()
    {
        $this->assertEquals(
            Country::countryToSiteId('FK'),
            ''
        );
    }

    public function siteIdCountryCodeList()
    {
        return [
            [Country::SITE_ID_MLA, Country::COUNTRY_CODE_MLA],
            [Country::SITE_ID_MLB, Country::COUNTRY_CODE_MLB],
            [Country::SITE_ID_MLM, Country::COUNTRY_CODE_MLM],
            [Country::SITE_ID_MLC, Country::COUNTRY_CODE_MLC],
            [Country::SITE_ID_MLU, Country::COUNTRY_CODE_MLU],
            [Country::SITE_ID_MCO, Country::COUNTRY_CODE_MCO],
            [Country::SITE_ID_MPE, Country::COUNTRY_CODE_MPE],
        ];
    }
}

<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Intervals;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class IntervalsTest extends TestCase
{
    private $adminTranslations;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('__', [
            'return' => function ($text, $domain = null) {
                return $text;
            }
        ]);
        $this->adminTranslations = $this->createMock(AdminTranslations::class);
        $this->adminTranslations->storeSettings = [
            'fisrt_option_cron_config' => 'No Cron',
            'second_option_cron_config' => 'Every 5 minutes',
            'third_option_cron_config' => 'Every 10 minutes',
            'fourth_option_cron_config' => 'Every 15 minutes',
            'fifth_option_cron_config' => 'Every 30 minutes',
            'sixth_option_cron_config' => 'Every 1 hour',
        ];
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testGetIntervals()
    {
        $intervals = new Intervals($this->adminTranslations);
        $result = $intervals->getIntervals();

        $expected = [
            [
                'id'          => 'no',
                'description' => 'No Cron',
            ],
            [
                'id'          => '5minutes',
                'description' => 'Every 5 minutes',
            ],
            [
                'id'          => '10minutes',
                'description' => 'Every 10 minutes',
            ],
            [
                'id'          => '15minutes',
                'description' => 'Every 15 minutes',
            ],
            [
                'id'          => '30minutes',
                'description' => 'Every 30 minutes',
            ],
            [
                'id'          => 'hourly',
                'description' => 'Every 1 hour',
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}

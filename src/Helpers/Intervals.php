<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Intervals
{
    protected AdminTranslations $adminTranslations;

    /**
     * @param AdminTranslations $adminTranslations
     */
    public function __construct(AdminTranslations $adminTranslations)
    {
        $this->adminTranslations = $adminTranslations;
    }

    /**
     * Get all store Intervals
     *
     * @return array
     */
    public function getIntervals(): array
    {
        return [
            [
                'id'          => 'no',
                'description' => $this->adminTranslations->storeSettings['fisrt_option_cron_config'],
            ],
            [
                'id'          => '5minutes',
                'description' => $this->adminTranslations->storeSettings['second_option_cron_config'],
            ],
            [
                'id'          => '10minutes',
                'description' =>  $this->adminTranslations->storeSettings['third_option_cron_config'],
            ],
            [
                'id'          => '15minutes',
                'description' =>  $this->adminTranslations->storeSettings['fourth_option_cron_config'],
            ],
            [
                'id'          => '30minutes',
                'description' =>  $this->adminTranslations->storeSettings['fifth_option_cron_config'],
            ],
            [
                'id'          => 'hourly',
                'description' =>  $this->adminTranslations->storeSettings['sixth_option_cron_config'],
            ]
        ];
    }
}

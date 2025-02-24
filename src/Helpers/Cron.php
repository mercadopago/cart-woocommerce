<?php

namespace MercadoPago\Woocommerce\Helpers;

use Exception;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class Cron
{
    public Logs $logs;

    /**
     * Cron helper constructor
     *
     * @param Logs $logs
     */
    public function __construct(Logs $logs)
    {
        $this->logs = $logs;
        $this->registerCustomIntervals();
    }

    /**
     * Register custom intervals
     *
     * @return void
     */
    public function registerCustomIntervals(): void
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['5minutes'] = array(
                'interval' => 300,
                'display'  => __('Every 5 minutes')
            );
            $schedules['10minutes'] = array(
                'interval' => 600,
                'display'  => __('Every 10 minutes')
            );
            $schedules['15minutes'] = array(
                'interval' => 900,
                'display'  => __('Every 15 minutes')
            );
            $schedules['30minutes'] = array(
                'interval' => 1800,
                'display'  => __('Every 30 minutes')
            );

            return $schedules;
        });
    }
    /**
     * Register an scheduled event
     *
     * @param string $periodicy
     * @param $hook
     *
     * @return void
     */
    public function registerScheduledEvent(string $periodicy, $hook): void
    {
        try {
            wp_schedule_event(time(), $periodicy, $hook);
        } catch (Exception $ex) {
            $this->logs->file->error(
                "Unable to register event $hook, got error: {$ex->getMessage()}",
                __CLASS__
            );
        }
    }

    /**
     * Unregister an scheduled event
     *
     * @param string $hook
     *
     * @return void
     */
    public function unregisterScheduledEvent(string $hook): void
    {
        try {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        } catch (Exception $ex) {
            $this->logs->file->error(
                "Unable to unregister event $hook, got error: {$ex->getMessage()}",
                __CLASS__
            );
        }
    }
}

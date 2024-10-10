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
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $periodicy, $hook);
            }
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

    /**
     * Alter an scheduled event
     *
     * @param string $periodicy
     * @param $hook
     *
     * @return void
     */
    public function alterScheduledEvent(string $periodicy, $hook): void
    {
        try {
            if (wp_next_scheduled($hook)) {
                wp_reschedule_event(time(), $periodicy, $hook);
            }
        } catch (Exception $ex) {
            $this->logs->file->error(
                "Unable to alter event periodicy on hook $hook, got error: {$ex->getMessage()}",
                __CLASS__
            );
        }
    }
}

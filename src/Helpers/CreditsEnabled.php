<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use MercadoPago\Woocommerce\Gateways\BasicGateway;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsEnabled
{
    /**
     * @const
     */
    private const CREDITS_ACTIVATION_NEEDED = 'mercadopago_credits_activation_needed';

    /**
     * @const
     */
    private const ALREADY_ENABLE_BY_DEFAULT = 'mercadopago_already_enabled_by_default';

    /**
     * @var Admin
     */
    private $admin;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var Options
     */
    private $options;

    /**
     * CreditsEnabled constructor
     *
     * @param Admin $admin
     * @param Logs $logs
     * @param Options $options
     */
    public function __construct(
        Admin $admin,
        Logs $logs,
        Options $options
    ) {
        $this->admin   = $admin;
        $this->logs    = $logs;
        $this->options = $options;
    }

    /**
     * Set default CreditsEnabled options when needed
     */
    public function setCreditsDefaultOptions(): void
    {
        if ($this->admin->isAdmin() && $this->options->get(self::CREDITS_ACTIVATION_NEEDED) !== 'no') {
            $this->options->set(self::CREDITS_ACTIVATION_NEEDED, 'yes');
            $this->options->set(self::ALREADY_ENABLE_BY_DEFAULT, 'no');
        }
    }

    /**
     * Enable credits on the first execution
     */
    public function enableCreditsAction(): void
    {
        $this->setCreditsDefaultOptions();

        try {
            if ($this->admin->isAdmin() && $this->options->get(self::CREDITS_ACTIVATION_NEEDED) === 'yes') {
                $this->options->set(self::CREDITS_ACTIVATION_NEEDED, 'no');

                $basicGateway   = new BasicGateway();
                $creditsGateway = new CreditsGateway();

                if ($this->options->get(self::ALREADY_ENABLE_BY_DEFAULT) === 'no') {
                    if (
                        isset($creditsGateway->settings['already_enabled_by_default']) &&
                        $this->options->getGatewayOption($creditsGateway, 'already_enabled_by_default')
                    ) {
                        return;
                    }

                    if (
                        isset($basicGateway->settings['enabled']) &&
                        $this->options->getGatewayOption($basicGateway, 'enabled')  === 'yes' &&
                        $creditsGateway->isAvailable()
                    ) {
                        $creditsGateway->activeByDefault();
                        $this->options->set(self::ALREADY_ENABLE_BY_DEFAULT, 'yes');
                    }
                }

                $this->logs->file->info('Credits was activated automatically', __METHOD__);
            }
        } catch (\Exception $ex) {
            $this->logs->file->error(
                "Mercado pago gave error to enable Credits: {$ex->getMessage()}",
                __CLASS__
            );
        }
    }
}

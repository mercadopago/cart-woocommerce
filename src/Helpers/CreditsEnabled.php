<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Helpers\Actions;
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
    const CREDITS_ACTIVATION_NEEDED = 'mercadopago_credits_activation_needed';

    /**
     * @const
     */
    const ALREADY_ENABLE_BY_DEFAULT = 'mercadopago_already_enabled_by_default';

    /**
     * @const
     */
    const ENABLE_CREDITS_ACTION     = 'mp_enable_credits_action';

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
     * @var Actions
     */
    private $actions;

    /**
     * CreditsEnabled constructor
     *
     * @param Admin             $admin
     * @param Logs              $logs
     * @param Options           $options
     * @param Actions           $actions
     */
    public function __construct(
        Admin             $admin,
        Logs              $logs,
        Options           $options,
        Actions           $actions
    ) {
        $this->admin        = $admin;
        $this->logs         = $logs;
        $this->options      = $options;
        $this->actions      = $actions;
    }

    public function setCreditsDefaultOptions(){
        if($this->admin->isAdmin() && $this->options->get(self::CREDITS_ACTIVATION_NEEDED) !== 'no'){
            $this->options->set(self::CREDITS_ACTIVATION_NEEDED, 'yes');
            $this->options->set(self::ALREADY_ENABLE_BY_DEFAULT, 'no');
        }
    }

    public function enableCreditsAction()
    {
        $this->setCreditsDefaultOptions();
        try {

            if ($this->admin->isAdmin() && $this->options->get(self::CREDITS_ACTIVATION_NEEDED) === 'yes') {

                $this->options->set(self::CREDITS_ACTIVATION_NEEDED, 'no');

                $basicGateway   = new BasicGateway();
                $creditsGateway = new CreditsGateway();

                if ($this->options->get(self::ALREADY_ENABLE_BY_DEFAULT) === 'no') {
                    if (isset($creditsGateway->settings['already_enabled_by_default']) && $this->options->getGatewayOption($creditsGateway, 'already_enabled_by_default')) {
                        return;
                    }

                    if (isset($basicGateway->settings['enabled']) && $this->options->getGatewayOption($basicGateway, 'enabled')  === 'yes') {
                        $creditsGateway->activeByDefault();
                        $this->options->set(self::ALREADY_ENABLE_BY_DEFAULT, 'yes');
                    }
                }
            }
        } catch (Exception $ex) {
            $this->logs->file->error("'Mercado pago gave error to enable Credits: {$ex->getMessage()}",
                __CLASS__
            );
        }
    }
}

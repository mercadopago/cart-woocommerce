<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\WoocommerceMercadoPago;

abstract class AbstractGateway extends \WC_Payment_Gateway
{
    /**
     * @var WoocommerceMercadoPago
     */
    protected $mercadopago;

    /**
     * Abstract Gateway constructor
     */
    public function __construct()
    {
        global $mercadopago;
        $this->mercadopago = $mercadopago;
    }

    /**
     * Added gateway scripts
     *
     * @return void
     */
    public function payment_scripts(): void
    {
        if ($this->mercadopago->admin->isAdmin()) {
            $this->mercadopago->scripts->registerAdminScript(
                'woocommerce-mercadopago-admin-components',
                $this->mercadopago->url->getPluginFileUrl('assets/js/admin/mp-admin-configs', '.js')
            );

            $this->mercadopago->scripts->registerAdminStyle(
                'woocommerce-mercadopago-admin-components',
                $this->mercadopago->url->getPluginFileUrl('assets/css/admin/mp-admin-configs', '.css')
            );
        }
    }

    /**
     * Generate custom toggle switch component
     *
     * @param string $key
     * @param array  $settings
     *
     * @return string
     */
    public function generate_mp_toggle_switch_html(string $key, array $settings): string {
        return wc_get_template_html(
            'toggle-switch.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => $this->get_option($key, $settings['default']),
                'settings'    => $settings,
            ],
            null,
            dirname(__FILE__) . '/../../templates/admin/components/'
        );
    }

    /**
     * Generate custom header component
     *
     * @param string $key
     * @param array  $settings
     *
     * @return string
     */
    public function generate_mp_config_header_html(string $key, array $settings): string {
        return wc_get_template_html(
            'config-header.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ],
            null,
            dirname(__FILE__) . '/../../templates/admin/components/'
        );
    }
}

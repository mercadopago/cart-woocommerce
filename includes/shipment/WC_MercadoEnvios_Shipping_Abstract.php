<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mercado Envios Shipping Method for Mercado Pago.
 *
 * A simple shipping method allowing free pickup as a shipping method for Mercado Pago.
 *
 * @class        WC_MercadoPago_Shipping_MercadoEnvios
 * @version        3.0.0
 * @package        WooCommerce/Classes/Shipping
 * @author        Mercado Pago
 */
abstract class WC_MercadoEnvios_Shipping_Abstract extends WC_Shipping_Method
{
    protected $shipments_id = array();

    /**
     * Constructor.
     */
    public function __construct($instance_id = 0)
    {

        add_action('init', array($this, 'load_plugin_textdomain'));

        $this->instance_id = absint($instance_id);
        $this->method_description = __('Mercado Envios is a shipping method available only for payments with Mercado Pago.', 'woocommerce-mercadopago');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        // Logging and debug.
        $_mp_debug_mode = get_option('_mp_debug_mode', '');
        if (!empty ($_mp_debug_mode)) {
            if (class_exists('WC_Logger')) {
                $this->log = new WC_Logger();
            } else {
                $this->log = WC_WooMercadoPago_Module::woocommerce_instance()->logger();
            }
        }

        $this->init();
    }

    // Write log.
    private function write_log($function, $message)
    {
        $_mp_debug_mode = get_option('_mp_debug_mode', '');
        if (!empty ($_mp_debug_mode)) {
            $this->log->add($this->id, '[' . $function . ']: ' . $message);
        }
    }

    /**
     * Initialize local pickup.
     */
    public function init()
    {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
        $this->cost = $this->get_option('cost');
        $this->free_shipping = $this->get_option('free_shipping');
        $this->show_delivery_time = $this->get_option('show_delivery_time');
        // Actions.
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    // Multi-language plugin.
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'woocommerce-mercadopago',
            false,
            dirname(plugin_basename(__FILE__)) . '/i18n/languages/'
        );
    }

    /**
     * @param array $package
     * @throws WC_WooMercadoPago_Exception
     */
    public function calculate_shipping($package = array())
    {
        // Check if Basic Checkout is enabled.
        $basic = new WC_WooMercadoPago_BasicGateway();
        if ($basic->get_option('enabled') != 'yes') {
            $this->write_log(__FUNCTION__, 'mercado pago standard needs to be active... ');
            return;
        }

        // Object package and zipcode.
        $me_package = new WC_MercadoEnvios_Package($package);
        $dimensions = $me_package->get_data();
        $zip_code = $package['destination']['postcode'];
        if (empty($zip_code)) {
            return;
        }

        // Check validity of dimensions
        if (!is_numeric($dimensions['height']) || !is_numeric($dimensions['width']) ||
            !is_numeric($dimensions['length']) || !is_numeric($dimensions['weight'])) {
            return;
        }

        $site_id = get_option('_site_id_v1', '');
        $shipping_method_id = $this->get_shipping_method_id($site_id);
        $mp = $basic->mp;

        // Height x width x length (centimeters), weight (grams).
        $params = array(
            'dimensions' => (int)$dimensions['height'] . 'x' . (int)$dimensions['width'] . 'x' .
                (int)$dimensions['length'] . ',' . $dimensions['weight'] * 1000,
            'zip_code' => preg_replace('([^0-9])', '', sanitize_text_field($zip_code)),
            'item_price' => $package['contents_cost'],
            'access_token' => $mp->get_access_token()
        );

        if ($this->get_option('free_shipping') == 'yes') {
            $params['free_method'] = $shipping_method_id;
        } else {
            $list_shipping_methods = $this->get_shipping_methods_zone_by_shipping_id($this->instance_id);
            foreach ($list_shipping_methods as $key => $shipping_object) {
                if ($key == 'woo-mercado-pago-me-normal' || $key == 'woo-mercado-pago-me-express') {
                    // WTF?
                    $shipping_object = new $shipping_object($shipping_object->instance_id);
                    if ($shipping_object->get_option('free_shipping') == 'yes') {
                        $temp_shipping_method_id = $shipping_object->get_shipping_method_id($site_id);
                        $params['free_method'] = $temp_shipping_method_id;
                    }
                }
            }
        }

        $response = $mp->get('/shipping_options', $params);
        $this->write_log(__FUNCTION__, 'params sent: ' . json_encode($params, JSON_PRETTY_PRINT));
        $this->write_log(__FUNCTION__, 'shipments response API: ' . json_encode($response, JSON_PRETTY_PRINT));

        if ($response['status'] != 200) {
            $this->write_log(__FUNCTION__, 'got response different of 200... returning false.');
            return false;
        }

        foreach ($response['response']['options'] as $shipping) {
            if ($shipping_method_id == $shipping['shipping_method_id']) {
                $label_free_shipping = '';
                if ($this->get_option('free_shipping') == 'yes' || $shipping['cost'] == 0) {
                    $label_free_shipping = __('Free shipping', 'woocommerce-mercadopago');
                }
                $label_delivery_time = '';
                if ($this->get_option('show_delivery_time') == 'yes') {
                    $days = $shipping['estimated_delivery_time']['shipping'] / 24;
                    if ($days <= 1) {
                        $label_delivery_time = $days . ' ' . __('Day', 'woocommerce-mercadopago');
                    } else {
                        $label_delivery_time = $days . ' ' . __('Days', 'woocommerce-mercadopago');
                    }
                }
                $separator = '';
                if ($label_free_shipping != '' && $label_delivery_time != '') {
                    $separator = ' - ';
                }
                $label_info = '';
                if ($label_free_shipping != '' || $label_delivery_time) {
                    $label_info = ' (' . $label_delivery_time . $separator . $label_free_shipping . ')';
                }
                $option = array(
                    'label' => 'Mercado Envios - ' . $shipping['name'] . $label_info,
                    'package' => $package,
                    'cost' => (float)$shipping['cost'],
                    'meta_data' => array(
                        'dimensions' => $params['dimensions'],
                        'shipping_method_id' => $shipping_method_id,
                        'free_shipping' => $this->get_option('free_shipping')
                    )
                );

                $this->write_log(__FUNCTION__, 'optiond added: ' . json_encode($option, JSON_PRETTY_PRINT));

                $this->add_rate($option);
            }
        }
    }

    /**
     * Replace comma by dot.
     * @param  mixed $value Value to fix.
     * @return mixed
     */
    private function fix_format($value)
    {
        $value = str_replace(',', '.', $value);
        return $value;
    }

    /**
     * Init form fields.
     */
    public function init_form_fields()
    {
        // Force quit loop.
        $mp = WC_WooMercadoPago_Module::init_mercado_pago_class();
        if (isset($mp->mercado_envios_loop) && $mp->mercado_envios_loop) {
            return false;
        }
        $warning_active_shipping_methods = '';
        if ($this->show_message_shipping_methods()) {
            $warning_active_shipping_methods = '<img width="14" height="14" src="' .
                plugins_url('assets/images/warning.png', dirname(dirname(__FILE__))) . '">' . ' ' .
                __('Enable the two shipping methods: Mercado Envios (Express and Normal) for the correct operation of the module.', 'woocommerce-mercadopago');
        }
        $this->instance_form_fields = array(
            'mercado_envios_title' => array(
                'title' => __('Mercado Envios', 'woocommerce-mercadopago'),
                'type' => 'title',
                'description' => sprintf('%s', $warning_active_shipping_methods)
            ),
            'title' => array(
                'title' => __('Mercado Envios', 'woocommerce-mercadopago'),
                'type' => 'text',
                'description' => __('This controls the title that the user sees during the payment process.', 'woocommerce-mercadopago'),
                'default' => __('Mercado Envios', 'woocommerce-mercadopago'),
                'desc_tip' => true,
            ),
            'free_shipping' => array(
                'title' => __('Free shipping', 'woocommerce-mercadopago'),
                'type' => 'checkbox',
                'label' => __('nable free shipping for this shipping method', 'woocommerce-mercadopago'),
                'default' => 'no',
            ),
            'show_delivery_time' => array(
                'title' => __('Delivery time', 'woocommerce-mercadopago'),
                'type' => 'checkbox',
                'label' => __('Show estimated delivery time', 'woocommerce-mercadopago'),
                'description' => __('Show the estimated delivery time in working days.', 'woocommerce-mercadopago'),
                'desc_tip' => true,
                'default' => 'no',
            )
        );

    }

    /**
     * Return shipping methods by zone and shipping id.
     */
    public function get_shipping_methods_zone_by_shipping_id($shipping_id)
    {
        $shipping_zone = WC_Shipping_Zones::get_zone_by('instance_id', $shipping_id);
        // Set looping shipping methods.
        $mp = WC_WooMercadoPago_Module::init_mercado_pago_class();
        $mp->mercado_envios_loop = true;
        $shipping_methods_list = array();
        foreach ($shipping_zone->get_shipping_methods() as $key => $shipping_object) {
            $shipping_methods_list[$shipping_object->id] = $shipping_object;
        }
        $mp->mercado_envios_loop = false;
        return $shipping_methods_list;
    }

    /**
     * Validate if it is necessary to enable message.
     */
    public function show_message_shipping_methods()
    {
        // Check if is admin.
        if (is_admin()) {
            if ($this->instance_id > 0) {
                $shipping_methods_list = $this->get_shipping_methods_zone_by_shipping_id($this->instance_id);
                $shipping_methods = array();
                foreach ($shipping_methods_list as $key => $shipping_object) {
                    $shipping_methods[$shipping_object->id] = $shipping_object->is_enabled();
                }
                if (isset($shipping_methods['woo-mercado-pago-me-normal']) && isset($shipping_methods['woo-mercado-pago-me-express'])) {
                    if ($shipping_methods['woo-mercado-pago-me-normal'] === true && $shipping_methods['woo-mercado-pago-me-express'] === true) {
                        // Add settings.
                        /**PERFOMANCE*/
                        //$this->update_settings_api( 'true' );
                        // Not display message.
                        return false;
                    } elseif ($shipping_methods['woo-mercado-pago-me-normal'] === false && $shipping_methods['woo-mercado-pago-me-express'] === false) {
                        // Remove settings.
                        /**PERFOMANCE*/
                        //$this->update_settings_api( 'false' );
                        // Not display message.
                        return false;
                    }
                }
                // Show message.
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Return shipping method id Mercado Envios.
     */
    public function get_shipping_method_id($site_id)
    {
        if (array_key_exists($site_id, $this->shipments_id)) {
            return $this->shipments_id[$site_id];
        } else {
            return 0;
        }
    }

    /**
     * @param $status
     * @throws WC_WooMercadoPago_Exception
     */
    public function update_settings_api($status)
    {
        $infra_data = WC_WooMercadoPago_Module::get_common_settings();
        $infra_data['mercado_envios'] = $status;

        $mp = WC_WooMercadoPago_Module::getMpInstanceSingleton();
        $response = $mp->analytics_save_settings($infra_data);
        $this->write_log(__FUNCTION__, 'analytics response: ' . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}

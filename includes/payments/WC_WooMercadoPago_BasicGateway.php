<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 *
 * WC_WooMercadoPago_BasicGateway
 *
 */
class WC_WooMercadoPago_BasicGateway extends WC_WooMercadoPago_PaymentAbstract
{
    const ID = 'woo-mercado-pago-basic';
    /**
     * WC_WooMercadoPago_BasicGateway constructor.
     * @throws WC_WooMercadoPago_Exception
     */
    public function __construct()
    {
        $this->id = self::ID;

        if (!$this->validateSection()) {
            return;
        }

        $this->description = __('It offers all means of payment: credit and debit cards, cash and account money. Your customers choose whether they pay as guests or from their Mercado Pago account.', 'woocommerce-mercadopago');

        $this->form_fields = array();
        $this->method_title = __('Mercado Pago Checkout', 'woocommerce-mercadopago');
        $this->method = $this->getOption('method', 'redirect');
        $this->title = __('Pay with the payment method you prefer', 'woocommerce-mercadopago');
        $this->method_description = $this->getMethodDescription($this->description);
        $this->auto_return = $this->getOption('auto_return', 'yes');
        $this->success_url = $this->getOption('success_url', '');
        $this->failure_url = $this->getOption('failure_url', '');
        $this->pending_url = $this->getOption('pending_url', '');
        $this->installments = $this->getOption('installments', '24');
        $this->gateway_discount = $this->getOption('gateway_discount', 0);
        $this->clientid_old_version = $this->getClientId();
        $this->field_forms_order = $this->get_fields_sequence();
        $this->ex_payments = $this->getExPayments();
        parent::__construct();
        $this->form_fields = $this->getFormFields('Basic');
        $this->hook = new WC_WooMercadoPago_Hook_Basic($this);
        $this->notification = new WC_WooMercadoPago_Notification_IPN($this);
        $this->currency_convertion = true;
    }

    /**
     * @param $label
     * @return array
     */
    public function getFormFields($label)
    {
        if (is_admin() && $this->isManageSection()) {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            wp_enqueue_script(
                'woocommerce-mercadopago-basic-config-script',
                plugins_url('../assets/js/basic_config_mercadopago'.$suffix.'.js', plugin_dir_path(__FILE__)),
                array(),
                WC_WooMercadoPago_Constants::VERSION
            );
        }

        if (empty($this->checkout_country)) {
            $this->field_forms_order = array_slice($this->field_forms_order, 0, 7);
        }

        if (!empty($this->checkout_country) && empty($this->getAccessToken()) && empty($this->getPublicKey())) {
            $this->field_forms_order = array_slice($this->field_forms_order, 0, 22);
        }

        $form_fields = array();

        $form_fields['checkout_header'] = $this->field_checkout_header();

        if (!empty($this->checkout_country) && !empty($this->getAccessToken()) && !empty($this->getPublicKey())) {
            $form_fields['checkout_options_title'] = $this->field_checkout_options_title();
            $form_fields['checkout_options_subtitle'] = $this->field_checkout_options_subtitle();
            $form_fields['checkout_payments_title'] = $this->field_checkout_payments_title();
            $form_fields['checkout_payments_subtitle'] = $this->field_checkout_payments_subtitle();
            $form_fields['binary_mode'] = $this->field_binary_mode();
            $form_fields['installments'] = $this->field_installments();
            $form_fields['checkout_payments_advanced_title'] = $this->field_checkout_payments_advanced_title();
            $form_fields['method'] = $this->field_method();
            $form_fields['success_url'] = $this->field_success_url();
            $form_fields['failure_url'] = $this->field_failure_url();
            $form_fields['pending_url'] = $this->field_pending_url();
            $form_fields['auto_return'] = $this->field_auto_return();
            foreach ($this->field_ex_payments() as $key => $value) {
                $form_fields[$key] = $value;
            }
        }

        $form_fields_abs = parent::getFormFields($label);
        if (count($form_fields_abs) == 1) {
            return $form_fields_abs;
        }
        $form_fields_merge = array_merge($form_fields_abs, $form_fields);
        $fields = $this->sortFormFields($form_fields_merge, $this->field_forms_order);

        return $fields;
    }

    /**
     * get_fields_sequence
     *
     * @return array
     */
    public function get_fields_sequence()
    {
        return [
            // Necessary to run
            'title',
            'description',
            // Checkout Básico. Acepta todos los medios de pago y lleva tus cobros a otro nivel
            'checkout_header',
            'checkout_steps',
            // ¿En qué país vas a activar tu tienda?
            'checkout_country_title',
            'checkout_country',
            'checkout_btn_save',
            // Carga tus credenciales
            'checkout_credential_title',
            'checkout_credential_mod_test_title',
            'checkout_credential_mod_test_description',
            'checkout_credential_mod_prod_title',
            'checkout_credential_mod_prod_description',
            'checkout_credential_prod',
            'checkout_credential_link',
            'checkout_credential_title_test',
            'checkout_credential_description_test',
            '_mp_public_key_test',
            '_mp_access_token_test',
            'checkout_credential_title_prod',
            'checkout_credential_description_prod',
            '_mp_public_key_prod',
            '_mp_access_token_prod',
            // No olvides de homologar tu cuenta
            'checkout_homolog_title',
            'checkout_homolog_subtitle',
            'checkout_homolog_link',
            // Configure Mercado Pago for WooCommerce
            'checkout_options_title',
            'checkout_options_subtitle',
            'mp_statement_descriptor',
            '_mp_category_id',
            '_mp_store_identificator',
            '_mp_integrator_id',
            // Advanced settings
            'checkout_advanced_settings',
            '_mp_debug_mode',
            '_mp_custom_domain',
            // Set up the payment experience in your store
            'checkout_payments_title',
            'checkout_payments_subtitle',
            'enabled',
            'checkout_payments_description',
            WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
            'installments',
            // advanced settings
            'checkout_payments_advanced_title',
            'checkout_payments_advanced_description',
            'method',
            'auto_return',
            'success_url',
            'failure_url',
            'pending_url',
            'binary_mode',
            'gateway_discount',
            'commission',
            // Everything ready for the takeoff of your sales?
            'checkout_ready_title',
            'checkout_ready_description',
            'checkout_ready_description_link'
        ];
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        if (parent::is_available()) {
            return true;
        }

        if (isset($this->settings['enabled']) && $this->settings['enabled'] == 'yes') {
            if ($this->mp instanceof MP) {
                $accessToken = $this->mp->get_access_token();
                if (strpos($accessToken, 'APP_USR') === false && strpos($accessToken, 'TEST') === false) {
                    return false;
                } else {
                    if(strpos($accessToken, 'TEST') === false && $this->sandbox == true) {
                        return false;
                    }

                    if(strpos($accessToken, 'APP_USR') === false && $this->sandbox == false) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Get clientID when update version 3.0.17 to 4 latest
     * @return string
     */
    public function getClientId() {
        $clientId = get_option( '_mp_client_id', '');
         if(!empty($clientId)){
           return true;
         }
         return false;
   }


    /**
     * @return array
     */
    private function getExPayments()
    {
        $ex_payments = array();
        $get_ex_payment_options = $this->getOption('_all_payment_methods_v0', '');
        if (!empty($get_ex_payment_options)) {
            foreach ($get_ex_payment_options = explode(',', $get_ex_payment_options) as $get_ex_payment_option) {
                if ($this->getOption('ex_payments_' . $get_ex_payment_option, 'yes') == 'no') {
                    $ex_payments[] = $get_ex_payment_option;
                }
            }
        }
        return $ex_payments;
    }

    /**
     * @return array
     */
    public function field_checkout_header()
    {
        $checkout_header = array(
            'title' => sprintf(
                __('Mercado Pago checkout %s', 'woocommerce-mercadopago'),
                '<div class="row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __('Accept all method of payment and take your charges to another level', 'woocommerce-mercadopago') . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __('Turn your online store into your customers preferred payment gateway. Choose if the final payment experience will be inside or outside your store.', 'woocommerce-mercadopago') . '
                </p>
              </div>
            </div>'
            ),
            'type' => 'title',
            'class' => 'mp_title_header'
        );
        return $checkout_header;
    }

    /**
     * @return array
     */
    public function field_checkout_options_title()
    {
        $checkout_options_title = array(
            'title' => __('Configure Mercado Pago for WooCommerce', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_title_bd'
        );
        return $checkout_options_title;
    }

    /**
     * @return array
     */
    public function field_checkout_options_description()
    {
        $checkout_options_description = array(
            'title' => __('Enable the experience of the Mercado Pago Checkout in your online store, select the means of payment available to your customers and<br> define the maximum fees in which they can pay you.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text'
        );
        return $checkout_options_description;
    }

    /**
     * @return array
     */
    public function field_checkout_options_subtitle()
    {
        $checkout_options_subtitle = array(
            'title' => __('Go to the basics. Place your business information.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle mp-mt-5 mp-mb-5'
        );
        return $checkout_options_subtitle;
    }

    /**
     * @return array
     */
    public function field_checkout_payments_title()
    {
        $checkout_payments_title = array(
            'title' => __('Set payment preferences in your store', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_title_bd'
        );
        return $checkout_payments_title;
    }

    /**
     * @return array
     */
    public function field_checkout_payments_advanced_title()
    {
        $checkout_payments_advanced_title = array(
            'title' => __('Advanced settings', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $checkout_payments_advanced_title;
    }

    /**
     * @return array
     */
    public function field_method()
    {
        $method = array(
            'title' => __('Payment experience', 'woocommerce-mercadopago'),
            'type' => 'select',
            'description' => __('Define what payment experience your customers will have, whether inside or outside your store.', 'woocommerce-mercadopago'),
            'default' => ($this->method == 'iframe') ? 'redirect' : $this->method,
            'options' => array(
                'redirect' => __('Redirect', 'woocommerce-mercadopago'),
                'modal' => __('Modal', 'woocommerce-mercadopago')
            )
        );
        return $method;
    }

    /**
     * @return array
     */
    public function field_success_url()
    {
        // Validate back URL.
        if (!empty($this->success_url) && filter_var($this->success_url, FILTER_VALIDATE_URL) === FALSE) {
            $success_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
                __('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
        } else {
            $success_back_url_message = __('Choose the URL that we will show your customers when they finish their purchase.', 'woocommerce-mercadopago');
        }
        $success_url = array(
            'title' => __('Success URL', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => $success_back_url_message,
            'default' => ''
        );
        return $success_url;
    }

    /**
     * @return array
     */
    public function field_failure_url()
    {
        if (!empty($this->failure_url) && filter_var($this->failure_url, FILTER_VALIDATE_URL) === FALSE) {
            $fail_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
                __('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
        } else {
            $fail_back_url_message = __('Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.', 'woocommerce-mercadopago');
        }
        $failure_url = array(
            'title' => __('Payment URL rejected', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => $fail_back_url_message,
            'default' => ''
        );
        return $failure_url;
    }

    /**
     * @return array
     */
    public function field_pending_url()
    {
        // Validate back URL.
        if (!empty($this->pending_url) && filter_var($this->pending_url, FILTER_VALIDATE_URL) === FALSE) {
            $pending_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
                __('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
        } else {
            $pending_back_url_message = __('Choose the URL that we will show to your customers when they have a payment pending approval.', 'woocommerce-mercadopago');
        }
        $pending_url = array(
            'title' => __('Payment URL pending', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => $pending_back_url_message,
            'default' => ''
        );
        return $pending_url;
    }

    /**
     * @return array
     */
    public function field_ex_payments()
    {
        $ex_payments = array();
        $ex_payments_sort = array();

        $all_payments = get_option('_checkout_payments_methods', '');

        if (empty($all_payments)) {
            return $ex_payments;
        }

        $get_payment_methods = get_option('_all_payment_methods_v0', '');

        if (!empty($get_payment_methods)) {
            $get_payment_methods = explode(',', $get_payment_methods);
        }

        //change type atm to ticket
        foreach ($all_payments as $key => $value) {
            if ($value['type'] == 'atm') {
                $all_payments[$key]['type'] = 'ticket';
            } else {
                continue;
            }
        }

        //sort array by type asc
        usort($all_payments, function($a, $b) {
            if($a['type'] == $b['type']) return 0;
            return $b['type'] < $a['type'] ? 1 : -1;
        });

        $count_payment = 0;

        foreach ($all_payments as $payment_method) {
            if ($payment_method['type'] == 'account_money') {
                $count_payment++;
                continue;
            } else {
                if ($payment_method['type'] == 'credit_card') {
                    $element = array(
                        'label' => $payment_method['name'],
                        'id' => 'woocommerce_mercadopago_' . $payment_method['id'],
                        'default' => 'yes',
                        'type' => 'checkbox',
                        'class' => 'online_payment_method',
                        'custom_attributes' => array(
                            'data-translate' => __('Select credit cards', 'woocommerce-mercadopago')
                        ),
                    );
                } elseif ($payment_method['type'] == 'debit_card' || $payment_method['type'] == 'prepaid_card') {
                    $element = array(
                        'label' => $payment_method['name'],
                        'id' => 'woocommerce_mercadopago_' . $payment_method['id'],
                        'default' => 'yes',
                        'type' => 'checkbox',
                        'class' => 'debit_payment_method',
                        'custom_attributes' => array(
                            'data-translate' => __('Select debit cards', 'woocommerce-mercadopago')
                        ),
                    );
                } else {
                    $element = array(
                        'label' => $payment_method['name'],
                        'id' => 'woocommerce_mercadopago_' . $payment_method['id'],
                        'default' => 'yes',
                        'type' => 'checkbox',
                        'class' => 'offline_payment_method',
                        'custom_attributes' => array(
                            'data-translate' => __('Select offline payments', 'woocommerce-mercadopago')
                        ),
                    );
                }
            }

            $count_payment++;

            if ($count_payment == 1) {
                $element['title'] = __('Payment methods', 'woocommerce-mercadopago');
                $element['desc_tip'] = __('Choose the available payment methods in your store.', 'woocommerce-mercadopago');
            }
            if ($count_payment == count($get_payment_methods)) {
                $element['description'] = __('Activate the available payment methods to your clients.', 'woocommerce-mercadopago');
            }

            $ex_payments["ex_payments_" . $payment_method['id']] = $element;
            $ex_payments_sort[] = "ex_payments_" . $payment_method['id'];
        }

        array_splice($this->field_forms_order, 37, 0, $ex_payments_sort);

        return $ex_payments;
    }

    /**
     * @return array
     */
    public function field_auto_return()
    {
        $auto_return = array(
            'title' => __('Return to the store', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'yes',
            'description' => __('Do you want your customer to automatically return to the store after payment?', 'woocommerce-mercadopago'),
            'options' => array(
                'yes' => __('Yes', 'woocommerce-mercadopago'),
                'no' => __('No', 'woocommerce-mercadopago'),
            )
        );
        return $auto_return;
    }

    /**
     * Payment Fields
     */
    public function payment_fields()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        //add css
        wp_enqueue_style(
            'woocommerce-mercadopago-basic-checkout-styles',
            plugins_url('../assets/css/basic_checkout_mercadopago.css', plugin_dir_path(__FILE__))
        );

        //validate active payments methods
        $debito = 0;
        $credito = 0;
        $efectivo = 0;
        $method = $this->getOption('method', 'redirect');
        $tarjetas = get_option('_checkout_payments_methods', '');
        $installments = $this->getOption('installments');
        $str_cuotas = __('installments', 'woocommerce-mercadopago');
        $cho_tarjetas = array();

        if ($installments == 1) {
            $str_cuotas = __('installment', 'woocommerce-mercadopago');
        }

        //change type account_money to ticket
        foreach ($tarjetas as $key => $value) {
            if ($value['type'] == 'account_money') {
                $all_payments[$key]['type'] = 'ticket';
            } else {
                continue;
            }
        }

        foreach ($tarjetas as $tarjeta) {
            if ($this->get_option($tarjeta['config'], '') == 'yes') {
                $cho_tarjetas[] = $tarjeta;
                if ($tarjeta['type'] == 'credit_card') {
                    $credito += 1;
                } elseif ($tarjeta['type'] == 'debit_card' || $tarjeta['type'] == 'prepaid_card') {
                    $debito += 1;
                } else {
                    $efectivo += 1;
                }
            }
        }

        $parameters = array(
            "debito" => $debito,
            "credito" => $credito,
            "efectivo" => $efectivo,
            "tarjetas" => $cho_tarjetas,
            "method" => $method,
            "str_cuotas" => $str_cuotas,
            "installments" => $installments,
            "plugin_version" => WC_WooMercadoPago_Constants::VERSION,
            "cho_image" => plugins_url('../assets/images/redirect_checkout.png', plugin_dir_path(__FILE__)),
            "path_to_javascript" => plugins_url('../assets/js/basic-cho'.$suffix.'.js', plugin_dir_path(__FILE__))
        );

        wc_get_template('checkout/basic_checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path());
    }

    /**
     * @param $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $amount = $this->get_order_total();
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data('_used_gateway', get_class($this));

            if (!empty($this->gateway_discount)) {
                $discount = $amount * ($this->gateway_discount / 100);
                $order->update_meta_data('Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' '  . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
            }

            if (!empty($this->commission)) {
                $comission = $amount * ($this->commission / 100);
                $order->update_meta_data('Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
            }
            $order->save();
        } else {
            update_post_meta($order_id, '_used_gateway', get_class($this));

            if (!empty($this->gateway_discount)) {
                $discount = $amount * ($this->gateway_discount / 100);
                update_post_meta($order_id,'Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' '  . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
            }

            if (!empty($this->commission)) {
                $comission = $amount * ($this->commission / 100);
                update_post_meta($order_id,'Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
            }
        }

        if ('redirect' == $this->method || 'iframe' == $this->method) {
            $this->log->write_log(__FUNCTION__, 'customer being redirected to Mercado Pago.');
            return array(
                'result' => 'success',
                'redirect' => $this->create_preference($order)
            );
        } elseif ('modal' == $this->method) {
            $this->log->write_log(__FUNCTION__, 'preparing to render Mercado Pago checkout view.');
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }
    }

    /**
     * @param $order
     * @return bool
     */
    public function create_preference($order)
    {
        $preferencesBasic = new WC_WooMercadoPago_PreferenceBasic($this, $order);
        $preferences = $preferencesBasic->get_preference();
        try {
            $checkout_info = $this->mp->create_preference(json_encode($preferences));
            $this->log->write_log(__FUNCTION__, 'Created Preference: ' . json_encode($checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($checkout_info['status'] < 200 || $checkout_info['status'] >= 300) {
                $this->log->write_log(__FUNCTION__, 'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message']);
                return false;
            } elseif (is_wp_error($checkout_info)) {
                $this->log->write_log(__FUNCTION__, 'wordpress gave error, payment creation failed with error: ' . $checkout_info['response']['message']);
                return false;
            } else {
                $this->log->write_log(__FUNCTION__, 'payment link generated with success from mercado pago, with structure as follow: ' . json_encode($checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                if ($this->sandbox) {
                    return $checkout_info['response']['sandbox_init_point'];
                }
                return $checkout_info['response']['init_point'];
            }
        } catch (WC_WooMercadoPago_Exception $ex) {
            $this->log->write_log(__FUNCTION__, 'payment creation failed with exception: ' . json_encode($ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return false;
        }
    }

	/**
	 * @return string
	 */
    public static function getId(){
        return WC_WooMercadoPago_BasicGateway::ID;
    }
}

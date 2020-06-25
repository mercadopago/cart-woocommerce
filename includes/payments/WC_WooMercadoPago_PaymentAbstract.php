<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_WooMercadoPago_Payments
 */
class WC_WooMercadoPago_PaymentAbstract extends WC_Payment_Gateway
{
    //ONLY get_option in this fields
    const COMMON_CONFIGS = array(
        '_mp_public_key_test',
        '_mp_access_token_test',
        '_mp_public_key_prod',
        '_mp_access_token_prod',
        'checkout_country',
        'mp_statement_descriptor',
        '_mp_category_id',
        '_mp_store_identificator',
        '_mp_integrator_id',
        '_mp_custom_domain',
        'installments',
        'auto_return'
    );

    const CREDENTIAL_FIELDS = array(
        '_mp_public_key_test',
        '_mp_access_token_test',
        '_mp_public_key_prod',
        '_mp_access_token_prod'
    );

    public $field_forms_order;
    public $id;
    public $method_title;
    public $title;
    public $description;
    public $ex_payments = array();
    public $method;
    public $method_description;
    public $auto_return;
    public $success_url;
    public $failure_url;
    public $pending_url;
    public $installments;
    public $form_fields;
    public $coupon_mode;
    public $payment_type;
    public $checkout_type;
    public $stock_reduce_mode;
    public $date_expiration;
    public $hook;
    public $supports;
    public $icon;
    public $mp_category_id;
    public $store_identificator;
    public $integrator_id;
    public $debug_mode;
    public $custom_domain;
    public $binary_mode;
    public $gateway_discount;
    public $site_data;
    public $log;
    public $sandbox;
    public $mp;
    public $mp_public_key_test;
    public $mp_access_token_test;
    public $mp_public_key_prod;
    public $mp_access_token_prod;
    public $notification;
    public $checkout_country;
    public $wc_country;
    public $commission;
    public $application_id;
    public $type_payments;
    public $activated_payment;
    public $homolog_validate;
    public $clientid_old_version;
    public $customer;
    public $logged_user_email;
    public $currency_convertion;

    /**
     * WC_WooMercadoPago_PaymentAbstract constructor.
     * @throws WC_WooMercadoPago_Exception
     */
    public function __construct()
    {
        $this->mp_public_key_test = $this->getOption('_mp_public_key_test');
        $this->mp_access_token_test = $this->getOption('_mp_access_token_test');
        $this->mp_public_key_prod = $this->getOption('_mp_public_key_prod');
        $this->mp_access_token_prod = $this->getOption('_mp_access_token_prod');
        $this->checkout_country = get_option('checkout_country', '');
        $this->wc_country = get_option('woocommerce_default_country', '');
        $this->mp_category_id = $this->getOption('_mp_category_id', 0);
        $this->store_identificator = $this->getOption('_mp_store_identificator', 'WC-');
        $this->integrator_id = $this->getOption('_mp_integrator_id', '');
        $this->debug_mode = $this->getOption('_mp_debug_mode', 'no');
        $this->custom_domain = $this->getOption('_mp_custom_domain', '');
        $this->binary_mode = $this->getOption('binary_mode', 'no');
        $this->gateway_discount = $this->getOption('gateway_discount', 0);
        $this->commission = $this->getOption('commission', 0);
        $this->sandbox = $this->isTestUser();
        $this->supports = array('products', 'refunds');
        $this->icon = $this->getMpIcon();
        $this->site_data = WC_WooMercadoPago_Module::get_site_data();
        $this->log = new WC_WooMercadoPago_Log($this);
        $this->mp = $this->getMpInstance();
        $this->homolog_validate = $this->getHomologValidate();
        $this->application_id = $this->getApplicationId($this->mp_access_token_prod);
        $this->logged_user_email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
        $this->discount_action_url = get_site_url() . '/index.php/woocommerce-mercadopago/?wc-api=' . get_class($this);
    }

    /**
     * @return mixed
     * @throws WC_WooMercadoPago_Exception
     */
    public function getHomologValidate()
    {
        $homolog_validate = (int)get_option('homolog_validate', 0);
        if (($this->isProductionMode() && !empty($this->mp_access_token_prod)) && $homolog_validate == 0) {
            if ($this->mp instanceof MP) {
                $homolog_validate = $this->mp->homologValidate($this->mp_access_token_prod);
                update_option('homolog_validate', $homolog_validate, true);
                return $homolog_validate;
            }
            return 0;
        }
        return 1;
    }

    /**
     * @return mixed|string
     */
    public function getAccessToken()
    {
        if (!$this->isProductionMode()) {
            return $this->mp_access_token_test;
        }
        return $this->mp_access_token_prod;
    }

    /**
     * @return mixed|string
     */
    public function getPublicKey()
    {
        if (!$this->isProductionMode()) {
            return $this->mp_public_key_test;
        }
        return $this->mp_public_key_prod;
    }

    /**
     * @param $key
     * @param string $default
     * @return mixed|string
     */
    public function getOption($key, $default = '')
    {
        $wordpressConfigs = self::COMMON_CONFIGS;
        if (in_array($key, $wordpressConfigs)) {
            return get_option($key, $default);
        }

        $option = $this->get_option($key, $default);
        if (!empty($option)) {
            return $option;
        }

        return get_option($key, $default);
    }

    /**
     * Normalize fields in admin
     */
    public function normalizeCommonAdminFields()
    {
        if (empty($this->mp_access_token_test) && empty($this->mp_access_token_prod)) {
            if (isset($this->settings['enabled']) && $this->settings['enabled'] == 'yes') {
                $this->settings['enabled'] = 'no';
                $this->disableAllPaymentsMethodsMP();
            }
        }

        $changed = false;
        foreach (self::COMMON_CONFIGS as $config) {
            $commonOption = get_option($config);
            if (isset($this->settings[$config]) && $this->settings[$config] != $commonOption) {
                $changed = true;
                $this->settings[$config] = $commonOption;
            }
        }

        if ($changed) {
            update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
        }
    }

    /**
     * @return bool
     */
    public function validateSection()
    {
        if (isset($_GET['section']) && !empty($_GET['section']) && ($this->id != $_GET['section'])) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isManageSection()
    {
        if (!isset($_GET['section']) || ($this->id !== $_GET['section'])) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function getMpLogo()
    {
        return '<img width="200" height="52" src="' . plugins_url('../assets/images/mplogo.png', plugin_dir_path(__FILE__)) . '"><br><br>';
    }

    /**
     * @return mixed
     */
    public function getMpIcon()
    {
        return apply_filters('woocommerce_mercadopago_icon', plugins_url('../assets/images/mercadopago.png', plugin_dir_path(__FILE__)));
    }

    /**
     * @param $description
     * @return string
     */
    public function getMethodDescription($description)
    {
        return '<div class="mp-header-logo">
            <div class="mp-left-header">
                <img src="' . plugins_url('../assets/images/mplogo.png', plugin_dir_path(__FILE__)) . '">
            </div>
            <div>' . $description . '</div>
        </div>';
    }

    /**
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function update_option($key, $value = '')
    {
        if ($key == 'enabled' && $value == 'yes') {
            if (empty($this->mp->get_access_token())) {
                $message = _('Configure as credenciais para habilitar os métodos de pagamento do Mercado Pago.');
                $this->log->write_log(__FUNCTION__, $message);
                echo json_encode(array('success' => false, 'data' => $message));
                die();
            }
        }
        return parent::update_option($key, $value);
    }

    /**
     *  ADMIN NOTICE HOMOLOG
     */
    public function noticeHomologValidate()
    {
        $type = 'notice-warning';
        $message = sprintf(__('%s, it only takes a few minutes', 'woocommerce-mercadopago'), '<a class="homologScroll mp-mouse_pointer"><b><u>' . __('Approve your account', 'woocommerce-mercadopago') . '</u></b></a>');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     * @param $label
     * @return array
     */
    public function getFormFields($label)
    {    
        $this->init_form_fields();
        $this->init_settings();
        $form_fields = array();
        $form_fields['title'] = $this->field_title();
        $form_fields['description'] = $this->field_description();
        $form_fields['checkout_steps'] = $this->field_checkout_steps();
        $form_fields['checkout_country_title'] = $this->field_checkout_country_title();
        $form_fields['checkout_country'] = $this->field_checkout_country($this->wc_country, $this->checkout_country);
        $form_fields['checkout_btn_save'] = $this->field_checkout_btn_save();

        if (!empty($this->checkout_country)) {
            $form_fields['checkout_credential_title'] = $this->field_checkout_credential_title();
            $form_fields['checkout_credential_mod_test_title'] = $this->field_checkout_credential_mod_test_title();
            $form_fields['checkout_credential_mod_test_description'] = $this->field_checkout_credential_mod_test_description();
            $form_fields['checkout_credential_mod_prod_title'] = $this->field_checkout_credential_mod_prod_title();
            $form_fields['checkout_credential_mod_prod_description'] = $this->field_checkout_credential_mod_prod_description();
            $form_fields['checkout_credential_prod'] = $this->field_checkout_credential_production();
            $form_fields['checkout_credential_link'] = $this->field_checkout_credential_link($this->checkout_country);
            $form_fields['checkout_credential_title_test'] = $this->field_checkout_credential_title_test();
            $form_fields['checkout_credential_description_test'] = $this->field_checkout_credential_description_test();
            $form_fields['_mp_public_key_test'] = $this->field_checkout_credential_publickey_test();
            $form_fields['_mp_access_token_test'] = $this->field_checkout_credential_accesstoken_test();
            $form_fields['checkout_credential_title_prod'] = $this->field_checkout_credential_title_prod();
            $form_fields['checkout_credential_description_prod'] = $this->field_checkout_credential_description_prod();
            $form_fields['_mp_public_key_prod'] = $this->field_checkout_credential_publickey_prod();
            $form_fields['_mp_access_token_prod'] = $this->field_checkout_credential_accesstoken_prod();
            $form_fields['_mp_category_id'] = $this->field_category_store();
            if (!empty($this->getAccessToken()) && !empty($this->getPublicKey())) {
                if ($this->homolog_validate == 0) {
                    if (isset($_GET['section']) && $_GET['section'] == $this->id && !has_action('woocommerce_update_options_payment_gateways_' . $this->id)) {
                        add_action('admin_notices', array($this, 'noticeHomologValidate'));
                    }
                    $form_fields['checkout_steps_link_homolog'] = $this->field_checkout_steps_link_homolog($this->checkout_country, $this->application_id);
                    $form_fields['checkout_homolog_title'] = $this->field_checkout_homolog_title();
                    $form_fields['checkout_homolog_subtitle'] = $this->field_checkout_homolog_subtitle();
                    $form_fields['checkout_homolog_link'] = $this->field_checkout_homolog_link($this->checkout_country, $this->application_id);
                }
                $form_fields['mp_statement_descriptor'] = $this->field_mp_statement_descriptor();
                $form_fields['_mp_store_identificator'] = $this->field_mp_store_identificator();
                $form_fields['_mp_integrator_id'] = $this->field_mp_integrator_id();
                $form_fields['checkout_payments_description'] = $this->field_checkout_options_description();
                $form_fields['checkout_advanced_settings'] = $this->field_checkout_advanced_settings();
                $form_fields['_mp_debug_mode'] = $this->field_debug_mode();
                $form_fields['enabled'] = $this->field_enabled($label);
                $form_fields['_mp_custom_domain'] = $this->field_custom_url_ipn();
                $form_fields['gateway_discount'] = $this->field_gateway_discount();
                $form_fields['commission'] = $this->field_commission();
                $form_fields['checkout_payments_advanced_description'] = $this->field_checkout_payments_advanced_description();
                $form_fields['checkout_ready_title'] = $this->field_checkout_ready_title();
                $form_fields['checkout_ready_description'] = $this->field_checkout_ready_description();
                $form_fields['checkout_ready_description_link'] = $this->field_checkout_ready_description_link();
                $form_fields[WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY] = $this->field_currency_conversion($this);
            }
        }

        if (is_admin()) {
            $this->normalizeCommonAdminFields();
        }

        return $form_fields;
    }

    /**
     * @return array
     */
    public function field_title()
    {
        $field_title = array(
            'title' => __('Title', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => '',
            'class' => 'hidden-field-mp-title mp-hidden-field',
            'default' => $this->title
        );
        return $field_title;
    }

    /**
     * @return array
     */
    public function field_description()
    {
        $field_description = array(
            'title' => __('Description', 'woocommerce-mercadopago'),
            'type' => 'text',
            'class' => 'hidden-field-mp-desc mp-hidden-field',
            'description' => '',
            'default' => $this->method_description
        );
        return $field_description;
    }

    /**
     * @param $formFields
     * @param $ordenation
     * @return array
     */
    public function sortFormFields($formFields, $ordenation)
    {
        $array = array();
        foreach ($ordenation as $order => $key) {
            if (!isset($formFields[$key])) {
                continue;
            }
            $array[$key] = $formFields[$key];
            unset($formFields[$key]);
        }
        return array_merge_recursive($array, $formFields);
    }

    /**
     * @return array
     */
    public function field_checkout_steps()
    {
        $checkout_steps = array(
            'title' => sprintf(
                '<div class="mp-row">
              <h4 class="mp-title-checkout-body mp-pb-20">' . __('<b>Follow these steps to activate Mercado Pago in your store:</b>', 'woocommerce-mercadopago') . '</h4>
              
              <div class="mp-col-md-2 mp-text-center mp-pb-10">
                <p class="mp-number-checkout-body">1</p>
                <p class="mp-text-steps mp-text-center mp-px-20">
                  ' . __('<b>Upload your credentials</b> depending on the country in which you are registered.', 'woocommerce-mercadopago') . '
                </p>
              </div>
            
              <div class="mp-col-md-2 mp-text-center mp-pb-10">
                <p class="mp-number-checkout-body">2</p>
                <p class="mp-text-steps mp-text-center mp-px-20">
                  ' . __('<b>Approve your account</b> to be able to charge.', 'woocommerce-mercadopago') . '
                </p>
              </div>

              <div class="mp-col-md-2 mp-text-center mp-pb-10">
                <p class="mp-number-checkout-body">3</p>
                <p class="mp-text-steps mp-text-center mp-px-20">
                  ' . __('<b>Add the basic information of your business</b> in the plugin configuration.', 'woocommerce-mercadopago') . '
                </p>
              </div>

              <div class="mp-col-md-2 mp-text-center mp-pb-10">
                <p class="mp-number-checkout-body">4</p>
                <p class="mp-text-steps mp-text-center mp-px-20">
                  ' . __('<b>Configure the payment preferences</b> for your customers.', 'woocommerce-mercadopago') . '
                </p>
              </div>

              <div class="mp-col-md-2 mp-text-center mp-pb-10">
              <p class="mp-number-checkout-body">5</p>
              <p class="mp-text-steps mp-text-center mp-px-20">
                ' . __('<b>Go to advanced settings</b> only when you want to change the presets.', 'woocommerce-mercadopago') . '
              </p>
            </div>
            </div>'
            ),
            'type' => 'title',
            'class' => 'mp_title_checkout'
        );
        return $checkout_steps;
    }

    /**
     * @return array
     */

    public function field_checkout_steps_link_homolog($country_link, $appliocation_id)
    {
        $checkout_steps_link_homolog = array(
            'title' => sprintf(
                __('Credentials are the keys we provide you to integrate quickly <br>and securely. You must have a %s in Mercado Pago to obtain and collect them <br>on your website. You do not need to know how to design or program to do it', 'woocommerce-mercadopago'),
                '<a href="https://www.mercadopago.com/' . $country_link . '/account/credentials/appliance?application_id=' . $appliocation_id . '" target="_blank">' . __('approved account', 'woocommerce-mercadopago') . '</a>'
            ),
            'type' => 'title',
            'class' => 'mp_homolog_text'
        );

        array_splice($this->field_forms_order, 4, 0, 'checkout_steps_link_homolog');
        return $checkout_steps_link_homolog;
    }

    /**
     * @param $label
     * @return array
     */
    public function field_checkout_country_title()
    {
        $checkout_country_title = array(
            'title' => __('In which country does your Mercado Pago account operate?', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $checkout_country_title;
    }

    /**
     * @return array
     */
    public function field_checkout_country($wc_country, $checkout_country)
    {
        $country = array(
            'AR' => 'mla', // Argentinian
            'BR' => 'mlb', // Brazil
            'CL' => 'mlc', // Chile
            'CO' => 'mco', // Colombia
            'MX' => 'mlm', // Mexico
            'PE' => 'mpe', // Peru
            'UY' => 'mlu', // Uruguay
        );

        $country_default = '';
        if (!empty($wc_country) && empty($checkout_country)) {
            $country_default = strlen($wc_country) > 2 ? substr($wc_country, 0, 2) : $wc_country;
            $country_default = array_key_exists($country_default, $country) ? $country[$country_default] : 'mla';
        }

        $checkout_country = array(
            'title' => __('Select your country', 'woocommerce-mercadopago'),
            'type' => 'select',
            'description' => __('Select the country in which you operate with Mercado Pago', 'woocommerce-mercadopago'),
            'default' => empty($checkout_country) ? $country_default : $checkout_country,
            'options' => array(
                'mla' => __('Argentina', 'woocommerce-mercadopago'),
                'mlb' => __('Brazil', 'woocommerce-mercadopago'),
                'mlc' => __('Chile', 'woocommerce-mercadopago'),
                'mco' => __('Colombia', 'woocommerce-mercadopago'),
                'mlm' => __('Mexico', 'woocommerce-mercadopago'),
                'mpe' => __('Peru', 'woocommerce-mercadopago'),
                'mlu' => __('Uruguay', 'woocommerce-mercadopago'),
            )
        );
        return $checkout_country;
    }

    /**
     * @return string
     */
    public function getApplicationId($mp_access_token_prod)
    {
        if (empty($mp_access_token_prod)) {
            return '';
        } else {
            $application_id = explode('-', $mp_access_token_prod);
            return $application_id[1];
        }
    }

    /**
     * @return array
     */
    public function field_checkout_btn_save()
    {
        $checkout_btn_save = array(
            'title' => sprintf(
                __('%s', 'woocommerce-mercadopago'),
                '<button name="save" class="button button-primary mp-save-button" type="submit" value="Save changes">' . __('Save Changes', 'woocommerce-mercadopago') . '</button>'
            ),
            'type' => 'title',
            'class' => ''
        );
        return $checkout_btn_save;
    }

    /**
     * @param $label
     * @return array
     */
    public function field_enabled($label)
    {
        $enabled = array(
            'title' => __('Activate checkout', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'no',
            'description' => __('Activate the Mercado Pago experience at the checkout of your store.', 'woocommerce-mercadopago'),
            'options' => array(
                'no' => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago')
            )
        );
        return $enabled;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_title()
    {
        $field_checkout_credential_title = array(
            'title' => __('Enter your credentials and choose how to operate', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $field_checkout_credential_title;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_mod_test_title()
    {
        $checkout_credential_mod_test_title = array(
            'title' => __('Test Mode', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_mt'
        );
        return $checkout_credential_mod_test_title;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_mod_test_description()
    {
        $checkout_credential_mod_test_description = array(
            'title' => __('By default, we activate the Sandbox test environment for you to test before you start selling.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text mp-mt--12'
        );
        return $checkout_credential_mod_test_description;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_mod_prod_title()
    {
        $checkout_credential_mod_prod_title = array(
            'title' => __('Production Mode', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_mt'
        );
        return $checkout_credential_mod_prod_title;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_mod_prod_description()
    {
        $checkout_credential_mod_prod_description = array(
            'title' => __('When you see that everything is going well, deactivate Sandbox, turn on Production and make way for your online sales.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text mp-mt--12'
        );
        return $checkout_credential_mod_prod_description;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_production()
    {
        $production_mode = $this->isProductionMode() ? 'yes' : 'no';
        $checkout_credential_production = array(
            'title' => __('Production', 'woocommerce-mercadopago'),
            'type' => 'select',
            'description' => __('Choose “Yes” only when you’re ready to sell. Switch to “No” to activate Testing mode.', 'woocommerce-mercadopago'),
            'default' => $this->id == 'woo-mercado-pago-basic' && $this->clientid_old_version ? 'yes' : $production_mode,
            'options' => array(
                'no' => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago')
            )
        );
        return $checkout_credential_production;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_link($country)
    {
        $checkout_credential_link = array(
            'title' => sprintf(
                '%s',
                '<table class="form-table" id="mp_table_7">
                    <tbody>
                        <tr valign="top">
                            <th scope="row" id="mp_field_text">
                                <label>' . __('Load credentials', 'woocommerce-mercadopago') . '</label>
                            </th>
                            <td class="forminp">
                                <fieldset>
                                    <a class="mp_general_links" href="https://www.mercadopago.com/' . $country . '/account/credentials" target="_blank">' . __('Search my credentials', 'woocommerce-mercadopago') . '</a>
                                    <p class="description mp-fw-400 mp-mb-0"></p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>'
            ),
            'type' => 'title',
        );
        return $checkout_credential_link;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_title_test()
    {
        $checkout_credential_title_test = array(
            'title' => __('Test credentials', 'woocommerce-mercadopago'),
            'type' => 'title',
        );
        return $checkout_credential_title_test;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_description_test()
    {
        $checkout_credential__description_test = array(
            'title' => __('With these keys you can do the tests you want..', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text mp-mt--12'
        );
        return $checkout_credential__description_test;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_publickey_test()
    {
        $mp_public_key_test = array(
            'title' => __('Public key', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => '',
            'default' => $this->getOption('_mp_public_key_test', ''),
            'placeholder' => 'TEST-00000000-0000-0000-0000-000000000000'
        );

        return $mp_public_key_test;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_accesstoken_test()
    {
        $mp_access_token_test = array(
            'title' => __('Access token', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => '',
            'default' => $this->getOption('_mp_access_token_test', ''),
            'placeholder' => 'TEST-000000000000000000000000000000000-000000-00000000000000000000000000000000-000000000'
        );

        return $mp_access_token_test;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_title_prod()
    {
        $checkout_credential_title_prod = array(
            'title' => __('Production credentials!', 'woocommerce-mercadopago'),
            'type' => 'title',
        );
        return $checkout_credential_title_prod;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_description_prod()
    {
        $checkout_credential__description_prod = array(
            'title' => __('With these keys you can receive real payments from your customers.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text mp-mt--12'
        );
        return $checkout_credential__description_prod;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_publickey_prod()
    {
        $mp_public_key_prod = array(
            'title' => __('Public key', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => '',
            'default' => $this->getOption('_mp_public_key_prod', ''),
            'placeholder' => 'APP-USR-00000000-0000-0000-0000-000000000000'

        );

        return $mp_public_key_prod;
    }

    /**
     * @return array
     */
    public function field_checkout_credential_accesstoken_prod()
    {
        $mp_public_key_prod = array(
            'title' => __('Access token', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => '',
            'default' => $this->getOption('_mp_access_token_prod', ''),
            'placeholder' => 'APP-USR-000000000000000000000000000000000-000000-00000000000000000000000000000000-000000000'
        );


        return $mp_public_key_prod;
    }

    /**
     * @return array
     */
    public function field_checkout_homolog_title()
    {
        $checkout_homolog_title = array(
            'title' => __('Approve your account, it will only take a few minutes', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $checkout_homolog_title;
    }

    /**
     * @return array
     */
    public function field_checkout_homolog_subtitle()
    {
        $checkout_homolog_subtitle = array(
            'title' => __('Complete this process to secure your customers data and comply with the regulations<br> and legal provisions of each country.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_text mp-mt--12'
        );
        return $checkout_homolog_subtitle;
    }

    /**
     * @return array
     */
    public function field_checkout_homolog_link($country_link, $appliocation_id)
    {
        $checkout_homolog_link = array(
            'title' => sprintf(
                __('%s', 'woocommerce-mercadopago'),
                '<a href="https://www.mercadopago.com/' . $country_link . '/account/credentials/appliance?application_id=' . $appliocation_id . '" target="_blank">' . __('Homologate account in Mercado Pago', 'woocommerce-mercadopago') . '</a>'
            ),
            'type' => 'title',
            'class' => 'mp_tienda_link'
        );
        return $checkout_homolog_link;
    }

    /**
     * @return array
     */
    public function field_mp_statement_descriptor()
    {
        $mp_statement_descriptor = array(
            'title' => __('Store name', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => __('This name will appear on your customers invoice.', 'woocommerce-mercadopago'),
            'default' => $this->getOption('mp_statement_descriptor', __('Mercado Pago', 'woocommerce-mercadopago')),
        );
        return $mp_statement_descriptor;
    }

    /**
     * @return array
     */
    public function field_category_store()
    {
        $category_store = WC_WooMercadoPago_Module::$categories;
        $option_category = array();
        for ($i = 0; $i < count($category_store['store_categories_id']); $i++) {
            $option_category[$category_store['store_categories_id'][$i]] = __($category_store['store_categories_id'][$i], 'woocommerce-mercadopago');
        }
        $field_category_store = array(
            'title' => __('Store Category', 'woocommerce-mercadopago'),
            'type' => 'select',
            'description' => __('What category do your products belong to? Choose the one that best characterizes them (choose "other" if your product is too specific).', 'woocommerce-mercadopago'),
            'default' => $this->getOption('_mp_category_id', __('Categories', 'woocommerce-mercadopago')),
            'options' => $option_category
        );
        return $field_category_store;
    }

    /**
     * @return array
     */
    public function field_mp_store_identificator()
    {
        $store_identificator = array(
            'title' => __('Store ID', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => __('Use a number or prefix to identify orders and payments from this store.', 'woocommerce-mercadopago'),
            'default' => $this->getOption('_mp_store_identificator', 'WC-'),
        );
        return $store_identificator;
    }

    /**
     * @return array
     */
    public function field_mp_integrator_id()
    {
        $links_mp = WC_WooMercadoPago_Module::define_link_country();
        $integrator_id = array(
            'title' => __('Integrator ID', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' =>sprintf(
                __('Do not forget to enter your integrator_id as a certified Mercado Pago Partner. If you don`t have it, you can %s', 'woocommerce-mercadopago'),
                '<a target="_blank" href="https://www.mercadopago.' . $links_mp['sufix_url'] . 'developers/' . $links_mp['translate'] . '/guides/plugins/woocommerce/preferences/#bookmark_informações_do_negócio">' . __('request it now.', 'woocommerce-mercadopago') .
                '</a>'
            ),
            'default' => $this->getOption('_mp_integrator_id', '')
        );
        return $integrator_id;
    }

    /**
     * @return array
     */
    public function field_checkout_advanced_settings()
    {
        $checkout_options_explanation = array(
            'title' => __('Advanced adjustment', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle_bd'
        );
        return $checkout_options_explanation;
    }

    /**
     * @return array
     */
    public function field_debug_mode()
    {
        $debug_mode = array(
            'title' => __('Debug and Log mode', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'no',
            'description' => __('Record your store actions in our changes file to have more support information.', 'woocommerce-mercadopago'),
            'desc_tip' => __('We debug the information in our change file.', 'woocommerce-mercadopago'),
            'options' => array(
                'no' => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago')
            )
        );
        return $debug_mode;
    }

    /**
     * @return array
     */
    public function field_checkout_payments_subtitle()
    {
        $checkout_payments_subtitle = array(
            'title' => __('Basic Configuration', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_subtitle mp-mt-5 mp-mb-0'
        );
        return $checkout_payments_subtitle;
    }

    /**
     * @return array
     */
    public function field_checkout_options_description()
    {
        $checkout_options_description = array(
            'title' => __('Enable Mercado Pago in your online store, select the means of payment available to your customers and <br>define the maximum fees in which they can pay you.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text'
        );
        return $checkout_options_description;
    }

    /**
     * @return array
     */
    public function field_installments()
    {
        $installments = array(
            'title' => __('Max of installments', 'woocommerce-mercadopago'),
            'type' => 'select',
            'description' => __('What is the maximum quota with which a customer can buy?', 'woocommerce-mercadopago'),
            'default' => '24',
            'options' => array(
                '1' => __('1x installment', 'woocommerce-mercadopago'),
                '2' => __('2x installments', 'woocommerce-mercadopago'),
                '3' => __('3x installments', 'woocommerce-mercadopago'),
                '4' => __('4x installments', 'woocommerce-mercadopago'),
                '5' => __('5x installments', 'woocommerce-mercadopago'),
                '6' => __('6x installments', 'woocommerce-mercadopago'),
                '10' => __('10x installments', 'woocommerce-mercadopago'),
                '12' => __('12x installments', 'woocommerce-mercadopago'),
                '15' => __('15x installments', 'woocommerce-mercadopago'),
                '18' => __('18x installments', 'woocommerce-mercadopago'),
                '24' => __('24x installments', 'woocommerce-mercadopago')
            )
        );
        return $installments;
    }

    /**
     * @return string
     */
    public function getCountryLinkGuide($checkout)
    {
        $countryLink = array(
            'mla' => 'https://www.mercadopago.com.ar/developers/es/', // Argentinian
            'mlb' => 'https://www.mercadopago.com.br/developers/pt/', // Brazil
            'mlc' => 'https://www.mercadopago.cl/developers/es/', // Chile
            'mco' => 'https://www.mercadopago.com.co/developers/es/', // Colombia
            'mlm' => 'https://www.mercadopago.com.mx/developers/es/', // Mexico
            'mpe' => 'https://www.mercadopago.com.pe/developers/es/', // Peru
            'mlu' => 'https://www.mercadopago.com.uy/developers/es/', // Uruguay
        );
        return $countryLink[$checkout];
    }

    /**
     * @return array
     */
    public function field_custom_url_ipn()
    {
        $custom_url_ipn = array(
            'title' => __('URL for IPN', 'woocommerce-mercadopago'),
            'type' => 'text',
            'description' => sprintf(
                __('Enter a URL to receive payment notifications. In %s you can check more information.', 'woocommerce-mercadopago'),
                '<a href="' . $this->getCountryLinkGuide($this->checkout_country) . 'guides/notifications/ipn/">' . __('our guides', 'woocommerce-mercadopago') .
                '</a>'
            ),
            'default' => '',
            'desc_tip' => __('IPN (Instant Payment Notification) es una notificación de eventos que se realizan en tu plataforma y que se envía de un servidor a otro mediante una llamada HTTP POST. Consulta más información en nuestras guías.', 'woocommerce-services')
        );
        return $custom_url_ipn;
    }

    /**
     * @return array
     */
    public function field_checkout_payments_advanced_description()
    {
        $checkout_payments_advanced_description = array(
            'title' => __('Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago'),
            'type' => 'title',
            'class' => 'mp_small_text mp-mt--12 mp-mb-18'
        );
        return $checkout_payments_advanced_description;
    }

    /**
    * @return array
    */
    public function field_coupon_mode()
    {
        return array(
            'title' => __('Discount coupons', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'no',
            'description' => __('Will you offer discount coupons to customers who buy with Mercado Pago?', 'woocommerce-mercadopago'),
            'options' => array(
                'no' => __('No', 'woocommerce-mercadopago'),
                'yes' => __('Yes', 'woocommerce-mercadopago')
            )
        );
    }

    /**
     * @return array
     */
    public function field_no_credentials()
    {
        $noCredentials = array(
            'title' => sprintf(
                __('It appears that your credentials are not properly configured.<br/>Please, go to %s and configure it.', 'woocommerce-mercadopago'),
                '<a href="' . esc_url(admin_url('admin.php?page=mercado-pago-settings')) . '">' . __('Market Payment Configuration', 'woocommerce-mercadopago') .
                '</a>'
            ),
            'type' => 'title'
        );
        return $noCredentials;
    }

    /**
     * @return array
     */
    public function field_binary_mode()
    {
        $binary_mode = array(
            'title' => __('Binary mode', 'woocommerce-mercadopago'),
            'type' => 'select',
            'default' => 'no',
            'description' => __('Accept and reject payments automatically. Do you want us to activate it?', 'woocommerce-mercadopago'),
            'desc_tip' => __('Si activas el modo binario no podrás dejar pagos pendientes. Esto puede afectar la prevención de fraude. Dejalo inactivo para estar respaldado por nuestra propia herramienta.', 'woocommerce-services'),
            'options' => array(
                'yes' => __('Yes', 'woocommerce-mercadopago'),
                'no' => __('No', 'woocommerce-mercadopago')
            )
        );
        return $binary_mode;
    }

    /**
     * @return array
     */
    public function field_gateway_discount()
    {
        $gateway_discount = array(
            'title' => __('Discounts per purchase with Mercado Pago', 'woocommerce-mercadopago'),
            'type' => 'number',
            'description' => __('Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'default' => '0',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0',
                'max' => '99'
            )
        );
        return $gateway_discount;
    }

    /**
     * @return array
     */
    public function field_commission()
    {
        $commission = array(
            'title' => __('Commission for purchase with Mercado Pago', 'woocommerce-mercadopago'),
            'type' => 'number',
            'description' => __('Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago'),
            'default' => '0',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0',
                'max' => '99'
            )
        );
        return $commission;
    }

    public function field_currency_conversion(WC_WooMercadoPago_PaymentAbstract $method)
    {
        return WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->getField($method);
    }

    /**
     * @return array
     */
    public function field_checkout_ready_title()
    {

        if ($this->isProductionMode()) {
            $message_ready_title = __('Everything ready for the takeoff of your sales?', 'woocommerce-mercadopago');
        } else {
            $message_ready_title = __('Everything set up? Go to your store in Sandbox mode', 'woocommerce-mercadopago');
        }

        $checkout_options_title = array(
            'title' => $message_ready_title,
            'type' => 'title',
            'class' => 'mp_subtitle_bd_mb mp-mg-0'
        );
        return $checkout_options_title;
    }

    /**
     * @return array
     */
    public function field_checkout_ready_description()
    {
        if ($this->isProductionMode()) {
            $message_ready_description = __('Visit your store as if you were one of your customers and check that everything is fine. If you already went to Production,<br> bring your customers and increase your sales with the best online shopping experience.', 'woocommerce-mercadopago');
        } else {
            $message_ready_description = __('Visit your store and simulate a payment to check that everything is fine.', 'woocommerce-mercadopago');
        }

        $checkout_options_subtitle = array(
            'title' => $message_ready_description,
            'type' => 'title',
            'class' => 'mp_small_text'
        );
        return $checkout_options_subtitle;
    }

    /**
     * @return array
     */
    public function field_checkout_ready_description_link()
    {
        if ($this->isProductionMode()) {
            $message_link = __('Visit my store', 'woocommerce-mercadopago');
        } else {
            $message_link = __('I want to test my sales', 'woocommerce-mercadopago');
        }

        $checkout_options_subtitle = array(
            'title' => sprintf(
                __('%s', 'woocommerce-mercadopago'),
                '<a href="' . get_site_url() . '" target="_blank">' . $message_link . '</a>'
            ),
            'type' => 'title',
            'class' => 'mp_tienda_link'
        );
        return $checkout_options_subtitle;
    }

    /**
     * @return bool
     */
    public function is_available()
    {
        if (!did_action('wp_loaded')) {
            return false;
        }
        global $woocommerce;
        $w_cart = $woocommerce->cart;
        // Check for recurrent product checkout.
        if (isset($w_cart)) {
            if (WC_WooMercadoPago_Module::is_subscription($w_cart->get_cart())) {
                return false;
            }
        }

        $_mp_public_key = $this->getPublicKey();
        $_mp_access_token = $this->getAccessToken();
        $_site_id_v1 = $this->getOption('_site_id_v1');

        if (!isset($this->settings['enabled'])) {
            return false;
        }

        return ('yes' == $this->settings['enabled']) && !empty($_mp_public_key) && !empty($_mp_access_token) && !empty($_site_id_v1);
    }

    /**
     * @return mixed
     */
    public function admin_url()
    {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
            return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id);
        }
        return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . get_class($this));
    }

    /**
     * @return array
     */
    public function getCommonConfigs()
    {
        return self::COMMON_CONFIGS;
    }

    /**
     * @return bool
     */
    public function isTestUser()
    {
        if ($this->isProductionMode()) {
            return false;
        }
        return true;
    }

    /**
     * @return MP|null
     * @throws WC_WooMercadoPago_Exception
     */
    public function getMpInstance()
    {
        $mp = WC_WooMercadoPago_Module::getMpInstanceSingleton($this);
        if (!empty($mp)) {
            $mp->sandbox_mode($this->sandbox);
        }
        return $mp;
    }

    /**
     * Disable Payments MP
     */
    public function disableAllPaymentsMethodsMP()
    {
        $gateways = apply_filters('woocommerce_payment_gateways', array());
        foreach ($gateways as $gateway) {
            if (!strpos($gateway, "MercadoPago")) {
                continue;
            }

            $key = 'woocommerce_' . $gateway::getId() . '_settings';
            $options = get_option($key);
            if (!empty($options)) {
                if (isset($options['checkout_credential_prod']) && $options['checkout_credential_prod'] == 'yes' && !empty($this->mp_access_token_prod)) {
                    continue;
                }

                if (isset($options['checkout_credential_prod']) && $options['checkout_credential_prod'] == 'no' && !empty($this->mp_access_token_test)) {
                    continue;
                }

                $options['enabled'] = 'no';
                update_option($key, apply_filters('woocommerce_settings_api_sanitized_fields_' . $gateway::getId(), $options));
            }
        }
    }

    /**
     * @return bool
     */
    public function isCurrencyConvertable()
    {
        return $this->currency_convertion;
    }

    /**
     * @return bool
     */
    public function isProductionMode()
    {
        $this->updateCredentialProduction();
        return $this->getOption('checkout_credential_prod', get_option('checkout_credential_prod', 'no')) === 'yes';
    }

    /**
     *
     */
    public function updateCredentialProduction()
    {
        if(!empty($this->getOption('checkout_credential_prod', null))){
            return;
        }

        $gateways = apply_filters('woocommerce_payment_gateways', array());
        foreach ($gateways as $gateway) {
            if (!strpos($gateway, "MercadoPago")) {
                continue;
            }

            $key = 'woocommerce_' . $gateway::getId() . '_settings';
            $options = get_option($key);
            if (!empty($options)) {
                if (!isset($options['checkout_credential_production']) || empty($options['checkout_credential_production']) ){
                    continue;
                }
                $options['checkout_credential_prod'] = $options['checkout_credential_production'];
                update_option($key, apply_filters('woocommerce_settings_api_sanitized_fields_' . $gateway::getId(), $options));
            }
        }
    }
}

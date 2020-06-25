<?php
/**
 * Class WC_WooMercadoPago_Hook_Abstract
 */

abstract class WC_WooMercadoPago_Hook_Abstract
{
    public $payment;
    public $class;
    public $mpInstance;
    public $publicKey;
    public $testUser;
    public $siteId;

    /**
     * WC_WooMercadoPago_Hook_Abstract constructor.
     * @param $payment
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->class = get_class($payment);
        $this->mpInstance = $payment->mp;
        $this->publicKey = $payment->getPublicKey();
        $this->testUser = get_option('_test_user_v1');
        $this->siteId = get_option('_site_id_v1');

        $this->loadHooks();
    }

    /**
     * Load Hooks
     */
    public function loadHooks()
    {
        add_action('woocommerce_update_options_payment_gateways_' . $this->payment->id, array($this, 'custom_process_admin_options'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_discount'), 10);
        add_filter('woocommerce_gateway_title', array($this, 'get_payment_method_title'), 10, 2);

        add_action('admin_notices', function() {
            WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->notices($this->payment);
        });

        if (!empty($this->payment->settings['enabled']) && $this->payment->settings['enabled'] == 'yes') {
            add_action('woocommerce_after_checkout_form', array($this, 'add_mp_settings_script'));
            add_action('woocommerce_thankyou', array($this, 'update_mp_settings_script'));
        }
    }

    /**
     * @param $checkout
     */
    public function add_discount_abst($checkout)
    {
        if (isset($checkout['discount']) && !empty($checkout['discount']) && isset($checkout['coupon_code']) && !empty($checkout['coupon_code']) && $checkout['discount'] > 0 && WC()->session->chosen_payment_method == $this->payment->id) {
            $this->payment->log->write_log(__FUNCTION__, $this->class . 'trying to apply discount...');
            $value = ($this->payment->site_data['currency'] == 'COP' || $this->payment->site_data['currency'] == 'CLP') ? floor($checkout['discount'] / $checkout['currency_ratio']) : floor($checkout['discount'] / $checkout['currency_ratio'] * 100) / 100;
            global $woocommerce;
            if (apply_filters('wc_mercadopago_custommodule_apply_discount', 0 < $value, $woocommerce->cart)) {
                $woocommerce->cart->add_fee(sprintf(__('Discount for coupon %s', 'woocommerce-mercadopago'), esc_attr($checkout['campaign'])), ($value * -1), false);
            }
        }
    }

    /**
     * Analytics Save
     */
    public function send_settings_mp()
    {
        if (!empty($this->payment->getAccessToken()) && !empty($this->siteId)) {
            if (!$this->testUser) {
                $this->payment->mp->analytics_save_settings($this->define_settings_to_send());
            }
        }
    }

    /**
     * @return array
     */
    public function define_settings_to_send()
    {
        $infra_data = WC_WooMercadoPago_Module::get_common_settings();
        switch (get_class($this->payment)) {
            case 'WC_WooMercadoPago_BasicGateway':
                $infra_data['checkout_basic'] = ($this->payment->settings['enabled'] == 'yes' ? 'true' : 'false');
                break;
            case 'WC_WooMercadoPago_CustomGateway':
                $infra_data['checkout_custom_credit_card'] = ($this->payment->settings['enabled'] == 'yes' ? 'true' : 'false');
                $infra_data['checkout_custom_credit_card_coupon'] = ($this->payment->settings['coupon_mode'] == 'yes' ? 'true' : 'false');
                break;
            case 'WC_WooMercadoPago_TicketGateway':
                $infra_data['checkout_custom_ticket'] = ($this->payment->settings['enabled'] == 'yes' ? 'true' : 'false');
                $infra_data['checkout_custom_ticket_coupon'] = ($this->payment->settings['coupon_mode'] == 'yes' ? 'true' : 'false');
                break;
        }
        return $infra_data;
    }

    /**
     * @param $title
     * @return string
     */
    public function get_payment_method_title($title, $id)
    {
        if (!preg_match('/woo-mercado-pago/', $id)) {
            return $title;
        }

        if ($id != $this->payment->id) {
            return $title;
        }

        if (!is_checkout() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            return $title;
        }
        if ($title != $this->payment->title && ($this->payment->commission == 0 && $this->payment->gateway_discount == 0)) {
            return $title;
        }
        if (!is_numeric($this->payment->gateway_discount) || $this->payment->commission > 99 || $this->payment->gateway_discount > 99) {
            return $title;
        }

        $total = (float) WC()->cart->subtotal;
        $price_discount = $total * ($this->payment->gateway_discount / 100);
        $price_commission = $total * ($this->payment->commission / 100);

        if ($this->payment->gateway_discount > 0 && $this->payment->commission > 0) {
            $title .= ' (' . __('discount of', 'woocommerce-mercadopago') . ' ' . strip_tags(wc_price($price_discount)) . __(' and fee of', 'woocommerce-mercadopago') . ' ' . strip_tags(wc_price($price_commission)) . ')';
        } elseif ($this->payment->gateway_discount > 0) {
            $title .= ' (' . __('discount of', 'woocommerce-mercadopago') . ' ' . strip_tags(wc_price($price_discount)) . ')';
        } elseif ($this->payment->commission > 0) {
            $title .= ' (' . __('fee of', 'woocommerce-mercadopago') . ' ' . strip_tags(wc_price($price_commission)) . ')';
        }
        return $title;
    }

    /**
     * MP Settings Script
     */
    public function add_mp_settings_script()
    {
        if (!empty($this->publicKey) && !$this->testUser) {
            $woo = WC_WooMercadoPago_Module::woocommerce_instance();
            $gateways = $woo->payment_gateways->get_available_payment_gateways();

            $available_payments = array();
            foreach ($gateways as $gateway) {
                $available_payments[] = $gateway->id;
            }

            $available_payments = str_replace('-', '_', implode(', ', $available_payments));
            $logged_user_email = null;
            if (wp_get_current_user()->ID != 0) {
                $logged_user_email = wp_get_current_user()->user_email;
            }
        }
    }

    /**
     * @param $order_id
     * @return string|void
     */
    public function update_mp_settings_script($order_id)
    {
        if (!empty($this->publicKey) && !$this->testUser) {
            // $this->payment->log->write_log(__FUNCTION__, 'updating order of ID ' . $order_id);
            // return '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
            // <script type="text/javascript">
            // 	try {
            // 		var MA = ModuleAnalytics;
            //         MA.setPublicKey(' . $this->publicKey . ');
            // 		MA.setPaymentType("basic");
            // 		MA.setCheckoutType("basic");
            // 		MA.put();
            // 	} catch(err) {}
            // </script>';
        }
    }

    /**
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    public function custom_process_admin_options()
    {
        $oldData = array();

        $valueCredentialProduction = null;
        $this->payment->init_settings();
        $post_data = $this->payment->get_post_data();
        foreach ($this->payment->get_form_fields() as $key => $field) {
            if ('title' !== $this->payment->get_field_type($field)) {
                $value = $this->payment->get_field_value($key, $field, $post_data);
                $oldData[$key] = isset($this->payment->settings[$key]) ?  $this->payment->settings[$key] : null;
                if ($key == 'checkout_credential_prod') {
                    $valueCredentialProduction = $value;
                }
                $commonConfigs = $this->payment->getCommonConfigs();
                if (in_array($key, $commonConfigs)) {

                    if ($this->validateCredentials($key, $value, $valueCredentialProduction)) {
                        continue;
                    }
                    update_option($key, $value, true);
                }

                $value = $this->payment->get_field_value($key, $field, $post_data);
                $this->payment->settings[$key] = $value;
            }
        }
        $this->send_settings_mp();

        $result = update_option($this->payment->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->payment->id, $this->payment->settings));

        WC_WooMercadoPago_Helpers_CurrencyConverter::getInstance()->scheduleNotice(
            $this->payment,
            $oldData,
            $this->payment->settings
        );

        return $result;
    }

    /**
     * @param $key
     * @param $value
     * @param null $valueCredentialProduction
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    private function validateCredentials($key, $value, $valueCredentialProduction = null)
    {
        if ($key == '_mp_public_key_test' && $value == $this->payment->mp_public_key_test) {
            return true;
        }

        if ($key == '_mp_access_token_test' && $value == $this->payment->mp_access_token_test) {
            return true;
        }

        if ($key == '_mp_public_key_prod' && $value == $this->payment->mp_public_key_prod) {
            return true;
        }

        if ($key == '_mp_access_token_prod' && $value == $this->payment->mp_access_token_prod) {
            return true;
        }

        if ($this->validatePublicKey($key, $value)) {
            return true;
        }

        if ($this->validateAccessToken($key, $value, $valueCredentialProduction)) {
            return true;
        }

        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    private function validatePublicKey($key, $value)
    {
        if ($key != '_mp_public_key_test' && $key != '_mp_public_key_prod') {
            return false;
        }

        if ($key == '_mp_public_key_prod' && strpos($value, 'APP_USR') === false) {
            update_option($key, '', true);
            return true;
        }

        if ($key == '_mp_public_key_test' && strpos($value, 'TEST') === false) {
            update_option($key, '', true);
            return true;
        }

        if (WC_WooMercadoPago_Credentials::public_key_is_valid($value) === false) {
            update_option($key, '', true);

            if ($key == '_mp_public_key_prod') {
                add_action('admin_notices', array($this, 'noticeInvalidPublicKeyProd'));
            } else {
                add_action('admin_notices', array($this, 'noticeInvalidPublicKeyTest'));
            }
            return true;
        }

        return false;
    }

    /**
     * @param $key
     * @param $value
     * @param null $isProduction
     * @return bool
     * @throws WC_WooMercadoPago_Exception
     */
    private function validateAccessToken($key, $value, $isProduction = null)
    {
        if ($key != '_mp_access_token_prod' && $key != '_mp_access_token_test') {
            return false;
        }

        if ($key == '_mp_access_token_prod' && strpos($value, 'APP_USR') === false) {
            update_option($key, '', true);
            return true;
        }

        if ($key == '_mp_access_token_test' && strpos($value, 'TEST') === false) {
            update_option($key, '', true);
            return true;
        }

        if (empty($isProduction)) {
            $isProduction = $this->payment->isProductionMode();
        }

        if (WC_WooMercadoPago_Credentials::access_token_is_valid($value)) {
            update_option($key, $value, true);

            if ($key == '_mp_access_token_prod') {
                $homolog_validate = $this->mpInstance->homologValidate($value);
                update_option('homolog_validate', $homolog_validate, true);
                if ($isProduction == 'yes' && $homolog_validate == 0) {
                    add_action('admin_notices', array($this, 'enablePaymentNotice'));
                }
            }

            if (
                ($key == '_mp_access_token_prod' && $isProduction == 'yes') || ($key == '_mp_access_token_test' && $isProduction == 'no')
            ) {
                WC_WooMercadoPago_Credentials::updatePaymentMethods($this->mpInstance, $value);
                WC_WooMercadoPago_Credentials::updateTicketMethod($this->mpInstance, $value);
            }
            return true;
        }

        if ($key == '_mp_access_token_prod') {
            update_option('_mp_public_key_prod', '', true);
            WC_WooMercadoPago_Credentials::setNoCredentials();
            add_action('admin_notices', array($this, 'noticeInvalidProdCredentials'));
        } else {
            update_option('_mp_public_key_test', '', true);
            add_action('admin_notices', array($this, 'noticeInvalidTestCredentials'));
        }

        update_option($key, '', true);
        return true;
    }

    /**
     *  ADMIN NOTICE
     */
    public function noticeInvalidPublicKeyProd()
    {
        $type = 'error';
        $message = __('<b>Public Key</b> production credential is invalid. Review the field to receive real payments.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     *  ADMIN NOTICE
     */
    public function noticeInvalidPublicKeyTest()
    {
        $type = 'error';
        $message = __('<b>Public Key</b> test credential is invalid. Review the field to perform tests in your store.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     *  ADMIN NOTICE
     */
    public function noticeInvalidProdCredentials()
    {
        $type = 'error';
        $message = __('<b>Access Token</b> production credential is invalid. Remember that it must be complete to receive real payments.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

    /**
     *  ADMIN NOTICE
     */
    public function noticeInvalidTestCredentials()
    {
        $type = 'error';
        $message = __('<b>Access Token</b> test credential is invalid. Review the field to perform tests in your store.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }

     /**
     * Enable Payment Notice
     */
    public function enablePaymentNotice()
    {
        $type = 'notice-warning';
        $message = __('Fill in your credentials to enable payment methods.', 'woocommerce-mercadopago');
        echo WC_WooMercadoPago_Notices::getAlertFrame($message, $type);
    }


}

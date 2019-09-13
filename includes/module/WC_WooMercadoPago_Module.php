<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Module
 */
class WC_WooMercadoPago_Module extends WC_WooMercadoPago_Configs
{
    public static $categories = array();
    public static $country_configs = array();
    public static $site_data;
    public static $instance = null;
    public static $mpInstancePayment = array();
    public static $mpInstance = null;
    public static $payments_name = null;

    /**
     * WC_WooMercadoPago_Module constructor.
     */
    public function __construct()
    {
        try {
            if (!class_exists('WC_Payment_Gateway')) {
                add_action('admin_notices', array($this, 'notify_woocommerce_miss'));
            }

            $this->loadConfigs();
            $this->loadLog();         
            $this->loadHooks();
            $this->loadPreferences();
            $this->loadPayments();
            $this->loadNotifications();
            $this->loadShipments();

            add_filter('woocommerce_available_payment_gateways', array($this, 'filterPaymentMethodByShipping'));
            add_filter('plugin_action_links_' . WC_MERCADOPAGO_BASENAME, array($this, 'woomercadopago_settings_link'));
            add_filter('plugin_row_meta', array($this, 'mp_plugin_row_meta'), 10, 2);
            
            if (is_admin()) {
              if(isset($_REQUEST['section'])){
                $credentials = new WC_WooMercadoPago_Credentials();
                if (!$credentials->tokenIsValid()) {
                    add_action('admin_notices', array($this, 'enablePaymentNotice'));
                }
               }
               self::loadMercadoEnviosAdmin();
           }
        } catch (Exception $e) {
            $log = WC_WooMercadoPago_Log::init_mercado_pago_log('WC_WooMercadoPago_Module');
            $log->write_log('__construct: ', $e->getMessage());
        }
    }

    /**
     * @param $payment
     * @return MP
     * @throws WC_WooMercadoPago_Exception
     */
    public static function getMpInstance($payment = null)
    {
        $credentials = new WC_WooMercadoPago_Credentials($payment);
        $validateCredentialsType = $credentials->validateCredentialsType();
        if ($validateCredentialsType == WC_WooMercadoPago_Credentials::TYPE_ACCESS_TOKEN) {
            $mp = new MP($credentials->accessToken);
            $mp->setPaymentClass($payment);
        }
        if ($validateCredentialsType == WC_WooMercadoPago_Credentials::TYPE_ACCESS_CLIENT) {
            $mp = new MP($credentials->clientId, $credentials->clientSecret);
            $mp->setPaymentClass($payment);
            if (!empty($payment)) {
                $payment->sandbox = false;
            }
        }

        if (!isset($mp)) {
            return false;
        }

        $email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
        $mp->set_email($email);

        $locale = get_locale();
        $locale = (strpos($locale, '_') !== false && strlen($locale) == 5) ? explode('_', $locale) : array('', '');
        $mp->set_locale($locale[1]);

        return $mp;
    }

    /**
     * @param null $payment
     * @return MP|null
     * @throws WC_WooMercadoPago_Exception
     */
    public static function getMpInstanceSingleton($payment = null)
    {
        $mp = null;
        if (!empty($payment)) {
            $class = get_class($payment);
            if (!isset(self::$mpInstancePayment[$class])) {
                self::$mpInstancePayment[$class] = self::getMpInstance($payment);
                $mp = self::$mpInstancePayment[$class];
                if (!empty($mp)) {
                    return $mp;
                }
            }
        }

        if (self::$mpInstance === null || empty($mp)) {
            self::$mpInstance = self::getMpInstance();
        }

        return self::$mpInstance;
    }

    /**
     * @return WC_WooMercadoPago_Module|null
     * Singleton
     */
    public static function init_mercado_pago_class()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Load Config / Categories
     */
    public function loadConfigs()
    {
        $configs = new parent();
        self::$categories = $configs->getCategories();
        self::$country_configs = $configs->getCountryConfigs();
        self::$site_data = self::get_site_data();
        self::$payments_name = self::setPaymentGateway();
    }

    /**
     *  Load Hooks
     */
    public function loadHooks()
    {
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Abstract.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Basic.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Custom.php';
        include_once dirname(__FILE__) . '/../payments/hooks/WC_WooMercadoPago_Hook_Ticket.php';
    }

    /**
     * Load Preferences Classes
     */
    public function loadPreferences()
    {
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceAbstract.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceBasic.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceCustom.php';
        include_once dirname(__FILE__) . '/preference/WC_WooMercadoPago_PreferenceTicket.php';
    }

    /**
     *  Load Payment Classes
     */
    public function loadPayments()
    {
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_PaymentAbstract.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_BasicGateway.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_CustomGateway.php';
        include_once dirname(__FILE__) . '/../payments/WC_WooMercadoPago_TicketGateway.php';
        include_once dirname(__FILE__) . '/../payments/mercadoenvios/WC_WooMercadoPago_Product_Recurrent.php';
        add_filter('woocommerce_payment_gateways', array($this, 'setPaymentGateway'));
    }

    /**
     *
     */
    public function loadNotifications()
    {
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_Abstract.php';
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_IPN.php';
        include_once dirname(__FILE__) . '/../notification/WC_WooMercadoPago_Notification_Webhook.php';
    }

    /**
     *
     */
    public function loadLog()
    {
        include_once dirname(__FILE__) . '/log/WC_WooMercadoPago_Log.php';
    }
 
    /**
     *  Load Shipment Types
     */
    public function loadShipments()
    {
        include_once dirname(__FILE__) . '/../shipment/WC_MercadoEnvios_Shipping_Abstract.php';
        include_once dirname(__FILE__) . '/../shipment/WC_MercadoEnvios_Shipping_Express.php';
        include_once dirname(__FILE__) . '/../shipment/WC_MercadoEnvios_Shipping_Normal.php';
        include_once dirname(__FILE__) . '/../shipment/WC_MercadoEnvios_Package.php';
        add_filter('woocommerce_shipping_methods', array($this, 'setShipping'));
    }

    /**
     * Load Admin Classes
     */
    public static function loadMercadoEnviosAdmin()
    {
        include_once dirname(__FILE__) . '/../admin/WC_MercadoEnvios_Admin_Orders.php';
    }

    /**
     * @param $methods
     * @return array
     */
    public function filterPaymentMethodByShipping($methods)
    {
        $session = WC()->session;
        if (!isset($session)) {
            return $methods;
        }
        $chosen_methods = $session->get('chosen_shipping_methods');
        $chosen_shipping = $chosen_methods[0];
        if (strpos($chosen_shipping, 'woo-mercado-pago-me-normal') !== false || strpos($chosen_shipping, 'woo-mercado-pago-me-express') !== false) {
            $new_array = array();
            foreach ($methods as $payment_method => $payment_method_object) {
                if ($payment_method == 'woo-mercado-pago-basic') {
                    $new_array['woo-mercado-pago-basic'] = $payment_method_object;
                }
            }
            return $new_array;
        }
        return $methods;
    }

    /**
     * Enable Payment Notice
     */
    public function enablePaymentNotice()
    {
        $message = __('Complete your credentials to enable the payment of method.', 'woocommerce-mercadopago');
        echo '<div class="notice notice-warning is-dismissible">  
                    <p style="font-size:13px">
                        <strong>MERCADO PAGO:</strong> ' . $message . '
                    </p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">' . __('Discard', 'woocommerce-mercadopago') . '</span>
                    </button>
              </div>';
    }

    /**
     * Summary: Places a warning error to notify user that WooCommerce is missing.
     * Description: Places a warning error to notify user that WooCommerce is missing.
     */
    public function notify_woocommerce_miss()
    {
        echo '<div class="error"><p>' .
            sprintf(
                __('The payment module of Woo Mercado depends on the latest version of %s to run!', 'woocommerce-mercadopago'),
                '<a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
            ) .
            '</p></div>';
    }

    // Add settings link on plugin page.
    public function woomercadopago_settings_link($links)
    {
        $plugin_links = array();
        $plugin_links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Set up', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<a target="_blank" href="' . 'https://wordpress.org/support/plugin/woocommerce-mercadopago/reviews/?rate=5#new-post' . '">' . __('Your opinion helps us get better', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<br><a target="_blank" href="' . 'https://github.com/mercadopago/cart-woocommerce#installation' . '">' . __('Guides and Documentation', 'woocommerce-mercadopago') . '</a>';
        $plugin_links[] = '<a target="_blank" href="' . 'https://www.mercadopago.com.br/ajuda' . '">' . __('Report Problem', 'woocommerce-mercadopago') . '</a>';
        return array_merge($plugin_links, $links);
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param mixed $links Plugin Row Meta.
     * @param mixed $file Plugin Base file.
     *
     * @return array
     */
    public function mp_plugin_row_meta($links, $file)
    {
        if (WC_MERCADOPAGO_BASENAME === $file) {
            $new_link = array();
            $new_link[] = $links[0];
            $new_link[] = esc_html__('By Mercado Pago', 'woocommerce-mercadopago');

            return $new_link;
        }

        return (array) $links;
    }

    // ============================================================

    // Save and valid Sponsor_id if the site_id returned that api is equal site_id costumers
    public static function is_valid_sponsor_id($sponsor_id)
    {
        $access_token = get_option('_mp_access_token_prod', '');
        $site_id = get_option('_site_id_v1', '');

        $varify_sponsor = true;

        if (empty($access_token)) {
            $varify_sponsor = false;
        } elseif ($sponsor_id == '') {
            $varify_sponsor = false;
            update_option('_mp_sponsor_id', $sponsor_id, true);
        } elseif (!is_numeric($sponsor_id)) {
            $varify_sponsor = false;
            echo '<div class="error"><p>' . __('The <strong> Sponsor ID  </strong> must be valid!', 'woocommerce-mercadopago') . '</p></div>';
        } elseif ($sponsor_id != get_option('_mp_sponsor_id', '')) {
            $varify_sponsor = true;
        } elseif ($site_id != get_option('_mp_sponsor_site_id', '')) {
            $varify_sponsor = true;
        } else {
            $varify_sponsor = false;
        }

        if ($varify_sponsor) {
            $mp_sponsor_id = WC_WooMercadoPago_Module::getMpInstanceSingleton();
            $get_sponor_id = $mp_sponsor_id->get('/users/' . $sponsor_id, $access_token, false);
            if (!is_wp_error($get_sponor_id) && ($get_sponor_id['status'] == 200 || $get_sponor_id['status'] == 201)) {
                if ($get_sponor_id['response']['site_id'] == $site_id) {
                    update_option('_mp_sponsor_id', $sponsor_id, true);
                    update_option('_mp_sponsor_site_id', $get_sponor_id['response']['site_id'], true);
                } else {
                    echo '<div class="error"><p>' . __('The <strong>Sponsor ID</strong> must be from the same country as the seller!', 'woocommerce-mercadopago') . '</p></div>';
                    update_option('_mp_sponsor_id', '', true);
                }
            } else {
                echo '<div class="error"><p>' . __('The <strong> Sponsor ID  </strong> must be valid!', 'woocommerce-mercadopago') . '</p></div>';
                update_option('_mp_sponsor_id', '', true);
            }
        }
    }

    /**
     * Summary: Check if we have valid credentials for v1.
     * Description: Check if we have valid credentials.
     * @return boolean true/false depending on the validation result.
     */


    // Get WooCommerce instance
    public static function woocommerce_instance()
    {
        if (function_exists('WC')) {
            return WC();
        } else {
            global $woocommerce;
            return $woocommerce;
        }
    }

    // Get common error messages
    public static function get_common_error_messages($key)
    {
        if ($key === 'Invalid payment_method_id') {
            return __('The payment method is not valid or not available.', 'woocommerce-mercadopago');
        }
        if ($key === 'Invalid transaction_amount') {
            return __('The transaction amount cannot be processed by Mercado Pago.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Currency not supported; Amounts below the minimum or above the maximum allowed.', 'woocommerce-mercadopago');
        }
        if ($key === 'Invalid users involved') {
            return __('The users are not valid.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Buyer and seller have the same account in Mercado Pago; The transaction involving production and test users.', 'woocommerce-mercadopago');
        }
        if ($key === 'Unauthorized use of live credentials') {
            return __('Unauthorized use of production credentials.', 'woocommerce-mercadopago') . ' ' .
                __('Possible causes: Use permission in use for the credential of the seller.', 'woocommerce-mercadopago');
        }
        return $key;
    }

    /**
     * Summary: Get the rate of conversion between two currencies.
     * Description: The currencies are the one used in WooCommerce and the one used in $site_id.
     * @return a float that is the rate of conversion.
     */
    public static function get_conversion_rate($used_currency)
    {
        $wc_currency = get_woocommerce_currency();
        $email = (wp_get_current_user()->ID != 0) ? wp_get_current_user()->user_email : null;
        MPRestClient::set_email($email);
        if (strlen($wc_currency) == 3 && strlen($used_currency) == 3) {
            $currency_obj = MPRestClient::get(
                array(
                    'uri' => '/currency_conversions/search?' .
                        'from=' . get_woocommerce_currency() .
                        '&to=' . $used_currency
                ),
                WC_WooMercadoPago_Constants::VERSION
            );
            if (isset($currency_obj['response'])) {
                $currency_obj = $currency_obj['response'];
                if (isset($currency_obj['ratio'])) {
                    return ((float) $currency_obj['ratio']);
                }
            }
        }
        return -1;
    }

    /**
     * @return array
     */
    public static function get_common_settings()
    {
        $w = WC_WooMercadoPago_Module::woocommerce_instance();
        $infra_data = array(
            'module_version' => WC_WooMercadoPago_Constants::VERSION,
            'platform' => 'WooCommerce',
            'platform_version' => $w->version,
            'code_version' => phpversion(),
            'so_server' => PHP_OS
        );
        return $infra_data;
    }

    /**
     * Summary: Get Sponsor ID to preferences.
     * Description: This function verifies, if the sponsor ID was configured,
     * if NO, return Sponsor ID determined of get_site_data(),
     * if YES return Sponsor ID configured on plugin
     * @return a string.
     */
    public static function get_sponsor_id()
    {
        $site_data = WC_WooMercadoPago_Module::get_site_data();
        $sponsor_id = get_option('_mp_sponsor_id', '');
        if ($sponsor_id == '' || empty($sponsor_id)) {
            return $site_data['sponsor_id'];
        } else {
            return $sponsor_id;
        }
    }


    /**
     * Summary: Get information about the used Mercado Pago account based in its site.
     * Description: Get information about the used Mercado Pago account based in its site.
     * @return an array with the information.
     */
    public static function get_site_data()
    {
        $site_id = get_option('_site_id_v1', '');
        if (isset($site_id) && !empty($site_id)) {
            return self::$country_configs[$site_id];
        } else {
            return null;
        }
    }

    // Fix to URL Problem : #038; replaces & and breaks the navigation.
    public static function fix_url_ampersand($link)
    {
        return str_replace('\/', '/', str_replace('&#038;', '&', $link));
    }

    /**
     * Summary: Find template's folder.
     * Description: Find template's folder.
     * @return a string that identifies the path.
     */
    public static function get_templates_path()
    {
        return plugin_dir_path(__FILE__) . '../../templates/';
    }

    /**
     * Summary: Get client id from access token.
     * Description: Get client id from access token.
     * @return the client id.
     */
    public static function get_client_id($at)
    {
        $t = explode('-', $at);
        if (count($t) > 0) {
            return $t[1];
        }
        return '';
    }

    // Check if an order is recurrent.
    public static function is_subscription($items)
    {
        $is_subscription = false;
        if (sizeof($items) == 1) {
            foreach ($items as $cart_item_key => $cart_item) {
                $is_recurrent = (method_exists($cart_item, 'get_meta')) ?
                    $cart_item->get_meta('_used_gateway') : get_post_meta($cart_item['product_id'], '_mp_recurring_is_recurrent', true);
                if ($is_recurrent == 'yes') {
                    $is_subscription = true;
                }
            }
        }
        return $is_subscription;
    }

    // Return boolean indicating if currency is supported.
    public static function is_supported_currency($site_id)
    {
        return get_woocommerce_currency() == WC_WooMercadoPago_Module::$country_configs[$site_id]['currency'];
    }

    public static function build_currency_conversion_err_msg($currency)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/error.png', __FILE__) . '"> ' .
            __('ERROR: It was not possible to convert the unsupported currency', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('a', 'woocommerce-mercadopago') . ' ' . $currency . '. ' .
            __('Currency conversions should be done outside of this module.', 'woocommerce-mercadopago');
    }

    public static function build_currency_not_converted_msg($currency, $country_name)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/warning.png', __FILE__) . '"> ' .
            __('ATTENTION: The currency', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('defined in WooCommerce is different from that used by the credentials of your country.<br>The currency for transactions made with this payment method will be', 'woocommerce-mercadopago') .
            ' ' . $currency . ' (' . $country_name . '). ' .
            __('Currency conversions should be done outside of this module.', 'woocommerce-mercadopago');
    }

    public static function build_currency_converted_msg($currency)
    {
        return '<img width="14" height="14" src="' .
            plugins_url('assets/images/check.png', __FILE__) . '"> ' .
            __('CONVERTED CURRENCY: Your store is converting currency of', 'woocommerce-mercadopago') .
            ' ' . get_woocommerce_currency() . ' ' .
            __('for', 'woocommerce-mercadopago') . ' ' . $currency;
    }

    public static function get_country_name($site_id)
    {
        switch ($site_id) {
            case 'MCO':
                return __('Colombia', 'woocommerce-mercadopago');
            case 'MLA':
                return __('Argentina', 'woocommerce-mercadopago');
            case 'MLB':
                return __('Brazil', 'woocommerce-mercadopago');
            case 'MLC':
                return __('Chile', 'woocommerce-mercadopago');
            case 'MLM':
                return __('Mexico', 'woocommerce-mercadopago');
            case 'MLU':
                return __('Uruguay', 'woocommerce-mercadopago');
            case 'MLV':
                return __('Venezuela', 'woocommerce-mercadopago');
            case 'MPE':
                return __('Peru', 'woocommerce-mercadopago');
        }
        return '';
    }

    // Build the string representing the path to the log file.
    public static function build_log_path_string($gateway_id, $gateway_name)
    {
        return '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' .
            esc_attr($gateway_id) . '-' . sanitize_file_name(wp_hash($gateway_id)) . '.log')) . '">' .
            $gateway_name . '</a>';
    }


    public static function get_map($selector_id)
    {
        $html = '';
        $arr = explode('_', $selector_id);
        $defaults = array(
            'pending' => 'pending',
            'approved' => 'processing',
            'inprocess' => 'on_hold',
            'inmediation' => 'on_hold',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'chargedback' => 'refunded'
        );
        $selection = get_option('_mp_' . $selector_id, $defaults[$arr[2]]);

        foreach (wc_get_order_statuses() as $slug => $status) {
            $slug = str_replace(array('wc-', '-'), array('', '_'), $slug);
            $html .= sprintf(
                '<option value="%s"%s>%s %s</option>',
                $slug,
                selected($selection, $slug, false),
                __('Update the WooCommerce order to ', 'woocommerce-mercadopago'),
                $status
            );
        }

        return $html;
    }

    public static function generate_refund_cancel_subscription($domain, $success_msg, $fail_msg, $options, $str1, $str2, $str3, $str4)
    {
        $subscription_js = '<script type="text/javascript">
				( function() {
					var MPSubscription = {}
					MPSubscription.callSubscriptionCancel = function () {
						var url = "' . $domain . '";
						url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
						url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
						url += "&action_mp_payment_action=cancel";
						document.getElementById("sub_pay_cancel_btn").disabled = true;
						MPSubscription.AJAX({
							url: url,
							method : "GET",
							timeout : 5000,
							error: function() {
								document.getElementById("sub_pay_cancel_btn").disabled = false;
								alert("' . $fail_msg . '");
							},
							success : function ( status, data ) {
								document.getElementById("sub_pay_cancel_btn").disabled = false;
								var mp_status = data.status;
								var mp_message = data.message;
								if (data.status == 200) {
									alert("' . $success_msg . '");
								} else {
									alert(mp_message);
								}
							}
						});
					}
					MPSubscription.callSubscriptionRefund = function () {
						var url = "' . $domain . '";
						url += "&action_mp_payment_id=" + document.getElementById("payment_id").value;
						url += "&action_mp_payment_amount=" + document.getElementById("payment_amount").value;
						url += "&action_mp_payment_action=refund";
						document.getElementById("sub_pay_refund_btn").disabled = true;
						MPSubscription.AJAX({
							url: url,
							method : "GET",
							timeout : 5000,
							error: function() {
								document.getElementById("sub_pay_refund_btn").disabled = false;
								alert("' . $fail_msg . '");
							},
							success : function ( status, data ) {
								document.getElementById("sub_pay_refund_btn").disabled = false;
								var mp_status = data.status;
								var mp_message = data.message;
								if (data.status == 200) {
									alert("' . $success_msg . '");
								} else {
									alert(mp_message);
								}
							}
						});
					}
					MPSubscription.AJAX = function( options ) {
						var useXDomain = !!window.XDomainRequest;
						var req = useXDomain ? new XDomainRequest() : new XMLHttpRequest()
						var data;
						options.url += ( options.url.indexOf( "?" ) >= 0 ? "&" : "?" );
						options.requestedMethod = options.method;
						if ( useXDomain && options.method == "PUT" ) {
							options.method = "POST";
							options.url += "&_method=PUT";
						}
						req.open( options.method, options.url, true );
						req.timeout = options.timeout || 1000;
						if ( window.XDomainRequest ) {
							req.onload = function() {
								data = JSON.parse( req.responseText );
								if ( typeof options.success === "function" ) {
									options.success( options.requestedMethod === "POST" ? 201 : 200, data );
								}
							};
							req.onerror = req.ontimeout = function() {
								if ( typeof options.error === "function" ) {
									options.error( 400, {
										user_agent:window.navigator.userAgent, error : "bad_request", cause:[]
									});
								}
							};
							req.onprogress = function() {};
						} else {
							req.setRequestHeader( "Accept", "application/json" );
							if ( options.contentType ) {
								req.setRequestHeader( "Content-Type", options.contentType );
							} else {
								req.setRequestHeader( "Content-Type", "application/json" );
							}
							req.onreadystatechange = function() {
								if ( this.readyState === 4 ) {
									if ( this.status >= 200 && this.status < 400 ) {
										// Success!
										data = JSON.parse( this.responseText );
										if ( typeof options.success === "function" ) {
											options.success( this.status, data );
										}
									} else if ( this.status >= 400 ) {
										data = JSON.parse( this.responseText );
										if ( typeof options.error === "function" ) {
											options.error( this.status, data );
										}
									} else if ( typeof options.error === "function" ) {
										options.error( 503, {} );
									}
								}
							};
						}
						if ( options.method === "GET" || options.data == null || options.data == undefined ) {
							req.send();
						} else {
							req.send( JSON.stringify( options.data ) );
						}
					}
					this.MPSubscription = MPSubscription;
				} ).call();
			</script>';
        $subscription_meta_box = '<table>' .
            '<tr class="total">' .
            '<td><label for="payment_id" style="margin-right:1px;">' .
            $str1 .
            '</label></td>' .
            '<td><select id="payment_id" name="refund_payment_id" style="margin-left:1px;">' .
            $options .
            '</select></td>' .
            '</tr>' .
            '<tr class="total">' .
            '<td><label for="payment_amount" style="margin-right:1px;">' .
            $str2 .
            '</label></td>' .
            '<td><input type="number" class="text amount_input" id="payment_amount" value="0" name="payment_amount"' .
            ' placeholder="Decimal" min="0" step="0.01" value="0.00" style="width:112px; margin-left:1px;"' .
            ' ng-pattern="/^[0-9]+(\.[0-9]{1,2})?$/"/>' .
            '</td>' .
            '</tr>' .
            '<tr class="total">' .
            '<td><input onclick="MPSubscription.callSubscriptionRefund();" type="button"' .
            ' id="sub_pay_refund_btn" class="button button" style="margin-left:1px; margin-top:2px;"' .
            ' name="refund" value="' . $str3 .
            '" style="margin-right:1px;"></td>' .
            '<td><input onclick="MPSubscription.callSubscriptionCancel();" type="button"' .
            ' id="sub_pay_cancel_btn" class="button button" style="margin-right:1px; margin-top:2px;"' .
            ' name="cancel" value="' . $str4 .
            '" style="margin-left:1px;"></td>' .
            '</tr>' .
            '</table>';
        return $subscription_js . $subscription_meta_box;
    }

    /**
     * Check if product dimensions are well defined
     */
    public static function is_product_dimensions_valid($all_product_data)
    {
        if (empty($all_product_data)) {
            return true;
        }
        foreach ($all_product_data as $p) {
            $product = wc_get_product($p->ID);
            if (!$product->is_virtual()) {
                $w = $product->get_weight();
                $dimensions = $product->get_dimensions(false);
                if (empty($w) || !is_numeric($w)) {
                    return false;
                }
                if (!is_numeric($dimensions['height'])) {
                    return false;
                }
                if (!is_numeric($dimensions['width'])) {
                    return false;
                }
                if (!is_numeric($dimensions['length'])) {
                    return false;
                }
            }
        }
        return true;
    }
}

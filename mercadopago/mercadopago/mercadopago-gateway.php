<?php
/**
 * Plugin Name: WooCommerce Mercado Pago
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: This is the <strong>oficial</strong> module of Mercado Pago for WooCommerce plugin. This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.
 * Author: Mercado Pago
 * Author URI: https://www.mercadopago.com.br/developers/
 * Developer: Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright: Copyright(c) MercadoPago [http://www.mercadopago.com]
 * Version: 1.0.0
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * Text Domain: woocommerce-mercadopago-module
 * Domain Path: /languages/
 */

/**
 * Implementation references:
 * 1. https://docs.woothemes.com/document/payment-gateway-api/
 * 2. https://www.mercadopago.com.br/developers/en/api-docs/
 */

// This include Mercado Pago library SDK
require_once "sdk/lib/mercadopago.php";

/**
 * Extending from WooCommerce Payment Gateway class.
 * TODO: better describe this class functionalities.
 */
class WC_WooMercadoPago_Gateway extends WC_Payment_Gateway {
	
	// This array stores each banner image, depending on the country it belongs to or on
	// the type of checkout we use.
	private $banners = array(
        "mercadopago_custom" => array(),
        "mercadopago_customticket" => array(),
        "mercadopago_standard" => array(
            "MLA" => 'MLA.jpg',
            "MLB" => 'MLB.jpg',
            "MCO" => 'MCO.jpg',
            "MLC" => 'MLC.gif',
            "MLV" => 'MLV.jpg',
            "MLM" => 'MLM.jpg'
        )
    );
    
    // Sponsor ID array by country
    private $sponsor_id = array(
    	"MLA" => '208686191',
    	"MLB" => '208682286',
    	"MCO" => '208687643',
    	"MLC" => '208690789',
    	"MLV" => '208692735',
    	"MLM" => '208692380'
	);
    	
	// Required inherited method from WC_Payment_Gateway class: __construct.
	// Please check:
	//    [https://docs.woothemes.com/wc-apidocs/class-WC_Payment_Gateway.html]
	// for documentation and further information.
	public function __construct() {
	
		$this->domain = get_site_url() . '/index.php';
		//$this->domain = 'http://7a2bb004.ngrok.io';

		// These fields are declared because we use them dinamically in our gateway class.
		$this->site_id = null;
		$this->isTestUser = false;
		$this->payment_methods = array();
		$this->store_categories_id = array();
    	$this->store_categories_description = array();
    	
		// Within your constructor, you should define the following variables.
		$this->id = 'woocommerce-mercadopago-module';
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url('images/mercadopago.png', plugin_dir_path(__FILE__))
		);
		$this->method_title = 'Mercado Pago';
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url('images/mplogo.png', plugin_dir_path(__FILE__)) . '"><br><br>' . '<strong>' .
			wordwrap(__('This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module'), 80, "\n") .
			'</strong>';
		
		// These fields are used in our Mercado Pago Module configuration page.
		$this->client_id = $this->get_option('client_id');
		$this->client_secret = $this->get_option('client_secret');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->category_id = $this->get_option('category_id');
		$this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');
		$this->method = $this->get_option('method', 'modal');
		$this->iframe_width = $this->get_option('iframe_width', 640);
		$this->iframe_height = $this->get_option('iframe_height', 800);
		$this->auto_return = $this->get_option('auto_return', true);
		$this->installments = $this->get_option('installments', '24');
		$this->ex_payments = $this->get_option('ex_payments', 'n/d');
		$this->sandbox = $this->get_option('sandbox', false);
		$this->debug = $this->get_option('debug');
		
		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();
		
		// Hook actions for WordPress.
		add_action( // Used by IPN to receive IPN incomings.
			'woocommerce_api_wc_woomercadopago_gateway',
			array($this, 'check_ipn_response')
		);
		add_action( // Used by IPN to process valid incomings.
			'valid_mercadopago_ipn_request',
			array($this, 'successful_request')
		);
		add_action( // Used by WordPress to render the custom checkout page.
			'woocommerce_receipt_' . $this->id,
			array($this, 'receipt_page')
		);
		add_action( // Used to fix CSS in some older WordPress/WooCommerce versions.
			'wp_head', array($this, 'css')
		);
		add_action( // Used in settings page to hook "save settings" action.
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array($this, 'process_admin_options')
		);
		
		// Verify if client_id or client_secret is empty.
		/* TODO: properly implement and enable this action to notify user
		if (!$this->validate_credentials()) {
			add_action('admin_notices', array($this, 'clientIdOrSecretMissingMessage'));
		}*/
		
		// Verify if currency is supported.
		if (!$this->isSupportedCurrency()) {
			add_action('admin_notices', array($this, 'currencyNotSupportedMessage'));
		}

		// Logging and debug.
		if ('yes' == $this->debug) {
			if (class_exists('WC_Logger')) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_MercadoPago_Module::woocommerce_instance()->logger();
			}
		}
		
	}
	
	// Required inherited method from WC_Payment_Gateway class: init_form_fields.
	// Initialise Gateway settings form fields with a customized page.
	public function init_form_fields() {
		
		$api_secret_locale = sprintf(
			'<a href="https://www.mercadopago.com/mla/herramientas/aplicaciones" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mlb/ferramentas/aplicacoes" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mlc/herramientas/aplicaciones" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mco/ferramentas/aplicacoes" target="_blank">%s</a>, <a href="https://www.mercadopago.com/mlm/herramientas/aplicaciones" target="_blank">%s</a> %s <a href="https://www.mercadopago.com/mlv/herramientas/aplicaciones" target="_blank">%s</a>',
			__('Argentine', 'woocommerce-mercadopago-module'),
			__('Brazil', 'woocommerce-mercadopago-module'),
			__('Chile', 'woocommerce-mercadopago-module'),
			__('Colombia', 'woocommerce-mercadopago-module'),
			__('Mexico', 'woocommerce-mercadopago-module'),
			__('or', 'woocommerce-mercadopago-module'),
			__('Venezuela', 'woocommerce-mercadopago-module')
		);
		
		// Trigger API to get payment methods and site_id, also validates Client_id/Client_secret.
		if ($this->validateCredentials()) {
			try {
				$mp = new MP($this->client_id, $this->client_secret);
				$access_token = $mp->get_access_token();
				$get_request = $mp->get("/users/me?access_token=" . $access_token);
				$this->isTestUser = in_array('test_user', $get_request['response']['tags']);
				$this->site_id = $get_request['response']['site_id'];
				$payments = $mp->get("/v1/payment_methods/?access_token=" . $access_token);
				array_push($this->payment_methods, "n/d");
				foreach ($payments["response"] as $payment) {
					array_push($this->payment_methods, str_replace("_", " ", $payment['id']));
				}
				$this->payment_desc =
					__('Select the payment methods that you <strong>don\'t</strong> want to receive with Mercado Pago.', 'woocommerce-mercadopago-module');
				$this->credentials_message = '<img width="12" height="12" src="' .
					plugins_url('images/check.png', plugin_dir_path(__FILE__)) . '">' .
					' ' . __('Your credentials are <strong>valid</strong> for', 'woocommerce-mercadopago-module') .
					': ' . $this->getCountryName($this->site_id) . ' <img width="18.6" height="12" src="' .
					plugins_url('images/' . $this->site_id . '.png', plugin_dir_path(__FILE__)) . '"> ';
			} catch (MercadoPagoException $e) {
				array_push($this->payment_methods, "n/d");
				$this->payment_desc = '<img width="12" height="12" src="' .
					plugins_url('images/warning.png', plugin_dir_path(__FILE__)) . '">' .
					' ' . __('Configure your Client_id and Client_secret to have access to more options.', 'woocommerce-mercadopago-module');
				$this->credentials_message = '<img width="12" height="12" src="' .
					plugins_url('images/error.png', plugin_dir_path(__FILE__)) . '">' .
					' ' . __('Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module');
			}
		} else {
			array_push($this->payment_methods, "n/d");
			$this->payment_desc = '<img width="12" height="12" src="' .
				plugins_url('images/warning.png', plugin_dir_path(__FILE__)) . '">' .
				' ' . __('Configure your Client_id and Client_secret to have access to more options.', 'woocommerce-mercadopago-module');
			$this->credentials_message = '<img width="12" height="12" src="' .
				plugins_url('images/error.png', plugin_dir_path(__FILE__)) . '">' .
				' ' . __('Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module');
		}
		
		// Fills categoy selector. We do not need credentials to make this call.
		$categories = MPRestClient::get(array("uri" => "/item_categories"));
		foreach ($categories["response"] as $category) {
			array_push($this->store_categories_id, str_replace("_", " ", $category['id']));
			array_push($this->store_categories_description, str_replace("_", " ", $category['description']));
		}
		
		// Checks validity of iFrame width/height fields.
		if (!is_numeric($this->iframe_width)) {
			$this->iframe_width_desc = '<img width="12" height="12" src="' .
				plugins_url('images/warning.png', plugin_dir_path(__FILE__)) . '">' .
				' ' . __('This field should be an integer.', 'woocommerce-mercadopago-module');
		} else {
			$this->iframe_width_desc =
				__('If your integration method is iFrame, please inform the payment iFrame width.', 'woocommerce-mercadopago-module');
		}
		if (!is_numeric($this->iframe_height)) {
			$this->iframe_height_desc = '<img width="12" height="12" src="' .
				plugins_url('images/warning.png', plugin_dir_path(__FILE__)) . '">' .
				' ' . __('This field should be an integer.', 'woocommerce-mercadopago-module');
		} else {
			$this->iframe_height_desc =
				__('If your integration method is iFrame, please inform the payment iFrame height.', 'woocommerce-mercadopago-module');
		}
		
		// Checks if max installments is a number.
		if (!is_numeric($this->installments)) {
			$this->installments_desc = '<img width="12" height="12" src="' .
				plugins_url('images/warning.png', plugin_dir_path(__FILE__)) . '">' .
				' ' . __('This field should be an integer.', 'woocommerce-mercadopago-module');
		} else {
			$this->installments_desc =
				__('Select the max number of installments for your customers.', 'woocommerce-mercadopago-module');
		}
		
		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'woocommerce-mercadopago-module'),
				'type' => 'checkbox',
				'label' => __('Enable Mercado Pago Module', 'woocommerce-mercadopago-module'),
				'default' => 'yes'
			),
			'credentials_title' => array(
				'title' => __('Mercado Pago Credentials', 'woocommerce-mercadopago-module'),
				'type' => 'title',
				'description' => sprintf('%s', $this->credentials_message) . '<br>' . sprintf(__('You can obtain your credentials for', 'woocommerce-mercadopago-module') . ' %s.', $api_secret_locale)
			),
			'client_id' => array(
				'title' => 'Client_id',
				'type' => 'text',
				'description' => __('Insert your Mercado Pago Client_id.', 'woocommerce-mercadopago-module'),
				'default' => '',
				'required' => true
			),
			'client_secret' => array(
				'title' => 'Client_secret',
				'type' => 'text',
				'description' => __('Insert your Mercado Pago Client_secret.', 'woocommerce-mercadopago-module'),
				'default' => '',
				'required' => true
			),
			'ipn_url' => array(
				'title' => __('Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module'),
				'type' => 'title',
				'description' => sprintf(__('Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module') . '<br>%s', '<code>' . $this->domain . '/' . $this->id . '/?wc-api=WC_WooMercadoPago_Gateway' . '</code>.')
			),
			'checkout_options_title' => array(
				'title' => __('Checkout Options', 'woocommerce-mercadopago-module'),
				'type' => 'title',
				'description' => ''
			),
			'title' => array(
				'title' => __('Title', 'woocommerce-mercadopago-module'),
				'type' => 'text',
				'description' => __('Title shown to the client in the checkout.', 'woocommerce-mercadopago-module'),
				'default' => 'Mercado Pago'
			),
			'description' => array(
				'title' => __('Description', 'woocommerce-mercadopago-module'),
				'type' => 'textarea',
				'description' => __('Description shown to the client in the checkout.', 'woocommerce-mercadopago-module'),
				'default' => __('Pay with Mercado Pago', 'woocommerce-mercadopago-module')
			),
			'category_id' => array(
				'title' => __('Store Category', 'woocommerce-mercadopago-module'),
				'type' => 'select',
				'description' => __('Define which type of products your store sells.', 'woocommerce-mercadopago-module'),
				'options' => $this->store_categories_id
			),
			'invoice_prefix' => array(
				'title' => __('Store Identificator', 'woocommerce-mercadopago-module'),
				'type' => 'text',
				'description' => __('Please, inform a prefix to your store.', 'woocommerce-mercadopago-module') . ' ' . __('If you use your Mercado Pago account on multiple stores you should make sure that this prefix is unique as Mercado Pago will not allow orders with same identificators.', 'woocommerce-mercadopago-module'),
				'default' => 'WC-'
			),
			'method' => array(
				'title' => __('Integration Method', 'woocommerce-mercadopago-module'),
				'type' => 'select',
				'description' => __('Select how your clients should interact with Mercado Pago. Modal Window (inside your store), Redirect (Client is redirected to Mercado Pago), or iFrame (an internal window is embedded to the page layout).', 'woocommerce-mercadopago-module'),
				'default' => 'iframe',
				'options' => array(
					'iframe' => __('iFrame', 'woocommerce-mercadopago-module'),
					'modal' => __('Modal Window', 'woocommerce-mercadopago-module'),
					'redirect' => __('Redirect', 'woocommerce-mercadopago-module')
				)
			),
			'iframe_width' => array(
				'title' => __('iFrame Width', 'woocommerce-mercadopago-module'),
				'type' => 'text',
				'description' => $this->iframe_width_desc,
				'default' => '640'
			),
			'iframe_height' => array(
				'title' => __('iFrame Height', 'woocommerce-mercadopago-module'),
				'type' => 'text',
				'description' => $this->iframe_height_desc,
				'default' => '800'
			),
			'auto_return' => array(
				'title' => __('Auto Return', 'woocommerce-mercadopago-module'),
				'type' => 'checkbox',
				'label' => __('Automatic Return After Payment', 'woocommerce-mercadopago-module'),
				'default' => 'yes',
				'description' => __('After the payment, client is automatically redirected.', 'woocommerce-mercadopago-module'),
			),
			'testing_title' => array(
				'title' => __('Payment Options', 'woocommerce-mercadopago-module'),
				'type' => 'title',
				'description' => ''
			),
			'installments' => array(
				'title' => __('Max installments', 'woocommerce-mercadopago-module'),
				'type' => 'text',
				'description' => $this->installments_desc,
				'default' => '24'
			),
			'ex_payments' => array(
                'title' => __('Exclude Payment Methods', 'woocommerce-mercadopago-module'),
                'description' => $this->payment_desc,
                'type' => 'multiselect',
                'options' => $this->payment_methods,
                'default' => ''
            ),
			'testing' => array(
				'title' => __('Test and Debug Options', 'woocommerce-mercadopago-module'),
				'type' => 'title',
				'description' => ''
			),
			'sandbox' => array(
				'title' => __('Mercado Pago Sandbox', 'woocommerce-mercadopago-module'),
				'type' => 'checkbox',
				'label' => __('Enable Mercado Pago Sandbox', 'woocommerce-mercadopago-module'),
				'default' => 'no',
				'description' => __('This options allows you to test payments inside a sandbox environment.', 'woocommerce-mercadopago-module'),
			),
			'debug' => array(
				'title' => __('Debug and Log', 'woocommerce-mercadopago-module'),
				'type' => 'checkbox',
				'label' => __('Enable log', 'woocommerce-mercadopago-module'),
				'default' => 'no',
				'description' => sprintf(__('Register event logs of Mercado Pago, such as API requests, in the file', 'woocommerce-mercadopago-module') . ' %s.', '<code>wordpress/wp-content/uploads/wc-logs/' . $this->id . '-' . sanitize_file_name(wp_hash($this->id)) . '.txt</code>')
			)
		);
		
	}
	
	/*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES
	 * ========================================================================
	 */
	 
	// 1. First step occurs when the customer selects Mercado Pago and proceed to
	// checkout. This method verify which integration method was selected and
	// makes the build for the checkout URL.
	public function process_payment($order_id) {
		$order = new WC_Order($order_id);
		// Check for the type of integration.
		if ('redirect' == $this->method) {
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[process_payment] - customer being redirected to Mercado Pago environment.');
			}
			return array(
				'result' => 'success',
				'redirect' => $this->createUrl($order)
			);
		} else if ('modal' == $this->method || 'iframe' == $this->method) {
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[process_payment] - preparing to render Mercado Pago checkout view.');
			}
			if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
			}
		}
	}

	// 2. Order page and this generates the form that shows the pay button. This step
	// generates the form to proceed to checkout.
	public function receipt_page($order) {
		echo $this->renderOrderForm($order);
	}
	// --------------------------------------------------
	public function renderOrderForm($order_id) {
		$order = new WC_Order($order_id);
		$url = $this->createUrl($order);
		if ($url) {
			// Display checkout.
			$html =
				$this->iframe_width_desc = '<img width="468" height="60" src="' .
				plugins_url('images/' . $this->banners['mercadopago_standard'][$this->site_id], plugin_dir_path(__FILE__)) . '">';
			if ('iframe' != $this->method) {
				if ('yes' == $this->debug) {
					$this->log->add($this->id, $this->id . ': @[renderOrderForm] - rendering Mercado Pago lightbox (modal window).');
				}
				$html .= '<p></p><p>' . wordwrap(
					__('Thank you for your order. Please, proceed with your payment clicking in the bellow button.', 'woocommerce-mercadopago-module'),
					60, '<br>') . '</p>';
				$html .=
					'<a id="submit-payment" href="' . $url . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
					__('Pay with Mercado Pago', 'woocommerce-mercadopago-module') .
					'</a> ';
				$html .=
					'<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' .
					__('Cancel order &amp; Clear cart', 'woocommerce-mercadopago-module') .
					'</a><style type="text/css">#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important;  height: 590px !important; }</style>';
				// Includes javascript.
				$html .=
					'<script type="text/javascript">(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true;s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"http://mp-tools.mlstatic.com/buttons/")+"render.js";var x = document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();}window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent("onload", $MPBR_load) : window.addEventListener("load", $MPBR_load, false)) : null;})();</script>';
			} else {
				if ('yes' == $this->debug) {
					$this->log->add($this->id, $this->id . ': @[renderOrderForm] - embedding Mercado Pago iFrame.');
				}
				$html .= '<p></p><p>' . wordwrap(
					__('Thank you for your order. Proceed with your payment completing the following information.', 'woocommerce-mercadopago-module'),
					60, '<br>') . '</p>';
				$html .=
					'<iframe src="' . $url . '" name="MP-Checkout" ' .
					'width="' . (is_numeric((int)$this->iframe_width) ? $this->iframe_width : 640) . '" ' .
					'height="' . (is_numeric((int)$this->iframe_height) ? $this->iframe_height : 800) . '" ' .
					'frameborder="0" scrolling="no" id="checkout_mercadopago"></iframe>';
			}
			return $html;
		} else {
			$html =
				'<p>' . __('An error occurred when proccessing your payment. Please try again or contact us for assistence.', 'woocommerce-mercadopago-module') . '</p>';
			$html .=
				'<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' .
				__('Click to try again', 'woocommerce-mercadopago-module') .
				'</a>';
			return $html;
		}
	}
	
	// 3. Create Mercado Pago preference and get init_point URL based in the
	// order options from the cart.
	public function buildPaymentPreference($order) {
	
		// Here we build the array that contains ordered itens, from customer cart
		// UPDATE: because [shipment cost] is only available for custom checkout, our
		// 		   preference need to be build with all items grouped in one or the
		//         ship cost will not be added to the total amount paid.
		/*$items = array();
		if (sizeof($order->get_items()) > 0) {
			foreach ($order->get_items() as $item) {
				if ($item['qty']) {
					$product = new WC_product($item['product_id']);
					array_push($items, array(
						'id' => $item['product_id'],
						'title' => ($product->post->post_title . ' x ' . $item['qty']),
						'description' => (
							// This handles description width limit of Mercado Pago.
							strlen($product->post->post_content) > 230 ?
							substr($product->post->post_content, 0, 230) . "..." :
							$product->post->post_content
						),
						'picture_url' => $product->get_image(),
						'category_id' => $this->store_categories_id[$this->category_id],
						'quantity' => 1,
						'unit_price' => (float)$item['line_subtotal'],
						'currency_id' => get_woocommerce_currency()
					));
				}
			}
		}*/
		
		// Here we build the array that contains ordered itens, from customer cart
		$items = array(
			array(
				'quantity' => 1,
				'unit_price' => (float)$order->order_total,
				'currency_id' => get_woocommerce_currency()
			)
		);
		$item_ids = array();
		$item_names = array();
		$item_descriptions = array();
		$item_picture_url = array();
		if (sizeof($order->get_items()) > 0) {
			foreach ($order->get_items() as $item) {
				if ($item['qty']) {
					$product = new WC_product($item['product_id']);
					$item_ids[] = $item['product_id'];
					$item_names[] = $item['name'] . ' x ' . $item['qty'];
					$item_descriptions[] = (
						// This handles description width limit of Mercado Pago.
						strlen($product->post->post_content) > 50 ?
						substr($product->post->post_content, 0, 50) . "..." :
						$product->post->post_content
					);
					$item_picture_url[] = $product->get_image();
				}
			}
		}
		$items[0]['id'] = implode('; ', $item_ids);
		$items[0]['title'] = implode('; ', $item_names);
		$items[0]['description'] = (
			// This handles description width limit of Mercado Pago.
			strlen(implode('; ', $item_descriptions)) > 230 ?
			substr(implode('; ', $item_descriptions), 0, 230) . "..." :
			implode('; ', $item_descriptions)
		);
		$items[0]['picture_url'] = implode('; ', $item_picture_url);
		$items[0]['category_id'] = $this->store_categories_id[$this->category_id];
		
		// Find excluded payment methods. If 'n/d' is in array index, we should
		// disconsider the remaining values.
        $excluded_payment_methods = array();
        try { // in some PHP versions, $this->ex_payments is interpreted as a not iterable object
	        foreach ($this->ex_payments as $excluded) {
				if ($excluded == 0) // if "n/d" is selected, we just not add any items to the array
          			break;
        		array_push($excluded_payment_methods, array(
	        		"id" => $this->payment_methods[$excluded]
    	    	));
        	}
        } catch (MercadoPagoException $e) {
        	if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[DEBUG] - excluded payments: exception caught: ' . print_r($e, true));
			}
        }
        $payment_methods = array(
            'installments' => (is_numeric((int)$this->installments) ? (int)$this->installments : 24),
            'default_installments' => 1
        );
        // Set excluded payment methods.
        if (count($excluded_payment_methods) > 0) {
        	$payment_methods['excluded_payment_methods'] = $excluded_payment_methods;
        }
        
        // Create Mercado Pago preference.
		$preferences = array(
			'items' => $items,
			// Payer should be filled with billing info because orders can be made with non-logged customers.
			'payer' => array(
				'name' => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email' => $order->billing_email,
				'phone'	=> array(
					'number' => $order->billing_phone
				),
				'address' => array(
					'street_name' => $order->billing_address_1 . ' / ' .
						$order->billing_city . ' ' .
						$order->billing_state . ' ' .
						$order->billing_country,
					'zip_code' => $order->billing_postcode
				)
			),
			'back_urls' => array(
				'success' => esc_url($this->get_return_url($order)),
				'failure' => str_replace('&amp;', '&', $order->get_cancel_order_url()),
				'pending' => esc_url($this->get_return_url($order))
			),
			//'marketplace' => $this->site_id,
            //'marketplace_fee' =>
            'shipments' => array(
            	'receiver_address' => array(
            		'zip_code' => $order->shipping_postcode,
            		//'street_number' =>
            		'street_name' => $order->shipping_address_1 . ' ' .
            			$order->shipping_city . ' ' .
            			$order->shipping_state . ' ' .
            			$order->shipping_country,
            		//'floor' =>
            		'apartment' => $order->shipping_address_2
            	)
            ),
			'payment_methods' => $payment_methods,
			'notification_url' => $this->domain . '/' . $this->id . '/?wc-api=WC_WooMercadoPago_Gateway',
			'external_reference' => $this->invoice_prefix . $order->id
			//'additional_info' => $order->customer_message
            //'expires' => 
            //'expiration_date_from' => 
            //'expiration_date_to' => 
		);
		// Set sponsor ID
		if (!$this->isTestUser) {
			$preferences['sponsor_id'] = (int)($sponsor_id[$this->site_id]);
		}
		// Auto return options.
		if ('yes' == $this->auto_return) {
			$preferences['auto_return'] = "approved";
		}
		if ('yes' == $this->debug) {
			$this->log->add($this->id, $this->id . ': @[buildPaymentPreference] - requesting mercado pago preference creation with following structure: ' . print_r($preferences, true));
		}
		$preferences = apply_filters('woocommerce_mercadopago_module_preferences', $preferences, $order);
		return $preferences;
	}
	// --------------------------------------------------
	protected function createUrl($order) {
		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->buildPaymentPreference($order);
		$mp = new MP($this->client_id, $this->client_secret);
		// Checks for sandbox mode.
		if ('yes' == $this->sandbox) {
			$mp->sandbox_mode(true);
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[createUrl] - sandbox mode is enabled');
			}
		} else {
			$mp->sandbox_mode(false);
		}
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $mp->create_preference(json_encode($preferences));
			if (is_wp_error($checkout_info) || $checkout_info['status'] < 200 || $checkout_info['status'] >= 300) {
				if ('yes' == $this->debug) {
					$this->log->add($this->id, $this->id . ': @[createUrl] - payment creation failed with error: ' . $checkout_info['response']['status']);
				}
				return false;
			} else {
				if ('yes' == $this->debug) {
					$this->log->add($this->id, $this->id . ': @[createUrl] - payment link generated with success from mercado pago, with structure as follow: ' . print_r($checkout_info, true));
				}
				if ('yes' == $this->sandbox) {
					return $checkout_info['response']['sandbox_init_point'];
				} else {
					return $checkout_info['response']['init_point'];
				}
			}
		} catch (MercadoPagoException $e) {
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[createUrl] - payment creation failed with exception: ' . print_r($e, true));
			}
			return false;
		}
	}
	
	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS
	 * ========================================================================
	 */

	// Check if we have valid credentials.
	public function validateCredentials() {
		if (empty($this->client_id)) return false;
		if (empty($this->client_secret)) return false;
		if (strlen($this->client_id) > 0 && strlen($this->client_secret) > 0) {
			try {
				$mp = new MP($this->client_id, $this->client_secret);
				return true;
			} catch (Exception $e) {
				return false;
			}
		}
		return false;
	}
	
	// Return boolean indicating if currency is supported.
	protected function isSupportedCurrency() {
		return in_array(get_woocommerce_currency(), array('ARS', 'BRL', 'CLP', 'COP', 'MXN', 'USD', 'VEF'));
	}

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		// Test if is valid for use.
		$available = ('yes' == $this->settings['enabled']) &&
					! empty($this->client_id) &&
					! empty($this->client_secret) &&
					$this->isSupportedCurrency();
		return $available;
	}
	
	// Fix css for Mercado Pago in specific cases.
	public function css() {
		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
			$page_id = wc_get_page_id('checkout');
		} else {
			$page_id = woocommerce_get_page_id('checkout');
		}
		if (is_page($page_id)) {
			echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
		}
	}
	
	// Get the URL to admin page.
	protected function admin_url() {
		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopago_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPago_Gateway'
		);
	}

	// Notify that Client_id and/or Client_secret are not valid.
	public function clientIdOrSecretMissingMessage() {
		echo '<div class="error"><p><strong>' . 
			__('Mercado Pago is Inactive', 'woocommerce-mercadopago-module') .
			'</strong>: ' .
			sprintf(
				__('Your Mercado Pago credentials Client_id/Client_secret appears to be misconfigured.', 'woocommerce-mercadopago-module') . ' %s',
				'<a href="' . $this->admin_url() . '">' . __('Click here and configure!', 'woocommerce-mercadopago-module') . '</a>') .
			'</p></div>';
	}

	// Notify that currency is not supported.
	public function currencyNotSupportedMessage() {
		echo '<div class="error"><p><strong>' .
			__('Mercado Pago is Inactive', 'woocommerce-mercadopago-module') .
			'</strong>: ' .
			sprintf(
				__('The currency') . ' <code>%s</code> ' . __('is not supported. Supported currencies are: ARS, BRL, CLP, COP, MXN, USD, VEF.', 'woocommerce-mercadopago-module'),
				get_woocommerce_currency()) .
			'</p></div>';
	}
	
	public function getCountryName($site_id) {
		$country = $site_id;
		switch ($site_id) {
			case 'MLA': return __('Argentine', 'woocommerce-mercadopago-module');
			case 'MLB': return __('Brazil', 'woocommerce-mercadopago-module');
			case 'MCO': return __('Colombia', 'woocommerce-mercadopago-module');
			case 'MLC': return __('Chile', 'woocommerce-mercadopago-module');
			case 'MLV': return __('Mexico', 'woocommerce-mercadopago-module');
			case 'MLM': return __('Venezuela', 'woocommerce-mercadopago-module');
		}
	}
	
	/*
	 * ========================================================================
	 * IPN MECHANICS
	 * ========================================================================
	 */
	
	// This call checks any incoming notifications from Mercado Pago server.
	public function check_ipn_response() {
		if ('yes' == $this->debug) {
			$this->log->add($this->id, $this->id . ': @[check_ipn_response] - got a call from mercado pago ipn');
		}
		@ob_clean();
		$data = $this->check_ipn_request_is_valid($_GET);
		if ($data) {
			header('HTTP/1.1 200 OK');
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[check_ipn_response] - received _get call with following content: ' . print_r($data, true));
			}
			do_action('valid_mercadopago_ipn_request', $data);
		} else {
			wp_die(__('Mercado Pago Request Failure', 'woocommerce-mercadopago-module'));
		}
	}
	
	// Get received data from IPN and checks if we have a merchant_order or
	// payment associated. If we have these information, we return data to be
	// processed by successful_request function.
	public function check_ipn_request_is_valid($data) {
		if ('yes' == $this->debug) {
			$this->log->add($this->id, $this->id . ': @[check_ipn_request_is_valid] - received ipn message from mercado pago, checking validity with $data containing: ' . print_r($data, true));
		}
		if (!isset($data['id'])) {
			return false; // No ID? No process!
		}
		// Create MP object and setup sandbox mode.
		$mp = new MP($this->client_id, $this->client_secret);
		if ('yes' == $this->sandbox) {
			$mp->sandbox_mode(true);
		} else {
			$mp->sandbox_mode(false);
		}
		try { // Get the merchant_order reported by the IPN. Glossary of attributes response in https://developers.mercadopago.com
			$params = array("access_token" => $mp->get_access_token());
			if ($data["topic"] == 'payment') {
				$payment_info = $mp->get("/collections/notifications/" . $_GET["id"], $params, false);
				$merchant_order_info = $mp->get("/merchant_orders/" . $payment_info["response"]["collection"]["merchant_order_id"], $params, false);
			} else if ($data["topic"] == 'merchant_order') {
				$merchant_order_info = $mp->get("/merchant_orders/" . $_GET["id"], $params, false);
			}
			// If the payment's transaction amount is equal (or bigger) than the merchant order's amount you can release your items 
			if (!is_wp_error($merchant_order_info) && ($merchant_order_info["status"] == 200)) {
				$payments = $merchant_order_info["response"]["payments"];
			   	// check if we have more than one payment method
			   	if (sizeof($payments) == 2) {
			   		if (strcasecmp($payments[0]['status'], $payments[1]['status']) != 0) {
			   			if ('yes' == $this->debug) {
							$this->log->add($this->id, $this->id . ': @[check_ipn_request_is_valid] - two payments with status not equal');
						}
					} else {
						return $merchant_order_info["response"];
					}
			   	} else { // If we have only one payment, we can go on its status
			   		return $merchant_order_info['response'];
			   	}
			} else {
				if ('yes' == $this->debug) {
					$this->log->add($this->id, $this->id . ': @[check_ipn_request_is_valid] - got status not equal 200 or some error');
				}
			}
		} catch (MercadoPagoException $e) {
			if ('yes' == $this->debug) {
				$this->log->add($this->id, $this->id . ': @[check_ipn_request_is_valid] - GOT EXCEPTION: ' . $e->getMessage());
			}
		}
		return false;
	}
	
	// Properly handles each case of notification, based in payment status.
	public function successful_request($data) {
		if ('yes' == $this->debug) {
			$this->log->add($this->id, $this->id . ': @[successful_request] - starting to process ipn update...');
		}
		$order_key = $data['external_reference'];
		if (!empty($order_key)) {
			$order_id = (int)str_replace($this->invoice_prefix, '', $order_key);
			$order = new WC_Order($order_id);
			// Checks whether the invoice number matches the order. If true processes the payment.
			if ($order->id === $order_id) {
				if ( 'yes' == $this->debug ) {
					$this->log->add($this->id, $this->id . ': @[successful_request] - got order with ID ' . $order->id . ' and status ' . $data['payments'][0]['status']);
				}
				switch ($data['payments'][0]['status']) {
					case 'approved':
						// Order details.
						if (!empty($data['id'])) {
							update_post_meta(
								$order_id,
								__('Mercado Pago Transaction ID', 'woocommerce-mercadopago-module'),
								$data['id']
							);
						}
						if (!empty($data['payer']['email'])) {
							update_post_meta(
								$order_id,
								__('Payer email', 'woocommerce-mercadopago-module'),
								$data['payer']['email']
							);
						}
						if (!empty($data['payment_type'])) {
							update_post_meta(
								$order_id,
								__('Payment type', 'woocommerce-mercadopago-module'),
								$data['payment_type']
							);
						}
						$order->add_order_note(
							'Mercado Pago: ' . __('Payment approved.', 'woocommerce-mercadopago-module')
						);
						$order->payment_complete();
						break;
					case 'pending':
						$order->add_order_note(
							'Mercado Pago: ' . __('Customer haven\'t paid yet.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'in_process':
						$order->update_status('on-hold',
							'Mercado Pago: ' . __('Payment under review.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'rejected':
						$order->update_status('failed',
							'Mercado Pago: ' . __('The payment was refused. The customer can try again.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'refunded':
						$order->update_status(
							'refunded',
							'Mercado Pago: ' . __('The payment was refunded to the customer.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'cancelled':
						$order->update_status(
							'cancelled',
							'Mercado Pago: ' . __('The payment was cancelled.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'in_mediation':
						$order->add_order_note(
							'Mercado Pago: ' . __('The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module')
						);
						break;
					case 'charged-back':
						$order->add_order_note(
							'Mercado Pago: ' . __('The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module')
						);
					default:
						break;
				}
			}
		}
	}
	
}

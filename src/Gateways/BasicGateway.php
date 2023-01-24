<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BasicGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * ID
     *
     * @const
     */
    const ID = 'woo-mercado-pago-basic';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-basic';

    /**
     * BasicGateway constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->translations = $this->mercadopago->storeTranslations->checkoutBasic;

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-mp');
        $this->title              = $this->translations['gateway_title'];
        $this->description        = $this->translations['gateway_description'];
        $this->method_title       = $this->translations['method_title'];
        $this->method_description = $this->description;
        $this->has_fields         = true;
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    /**
     * Get checkout name
     *
     * @return string
     */
    public function getCheckoutName(): string
    {
        return self::CHECKOUT_NAME;
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'config_header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->translations['config_header_title'],
                'description' => $this->translations['config_header_desc'],
            ],
            'enabled'       => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->translations['config_enabled_title'],
                'subtitle'     => $this->translations['config_enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->translations['config_enabled_enabled'],
                    'disabled' => $this->translations['config_enabled_disabled'],
                ],
            ],
            'title'         => [
                'type'        => 'text',
                'title'       => 'Title in the store Checkout',
                'description' => 'Change the display text in Checkout, maximum characters: 85',
                'default'     => 'Credit Card',
                'desc_tip'    => 'The text inserted here will not be translated to other languages',
                'class'       => 'limit-title-max-length',
            ],
            'description'   => [
                'type'        => 'text',
                'title'       => 'Description',
                'description' => '',
                'default'     => 'Debit, Credit and Invoice in Mercado Pago environment.',
                'class'       => 'mp-hidden-field-description',
            ],
            'currency_conversion'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Convert Currency',
                'subtitle'     => 'Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.',
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => 'Currency convertion is <b>enabled</b>.',
                    'disabled' => 'Currency convertion is <b>disabled</b>.',
                ],
            ],
            'ex_payments'   => $this->field_ex_payments(),
            'installments'   => [
                'type'        => 'select',
                'title'       => 'Maximum number of installments',
                'description' => 'What is the maximum quota with which a customer can buy?',
                'default'     => '24',
                'options'     => [
                    '1'  => '1 installment',
                    '2'  => '2 installments',
                    '3'  => '3 installments',
                    '4'  => '4 installments',
                    '5'  => '5 installments',
                    '6'  => '6 installments',
                    '10' => '10 installments',
                    '12' => '12 installments',
                    '15' => '15 installments',
                    '18' => '18 installments',
                    '24' => '24 installments',
                ],
            ],
            'checkout_payments_advanced_title'   => [
                'type'        => 'title',
                'title'       => 'Advanced settings',
                'class'       => 'mp_subtitle_body',
            ],
            'checkout_payments_advanced_description'   => [
                'type'        => 'title',
                'title'       => 'Edit these advanced fields only when you want to modify the preset values.',
                'class'       => 'mp_small_text',
            ],
            'method'   => [
                'type'        => 'select',
                'title'       => 'Payment experience',
                'description' => 'Define what payment experience your customers will have, whether inside or outside your store.',
                'default'     => 'redirect',
                'options'     => [
                    'redirect' => __('Redirect', 'woocommerce-mercadopago'),
                    'modal'    => __('Modal', 'woocommerce-mercadopago'),
                ],
            ],
            'auto_return'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Return to the store',
                'subtitle'     => 'Do you want your customer to automatically return to the store after payment?',
                'default'      => 'yes',
                'descriptions' => [
                    'enabled' => __('The buyer <b>will be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
                    'disabled' => __('The buyer <b>will not be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
                ],
            ],
            'success_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL success',
                'description' => $this->validateBackUrl($this->settings['success_url'], 'Choose the URL that we will show your customers when they finish their purchase.'),
            ],
            'failure_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL rejected',
                'description' => $this->validateBackUrl($this->settings['failure_url'], 'Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.'),
            ],
            'pending_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL pending',
                'description' => $this->validateBackUrl($this->settings['pending_url'], 'Choose the URL that we will show to your customers when they have a payment pending approval.'),
            ],
            'binary_mode'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Automatic decline of payments without instant approval',
                'subtitle'     => 'Enable it if you want to automatically decline payments that are not instantly approved by banks or other institutions.',
                'descriptions' => [
                    'enabled'  => 'Pending payments <b>will be automatically declined</b>.',
                    'disabled' => 'Pending payments <b>will not be automatically declined</b>.',
                ],
                'default'      => 'Debit, Credit and Invoice in Mercado Pago environment.',
            ],
        ];
    }

    /**
     * Validate Back URL and return error message or default string
     *
     * @return string
     */
    private function validateBackUrl($url, $default)
    {
        if (! empty($url) && filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '<img width="14" height="14" src="' . plugins_url('../assets/images/icons/icon-warning.png', plugin_dir_path(__FILE__)) . '"> ' .
                'This seems to be an invalid URL.';
        }
        return $default;
    }

    //@TODO change getOption
    /**
     * Field payments
     *
     * @return array
     */
    private function field_ex_payments()
    {
        $payment_list = array(
            'description'          => __('Enable the payment methods available to your clients.', 'woocommerce-mercadopago'),
            'title'                => __('Choose the payment methods you accept in your store', 'woocommerce-mercadopago'),
            'type'                 => 'mp_checkbox_list',
            'payment_method_types' => array(
                'credit_card'      => array(
                    'label'        => __('Credit Cards', 'woocommerce-mercadopago'),
                    'list'         => array(),
                ),
                'debit_card'       => array(
                    'label'        => __('Debit Cards', 'woocommerce-mercadopago'),
                    'list'         => array(),
                ),
                'other'            => array(
                    'label'        => __('Other Payment Methods', 'woocommerce-mercadopago'),
                    'list'         => array(),
                ),
            ),
        );

        $all_payments = get_option('_checkout_payments_methods', '');

        if (empty($all_payments)) {
            return $payment_list;
        }

        foreach ($all_payments as $payment_method) {
            if ('credit_card' === $payment_method['type']) {
                $payment_list['payment_method_types']['credit_card']['list'][] = array(
                'id'        => 'ex_payments_' . $payment_method['id'],
                'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
                'label'     => $payment_method['name'],
                'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
                'type'      => 'checkbox',
                );
            } elseif ('debit_card' === $payment_method['type'] || 'prepaid_card' === $payment_method['type']) {
                $payment_list['payment_method_types']['debit_card']['list'][] = array(
                'id'        => 'ex_payments_' . $payment_method['id'],
                'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
                'label'     => $payment_method['name'],
                'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
                'type'      => 'checkbox',
                );
            } else {
                $payment_list['payment_method_types']['other']['list'][] = array(
                'id'        => 'ex_payments_' . $payment_method['id'],
                'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
                'label'     => $payment_method['name'],
                'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
                'type'      => 'checkbox',
                );
            }
        }

        return $payment_list;
    }

    /**
     * Added gateway scripts
     *
     * @param string $gatewaySection
     *
     * @return void
     */
    public function payment_scripts(string $gatewaySection): void
    {
        parent::payment_scripts($gatewaySection);
    }

    //@TODO remove plugins_url, change plugin_version and fix link_terms_and_conditions
    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->scripts->registerStoreStyle(
            'woocommerce-mercadopago-narciso-styles',
            plugins_url('../assets/css/checkout/mp-plugins-components.css', plugin_dir_path(__FILE__))
        );
        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-narciso-scripts',
            plugins_url('../assets/js/checkout/mp-plugins-components.js', plugin_dir_path(__FILE__))
        );

        $method         = $this->settings['method'];
        $siteId         = strtoupper($this->mercadopago->seller->getSiteId());
        $test_mode_link = $this->get_mp_devsite_link($siteId);

        $payment_methods       = $this->get_payment_methods();
        $payment_methods_title = count($payment_methods) !== 0 ? 'Available payment methods' : '';

        $checkout_benefits_items = $this->get_benefits($siteId);

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/basic-checkout.php',
            [
                'method'                    => $method,
                'test_mode'                 => ! $this->mercadopago->store->getCheckboxCheckoutProductionMode(),
                'test_mode_link'            => $test_mode_link,
                'checkout_redirect_src'     => plugins_url('../assets/images/checkouts/basic/cho-pro-redirect-v2.png', plugin_dir_path(__FILE__)),
                'payment_methods'           => wp_json_encode($payment_methods),
                'payment_methods_title'     => $payment_methods_title,
                'checkout_benefits_items'   => wp_json_encode($checkout_benefits_items),
                'text_prefix'               => 'By continuing, you agree to our ',
                'link_terms_and_conditions' => 'https://www.google.com.br',
                'text_suffix'               => 'Terms and Conditions',
            ]
        );
    }

    /**
     * Validate gateway checkout form fields
     *
     * @return bool
     */
    public function validate_fields(): bool
    {
        return true;
    }

    /**
     * Process payment and create woocommerce order
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $order->payment_complete();
        $order->add_order_note('Hey, your order is paid! Thank you!', true);

        wc_reduce_stock_levels($order_id);

        $this->mercadopago->woocommerce->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }

    /**
     * Receive gateway webhook notifications
     *
     * @return void
     */
    public function webhook(): void
    {
        $status   = 200;
        $response = [
            'status'  => $status,
            'message' => 'Webhook handled successful'
        ];

        wp_send_json_success($response, $status);
    }

    /**
     * Get benefits items
     *
     * @param string $site
     * @return array
     */
    private function get_benefits($site)
    {
        $benefits = array(
            'MLB' => array(
                array(
                    'title'    => __('Easy login', 'woocommerce-mercadopago'),
                    'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-phone.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue phone image'
                    )
                ),
                array(
                    'title'    => __('Quick payments', 'woocommerce-mercadopago'),
                    'subtitle' => __('Use your saved cards, Pix or available balance.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-wallet.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue wallet image'
                    )
                ),
                array(
                    'title'    => __('Protected purchases', 'woocommerce-mercadopago'),
                    'subtitle' => __('Get your money back in case you don\'t receive your product.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-protection.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue protection image'
                    )
                )
            ),
            'MLM' => array(
                array(
                    'title'    => __('Easy login', 'woocommerce-mercadopago'),
                    'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-phone.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue phone image'
                    )
                ),
                array(
                    'title'    => __('Quick payments', 'woocommerce-mercadopago'),
                    'subtitle' => __('Use your available Mercado Pago Wallet balance or saved cards.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-wallet.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue wallet image'
                    )
                ),
                array(
                    'title'    => __('Protected purchases', 'woocommerce-mercadopago'),
                    'subtitle' => __('Get your money back in case you don\'t receive your product.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-protection.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue protection image'
                    )
                )
            ),
            'MLA' => array(
                array(
                    'title'    => __('Quick payments', 'woocommerce-mercadopago'),
                    'subtitle' => __('Use your available money or saved cards.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-wallet.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue wallet image'
                    )
                ),
                array(
                    'title'    => __('Installments option', 'woocommerce-mercadopago'),
                    'subtitle' => __('Pay with or without a credit card.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-phone-installments.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue phone installments image'
                    )
                ),
                array(
                    'title'    => __('Reliable purchases', 'woocommerce-mercadopago'),
                    'subtitle' => __('Get help if you have a problem with your purchase.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-protection.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue protection image'
                    )
                )
            ),
            'ROLA' => array(
                array(
                    'title'    => __('Easy login', 'woocommerce-mercadopago'),
                    'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-phone.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue phone image'
                    )
                ),
                array(
                    'title'    => __('Quick payments', 'woocommerce-mercadopago'),
                    'subtitle' => __('Use your available money or saved cards.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-wallet.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue wallet image'
                    )
                ),
                array(
                    'title'    => __('Installments option', 'woocommerce-mercadopago'),
                    'subtitle' => __('Interest-free installments with selected banks.', 'woocommerce-mercadopago'),
                    'image'    => array(
                        'src' => plugins_url('../assets/images/checkouts/basic/blue-phone-installments.png', plugin_dir_path(__FILE__)),
                        'alt' => 'Blue phone installments image'
                    )
                )
            ),
        );

        return array_key_exists($site, $benefits) ? $benefits[ $site ] : $benefits[ 'ROLA' ];
    }

    /**
     * Get Mercado Pago Devsite Page Link
     *
     * @param String $country Country Acronym
     *
     * @return String
     */
    private static function get_mp_devsite_link($country)
    {
        $country_links = [
            'mla' => 'https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/testing',
            'mlb' => 'https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/testing',
            'mlc' => 'https://www.mercadopago.cl/developers/es/guides/plugins/woocommerce/testing',
            'mco' => 'https://www.mercadopago.com.co/developers/es/guides/plugins/woocommerce/testing',
            'mlm' => 'https://www.mercadopago.com.mx/developers/es/guides/plugins/woocommerce/testing',
            'mpe' => 'https://www.mercadopago.com.pe/developers/es/guides/plugins/woocommerce/testing',
            'mlu' => 'https://www.mercadopago.com.uy/developers/es/guides/plugins/woocommerce/testing',
        ];

        $link = array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];

        return $link;
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function get_payment_methods()
    {
        //@TODO change getOption
        $payment_methods_options = get_option('_checkout_payments_methods', '');
        $payment_methods         = [];

        //@TODO add credits helper
        /*
        if ( $this->credits_helper->is_credits() ) {
            $payment_methods[] = [
                'src' => plugins_url( '../assets/images/mercado-credito.png', plugin_dir_path(__FILE__) ),
                'alt' => 'Credits image'
            ];
        }
        */

        foreach ($payment_methods_options as $payment_method_option) {
            //@TODO change getOption
            if ('yes' === $this->get_option($payment_method_option[ 'config' ], '')) {
                $payment_methods[] = [
                    'src' => $payment_method_option[ 'image' ],
                    'alt' => $payment_method_option[ 'id' ]
                ];
            }
        }

        return $payment_methods;
    }
}

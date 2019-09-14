<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Product_Recurrent
 */
class WC_WooMercadoPago_Product_Recurrent
{
    /**
     * WC_WooMercadoPago_Product_Recurrent constructor.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_filter('woocommerce_is_sold_individually', array($this, 'default_no_quantities'), 10, 2);
        add_action('woocommerce_check_cart_items', array($this, 'check_recurrent_product_singularity'));
        add_filter('woocommerce_is_purchasable', array($this, 'filter_woocommerce_is_purchasable'), 10, 2);
        add_action('woocommerce_product_options_general_product_data', array($this, 'mp_add_recurrent_settings'));
        add_action('woocommerce_process_product_meta', array($this, 'mp_save_recurrent_settings'));
    }

    /**
     * Add Meta Boxes
     */
    public function add_meta_boxes()
    {
        // Get order.
        global $post;
        $order = wc_get_order($post->ID);
        if (!isset($order) || $order == false) {
            return;
        }
        $order_id = trim(str_replace('#', '', $order->get_order_number()));
        $payments = get_post_meta($order_id, '_Mercado_Pago_Sub_Payment_IDs', true);
        if (isset($payments) && !empty($payments)) {
            add_meta_box(
                'woocommerce-mp-order-action-refund',
                __('Subscription Mercado Pago', 'woocommerce-mercadopago'),
                'mp_subscription_order_refund_cancel_box',
                'shop_order',
                'side',
                'default'
            );
        }
    }

    /**
     *  Refound Cancel
     */
    public function mp_subscription_order_refund_cancel_box()
    {
        // Get order.
        global $post;
        $order = wc_get_order($post->ID);
        if (!isset($order) || $order == false) {
            return;
        }
        $order_id = trim(str_replace('#', '', $order->get_order_number()));
        // Get payment information for the order.
        $payments = get_post_meta($order_id, '_Mercado_Pago_Sub_Payment_IDs', true);
        $options = '';
        if (!empty($payments)) {
            $payment_structs = array();
            $payment_ids = explode(', ', $payments);
            foreach ($payment_ids as $p_id) {
                $options .= '<option value="' . $p_id . '">' . $p_id . '</option>';
            }
        }
        if ($options == '') {
            return;
        }
        // Build javascript for the window.
        $domain = get_site_url() . '/index.php' . '/woocommerce-mercadopago/';
        $domain .= '?wc-api=WC_WooMercadoPago_SubscriptionGateway';
        echo WC_WooMercadoPago_Module::generate_refund_cancel_subscription(
            $domain,
            __('The operation was successful.', 'woocommerce-mercadopago'),
            __('This operation cannot be completed.', 'woocommerce-mercadopago'),
            $options,
            __('Payment ID:', 'woocommerce-mercadopago'),
            __('Quantity:', 'woocommerce-mercadopago'),
            __('Payment Refund', 'woocommerce-mercadopago'),
            __('Cancel Payment', 'woocommerce-mercadopago')
        );
    }

    /**
     * @param $individually
     * @param $product
     * @return bool
     */
    public function default_no_quantities($individually, $product)
    {
        $product_id = (method_exists($product, 'get_id')) ?
            $product->get_id() :
            $product->id;
        $is_recurrent = get_post_meta($product_id, '_mp_recurring_is_recurrent', true);
        if ($is_recurrent == 'yes') {
            $individually = true;
        }
        return $individually;
    }

    /**
     * Recurrent Product
     */
    public function check_recurrent_product_singularity()
    {
        global $woocommerce;
        $w_cart = $woocommerce->cart;
        if (!isset($w_cart)) {
            return;
        }
        $items = $w_cart->get_cart();
        if (sizeof($items) > 1) {
            foreach ($items as $cart_item_key => $cart_item) {
                $is_recurrent = get_post_meta($cart_item['product_id'], '_mp_recurring_is_recurrent', true);
                if ($is_recurrent == 'yes') {
                    wc_add_notice(
                        __('A recurring product is a subscription that must be purchased separately in your cart. Please place orders separately.', 'woocommerce-mercadopago'),
                        'error'
                    );
                }
            }
        }
    }

    /**
     * @param $purchasable
     * @param $product
     * @return bool
     */
    public function filter_woocommerce_is_purchasable($purchasable, $product)
    {
        $product_id = (method_exists($product, 'get_id')) ?
            $product->get_id() :
            $product->id;
        // skip this check if product is not a subscription
        $is_recurrent = get_post_meta($product_id, '_mp_recurring_is_recurrent', true);
        if ($is_recurrent !== 'yes') {
            return $purchasable;
        }
        $today_date = date('Y-m-d');
        $end_date = get_post_meta($product_id, '_mp_recurring_end_date', true);
        // If there is no date, we should just return the original value.
        if (!isset($end_date) || empty($end_date)) {
            return $purchasable;
        }
        // If end date had passed, this product is no longer available.
        $days_diff = (strtotime($today_date) - strtotime($end_date)) / 86400;
        if ($days_diff >= 0) {
            return false;
        }
        return $purchasable;
    }

    /**
     * ADD SETTINGS
     */
    public function mp_add_recurrent_settings()
    {
        wp_nonce_field('woocommerce_save_data', 'woocommerce_meta_nonce');
        echo '<div class="options_group show_if_simple">';
        woocommerce_wp_checkbox(
            array(
                'id' => '_mp_recurring_is_recurrent',
                'label' => __('Recurring Product', 'woocommerce-mercadopago'),
                'description' => __('Make this product a subscription.', 'woocommerce-mercadopago')
            )
        );
        woocommerce_wp_text_input(
            array(
                'id' => '_mp_recurring_frequency',
                'label' => __('Frequency', 'woocommerce-mercadopago'),
                'placeholder' => '1',
                'desc_tip' => 'true',
                'description' => __('Amount of time (in days or months) for the execution of the next payment.', 'woocommerce-mercadopago'),
                'type' => 'number'
            )
        );
        woocommerce_wp_select(
            array(
                'id' => '_mp_recurring_frequency_type',
                'label' => __('Frequency type', 'woocommerce-mercadopago'),
                'desc_tip' => 'true',
                'description' => __('Indicates the period of time.', 'woocommerce-mercadopago'),
                'options' => array(
                    'days' => __('Days', 'woocommerce-mercadopago'),
                    'months' => __('Months', 'woocommerce-mercadopago')
                )
            )
        );
        woocommerce_wp_text_input(
            array(
                'id' => '_mp_recurring_end_date',
                'label' => __('Final date', 'woocommerce-mercadopago'),
                'placeholder' => _x('YYYY-MM-DD', 'placeholder', 'woocommerce-mercadopago'),
                'desc_tip' => 'true',
                'description' => __('Deadline to generate new charges. By default as never, if it is blank.', 'woocommerce-mercadopago'),
                'class' => 'date-picker',
                'custom_attributes' => array('pattern' => "[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])")
            )
        );
        echo '</div>';
    }

    /**
     * @param $post_id
     */
    public function mp_save_recurrent_settings($post_id)
    {
        $_mp_recurring_is_recurrent = isset($_POST['_mp_recurring_is_recurrent']) ? $_POST['_mp_recurring_is_recurrent'] : '';
        if (!empty($_mp_recurring_is_recurrent)) {
            update_post_meta($post_id, '_mp_recurring_is_recurrent', esc_attr($_mp_recurring_is_recurrent));
        } else {
            update_post_meta($post_id, '_mp_recurring_is_recurrent', esc_attr(null));
        }
        $_mp_recurring_frequency = $_POST['_mp_recurring_frequency'];
        if (!empty($_mp_recurring_frequency)) {
            update_post_meta($post_id, '_mp_recurring_frequency', esc_attr($_mp_recurring_frequency));
        } else {
            update_post_meta($post_id, '_mp_recurring_frequency', esc_attr(1));
        }
        $_mp_recurring_frequency_type = $_POST['_mp_recurring_frequency_type'];
        if (!empty($_mp_recurring_frequency_type)) {
            update_post_meta($post_id, '_mp_recurring_frequency_type', esc_attr($_mp_recurring_frequency_type));
        } else {
            update_post_meta($post_id, '_mp_recurring_frequency_type', esc_attr('days'));
        }
        $_mp_recurring_end_date = $_POST['_mp_recurring_end_date'];
        if (!empty($_mp_recurring_end_date)) {
            update_post_meta($post_id, '_mp_recurring_end_date', esc_attr($_mp_recurring_end_date));
        } else {
            update_post_meta($post_id, '_mp_recurring_end_date', esc_attr(null));
        }
    }
}

new WC_WooMercadoPago_Product_Recurrent();
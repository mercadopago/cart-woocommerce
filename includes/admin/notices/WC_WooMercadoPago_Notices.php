<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_WooMercadoPago_Notification
 */
class WC_WooMercadoPago_Notices
{
    public static $instance = null;

    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'loadAdminNoticeCss']);
    }

    /**
     * @return WC_WooMercadoPago_Module|null
     * Singleton
     */
    public static function initMercadopagoNnotice()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     *
     */
    public function loadAdminNoticeCss()
    {
        if (is_admin()) {
            wp_enqueue_style(
                'woocommerce-mercadopago-admin-notice',
                plugins_url('../../assets/css/admin_notice_mercadopago.css', plugin_dir_path(__FILE__))
            );
        }
    }

    /**
     * @param $message
     * @param $type
     * @return string
     */
    public static function getAlertFrame($message, $type)
    {
        $inline = null;
        if (
            (class_exists('WC_WooMercadoPago_Module') && WC_WooMercadoPago_Module::isWcNewVersion())
            &&
            (isset($_GET['page']) && $_GET['page'] == "wc-settings")
        ) {
            $inline = "inline";
        }

        $notice = '<div id="message" class="notice ' . $type . ' is-dismissible ' . $inline . '">
                    <div class="mp-alert-frame"> 
                        <div class="mp-left-alert">
                            <img src="' . plugins_url('../../assets/images/minilogo.png', plugin_dir_path(__FILE__)) . '">
                        </div>
                        <div class="mp-right-alert">
                            <p>' . $message . '</p>
                        </div>
                    </div>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">' . __('Discard', 'woocommerce-mercadopago') . '</span>
                    </button>
                </div>';
        if (class_exists('WC_WooMercadoPago_Module')) {
            WC_WooMercadoPago_Module::$notices[] = $notice;
        }

        return $notice;
    }

    /**
     * @param $message
     * @param $type
     * @return string
     */
    public static function getAlertWocommerceMiss($message, $type)
    {

        $is_installed = false;

        if (function_exists('get_plugins')) {
            $all_plugins = get_plugins();
            $is_installed = !empty($all_plugins['woocommerce/woocommerce.php']);
        }

        if ($is_installed && current_user_can('install_plugins')) {
            $buttonUrl = '<a href="' . wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=active'), 'activate-plugin_woocommerce/woocommerce.php') . '" class="button button-primary">' . __('Activate WooCommerce', 'woocommerce-mercadopago') . '</a>';
        } else {
            if (current_user_can('install_plugins')) {
                $buttonUrl = '<a href="' . wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=woocommerce'), 'install-plugin_woocommerce') . '" class="button button-primary">' . __('Install WooCommerce', 'woocommerce-mercadopago') . '</a>';
            } else {
                $buttonUrl = '<a href="http://wordpress.org/plugins/woocommerce/" class="button button-primary">' . __('See WooCommerce', 'woocommerce-mercadopago') . '</a>';
            }
        }

        $inline = null;
        if (
            (class_exists('WC_WooMercadoPago_Module') && WC_WooMercadoPago_Module::isWcNewVersion())
            &&
            (isset($_GET['page']) && $_GET['page'] == "wc-settings")
        ) {
            $inline = "inline";
        }

        $notice = '<div id="message" class="notice ' . $type . ' is-dismissible ' . $inline . '">
                    <div class="mp-alert-frame"> 
                        <div class="mp-left-alert">
                            <img src="' . plugins_url('../../assets/images/minilogo.png', plugin_dir_path(__FILE__)) . '">
                        </div>
                        <div class="mp-right-alert">
                            <p>' . $message . '</p>
							<p>' . $buttonUrl . '</p>
                        </div>
                    </div>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">' . __('Discard', 'woocommerce-mercadopago') . '</span>
                    </button>
                </div>';

        if (class_exists('WC_WooMercadoPago_Module')) {
            WC_WooMercadoPago_Module::$notices[] = $notice;
        }
        return $notice;
    }
}

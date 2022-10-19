<?php

namespace MercadoPago\Woocommerce\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Notices
{
    /**
     * @var Notices
     */
    protected static $instance = null;

    public function __construct()
    {
        add_action('admin_enqueue_scripts', array( $this, 'loadAdminNoticeCss' ));
    }

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadAdminNoticeCss()
    {
        if (is_admin()) {
            wp_enqueue_style(
                'woocommerce-mercadopago-admin-notice',
                plugins_url('../assets/css/admin/mp-admin-notice.css', plugin_dir_path(__FILE__)),
                array(),
                MP_VERSION
            );
        }
    }

    public function adminNoticeInfo($message, $dismiss = true)
    {
        $this->adminNotice($message, 'notice-info', $dismiss);
    }

    public function adminNoticeSuccess($message, $dismiss = true)
    {
        $this->adminNotice($message, 'notice-success', $dismiss);
    }

    public function adminNoticeWarning($message, $dismiss = true)
    {
        $this->adminNotice($message, 'notice-warning', $dismiss);
    }

    public function adminNoticeError($message, $dismiss = true)
    {
        $this->adminNotice($message, 'notice-error', $dismiss);
    }

    public function adminNoticeMissWoocoommerce()
    {
        add_action(
            'admin_notices',
            function () {
                $isInstalled = false;
                $currentUserCanInstallPlugins = current_user_can('install_plugins');
                $minilogo = plugins_url('../assets/images/minilogo.png', plugin_dir_path(__FILE__));

                if (function_exists('get_plugins')) {
                    $allPlugins  = get_plugins();
                    $isInstalled = !empty($allPlugins['woocommerce/woocommerce.php']);
                }

                if ($isInstalled && $currentUserCanInstallPlugins) {
                    $missWoocommerceAction = 'active';
                } else {
                    if ($currentUserCanInstallPlugins) {
                        $missWoocommerceAction = 'install';
                    } else {
                        $missWoocommerceAction = 'see';
                    }
                }

                include dirname(__FILE__) . '/../../templates/admin/notices/miss-woocommerce-notice.php';
            }
        );
    }

    private function adminNotice($message, $type, $dismiss)
    {
        add_action(
            'admin_notices',
            function () use ($message, $type, $dismiss) {
                $minilogo = plugins_url('../assets/images/minilogo.png', plugin_dir_path(__FILE__));
                $isDismissible = $dismiss ? 'is-dismissible' : '';

                include dirname(__FILE__) . '/../../templates/admin/notices/generic-notice.php';
            }
        );
    }
}

<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Scripts;

if (!defined('ABSPATH')) {
    exit;
}

class Notices
{
    /**
     * @var Scripts
     */
    protected $scripts;

    /**
     * @var Notices
     */
    private static $instance = null;

    /**
     * Notices constructor
     */
    private function __construct()
    {
        $this->scripts = Scripts::getInstance();

        add_action('admin_enqueue_scripts', array($this, 'loadAdminNoticeCss'));
    }

    /**
     * Get Notice instance
     *
     * @return Notices
     */
    public static function getInstance(): Notices
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load admin notice css
     *
     * @return void
     */
    public function loadAdminNoticeCss(): void
    {
        if (is_admin()) {
            $this->scripts->registerAdminStyle(
                'woocommerce-mercadopago-admin-notice',
                Url::getPluginFileUrl('assets/css/admin/mp-admin-notice', '.css')
            );
        }
    }

    /**
     * Set a notice info
     *
     * @param string $message
     * @param bool $dismiss
     *
     * @return void
     */
    public function adminNoticeInfo(string $message, bool $dismiss = true): void
    {
        $this->adminNotice($message, 'notice-info', $dismiss);
    }

    /**
     * Set a notice success
     *
     * @param string $message
     * @param bool $dismiss
     *
     * @return void
     */
    public function adminNoticeSuccess(string $message, bool $dismiss = true): void
    {
        $this->adminNotice($message, 'notice-success', $dismiss);
    }

    /**
     * Set a notice warning
     *
     * @param string $message
     * @param bool $dismiss
     *
     * @return void
     */
    public function adminNoticeWarning(string $message, bool $dismiss = true): void
    {
        $this->adminNotice($message, 'notice-warning', $dismiss);
    }

    /**
     * Set a notice error
     *
     * @param string $message
     * @param bool $dismiss
     *
     * @return void
     */
    public function adminNoticeError(string $message, bool $dismiss = true): void
    {
        $this->adminNotice($message, 'notice-error', $dismiss);
    }

    /**
     * Show woocommerce missing notice
     *
     * @return void
     */
    public function adminNoticeMissWoocoommerce(): void
    {
        add_action(
            'admin_notices',
            function () {
                $isInstalled = false;
                $currentUserCanInstallPlugins = current_user_can('install_plugins');
                $minilogo = plugins_url('../assets/images/minilogo.png', plugin_dir_path(__FILE__));
                $translations = Translations::$notices;

                $activateLink = wp_nonce_url(
                    self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=all'),
                    'activate-plugin_woocommerce/woocommerce.php'
                );

                $installLink = wp_nonce_url(
                    self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                    'install-plugin_woocommerce'
                );

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

    /**
     * Show admin notice
     *
     * @param string $message
     * @param string $type
     * @param bool $dismiss
     *
     * @return void
     */
    private function adminNotice(string $message, string $type, bool $dismiss): void
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

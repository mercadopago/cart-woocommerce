<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Translations\AdminTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Notices
{
    /**
     * @var Scripts
     */
    private $scripts;

    /**
     * @var AdminTranslations
     */
    private $translations;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var Links
     */
    private $links;

    /**
     * @var CurrentUser
     */
    private $currentUser;

    /**
     * Notices constructor
     */
    public function __construct(
        Scripts $scripts,
        AdminTranslations $translations,
        Url $url,
        Links $links,
        CurrentUser $currentUser
    ) {
        $this->scripts      = $scripts;
        $this->translations = $translations;
        $this->url          = $url;
        $this->links        = $links;
        $this->currentUser  = $currentUser;

        $this->loadAdminNoticeCss();
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
                $this->url->getPluginFileUrl('assets/css/admin/mp-admin-notice', '.css')
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
                $currentUserCanInstallPlugins = $this->currentUser->currentUserCan('install_plugins');

                $minilogo     = $this->url->getPluginFileUrl('assets/images/minilogo', '.png', true);
                $translations = $this->translations->notices;

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
     * Show pix missing notice
     * @return void
     */
    public function adminNoticeMissPix(): void
    {
        add_action(
            'admin_notices',
            function () {
                $miniLogo = $this->url->getPluginFileUrl('assets/images/minilogo', '.png', true);
                $message  = $this->translations->notices['miss_pix_text'];
                $textLink = $this->translations->notices['miss_pix_link'];
                $urlLink  = $this->links->getLinks()['mercadopago_pix_config'];

                include dirname(__FILE__) . '/../../templates/admin/notices/miss-pix-notice.php';
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
                $minilogo = $this->url->getPluginFileUrl('assets/images/minilogo', '.png', true);
                $isDismissible = $dismiss ? 'is-dismissible' : '';

                include dirname(__FILE__) . '/../../templates/admin/notices/generic-notice.php';
            }
        );
    }

    /**
     * Show approved store notice
     *
     * @param $orderStatus
     *
     * @return void
     */
    public function storeApprovedStatusNotice($orderStatus): void
    {
        $this->storeNotice($orderStatus, 'notice');
    }

    /**
     * Show in process store notice
     *
     * @param $orderStatus
     * @param string $urlReceived
     * @param string $checkoutType
     * @param string $linkText
     *
     * @return void
     */
    public function storePendingStatusNotice($orderStatus, string $urlReceived, string $checkoutType, string $linkText): void
    {
        $message = "
            <p>$orderStatus</p>
            <a id='mp_pending_payment_button' class='button' href=''$urlReceived' data-mp-checkout-type='woo-mercado-pago-$checkoutType'>
                $linkText
            </a>
        ";

        $this->storeNotice($message, 'notice');
    }

    /**
     * Show in process store notice
     *
     * @param string $noticeTitle
     * @param string $orderStatus
     * @param string $urlReceived
     * @param string $checkoutType
     * @param string $linkText
     *
     * @return void
     */
    public function storeRejectedStatusNotice(string $noticeTitle, string $orderStatus, string $urlReceived, string $checkoutType, string $linkText): void
    {
        $message = "
            <p>$noticeTitle</p>
            <span>$orderStatus</span>
            <a id='mp_failed_payment_button' class='button' href='$urlReceived' data-mp-checkout-type='woo-mercado-pago-$checkoutType'>
                $linkText
            </a>
        ";

        $this->storeNotice($message, 'error');
    }

    /**
     * Show store notice
     *
     * @param string $message
     * @param string $type
     * @param array $data
     *
     * @return void
     */
    public function storeNotice(string $message, string $type = 'success', array $data = []): void
    {
        wc_add_notice($message, $type, $data);
    }
}

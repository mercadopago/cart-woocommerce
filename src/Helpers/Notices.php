<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Configs\Seller;

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
     * @var array
     */
    private $links;

    /**
     * @var CurrentUser
     */
    private $currentUser;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Nonce
     */
    private $nonce;

    /**
     * @var Endpoints
     */
    private $endpoints;

    /**
     * @var Seller
     */
    private $sellerConfig;

    /**
     * @const
     */
    private const NONCE_ID = 'mp_notices_dismiss';

    /**
     * Notices constructor
     *
     * @param Scripts $scripts
     * @param AdminTranslations $translations
     * @param Url $url
     * @param Links $links
     * @param CurrentUser $currentUser
     * @param Store $store
     * @param Nonce $nonce
     * @param Endpoints $endpoints
     * @param Seller $sellerConfig
     */

    public function __construct(
        Scripts $scripts,
        AdminTranslations $translations,
        Url $url,
        Links $links,
        CurrentUser $currentUser,
        Store $store,
        Nonce $nonce,
        Endpoints $endpoints,
        Seller $sellerConfig
    ) {
        $this->scripts      = $scripts;
        $this->translations = $translations;
        $this->url          = $url;
        $this->links        = $links->getLinks();
        $this->currentUser  = $currentUser;
        $this->store        = $store;
        $this->nonce        = $nonce;
        $this->endpoints    = $endpoints;
        $this->sellerConfig = $sellerConfig;

        $this->loadAdminNoticesCss();
        $this->loadAdminNoticesJs();
        $this->insertDismissibleNotices();
        $this->endpoints->registerAjaxEndpoint('mp_review_notice_dismiss', [$this, 'reviewNoticeDismiss']);
        $this->endpoints->registerAjaxEndpoint('mp_saved_cards_notice_dismiss', [$this, 'savedCardsDismiss']);
        add_action('woocommerce_order_status_processing', [$this, 'checkOrderCompleted'], 10, 1);
    }

    /**
     * Load admin notices css
     *
     * @return void
     */
    public function loadAdminNoticesCss(): void
    {
        if (is_admin()) {
            $this->scripts->registerAdminStyle(
                'woocommerce-mercadopago-admin-notice-css',
                $this->url->getPluginFileUrl('assets/css/admin/mp-admin-notices', '.css')
            );
        }
    }

    /**
     * Load admin notices js
     *
     * @return void
     */
    public function loadAdminNoticesJs(): void
    {
        if (is_admin()) {
            $this->scripts->registerAdminScript(
                'woocommerce_mercadopago_admin_notice_js',
                $this->url->getPluginFileUrl('assets/js/admin/mp-admin-notices', '.js'),
                [
                    'nonce' => $this->nonce->generateNonce(self::NONCE_ID)
                ]
            );
        }
    }

    /**
     * Insert admin dismissible notices
     *
     * @return void
     */
    public function insertDismissibleNotices(): void
    {
        if (!$this->shouldShowNotices()) {
            return;
        }

        if (!$this->store->getDismissedReviewNotice()) {
            if ($this->store->getAnyOrderCompleted()) {
                add_action(
                    'admin_notices',
                    function () {
                        $title      = $this->translations->notices['dismissed_review_title'];
                        $subtitle   = $this->translations->notices['dismissed_review_subtitle'];
                        $buttonText = $this->translations->notices['dismissed_review_button'];
                        $buttonLink = $this->links['wordpress_review_link'];
                        include dirname(__FILE__) . '/../../templates/admin/notices/review-notice.php';
                    }
                );
            }
        }

        if (
            !$this->store->getDismissedSavedCardsNotice() &&
            !empty($this->sellerConfig->getCredentialsPublicKey()) && !empty($this->sellerConfig->getCredentialsAccessToken())
        ) {
            add_action(
                'admin_notices',
                function () {
                    $cardIcon   = $this->url->getPluginFileUrl('assets/images/icons/icon-mp-card', '.png', true);
                    $title      = $this->translations->notices['saved_cards_title'];
                    $subtitle   = $this->translations->notices['saved_cards_subtitle'];
                    $buttonText = $this->translations->notices['saved_cards_button'];
                    $buttonLink = $this->links['admin_settings_page'];

                    include dirname(__FILE__) . '/../../templates/admin/notices/saved-cards-notice.php';
                }
            );
        }
    }

    /**
     * Check if notices should be shown
     *
     * @return bool
     */
    public function shouldShowNotices(): bool
    {
        return is_admin() &&
            (
                $this->url->validateSection('mercado-pago')
                || $this->url->validateUrl('index')
                || $this->url->validateUrl('plugins')
            );
    }

    /**
     * Check if notices should be shown for settings section
     *
     * @return bool
     */
    public function shouldShowNoticesForSettingsSection(): bool
    {
        return is_admin() &&
            (
                $this->url->validatePage('mercadopago-settings')
            );
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
                $urlLink  = $this->links['mercadopago_pix_config'];

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
            <a id='mp_pending_payment_button' class='button' href='$urlReceived' data-mp-checkout-type='woo-mercado-pago-$checkoutType'>
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

    /**
     * Dismiss the review admin notice
     */
    public function reviewNoticeDismiss(): void
    {
        $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizeTextFromPost('nonce'));
        $this->currentUser->validateUserNeededPermissions();

        $this->store->setDismissedReviewNotice(1);
        wp_send_json_success();
    }

    /**
     * Dismiss the saved cards admin notice
     */
    public function savedCardsDismiss(): void
    {
        $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizeTextFromPost('nonce'));
        $this->currentUser->validateUserNeededPermissions();

        $this->store->setDismissedSavedCardsNotice(1);
        wp_send_json_success();
    }

    public function checkOrderCompleted($order_id)
    {
        if (!$this->store->getAnyOrderCompleted()) {
            $order = wc_get_order($order_id);
            $paymentMethod = $order->get_payment_method();
            foreach ($this->store->getAvailablePaymentGateways() as $gateway) {
                if ($gateway::ID === $paymentMethod) {
                    $this->store->setAnyOrderCompleted(1);
                }
            }
        }
    }

    /**
     * Show instructional notice
     *
     * @return void
     */
    public function instructionalNotice(): void
    {
        add_action(
            'admin_notices',
            function () {
                $minilogo = $this->url->getPluginFileUrl('assets/images/icons/icon-feedback-info', '.png', true);
                $title = $this->translations->notices['action_feedback_title'];
                $subtitle = $this->translations->notices['action_feedback_subtitle'];

                include dirname(__FILE__) . '/../../templates/admin/notices/action-feedback-notice.php';
            }
        );
    }
}

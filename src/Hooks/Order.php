<?php

namespace MercadoPago\Woocommerce\Hooks;

use Exception;
use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\PaymentStatus;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

class Order
{
    /**
     * @var Template
     */
    private $template;

    /**
     * @var OrderMetadata
     */
    private $orderMetadata;

    /**
     * @var OrderStatus
     */
    private $orderStatus;

    /**
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * @var AdminTranslations
     */
    private $adminTranslations;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var Seller
     */
    private $seller;

    /**
     * @var Scripts
     */
    private $scripts;

    /**
     * @var Url
     */
    private $url;

    /**
     * @var Nonce
     */
    private $nonce;

    /**
     * @var Endpoints
     */
    private $endpoints;

    /**
     * @var CurrentUser
     */
    private $currentUser;

    /**
     * @var Requester
     */
    private $requester;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @const
     */
    private const NONCE_ID = 'MP_ORDER_NONCE';

          /**
     * Order constructor
     *
     * @param Template $template
     * @param OrderMetadata $orderMetadata
     * @param OrderStatus $orderStatus
     * @param AdminTranslations $adminTranslations
     * @param StoreTranslations $storeTranslations
     * @param Store $store
     * @param Seller $seller
     * @param Scripts $scripts
     * @param Url $url
     * @param Nonce $nonce
     * @param Endpoints $endpoints
     * @param CurrentUser $currentUser
     * @param Requester $requester
     * @param Logs $logs
     */
    public function __construct(
        Template $template,
        OrderMetadata $orderMetadata,
        OrderStatus $orderStatus,
        AdminTranslations $adminTranslations,
        StoreTranslations $storeTranslations,
        Store $store,
        Seller $seller,
        Scripts $scripts,
        Url $url,
        Nonce $nonce,
        Endpoints $endpoints,
        CurrentUser $currentUser,
        Requester $requester,
        Logs $logs
    ) {
        $this->template          = $template;
        $this->orderMetadata     = $orderMetadata;
        $this->orderStatus       = $orderStatus;
        $this->adminTranslations = $adminTranslations;
        $this->storeTranslations = $storeTranslations;
        $this->store             = $store;
        $this->seller            = $seller;
        $this->scripts           = $scripts;
        $this->url               = $url;
        $this->nonce             = $nonce;
        $this->endpoints         = $endpoints;
        $this->currentUser       = $currentUser;
        $this->requester         = $requester;
        $this->logs              = $logs;

        $this->registerStatusSyncMetaBox();
        $this->endpoints->registerAjaxEndpoint('mp_sync_payment_status', [$this, 'paymentStatusSync']);
    }

    /**
     * Registers the Status Sync Metabox
     */
    private function registerStatusSyncMetabox(): void
    {
        $this->registerMetaBox(function ($postOrOrderObject) {
            $order = ($postOrOrderObject instanceof \WP_Post) ? wc_get_order($postOrOrderObject->ID) : $postOrOrderObject;

            if (!$order || !$this->getLastPaymentInfo($order)) {
                return;
            }

            $paymentMethod     = $this->orderMetadata->getUsedGatewayData($order);
            $isMpPaymentMethod = array_filter($this->store->getAvailablePaymentGateways(), function ($gateway) use ($paymentMethod) {
                return $gateway::ID === $paymentMethod || $gateway::WEBHOOK_API_NAME === $paymentMethod;
            });

            if (!$isMpPaymentMethod) {
                return;
            }

            $this->loadScripts($order);

            $this->addMetaBox(
                'mp_payment_status_sync',
                $this->adminTranslations->statusSync['metabox_title'],
                'admin/order/payment-status-metabox-content.php',
                $this->getMetaboxData($order)
            );
        });
    }

    /**
     * Load the Status Sync Metabox script and style
     *
     * @param \WC_Order $order
     */
    private function loadScripts(\WC_Order $order): void
    {
        $this->scripts->registerStoreScript(
            'mp_payment_status_sync',
            $this->url->getPluginFileUrl('assets/js/admin/order/payment-status-sync', '.js'),
            [
                'order_id' => $order->get_id(),
                'nonce' => $this->nonce->generateNonce(self::NONCE_ID),
            ]
        );

        $this->scripts->registerStoreStyle(
            'mp_payment_status_sync',
            $this->url->getPluginFileUrl('assets/css/admin/order/payment-status-sync', '.css')
        );
    }

    /**
     * Get the data to be renreded on the Status Sync Metabox
     *
     * @param \WC_Order $order
     *
     * @return array
     */
    private function getMetaboxData(\WC_Order $order): array
    {
        $paymentInfo  = $this->getLastPaymentInfo($order);

        $isCreditCard      = $paymentInfo['payment_type_id'] === 'credit_card';
        $paymentStatusType = PaymentStatus::getStatusType($paymentInfo['status']);

        $cardContent = PaymentStatus::getCardDescription(
            $this->adminTranslations->statusSync,
            $paymentInfo['status_detail'],
            $isCreditCard
        );

        switch ($paymentStatusType) {
            case 'success':
                return [
                    'card_title'        => $this->adminTranslations->statusSync['card_title'],
                    'img_src'           => $this->url->getPluginFileUrl('assets/images/icons/icon-success', '.png', true),
                    'alert_title'       => $cardContent['alert_title'],
                    'alert_description' => $cardContent['description'],
                    'link'              => 'https://www.mercadopago.com',
                    'border_left_color' => '#00A650',
                    'link_description'  => $this->adminTranslations->statusSync['link_description_success'],
                    'sync_button_text'  => $this->adminTranslations->statusSync['sync_button_success'],
                ];

            case 'pending':
                return [
                    'card_title'        => $this->adminTranslations->statusSync['card_title'],
                    'img_src'           => $this->url->getPluginFileUrl('assets/images/icons/icon-alert', '.png', true),
                    'alert_title'       => $cardContent['alert_title'],
                    'alert_description' => $cardContent['description'],
                    'link'              => 'https://www.mercadopago.com',
                    'border_left_color' => '#f73',
                    'link_description'  => $this->adminTranslations->statusSync['link_description_pending'],
                    'sync_button_text'  => $this->adminTranslations->statusSync['sync_button_pending'],
                ];

            case 'rejected':
            case 'refunded':
            case 'charged_back':
                return [
                    'card_title'        => $this->adminTranslations->statusSync['card_title'],
                    'img_src'           => $this->url->getPluginFileUrl('assets/images/icons/icon-warning', '.png', true),
                    'alert_title'       => $cardContent['alert_title'],
                    'alert_description' => $cardContent['description'],
                    'link'              => $this->adminTranslations->links['reasons_refusals'],
                    'border_left_color' => '#F23D4F',
                    'link_description'  => $this->adminTranslations->statusSync['link_description_failure'],
                    'sync_button_text'  => $this->adminTranslations->statusSync['sync_button_failure'],
                ];

            default:
                return [];
        }
    }

    /**
     * Get the last order payment info
     *
     * @param \WC_Order $order
     *
     * @return bool|AbstractCollection|AbstractEntity|object
     */
    private function getLastPaymentInfo(\WC_Order $order)
    {
        try {
            $paymentsIds   = explode(',', $this->orderMetadata->getPaymentsIdMeta($order));
            $lastPaymentId = trim(end($paymentsIds));

            if (!$lastPaymentId) {
                return false;
            }

            $headers  = ['Authorization: Bearer ' . $this->seller->getCredentialsAccessToken()];
            $response = $this->requester->get("/v1/payments/$lastPaymentId", $headers);

            return $response->getData();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Updates the order based on current payment status from API
     *
     */
    public function paymentStatusSync(): void
    {
        try {
            $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizeTextFromPost('nonce'));
            $this->currentUser->validateUserNeededPermissions();

            $order       = wc_get_order(Form::sanitizeTextFromPost('order_id'));
            $paymentData = $this->getLastPaymentInfo($order);

            if (!$paymentData) {
                throw new Exception('Couldn\'t find payment');
            }
            $this->orderStatus->processStatus($paymentData['status'], (array) $paymentData, $order, $this->orderMetadata->getUsedGatewayData($order));

            wp_send_json_success(
                $this->adminTranslations->statusSync['response_success']
            );
        } catch (\Exception $e) {
            $this->logs->file->error(
                "Mercado pago gave error in payment status Sync: {$e->getMessage()}",
                __CLASS__
            );

            wp_send_json_error(
                $this->adminTranslations->statusSync['response_error'] . ' ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Register meta box addition on order page
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerMetaBox($callback): void
    {
        add_action('add_meta_boxes_shop_order', $callback);
        add_action('add_meta_boxes_woocommerce_page_wc-orders', $callback);
    }

    /**
     * Add a meta box to screen
     *
     * @param string $id
     * @param string $title
     * @param string $name
     * @param array $args
     *
     * @return void
     */
    public function addMetaBox(string $id, string $title, string $name, array $args): void
    {
        add_meta_box($id, $title, function () use ($name, $args) {
            $this->template->getWoocommerceTemplate($name, $args);
        });
    }

    /**
     * Register order details after order table
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOrderDetailsAfterOrderTable($callback): void
    {
        add_action('woocommerce_order_details_after_order_table', $callback);
    }

    /**
     * Register email before order table
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerEmailBeforeOrderTable($callback): void
    {
        add_action('woocommerce_email_before_order_table', $callback);
    }

    /**
     * Register total line after WooCommerce order totals callback
     *
     * @param mixed $callback
     *
     * @return void
     */
    public function registerAdminOrderTotalsAfterTotal($callback): void
    {
        add_action('woocommerce_admin_order_totals_after_total', $callback);
    }

    /**
     * Add order note
     *
     * @param \WC_Order $order
     * @param string $description
     * @param int $isCustomerNote
     * @param bool $addedByUser
     *
     * @return void
     */
    public function addOrderNote(\WC_Order $order, string $description, int $isCustomerNote = 0, bool $addedByUser = false)
    {
        $order->add_order_note($description, $isCustomerNote, $addedByUser);
    }

    /**
     * Set ticket metadata in the order
     *
     * @param \WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setTicketMetadata(\WC_Order $order, $data): void
    {
        $externalResourceUrl = $data['transaction_details']['external_resource_url'];
        $this->orderMetadata->setTicketTransactionDetailsData($order, $externalResourceUrl);
        $order->save();
    }

    /**
     * Set pix metadata in the order
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setPixMetadata(AbstractGateway $gateway, \WC_Order $order, $data): void
    {
        $transactionAmount = $data['transaction_amount'];
        $qrCodeBase64      = $data['point_of_interaction']['transaction_data']['qr_code_base64'];
        $qrCode            = $data['point_of_interaction']['transaction_data']['qr_code'];
        $defaultValue      = $this->storeTranslations->pixCheckout['expiration_30_minutes'];
        $expiration        = $this->store->getCheckoutDateExpirationPix($gateway, $defaultValue);

        $this->orderMetadata->setTransactionAmountData($order, $transactionAmount);
        $this->orderMetadata->setPixQrBase64Data($order, $qrCodeBase64);
        $this->orderMetadata->setPixQrCodeData($order, $qrCode);
        $this->orderMetadata->setPixExpirationDateData($order, $expiration);
        $this->orderMetadata->setPixExpirationDateData($order, $expiration);
        $this->orderMetadata->setPixOnData($order, 1);

        $order->save();
    }
}

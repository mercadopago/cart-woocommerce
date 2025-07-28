<?php

namespace MercadoPago\Woocommerce\Hooks;

use Exception;
use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Libraries\Singleton\Singleton;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Cron;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\PaymentStatus;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use WC_Order;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

class Order
{
    private Template $template;

    private OrderMetadata $orderMetadata;

    private OrderStatus $orderStatus;

    private StoreTranslations $storeTranslations;

    private AdminTranslations $adminTranslations;

    private Store $store;

    private Seller $seller;

    private Scripts $scripts;

    private Url $url;

    private Nonce $nonce;

    private Endpoints $endpoints;

    private Cron $cron;

    private CurrentUser $currentUser;

    private Requester $requester;

    private Logs $logs;

    private Singleton $datadog;

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
     * @param Cron $cron
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
        Cron $cron,
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
        $this->cron              = $cron;
        $this->currentUser       = $currentUser;
        $this->requester         = $requester;
        $this->logs              = $logs;
        $this->datadog           = Datadog::getInstance();

        $this->registerStatusSyncMetaBox();
        $this->registerSyncPendingStatusOrdersAction();
        $this->endpoints->registerAjaxEndpoint('mp_sync_payment_status', [$this, 'paymentStatusSync']);
    }

    /**
     * Registers the Status Sync Metabox
     */
    private function registerStatusSyncMetabox(): void
    {
        $this->registerMetaBox(function ($postOrOrderObject) {
            $order = ($postOrOrderObject instanceof WP_Post) ? wc_get_order($postOrOrderObject->ID) : $postOrOrderObject;

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
     * @param WC_Order $order
     */
    private function loadScripts(WC_Order $order): void
    {
        $this->scripts->registerStoreScript(
            'mp_payment_status_sync',
            $this->url->getJsAsset('admin/order/payment-status-sync'),
            [
                'order_id' => $order->get_id(),
                'nonce' => $this->nonce->generateNonce(self::NONCE_ID),
            ]
        );

        $this->scripts->registerStoreStyle(
            'mp_payment_status_sync',
            $this->url->getCssAsset('admin/order/payment-status-sync')
        );
    }

    /**
     * Get the data to be renreded on the Status Sync Metabox
     *
     * @param WC_Order $order
     *
     * @return array
     */
    private function getMetaboxData(WC_Order $order): array
    {
        try {
            $paymentInfo  = $this->getLastPaymentInfo($order);
        } catch (Exception $e) {
            $this->logs->file->error('Mercado Pago: Error getting payment info for metabox: ' . $e->getMessage(), __CLASS__);
            return [];
        }

        $isCreditCard      = $paymentInfo['payment_type_id'] === 'credit_card';
        $paymentStatusType = PaymentStatus::getStatusType($paymentInfo['status']);

        $cardContent = PaymentStatus::getCardDescription(
            $this->adminTranslations->statusSync,
            $paymentInfo['status_detail'],
            $isCreditCard
        );

        try {
            $paymentsData = $this->orderStatus->getPaymentsData($order);
        } catch (Exception $e) {
            $this->logs->file->error('Mercado Pago: Error getting payments data for metabox: ' . $e->getMessage(), __CLASS__);
            return [];
        }

        if ($this->hasMultiplePayments($paymentsData) && $this->hasRefundedPayments($paymentsData)) {
            $refundedStatusDetail = $this->orderStatus->getRefundedStatusDetail($paymentsData);

            $paymentStatusType = PaymentStatus::getStatusType($refundedStatusDetail['title']);

            $cardContent = PaymentStatus::getCardDescription(
                $this->adminTranslations->statusSync,
                $refundedStatusDetail['description'],
                $isCreditCard
            );
        }

        switch ($paymentStatusType) {
            case 'success':
                return [
                    'card_title'        => $this->adminTranslations->statusSync['card_title'],
                    'img_src'           => $this->url->getImageAsset('icons/icon-success'),
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
                    'img_src'           => $this->url->getImageAsset('icons/icon-alert'),
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
                    'img_src'           => $this->url->getImageAsset('icons/icon-warning'),
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
     * @param WC_Order $order
     *
     * @return bool|AbstractCollection|AbstractEntity|object
     */
    private function getLastPaymentInfo(WC_Order $order)
    {
        try {
            $paymentsData = $this->orderStatus->getPaymentsData($order);
            if (empty($paymentsData)) {
                return false;
            }

            $data = end($paymentsData);

            if (
                count($paymentsData) === 1 &&
                isset($data['status'], $data['status_detail']) &&
                $data['status'] === 'refunded' &&
                $data['status_detail'] === 'by_admin'
            ) {
                $data['status_detail'] = 'refunded';
            }

            return $data;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if the order has multiple payments
     *
     * @param array $paymentsData
     *
     * @return bool
     */
    private function hasMultiplePayments(array $paymentsData): bool
    {
        return count($paymentsData) > 1;
    }

    /**
     * Check if the order has refunded payments
     *
     * @param array $paymentsData
     *
     * @return bool
     */
    private function hasRefundedPayments(array $paymentsData): bool
    {
        foreach ($paymentsData as $payment) {
            if (($payment['status'] === 'refunded') || ($payment['status_detail'] === 'partially_refunded')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Updates the order based on current payment status from API
     *
     */
    public function paymentStatusSync(): void
    {
        try {
            $this->nonce->validateNonce(self::NONCE_ID, Form::sanitizedPostData('nonce'));
            $this->currentUser->validateUserNeededPermissions();

            $orderId = Form::sanitizedPostData('order_id');
            $order = wc_get_order($orderId);
            $this->syncOrderStatus($order);

            wp_send_json_success(
                $this->adminTranslations->statusSync['response_success']
            );
        } catch (Exception $e) {
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
     * Syncs the order in woocommerce to mercadopago
     *
     * @param WC_Order $order
     *
     * @return void
     */
    public function syncOrderStatus(WC_Order $order): void
    {
        if (!$paymentData = $this->getLastPaymentInfo($order)) {
            $this->logs->file->error('Mercado Pago: Error getting payment info for sync order status', __CLASS__);
            return;
        }

        $this->orderStatus->processStatus($paymentData['status'], (array) $paymentData, $order, $this->orderMetadata->getUsedGatewayData($order));
    }

    /**
     * Register action that sync orders with pending status with corresponding status in mercadopago
     *
     * @return void
     */
    public function registerSyncPendingStatusOrdersAction(): void
    {
        add_action('mercadopago_sync_pending_status_order_action', function () {
            try {
                $orders = wc_get_orders(array(
                    'limit'    => -1,
                    'status'   => 'pending',
                    'meta_query' => array(
                        array(
                            'key' => 'is_production_mode',
                            'compare' => 'EXISTS'
                        ),
                        array(
                            'key' => 'blocks_payment',
                            'compare' => 'EXISTS'
                        )
                    )
                ));

                foreach ($orders as $order) {
                    try {
                        if ($this->orderMetadata->getSyncCronErrorCount($order) > 2) {
                            continue;
                        }

                        $this->syncOrderStatus($order);
                    } catch (Exception $ex) {
                        $this->orderMetadata->incrementSyncCronErrorCount($order);
                        $error_message = "Unable to update order {$order->get_id()} on action got error: {$ex->getMessage()}";

                        $this->logs->file->error(
                            $error_message,
                            __CLASS__
                        );
                    }
                }

                $this->sendEventOnAction('success');
            } catch (Exception $ex) {
                $error_message = "Unable to update batch of orders on action got error: {$ex->getMessage()}";

                $this->logs->file->error(
                    $error_message,
                    __CLASS__
                );
                $this->sendEventOnAction('error', $error_message);
            }
        });
    }

    /**
     * Register/Unregister cron job that sync pending orders
     *
     * @param string $enabled
     *
     * @return void
     */
    public function selectSyncPendingStatusOrdersCron(string $value): void
    {
        $action = 'mercadopago_sync_pending_status_order_action';

        if ($value !== 'no') {
            $this->cron->registerScheduledEvent($value, $action);
        } else {
            $this->cron->unregisterScheduledEvent($action);
        }

        $this->sendEventOnSelect($value);
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
     * @param WC_Order $order
     * @param string $description
     * @param int $isCustomerNote
     * @param bool $addedByUser
     *
     * @return void
     */
    public function addOrderNote(WC_Order $order, string $description, int $isCustomerNote = 0, bool $addedByUser = false)
    {
        $order->add_order_note($description, $isCustomerNote, $addedByUser);
    }

    /**
     * Set ticket metadata in the order
     *
     * @param WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setTicketMetadata(WC_Order $order, $data): void
    {
        $externalResourceUrl = $data['transaction_details']['external_resource_url'];
        $this->orderMetadata->setTicketTransactionDetailsData($order, $externalResourceUrl);
        $order->save();
    }

    /**
     * Set pix metadata in the order
     *
     * @param AbstractGateway $gateway
     * @param WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setPixMetadata(AbstractGateway $gateway, WC_Order $order, $data): void
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

    /**
     * Send an datadog event inside the sync order status action on fail and success
     */
    private function sendEventOnAction($value, $message = null)
    {
        $this->datadog->sendEvent('order_sync_status_action', $value, $message);
    }

    /**
     * Send an datadog event when an seller toggles (activating or deactivating) the cron button
     */
    private function sendEventOnSelect($value)
    {
        $this->datadog->sendEvent('order_toggle_cron', $value);
    }
}

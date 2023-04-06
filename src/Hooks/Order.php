<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

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
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * @var Store
     */
    private $store;

    /**
     * Order constructor
     */
    public function __construct(Template $template, OrderMetadata $orderMetadata, StoreTranslations $storeTranslations, Store $store)
    {
        $this->template          = $template;
        $this->orderMetadata     = $orderMetadata;
        $this->storeTranslations = $storeTranslations;
        $this->store             = $store;
    }

    /**
     * Register meta box addition on order page
     *
     * @param string $id
     * @param string $title
     * @param string $name
     * @param array $args
     * @param string $path
     *
     * @return void
     */
    public function registerMetaBox(string $id, string $title, string $name, array $args, string $path): void
    {
        add_action('add_meta_boxes_shop_order', function () use ($id, $title, $name, $args, $path) {
            $this->addMetaBox($id, $title, $name, $args);
        });
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
     * Register order actions
     *
     * @param array $action
     *
     * @return void
     */
    public function registerOrderActions(array $action): void
    {
        add_action('woocommerce_order_actions', function ($actions) use ($action) {
            $actions[] = $action;
            return $actions;
        });
    }

    /**
     * Register order status transition
     *
     * @param string $toStatus
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOrderStatusTransitionTo(string $toStatus, $callback): void
    {
        add_action('woocommerce_order_status_' . $toStatus, $callback);
    }

    /**
     * Register order status transition
     *
     * @param string $fromStatus
     * @param string $toStatus
     * @param mixed $callback
     *
     * @return void
     */
    public function registerOrderStatusTransitionFromTo(string $fromStatus, string $toStatus, $callback): void
    {
        add_action('woocommerce_order_status_' . $fromStatus . '_to_' . $toStatus, $callback);
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
     * Register total line after WooCommerce order totals template
     *
     * @param string $tip
     * @param string $title
     * @param string $value
     *
     * @return void
     */
    public function registerAdminOrderTotalsAfterTotalTemplate(string $tip, string $title, string $value): void
    {
        add_action('woocommerce_admin_order_totals_after_total', function ($orderId) use ($tip, $title, $value) {
            $this->template->getWoocommerceTemplate(
                'admin/order/generic-note.php',
                [
                    'tip'   => $tip,
                    'title' => $title,
                    'value' => $value
                ]
            );
        });
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
     * Set order status from/to
     *
     * @param \WC_Order $order
     * @param string $fromStatus
     * @param string $toStatus
     *
     * @return void
     */
    public function setOrderStatus(\WC_Order $order, string $fromStatus, string $toStatus): void
    {
        if ($order->get_status() === $fromStatus) {
            $order->set_status($toStatus);

            $order->save();
        }
    }

    /**
     * Set custom metadata in the order
     *
     * @param \WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setCustomMetadata(\WC_Order $order, $data): void
    {
        $installments      = (float) $data['installments'];
        $installmentAmount = (float) $data['transaction_details']['installment_amount'];
        $transactionAmount = (float) $data['transaction_amount'];
        $totalPaidAmount   = (float) $data['transaction_details']['total_paid_amount'];

        $this->orderMetadata->addInstallmentsData($order, $installments);
        $this->orderMetadata->addTransactionDetailsData($order, $installmentAmount);
        $this->orderMetadata->addTransactionAmountData($order, $transactionAmount);
        $this->orderMetadata->addTotalPaidAmountData($order, $totalPaidAmount);

        $order->save();
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

        if (method_exists($order, 'update_meta_data')) {
            $this->orderMetadata->setTicketTransactionDetailsData($order, $externalResourceUrl);
            $order->save();
        } else {
            $this->orderMetadata->setTicketTransactionDetailsPost($order->get_id(), $externalResourceUrl);
        }
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

        if (method_exists($order, 'update_meta_data')) {
            $this->orderMetadata->setTransactionAmountData($order, $transactionAmount);
            $this->orderMetadata->setPixQrBase64Data($order, $qrCodeBase64);
            $this->orderMetadata->setPixQrCodeData($order, $qrCode);
            $this->orderMetadata->setPixExpirationDateData($order, $expiration);
            $this->orderMetadata->setPixExpirationDateData($order, $expiration);
            $this->orderMetadata->setPixOnData($order, 1);
            $order->save();
        } else {
            $this->orderMetadata->setTransactionAmountPost($order->get_id(), $transactionAmount);
            $this->orderMetadata->setPixQrBase64Post($order->get_id(), $qrCodeBase64);
            $this->orderMetadata->setPixQrCodePost($order->get_id(), $qrCode);
            $this->orderMetadata->setPixExpirationDatePost($order->get_id(), $expiration);
            $this->orderMetadata->setPixOnPost($order->get_id(), 1);
        }
    }
}

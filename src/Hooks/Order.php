<?php

namespace MercadoPago\Woocommerce\Hooks;

use MercadoPago\Woocommerce\Configs\MetaData;
use MercadoPago\Woocommerce\Configs\Seller;
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
     * @var MetaData
     */
    private $metaData;

    /**
     * @var StoreTranslations
     */
    private $storeTranslations;

    /**
     * @var Seller
     */
    private $seller;

    /**
     * Order constructor
     */
    public function __construct(Template $template, MetaData $metaData, StoreTranslations $storeTranslations, Seller $seller)
    {
        $this->template          = $template;
        $this->metaData          = $metaData;
        $this->storeTranslations = $storeTranslations;
        $this->seller            = $seller;
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
            $this->addMetaBox($id, $title, $name, $args, $path);
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

        $this->metaData->addInstallments($order, $installments);
        $this->metaData->addTransactionDetails($order, $installmentAmount);
        $this->metaData->addTransactionAmount($order, $transactionAmount);
        $this->metaData->addTotalPaidAmount($order, $totalPaidAmount);

        $order->save();
    }

    /**
     * Set ticket metadata in the order
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param $data
     *
     * @return void
     */
    public function setTicketMetadata(AbstractGateway $gateway, \WC_Order $order, $data): void
    {
        $externalResourceUrl = $data['transaction_details']['external_resource_url'];

        if (method_exists($order, 'update_meta_data')) {
            $this->metaData->setTicketTransactionDetails($order, $externalResourceUrl);
            $order->save();
        } else {
            $this->metaData->setTicketTransactionDetails($order->get_id(), $externalResourceUrl);
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
        $expiration        = $this->seller->getCheckoutDateExpirationPix($gateway, $defaultValue);

        if (method_exists($order, 'update_meta_data')) {
            $this->metaData->setTransactionAmount($order, $transactionAmount);
            $this->metaData->setPostPixQrBase64($order, $qrCodeBase64);
            $this->metaData->setPixQrCode($order, $qrCode);
            $this->metaData->setPixExpirationDate($order, $expiration);
            $this->metaData->setPixOn($order, 1);
            $order->save();
        } else {
            $this->metaData->setPostTransactionAmount($order->get_id(), $transactionAmount);
            $this->metaData->setPostPixQrBase64($order->get_id(), $qrCodeBase64);
            $this->metaData->setPostPixQrCode($order->get_id(), $qrCode);
            $this->metaData->setPostPixExpirationDate($order->get_id(), $expiration);
            $this->metaData->setPostPixOn($order->get_id(), 1);
        }
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
    public function addOrderNote(\WC_Order $order, string $description, $isCustomerNote = 0, $addedByUser = false)
    {
        $order->add_order_note($description, $isCustomerNote, $addedByUser);
    }

    /**
     * Get order status message
     *
     * @param string $statusDetail
     *
     * @return string
     */
    public function getOrderStatusMessage(string $statusDetail): string
    {
        $messages = $this->storeTranslations->commonMessages;

        switch ($statusDetail) {
            case 'accredited':
                return $messages['cho_accredited'];
            case 'pending_contingency':
                return $messages['cho_pending_contingency'];
            case 'pending_review_manual':
                return $messages['cho_pending_review_manual'];
            case 'cc_rejected_bad_filled_card_number':
                return $messages['cho_cc_rejected_bad_filled_card_number'];
            case 'cc_rejected_bad_filled_date':
                return $messages['cho_cc_rejected_bad_filled_date'];
            case 'cc_rejected_bad_filled_other':
                return $messages['cho_cc_rejected_bad_filled_other'];
            case 'cc_rejected_bad_filled_security_code':
                return $messages['cho_cc_rejected_bad_filled_security_code'];
            case 'cc_rejected_card_error':
                return $messages['cho_cc_rejected_card_error'];
            case 'cc_rejected_blacklist':
                return $messages['cho_cc_rejected_blacklist'];
            case 'cc_rejected_call_for_authorize':
                return $messages['cho_cc_rejected_call_for_authorize'];
            case 'cc_rejected_card_disabled':
                return $messages['cho_cc_rejected_card_disabled'];
            case 'cc_rejected_duplicated_payment':
                return $messages['cho_cc_rejected_duplicated_payment'];
            case 'cc_rejected_high_risk':
                return $messages['cho_cc_rejected_high_risk'];
            case 'cc_rejected_insufficient_amount':
                return $messages['cho_cc_rejected_insufficient_amount'];
            case 'cc_rejected_invalid_installments':
                return $messages['cho_cc_rejected_invalid_installments'];
            case 'cc_rejected_max_attempts':
                return $messages['cho_cc_rejected_max_attempts'];
            default:
                return $messages['cho_default'];
        }
    }
}

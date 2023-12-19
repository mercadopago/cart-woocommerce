<?php

namespace MercadoPago\Woocommerce\Order;

use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderStatus
{
    /**
     * @var array
     */
    private $translations;

    /**
     * @var array
     */
    private $commonMessages;

    /**
     * Order constructor
     */
    public function __construct(StoreTranslations $storeTranslations)
    {
        $this->translations   = $storeTranslations->orderStatus;
        $this->commonMessages = $storeTranslations->commonMessages;
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
     * Get order status message
     *
     * @param string $statusDetail
     *
     * @return string
     */
    public function getOrderStatusMessage(string $statusDetail): string
    {
        switch ($statusDetail) {
            case 'accredited':
                return $this->commonMessages['cho_accredited'];
            case 'pending_contingency':
                return $this->commonMessages['cho_pending_contingency'];
            case 'pending_review_manual':
                return $this->commonMessages['cho_pending_review_manual'];
            case 'cc_rejected_bad_filled_card_number':
                return $this->commonMessages['cho_cc_rejected_bad_filled_card_number'];
            case 'cc_rejected_bad_filled_date':
                return $this->commonMessages['cho_cc_rejected_bad_filled_date'];
            case 'cc_rejected_bad_filled_other':
                return $this->commonMessages['cho_cc_rejected_bad_filled_other'];
            case 'cc_rejected_bad_filled_security_code':
                return $this->commonMessages['cho_cc_rejected_bad_filled_security_code'];
            case 'cc_rejected_card_error':
                return $this->commonMessages['cho_cc_rejected_card_error'];
            case 'cc_rejected_blacklist':
                return $this->commonMessages['cho_cc_rejected_blacklist'];
            case 'cc_rejected_call_for_authorize':
                return $this->commonMessages['cho_cc_rejected_call_for_authorize'];
            case 'cc_rejected_card_disabled':
                return $this->commonMessages['cho_cc_rejected_card_disabled'];
            case 'cc_rejected_duplicated_payment':
                return $this->commonMessages['cho_cc_rejected_duplicated_payment'];
            case 'cc_rejected_high_risk':
                return $this->commonMessages['cho_cc_rejected_high_risk'];
            case 'cc_rejected_insufficient_amount':
                return $this->commonMessages['cho_cc_rejected_insufficient_amount'];
            case 'cc_rejected_invalid_installments':
                return $this->commonMessages['cho_cc_rejected_invalid_installments'];
            case 'cc_rejected_max_attempts':
                return $this->commonMessages['cho_cc_rejected_max_attempts'];
            default:
                return $this->commonMessages['cho_default'];
        }
    }

    /**
     * Process order status
     *
     * @param string $processedStatus
     * @param array $data
     * @param \WC_Order $order
     * @param string $usedGateway
     *
     * @return void
     * @throws \Exception
     */
    public function processStatus(string $processedStatus, array $data, \WC_Order $order, string $usedGateway): void
    {
        switch ($processedStatus) {
            case 'approved':
                $this->approvedFlow($data, $order, $usedGateway);
                break;
            case 'pending':
                $this->pendingFlow($data, $order, $usedGateway);
                break;
            case 'in_process':
                $this->inProcessFlow($data, $order);
                break;
            case 'rejected':
                $this->rejectedFlow($data, $order);
                break;
            case 'refunded':
                $this->refundedFlow($order);
                break;
            case 'cancelled':
                $this->cancelledFlow($data, $order);
                break;
            case 'in_mediation':
                $this->inMediationFlow($order);
                break;
            case 'charged_back':
                $this->chargedBackFlow($order);
                break;
            default:
                throw new \Exception('Process Status - Invalid Status: ' . $processedStatus);
        }
    }

    /**
     * Rule of approved payment
     *
     * @param array $data
     * @param \WC_Order $order
     * @param string $usedGateway
     *
     * @return void
     */
    private function approvedFlow(array $data, \WC_Order $order, string $usedGateway): void
    {
        if (isset($data['status_detail']) && $data['status_detail'] === 'partially_refunded') {
            return;
        }

        $status = $order->get_status();

        if ($status === 'pending' || $status === 'on-hold' || $status === 'failed') {
            $order->add_order_note('Mercado Pago: ' . $this->translations['payment_approved']);

            $payment_completed_status = apply_filters(
                'woocommerce_payment_complete_order_status',
                $order->needs_processing() ? 'processing' : 'completed',
                $order->get_id(),
                $order
            );

            if (method_exists($order, 'get_status') && $order->get_status() !== 'completed') {
                switch ($usedGateway) {
                    case 'MercadoPago\Woocommerce\Gateways\TicketGateway':
                        if (get_option('stock_reduce_mode', 'no') === 'no') {
                            $order->payment_complete();
                            if ($payment_completed_status !== 'completed') {
                                $order->update_status(self::mapMpStatusToWoocommerceStatus('approved'));
                            }
                        }
                        break;

                    default:
                        $order->payment_complete();
                        if ($payment_completed_status !== 'completed') {
                            $order->update_status(self::mapMpStatusToWoocommerceStatus('approved'));
                        }
                        break;
                }
            }
        }
    }

    /**
     * Rule of pending
     *
     * @param array $data
     * @param \WC_Order $order
     * @param string $usedGateway
     *
     * @return void
     */
    private function pendingFlow(array $data, \WC_Order $order, string $usedGateway): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(self::mapMpStatusToWoocommerceStatus('pending'));

            switch ($usedGateway) {
                case 'MercadoPago\Woocommerce\Gateways\PixGateway':
                    $notes = $order->get_customer_order_notes();

                    if (count($notes) > 1) {
                        break;
                    }

                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_pix']);
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_pix'], 1);
                    break;

                case 'MercadoPago\Woocommerce\Gateways\TicketGateway':
                    $notes = $order->get_customer_order_notes();

                    if (count($notes) > 1) {
                        break;
                    }

                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_ticket']);
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending_ticket'], 1);
                    break;

                default:
                    $order->add_order_note('Mercado Pago: ' . $this->translations['pending']);
                    break;
            }
        } else {
            $this->validateOrderNoteType($data, $order, 'pending');
        }
    }

    /**
     * Rule of In Process
     *
     * @param array $data
     * @param \WC_Order $order
     *
     * @return void
     */
    private function inProcessFlow(array $data, \WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('inprocess'),
                'Mercado Pago: ' . $this->translations['in_process']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'in_process');
        }
    }

    /**
     * Rule of Rejected
     *
     * @param array $data
     * @param \WC_Order $order
     *
     * @return void
     */
    private function rejectedFlow(array $data, \WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('rejected'),
                'Mercado Pago: ' . $this->translations['rejected']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'rejected');
        }
    }

    /**
     * Rule of Refunded
     *
     * @param \WC_Order $order
     *
     * @return void
     */
    private function refundedFlow(\WC_Order $order): void
    {
        $order->update_status(
            self::mapMpStatusToWoocommerceStatus('refunded'),
            'Mercado Pago: ' . $this->translations['refunded']
        );
    }

    /**
     * Rule of Cancelled
     *
     * @param array $data
     * @param \WC_Order $order
     *
     * @return void
     */
    private function cancelledFlow(array $data, \WC_Order $order): void
    {
        if ($this->canUpdateOrderStatus($order)) {
            $order->update_status(
                self::mapMpStatusToWoocommerceStatus('cancelled'),
                'Mercado Pago: ' . $this->translations['cancelled']
            );
        } else {
            $this->validateOrderNoteType($data, $order, 'cancelled');
        }
    }

    /**
     * Rule of In mediation
     *
     * @param \WC_Order $order
     *
     * @return void
     */
    private function inMediationFlow(\WC_Order $order): void
    {
        $order->update_status(self::mapMpStatusToWoocommerceStatus('inmediation'));
        $order->add_order_note('Mercado Pago: ' . $this->translations['in_mediation']);
    }

    /**
     * Rule of Charged back
     *
     * @param \WC_Order $order
     *
     * @return void
     */
    private function chargedBackFlow(\WC_Order $order): void
    {
        $order->update_status(self::mapMpStatusToWoocommerceStatus('chargedback'));
        $order->add_order_note('Mercado Pago: ' . $this->translations['charged_back']);
    }

    /**
     * Mercado Pago status
     *
     * @param string $mpStatus
     *
     * @return string
     */
    public static function mapMpStatusToWoocommerceStatus(string $mpStatus): string
    {
        $statusMap = array(
            'pending'     => 'pending',
            'approved'    => 'processing',
            'inprocess'   => 'on_hold',
            'inmediation' => 'on_hold',
            'rejected'    => 'failed',
            'cancelled'   => 'cancelled',
            'refunded'    => 'refunded',
            'chargedback' => 'refunded',
        );

        $status = $statusMap[ $mpStatus ];

        return str_replace('_', '-', $status);
    }

    /**
     * Can update order status?
     *
     * @param \WC_Order $order
     *
     * @return bool
     */
    protected function canUpdateOrderStatus(\WC_Order $order): bool
    {
        return method_exists($order, 'get_status') &&
            $order->get_status() !== 'completed' &&
            $order->get_status() !== 'processing';
    }

    /**
     * Validate Order Note by Type
     *
     * @param array $data
     * @param \WC_Order $order
     * @param string $status
     *
     * @return void
     */
    protected function validateOrderNoteType(array $data, \WC_Order $order, string $status): void
    {
        $paymentId = $data['id'];

        if (isset($data['ipn_type']) && $data['ipn_type'] === 'merchant_order') {
            $payments = array();

            foreach ($data['payments'] as $payment) {
                $payments[] = $payment['id'];
            }

            $paymentId = implode(',', $payments);
        }

        $order->add_order_note("Mercado Pago: {$this->translations['validate_order_1']} $paymentId {$this->translations['validate_order_1']} $status");
    }
}

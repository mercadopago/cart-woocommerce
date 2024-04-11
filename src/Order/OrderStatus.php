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
        if (isset($this->commonMessages['cho_' . $statusDetail])) {
            return $this->commonMessages['cho_' . $statusDetail];
        }
        return $this->commonMessages['cho_default'];
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
                $this->refundedFlow($data, $order);
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
                $order->payment_complete();
                if ($payment_completed_status !== 'completed') {
                    $order->update_status(self::mapMpStatusToWoocommerceStatus('approved'));
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
    private function refundedFlow(array $data, \WC_Order $order): void
    {
        if ($this->isPartialRefund($data)) {
            $refund_amount = floatval($data['total_refunded']);
            wc_create_refund(array(
                'amount'   => $refund_amount,
                'reason'   => $this->translations['refunded'],
                'order_id' => $order->get_id(),
            ));
            $order->add_order_note('Mercado Pago: ' . $this->translations['partial_refunded'] . $refund_amount);
            return;
        }

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

        $status = $statusMap[$mpStatus];

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

    /**
     * Check if refund is partial
     *
     * @param array $data
     *
     * @return bool
     */
    private function isPartialRefund(array $data): bool
    {
        return $data['transaction_amount'] !== $data['total_refunded'] && $data['total_refunded'] !== 0;
    }
}

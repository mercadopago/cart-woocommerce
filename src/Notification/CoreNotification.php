<?php

namespace MercadoPago\Woocommerce\Notification;

use Exception;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class CoreNotification extends AbstractNotification
{
    protected WoocommerceMercadoPago $mercadopago;

    private const REFUND_ORIGIN_PANEL = 'painel_woocommerce';
    private const REFUND_METRIC_SUCCESS_MP = 'mp_refund_success';
    private const REFUND_METRIC_ERROR_MP = 'mp_refund_error';
    private const REFUND_ORIGIN_MP = 'origin_mercadopago';
    private const CHECKOUT_TYPE_META_KEY = 'checkout_type';

    /**
     * Get Notification Id
     *
     * @return string
     */
    public function getNotificationId()
    {
        $body = json_decode($this->getInput());

        // For both Core and Bifrost. Core sends a complete object, but Bifrost sends only a string with the notification id
        if (is_object($body)) {
            return $body->notification_id;
        }

        return $body;
    }

    /**
     * Get input from php://input
     *
     * @codeCoverageIgnore
     */
    protected function getInput(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * Validate if ID is in the format P-12345 or M-12345 (or any number of digits)
     *
     * @return string
     */
    public function validateNotificationId($notification_id)
    {
        return preg_match('/^[PM]-\\d+$/', $notification_id) === 1;
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->store->getIntegratorId();
        $accessToken  = $this->seller->getCredentialsAccessToken();

        return new Sdk($accessToken, $platformId, $productId, $integratorId);
    }

    /**
     * Handle Notification Request
     *
     * @param $data
     *
     * @return void
     */
    public function handleReceivedNotification($data)
    {
        parent::handleReceivedNotification($data);

        $sdkNotification = $this->getSdkInstance()->getNotificationInstance();
        $notification_id = $this->getNotificationId();

        if (!$this->validateNotificationId($notification_id)) {
            $message = 'Invalid notification id';
            $this->logs->file->error($message, __CLASS__, $data);
            $this->setResponse(400, $message);
            return;
        }

        try {
            $notificationEntity = $sdkNotification->read([
                'id' => $notification_id
            ]);

            $this->handleSuccessfulRequest($notificationEntity->toArray());
        } catch (Exception $e) {
            $this->logs->file->error($e->getMessage(), __CLASS__, $data);
            $this->setResponse(500, $e->getMessage());
        }
    }

    public function handleSuccessfulRequestInternal($data, $order): void
    {
        $oldOrderStatus = $order->get_status();

        if ($this->isRefundNotification($data)) {
            $this->handleRefundNotification($order, $oldOrderStatus, $data);
            return;
        }

        $processedStatus = $this->getProcessedStatus($order, $data);
        $this->logStatusChange($oldOrderStatus, $processedStatus);
        $this->processStatus($processedStatus, $order, $data);
    }

    /**
     * Check if notification is for refund
     *
     * @param mixed $data
     *
     * @return bool
     */
    private function isRefundNotification($data): bool
    {
        return isset($data['refunds_notifying']) && is_array($data['refunds_notifying']);
    }

    /**
     * Handle refund notification
     *
     * @param WC_Order $order
     * @param string $oldOrderStatus
     * @param mixed $data
     *
     * @return void
     */
    private function handleRefundNotification(WC_Order $order, string $oldOrderStatus, $data): void
    {
        foreach ($data['refunds_notifying'] as $refund) {
            $data['current_refund'] = [];
            $refundId = $refund['id'] ?? null;

            if (!$this->isValidRefund($refund, $refundId, $data, $order)) {
                continue;
            }

            // $refundId comes straight from the webhook payload; strip CR/LF/TAB before it is
            // concatenated into any log line so a malformed id cannot forge log entries or trip
            // SIEM parsers (log injection). Used only for logging — lookups keep the raw value.
            $safeRefundId = preg_replace('/[\r\n\t]/', '', (string) $refundId);

            // Refund-id dedup: if this refund_id was already applied (persisted at refund
            // time from the panel), skip the refund flow entirely. Still sync payment-details
            // metadata (installments, last_four_digits, and the per-payment refunded amount)
            // so order meta stays current. The sync runs in authoritative mode: the per-payment
            // refunded total is recomputed from the MP payload (not incremented), which keeps it
            // idempotent under webhook redelivery and is the only place panel-origin refunds
            // update that metadata — RefundHandler persists only the refund_id. Leaving it stale
            // would let a later multi-payment refund read an over-stated remaining balance for an
            // already-refunded payment and issue a duplicate refund against it.
            if ($this->orderStatus->isRefundIdApplied($order, (string) $refundId)) {
                $this->logs->file->info('Refund already applied, skipping notification refund: ' . $safeRefundId, __CLASS__);
                if (!empty($data['payments_details'])) {
                    // Guard the metadata sync: a failure here must not abort the remaining
                    // entries of the refunds_notifying loop (which would leave the MP webhook
                    // retrying indefinitely on a 500).
                    try {
                        $this->updatePaymentDetails($order, $data, true);
                        $order->save();
                    } catch (Exception $e) {
                        $this->logs->file->error(
                            'Failed to sync payment details after dedup skip for refund '
                            . $safeRefundId . ': ' . $e->getMessage(),
                            __CLASS__
                        );
                    }
                }
                continue;
            }

            foreach ($data['payments_details'] as $payment) {
                if (isset($payment['refunds'][$refundId])) {
                    $currentRefund = $payment['refunds'][$refundId];
                    break;
                }
            }

            $data['current_refund'] = array_merge($currentRefund, $refund);

            if ($this->shouldProcessRefund($currentRefund)) {
                $processedStatus = $this->getProcessedStatus($order, $data);
                $this->logStatusChange($oldOrderStatus, $processedStatus);
                $refundApplied = $this->processStatus($processedStatus, $order, $data);

                // Only report success when the refund was actually applied. A wc_create_refund
                // failure inside refundedFlow already emitted mp_refund_error and returns false.
                if ($refundApplied) {
                    $this->sendRefundSuccessMetric($order);
                }
            } else {
                if (!empty($data['payments_details'])) {
                    $this->updatePaymentDetails($order, $data);
                    $order->save();
                }
            }
        }
    }

    /**
     * Validate refund data
     *
     * @param array $refund
     * @param string|null $refundId
     * @param mixed $data
     * @param WC_Order $order
     *
     * @return bool
     */
    private function isValidRefund($refund, $refundId, $data, WC_Order $order): bool
    {
        if (!$refundId) {
            $this->logs->file->error('Refund ID not found in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Refund ID not found in notification', $order);
            return false;
        }

        if (!isset($refund['amount']) || empty($refund['amount']) || $refund['amount'] <= 0.00) {
            $this->logs->file->error('Invalid refund amount: must be greater than 0', __CLASS__, $refund);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid refund amount: must be greater than 0', $order);
            return false;
        }

        if (!$this->isValidPaymentsDetailsStructure($data)) {
            $this->logs->file->error('Invalid payments_details structure in notification', __CLASS__, $data);
            $this->sendRefundErrorMetric('validation_failed', 'Invalid payments_details structure in notification', $order);
            return false;
        }

        return true;
    }

    /**
     * Check if refund should be processed
     *
     * @param array|null $currentRefund
     *
     * @return bool
     */
    private function shouldProcessRefund($currentRefund): bool
    {
        return $currentRefund &&
               (!isset($currentRefund['metadata']['origin']) ||
                $currentRefund['metadata']['origin'] !== self::REFUND_ORIGIN_PANEL);
    }

    /**
     * Validate payments_details structure
     *
     * @param array $data
     *
     * @return bool
     */
    private function isValidPaymentsDetailsStructure(array $data): bool
    {
        return isset($data['payments_details']) &&
               is_array($data['payments_details']) &&
               !empty($data['payments_details']) &&
               isset($data['payments_details'][0]['refunds']) &&
               is_array($data['payments_details'][0]['refunds']);
    }

    /**
     * Process status
     *
     * @param WC_Order $order
     * @param mixed $data
     *
     * @return string
     */
    public function getProcessedStatus(WC_Order $order, $data): string
    {
        $status = $data['status'];

        if (!empty($data['payer']['email'])) {
            $order->update_meta_data('Buyer email', $data['payer']['email']);
        }

        if (!empty($data['payments_details'])) {
            $this->updatePaymentDetails($order, $data);
        }

        $order->save();

        return $status;
    }

    /**
     * Update payment details metadata for order
     *
     * @param WC_Order $order
     * @param array $data
     *
     * @return void
     */
    public function updatePaymentDetails(WC_Order $order, array $data, bool $authoritativeRefund = false): void
    {
        $payment_ids = [];

        foreach ($data['payments_details'] as $payment) {
            $payment_ids[] = $payment['id'];

            $paymentData = PaymentMetadata::extractPaymentDataFromMeta(
                $order->get_meta(PaymentMetadata::getPaymentMetaKey($payment['id']))
            );

            $refundedAmount = $paymentData->refund ?? 0;

            if ($authoritativeRefund) {
                // Dedup-skip path: sync the per-payment refunded total from the MP payload. This
                // is the ONLY place the per-payment refund metadata is updated for panel-origin
                // refunds (RefundHandler persists just the refund_id, never the per-payment
                // amount, so the normal incrementing path is never reached for them).
                //
                // The refund amount does NOT live in payment['refunds'] — that structure only
                // carries id/status/notifying/metadata per refund. The amount lives in
                // refunds_notifying[]. So we cross-reference by refund id to sum the amounts of
                // this payment's refunds that are present in the current notification.
                //
                // A single notification only carries the amounts of the refunds it is notifying;
                // amounts of previously-applied refunds are not in the payload. We therefore never
                // reduce the stored total (max) — this keeps the sync idempotent under webhook
                // redelivery and prevents a partial payload from zeroing out or shrinking a value
                // that was already recorded.
                $notifyingAmountById = [];
                foreach ($data['refunds_notifying'] ?? [] as $notifyingRefund) {
                    if (isset($notifyingRefund['id'], $notifyingRefund['amount']) && is_numeric($notifyingRefund['amount'])) {
                        $notifyingAmountById[(string) $notifyingRefund['id']] = (float) $notifyingRefund['amount'];
                    }
                }

                $notifyingTotal    = 0.0;
                $hasNotifyingMatch = false;
                foreach (array_keys($payment['refunds'] ?? []) as $paymentRefundId) {
                    if (isset($notifyingAmountById[(string) $paymentRefundId])) {
                        $notifyingTotal += $notifyingAmountById[(string) $paymentRefundId];
                        $hasNotifyingMatch = true;
                    }
                }

                if ($hasNotifyingMatch) {
                    $refundedAmount = max((float) $refundedAmount, $notifyingTotal);
                }
            } elseif (isset($data['current_refund']) && isset($payment['refunds'][$data['current_refund']['id']])) {
                $refundedAmount += $data['current_refund']['amount'];
            }

            $order->update_meta_data(PaymentMetadata::getPaymentMetaKey($payment['id']), PaymentMetadata::formatPaymentMetadata($payment, $refundedAmount));

            if (Strings::contains($payment['payment_type_id'], 'card')) {
                $paymentMetaPrefix = "Mercado Pago - {$payment['id']} -";
                $order->update_meta_data("$paymentMetaPrefix installments", $payment['payment_method_info']['installments']);
                $order->update_meta_data("$paymentMetaPrefix installment_amount", $payment['payment_method_info']['installment_amount']);
                $order->update_meta_data("$paymentMetaPrefix transaction_amount", $payment['total_amount']);
                $order->update_meta_data("$paymentMetaPrefix total_paid_amount", $payment['paid_amount']);
                $order->update_meta_data("$paymentMetaPrefix card_last_four_digits", $payment['payment_method_info']['last_four_digits']);
            }
        }

        if (!isset($data['refunds_notifying'])) {
            $order->update_meta_data(PaymentMetadata::PAYMENT_IDS_META_KEY, PaymentMetadata::joinPaymentIds($payment_ids));
        }
    }

    /**
    * Log status change
    *
    * @param string $oldStatus
    * @param string $newStatus
    *
    * @return void
    */
    private function logStatusChange(string $oldStatus, string $newStatus): void
    {
        $this->logs->file->info(
            sprintf(
                'Changing order status from %s to %s',
                $oldStatus,
                $this->orderStatus->mapMpStatusToWoocommerceStatus(str_replace('_', '', $newStatus))
            ),
            __CLASS__
        );
    }

    /**
     * Get the checkout_type from the order metadata so refund metrics can be
     * segmented by product bucket (super_token, credit_card, pix…) in Datadog.
     * Returns null for legacy orders that predate the checkout_type metadata.
     *
     * @param WC_Order|null $order
     *
     * @return string|null
     */
    private function getCheckoutType(?WC_Order $order): ?string
    {
        if (!$order) {
            return null;
        }

        $checkoutType = $order->get_meta(self::CHECKOUT_TYPE_META_KEY);

        return !empty($checkoutType) ? (string) $checkoutType : null;
    }

    /**
     * Send refund success metric to Datadog
     *
     * @param WC_Order $order
     */
    private function sendRefundSuccessMetric(WC_Order $order): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_SUCCESS_MP, 'refund_success', self::REFUND_ORIGIN_MP, $this->getCheckoutType($order));
    }

    /**
     * Send refund error metric to Datadog
     *
     * @param string $errorCode
     * @param string $errorMessage
     * @param WC_Order|null $order
     */
    private function sendRefundErrorMetric(string $errorCode, string $errorMessage, ?WC_Order $order = null): void
    {
        Datadog::getInstance()->sendEvent(self::REFUND_METRIC_ERROR_MP, $errorCode, $errorMessage, $this->getCheckoutType($order));
    }
}

<?php

namespace MercadoPago\Woocommerce\Refund;

use Exception;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\RefundStatusCodes;
use MercadoPago\Woocommerce\Exceptions\RefundException;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

if (!defined('ABSPATH')) {
    exit;
}

class RefundHandler
{
    private const REFUND_ENDPOINT = '/ppcore/prod/transaction/v1/payments/%s/refund';
    private const LOG_SOURCE = 'MercadoPago_RefundHandler';

    private const REFUND_ORIGIN = 'painel_woocommerce';
    private const PAYMENT_ID_META_KEY = '_Mercado_Pago_Payment_IDs';
    private const CHECKOUT_TYPE_META_KEY = 'checkout_type';
    private const REFUND_METRIC_SUCCESS_WOO = 'woo_refund_success';
    private const REFUND_METRIC_ERROR_WOO   = 'woo_refund_error';
    private const REFUND_METRIC_ERROR_MP    = 'mp_refund_error';
    private const REFUND_METRIC_LATENCY     = 'woo_refund_latency';
    private const REFUND_ORIGIN_WOO         = 'origin_woocommerce';

    private Requester $requester;
    private $order;
    private WoocommerceMercadoPago $mercadopago;
    private Datadog $datadog;
    private RefundStatusCodes $refundStatusCodes;

    public function __construct(Requester $requester, $order, WoocommerceMercadoPago $mercadopago)
    {
        $this->requester = $requester;
        $this->order = $order;
        $this->mercadopago = $mercadopago;
        $this->datadog = Datadog::getInstance();
        $this->refundStatusCodes = new RefundStatusCodes($mercadopago->adminTranslations);
    }

    /**
     * Process refund request
     *
     * @param float $amount
     * @param string $reason
     * @return array
     * @throws RefundException
     */
    public function processRefund(float $amount, string $reason = ''): array
    {
        $start = microtime(true);

        try {
            if (!\current_user_can('manage_woocommerce')) {
                throw new Exception(RefundException::TYPE_NO_PERMISSION);
            }

            $currencyRatio = 1.0;
            $currencyRatioMeta = $this->order->get_meta($this->mercadopago->orderMetadata::CURRENCY_RATIO);
            if (!empty($currencyRatioMeta)) {
                $currencyRatio = (float) $currencyRatioMeta;
                $amount = $amount * $currencyRatio;
            }

            $paymentId = $this->getPaymentId();
            $paymentIds = explode(', ', $paymentId);

            if (count($paymentIds) > 1) {
                $amountToRefund = $amount;
                $amountRemainingInPayment = 0;
                $response = [];
                // Order-wide already-refunded before this refund, in the same currency as
                // $amount (both scaled by $currencyRatio). Computed once; the same value is
                // passed to every executeRefund of this call. Because get_total_refunded()
                // advances between distinct refund actions (updated before process_refund),
                // the "before" term advances synchronously across actions, while the distinct
                // $refundingPaymentId per iteration keeps keys distinct within this call.
                $alreadyRefundedBefore = max(0.0, (float) $this->order->get_total_refunded() * $currencyRatio - $amount);
                foreach ($paymentIds as $refundingPaymentId) {
                    $field = $this->order->get_meta(PaymentMetadata::getPaymentMetaKey($refundingPaymentId));

                    $paymentData = PaymentMetadata::extractPaymentDataFromMeta($field);

                    $paidAmount = $paymentData->paid ?? 0;
                    $refundedAmount = $paymentData->refund ?? 0;
                    $amountRemainingInPayment = max(0, $paidAmount - $refundedAmount);

                    if ($amountRemainingInPayment <= 0) {
                        continue;
                    }

                    if ($amountToRefund > $amountRemainingInPayment) {
                        $amountToRefundInPayment = $amountRemainingInPayment;
                        $amountToRefund = $amountToRefund - $amountRemainingInPayment;
                    } else {
                        $amountToRefundInPayment = $amountToRefund;
                        $amountToRefund = 0;
                    }

                    // Order-wide "already refunded before this refund", same for each
                    // iteration; the distinct $refundingPaymentId keeps per-iteration keys distinct.
                    $result = $this->executeRefund($refundingPaymentId, $amountToRefundInPayment, $reason, $alreadyRefundedBefore);
                    $response[] = $result;

                    if ($amountToRefund === 0) {
                        break;
                    }
                }
                $this->sendRefundLatencyMetric($start);
                return $response;
            } else {
                // Order-wide already-refunded before this refund, in the same currency as $amount.
                $alreadyRefundedBefore = max(0.0, (float) $this->order->get_total_refunded() * $currencyRatio - $amount);
                $result = $this->executeRefund($paymentId, $amount, $reason, $alreadyRefundedBefore);
                $this->sendRefundLatencyMetric($start);
                return $result;
            }
        } catch (RefundException $e) {
            $this->sendRefundErrorMetric(self::REFUND_METRIC_ERROR_MP, (string) $e->getCode(), $e->getMessage());
            $this->mercadopago->logs->file->error('Refund processing failed - ' . $e->getMessage(), self::LOG_SOURCE, $e->getLoggingContext());

            throw $e;
        } catch (Exception $e) {
            $this->sendRefundErrorMetric(self::REFUND_METRIC_ERROR_WOO, (string) $e->getCode(), $e->getMessage());
            $this->mercadopago->logs->file->error('Unexpected refund error: ' . $e->getMessage(), self::LOG_SOURCE);

            throw $e;
        }
    }

    /**
     * Execute refund process for a single payment
     *
     * @param string $paymentId
     * @param float $amount
     * @param string $reason
     * @param float $alreadyRefundedBefore
     *
     * @return array
     * @throws Exception
     */
    private function executeRefund(string $paymentId, float $amount, string $reason, float $alreadyRefundedBefore): array
    {
        $payload = $this->buildRefundPayload($amount, $reason);
        $headers = $this->buildRequestHeaders($this->buildIdempotencyKey($paymentId, $amount, $alreadyRefundedBefore));

        $refundResponse = $this->executeRefundRequest($paymentId, $headers, $payload);
        $result = $this->processRefundResponse($refundResponse, $paymentId);

        $this->mercadopago->logs->file->info('Refund processed successfully', self::LOG_SOURCE, [
            'order_id' => $this->order->get_id(),
            'result' => $result
        ]);

        // Persist the refund_id at refund time so that a later notification confirming the
        // same refund is recognised and skipped (deduplication).
        //
        // Why refund_id is NOT used for Super Token:
        // The Asgard Transaction endpoint (/ppcore/prod/transaction/v1/payments/{id}/refund)
        // returns HTTP 200 with an empty body for Super Token — the refund_id is not included
        // in the response (documented behaviour; confirmed in testing). Because the id is
        // unavailable at refund time, _mp_applied_refund_ids is left empty for ST refunds.
        //
        // Deduplication for ST therefore relies on the value-based barrier in
        // OrderStatus::refundedFlow (refundAlreadyProcessed): if totalRefundedMP <= totalRefundedWC
        // the notification is silently skipped. This barrier is idempotent, already runs in
        // production for all payment methods, and was validated end-to-end for ST (PSW-4308).
        //
        // Use a strict null check (not empty()): a hypothetical refund_id of 0/"0" is still a
        // valid id to persist, whereas empty() would drop it and fall back to the value barrier.
        $refundId = $result['data']['id'] ?? null;
        if ($refundId !== null) {
            $this->mercadopago->orderMetadata->addAppliedRefundId($this->order, (string) $refundId);
        } else {
            $this->mercadopago->logs->file->info(
                'Refund response has no id (Super Token / empty body); relying on value-based dedup',
                self::LOG_SOURCE
            );
        }

        $this->sendRefundSuccessMetric();
        return $result;
    }

    /**
     * Build refund payload
     *
     * @param float $amount
     * @param string $reason
     *
     * @return array
     */
    private function buildRefundPayload(float $amount, string $reason): array
    {
        $payload = [
            'amount' => Numbers::format($amount),
            'metadata' => [
                'origin' => self::REFUND_ORIGIN
            ]
        ];

        if (!empty($reason)) {
            $payload['metadata']['reason'] = \sanitize_text_field($reason);
        }

        return $payload;
    }

    /**
     * Build a deterministic idempotency key for the refund request.
     *
     * Canonical string: sha256(payment_id|amount|order_id|already_refunded_before),
     * joined by the pipe separator. Amounts are canonicalized to a fixed 2-decimal
     * string (locale-independent) to keep the key stable across environments.
     *
     * `already_refunded_before` is the amount already refunded BEFORE this refund,
     * expressed in the same currency as `amount`. It is computed **order-wide in both
     * branches** (`get_total_refunded() * currency_ratio - amount`) — once, then reused
     * across the multi-payment loop; only `payment_id`/`amount` vary per iteration, which
     * keeps each payment's key distinct. The caller applies the ratio to both terms so
     * they share the same unit. The same logical refund submitted twice yields the same
     * key (MP-side dedup); distinct sequential partial refunds yield different keys.
     *
     * @param string $paymentId
     * @param float $amount
     * @param float $alreadyRefundedBefore
     *
     * @return string
     */
    private function buildIdempotencyKey(string $paymentId, float $amount, float $alreadyRefundedBefore): string
    {
        // Fixed 2-decimal canonicalization: locale-independent and deterministic for the same
        // float across environments (IEEE-754). Safe for every MP LATAM currency — all are <= 2
        // decimals (BRL/ARS/MXN/UYU/PEN); CLP/COP (0 decimals) round to ".00" harmlessly. The
        // rounding also absorbs float residue from "total * ratio - amount". If a currency with
        // >2 decimals is ever supported, revisit this precision (the key is a hash input, not a
        // currency-formatted value, so it only needs to stay internally consistent).
        $canonicalAmount = number_format($amount, 2, '.', '');
        $canonicalBefore = number_format($alreadyRefundedBefore, 2, '.', '');

        $refundKey = $this->order->get_id() . '|' . $canonicalBefore;

        return hash('sha256', $paymentId . '|' . $canonicalAmount . '|' . $refundKey);
    }

    /**
     * Build request headers
     *
     * @param string $idempotencyKey
     *
     * @return array
     * @throws Exception
     */
    private function buildRequestHeaders(string $idempotencyKey): array
    {
        $accessToken = $this->mercadopago->sellerConfig->getCredentialsAccessToken();
        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'x-platform-id' => MP_PLATFORM_ID,
            'x-product-id' => Device::getDeviceProductId(),
            'x-idempotency-key' => $idempotencyKey
        ];
    }

    /**
     * Execute refund request
     *
     * @param string $paymentId
     * @param array $headers
     * @param array $payload
     *
     * @return Response
     * @throws Exception
     */
    private function executeRefundRequest(string $paymentId, array $headers, array $payload): Response
    {
        $endpoint = sprintf(self::REFUND_ENDPOINT, $paymentId);

        return $this->requester->post($endpoint, $headers, $payload);
    }

    /**
     * Process refund response
     *
     * @param Response $response
     * @param string $paymentId
     *
     * @return array
     * @throws RefundException
     */
    private function processRefundResponse(Response $response, string $paymentId): array
    {
        $statusCode = $response->getStatus();
        $rawData = $response->getData();

        $data = [];
        if ($rawData !== null) {
            $data = is_array($rawData) ? $rawData : (array) $rawData;
        }

        if ($this->refundStatusCodes->isSuccessful($statusCode)) {
            return ['status' => 'approved', 'data' => $data];
        }

        throw $this->refundStatusCodes->createException($statusCode, $data, $paymentId, $this->order->get_id());
    }

    /**
     * Get payment ID from order
     *
     * @return string
     * @throws RefundException
     */
    private function getPaymentId(): string
    {
        $paymentId = $this->order->get_meta(self::PAYMENT_ID_META_KEY);

        if (empty($paymentId)) {
            throw $this->refundStatusCodes->createException(
                RefundStatusCodes::NOT_FOUND,
                ['message' => 'Payment ID not found in order metadata'],
                null,
                $this->order->get_id(),
                ['meta_key_searched' => self::PAYMENT_ID_META_KEY]
            );
        }

        return $paymentId;
    }

    /**
     * Send refund latency metric to Datadog. Fires on the happy path only (same
     * pattern as CheckoutValidation::sendLatencyMetric). The latency value doubles
     * as a Rate signal: its count per time window equals the number of successful
     * refund requests processed, without requiring a separate counter metric.
     *
     * @param float $start microtime(true) captured at the beginning of processRefund
     */
    private function sendRefundLatencyMetric(float $start): void
    {
        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        $this->datadog->sendEvent(self::REFUND_METRIC_LATENCY, $latencyMs, null, $this->getCheckoutType());
    }

    /**
     * Get the checkout_type from the order metadata so refund metrics can be
     * segmented by product bucket (super_token, credit_card, pix…) in Datadog.
     * Returns null for legacy orders that predate the checkout_type metadata.
     *
     * @return string|null
     */
    private function getCheckoutType(): ?string
    {
        if (!$this->order) {
            return null;
        }

        $checkoutType = $this->order->get_meta(self::CHECKOUT_TYPE_META_KEY);

        return !empty($checkoutType) ? (string) $checkoutType : null;
    }

    /**
     * Send refund success metric to Datadog
     */
    private function sendRefundSuccessMetric(): void
    {
        $this->datadog->sendEvent(self::REFUND_METRIC_SUCCESS_WOO, 'refund_success', self::REFUND_ORIGIN_WOO, $this->getCheckoutType());
    }

    /**
     * Send refund error metric to Datadog. The metric name distinguishes the
     * error origin: mp_refund_error for MP API failures (RefundException),
     * woo_refund_error for unexpected WooCommerce-side failures.
     *
     * @param string $metricName
     * @param string $errorCode
     * @param string $errorMessage
     */
    private function sendRefundErrorMetric(string $metricName, string $errorCode, string $errorMessage): void
    {
        $this->datadog->sendEvent($metricName, $errorCode, $errorMessage, $this->getCheckoutType());
    }
}

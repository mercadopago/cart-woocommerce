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
    private const REFUND_METRIC_SUCCESS_WOO = 'woo_refund_success';
    private const REFUND_METRIC_ERROR_WOO = 'woo_refund_error';
    private const REFUND_ORIGIN_WOO = 'origin_woocommerce';
    private const CHECKOUT_TYPE = 'checkout_type';
    private const SUPER_TOKEN = 'super_token';
    private const CURRENCY_RATIO = '_currency_ratio';

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
        if (!\current_user_can('manage_woocommerce')) {
            throw new Exception(RefundException::TYPE_NO_PERMISSION);
        }

        $checkoutType = $this->order->get_meta(self::CHECKOUT_TYPE);
        if (!empty($checkoutType) && $checkoutType === self::SUPER_TOKEN) {
            throw new Exception(RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED);
        }

        if (!empty($this->order->get_meta($this->mercadopago->orderMetadata::CURRENCY_RATIO))) {
            $amount = $amount * (float) $this->order->get_meta($this->mercadopago->orderMetadata::CURRENCY_RATIO);
        }

        try {
            $paymentId = $this->getPaymentId();
            $paymentIds = explode(', ', $paymentId);

            if (count($paymentIds) > 1) {
                $amountToRefund = $amount;
                $amountRemainingInPayment = 0;
                $response = [];
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

                    $result = $this->executeRefund($refundingPaymentId, $amountToRefundInPayment, $reason);
                    $response[] = $result;

                    if ($amountToRefund === 0) {
                        break;
                    }
                }
                return $response;
            } else {
                return $this->executeRefund($paymentId, $amount, $reason);
            }
        } catch (RefundException $e) {
            $this->sendRefundErrorMetric($e->getCode(), $e->getMessage());
            $this->mercadopago->logs->file->error('Refund processing failed - ' . $e->getMessage(), self::LOG_SOURCE, $e->getLoggingContext());

            throw $e;
        } catch (Exception $e) {
            $this->sendRefundErrorMetric($e->getCode(), $e->getMessage());
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
     *
     * @return array
     * @throws Exception
     */
    private function executeRefund(string $paymentId, float $amount, string $reason): array
    {
        $payload = $this->buildRefundPayload($amount, $reason);
        $headers = $this->buildRequestHeaders();

        $refundResponse = $this->executeRefundRequest($paymentId, $headers, $payload);
        $result = $this->processRefundResponse($refundResponse, $paymentId);

        $this->mercadopago->logs->file->info('Refund processed successfully', self::LOG_SOURCE, [
            'order_id' => $this->order->get_id(),
            'result' => $result
        ]);

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
     * Build request headers
     *
     * @return array
     * @throws Exception
     */
    private function buildRequestHeaders(): array
    {
        $accessToken = $this->mercadopago->sellerConfig->getCredentialsAccessToken();

        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'x-platform-id' => MP_PLATFORM_ID,
            'x-product-id' => Device::getDeviceProductId()
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
     * Send refund success metric to Datadog
     */
    private function sendRefundSuccessMetric(): void
    {
        $this->datadog->sendEvent(self::REFUND_METRIC_SUCCESS_WOO, 'refund_success', self::REFUND_ORIGIN_WOO);
    }

    /**
     * Send refund error metric to Datadog
     *
     * @param string $errorCode
     * @param string $errorMessage
     */
    private function sendRefundErrorMetric(string $errorCode, string $errorMessage): void
    {
        $this->datadog->sendEvent(self::REFUND_METRIC_ERROR_WOO, $errorCode, $errorMessage);
    }
}

<?php

namespace MercadoPago\Woocommerce\SuperToken;

use MercadoPago\PP\Sdk\Exceptions\ApiException;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenMetadataWriter;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenTransactionFactory;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class SuperTokenPaymentProcessor
{
    private const PAYMENT_METHOD_NAME = 'woo-mercado-pago-super-token';

    private SuperTokenValidator $validator;

    private SuperTokenTransactionFactory $transactionFactory;

    private SuperTokenMetadataWriter $metadataWriter;

    public function __construct(
        SuperTokenValidator $validator,
        SuperTokenTransactionFactory $transactionFactory,
        SuperTokenMetadataWriter $metadataWriter
    ) {
        $this->validator = $validator;
        $this->transactionFactory = $transactionFactory;
        $this->metadataWriter = $metadataWriter;
    }

    public function process(AbstractGateway $gateway, WC_Order $order, array $checkout): array
    {
        $gateway->setPaymentMethodName(self::PAYMENT_METHOD_NAME);
        $gateway->mercadopago->logs->file->info(
            'Preparing to get response of custom super token checkout',
            $gateway::LOG_SOURCE
        );

        $superTokenCheckout = new SuperTokenCheckout($checkout);

        $missingFields = $this->validator->getMissingFields($superTokenCheckout);
        if (!empty($missingFields)) {
            throw new InvalidCheckoutDataException(
                'exception : Unable to process payment on ' . __METHOD__,
                0,
                null,
                [
                    'missing_fields'  => implode(',', $missingFields),
                    'payment_type_id' => $superTokenCheckout->getPaymentTypeId() ?? 'unknown',
                ]
            );
        }

        $gateway->transaction = $this->transactionFactory->create($gateway, $order, $superTokenCheckout->toArray());
        $flowId = $gateway->transaction->getCheckoutSessionData()['_mp_flow_id'] ?? 'Unknown';

        $this->validator->reportTelemetry($superTokenCheckout, $gateway, $flowId);

        try {
            $response = $gateway->transaction->createPayment();
        } catch (ApiException $e) {
            $errorCode = $gateway->mercadopago->helpers->errorMessages->findCodeInOriginalMessage($e->getOriginalMessage())
                ?? $e->getErrorCode()
                ?? $e->getMessage();

            return $gateway->processReturnFail(
                $e,
                $errorCode,
                $gateway::LOG_SOURCE,
                [],
                true
            );
        }

        $this->metadataWriter->write($order, $response, $gateway->transaction->getInternalMetadata());

        // Latent coupling — TECH-3, deferred by ADR-002. handleResponseStatus() lives only on
        // CustomGateway, not on AbstractGateway. Safe today because CustomGateway is the sole caller.
        // Reusing this processor from another gateway (e.g. the future Pix flow, PSW-4213) REQUIRES
        // first extracting a GatewayResponsePort; otherwise this call fatals with "undefined method".
        // @phpstan-ignore-next-line
        return $gateway->handleResponseStatus($order, $response);
    }
}

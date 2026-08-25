<?php

namespace MercadoPago\Woocommerce\SuperToken;

use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!defined('ABSPATH')) {
    exit;
}

class SuperTokenValidator
{
    public function getMissingFields(SuperTokenCheckout $checkout): array
    {
        $requiredValues = [
            'authorized_pseudotoken' => $checkout->getAuthorizedPseudotoken(),
            'amount'                 => $checkout->getAmount(),
            'payment_method_id'      => $checkout->getPaymentMethodId(),
            'payment_type_id'        => $checkout->getPaymentTypeId(),
        ];

        $missingFields = [];
        foreach ($requiredValues as $field => $value) {
            // Presence check, not empty(): a fully coupon-covered order carries amount '0', which is
            // a valid value empty() would misreport as a missing field.
            if ($value === null || $value === '') {
                $missingFields[] = $field;
            }
        }

        $installments = $checkout->getInstallments();
        if ($checkout->isCreditCard() && (empty($installments) || $installments <= 0)) {
            $missingFields[] = 'installments_required_for_credit';
        }

        return $missingFields;
    }

    public function reportTelemetry(SuperTokenCheckout $checkout, AbstractGateway $gateway, string $flowId): void
    {
        if ($checkout->getSuperTokenValidation() === 'false') {
            $gateway->datadog->sendEvent(
                'super_token_validation_failed',
                'true',
                'INCOMPLETE_SUPER_TOKEN_VALIDATION',
                'super_token',
                $this->buildEventContext($gateway, $flowId)
            );
        }
    }

    private function buildEventContext(AbstractGateway $gateway, string $flowId): array
    {
        return [
            'site_id'         => $gateway->mercadopago->sellerConfig->getSiteId(),
            'environment'     => $gateway->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
            'cust_id'         => $gateway->mercadopago->sellerConfig->getCustIdFromAT(),
            'sdk_instance_id' => $flowId,
        ];
    }
}

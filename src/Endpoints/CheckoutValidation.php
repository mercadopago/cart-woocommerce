<?php

namespace MercadoPago\Woocommerce\Endpoints;

use Throwable;
use MercadoPago\Woocommerce\Helpers\CheckoutValidator;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

if (!defined('ABSPATH')) {
    exit;
}

class CheckoutValidation
{
    public const VALIDATION_ENDPOINT = 'mp_validate_checkout';

    private const WC_CHECKOUT_NONCE_ACTION = 'woocommerce-process_checkout';

    private const WC_CHECKOUT_NONCE_FIELD = 'woocommerce-process-checkout-nonce';

    private const METRIC_ERROR = 'MP_CUSTOM_CHECKOUT_AJAX_VALIDATION_ERROR';

    private const METRIC_LATENCY = 'MP_CUSTOM_CHECKOUT_AJAX_VALIDATION_LATENCY';

    public Endpoints $endpoints;

    public Nonce $nonce;

    /**
     * @param Endpoints $endpoints
     * @param Nonce $nonce
     */
    public function __construct(
        Endpoints $endpoints,
        Nonce $nonce
    ) {
        $this->endpoints = $endpoints;
        $this->nonce     = $nonce;

        $this->registerEndpoints();
    }

    /**
     * @return void
     */
    public function registerEndpoints(): void
    {
        $this->endpoints->registerWCAjaxEndpoint(self::VALIDATION_ENDPOINT, [$this, 'mercadopagoValidateCheckout']);
    }

    /**
     * Validate the posted checkout form server-side, before Super Token tokenization.
     * Returns a canonical schema: { valid: bool, errors: [{ field, code, message }] }.
     *
     * @return void
     */
    public function mercadopagoValidateCheckout(): void
    {
        $start = microtime(true);

        try {
            $this->nonce->validateNonce(
                self::WC_CHECKOUT_NONCE_ACTION,
                Form::sanitizedPostData(self::WC_CHECKOUT_NONCE_FIELD)
            );

            $errors    = (new CheckoutValidator())->validate();
            $errorList = $this->mapErrors($errors);

            // Latency is the only signal worth metering on the happy path; the valid/invalid
            // verdict itself is an expected outcome and is not metered (only unexpected
            // failures are — see the catch block).
            $this->sendLatencyMetric($start);

            wp_send_json_success([
                'valid'  => empty($errorList),
                'errors' => $errorList,
            ]);
        } catch (Throwable $e) {
            // Unexpected failure of this best-effort pre-check. Report it with enough context
            // to diagnose what failed and why (exception type + message). The client must not
            // be blocked by it — the real WooCommerce submit still validates (defense in depth).
            Datadog::getInstance()->sendEvent(self::METRIC_ERROR, \get_class($e), $e->getMessage());

            wp_send_json_error([
                'error' => 'unexpected_error',
            ]);
        }
    }

    /**
     * Map a WP_Error into the canonical error list. Never includes user-submitted
     * values (RN-4) — only field names, error codes and WooCommerce messages.
     *
     * @param \WP_Error $errors
     *
     * @return array
     */
    private function mapErrors(\WP_Error $errors): array
    {
        $errorList = [];

        foreach ($errors->get_error_codes() as $code) {
            $field     = $code;
            $errorData = $errors->get_error_data($code);
            if (is_array($errorData) && isset($errorData['id'])) {
                $field = $errorData['id'];
            }

            foreach ($errors->get_error_messages($code) as $message) {
                $errorList[] = [
                    'field'   => $field,
                    'code'    => $code,
                    'message' => wp_strip_all_tags($message),
                ];
            }
        }

        return $errorList;
    }

    /**
     * @param float $start
     *
     * @return void
     */
    private function sendLatencyMetric(float $start): void
    {
        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        Datadog::getInstance()->sendEvent(self::METRIC_LATENCY, $latencyMs);
    }
}

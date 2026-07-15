<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AutomaticPaymentsClient
 *
 * Dedicated HTTP client for the Mercado Pago Automatic Payments v2 API.
 * Centralizes header injection and deterministic idempotency-key wiring
 * (via {@see SubscriptionsHelper}).
 *
 * Wraps {@see Requester} so every request is instrumented in Datadog through
 * `mp_api_error` automatically — no extra telemetry code needed here.
 *
 * @package MercadoPago\Woocommerce\Helpers
 */
class AutomaticPaymentsClient
{
    /**
     * Base path for the AP v2 API on api.mercadopago.com.
     * In test mode a /homol prefix is prepended (see resolveBasePath).
     */
    private const BASE_PATH = '/plugins-platforms/automatic-payments/v2';

    /**
     * PHP constant that, when defined, overrides the resolved base path.
     * Useful for CI environments or local development pointing at a custom endpoint.
     */
    public const BASE_PATH_CONSTANT = 'MP_AUTOMATIC_PAYMENTS_BASE_PATH';

    /**
     * WC Logger channel used by every AP v2 request/response audit entry.
     * Sellers can filter `mercadopago-subscriptions` in WC > Status > Logs.
     */
    public const LOG_SOURCE = 'mercadopago-subscriptions';

    /**
     * @var Requester
     */
    private $requester;

    /**
     * @var SubscriptionsHelper
     */
    private $subscriptionsHelper;

    /**
     * @var Logs
     */
    private $logs;

    /**
     * @var string Resolved base path for all AP v2 requests.
     */
    private $basePath;

    /**
     * AutomaticPaymentsClient constructor.
     *
     * @param Requester           $requester           Shared HTTP wrapper (base URL: api.mercadopago.com).
     * @param SubscriptionsHelper $subscriptionsHelper Provides idempotency keys and error mapping.
     * @param Logs                $logs                Structured logger; entries land under {@see LOG_SOURCE}.
     * @param bool                $isTestMode          When true, prepends /homol to route requests to the homologation environment.
     */
    public function __construct(Requester $requester, SubscriptionsHelper $subscriptionsHelper, Logs $logs, bool $isTestMode = false)
    {
        $this->requester           = $requester;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->logs                = $logs;
        $this->basePath            = self::resolveBasePath($isTestMode);
    }

    /**
     * Resolves the effective base path for AP v2 requests.
     *
     * Order of precedence:
     *   1. `MP_AUTOMATIC_PAYMENTS_BASE_PATH` constant (CI/local override)
     *   2. Test mode  → /homol prefix
     *   3. Production → no prefix
     */
    private static function resolveBasePath(bool $isTestMode): string
    {
        if (defined(self::BASE_PATH_CONSTANT)) {
            $override = constant(self::BASE_PATH_CONSTANT);
            if (is_string($override) && $override !== '') {
                return rtrim($override, '/');
            }
        }

        $envPrefix = $isTestMode ? '/homol' : '';
        return $envPrefix . self::BASE_PATH;
    }

    /**
     * Executes a Customer Initiated Transaction — first payment of a subscription (§4.2).
     *
     * Wires a deterministic idempotency key (DD-7) so network retries reuse the
     * Core P&P cached response and never produce a double charge.
     *
     * @param string    $accessToken Pre-approval bearer token configured in admin (§3.5).
     * @param \WC_Order $order       WC order being paid — used to derive the idempotency seed.
     * @param array     $payload     Full AP v2 body: token, payer, transaction, subscription, device.
     *
     * @return Response
     *
     * @throws \RuntimeException When subscription.id is absent from the response (orphan payment, AC-2).
     * @throws \Exception        Propagated from {@see Requester} on network or HTTP >= 400 errors.
     */
    public function cit(string $accessToken, \WC_Order $order, array $payload): Response
    {
        $idemKey = $this->subscriptionsHelper->generateIdempotencyKey(
            $this->subscriptionsHelper->buildCitSeed($order, (string) ($payload['token'] ?? ''))
        );
        $headers   = $this->buildHeaders($accessToken, $idemKey);
        // request_id ties the three log lines (sending/approved|orphan) for a single CIT call.
        $requestId = $idemKey;
        $startedAt = microtime(true);

        $this->log('info', 'op=cit status=sending', [
            'request_id'         => $requestId,
            'external_reference' => $order->get_id(),
            'idempotency_key'    => $idemKey,
        ]);

        $response    = $this->requester->post($this->basePath . '/intents/cit', $headers, $payload);
        $data        = (array) ($response->getData() ?? []);
        $httpStatus  = $response->getStatus();
        $durationMs  = (int) round((microtime(true) - $startedAt) * 1000);

        // For 4xx/5xx responses: return immediately so the caller (handler) can map
        // the API error payload. Orphan detection and approved-log do not apply here.
        if ($httpStatus >= 400) {
            return $response;
        }

        // Orphan detection: a 2xx without subscription.id means a charge was created
        // without a registered subscription — unrecoverable orphan state.
        // Logged as 'error' (WC Logger does not have a 'critical' level; 'error' is the highest).
        if (empty($data['subscription']['id'])) {
            $this->log('error', 'op=cit status=orphan_detected', [
                'request_id'  => $requestId,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'payment_id'  => $data['payment']['id'] ?? null,
            ]);
            throw new \RuntimeException(
                $this->subscriptionsHelper->mapApiErrorToUserMessage($httpStatus, 'OrphanPayment')
            );
        }

        $this->log('info', 'op=cit status=approved', [
            'request_id'      => $requestId,
            'http_status'     => $httpStatus,
            'duration_ms'     => $durationMs,
            'payment_id'      => $data['payment']['id'] ?? null,
            'subscription_id' => $data['subscription']['id'],
        ]);

        return $response;
    }

    /**
     * Builds the headers required by every AP v2 request.
     *
     * `X-Idempotency-Key` is only set when an explicit value is supplied —
     * DELETE calls (Flow 3b, Flow 4) omit it because they are naturally idempotent.
     *
     * Emits a `warning` when `$accessToken` is empty or `MP_PLATFORM_ID` is not
     * defined; both conditions produce a silent bad request that is hard to diagnose.
     *
     * @param string      $accessToken    Non-empty pre-approval bearer token.
     * @param string|null $idempotencyKey Deterministic UUID v4 from {@see SubscriptionsHelper::generateIdempotencyKey()}.
     *
     * @return array<string, string>
     */
    public function buildHeaders(string $accessToken, ?string $idempotencyKey = null): array
    {
        if ($accessToken === '') {
            $this->log('warning', 'buildHeaders: empty access_token — all AP v2 requests will fail with 401');
        }

        $platformId = $this->getPlatformId();
        if ($platformId === '') {
            $this->log('warning', 'buildHeaders: MP_PLATFORM_ID is not defined — X-Platform-Id header will be empty');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-Platform-Id' => $platformId,
            'X-Product-Id'  => Device::getDeviceProductId(),
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * Returns the platform ID from the MP_PLATFORM_ID constant.
     * Extracted to allow testing the empty-platform-id warning branch.
     */
    protected function getPlatformId(): string
    {
        return defined('MP_PLATFORM_ID') ? (string) constant('MP_PLATFORM_ID') : '';
    }

    /**
     * Endpoint 3a — `POST /v2/subscriptions/{id}/payment-methods`.
     *
     * Adiciona um novo PM e força `set_as_default: true` (DD-15: add-then-remove).
     * Após o sucesso, extrai o `card_id` do PM marcado como `default === true`
     * cujo valor seja diferente de `$currentCardId` (precaução para o caso
     * de o token novo gerar o mesmo cartão já cadastrado — AC-1).
     *
     * @param string      $subscriptionId Valor de `_mp_subscription_id`.
     * @param string      $token          Token de cartão gerado pelo MP.js.
     * @param string      $accessToken    Bearer Pre-approval (DD-9).
     * @param string      $idempotencyKey UUID determinístico do {@see SubscriptionsHelper}.
     * @param string|null $currentCardId  Valor atual de `_mp_active_card_id`, se houver.
     *
     * @return array{status:int, data:array, new_card_id: string|null}
     */
    public function addPaymentMethod(
        string $subscriptionId,
        string $token,
        string $accessToken,
        string $idempotencyKey,
        ?string $currentCardId = null
    ): array {
        $uri     = $this->basePath . '/subscriptions/' . rawurlencode($subscriptionId) . '/payment-methods';
        $headers = $this->buildHeaders($accessToken, $idempotencyKey);
        $body    = [
            'token'          => $token,
            'set_as_default' => true,
        ];

        $startedAt  = microtime(true);
        $response   = $this->requester->post($uri, $headers, $body);
        $status     = $response->getStatus();
        $data       = $this->responseToArray($response);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $newCardId = $this->extractDefaultCardId($data, $currentCardId);

        $this->log($status >= 400 ? 'warning' : 'info', 'op=add-payment-method status=' . $status, [
            'request_id'     => $idempotencyKey,
            'http_status'    => $status,
            'duration_ms'    => $durationMs,
            'subscription_id' => $subscriptionId,
            'new_card_id'    => $newCardId,
        ]);

        return [
            'status'      => $status,
            'data'        => $data,
            'new_card_id' => $newCardId,
        ];
    }

    /**
     * Endpoint 3b — `DELETE /v2/subscriptions/{id}/payment-methods/{card_id}`.
     *
     * Remove o cartão do profile. O card_id é enviado na URL — sem body
     * (semântica REST correta para DELETE). Não envia `X-Idempotency-Key`
     * — DELETE é naturalmente idempotente (AC-3).
     *
     * Propaga erros 422 do contrato AP v2 com flag distintiva (AC-2):
     *   - `LastPaymentMethod`  → `error = 'last_payment_method'`
     *   - `CannotRemoveDefault` → `error = 'cannot_remove_default'`
     *
     * @param string $subscriptionId Valor de `_mp_subscription_id`.
     * @param string $cardId         ID do cartão a remover.
     * @param string $accessToken    Bearer Pre-approval.
     *
     * @return array{status:int, data:array, error: string|null}
     *               `status` é o HTTP status da chamada AP v2, ou `0` quando
     *               `card_id` é vazio (early return, nenhuma chamada HTTP feita).
     */
    public function removePaymentMethod(
        string $subscriptionId,
        string $cardId,
        string $accessToken
    ): array {
        if ($cardId === '') {
            $this->log('warning', 'op=remove-payment-method error=empty_card_id', ['subscription_id' => $subscriptionId]);
            return ['status' => 0, 'data' => [], 'error' => null];
        }

        $uri     = $this->basePath . '/subscriptions/' . rawurlencode($subscriptionId) . '/payment-methods/' . rawurlencode($cardId);
        $headers = $this->buildHeaders($accessToken);

        $startedAt  = microtime(true);
        $response   = $this->requester->delete($uri, $headers);
        $status     = $response->getStatus();
        $data       = $this->responseToArray($response);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $error = $this->classifyRemoveError($status, $data);

        $logLevel = $error === 'cannot_remove_default' ? 'error' : ($status >= 400 ? 'warning' : 'info');
        $this->log($logLevel, 'op=remove-payment-method status=' . $status, [
            'http_status'    => $status,
            'duration_ms'    => $durationMs,
            'subscription_id' => $subscriptionId,
            'error'          => $error,
        ]);

        return [
            'status' => $status,
            'data'   => $data,
            'error'  => $error,
        ];
    }

    /**
     * Executes a Merchant-Initiated Transaction (MIT) — recurring renewal.
     *
     * MIT is intentionally stateless from the plugin side: payer identity,
     * card, device and additional_info were all persisted by the Core P&P
     * during the CIT. Only the subscription reference and transaction
     * details are required.
     *
     * Returns an associative array with:
     *   - `status`             (int)   HTTP status code
     *   - `credential_revoked` (bool)  true when status is 401 or 403
     *   - `data`               (array) decoded response body
     *
     * @param string $accessToken    Pre-approval bearer token (§3.5 admin config).
     * @param array<string, mixed> $payload MIT request body — must NOT contain token/payer/device/additional_info.
     * @param string $idempotencyKey Deterministic UUID v4 from {@see SubscriptionsHelper::buildMitSeed()}.
     *
     * @return array<string, mixed>
     */
    public function mit(string $accessToken, array $payload, string $idempotencyKey): array
    {
        $subscriptionId = $payload['subscription']['id'] ?? '';
        $externalRef    = $payload['transaction']['external_reference'] ?? '';
        $requestId      = $idempotencyKey;
        $startedAt      = microtime(true);

        $this->log('info', 'op=mit status=sending', [
            'request_id'         => $requestId,
            'subscription_id'    => $subscriptionId,
            'external_reference' => $externalRef,
        ]);

        $headers    = $this->buildHeaders($accessToken, $idempotencyKey);
        $response   = $this->requester->post($this->basePath . '/intents/mit', $headers, $payload);
        $status     = $response->getStatus();
        $data       = $response->getData();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->log(
            $status >= 400 ? 'error' : 'info',
            'op=mit http_status=' . $status,
            [
                'request_id'  => $requestId,
                'http_status' => $status,
                'duration_ms' => $durationMs,
                'subscription_id' => $subscriptionId,
            ]
        );

        return [
            'status'             => $status,
            'credential_revoked' => ($status === 401 || $status === 403),
            'data'               => is_array($data) ? $data : (array) $data,
        ];
    }

    /**
     * Cancels a subscription in the Core P&P AP v2 (Flow 4 — §4.6).
     *
     * Issues `DELETE /subscriptions/{subscription_id}` with no body and no
     * idempotency key (DELETE is naturally idempotent; 404 = already gone).
     *
     * Returns an associative array with:
     *   - `status`    (int)  HTTP status code
     *   - `success`   (bool) true only for 204
     *   - `not_found` (bool) true for 404 (silent ok — subscription already gone)
     *
     * Any status other than 204/404 is an error. The caller is responsible for
     * logging and continuing — WCS cancellation must NOT be blocked.
     *
     * @param string $accessToken    Pre-approval bearer token (§3.5 admin config).
     * @param string $subscriptionId Core P&P subscription identifier (`_mp_subscription_id`).
     *
     * @return array<string, mixed>
     */
    public function deleteSubscription(string $accessToken, string $subscriptionId): array
    {
        $startedAt = microtime(true);
        $this->log('info', 'op=subscription-cancel status=sending', ['subscription_id' => $subscriptionId]);

        $headers    = $this->buildHeaders($accessToken);
        $uri        = $this->basePath . '/subscriptions/' . rawurlencode($subscriptionId);
        $response   = $this->requester->delete($uri, $headers);
        $status     = $response->getStatus();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($status === 204) {
            $this->log('info', 'op=subscription-cancel status=ok', [
                'subscription_id' => $subscriptionId,
                'http_status'     => $status,
                'duration_ms'     => $durationMs,
            ]);
        } elseif ($status === 404) {
            $this->log('warning', 'op=subscription-cancel status=not_found', [
                'subscription_id' => $subscriptionId,
                'http_status'     => $status,
                'duration_ms'     => $durationMs,
            ]);
        } else {
            $this->log('error', 'op=subscription-cancel http_status=' . $status, [
                'subscription_id' => $subscriptionId,
                'http_status'     => $status,
                'duration_ms'     => $durationMs,
            ]);
        }

        return [
            'status'    => $status,
            'success'   => ($status === 204),
            'not_found' => ($status === 404),
        ];
    }

    /**
     * Extrai o `card_id` do PM com `default === true` cujo valor seja
     * diferente de `$currentCardId`. Retorna `null` quando o token novo
     * gerou o mesmo cartão já ativo (re-add do mesmo cartão).
     *
     * @param array<string, mixed> $data
     */
    private function extractDefaultCardId(array $data, ?string $currentCardId): ?string
    {
        $methods = $data['profile']['payment_methods'] ?? [];
        if (!is_array($methods)) {
            return null;
        }

        foreach ($methods as $pm) {
            if (!is_array($pm)) {
                continue;
            }
            if (($pm['default'] ?? false) !== true) {
                continue;
            }
            $cardId = isset($pm['card_id']) ? (string) $pm['card_id'] : null;
            if ($cardId === null || $cardId === '') {
                continue;
            }
            if ($currentCardId !== null && $cardId === $currentCardId) {
                continue;
            }
            return $cardId;
        }

        return null;
    }

    /**
     * Mapeia respostas 422 do `DELETE /payment-methods` para flags do contrato.
     *
     * @param array<string, mixed> $data
     */
    private function classifyRemoveError(int $status, array $data): ?string
    {
        if ($status !== 422) {
            return null;
        }

        $code = (string) ($data['code'] ?? $data['error'] ?? $data['error_code'] ?? '');
        if ($code === '') {
            return null;
        }

        if (stripos($code, 'LastPaymentMethod') !== false) {
            return 'last_payment_method';
        }
        if (stripos($code, 'CannotRemoveDefault') !== false) {
            return 'cannot_remove_default';
        }

        return null;
    }

    /**
     * Normaliza o payload do {@see Response} para array associativo.
     *
     * @return array<string, mixed>
     */
    private function responseToArray(Response $response): array
    {
        $data = $response->getData();
        if (is_array($data)) {
            return $data;
        }
        if ($data === null) {
            return [];
        }
        $decoded = json_decode((string) $data, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Emits a structured WC Logger entry under {@see LOG_SOURCE}.
     *
     * Routes through {@see scrubPii()} before writing so PII is stripped at a
     * single audited entry point — all future operation methods must use this
     * method instead of calling the transport directly.
     *
     * Unknown levels are treated as `warning` rather than silently downgraded to `info`.
     *
     * @param string               $level   One of `info`, `warning`, `error`, `debug`.
     * @param string               $message Short structured message (e.g. `"op=cit status=approved"`).
     * @param array<string, mixed> $context Key=value pairs — scrubbed before writing.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $sanitized = $this->scrubPii($context);
        $transport = $this->logs->file;

        switch ($level) {
            case 'error':
                $transport->error($message, self::LOG_SOURCE, $sanitized);
                break;
            case 'warning':
                $transport->warning($message, self::LOG_SOURCE, $sanitized);
                break;
            case 'debug':
                $transport->debug($message, self::LOG_SOURCE, $sanitized);
                break;
            case 'info':
                $transport->info($message, self::LOG_SOURCE, $sanitized);
                break;
            default:
                $transport->warning($message, self::LOG_SOURCE, $sanitized);
                break;
        }
    }

    /**
     * Strips well-known PII-bearing keys from a log context array.
     *
     * Case-insensitive so keys like `Authorization` or `TOKEN` are also removed.
     * Only top-level keys are inspected — nested PII is the caller's responsibility.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function scrubPii(array $context): array
    {
        $deny = array_flip([
            'authorization',
            'token',
            'card_token',
            'email',
            'payer_email',
            'document',
            'cpf',
            'cnpj',
            'last_four',
            'last_four_digits',
            'first_six_digits',
            'device_fingerprint',
        ]);

        foreach (array_keys($context) as $key) {
            if (isset($deny[strtolower($key)])) {
                unset($context[$key]);
            }
        }

        return $context;
    }
}

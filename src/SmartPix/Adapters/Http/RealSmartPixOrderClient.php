<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Http;

use JsonException;
use RuntimeException;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\WebhookUrl;
use Throwable;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixCpf;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationReferences;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationRequestId;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationTiming;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEnvironment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMutationDispatchClient;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMutationNormalizer;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixMutationProcessor;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixRequestObservation;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

final class RealSmartPixOrderClient implements SmartPixCreationDispatchClient, SmartPixMutationDispatchClient, SmartPixCreationTiming, SmartPixCreationRequestId, SmartPixCreationReferences, SmartPixRequestObservation
{
    private const BETA_BASE_PATH = '/homol/plugins-platforms';

    private const PRODUCTION_BASE_PATH = '/plugins-platforms';

    /**
     * P&P GET statuses documented as transient read failures: 418 communication error,
     * 422 failed order data update and 424 database dependency failure.
     */
    private const TRANSIENT_QUERY_STATUSES = [418, 422, 424];

    private HttpClientInterface $httpClient;

    private Seller $seller;

    private Store $store;

    private bool $mutationSnapshots;

    private SmartPixTelemetry $telemetry;

    private string $querySource;

    private ?float $creationDuration = null;

    private ?string $creationRequestId = null;

    private array $requestDetails = [];

    private string $diagnosticMessage = '';

    private string $diagnosticStage = 'request_validation';

    /** @var array<string, string>|null */
    private ?array $creationReferences = null;

    /** @var callable(): string */
    private $apiRequestUrlResolver;

    /**
     * @param (callable(): string)|null $apiRequestUrlResolver Lazily returns the WooCommerce API
     *                                                         URL of the Pix gateway webhook.
     */
    public function __construct(
        HttpClientInterface $httpClient,
        Seller $seller,
        Store $store,
        ?SmartPixTelemetry $telemetry = null,
        string $querySource = 'pending_query',
        ?callable $apiRequestUrlResolver = null,
        bool $mutationSnapshots = false
    ) {
        $this->httpClient = $httpClient;
        $this->seller     = $seller;
        $this->store      = $store;
        $this->telemetry = $telemetry ?? new SmartPixTelemetry();
        $this->querySource = $querySource === 'notification' ? 'notification' : 'pending_query';
        $this->mutationSnapshots = $mutationSnapshots;
        $this->apiRequestUrlResolver = $apiRequestUrlResolver ?? static function (): string {
            // WC() may be unavailable outside a full WooCommerce request.
            $woocommerce = function_exists('WC') ? WC() : null;
            return $woocommerce ? (string) $woocommerce->api_request_url(PixGateway::WEBHOOK_API_NAME) : '';
        };
    }

    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null, ?callable $onDispatch = null): array
    {
        $this->diagnosticStage = 'create_request';
        $this->diagnosticMessage = '';
        $this->creationDuration = null;
        $this->creationRequestId = null;
        $this->creationReferences = null;
        $this->observeRequest(null, 'POST ' . $this->getBasePath() . '/v1/orders');
        if ($payment === null || !$this->isCreateRequestValid($request, $payment)) {
            return $this->observedFailure('validation', false, 'error', 'create', $request, null, 'create request or authorized payment proof is missing/invalid');
        }

        $headers = $this->buildHeaders($payment->getIdempotencyKey());
        if (!$this->hasRequiredAuthorization($headers)) {
            return $this->observedFailure('authentication', false, 'error', 'create', $request, null, 'seller access token is missing; create request was not sent');
        }

        if (!$this->hasRequiredIntegrationHeaders($headers, true)) {
            return $this->observedFailure('validation', false, 'error', 'create', $request, null, 'required create integration headers are missing');
        }

        $store = $this->buildStore();
        $seller = $this->buildSeller();
        if ($store === null || $seller === null) {
            return $this->observedFailure('validation', false, 'error', 'create', $request, null, 'store or seller configuration is missing/invalid');
        }

        try {
            $body = json_encode($this->buildCreateBody($request, $payment, $store, $seller), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return $this->observedFailure('validation', false, 'error', 'create', $request, null, SmartPixDiagnostic::exception($exception));
        }

        $path = $this->getBasePath() . '/v1/orders';
        if ($onDispatch !== null) {
            $onDispatch();
        }
        $startedAt = $this->telemetry->startTimer();
        try {
            try {
                $response = $this->httpClient->request('POST', $path, $headers, $body);
            } finally {
                // Capture the transport alone, before normalization or telemetry delivery.
                $this->creationDuration = $this->telemetry->elapsed($startedAt);
            }
        } catch (TransportException $exception) {
            return $this->observedFailure(
                $this->isTimeout($exception) ? 'timeout' : 'unavailable',
                true,
                'processing',
                'create',
                $request,
                null,
                SmartPixDiagnostic::exception($exception, [$payment->getAuthorizedPseudotoken(), $payment->getOpenFinanceAccountId(), $payment->getIdempotencyKey()])
            );
        }

        $this->creationRequestId = $this->readCreationRequestId($response);
        $this->observeRequest($this->creationRequestId, 'POST ' . $this->getBasePath() . '/v1/orders');
        $result = $this->mapCreateResponse($response, $request);
        $this->creationReferences = $this->readCreationReferences($response, $result);
        return $this->observeResult(
            $result,
            'create',
            $request,
            $response,
            '',
            [$payment->getAuthorizedPseudotoken(), $payment->getOpenFinanceAccountId(), $payment->getIdempotencyKey()]
        );
    }

    /** @return array<string, string>|null */
    public function creationReferences(): ?array
    {
        return $this->creationReferences;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, string>|null
     */
    private function readCreationReferences(HttpResponse $response, array $result): ?array
    {
        $data = $response->getData();
        if (
            !in_array($response->getStatus(), [200, 201], true)
            || !is_array($data) || ($data['site_id'] ?? null) !== 'MLB'
            || !is_array($data['payments'] ?? null) || count($data['payments']) !== 1
            || !is_array($result['payments'] ?? null) || count($result['payments']) !== 1
            || !$this->isOpaqueReference($result['order_reference'] ?? null)
            || ($data['id'] ?? null) !== $result['order_reference']
            || !is_array($data['payments'][0] ?? null)
            || !$this->isOpaqueReference($data['payments'][0]['id'] ?? null)
            || ($result['payments'][0]['reference'] ?? null) !== $data['payments'][0]['id']
        ) {
            return null;
        }

        $payment = $data['payments'][0];
        $references = [
            'order_reference' => (string) $data['id'],
            'payment_reference' => (string) $payment['id'],
        ];
        // Copy only identifiers actually present in the authenticated provider response.
        foreach (['order_id' => 'provider_order_reference', 'transaction_intent_id' => 'transaction_intent_reference'] as $key => $target) {
            $value = is_array($payment['references'] ?? null) ? ($payment['references'][$key] ?? null) : null;
            if ($this->isOpaqueReference($value)) {
                $references[$target] = (string) $value;
            }
        }
        return $references;
    }

    public function creationDurationSeconds(): ?float
    {
        return $this->creationDuration;
    }

    public function creationRequestId(): ?string
    {
        return $this->creationRequestId;
    }

    public function requestDetails(): array
    {
        return $this->requestDetails;
    }

    private function observeRequest(?string $requestId, string $route): void
    {
        $this->requestDetails = ['api_route' => $route];
        if ($requestId !== null) {
            $this->requestDetails['request_id'] = $requestId;
        }
        $this->telemetry->observeRequest($requestId, $route);
    }

    private function readCreationRequestId(HttpResponse $response): ?string
    {
        foreach ($response->getHeaders() as $name => $value) {
            if (!is_string($name) || strcasecmp($name, 'x-request-id') !== 0 || !is_string($value)) {
                continue;
            }

            $requestId = trim($value);
            // The response header is external input: never forward free text or control characters to telemetry.
            return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D', $requestId) === 1
                ? $requestId : null;
        }

        return null;
    }

    public function query(array $request): array
    {
        $result = $this->querySnapshot($request, $this->mutationSnapshots);
        if (!$this->mutationSnapshots) {
            if (
                isset($result['order_context']) && (($result['payments'][0]['state'] ?? null) === 'refunded'
                || ($result['observed']['order_status_detail'] ?? null) === 'partially_refunded')
            ) {
                $result['refund_context'] = $result['order_context'];
            }
            unset($result['order_context']);
        }
        return $result;
    }

    /** Read-only snapshot for the operation composition; ordinary query retains normalized v1. */
    private function querySnapshot(array $request, bool $mutationSnapshot = false, string $operation = 'get', string $stage = 'order_read'): array
    {
        $this->diagnosticStage = $stage;
        $this->diagnosticMessage = '';
        $this->observeRequest(null, 'GET ' . $this->getBasePath() . '/v1/orders/{id}');
        $orderReference = $request['order_reference'] ?? null;
        if (!$this->isQueryRequestValid($request, $orderReference)) {
            return $this->observedFailure('validation', false, 'error', $operation, $request, null, 'read request has invalid order/attempt reference, surface, country or payment contract');
        }

        $headers = $this->buildReadHeaders();
        if (!$this->hasRequiredAuthorization($headers)) {
            return $this->observedFailure('authentication', false, 'error', $operation, $request, $orderReference, 'seller access token is missing; read request was not sent');
        }

        if (!$this->hasRequiredIntegrationHeaders($headers, false)) {
            return $this->observedFailure('validation', false, 'error', $operation, $request, $orderReference, 'required read integration headers are missing');
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $this->getBasePath() . '/v1/orders/' . rawurlencode($orderReference),
                $headers
            );
        } catch (TransportException $exception) {
            return $this->observedFailure(
                $this->isTimeout($exception) ? 'timeout' : 'unavailable',
                true,
                'processing',
                $operation,
                $request,
                $orderReference,
                SmartPixDiagnostic::exception($exception)
            );
        }

        $this->observeRequest($this->readCreationRequestId($response), 'GET ' . $this->getBasePath() . '/v1/orders/{id}');
        return $this->observeResult($this->mapQueryResponse($response, $orderReference, $request, $mutationSnapshot), $operation, $request, $response);
    }

    public function cancel(array $request, ?callable $onDispatch = null): array
    {
        $this->diagnosticStage = 'request_validation';
        $this->diagnosticMessage = '';
        $context = $request['operation_context'] ?? null;
        $read = $request;
        unset($read['operation_context']);
        $seller = $this->seller->getCustIdFromAT();
        if (
            !$this->isQueryRequestValid($read, $read['order_reference'] ?? null)
            || !is_array($context) || count($context) !== 3
            || ($context['contract_version'] ?? null) !== SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            || !is_string($seller) || $seller === '' || ($context['seller_reference'] ?? null) !== $seller
            || !is_string($context['idempotency_key'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $context['idempotency_key']) !== 1
        ) {
            return $this->observedFailure('validation', false, 'error', 'cancel', $request, null, 'mutation request, refund amount or seller/idempotency operation context is missing/invalid');
        }
        $reference = $read['order_reference'];
        $headers = $this->buildHeaders($context['idempotency_key']);
        if (!$this->hasRequiredAuthorization($headers) || !$this->hasRequiredIntegrationHeaders($headers, true)) {
            return $this->observedFailure('authentication', false, 'error', 'cancel', $request, $reference, 'required seller authorization or mutation integration headers are missing');
        }
        $before = $this->querySnapshot($read, true, 'cancel', 'preflight_read');
        if (!isset($before['order_context'])) {
            return $before['outcome'] === 'failure' ? $before : $this->failure('invalid_response', false, 'error', $reference);
        }
        // A payment approved between the processor read and this read must never be cancelled.
        if (in_array($before['payments'][0]['state'], ['approved', 'cancelled', 'rejected'], true)) {
            return $before;
        }
        if ($before['payments'][0]['state'] !== 'processing' || $before['order_context']['refunded_amount'] !== '0.00') {
            return $this->failure('conflict', false, 'error', $reference);
        }
        $path = $this->getBasePath() . '/v1/orders/' . rawurlencode($reference) . '/cancel';
        $route = 'POST ' . $this->getBasePath() . '/v1/orders/{id}/cancel';
        $this->diagnosticStage = 'cancel_request';
        $this->diagnosticMessage = '';
        $this->observeRequest(null, $route);
        try {
            // From this boundary onward the POST may have reached the provider, even on timeout.
            if ($onDispatch !== null) {
                $onDispatch();
            }
            $response = $this->httpClient->request('POST', $path, $headers, null);
            $this->observeRequest($this->readCreationRequestId($response), $route);
        } catch (TransportException $exception) {
            return $this->observedFailure($this->isTimeout($exception) ? 'timeout' : 'unavailable', false, 'processing', 'cancel', $request, $reference, SmartPixDiagnostic::exception($exception, [$this->seller->getCredentialsAccessToken()]));
        }
        if ($response->getStatus() !== 200) {
            $status = $response->getStatus();
            return $this->observeResult($this->failure($status >= 500 ? 'unavailable' : ($status === 429 ? 'rate_limit' : $this->mapClientErrorCategory($status)), false, 'processing', $reference), 'cancel', $request, $response, '', [$this->seller->getCredentialsAccessToken()]);
        }
        $result = $this->mapQueryResponse($response, $reference, $read, true);
        if (
            !isset($result['order_context'])
            || !in_array($result['payments'][0]['state'] ?? null, ['cancelled', 'approved', 'rejected'], true)
            || $result['payments'][0]['reference'] !== $before['payments'][0]['reference']
            || $result['order_context']['amount'] !== $before['order_context']['amount']
            || $result['order_context']['refunded_amount'] !== '0.00'
        ) {
            return $this->observeResult($this->failure($result['error']['category'] ?? 'invalid_response', false, 'processing', $reference), 'cancel', $request, $response);
        }
        return $result;
    }

    public function refund(array $request, ?callable $onDispatch = null): array
    {
        $this->diagnosticStage = 'request_validation';
        $this->diagnosticMessage = '';
        $context = $request['operation_context'] ?? null;
        $read = $request;
        unset($read['operation_context']);
        $read['refund_amount'] = null;
        $seller = $this->seller->getCustIdFromAT();
        $amount = SmartPixMoney::normalize($request['refund_amount']['value'] ?? null);
        if (
            !$this->isQueryRequestValid($read, $read['order_reference'] ?? null)
            || ($request['amount'] ?? null) !== null || $amount === null || $amount === '0.00'
            || ($request['refund_amount'] ?? null) !== ['value' => $amount, 'currency' => 'BRL']
            || !is_array($context) || count($context) !== 3
            || ($context['contract_version'] ?? null) !== SmartPixMutationProcessor::SNAPSHOT_CONTRACT
            || !is_string($seller) || $seller === '' || ($context['seller_reference'] ?? null) !== $seller
            || !is_string($context['idempotency_key'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $context['idempotency_key']) !== 1
        ) {
            return $this->observedFailure('validation', false, 'error', 'refund', $request, null, 'mutation request, refund amount or seller/idempotency operation context is missing/invalid');
        }
        $reference = $read['order_reference'];
        $headers = $this->buildHeaders($context['idempotency_key']);
        if (!$this->hasRequiredAuthorization($headers) || !$this->hasRequiredIntegrationHeaders($headers, true)) {
            return $this->observedFailure('authentication', false, 'error', 'refund', $request, $reference, 'required seller authorization or mutation integration headers are missing');
        }
        // Derive payment IDs and remaining balance exclusively from a fresh authenticated GET.
        $before = $this->querySnapshot($read, true, 'refund', 'preflight_read');
        if (($before['outcome'] ?? null) === 'failure') {
            return $before;
        }
        if (!isset($before['order_context']) || ($before['payments'][0]['state'] ?? null) !== 'approved') {
            return $this->failure('invalid_response', false, 'error', $reference);
        }
        $remaining = SmartPixMoney::subtract($before['order_context']['amount']['value'], $before['order_context']['refunded_amount']);
        if ($remaining === null || SmartPixMoney::compare($amount, $remaining) > 0) {
            return $this->failure('validation', false, 'error', $reference);
        }
        $body = $amount === $remaining ? null : json_encode([
            'payments' => [['id' => $before['payments'][0]['reference'], 'amount' => $amount]],
        ], JSON_THROW_ON_ERROR);
        $path = $this->getBasePath() . '/v1/orders/' . rawurlencode($reference) . '/refund';
        $route = 'POST ' . $this->getBasePath() . '/v1/orders/{id}/refund';
        $this->diagnosticStage = 'refund_request';
        $this->diagnosticMessage = '';
        $this->observeRequest(null, $route);
        try {
            // Read/validation failures never signal dispatch; transport failures remain uncertain.
            if ($onDispatch !== null) {
                $onDispatch();
            }
            $response = $this->httpClient->request('POST', $path, $headers, $body);
            $this->observeRequest($this->readCreationRequestId($response), $route);
        } catch (TransportException $exception) {
            return $this->observedFailure($this->isTimeout($exception) ? 'timeout' : 'unavailable', false, 'processing', 'refund', $request, $reference, SmartPixDiagnostic::exception($exception, [$this->seller->getCredentialsAccessToken()]));
        }
        if ($response->getStatus() !== 200) {
            $status = $response->getStatus();
            return $this->observeResult($this->failure($status >= 500 ? 'unavailable' : ($status === 429 ? 'rate_limit' : $this->mapClientErrorCategory($status)), false, 'processing', $reference), 'refund', $request, $response, '', [$this->seller->getCredentialsAccessToken()]);
        }
        $normalizer = new SmartPixMutationNormalizer();
        if (!is_array($response->getData()) || !$normalizer->confirmsRefund($response->getData(), $before, $amount)) {
            $this->diagnosticMessage = is_array($response->getData()) ? $normalizer->diagnosticMessage() : 'refund receipt is not a JSON object';
            return $this->observeResult($this->failure('invalid_response', false, 'processing', $reference), 'refund', $request, $response, '', [$this->seller->getCredentialsAccessToken()]);
        }
        try {
            $this->diagnosticStage = 'refund_confirmation_read';
            $this->diagnosticMessage = '';
            $readRoute = 'GET ' . $this->getBasePath() . '/v1/orders/{id}';
            $this->observeRequest(null, $readRoute);
            $confirmation = $this->httpClient->request('GET', $this->getBasePath() . '/v1/orders/' . rawurlencode($reference), $this->buildReadHeaders());
            $this->observeRequest($this->readCreationRequestId($confirmation), $readRoute);
            $after = $this->mapQueryResponse($confirmation, $reference, $read, true);
        } catch (TransportException $exception) {
            return $this->observedFailure($this->isTimeout($exception) ? 'timeout' : 'unavailable', false, 'processing', 'refund', $request, $reference, SmartPixDiagnostic::exception($exception, [$this->seller->getCredentialsAccessToken()]));
        }
        if (
            !isset($after['order_context'])
            || !is_array($confirmation->getData()) || !$normalizer->confirmsRefundHistory($confirmation->getData(), $response->getData(), $amount) || ($after['outcome'] ?? null) !== 'success'
            || !in_array($after['payments'][0]['state'] ?? null, ['approved', 'refunded'], true)
            || $after['payments'][0]['reference'] !== $before['payments'][0]['reference']
            || $after['order_context']['amount'] !== $before['order_context']['amount']
            || $after['order_context']['refunded_amount'] !== SmartPixMoney::add($before['order_context']['refunded_amount'], $amount)
        ) {
            if ($this->diagnosticMessage === '') {
                $this->diagnosticMessage = $normalizer->diagnosticMessage() !== '' ? $normalizer->diagnosticMessage()
                    : 'refreshed order/payment context or cumulative refund total does not confirm the requested refund';
            }
            return $this->observeResult($this->failure('invalid_response', false, 'processing', $reference), 'refund', $request, $confirmation, '', [$this->seller->getCredentialsAccessToken()]);
        }
        $after['state'] = 'terminal_success';
        $after['payments'][0]['state'] = 'refunded';
        $after['payments'][0]['amount'] = ['value' => $amount, 'currency' => 'BRL'];
        return $after;
    }

    /**
     * Key order follows the provider's documented create payload.
     *
     * @param array<string, mixed>  $request
     * @param array<string, string> $store
     * @param array<string, string> $seller
     *
     * @return array<string, mixed>
     */
    private function buildCreateBody(array $request, SmartPixAuthorizedPayment $payment, array $store, array $seller): array
    {
        $amount  = $request['amount']['value'];
        $details = $request['order_details'];

        $body = [
            'processing_mode'    => 'automatic',
            'type'               => 'online',
            'description'        => $details['description'],
            'external_reference' => $request['attempt_reference'],
            'transaction_amount' => $amount,
            // Smart Pix is MLB-only; Orders expects the plugin's sponsor as a JSON string.
            'sponsor_id'         => (string) Country::SPONSOR_ID_MLB,
        ];

        $notificationUrl = $this->buildNotificationUrl();
        if ($notificationUrl !== '') {
            $body['notification_url'] = $notificationUrl;
        }

        return $body + [
            'ip_address'         => $details['ip_address'],
            'payments'           => [
                [
                    'amount'             => $amount,
                    'date_of_expiration' => 'PT15M',
                    'payment_method'     => [
                        'id'                      => 'smart_transfer',
                        'type'                    => 'smart_transfer',
                        'token'                   => $payment->getAuthorizedPseudotoken(),
                        'open_finance_account_id' => $payment->getOpenFinanceAccountId(),
                    ],
                ],
            ],
            'payer'              => $details['payer'],
            'items'              => $details['items'],
            'store'              => $store,
            'seller'             => $seller,
            'config'             => [
                'capture'         => true,
                'binary_mode'     => false,
                'processing_mode' => 'automatic',
            ],
            // Merchant metadata is not defined yet; the provider expects an object.
            'metadata'           => new \stdClass(),
        ];
    }

    /**
     * Same construction as the other gateways, routed to the Pix webhook (source_news=webhooks).
     * Without a public URL the field is omitted, like subscription renewals, and a fixed
     * warning without identifiers is logged.
     */
    private function buildNotificationUrl(): string
    {
        $notificationUrl = WebhookUrl::build(
            $this->store->getCustomDomain(),
            $this->store->getCustomDomainOptions(),
            $this->apiRequestUrlResolver,
            get_site_url(),
            PixGateway::WEBHOOK_API_NAME
        );
        if ($notificationUrl === '') {
            try {
                wc_get_logger()->warning('Smart Pix notification URL unavailable.', [
                    'source' => 'mercadopago-smart-pix', 'reason' => 'no_public_url',
                ]);
            } catch (Throwable $loggingFailure) {
                // An optional logging integration cannot change the creation request.
            }
        }

        return $notificationUrl;
    }

    /** @return array<string, string>|null */
    private function buildStore(): ?array
    {
        $storeId = $this->getStoreReference();
        $siteUrl = get_site_url();
        if ($storeId === '' || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return ['id' => $storeId, 'url' => $siteUrl];
    }

    /** @return array<string, string>|null */
    private function buildSeller(): ?array
    {
        // Match the other gateways' public storefront URL, not the current checkout request.
        $url = explode('?', home_url(), 2)[0];
        $url = explode('#', $url, 2)[0];
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        return ['referral_url' => $url, 'website' => $url];
    }

    /**
     * No provider-agreed store identifier exists yet. The configured store identificator is the
     * value the other gateways already send as additional_info.seller.store_id.
     */
    private function getStoreReference(): string
    {
        return trim($this->store->getStoreId());
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $idempotencyKey): array
    {
        return [
            'Authorization'       => 'Bearer ' . $this->seller->getCredentialsAccessToken(),
            'Content-Type'        => 'application/json',
            'Accept'              => 'application/json',
            'X-Platform-Id'       => $this->getPlatformId(),
            'X-Product-Id'        => Device::getDeviceProductId(),
            'X-Idempotency-Key'   => $idempotencyKey,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildReadHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->seller->getCredentialsAccessToken(),
            'Accept'        => 'application/json',
            'X-Platform-Id' => $this->getPlatformId(),
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function isCreateRequestValid(array $request, SmartPixAuthorizedPayment $payment): bool
    {
        return $this->hasExactNormalizedRequestFields($request, true)
            && $this->isKnownSurface($request['surface'])
            && ($request['country'] ?? null) === 'MLB'
            && ($request['payment_contract'] ?? null) === 'orders_smart_pix_v1'
            && $this->isOpaqueReference($request['attempt_reference'] ?? null)
            && ($request['order_reference'] ?? null) === null
            && is_array($request['amount'] ?? null)
            && array_keys($request['amount']) === ['value', 'currency']
            && is_string($request['amount']['value'] ?? null)
            && ($request['amount']['currency'] ?? null) === 'BRL'
            && preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $request['amount']['value']) === 1
            && strlen($request['amount']['value']) <= 64
            && $request['amount']['value'] !== '0.00'
            && ($request['refund_amount'] ?? null) === null
            && $payment->getAuthorizedPseudotoken() !== ''
            && $payment->getIdempotencyKey() !== ''
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $payment->getOpenFinanceAccountId()) === 1
            && $this->isOrderDetailsValid($request['order_details'], $request['amount']['value']);
    }

    /**
     * @param mixed $details
     */
    private function isOrderDetailsValid($details, string $amount): bool
    {
        return is_array($details)
            && $this->hasExactKeys($details, ['description', 'ip_address', 'payer', 'items'])
            && $this->isNonEmptyText($details['description'])
            && is_string($details['ip_address'])
            && filter_var($details['ip_address'], FILTER_VALIDATE_IP) !== false
            && $this->isPayerValid($details['payer'])
            && $this->isItemsValid($details['items'], $amount);
    }

    /** @param mixed $payer */
    private function isPayerValid($payer): bool
    {
        return is_array($payer)
            && ($this->hasExactKeys($payer, ['email', 'first_name', 'last_name'])
                || $this->hasExactKeys($payer, ['email', 'first_name', 'last_name', 'identification']))
            && is_string($payer['email'])
            && filter_var($payer['email'], FILTER_VALIDATE_EMAIL) !== false
            && $this->isNonEmptyText($payer['first_name'])
            && ($payer['last_name'] === '' || $this->isNonEmptyText($payer['last_name']))
            && (!array_key_exists('identification', $payer) || (
                is_array($payer['identification'])
                && $this->hasExactKeys($payer['identification'], ['type', 'number'])
                && $payer['identification']['type'] === SmartPixCpf::TYPE
                && is_string($payer['identification']['number'])
                && SmartPixCpf::normalize($payer['identification']['number']) === $payer['identification']['number']
            ));
    }

    /**
     * Items must reconcile exactly with the authoritative amount; they never define the charge.
     * @param mixed $items
     */
    private function isItemsValid($items, string $amount): bool
    {
        if (!is_array($items) || $items === [] || array_keys($items) !== range(0, count($items) - 1)) {
            return false;
        }
        $total = '0.00';
        foreach ($items as $item) {
            if (
                !is_array($item)
                || !$this->hasExactKeys($item, ['id', 'title', 'unit_price', 'quantity', 'type'])
                || !$this->isNonEmptyText($item['id'])
                || !$this->isNonEmptyText($item['title'])
                || !SmartPixDecimal::isCanonical($item['unit_price'])
                || !is_int($item['quantity'])
                || $item['quantity'] < 1
                || $item['quantity'] > SmartPixDecimal::MAX_FACTOR
                || !$this->isNonEmptyText($item['type'])
                || strlen($item['type']) > SmartPixOrderDetails::MAX_ITEM_TYPE_LENGTH
            ) {
                return false;
            }
            $total = SmartPixDecimal::add($total, SmartPixDecimal::multiply($item['unit_price'], $item['quantity']));
        }

        return $total === $amount;
    }

    /** @param mixed $value */
    private function isNonEmptyText($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param array<string, mixed> $request
     * @param mixed                $orderReference
     */
    private function isQueryRequestValid(array $request, $orderReference): bool
    {
        return $this->hasExactNormalizedRequestFields($request, false)
            && $this->isKnownSurface($request['surface'])
            && ($request['country'] ?? null) === 'MLB'
            && ($request['payment_contract'] ?? null) === 'orders_smart_pix_v1'
            && $this->isOpaqueReference($request['attempt_reference'] ?? null)
            && $this->isOpaqueReference($orderReference)
            && ($request['amount'] ?? null) === null
            && ($request['refund_amount'] ?? null) === null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCreateResponse(HttpResponse $response, array $request): array
    {
        $status = $response->getStatus();
        $data   = (array) $response->getData();

        if ($status === 200 || $status === 201) {
            $normalizer = new SmartPixOrderNormalizer();
            $result = $normalizer->normalize(
                $data,
                null,
                $request['amount'],
                $request['attempt_reference']
            );
            $this->diagnosticMessage = $normalizer->diagnosticMessage();
            if (
                ($result['error']['category'] ?? null) === 'invalid_response'
                && ($result['order_reference'] ?? null) === null
            ) {
                // A successful HTTP status does not establish whether creation failed.
                // Recovery without an Order ID needs the provider idempotency contract;
                // preserve uncertainty without authorizing an automatic POST retry.
                return $this->failure('invalid_response', false, 'processing');
            }
            return $result;
        }

        if ($status === 429) {
            return $this->failure('rate_limit', true, 'processing');
        }

        if ($status >= 500) {
            return $this->failure('unavailable', true, 'processing');
        }

        if ($status >= 400) {
            return $this->failure($this->mapClientErrorCategory($status), false, 'error');
        }

        return $this->failure('invalid_response', false, 'error');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapQueryResponse(HttpResponse $response, string $orderReference, array $request, bool $mutationSnapshot = false): array
    {
        $status = $response->getStatus();
        $data   = (array) $response->getData();

        if ($status === 200) {
            // Failed Orders retain their rejection reason. Lifecycle queries share the
            // authenticated mutation normalizer, including transfers still awaiting funding.
            $lifecycle = in_array($data['status'] ?? null, ['canceled', 'expired', 'refunded'], true)
                || (($data['status'] ?? null) === 'processed'
                    && ($data['status_detail'] ?? null) === 'partially_refunded');
            if (!$mutationSnapshot && !$lifecycle) {
                return $this->normalizeOrder(
                    $data,
                    $orderReference,
                    null,
                    $request['attempt_reference']
                );
            }
            if (array_key_exists('site_id', $data)) {
                try {
                    return (new SmartPixMutationNormalizer())->normalize($data, $request, (string) $this->seller->getCustIdFromAT());
                } catch (RuntimeException $exception) {
                    $this->diagnosticMessage = SmartPixDiagnostic::exception($exception);
                    return $this->failure('invalid_response', false, 'error', $orderReference);
                }
            }
            return $this->normalizeOrder(
                $data,
                $orderReference,
                null,
                $request['attempt_reference']
            );
        }

        if ($status === 429) {
            return $this->failure('rate_limit', true, 'processing', $orderReference);
        }

        if ($status >= 500 || in_array($status, self::TRANSIENT_QUERY_STATUSES, true)) {
            return $this->failure('unavailable', true, 'processing', $orderReference);
        }

        if ($status === 404) {
            return $this->failure('unknown', false, 'error', $orderReference);
        }

        if ($status >= 400) {
            return $this->failure(
                $this->mapClientErrorCategory($status),
                false,
                'error',
                $orderReference
            );
        }

        return $this->failure('invalid_response', false, 'error', $orderReference);
    }

    /** @param array<string, mixed> $request */
    private function observedFailure(
        string $category,
        bool $retryable,
        string $state,
        string $operation,
        array $request,
        ?string $orderReference = null,
        string $diagnostic = ''
    ): array {
        return $this->observeResult($this->failure($category, $retryable, $state, $orderReference), $operation, $request, null, $diagnostic);
    }

    /**
     * Report once at the boundary that owns the HTTP status. Business rejection is not an HTTP error.
     * @param array<string, mixed> $result
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function observeResult(array $result, string $operation, array $request, ?HttpResponse $response = null, string $diagnostic = '', array $secrets = []): array
    {
        $httpStatus = $response === null ? null : $response->getStatus();
        $category = $httpStatus !== null && $httpStatus >= 500 ? 'http_5xx'
            : ($httpStatus !== null && $httpStatus >= 400 ? 'http_4xx' : $this->telemetry->normalizedErrorCategory($result));
        if ($category !== null) {
            $surface = in_array($request['surface'] ?? null, ['classic', 'blocks', 'order_pay', 'server'], true)
                ? $request['surface'] : 'server';
            $source = $operation === 'create' ? 'sync_response' : $this->querySource;
            $cause = $this->diagnosticMessage !== '' ? $this->diagnosticMessage : $diagnostic;
            if ($response !== null) {
                $cause = ($cause !== '' ? $cause . '; ' : '') . SmartPixDiagnostic::response($response, $secrets);
            } elseif ($cause === '') {
                $cause = SmartPixDiagnostic::reason($result['error']['category'] ?? 'diagnostic_unavailable');
            }
            $this->telemetry->error(
                $source === 'notification' ? 'notification' : $operation,
                $category,
                $surface,
                $source,
                SmartPixDiagnostic::message($operation . '.' . $this->diagnosticStage . ': ' . $cause, $secrets)
            );
        }
        return $result;
    }

    private function normalizeOrder(array $data, ?string $reference, ?array $amount, ?string $attemptReference): array
    {
        $normalizer = new SmartPixOrderNormalizer();
        $result = $normalizer->normalize($data, $reference, $amount, $attemptReference);
        $this->diagnosticMessage = $normalizer->diagnosticMessage();
        return $result;
    }

    private function getBasePath(): string
    {
        return SmartPixEnvironment::resolve($this->store) === SmartPixEnvironment::HOMOLOGATION
            ? self::BETA_BASE_PATH : self::PRODUCTION_BASE_PATH;
    }

    private function getPlatformId(): string
    {
        return defined('MP_PLATFORM_ID') ? (string) constant('MP_PLATFORM_ID') : '';
    }

    private function mapClientErrorCategory(int $status): string
    {
        if ($status === 400 || $status === 422) {
            return 'validation';
        }

        if ($status === 401 || $status === 403) {
            return 'authentication';
        }

        if ($status === 409) {
            return 'conflict';
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $category, bool $retryable, string $state, ?string $orderReference = null): array
    {
        return [
            'outcome'         => 'failure',
            'state'           => $state,
            'order_reference' => $orderReference,
            'eligibility'     => null,
            'authorization'   => null,
            'payments'        => [],
            'error'           => [
                'category'  => $category,
                'code'      => 'SMART_PIX_' . strtoupper($category),
                'retryable' => $retryable,
            ],
        ];
    }

    private function isTimeout(TransportException $exception): bool
    {
        // WordPress usually exposes a generic transport code for cURL errors, so it
        // cannot establish a timeout without inspecting the sensitive raw message.
        return in_array($exception->getTransportCode(), ['timeout', 'request_timeout', 'http_request_timeout'], true);
    }

    /**
     * Only creation carries the buyer and cart facts in order_details.
     * @param array<string, mixed> $request
     */
    private function hasExactNormalizedRequestFields(array $request, bool $create): bool
    {
        $expected = [
            'surface',
            'country',
            'payment_contract',
            'attempt_reference',
            'order_reference',
            'amount',
            'refund_amount',
        ];
        if ($create) {
            $expected[] = 'order_details';
        }

        return $this->hasExactKeys($request, $expected);
    }

    /**
     * @param array<mixed>      $values
     * @param list<string>      $expected
     */
    private function hasExactKeys(array $values, array $expected): bool
    {
        $actual = array_keys($values);
        sort($expected);
        sort($actual);

        return $actual === $expected;
    }

    private function isKnownSurface($surface): bool
    {
        return in_array($surface, ['classic', 'blocks', 'order_pay', 'server'], true);
    }

    private function isOpaqueReference($reference): bool
    {
        return is_string($reference)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,127}$/D', $reference) === 1;
    }

    /**
     * @param array<string, string> $headers
     */
    private function hasRequiredAuthorization(array $headers): bool
    {
        return isset($headers['Authorization']) && $headers['Authorization'] !== 'Bearer ';
    }

    /**
     * @param array<string, string> $headers
     */
    private function hasRequiredIntegrationHeaders(array $headers, bool $includeProduct): bool
    {
        if (($headers['X-Platform-Id'] ?? '') === '') {
            return false;
        }

        return !$includeProduct || ($headers['X-Product-Id'] ?? '') !== '';
    }
}

<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use MercadoPago\Woocommerce\SmartPix\SmartPixExistingPixException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixCheckoutRecovery;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixDecimal;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixLegacyPaymentNotice;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixRepaymentGuard;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixEffectOwnership;
use MercadoPago\Woocommerce\Transactions\PixTransaction;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixAttemptRepository;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixPaymentProcessor;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixValidator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixOrderNormalizer;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationRequestId;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEventMetrics;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixRequestObservation;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixStatusEndpoint;
use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTerminalObservation;
use MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEnvironment;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Api\Http\WordPressHttpClient;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Hooks\Scripts;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class PixGateway extends AbstractGateway
{
    private ?WC_Order $smartPixThankYouOrder = null;
    private ?int $smartPixClassicThankYouOrderId = null;
    private array $renderedSmartPixThankYouOrders = [];

    protected const CHECKOUT_EXPIRATION_DATE_OPTION = 'expiration_date';
    protected const CHECKOUT_EXPIRATION_DATE_DEFAULT = '30 minutes';
    private const SMART_PIX_CHECKOUT_MARKER_FIELD = 'mp-smart-pix-selected';
    private const PIX_FLOW_CONVENTIONAL = 'conventional_pix';
    private const PIX_FLOW_SMART_TRANSFER = 'smart_transfer';
    private const SMART_PIX_ORDERS_HTTP_TIMEOUT_SECONDS = 10;
    private const SMART_PIX_ORDER_CREATION_METRIC = 'smart_pix_order_creation';
    private const SMART_PIX_CREATION_PENDING = 'pending';
    private const SMART_PIX_CREATION_UNEXPECTED_ERROR = 'unexpected_error';
    private const SMART_PIX_CREATION_RESULTS = [
        'pending', 'rejected', 'insufficient_balance', 'limit_exceeded', 'validation', 'authentication',
        'conflict', 'rate_limit', 'unavailable', 'timeout', 'invalid_response', 'linkage_mismatch',
        'persistence', 'unknown', 'unexpected_error',
    ];
    private const SMART_PIX_CREATION_RESULT_BY_BUYER_ERROR = [
        SmartPixBuyerError::REJECTED             => 'rejected',
        SmartPixBuyerError::INSUFFICIENT_BALANCE => 'insufficient_balance',
        SmartPixBuyerError::LIMIT_EXCEEDED       => 'limit_exceeded',
    ];
    private const SMART_PIX_STATUS_POLLING_INTERVAL_MS = 5000;
    private const SMART_PIX_STATUS_POLLING_MAX_DURATION_MS = SmartPixStatusEndpoint::POLLING_WINDOW_SECONDS * 1000;

    /**
     * @var OrderMeta
     */
    public $orderMeta;

    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-pix';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Pix_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_PixGateway';

    /**
     * @const
     */
    public const PIX_IMAGE_ENDPOINT = 'mp_pix_image';

    /**
     * @const
     */
    public const PIX_PAYMENT_STATUS_ENDPOINT = 'mp_pix_payment_status';

    /**
     * @const
     */
    public const PIX_STATUS_NONCE = 'mp_pix_polling_nonce';

    /**
     * PixGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        if (!$this->mercadopago->booted()) {
            return;
        }

        $this->adminTranslations = $this->mercadopago->adminTranslations->pixGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pixCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-pix-admin');
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = (float) $this->getActionableValue('gateway_discount', 0);
        $this->commission         = (float) $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'renderThankYouPage']);
        $this->registerSmartPixThankYouHooks();

        $this->mercadopago->hooks->order->registerEmailBeforeOrderTable([$this, 'renderOrderReceivedTemplate']);
        $this->mercadopago->hooks->order->registerOrderDetailsAfterOrderTable([$this, 'renderOrderReceivedTemplate']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);
        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::PIX_IMAGE_ENDPOINT, [$this, 'generatePixImage']);
        $this->mercadopago->hooks->endpoints->registerWCAjaxEndpoint(self::PIX_PAYMENT_STATUS_ENDPOINT, [$this, 'checkPixPaymentStatus']);
        $this->mercadopago->hooks->endpoints->registerWCAjaxEndpoint(SmartPixStatusEndpoint::ACTION, [$this, 'checkSmartPixOrderStatus']);
        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);

        $this->orderMeta = new OrderMeta();
        $this->paymentMethodName = self::ID;
    }

    public function getCheckoutName(): string
    {
        return 'checkout-pix';
    }

    /**
     * Register the MP JS SDK and immutable Smart Pix assets for MLB checkout surfaces.
     * The Smart Pix bundle remains a progressive enhancement; its load or integrity failure does
     * not remove the conventional Pix template.
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        parent::registerCheckoutScripts();

        if (($this->countryConfigs['site_id'] ?? '') !== Country::SITE_ID_MLB) {
            return;
        }

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_sdk',
            $this->mercadopago->helpers->url->getMercadoPagoSdkUrl()
        );

        $environment = SmartPixEnvironment::resolve($this->mercadopago->storeConfig) === SmartPixEnvironment::HOMOLOGATION
            ? Scripts::SMART_PIX_ENV_HOMOLOGATION
            : Scripts::SMART_PIX_ENV_PRODUCTION;

        // Without a local build on disk, fall back to the allow-listed CDN assets with SRI.
        if (!MP_SUPER_TOKEN_USE_BUNDLE && $this->mercadopago->hooks->scripts->hasSmartPixLocalCheckoutAssets()) {
            $this->mercadopago->hooks->scripts->registerSmartPixLocalCheckoutAssets();
            return;
        }

        $this->mercadopago->hooks->scripts->registerSmartPixCheckoutAssets($environment);
    }

    private ?string $smartPixCreationResult = null;

    private ?string $smartPixCreationRequestId = null;

    private array $smartPixMetricDetails = [];

    private ?SmartPixEventMetrics $smartPixEventMetrics = null;
    private ?SmartPixTelemetry $smartPixTelemetry = null;

    /**
     * Reports one Smart Pix creation result (with duration and surface) per Smart Pix submission.
     * A generic checkout marker identifies the flow without sending the selected account.
     *
     * @param $order_id
     * @return array
     * @throws Exception
     */
    public function process_payment($order_id): array
    {
        if (!$this->isSmartPixCheckoutRequest()) {
            return parent::process_payment($order_id);
        }

        $startedAt = microtime(true);
        $this->smartPixCreationResult = null;
        $this->smartPixCreationRequestId = null;
        $this->smartPixMetricDetails = [];
        $succeeded = false;

        try {
            $response  = parent::process_payment($order_id);
            $succeeded = ($response['result'] ?? null) === 'success';

            return $response;
        } finally {
            $durationSeconds = number_format(microtime(true) - $startedAt, 3, '.', '') . 's';

            $this->sendSmartPixMetric(
                self::SMART_PIX_ORDER_CREATION_METRIC,
                $this->smartPixCreationResult ?? ($succeeded ? self::SMART_PIX_CREATION_PENDING : self::SMART_PIX_CREATION_UNEXPECTED_ERROR),
                $durationSeconds,
                $this->smartPixCreationMetricDetails()
            );
        }
    }

    /** @return array<string, string> */
    private function smartPixCreationMetricDetails(): array
    {
        $details = $this->smartPixMetricDetails + ['surface' => $this->smartPixSurface()];
        $checkout = Form::sanitizedPostData();
        $sdkId = SmartPixMetricContext::identifier(is_array($checkout) ? ($checkout[SmartPixMetricContext::SDK_FIELD] ?? null) : null);
        if ($sdkId !== null && !isset($details['sdk_instance_id'])) {
            $details['sdk_instance_id'] = $sdkId;
        }
        if ($this->smartPixCreationRequestId !== null) {
            $details['request_id'] = $this->smartPixCreationRequestId;
        }

        return $details;
    }

    /** Records only a known creation result; the metric itself is sent once by process_payment. */
    public function processReturnFail(
        Exception $e,
        string $message,
        string $source,
        array $context = [],
        bool $notice = false
    ): array {
        if ($this->isSmartPixCheckoutRequest() && $this->smartPixCreationResult === null) {
            $this->smartPixCreationResult = $this->smartPixCreationResultFor($e);
        }

        return parent::processReturnFail($e, $message, $source, $context, $notice);
    }

    /** A Smart Pix rejection is an expected buyer outcome: a warning without a stacktrace. */
    protected function logCheckoutFailure(Exception $e, string $source, array $context = []): void
    {
        $buyerErrorCode = $this->smartPixBuyerErrorCode($e);

        if ($buyerErrorCode !== null && SmartPixBuyerError::isRejection($buyerErrorCode)) {
            $this->mercadopago->logs->file->warning("Smart Pix rejection: {$buyerErrorCode}", $source, $context);
            return;
        }

        parent::logCheckoutFailure($e, $source, $context);
    }

    private function smartPixBuyerErrorCode(Exception $error): ?string
    {
        if (!$error instanceof InvalidCheckoutDataException) {
            return null;
        }

        $buyerErrorCode = $error->getDetails()[SmartPixBuyerError::PAYMENT_DETAILS_KEY] ?? null;

        return is_string($buyerErrorCode) && SmartPixBuyerError::isCode($buyerErrorCode) ? $buyerErrorCode : null;
    }

    private function isSmartPixCheckoutRequest(): bool
    {
        $checkout = Form::sanitizedPostData();
        $marker   = is_array($checkout) ? ($checkout[self::SMART_PIX_CHECKOUT_MARKER_FIELD] ?? null) : null;

        return $marker === '1';
    }

    private function smartPixCreationResultFor(Exception $error): string
    {
        if (!$error instanceof InvalidCheckoutDataException) {
            return self::SMART_PIX_CREATION_UNEXPECTED_ERROR;
        }

        $buyerErrorCode = $this->smartPixBuyerErrorCode($error);
        if ($buyerErrorCode === null) {
            return 'validation';
        }

        return self::SMART_PIX_CREATION_RESULT_BY_BUYER_ERROR[$buyerErrorCode] ?? 'unknown';
    }

    /**
     * The account proof is an opaque provider token: sanitize_text_field() would strip percent
     * octets, tags or repeated spaces and break it, so it is read raw (bounded by the validator).
     *
     * @return mixed
     */
    private function readSmartPixToken(array $checkout)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout request.
        if (isset($_POST['mp-smart-pix-token']) && is_string($_POST['mp-smart-pix-token'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque provider token, validated by bounds downstream.
            return wp_unslash($_POST['mp-smart-pix-token']);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return $checkout['mp-smart-pix-token'] ?? null;
    }

    /** A decline keeps its buyer-facing class (insufficient balance, daily limit) as the creation result. */
    private static function smartPixFailureResult(string $buyerError, string $category): string
    {
        return $category === 'rejected'
            ? (self::SMART_PIX_CREATION_RESULT_BY_BUYER_ERROR[$buyerError] ?? 'rejected')
            : $category;
    }

    private function smartPixSurface(): string
    {
        if (is_checkout_pay_page()) {
            return 'order_pay';
        }

        return isset($_POST['wc-woo-mercado-pago-pix-new-payment-method']) ? 'blocks' : 'classic';
    }

    private function sendSmartPixMetric(string $name, string $value, string $message, array $details = []): void
    {
        try {
            $this->smartPixEventMetrics ??= SmartPixEventMetrics::fromPlugin($this->mercadopago, $this->datadog);
            $this->smartPixEventMetrics->send($name, $value, $message, $details);
        } catch (\Throwable $error) {
            // Observability is best-effort and must never change checkout behavior.
        }
    }

    public function formFields(): array
    {
        return $this->sellerHavePix()
            ? parent::formFields()
            : $this->sellerWithoutPixFields();
    }

    public function formFieldsMainSection(): array
    {
        return $this->sellerWithPixFields();
    }

    public function sellerHavePix(): bool
    {
        return !empty($this->mercadopago->sellerConfig->getCheckoutPixPaymentMethods());
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/pix-checkout.php',
            $this->getPaymentFieldsParams()
        );
    }

    /**
     * Get Payment Fields params
     *
     * @return array
     */
    public function getPaymentFieldsParams(): array
    {
        $amountAndCurrencyRatio = $this->getAmountAndCurrency();
        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'pix_template_title'               => $this->storeTranslations['pix_template_title'],
            'pix_template_subtitle'            => $this->storeTranslations['pix_template_subtitle'],
            'pix_template_alt'                 => $this->storeTranslations['pix_template_alt'],
            'pix_template_src'                 => $this->mercadopago->helpers->url->getImageAsset('checkouts/pix/pix'),
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'smart_pix_selected_account_text'  => $this->storeTranslations['smart_pix_selected_account_text'],
            'amount'                           => $amountAndCurrencyRatio['amount'],
            'smart_pix_amount'                 => $amountAndCurrencyRatio['amount'] === null
                ? null : SmartPixDecimal::canonical((string) $amountAndCurrencyRatio['amount']),
            'message_error_amount'             => $this->storeTranslations['message_error_amount'],
            'icon'                             => $this->icon,
            'smart_pix_mp_icon_src'            => $this->mercadopago->helpers->url->getImageAsset('icons/icon-mp'),
            'smart_pix_pix_icon_src'           => $this->mercadopago->helpers->url->getImageAsset('icons/pix-icon'),
            'smart_pix_bb_icon_src'            => $this->mercadopago->helpers->url->getImageAsset('icons/smart-pix-banco-do-brasil.svg'),
            'smart_pix_nubank_icon_src'        => $this->mercadopago->helpers->url->getImageAsset('icons/smart-pix-nubank.svg'),
        ];
    }

    public function proccessPaymentInternal($order): array
    {
        $checkout = Form::sanitizedPostData();

        $this->mercadopago->orderMetadata->markPaymentAsBlocks(
            $order,
            isset($_POST['wc-woo-mercado-pago-pix-new-payment-method']) ? "yes" : "no"
        );

        if (!filter_var($order->get_billing_email(), FILTER_VALIDATE_EMAIL)) {
            return $this->processReturnFail(
                new InvalidCheckoutDataException('Email not valid on ' . __METHOD__),
                'invalid_email',
                self::LOG_SOURCE,
                (array) $order,
                true
            );
        }

        $flow = $this->selectPixFlow($checkout);

        if ($flow === null) {
            return $this->rejectInvalidPixFlow();
        }

        if ($flow === self::PIX_FLOW_SMART_TRANSFER) {
            return $this->processSmartPix($order, $checkout);
        }

        // A linked Smart Pix Order may still settle: keep the buyer on it. An unanswered create is
        // preserved by endSmartPixAttempt() so a late Order is still reviewed.
        if ((new SmartPixRepaymentGuard($this->mercadopago->orderMetadata))->isProcessingSmartPixOrder($order)) {
            $message = $this->storeTranslations['smart_pix_processing_message'];
            wc_add_notice($message, 'notice');

            // Blocks reads `message` from the payment details; Classic shows the notice.
            return [
                'result' => 'failure',
                'message' => $message,
                'smart_pix_state' => 'processing',
                'smart_pix_message' => $message,
            ];
        }

        $this->mercadopago->orderMetadata->endSmartPixAttempt($order);

        $this->transaction = new PixTransaction($this, $order, $checkout);
        $response          = $this->transaction->createPayment();

        if (is_array($response) && array_key_exists('status', $response)) {
            return $this->verifyPixPaymentResponse($response, $order);
        }

        throw new ResponseStatusException('exception : Unable to process payment on ' . __METHOD__);
    }

    private function selectPixFlow(array $checkout): ?string
    {
        if (!array_key_exists(self::SMART_PIX_CHECKOUT_MARKER_FIELD, $checkout)) {
            return self::PIX_FLOW_CONVENTIONAL;
        }

        $selection = $checkout[self::SMART_PIX_CHECKOUT_MARKER_FIELD];

        if ($selection === '0') {
            return self::PIX_FLOW_CONVENTIONAL;
        }

        if ($selection === '1' && ($this->countryConfigs['site_id'] ?? '') === Country::SITE_ID_MLB) {
            return self::PIX_FLOW_SMART_TRANSFER;
        }

        return null;
    }

    /**
     * A Smart Pix intent always uses the Orders port. WooCommerce owns checkout authorization;
     * the validator additionally binds the proof to this server-owned order and Order Pay key.
     *
     * @param mixed $order Usually a WC_Order; anything else fails closed before the Orders port.
     * @param array $checkout
     *
     * @return array
     */
    protected function processSmartPix($order, array $checkout): array
    {
        // A selected Smart Pix account cannot silently fall back to Payments.
        // The Orders environment is selected server-side and does not change WooCommerce mode.
        if (!$order instanceof WC_Order) {
            return $this->processSmartPixFailure(SmartPixBuyerError::PROCESSING_FAILED, 'validation');
        }

        $this->smartPixMetricDetails = SmartPixMetricContext::forOrder($order);
        $sdkId = SmartPixMetricContext::identifier($checkout[SmartPixMetricContext::SDK_FIELD] ?? null);
        if ($sdkId !== null && !isset($this->smartPixMetricDetails['sdk_instance_id'])) {
            $this->smartPixMetricDetails['sdk_instance_id'] = $sdkId;
        }
        $context = new SmartPixMetricContext();
        $context->observeSdkInstance($this->smartPixMetricDetails['sdk_instance_id'] ?? null);
        $this->smartPixTelemetry = SmartPixTelemetry::fromPlugin($this->mercadopago, $context);
        $surface = $this->smartPixSurface();
        $orderKey = null;
        if ($surface === 'order_pay') {
            $providedKey = $checkout['key'] ?? null;
            if ($providedKey === null && isset($_GET['key']) && is_string($_GET['key'])) {
                $providedKey = sanitize_text_field(wp_unslash($_GET['key']));
            }
            $orderKey = is_string($providedKey) ? sanitize_text_field(wp_unslash($providedKey)) : null;
        }

        // AbstractGateway writes unrelated order metadata immediately before this call. The
        // attempt repository reloads metadata, so persist those writes before entering it.
        $order->save();
        $client = $this->createSmartPixOrderClient();
        global $wpdb;
        try {
            $result = (new SmartPixPaymentProcessor(
                new SmartPixValidator($this->mercadopago->sellerConfig, null, null, $this->mercadopago->orderMetadata),
                new SmartPixAttemptRepository($this->mercadopago->orderMetadata),
                $client,
                $this->mercadopago->orderMetadata,
                $this->smartPixTelemetry,
                new SmartPixCreationStore($wpdb),
                static function (WC_Order $creatingOrder) use ($checkout, $surface): void {
                    SmartPixMetricContext::capture($creatingOrder, $checkout[SmartPixMetricContext::SDK_FIELD] ?? null);
                    if ($surface === 'classic') {
                        SmartPixCheckoutRecovery::bindSession($creatingOrder);
                    }
                }
            ))->process($order, $order->get_id(), $surface, [
                'token' => $this->readSmartPixToken($checkout),
                'openFinanceAccountId' => $checkout['mp-smart-pix-open-finance-account-id'] ?? null,
            ], wp_generate_uuid4(), $orderKey);
        } catch (\Throwable $failure) {
            // The processor rethrows a failure of the create call only before admission, but a PHP
            // Error after the call returned (for example while linking the answered Order) also lands
            // here. The admission marker, written right before the POST, tells the two apart for the
            // log; the buyer gets the same generic error. A PHP Error would otherwise escape
            // AbstractGateway's Exception handler as an HTTP 500.
            $phase = SmartPixCreationStore::hasStarted($order) ? 'after' : 'before';
            $this->mercadopago->logs->file->error(
                'Smart Pix creation failed ' . $phase . ' dispatch: ' . get_class($failure),
                self::LOG_SOURCE
            );
            $this->smartPixTelemetry->error('create', 'internal_error', $surface, 'sync_response', SmartPixDiagnostic::exception($failure));
            return $this->processSmartPixFailure(SmartPixBuyerError::PROCESSING_FAILED, self::SMART_PIX_CREATION_UNEXPECTED_ERROR);
        } finally {
            if ($client instanceof SmartPixRequestObservation) {
                try {
                    $this->smartPixMetricDetails = array_merge($this->smartPixMetricDetails, $client->requestDetails());
                } catch (\Throwable $telemetryFailure) {
                    // Optional diagnostics cannot replace the payment result.
                }
            }
            if ($client instanceof SmartPixCreationRequestId) {
                try {
                    $this->smartPixCreationRequestId = $client->creationRequestId();
                } catch (\Throwable $telemetryFailure) {
                    // A missing request ID never changes the checkout result.
                }
            }
        }

        $reference = $result['order_reference'] ?? null;
        $state = $result['state'] ?? null;
        if (($result['outcome'] ?? null) === 'success' && in_array($state, ['processing', 'terminal_success'], true)) {
            $this->smartPixMetricDetails['payment_status'] = $state === 'terminal_success' ? 'approved' : 'pending';
        }
        $statusDetail = $result['observed']['payment_status_detail'] ?? null;
        if (is_string($statusDetail) && preg_match('/\A[a-z0-9_]{1,64}\z/D', $statusDetail) === 1) {
            $this->smartPixMetricDetails['payment_status_detail'] = $statusDetail;
            if ($state === 'terminal_failure') {
                $this->smartPixMetricDetails['reason'] = $statusDetail;
            }
        }
        if (is_string($reference) && $reference !== '' && in_array($state, ['processing', 'terminal_success', 'terminal_failure'], true)) {
            SmartPixMetricContext::capture($order, $checkout[SmartPixMetricContext::SDK_FIELD] ?? null);
            $this->smartPixMetricDetails = array_merge($this->smartPixMetricDetails, SmartPixMetricContext::forOrder($order));
            if ($state !== 'processing' && !empty($result['payments'])) {
                global $wpdb;
                (new SmartPixStateApplicator(
                    $this->mercadopago->orderMetadata,
                    new SmartPixStateReducer(),
                    $this->mercadopago->sellerConfig,
                    new SmartPixEffectOwnership($wpdb),
                    $this->smartPixTelemetry,
                    $this->mercadopago->smartPixNativeEffects,
                    [$this->mercadopago->orderStatus, 'addSmartPixStatusNotes']
                ))->apply($order->get_id(), $this->mercadopago->orderMetadata->getIdempotencyKey($order), $result, 'sync_response', $surface);
            }
            if ($state !== 'terminal_failure') {
                if ($state === 'processing') {
                    try {
                        $this->mercadopago->orderStatus->addSmartPixPendingNote($order);
                    } catch (\Throwable $noteFailure) {
                        $this->mercadopago->logs->file->warning('Smart Transfer pending note could not be recorded.', self::LOG_SOURCE);
                    }
                }
                $this->smartPixCreationResult = self::SMART_PIX_CREATION_PENDING;
                return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
            }
        }

        $category = $result['error']['category'] ?? 'unknown';
        $buyerError = $category === 'rejected'
            ? SmartPixBuyerError::fromRejectionStatusDetail(SmartPixOrderNormalizer::rejectionStatusDetail($result['error']))
            : SmartPixBuyerError::PROCESSING_FAILED;
        $messageKey = $category === 'validation' && ($result['error']['code'] ?? null) === SmartPixExistingPixException::ERROR_CODE
            ? 'smart_pix_existing_pix' : $buyerError;
        return $this->processSmartPixFailure($buyerError, self::smartPixFailureResult($buyerError, $category), $messageKey);
    }

    /**
     * The stable code reaches the Smart Pix bundle as notice data (Classic, Order Pay)
     * and as Store API payment details (Blocks), so it can choose where to show the message.
     */
    /**
     * @param string $creationResult Normalized Orders/processor category; a processor failure passes the processor's error category.
     * @param string|null $messageKey Server-selected translation key; the bundle's stable error code stays unchanged.
     */
    private function processSmartPixFailure(string $buyerErrorCode, string $creationResult = 'unknown', ?string $messageKey = null): array
    {
        $this->smartPixCreationResult = in_array($creationResult, self::SMART_PIX_CREATION_RESULTS, true)
            ? $creationResult
            : 'unknown';
        $response = $this->processReturnFail(
            new InvalidCheckoutDataException(
                $buyerErrorCode,
                0,
                null,
                [SmartPixBuyerError::PAYMENT_DETAILS_KEY => $buyerErrorCode]
            ),
            $messageKey ?? $buyerErrorCode,
            self::LOG_SOURCE
        );

        $this->mercadopago->helpers->notices->storeNotice(
            $response['message'],
            'error',
            [SmartPixBuyerError::NOTICE_DATA_KEY => $buyerErrorCode]
        );
        $response[SmartPixBuyerError::PAYMENT_DETAILS_KEY] = $buyerErrorCode;

        return $response;
    }

    private function rejectInvalidPixFlow(): array
    {
        return $this->processReturnFail(
            new InvalidCheckoutDataException('invalid_pix_flow'),
            'smart_pix_unavailable',
            self::LOG_SOURCE,
            [],
            true
        );
    }

    /**
     * Verify and returns response for pix payment
     *
     * @param $response
     * @param $order
     *
     * @return array
     * @throws RejectedPaymentException
     * @throws ResponseStatusException
     */
    private function verifyPixPaymentResponse($response, $order): array
    {
        $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, ['id' => $response['id']]);

        $this->handleWithRejectPayment($response);

        if (
            $response['status'] === 'pending' &&
            in_array($response['status_detail'], ['pending_waiting_payment', 'pending_waiting_transfer'])
        ) {
            $this->mercadopago->helpers->cart->emptyCart();
            $this->mercadopago->hooks->order->setPixMetadata($this, $order, $response);
            $this->mercadopago->hooks->order->addOrderNote($order, $this->storeTranslations['customer_not_paid']);

            $urlReceived = $order->get_checkout_order_received_url();

            $description = "
                <div style='text-align: justify;'>
                <p>{$this->storeTranslations['congrats_title']}</p>
                <small>{$this->storeTranslations['congrats_subtitle']}</small>
                </div>
            ";

            $this->mercadopago->hooks->order->addOrderNote($order, $description, 1);

            return [
                'result'   => 'success',
                'redirect' => $urlReceived,
            ];
        }
        throw new ResponseStatusException('exception : Unable to process payment on ' . __METHOD__);
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;
        return $mercadopago->helpers->country->getPluginDefaultCountry() === Country::COUNTRY_CODE_MLB;
    }

    /**
     * Mount fields for seller configure Pix
     *
     * @return array
     */
    private function sellerWithPixFields(): array
    {
        return [
            static::CHECKOUT_EXPIRATION_DATE_OPTION => [
                'type'        => 'select',
                'title'       => $this->adminTranslations['expiration_date_title'],
                'description' => $this->adminTranslations['expiration_date_description'],
                'default'     => static::CHECKOUT_EXPIRATION_DATE_DEFAULT,
                'options'     => [
                    '15 minutes' => $this->adminTranslations['expiration_date_options_15_minutes'],
                    '30 minutes' => $this->adminTranslations['expiration_date_options_30_minutes'],
                    '60 minutes' => $this->adminTranslations['expiration_date_options_60_minutes'],
                    '12 hours'   => $this->adminTranslations['expiration_date_options_12_hours'],
                    '24 hours'   => $this->adminTranslations['expiration_date_options_24_hours'],
                    '2 days'     => $this->adminTranslations['expiration_date_options_2_days'],
                    '3 days'     => $this->adminTranslations['expiration_date_options_3_days'],
                    '4 days'     => $this->adminTranslations['expiration_date_options_4_days'],
                    '5 days'     => $this->adminTranslations['expiration_date_options_5_days'],
                    '6 days'     => $this->adminTranslations['expiration_date_options_6_days'],
                    '7 days'     => $this->adminTranslations['expiration_date_options_7_days'],
                ]
            ],
            'currency_conversion' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['currency_conversion_title'],
                'subtitle'     => $this->adminTranslations['currency_conversion_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['currency_conversion_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['currency_conversion_descriptions_disabled'],
                ],
            ],
            'card_info_helper' => [
                'type'  => 'title',
                'value' => '',
            ],
            'card_info' => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_info_title'],
                    'subtitle'    => $this->adminTranslations['card_info_subtitle'],
                    'button_text' => $this->adminTranslations['card_info_button_text'],
                    'button_url'  => $this->links['mercadopago_pix'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_blank',
                ]
            ],
            'advanced_configuration_title' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_subtitle'],
                'class' => 'mp-small-text',
            ],
        ];
    }

    /**
     * Mount fields to show the seller how to activate Pix
     *
     * @return array
     */
    private function sellerWithoutPixFields(): array
    {
        if ($this->mercadopago->helpers->url->getCurrentSection() == $this->id) {
            $this->mercadopago->helpers->notices->adminNoticeMissPix();
        }

        $stepsContent = $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/settings/steps.php',
            [
                'title'             => $this->adminTranslations['steps_title'],
                'step_one_text'     => $this->adminTranslations['steps_step_one_text'],
                'step_two_text'     => $this->adminTranslations['steps_step_two_text'],
                'step_three_text'   => $this->adminTranslations['steps_step_three_text'],
                'observation_one'   => $this->adminTranslations['steps_observation_one'],
                'observation_two'   => $this->adminTranslations['steps_observation_two'],
                'button_about_pix'  => $this->adminTranslations['steps_button_about_pix'],
                'observation_three' => $this->adminTranslations['steps_observation_three'],
                'link_title_one'    => $this->adminTranslations['steps_link_title_one'],
                'link_url_one'      => $this->links['mercadopago_pix'],
                'link_url_two'      => $this->links['mercadopago_support'],
            ]
        );

        return [
            'header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'steps_content' => [
                'title' => $stepsContent,
                'type'  => 'title',
                'class' => 'mp_title_checkout',
            ],
        ];
    }

    /**
     * Generate pix image with gd_extension and fallback
     *
     * @return void
     */
    public function generatePixImage(): void
    {
        $metricDetails = [
            'site_id' => $this->mercadopago->sellerConfig->getSiteId(),
            'environment' => $this->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
            'cust_id' => $this->mercadopago->sellerConfig->getCustIdFromAT(),
        ];

        $orderId = Form::sanitizedGetData('id');
        if (!$orderId) {
            $this->datadog->sendEvent('pix_qr_access', 'missing_order_id', null, 'pix', $metricDetails);
            $this->mercadopago->helpers->images->getErrorImage();
            return;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->datadog->sendEvent('pix_qr_access', 'order_not_found', null, 'pix', $metricDetails);
            $this->mercadopago->helpers->images->getErrorImage();
            return;
        }

        $orderKey = Form::sanitizedGetData('key');
        if (!$orderKey || $order->get_order_key() !== $orderKey) {
            $reason = !$orderKey ? 'missing_order_key' : 'invalid_order_key';
            $this->datadog->sendEvent('pix_qr_access', $reason, null, 'pix', $metricDetails);
            $this->mercadopago->helpers->images->getErrorImage();
            return;
        }

        $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);
        if (is_array($qrCodeBase64)) {
            $qrCodeBase64 = array_pop($qrCodeBase64);
        }

        if (!$qrCodeBase64) {
            $this->datadog->sendEvent('pix_qr_access', 'qr_not_found', null, 'pix', $metricDetails);
            $this->mercadopago->helpers->images->getErrorImage();
            return;
        }

        $this->datadog->sendEvent('pix_qr_access', 'authorized', null, 'pix', $metricDetails);
        $this->mercadopago->helpers->images->getBase64Image($qrCodeBase64);
    }

    /**
     * Get pix order received template
     *
     * @param $order
     *
     * @return void
     */
    public function renderOrderReceivedTemplate($order): void
    {
        $pixOn   = (array) $this->mercadopago->orderMetadata->getPixOnMeta($order);
        $pixOn   = (bool) array_pop($pixOn);

        if ($pixOn && $order->get_status() === 'pending') {
            $qrCode = $this->mercadopago->orderMetadata->getPixQrCodeMeta($order);
            $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);
            $expirationDate = $this->mercadopago->orderMetadata->getPixExpirationDateData($order);

            if (is_array($qrCode)) {
                $qrCode = array_pop($qrCode);
            }

            if (is_array($qrCodeBase64)) {
                $qrCodeBase64 = array_pop($qrCodeBase64);
            }

            if (is_array($expirationDate)) {
                $expirationDate = array_pop($expirationDate);
            }

            $siteUrl       = $this->mercadopago->hooks->options->get('siteurl');
            $imageEndpoint = self::PIX_IMAGE_ENDPOINT;

            $qrCodeImage = !in_array('gd', get_loaded_extensions(), true)
                ? "data:image/jpeg;base64,$qrCodeBase64"
                : "$siteUrl?wc-api=$imageEndpoint&id={$order->get_id()}&key={$order->get_order_key()}";

            $this->mercadopago->hooks->scripts->registerStoreStyle(
                'mp_pix_image',
                $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-image')
            );

            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/order/pix-order-received-image.php',
                [
                    'qr_code'              => $qrCode,
                    'expiration_date'      => $expirationDate,
                    'expiration_date_text' => $this->storeTranslations['expiration_date_text'],
                    'qr_code_image'        => $qrCodeImage,
                ]
            );
        }
    }

    public function registerApprovedPaymentStyles(): void
    {
        $this->mercadopago->hooks->scripts->registerStoreStyle(
            'mp_pix_appproved',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-approved')
        );
    }

    public function registerSmartPixThankYouHooks(): void
    {
        add_action('woocommerce_before_thankyou', [$this, 'prepareSmartPixThankYou']);
        add_action('woocommerce_before_template_part', [$this, 'prepareSmartPixThankYouTemplate'], 10, 4);
        add_action('woocommerce_after_template_part', [$this, 'renderSmartPixAfterOrderReceived'], 10, 4);
        add_filter('render_block_woocommerce/order-confirmation-status', [$this, 'appendSmartPixAfterOrderStatus']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'filterSmartPixThankYouActions'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'registerSmartPixThankYouStyles']);
    }

    /** Hide native actions only on this payment's receipt; financial guards remain independent. */
    public function filterSmartPixThankYouActions(array $actions, WC_Order $order): array
    {
        if (!is_order_received_page() || $order->get_payment_method() !== self::ID || !$this->isSmartPixOrder($order)) {
            return $actions;
        }

        unset($actions['pay'], $actions['cancel']);
        return $actions;
    }

    public function registerSmartPixThankYouStyles(): void
    {
        if (is_order_received_page()) {
            $this->mercadopago->hooks->scripts->registerStoreStyle(
                'mp_smart_pix_status',
                $this->mercadopago->helpers->url->getCssAsset('public/mp-smart-pix-status')
            );
        }
    }

    /** Identify the Classic template without rendering before WooCommerce's authorized hook. */
    public function prepareSmartPixThankYouTemplate(string $templateName, string $templatePath, string $located, array $args): void
    {
        if ($templateName === 'checkout/thankyou.php') {
            $order = $args['order'] ?? null;
            $this->smartPixClassicThankYouOrderId = $order instanceof WC_Order ? $order->get_id() : null;
        }
    }

    /** WooCommerce invokes this only after its native order-access checks, including in Blocks. */
    public function prepareSmartPixThankYou($orderId): void
    {
        $order = wc_get_order($orderId);
        $this->smartPixThankYouOrder = $order instanceof WC_Order && $this->isSmartPixOrder($order) ? $order : null;
        if (
            $this->smartPixThankYouOrder !== null && $this->smartPixClassicThankYouOrderId === $this->smartPixThankYouOrder->get_id() &&
            $this->smartPixThankYouOrder->get_status() === 'failed'
        ) {
            // Failed Classic orders have no order-received subtitle: precede their native notice/actions.
            $this->renderSmartPixPendingPage($this->smartPixThankYouOrder);
            $this->smartPixThankYouOrder = null;
        }
    }

    /** Classic: output immediately after the native subtitle, before the order overview. */
    public function renderSmartPixAfterOrderReceived(string $templateName, string $templatePath, string $located, array $args): void
    {
        if ($templateName === 'checkout/thankyou.php') {
            $this->smartPixClassicThankYouOrderId = null;
            $this->smartPixThankYouOrder = null;
            return;
        }
        if (
            $templateName === 'checkout/order-received.php' && $this->smartPixThankYouOrder !== null &&
            ($args['order'] ?? null) instanceof WC_Order &&
            $args['order']->get_id() === $this->smartPixThankYouOrder->get_id()
        ) {
            $this->renderSmartPixPendingPage($this->smartPixThankYouOrder);
            $this->smartPixThankYouOrder = null;
        }
    }

    /** Blocks: append after the complete native heading/subtitle, using its authorized context. */
    public function appendSmartPixAfterOrderStatus(string $content): string
    {
        $order = $this->smartPixThankYouOrder;
        $this->smartPixThankYouOrder = null;
        if ($order === null || $content === '') {
            return $content;
        }

        ob_start();
        try {
            $this->renderSmartPixPendingPage($order);
            $status = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if ($order->get_status() === 'failed' && $status !== '') {
            // WooCommerce's failed status block puts its notice/actions immediately after the h1.
            // Insert only our HTML, retaining the native heading, notice, links and attributes verbatim.
            $headingEnd = stripos($content, '</h1>');
            if ($headingEnd !== false) {
                $offset = $headingEnd + strlen('</h1>');
                return substr($content, 0, $offset) . $status . substr($content, $offset);
            }
        }

        return $content . $status;
    }

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderThankYouPage($order_id): void
    {
        $order = wc_get_order($order_id);

        if ($order instanceof WC_Order && $this->isSmartPixOrder($order)) {
            $this->renderSmartPixPendingPage($order);
            return;
        }

        $isPixPaymentApproved = $this->orderMeta->get($order, 'pix_payment_approved');

        // to avoid pix steps exibition on page reloads
        if ($isPixPaymentApproved) {
            $this->renderPixPaymentApprovedTemplate();
            return;
        }

        $transactionAmount = (float) $this->mercadopago->orderMetadata->getTransactionAmountMeta($order);
        $transactionAmount = Numbers::format($transactionAmount);

        $qrCode       = $this->mercadopago->orderMetadata->getPixQrCodeMeta($order);
        $qrCodeBase64 = $this->mercadopago->orderMetadata->getPixQrBase64Meta($order);

        if (empty($qrCodeBase64) && empty($qrCode)) {
            return;
        }

        $this->mercadopago->hooks->scripts->registerStoreStyle(
            'mp_pix_thankyou',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-pix-thankyou')
        );

        $this->registerPixPoolingScript($order);

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/pix-order-received.php',
            [
                'img_pix'             => $this->mercadopago->helpers->url->getImageAsset('checkouts/pix/pix'),
                'amount'              => Numbers::formatWithCurrencySymbol($this->countryConfigs['currency_symbol'], $transactionAmount),
                'qr_base64'           => $qrCodeBase64,
                'title_purchase_pix'  => $this->storeTranslations['title_purchase_pix'],
                'title_how_to_pay'    => $this->storeTranslations['title_how_to_pay'],
                'step_one'            => $this->storeTranslations['step_one'],
                'step_two'            => $this->storeTranslations['step_two'],
                'step_three'          => $this->storeTranslations['step_three'],
                'step_four'           => $this->storeTranslations['step_four'],
                'text_amount'         => $this->storeTranslations['text_amount'],
                'text_scan_qr'        => $this->storeTranslations['text_scan_qr'],
                'text_time_qr_one'    => $this->storeTranslations['expiration_date_text'],
                'qr_date_expiration'  => $this->getCheckoutExpirationDate(),
                'text_description_qr' => $this->storeTranslations['text_description_qr'],
                'qr_code'             => $qrCode,
                'text_button'         => $this->storeTranslations['text_button'],
            ]
        );
    }

    /**
     * Classic, Blocks and Order Pay all land here; the initial state comes from the server so a
     * reload after a terminal transition never shows the processing screen again.
     */
    private function renderSmartPixPendingPage(WC_Order $order): void
    {
        // The native placement and the gateway's compatibility hook must share one render/effect.
        if (isset($this->renderedSmartPixThankYouOrders[$order->get_id()])) {
            return;
        }
        $this->renderedSmartPixThankYouOrders[$order->get_id()] = true;

        $elapsedMs = (SmartPixStatusEndpoint::attemptAgeSeconds($this->mercadopago->orderMetadata, $order) ?? 0) * 1000;
        $reference = $this->mercadopago->orderMetadata->getPpOrderId($order);
        if (is_string($reference) && $reference !== '') {
            SmartPixCheckoutRecovery::acknowledge($order);
        }

        $this->mercadopago->hooks->scripts->registerStoreStyle(
            'mp_smart_pix_status',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-smart-pix-status')
        );

        $this->mercadopago->hooks->scripts->registerStoreScript(
            'wc_mercadopago_smart_pix_status',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/pix/mp-smart-pix-status'),
            [
                'order_id'         => $order->get_id(),
                'order_key'        => $order->get_order_key(),
                'ajax_url'         => \WC_AJAX::get_endpoint(SmartPixStatusEndpoint::ACTION),
                'nonce'            => wp_create_nonce(SmartPixStatusEndpoint::NONCE_ACTION),
                'interval_ms'      => self::SMART_PIX_STATUS_POLLING_INTERVAL_MS,
                'max_duration_ms'  => self::SMART_PIX_STATUS_POLLING_MAX_DURATION_MS,
                // A reload continues the attempt's window instead of restarting it; 0 when unknown.
                'elapsed_ms'       => $elapsedMs,
            ]
        );

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/smart-pix-status.php',
            [
                'initial_state'           => SmartPixStatusEndpoint::publicStateFor($order),
                'initial_elapsed_ms'      => $elapsedMs,
                'initial_error'           => SmartPixStatusEndpoint::publicErrorFor($order, $this->mercadopago->orderMetadata),
                'processing_title'        => $this->storeTranslations['smart_pix_status_processing_title'],
                'processing_description'  => $this->storeTranslations['smart_pix_status_processing_description'],
                'finalizing_description'  => $this->storeTranslations['smart_pix_status_finalizing_description'],
                'delayed_description'     => $this->storeTranslations['smart_pix_status_delayed_description'],
                'waiting_description'     => $this->storeTranslations['smart_pix_status_waiting_description'],
                'long_wait_description'   => $this->storeTranslations['smart_pix_status_long_wait_description'],
                'timeout_description'     => $this->storeTranslations['smart_pix_status_timeout_description'],
                'approved_title'          => $this->storeTranslations['smart_pix_status_approved_title'],
                'approved_description'    => $this->storeTranslations['smart_pix_status_approved_description'],
                'rejected_title'          => $this->storeTranslations['smart_pix_status_rejected_title'],
                'rejected_description'    => $this->storeTranslations['smart_pix_status_rejected_description'],
                'insufficient_title'      => $this->storeTranslations['smart_pix_status_insufficient_title'],
                'limit_title'             => $this->storeTranslations['smart_pix_status_limit_title'],
                'alternative_description' => $this->storeTranslations['smart_pix_status_alternative_description'],
                'new_purchase_label'      => $this->storeTranslations['smart_pix_status_new_purchase'],
                'new_purchase_url'        => wc_get_page_permalink('shop'),
                'approved_icon'           => $this->mercadopago->helpers->url->getImageAsset('icons/smart-pix-approved.svg'),
                'rejected_icon'           => $this->mercadopago->helpers->url->getImageAsset('icons/smart-pix-rejected.svg'),
                'info_icon'               => $this->mercadopago->helpers->url->getImageAsset('icons/smart-pix-info.svg'),
                'cancelled_title'         => $this->storeTranslations['smart_pix_status_cancelled_title'],
                'cancelled_description'   => $this->storeTranslations['smart_pix_status_cancelled_description'],
            ]
        );

        $this->recordSmartPixTerminalObservation($order);
    }

    /** A page first rendered already terminal never polls, so its outcome is only counted here. */
    private function recordSmartPixTerminalObservation(WC_Order $order): void
    {
        SmartPixTerminalObservation::fromPlugin($this->mercadopago, 'thankyou', [], $this->datadog)->record($order);
    }

    private function isSmartPixOrder(WC_Order $order): bool
    {
        $metadata = $this->mercadopago->orderMetadata;
        if ($metadata->getPaymentContract($order) !== OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1) {
            return false;
        }

        $reference = $metadata->getPpOrderId($order);

        return (is_string($reference) && $reference !== '') || SmartPixCreationStore::hasStarted($order);
    }

    public function checkSmartPixOrderStatus(): void
    {
        $this->createSmartPixStatusEndpoint()->handle();
    }

    /** Built per request: only the status action pays for this composition. */
    protected function createSmartPixStatusEndpoint(): SmartPixStatusEndpoint
    {
        global $wpdb;

        $metadata = $this->mercadopago->orderMetadata;
        $seller   = $this->mercadopago->sellerConfig;
        $this->smartPixTelemetry = SmartPixTelemetry::fromPlugin($this->mercadopago);

        return new SmartPixStatusEndpoint(
            new SmartPixOrderQuery($this->createSmartPixOrderClient(), $metadata, $seller, $this->smartPixTelemetry),
            new SmartPixStateApplicator(
                $metadata,
                new SmartPixStateReducer(),
                $seller,
                new SmartPixEffectOwnership($wpdb),
                $this->smartPixTelemetry,
                $this->mercadopago->smartPixNativeEffects,
                [$this->mercadopago->orderStatus, 'addSmartPixStatusNotes']
            ),
            $metadata,
            function (string $reason): void {
                $this->sendSmartPixMetric('smart_pix_status_access', $reason, '');
            },
            function (string $name, string $value, string $message, array $details = []): void {
                $this->sendSmartPixMetric($name, $value, $message, $details);
            },
            new SmartPixStatusLock(),
            new SmartPixStatusLock(null, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX)
        );
    }

    protected function createSmartPixOrderClient(): SmartPixOrderClient
    {
        return new RealSmartPixOrderClient(
            new WordPressHttpClient(Requester::BASEURL_MP, self::SMART_PIX_ORDERS_HTTP_TIMEOUT_SECONDS),
            $this->mercadopago->sellerConfig,
            $this->mercadopago->storeConfig,
            $this->smartPixTelemetry
        );
    }

    /**
     * Register pooling script
     * @param WC_Order $order
     */
    public function registerPixPoolingScript($order): void
    {
        if ($order->get_status() === 'pending') {
            $scriptUrl = $this->mercadopago->helpers->url->getJsAsset('checkouts/pix/mp-pix-pooling');
            $this->mercadopago->hooks->scripts->registerStoreScript(
                'wc_mercadopago_pix_pooling',
                $scriptUrl,
                [
                    'order_id'  => $order->get_id(),
                    'order_key' => $order->get_order_key(),
                    'ajax_url'  => \WC_AJAX::get_endpoint(self::PIX_PAYMENT_STATUS_ENDPOINT),
                    'nonce'     => wp_create_nonce(self::PIX_STATUS_NONCE)
                ]
            );
        }
    }

    /**
     * Render PIX payment approved template
     *
     * @param WC_Order $order
     * @param array $paymentData
     *
     * @return void
     */
    public function renderPixPaymentApprovedTemplate(): void
    {
        $this->registerApprovedPaymentStyles();
        // to do add texts to array
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/order/pix-payment-approved.php',
            [
                'approved_template_title' => $this->storeTranslations['approved_template_title'],
                'approved_template_description' => $this->storeTranslations['approved_template_description'],
            ]
        );
    }

    /**
     * Check PIX payment status via AJAX
     *
     * @return void
     */
    public function checkPixPaymentStatus(): void
    {
        try {
            $this->validateNonce();
            $order = $this->getOrderFromRequest();
            $paymentId = $this->getPaymentIdFromOrder($order);
            $paymentData = $this->fetchPaymentData($paymentId);
            $status = $paymentData['status'] ?? null;

            $this->handlePaymentStatus($status, $paymentData, $order);
        } catch (Exception $e) {
            throw new ResponseStatusException('Unable to check pix payment status: ' . $e->getMessage());
        }
    }

    /**
     * Validate nonce for security
     *
     * @return void
     * @throws Exception
     */
    private function validateNonce(): void
    {
        $nonce = Form::sanitizedPostData('nonce');
        if (!wp_verify_nonce($nonce, self::PIX_STATUS_NONCE)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            throw new Exception('Invalid nonce');
        }
    }

    /**
     * Get order from request data
     *
     * @return \WC_Order
     * @throws Exception
     */
    private function getOrderFromRequest(): \WC_Order
    {
        $orderId  = Form::sanitizedPostData('order_id');
        $orderKey = Form::sanitizedPostData('order_key');
        $order    = wc_get_order($orderId);

        if (!$order) {
            $this->rejectPixStatusRequest('order_not_found');
        }

        $orderKey = is_string($orderKey) ? $orderKey : '';

        if (!$orderKey || !hash_equals($order->get_order_key(), $orderKey)) {
            $this->rejectPixStatusRequest(!$orderKey ? 'missing_order_key' : 'invalid_order_key');
        }

        return $order;
    }

    /**
     * Reject a Pix status request with a uniform 403 so a caller cannot tell an
     * absent order from a wrong key; the real reason is recorded only server-side.
     *
     * @param string $reason
     * @return never
     * @throws Exception
     */
    private function rejectPixStatusRequest(string $reason): void
    {
        try {
            $this->datadog->sendEvent('pix_status_access', $reason, null, 'pix', [
                'site_id'     => $this->mercadopago->sellerConfig->getSiteId(),
                'environment' => $this->mercadopago->storeConfig->isTestMode() ? 'homol' : 'prod',
                'cust_id'     => $this->mercadopago->sellerConfig->getCustIdFromAT(),
            ]);
        } catch (\Throwable $telemetryFailure) {
            // Telemetry must never prevent the 403 that blocks the request.
        }
        wp_send_json_error(['message' => 'Forbidden'], 403);
        throw new Exception('Pix status request rejected: ' . $reason);
    }

    /**
     * Get payment ID from order metadata
     *
     * @param \WC_Order $order
     * @return string
     * @throws Exception
     */
    private function getPaymentIdFromOrder(\WC_Order $order): string
    {
        $paymentIds = $this->mercadopago->orderMetadata->getPaymentsIdMeta($order);

        if (empty($paymentIds)) {
            wp_send_json_error(['message' => 'No payment ID found'], 404);
            throw new Exception('No payment ID found');
        }

        return explode(',', $paymentIds)[0];
    }

    /**
     * Fetch payment data from MercadoPago API
     *
     * @param string $paymentId
     * @return array
     * @throws Exception
     */
    private function fetchPaymentData(string $paymentId): array
    {
        $headers = ['Authorization: Bearer ' . $this->mercadopago->sellerConfig->getCredentialsAccessToken()];
        $response = $this->mercadopago->helpers->requester->get("/v1/payments/$paymentId", $headers);

        if ($response->getStatus() !== 200) {
            wp_send_json_error(['message' => 'Failed to get payment status'], 500);
            throw new Exception('Failed to get payment status');
        }

        $paymentData = $response->getData();

        if (is_object($paymentData)) {
            $paymentData = (array) $paymentData;
        } elseif (!is_array($paymentData)) {
            $paymentData = [];
        }

        return $paymentData;
    }

    /**
     * Handle different payment statuses
     *
     * @param string|null $status
     * @param array $paymentData
     * @param \WC_Order $order
     * @return void
     */
    private function handlePaymentStatus(?string $status, array $paymentData, \WC_Order $order): void
    {
        switch ($status) {
            case 'approved':
                $this->handleApprovedPayment($order, $paymentData);
                break;
            case 'pending':
                $this->handlePendingPayment();
                break;
            default:
                $this->handleOtherStatus($status);
                break;
        }
    }

    /**
     * Handle approved payment status
     *
     * @param \WC_Order $order
     * @param array $paymentData
     * @return void
     */
    private function handleApprovedPayment(\WC_Order $order, array $paymentData): void
    {
        $paymentAlreadyProcessed = $this->orderMeta->get($order, 'pix_payment_approved');

        if ($paymentAlreadyProcessed) {
            wp_send_json_success([
                'status' => 'approved',
                'message' => 'Payment has already been processed',
            ]);
            return;
        }

        $applied = $this->mercadopago->orderStatus->processStatus('approved', $paymentData, $order, self::WEBHOOK_API_NAME);

        // A status that was not applied (e.g. an order owned by another payment contract)
        // must not be marked as processed nor reported as approved to the polling client.
        if (!$applied) {
            if ($this->mercadopago->orderStatus->isOwnedBySmartPixOrders($order)) {
                // Keep a trace of the approved conventional Pix payment without changing the order.
                $this->smartPixLegacyPaymentNotice()->record($order, $paymentData, 'pending_query');
            }
            wp_send_json_success([
                'status' => 'not_applied',
                'message' => 'Payment status was not applied',
            ]);
            return;
        }

        $this->orderMeta->update($order, 'pix_payment_approved', true);

        wp_send_json_success([
            'status' => 'approved',
            'message' => 'Payment successfully approved',
        ]);
    }

    /**
     * Handle pending payment status
     *
     * @return void
     */
    private function handlePendingPayment(): void
    {
        wp_send_json_success([
            'status' => 'pending',
            'message' => 'Payment pending'
        ]);
    }

    /**
     * Handle other payment statuses
     *
     * @param string|null $status
     * @return void
     */
    private function handleOtherStatus(?string $status): void
    {
        wp_send_json_success([
            'status' => $status,
            'message' => 'Payment status: ' . $status
        ]);
    }

    /** Composition seam for the Smart Pix legacy payment trace; tests replace it. */
    protected function smartPixLegacyPaymentNotice(): SmartPixLegacyPaymentNotice
    {
        return SmartPixLegacyPaymentNotice::fromPlugin();
    }

    /**
     * Gets expiration date option
     */
    public function getCheckoutExpirationDate(): string
    {
        return $this->get_option(static::CHECKOUT_EXPIRATION_DATE_OPTION, static::CHECKOUT_EXPIRATION_DATE_DEFAULT);
    }
}

<?php

namespace MercadoPago\Woocommerce\Transactions;

use Exception;
use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\MetricContext;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Helpers\NotificationType;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataAddress;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataUser;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataCpp;
use MercadoPago\Woocommerce\Entities\Metadata\ThemeMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use WC_Order;
use WC_Order_Item_Product;
use MercadoPago\Woocommerce\Helpers\Form;

abstract class AbstractTransaction
{
    private const REDACTED_LOG_VALUE = '[redacted]';

    private const SENSITIVE_LOG_FIELDS = [
        'access_token',
        'authorization',
        'card_number',
        'card_token',
        'client_secret',
        'cvv',
        'customer_id',
        'device_fingerprint',
        'doc_number',
        'document',
        'email',
        'external_reference',
        'first_name',
        'flow_id',
        'last_name',
        'name',
        'number',
        'platform_email',
        'registration_date',
        'session_id',
        'security_code',
        'surname',
        'token',
        'user_agent',
        'user_email',
        'user_id',
    ];

    private const SENSITIVE_LOG_CONTAINERS = [
        'address',
        'billing_address',
        'card',
        'mobile',
        'phone',
        'receiver_address',
        'user',
    ];

    private const SAFE_LOG_URL_QUERY_FIELDS = [
        'source_news',
        'wc-api',
    ];

    public WoocommerceMercadoPago $mercadopago;

    public array $transaction = [];

    protected string $transactionType = 'Transaction';

    public AbstractGateway $gateway;

    protected WC_Order $order;

    protected ?array $checkout = null;

    protected array $countryConfigs;

    protected float $ratio;

    protected float $orderTotal;

    protected array $listOfItems = [];

    /**
     * Abstract Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param WC_Order $order
     * @param array|null $checkout
     *
     * @throws Exception
     */
    public function __construct(AbstractGateway $gateway, WC_Order $order, ?array $checkout = null)
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->order       = $order;
        $this->gateway     = $gateway;
        $this->checkout    = $checkout;

        $this->ratio          = $this->mercadopago->helpers->currency->getRatio($gateway);
        $this->countryConfigs = $this->mercadopago->helpers->country->getCountryConfigs();

        $this->orderTotal     = 0;
    }

    protected function getCoreRequestContext(): CoreRequestContext
    {
        return new CoreRequestContext(
            $this->mercadopago->sellerConfig->getCredentialsAccessToken(),
            MP_PLATFORM_ID,
            Device::getDeviceProductId(),
            $this->mercadopago->storeConfig->getIntegratorId()
        );
    }

    public function logTransactionPayload(): void
    {
        $payload = $this->sanitizePayloadForLog($this->transaction);
        unset($payload['token']);

        $this->mercadopago->logs->file->info(
            $this->transactionType . ' payload',
            $this->gateway::LOG_SOURCE,
            $payload
        );
    }

    private function sanitizePayloadForLog(array $payload, bool $redactValues = false): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $field = strtolower((string) $key);
            $redactField = $redactValues
                || in_array($field, self::SENSITIVE_LOG_FIELDS, true)
                || in_array($field, self::SENSITIVE_LOG_CONTAINERS, true);

            if (is_object($value)) {
                $value = (array) $value;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayloadForLog($value, $redactField);
                continue;
            }

            if ($value === null || $value === '') {
                $sanitized[$key] = $value;
                continue;
            }

            if (substr($field, -4) === '_url' && is_string($value)) {
                $sanitized[$key] = $this->sanitizeUrlForLog($value);
                continue;
            }

            $sanitized[$key] = $redactField ? self::REDACTED_LOG_VALUE : $value;
        }

        return $sanitized;
    }

    private function sanitizeUrlForLog(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return self::REDACTED_LOG_VALUE;
        }

        $sanitized = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $sanitized .= ':' . $parts['port'];
        }

        $sanitized .= $parts['path'] ?? '';

        if (!empty($parts['query'])) {
            $queryFields = [];
            foreach (explode('&', $parts['query']) as $parameter) {
                $field = strstr($parameter, '=', true);
                $field = $field === false ? $parameter : $field;
                if ($field !== '' && preg_match('/^[A-Za-z0-9_.\[\]-]+$/', $field)) {
                    $value = strstr($parameter, '=');
                    $value = $value === false ? '' : substr($value, 1);
                    $queryFields[] = $field . '=' . (
                        in_array($field, self::SAFE_LOG_URL_QUERY_FIELDS, true)
                            ? $value
                            : self::REDACTED_LOG_VALUE
                    );
                }
            }

            if ($queryFields) {
                $sanitized .= '?' . implode('&', $queryFields);
            }
        }

        return $sanitized;
    }

    /**
     * Set common transaction
     *
     * @return void
     */
    public function setCommonTransaction(): void
    {
        $this->transaction['binary_mode']          = $this->getBinaryMode();
        $this->transaction['external_reference']   = $this->getExternalReference();
        $this->transaction['notification_url']     = $this->getNotificationUrl();
        $this->transaction['metadata']             = (array) $this->getInternalMetadata();
        $this->transaction['statement_descriptor'] = $this->mercadopago->storeConfig->getStoreName('Mercado Pago');
    }

    /**
     * Get notification url
     *
     * @return string|void
     */
    protected function getNotificationUrl()
    {
        $customDomain        = $this->mercadopago->storeConfig->getCustomDomain();
        $customDomainOptions = $this->mercadopago->storeConfig->getCustomDomainOptions();

        if (
            !empty($customDomain) &&
            !Strings::contains($customDomain, 'localhost') &&
            Url::isValid($customDomain)
        ) {
            return $customDomainOptions === 'yes'
                ? $customDomain . '?wc-api=' . $this->gateway::WEBHOOK_API_NAME . '&source_news=' . NotificationType::getNotificationType($this->gateway::WEBHOOK_API_NAME)
                : $customDomain;
        }

        if (empty($customDomain) && !Strings::contains(get_site_url(), 'localhost')) {
            $notificationUrl  = $this->mercadopago->woocommerce->api_request_url($this->gateway::WEBHOOK_API_NAME);
            $urlJoinCharacter = preg_match('#/wc-api/#', $notificationUrl) ? '?' : '&';

            return $notificationUrl . $urlJoinCharacter . 'source_news=' . NotificationType::getNotificationType($this->gateway::WEBHOOK_API_NAME);
        }
    }

    /**
     * Get binary mode
     *
     * @return bool
     */
    public function getBinaryMode(): bool
    {
        $binaryMode = $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'binary_mode', 'no');

        return $binaryMode !== 'no';
    }

    /**
     * Get external reference
     *
     * @return string
     */
    public function getExternalReference(): string
    {
        return $this->mercadopago->storeConfig->getStoreId() . $this->order->get_id();
    }

    /**
     * Get internal metadata
     *
     * @return PaymentMetadata
     */
    public function getInternalMetadata(): PaymentMetadata
    {
        $user                                    = $this->mercadopago->helpers->currentUser->getCurrentUser();
        $theme                                   = wp_get_theme();
        $metadata                                = new PaymentMetadata();
        $metadata->platform                      = MP_PLATFORM_ID;
        $metadata->platform_version              = $this->mercadopago->woocommerce->version;
        $metadata->module_version                = MP_VERSION;
        $metadata->php_version                   = PHP_VERSION;
        $metadata->site_id                       = strtolower($this->mercadopago->sellerConfig->getSiteId());
        $metadata->sponsor_id                    = $this->countryConfigs['sponsor_id'];
        $metadata->collector                     = $this->mercadopago->sellerConfig->getCollectorId();
        $metadata->test_mode                     = $this->mercadopago->storeConfig->isTestMode();
        $metadata->details                       = '';
        $metadata->seller_website                = $this->mercadopago->hooks->options->get('siteurl');
        $metadata->billing_address               = new PaymentMetadataAddress();
        $metadata->billing_address->zip_code     = str_replace('-', '', $this->mercadopago->orderBilling->getZipcode($this->order));
        $metadata->billing_address->street_name  = $this->mercadopago->orderBilling->getAddress1($this->order);
        $metadata->billing_address->city_name    = $this->mercadopago->orderBilling->getCity($this->order);
        $metadata->billing_address->state_name   = $this->mercadopago->orderBilling->getState($this->order);
        $metadata->billing_address->country_name = $this->mercadopago->orderBilling->getCountry($this->order);
        $metadata->user                          = new PaymentMetadataUser();
        $metadata->user->registered_user         = $user->exists() ? 'yes' : 'no';
        $metadata->user->user_email              = $user->exists() ? $user->user_email : null;
        $metadata->user->user_registration_date  = $user->exists() ? Date::formatGmDate($user->user_registered) : null;
        $metadata->cpp_extra                     = new PaymentMetadataCpp();
        $metadata->cpp_extra->platform_version   = $this->mercadopago->woocommerce->version;
        $metadata->cpp_extra->module_version     = MP_VERSION;
        $metadata->blocks_payment                = $this->mercadopago->orderMetadata->getPaymentBlocks($this->order);
        $metadata->settings                      = $this->mercadopago->metadataConfig->getGatewaySettings($this->gateway::ID);
        $metadata->auto_update                   = $this->mercadopago->sellerConfig->isAutoUpdate();
        $metadata->theme                         = new ThemeMetadata();
        $metadata->theme->theme_name             = $theme->get('Name');
        $metadata->theme->theme_version          = $theme->get('Version');
        $metadata->flow_id = $this->getCheckoutSessionData()['_mp_flow_id'] ?? null;

        $this->extendInternalMetadata($metadata);

        return $metadata;
    }

    private function getAndDeleteCheckoutSessionDataOnHelperSessionByOrderId(string $orderId)
    {
        $checkoutSessionData = $this->mercadopago->helpers->session->getSession('mp_checkout_session_' . $orderId);

        $this->mercadopago->helpers->session->deleteSession('mp_checkout_session_' . $orderId);

        return $checkoutSessionData;
    }

    public function getCheckoutSessionData()
    {
        $mercado_pago_checkout_session = $this->getAndDeleteCheckoutSessionDataOnHelperSessionByOrderId($this->order->get_id()) ?? [];

        if (isset($_POST['mercadopago_checkout_session'])) {
            // Classic Checkout
            $mercado_pago_checkout_session = array_merge($mercado_pago_checkout_session, Form::sanitizedPostData('mercadopago_checkout_session'));
        } else {
            // Blocks Checkout
            $mercado_pago_checkout_session = array_merge($mercado_pago_checkout_session, $this->gateway->processBlocksCheckoutData('mercadopago_checkout_session', Form::sanitizedPostData()));
        }

        return $mercado_pago_checkout_session;
    }

    /**
     * Extends internal metadata with payment-specific data.
     */
    abstract public function extendInternalMetadata(PaymentMetadata $internalMetadata): void;

    /**
     * Set additional shipments information
     *
     * @param array $shipments
     *
     * @return void
     */
    public function setShipmentsTransaction(array &$shipments): void
    {
        $shipments['receiver_address']['street_name'] = $this->mercadopago->orderShipping->getAddress1($this->order);
        $shipments['receiver_address']['zip_code']    = $this->mercadopago->orderShipping->getZipcode($this->order);
        $shipments['receiver_address']['city']        = $this->mercadopago->orderShipping->getCity($this->order);
        $shipments['receiver_address']['state']       = $this->mercadopago->orderShipping->getState($this->order);
        $shipments['receiver_address']['country']     = $this->mercadopago->orderShipping->getCountry($this->order);
        $shipments['receiver_address']['apartment']   = $this->mercadopago->orderShipping->getAddress2($this->order);
    }

    /**
     * Set checkout data after instantiation (for wallet button)
     *
     * @param array $checkout
     * @return self
     */
    public function setCheckoutData(array $checkout): self
    {
        $this->checkout = $checkout;

        // Recreate metadata with the new checkout data
        $this->transaction['metadata'] = (array) $this->getInternalMetadata();

        return $this;
    }

    /**
     * Set items on transaction
     *
     * @param array $items
     *
     * @return void
     */
    public function setItemsTransaction(array &$items): void
    {
        /** @var WC_Order_Item_Product $item */
        foreach ($this->order->get_items() as $item) {
            $product  = $item->get_product();
            $quantity = $item->get_quantity();

            $title = $product->get_name();
            $title = "$title x $quantity";

            $amount = Numbers::calculateByCurrency(
                $this->countryConfigs['currency'],
                $item->get_total() + $item->get_total_tax(),
                $this->ratio
            );

            $this->orderTotal   += $amount;
            $this->listOfItems[] = $title;

            $item = [
                'id'          => $item->get_product_id(),
                'title'       => $title,
                'description' => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($product->get_description()),
                'picture_url' => wp_get_attachment_url($product->get_image_id()),
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => $amount,
                'currency_id' => $this->countryConfigs['currency'],
                'quantity'    => 1,
            ];

            $items[] = $item;
        }
    }

    /**
     * Set shipping
     *
     * @param array $items
     *
     * @return void
     */
    public function setShippingTransaction(array &$items): void
    {
        $shipTotal = Numbers::format((float) $this->order->get_shipping_total());
        $shipTaxes = Numbers::format((float) $this->order->get_shipping_tax());

        $amount = $shipTotal + $shipTaxes;
        $amount = Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);

        if ($amount > 0) {
            $this->orderTotal += $amount;

            $item = [
                'id'          => 'shipping',
                'title'       => $this->mercadopago->orderShipping->getShippingMethod($this->order),
                'description' => $this->mercadopago->storeTranslations->commonCheckout['shipping_title'],
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => $amount,
                'currency_id' => $this->countryConfigs['currency'],
                'quantity'    => 1,
            ];

            $items[] = $item;
        }
    }

    /**
     * Set fee
     *
     * @param array $items
     *
     * @return void
     */
    public function setFeeTransaction(array &$items): void
    {
        foreach ($this->order->get_fees() as $fee) {
            $feeTotal = Numbers::format((float) $fee->get_total());
            $feeTaxes = Numbers::format((float) $fee->get_total_tax());

            $amount = $feeTotal + $feeTaxes;
            $amount = Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);

            $this->orderTotal += $amount;

            $item = [
                'id'          => 'fee',
                'title'       => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($fee['name']),
                'description' => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($fee['name']),
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => $amount,
                'currency_id' => $this->countryConfigs['currency'],
                'quantity'    => 1,
            ];

            $items[] = $item;
        }
    }

    /**
     * Set additional info
     *
     * @return void
     */
    public function setAdditionalInfoTransaction(): void
    {
        $this->setAdditionalInfoBaseInfoTransaction();
        $this->setAdditionalInfoItemsTransaction();
        $this->setAdditionalInfoShipmentsTransaction();
        $this->setAdditionalInfoPayerTransaction();
        $this->setAdditionalInfoSellerTransaction();
    }

    /**
     * Set base information
     *
     * @return void
     */
    public function setAdditionalInfoBaseInfoTransaction(): void
    {
        $this->transaction['additional_info']['ip_address'] = $this->mercadopago->helpers->url->getServerAddress();
        $this->transaction['additional_info']['referral_url'] = $this->mercadopago->helpers->url->getBaseUrl();
    }

    /**
     * Set additional items information
     *
     * @return void
     */
    public function setAdditionalInfoItemsTransaction(): void
    {
        $this->setItemsTransaction($this->transaction['additional_info']['items']);
        $this->setShippingTransaction($this->transaction['additional_info']['items']);
        $this->setFeeTransaction($this->transaction['additional_info']['items']);
    }

    /**
     * Set additional shipments information
     *
     * @return void
     */
    public function setAdditionalInfoShipmentsTransaction(): void
    {
        $this->setShipmentsTransaction($this->transaction['additional_info']['shipments']);
    }

    /**
     * Set additional seller information
     *
     * @return void
     */
    public function setAdditionalInfoSellerTransaction(): void
    {
        $this->transaction['additional_info']['seller']['store_id'] = $this->mercadopago->storeConfig->getStoreId();
        $this->transaction['additional_info']['seller']['business_type'] = $this->mercadopago->storeConfig->getStoreCategory('others');
        $this->transaction['additional_info']['seller']['collector'] = $this->mercadopago->sellerConfig->getClientId();
        $this->transaction['additional_info']['seller']['website'] = $this->mercadopago->helpers->url->getBaseUrl();
        $this->transaction['additional_info']['seller']['platform_url'] = $this->mercadopago->helpers->url->getBaseUrl();
        $this->transaction['additional_info']['seller']['referral_url'] = $this->mercadopago->helpers->url->getBaseUrl();
    }

    /**
     * Set additional payer information
     *
     * @return void
     */
    public function setAdditionalInfoPayerTransaction(): void
    {
        $this->transaction['additional_info']['payer']['first_name'] = $this->mercadopago->orderBilling->getFirstName($this->order);
        $this->transaction['additional_info']['payer']['last_name'] = $this->mercadopago->orderBilling->getLastName($this->order);
        $this->transaction['additional_info']['payer']['user_email'] = $this->mercadopago->orderBilling->getEmail($this->order);
        $this->transaction['additional_info']['payer']['phone']['number'] = $this->mercadopago->orderBilling->getPhone($this->order);
        $this->transaction['additional_info']['payer']['mobile']['number'] = $this->mercadopago->orderBilling->getPhone($this->order);
        $this->transaction['additional_info']['payer']['address']['city'] = $this->mercadopago->orderBilling->getCity($this->order);
        $this->transaction['additional_info']['payer']['address']['state'] = $this->mercadopago->orderBilling->getState($this->order);
        $this->transaction['additional_info']['payer']['address']['country'] = $this->mercadopago->orderBilling->getCountry($this->order);
        $this->transaction['additional_info']['payer']['address']['zip_code'] = $this->mercadopago->orderBilling->getZipcode($this->order);
        $this->transaction['additional_info']['payer']['address']['street_name'] = $this->mercadopago->orderBilling->getAddress1($this->order);
        $this->transaction['additional_info']['payer']['address']['apartment'] = $this->mercadopago->orderBilling->getAddress2($this->order);

        if ($this->mercadopago->helpers->currentUser->isUserLoggedIn()) {
            $this->transaction['additional_info']['payer']['registered_user'] = true;
            $this->transaction['additional_info']['payer']['identification']['number'] = $this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_document', true);
            $this->transaction['additional_info']['payer']['registration_date'] = $this->mercadopago->helpers->currentUser->getCurrentUserData()->user_registered;
            $this->transaction['additional_info']['payer']['platform_email'] = $this->mercadopago->helpers->currentUser->getCurrentUserData()->user_email;
            $this->transaction['additional_info']['payer']['register_updated_at'] = $this->mercadopago->helpers->currentUser->getCurrentUserData()->__get('user_modified');
        }
    }

    // Datadog value reflects the HTTP status when the Core client can attribute one.
    protected function sendApiErrorMetric(string $apiRoute, Exception $e): void
    {
        $details = MetricContext::buildBaseMetricDetails($apiRoute, $this->mercadopago ?? null);
        $details['sdk_instance_id'] = $this->resolveMetadataField('flow_id');
        $details = $this->addTransportMetricDetails($details, $e);
        // payment_method carries checkout_type — the product bucket (super_token, credit_card, pix…), not the card brand. Intentional: PSW-3760 needs the SuperToken flow identifiable in API errors.
        $paymentMethod = $this->resolveMetadataField('checkout_type');
        Datadog::getInstance()->sendEvent('mp_api_error', (string) $e->getCode(), $e->getMessage(), $paymentMethod, $details);
    }

    // Records every payment-creation outcome (success and error) so it can be cross-referenced with the Core P&P funnel.
    // The HTTP status class is inferred from control flow: the Core client only returns data on 2xx. On an
    // ApiException the class comes from the status it carries; any other Exception is treated as a 5xx. A rejected
    // card is NOT an API error — it is a 2xx whose
    // business outcome lives in the response body, so on success payment_status carries that outcome
    // (approved/rejected/pending/in_process/…). This runs on the successful payment return path, so it must never
    // affect the flow: any failure here is swallowed silently (no block, no log) — a missing metric must never cost a payment.
    protected function sendPaymentCreateResultMetric(string $apiRoute, ?Exception $e = null, ?array $data = null): void
    {
        try {
            if ($e === null) {
                $statusClass   = '2xx';
                $alertType     = 'success';
                $paymentStatus = is_array($data) ? ($data['status'] ?? null) : null;
                $message       = 'success';
            } elseif ($e instanceof ApiException) {
                $apiStatus     = $e->getApiStatus();
                $statusClass   = ($apiStatus !== null && $apiStatus >= 500) ? '5xx' : '4xx';
                $alertType     = 'error';
                $paymentStatus = null;
                $message       = $e->getMessage();
            } else {
                $statusClass   = '5xx';
                $alertType     = 'error';
                $paymentStatus = null;
                $message       = $e->getMessage();
            }

            $details = MetricContext::buildBaseMetricDetails($apiRoute, $this->mercadopago ?? null);
            $details['sdk_instance_id'] = $this->resolveMetadataField('flow_id');
            $details['alert_type']      = $alertType;
            $details['payment_status']  = $paymentStatus;
            $details['device']          = Device::getDeviceType();
            if ($e !== null) {
                $details = $this->addTransportMetricDetails($details, $e);
            }

            $paymentMethod = $this->resolveMetadataField('checkout_type');
            Datadog::getInstance()->sendEvent('mp_payment_create_result', $statusClass, $message, $paymentMethod, $details);
        } catch (\Throwable $t) {
            // Intentionally ignored: observability must never block or pollute the payment flow.
        }
    }

    // Assumes setCommonTransaction() has already run (called in every subtype constructor before save()).
    // If a failure occurs before that point, the field will be absent and the value degrades to null — by design.
    private function resolveMetadataField(string $field): ?string
    {
        $metadata = $this->transaction['metadata'] ?? null;
        $value    = is_array($metadata) ? ($metadata[$field] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function addTransportMetricDetails(array $details, Exception $e): array
    {
        if ($e instanceof TransportException) {
            $details['transport_code'] = $e->getTransportCode();
        }

        return $details;
    }
}

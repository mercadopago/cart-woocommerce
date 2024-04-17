<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Helpers\NotificationType;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataAddress;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataUser;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadataCpp;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

abstract class AbstractTransaction
{
    /**
     * @var WoocommerceMercadoPago
     */
    protected $mercadopago;

    /**
     * @var Sdk
     */
    protected $sdk;

    /**
     * Transaction
     *
     * @var Payment|Preference
     */
    protected $transaction;

    /**
     * Gateway
     *
     * @var AbstractGateway
     */
    protected $gateway;

    /**
     * Order
     *
     * @var \WC_Order
     */
    protected $order;

    /**
     * Checkout data
     *
     * @var array
     */
    protected $checkout = null;

    /**
     * Country configs
     *
     * @var array
     */
    protected $countryConfigs;

    /**
     * @var float
     */
    protected $ratio;

    /**
     * @var float
     */
    protected $orderTotal;

    /**
     * @var array
     */
    protected $listOfItems;

    /**
     * Abstract Transaction constructor
     *
     * @param AbstractGateway $gateway
     * @param \WC_Order $order
     * @param array|null $checkout
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout = null)
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->order       = $order;
        $this->gateway     = $gateway;
        $this->checkout    = $checkout;
        $this->sdk         = $this->getSdkInstance();

        $this->ratio          = $this->mercadopago->helpers->currency->getRatio($gateway);
        $this->countryConfigs = $this->mercadopago->helpers->country->getCountryConfigs();

        $this->orderTotal     = 0;
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $accessToken  = $this->mercadopago->sellerConfig->getCredentialsAccessToken();
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->mercadopago->storeConfig->getIntegratorId();

        return new Sdk($accessToken, $platformId, $productId, $integratorId);
    }

    /**
     * Get transaction
     *
     * @param string $transactionType
     *
     * @return Payment|Preference
     */
    public function getTransaction(string $transactionType)
    {
        $transactionClone = clone $this->transaction;

        unset($transactionClone->token);
        $this->mercadopago->logs->file->info("$transactionType payload", $this->gateway::LOG_SOURCE, $transactionClone);

        return $this->transaction;
    }

    /**
     * Set common transaction
     *
     * @return void
     */
    public function setCommonTransaction(): void
    {
        $this->transaction->binary_mode          = $this->getBinaryMode();
        $this->transaction->external_reference   = $this->getExternalReference();
        $this->transaction->notification_url      = $this->getNotificationUrl();
        $this->transaction->metadata             = (array) $this->getInternalMetadata();
        $this->transaction->statement_descriptor = $this->mercadopago->storeConfig->getStoreName('Mercado Pago');
    }

    /**
     * Get notification url
     *
     * @return string|void
     */
    private function getNotificationUrl()
    {
        $customDomain        = $this->mercadopago->storeConfig->getCustomDomain();
        $customDomainOptions = $this->mercadopago->storeConfig->getCustomDomainOptions();

        if (
            !empty($customDomain) && (
            strrpos($customDomain, 'localhost') === false ||
            filter_var($customDomain, FILTER_VALIDATE_URL) === false
            )
        ) {
            if ($customDomainOptions === 'yes') {
                return $customDomain . '?wc-api=' . $this->gateway::WEBHOOK_API_NAME . '&source_news=' . NotificationType::getNotificationType($this->gateway::WEBHOOK_API_NAME);
            } else {
                return $customDomain;
            }
        }

        if (empty($customDomain) && !strrpos(get_site_url(), 'localhost')) {
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
        $binaryMode = $this->gateway
            ? $this->mercadopago->hooks->options->getGatewayOption($this->gateway, 'binary_mode', 'no')
            : 'no';

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
        $seller  = $this->mercadopago->sellerConfig->getCollectorId();
        $siteId  = $this->mercadopago->sellerConfig->getSiteId();
        $siteUrl = $this->mercadopago->hooks->options->get('siteurl');

        $zipCode = $this->mercadopago->orderBilling->getZipcode($this->order);
        $zipCode = str_replace('-', '', $zipCode);

        $user             = $this->mercadopago->helpers->currentUser->getCurrentUser();
        $userId           = $user->ID;
        $userRegistration = $user->user_registered;

        $metadata = new PaymentMetadata();
        $metadata->platform                      = MP_PLATFORM_ID;
        $metadata->platform_version              = $this->mercadopago->woocommerce->version;
        $metadata->module_version                = MP_VERSION;
        $metadata->php_version                   = PHP_VERSION;
        $metadata->site_id                       = strtolower($siteId);
        $metadata->sponsor_id                    = $this->countryConfigs['sponsor_id'];
        $metadata->collector                     = $seller;
        $metadata->test_mode                     = $this->mercadopago->storeConfig->isTestMode();
        $metadata->details                       = '';
        $metadata->seller_website                = $siteUrl;
        $metadata->billing_address               = new PaymentMetadataAddress();
        $metadata->billing_address->zip_code     = $zipCode;
        $metadata->billing_address->street_name  = $this->mercadopago->orderBilling->getAddress1($this->order);
        $metadata->billing_address->city_name    = $this->mercadopago->orderBilling->getCity($this->order);
        $metadata->billing_address->state_name   = $this->mercadopago->orderBilling->getState($this->order);
        $metadata->billing_address->country_name = $this->mercadopago->orderBilling->getCountry($this->order);
        $metadata->user                          = new PaymentMetadataUser();
        $metadata->user->registered_user         = $userId ? 'yes' : 'no';
        $metadata->user->user_email              = $userId ? $user->user_email : null;
        $metadata->user->user_registration_date  = $userId ? Date::formatGmDate($userRegistration) : null;
        $metadata->cpp_extra                     = new PaymentMetadataCpp();
        $metadata->cpp_extra->platform_version   = $this->mercadopago->woocommerce->version;
        $metadata->cpp_extra->module_version     = MP_VERSION;
        $metadata->blocks_payment                = $this->mercadopago->orderMetadata->getPaymentBlocks($this->order);
        $metadata->settings                      = $this->mercadopago->metadataConfig->getGatewaySettings($this->gateway::ID);

        return $metadata;
    }

    /**
     * Set additional shipments information
     *
     * @param $shipments
     *
     * @return void
     */
    public function setShipmentsTransaction($shipments): void
    {
        $shipments->receiver_address->street_name = $this->mercadopago->orderShipping->getAddress1($this->order);
        $shipments->receiver_address->zip_code    = $this->mercadopago->orderShipping->getZipcode($this->order);
        $shipments->receiver_address->city        = $this->mercadopago->orderShipping->getCity($this->order);
        $shipments->receiver_address->state       = $this->mercadopago->orderShipping->getState($this->order);
        $shipments->receiver_address->country     = $this->mercadopago->orderShipping->getCountry($this->order);
        $shipments->receiver_address->apartment   = $this->mercadopago->orderShipping->getAddress2($this->order);
    }

    /**
     * Set items on transaction
     *
     * @param $items
     *
     * @return void
     */
    public function setItemsTransaction($items): void
    {
        foreach ($this->order->get_items() as $item) {
            $product  = $item->get_product();
            $quantity = $item->get_quantity();

            $title = $product->get_name();
            $title = "$title x $quantity";

            $amount = $this->getItemAmount($item);

            $this->orderTotal   += $amount;
            $this->listOfItems[] = $title;

            $item = [
                'id'          => $item->get_product_id(),
                'title'       => $title,
                'description' => $this->mercadopago->helpers->strings->sanitizeAndTruncateText($product->get_description()),
                'picture_url' => $this->getItemImage($product),
                'category_id' => $this->mercadopago->storeConfig->getStoreCategory('others'),
                'unit_price'  => $amount,
                'currency_id' => $this->countryConfigs['currency'],
                'quantity'    => 1,
            ];

            $items->add($item);
        }
    }

    /**
     * Set shipping
     *
     * @param $items
     *
     * @return void
     */
    public function setShippingTransaction($items): void
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

            $items->add($item);
        }
    }

    /**
     * Set fee
     *
     * @param $items
     *
     * @return void
     */
    public function setFeeTransaction($items): void
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

            $items->add($item);
        }
    }

    /**
     * Get item amount
     *
     * @param \WC_Order_Item|\WC_Order_Item_Product $item
     *
     * @return float
     */
    public function getItemAmount(\WC_Order_Item $item): float
    {
        $lineAmount = $item->get_total() + $item->get_total_tax();
        return Numbers::calculateByCurrency($this->countryConfigs['currency'], $lineAmount, $this->ratio);
    }

    /**
     * Get item image
     *
     * @param mixed $product
     *
     * @return string
     */
    public function getItemImage($product): string
    {
        return is_object($product) && method_exists($product, 'get_image_id')
            ? wp_get_attachment_url($product->get_image_id())
            : $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/gateways/all/blue-cart', '.png', true);
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
        $this->transaction->additional_info->ip_address = $this->mercadopago->helpers->url->getServerAddress();
        $this->transaction->additional_info->referral_url = $this->mercadopago->helpers->url->getBaseUrl();
    }

    /**
     * Set additional items information
     *
     * @return void
     */
    public function setAdditionalInfoItemsTransaction(): void
    {
        $items = $this->transaction->additional_info->items;

        $this->setItemsTransaction($items);
        $this->setShippingTransaction($items);
        $this->setFeeTransaction($items);
    }

    /**
     * Set additional shipments information
     *
     * @return void
     */
    public function setAdditionalInfoShipmentsTransaction(): void
    {
        $this->setShipmentsTransaction($this->transaction->additional_info->shipments);
    }

    /**
     * Set additional seller information
     *
     * @return void
     */
    public function setAdditionalInfoSellerTransaction(): void
    {
        $seller = $this->transaction->additional_info->seller;

        $seller->store_id      = $this->mercadopago->storeConfig->getStoreId();
        $seller->business_type = $this->mercadopago->storeConfig->getStoreCategory('others');
        $seller->collector     = $this->mercadopago->sellerConfig->getClientId();
        $seller->website       = $this->mercadopago->helpers->url->getBaseUrl();
        $seller->platform_url  = $this->mercadopago->helpers->url->getBaseUrl();
        $seller->referral_url  = $this->mercadopago->helpers->url->getBaseUrl();
    }

    /**
     * Set additional payer information
     *
     * @return void
     */
    public function setAdditionalInfoPayerTransaction(): void
    {
        $payer = $this->transaction->additional_info->payer;

        $payer->first_name           = $this->mercadopago->orderBilling->getFirstName($this->order);
        $payer->last_name            = $this->mercadopago->orderBilling->getLastName($this->order);
        $payer->user_email           = $this->mercadopago->orderBilling->getEmail($this->order);
        $payer->phone->number        = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->mobile->number       = $this->mercadopago->orderBilling->getPhone($this->order);
        $payer->address->city        = $this->mercadopago->orderBilling->getCity($this->order);
        $payer->address->state       = $this->mercadopago->orderBilling->getState($this->order);
        $payer->address->country     = $this->mercadopago->orderBilling->getCountry($this->order);
        $payer->address->zip_code    = $this->mercadopago->orderBilling->getZipcode($this->order);
        $payer->address->street_name = $this->mercadopago->orderBilling->getAddress1($this->order);
        $payer->address->apartment   = $this->mercadopago->orderBilling->getAddress2($this->order);

        if ($this->mercadopago->helpers->currentUser->isUserLoggedIn()) {
            $payer->registered_user        = true;
            $payer->identification->number = $this->mercadopago->helpers->currentUser->getCurrentUserMeta('billing_document', true);
            $payer->registration_date      = $this->mercadopago->helpers->currentUser->getCurrentUserData()->user_registered;
            $payer->platform_email         = $this->mercadopago->helpers->currentUser->getCurrentUserData()->user_email;
            $payer->register_updated_at    = $this->mercadopago->helpers->currentUser->getCurrentUserData()->__get('user_modified');
        }
    }
}

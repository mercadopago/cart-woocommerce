<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Helpers\Date;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

abstract class AbstractTransaction extends \WC_Payment_Gateway
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
     * @param array $checkout
     */
    public function __construct(AbstractGateway $gateway, \WC_Order $order, array $checkout = null)
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->sdk         = $this->getSdkInstance();
        $this->gateway     = $gateway;
        $this->order       = $order;
        $this->checkout    = $checkout;

        $this->orderTotal     = 0;
        $this->ratio          = $this->mercadopago->currency->getRatio($gateway);
        $this->countryConfigs = $this->mercadopago->country->getCountryConfigs();
    }

    /**
     * Get SDK instance
     */
    public function getSdkInstance(): Sdk
    {
        $accessToken  = $this->mercadopago->seller->getCredentialsAccessToken();
        $platformId   = MP_PLATFORM_ID;
        $productId    = Device::getDeviceProductId();
        $integratorId = $this->mercadopago->store->getIntegratorId();

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
        $this->transaction->notification_url     = $this->getNotificationUrl();
        $this->transaction->metadata             = $this->getInternalMetadata();
        $this->transaction->statement_descriptor = $this->mercadopago->store->getStoreName('Mercado Pago');
    }

	/**
	 * Get notification url
	 *
	 * @return string|void
     */
	private function getNotificationUrl()
    {
		if (!strrpos(get_site_url(), 'localhost')) {
			$notificationUrl = $this->mercadopago->store->getCustomDomain();

			if (empty($notificationUrl) || filter_var($notificationUrl, FILTER_VALIDATE_URL) === false) {
				return $this->mercadopago->woocommerce->api_request_url($this->gateway::WEBHOOK_API_NAME);
			} else {
                $customDomainOptions = $this->mercadopago->store->getCustomDomainOptions();
                if ($customDomainOptions === 'yes') {
                    return $this->mercadopago->strings->fixUrlAmpersand(esc_url($notificationUrl . '/wc-api/' . $this->gateway::WEBHOOK_API_NAME . '/'));
                }
                return $this->mercadopago->strings->fixUrlAmpersand(esc_url($notificationUrl));
			}
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
            ? $this->mercadopago->options->getGatewayOption($this->gateway, 'binary_mode', 'no')
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
        return $this->mercadopago->store->getStoreId('WC-') . $this->order->get_id();
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        $seller  = $this->mercadopago->seller->getClientId();
        $siteId  = $this->mercadopago->seller->getSiteId();
        $siteUrl = $this->mercadopago->options->get('siteurl');

        $zipCode = $this->mercadopago->orderBilling->getZipcode($this->order);
        $zipCode = str_replace('-', '', $zipCode);

        $user             = $this->mercadopago->currentUser->getCurrentUser();
        $userId           = $user->ID;
        $userRegistration = $user->user_registered;

        return [
            'platform'         => MP_PLATFORM_ID,
            'platform_version' => $this->mercadopago->woocommerce->version,
            'module_version'   => MP_VERSION,
            'php_version'      => PHP_VERSION,
            'site_id'          => strtolower($siteId),
            'sponsor_id'       => $this->countryConfigs['sponsor_id'],
            'collector'        => $seller,
            'test_mode'        => $this->mercadopago->store->isTestMode(),
            'details'          => '',
            'basic_settings'   => $this->mercadopago->metadataConfig->getGatewaySettings('basic'),
			'custom_settings'  => $this->mercadopago->metadataConfig->getGatewaySettings('custom'),
			'ticket_settings'  => $this->mercadopago->metadataConfig->getGatewaySettings('ticket'),
			'pix_settings'     => $this->mercadopago->metadataConfig->getGatewaySettings('pix'),
			'credits_settings' => $this->mercadopago->metadataConfig->getGatewaySettings('credits'),
            'wallet_button_settings' => $this->mercadopago->metadataConfig->getGatewaySettings('wallet_button'),
            'seller_website'   => $siteUrl,
            'billing_address'  => [
                'zip_code'     => $zipCode,
                'street_name'  => $this->mercadopago->orderBilling->getAddress1($this->order),
                'city_name'    => $this->mercadopago->orderBilling->getCity($this->order),
                'state_name'   => $this->mercadopago->orderBilling->getState($this->order),
                'country_name' => $this->mercadopago->orderBilling->getCountry($this->order),
            ],
            'user' => [
                'registered_user'        => $userId ? 'yes' : 'no',
                'user_email'             => $userId ? $user->user_email : null,
                'user_registration_date' => $userId ? Date::formatGmDate($userRegistration) : null,
            ],
            'cpp_extra' => [
                'platform_version' => $this->mercadopago->woocommerce->version,
                'module_version'   => MP_VERSION,
            ]
        ];
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
        $shipments->receiver_address->street_name     = $this->mercadopago->orderShipping->getAddress1($this->order);
        $shipments->receiver_address->zip_code        = $this->mercadopago->orderShipping->getZipcode($this->order);
        $shipments->receiver_address->city            = $this->mercadopago->orderShipping->getCity($this->order);
        $shipments->receiver_address->state           = $this->mercadopago->orderShipping->getState($this->order);
        $shipments->receiver_address->country         = $this->mercadopago->orderShipping->getCountry($this->order);
        $shipments->receiver_address->apartment       = $this->mercadopago->orderShipping->getAddress2($this->order);

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
            $amount = Numbers::format($amount);

            $this->orderTotal   += Numbers::format($amount);
            $this->listOfItems[] = $title;

            $item = [
                'id'          => $item->get_product_id(),
                'title'       => $title,
                'description' => $this->mercadopago->strings->sanitizeAndTruncateText($product->get_description()),
                'picture_url' => $this->getItemImage($product),
                'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'currency_id' => $this->countryConfigs['currency'],
            ];

            $items->add($item);
        }
    }

    /**
     * Set shipping
     *
     * @return void
     */
    public function setShippingTransaction(): void
    {
        $amount = Numbers::format($this->order->get_shipping_total()) + Numbers::format($this->order->get_shipping_tax());
        $cost   = Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);

        if ($amount > 0) {
            $this->orderTotal += Numbers::format($cost);

            $item = [
                'id'          => 'shipping',
                'title'       => $this->mercadopago->orderShipping->getShippingMethod($this->order),
                'description' => $this->mercadopago->storeTranslations->commonCheckout['shipping_title'],
                'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                'quantity'    => 1,
                'unit_price'  => Numbers::format($amount),
                'currency_id' => $this->countryConfigs['currency'],
            ];

            $this->transaction->items->add($item);
        }
    }

    /**
     * Set fee
     *
     * @return void
     */
    public function setFeeTransaction(): void
    {
        $fees = $this->order->get_fees();

        foreach ($fees as $fee) {
            $feeTotal = Numbers::format($fee->get_total());
            $feeTaxes = Numbers::format($fee->get_total_tax());
            $amount   = ($feeTotal + $feeTaxes) * $this->ratio;

            $this->orderTotal += Numbers::format($amount);

            $item = [
                'id'          => 'fee',
                'title'       => $this->mercadopago->strings->sanitizeAndTruncateText($fee['name']),
                'description' => $this->mercadopago->strings->sanitizeAndTruncateText($fee['name']),
                'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                'quantity'    => 1,
                'unit_price'  => Numbers::format($amount),
                'currency_id' => $this->countryConfigs['currency'],
            ];

            $this->transaction->items->add($item);
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
        $lineAmount = $item->get_subtotal() + $item->get_subtotal_tax();
        $discount   = Numbers::format($lineAmount * ($this->gateway->discount / 100));
        $commission = Numbers::format($lineAmount * ($this->gateway->commission / 100));
        $amount     = $lineAmount - $discount + $commission;

        return Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);
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
            : $this->mercadopago->url->getPluginFileUrl('assets/images/gateways/all/blue-cart', '.png', true);
    }
}

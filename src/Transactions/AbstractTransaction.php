<?php

namespace MercadoPago\Woocommerce\Transactions;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
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
     * @var object
     */
    protected $order;

    /**
     * Checkout
     *
     * @var null
     */
    protected $checkout;

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
     * @var int
     */
    protected $orderTotal;

    /**
     * @var array
     */
    protected $listOfItems;

    /**
     * Abstract Transaction constructor
     */
    public function __construct(AbstractGateway $gateway, $order, $checkout = null)
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
    public function getTransaction(string $transactionType = 'Preference')
    {
        $transactionClone = clone $this->transaction;

        if (isset($transactionClone->token)) {
            unset($transactionClone->token);
        }

        $this->mercadopago->logs->file->info(
            $transactionType . ': ' . wp_json_encode($transactionClone, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            __FUNCTION__
        );

        return $this->transaction;
    }

    /**
     * Set common transaction
     */
    public function setCommonTransaction()
    {
        $statementDescriptor = $this->mercadopago->store->getStoreName('Mercado Pago');

        $this->transaction->binary_mode          = $this->getBinaryMode();
        $this->transaction->external_reference   = $this->getExternalReference();
        $this->transaction->notification_url     = $this->getNotificationUrl();
        $this->transaction->statement_descriptor = $statementDescriptor;
        $this->transaction->metadata             = $this->getInternalMetadata();
    }

	/**
	 * Get notification url
	 *
	 * @return string|void
	 */
	private function getNotificationUrl() {
		if (!strrpos(get_site_url(), 'localhost')) {
			$notificationUrl = $this->mercadopago->store->getCustomDomain();

			// Check if we have a custom URL.
			if ( empty($notificationUrl) || filter_var($notificationUrl, FILTER_VALIDATE_URL) === false ) {
				return WC()->api_request_url($this->gateway->id);
			} else {
                return $this->mercadopago->strings->fixUrlAmpersand(esc_url($notificationUrl . '/wc-api/' . $this->gateway->id . '/'));
			}
		}
	}

    /**
     * Get binary mode
     *
     * @return string
     */
    public function getBinaryMode(): bool
    {
        $binaryMode = !$this->gateway
            ? $this->mercadopago->options->getGatewayOption($this->gateway, 'binary_mode', 'no')
            : 'no';

        return 'no' !== $binaryMode;
    }

    /**
     * Get external reference
     *
     * @return string
     */
    public function getExternalReference(): string
    {
        $storeId = $this->mercadopago->store->getStoreId('WC-');

        if (method_exists($this->order, 'get_id')) {
            return $storeId . $this->order->get_id();
        } else {
            return $storeId . $this->order->id;
        }
    }

    /**
     * Get internal metadata
     *
     * @return array
     */
    public function getInternalMetadata(): array
    {
        global $woocommerce;

        $userId  = get_current_user_id();
        $seller  = $this->mercadopago->options->get('_collector_id_v1', '');
        $siteId  = $this->mercadopago->seller->getSiteId();
        $siteUrl = $this->mercadopago->options->get('siteurl');
        $zipCode = $this->getObjectAttributeValue($this->order, 'get_billing_postcode', 'billing_postcode');
        $zipCode = str_replace('-', '', $zipCode);

        $userRegistration = get_userdata($userId)->user_registered;

        return [
            'platform'         => MP_PLATFORM_ID,
            'platform_version' => $woocommerce->version,
            'module_version'   => MP_VERSION,
            'php_version'      => PHP_VERSION,
            'site_id'          => strtolower($siteId),
            'sponsor_id'       => $this->countryConfigs['sponsor_id'],
            'collector'        => $seller,
            'test_mode'        => $this->mercadopago->seller->isTestMode(),
            'details'          => '',
            'seller_website'   => $siteUrl,
            'billing_address'  => [
                'zip_code'     => $zipCode,
                'street_name'  => $this->getObjectAttributeValue($this->order, 'get_billing_address_1', 'billing_address_1'),
                'city_name'    => $this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_city'),
                'state_name'   => $this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_state'),
                'country_name' => $this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_country'),
            ],
            'user' => [
                'registered_user'        => $userId ? 'yes' : 'no',
                'user_email'             => $userId ? get_userdata($userId)->user_email : null,
                'user_registration_date' => $userId ? gmdate('Y-m-d\TH:i:s.vP', strtotime($userRegistration)) : null,
            ],
        ];
    }

    /**
     * Set additional shipments information
     */
    public function setShipmentsTransaction($shipments)
    {
        $shipments->receiverAddress->apartment   =
            $this->getObjectAttributeValue($this->order, 'get_shipping_address_2', 'shipping_address_2');
        $shipments->receiverAddress->city_name   =
            $this->getObjectAttributeValue($this->order, 'get_shipping_city', 'shipping_city');
        $shipments->receiverAddress->state_name  =
            $this->getObjectAttributeValue($this->order, 'get_shipping_state', 'shipping_state');
        $shipments->receiverAddress->street_name =
            "{$this->getObjectAttributeValue($this->order, 'get_billing_address_1', 'billing_address_1')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_billing_address_2', 'billing_address_2')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_billing_city', 'billing_city')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_billing_state', 'billing_state')} " .
            "{$this->getObjectAttributeValue($this->order, 'get_billing_country', 'billing_country')}";
        $shipments->receiverAddress->zip_code    =
            $this->getObjectAttributeValue($this->order, 'get_shipping_postcode', 'shipping_postcode');
    }

    /**
     * Set items
     */
    public function setItemsTransaction($items)
    {
        $orderItems = $this->order->get_items();

        if (count($orderItems) > 0) {
            foreach ($orderItems as $orderItem) {
                if ($orderItem['qty']) {
                    $product = wc_get_product($orderItem['product_id']);

                    $title   = $this->getObjectAttributeValue($product, 'get_name', 'post->post_title');
                    $content = $this->getObjectAttributeValue($product, 'get_description', 'post->post_content');
                    $amount  = $this->getItemAmount($orderItem);
                    $amount  = Numbers::format($amount);

                    $this->orderTotal += Numbers::format($amount);
                    $this->listOfItems[] = $title . ' x ' . $orderItem['qty'];

                    $item = [
                        'id'          => $orderItem['product_id'],
                        'title'       => "$title x {$orderItem['qty']}",
                        'description' => $this->mercadopago->strings->sanitizeAndTruncateText($content),
                        'picture_url' => $this->getItemImage($product),
                        'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                        'quantity'    => 1,
                        'unit_price'  => $amount,
                        'currency_id' => $this->countryConfigs['currency'],
                    ];

                    $items->add($item);
                }
            }
        }
    }

    /**
     * Set shipping
     */
    public function setShippingTransaction()
    {
        $amount = $this->order->get_total_shipping() + $this->order->get_shipping_tax();
        $cost   = Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);

        if ($amount > 0) {
            $this->orderTotal += Numbers::format($cost);

            $item = [
                'title'       => $this->getObjectAttributeValue($this->order, 'get_id', 'get_shipping_method', 'shipping_method'),
                'description' => $this->mercadopago->storeTranslations->commonCheckout['shipping_title'],
                'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                'quantity'    => 1,
                'unit_price'  => Numbers::format($amount),
            ];

            $this->transaction->items->add($item);
        }
    }

    /**
     * Set fee
     */
    public function setFeeTransaction()
    {
        $fees = $this->order->get_fees();

        if (0 < count($fees)) {
            foreach ($fees as $fee) {
                $amount            = ($fee['total'] + $fee['total_tax']) * $this->ratio;
                $this->orderTotal += Numbers::format($amount);

                $item = [
                    'title'       => $this->mercadopago->strings->sanitizeAndTruncateText($fee['name']),
                    'description' => $this->mercadopago->strings->sanitizeAndTruncateText($fee['name']),
                    'category_id' => $this->mercadopago->store->getStoreCategory('others'),
                    'quantity'    => 1,
                    'unit_price'  => Numbers::format($amount),
                ];

                $this->transaction->items->add($item);
            }
        }
    }

    /**
     * Get item amount
     *
     * @param array|WC_Order_Item_Product $item
     *
     * @return float
     */
    public function getItemAmount($item): float
    {
        $lineAmount = $item['line_total'] + $item['line_tax'];
        $discount   = (float) $lineAmount * ($this->gateway->discount / 100);
        $commission = (float) $lineAmount * ($this->gateway->commission / 100);
        $amount     = $lineAmount - $discount + $commission;

        return Numbers::calculateByCurrency($this->countryConfigs['currency'], $amount, $this->ratio);
    }

    /**
     * Get item image
     *
     * @param $product
     *
     * @return string
     */
    public function getItemImage($product): string
    {
        return is_object($product) && method_exists($product, 'get_image_id')
            ? wp_get_attachment_url($product->get_image_id())
            : $this->mercadopago->url->getPluginFileUrl('assets/images/gateways/all/blue-cart', '.png');
    }

    /**
     * Get the value of an object's attribute
     *
     * @param $object
     * @param string $methodName
     * @param string $attributePath
     * @param string $methodPath
     *
     * @return string
     */
    public function getObjectAttributeValue($object, string $methodName, string $attributePath, string $methodPath = ''): string
    {
        if (!$methodPath) {
            $methodPath = $methodName;
        }

        $value = is_object($object) && method_exists($object, $methodName)
            ? $object->{$methodPath}()
            : $object->{$attributePath};

        return html_entity_decode($value);
    }
}

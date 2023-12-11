<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Configs\Metadata;
use MercadoPago\Woocommerce\Helpers\Actions;
use MercadoPago\Woocommerce\Helpers\Images;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Order\OrderBilling;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Endpoints\FrontendEndpoints;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Notices;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\Helpers\CreditsEnabled;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Hooks\Template;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Logs\Transports\File;
use MercadoPago\Woocommerce\Logs\Transports\Remote;
use MercadoPago\Woocommerce\Order\OrderShipping;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class Dependencies
{
    /**
     * @var \WooCommerce
     */
    public $woocommerce;

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var Strings
     */
    public $strings;

    /**
     * @var Admin
     */
    public $admin;

    /**
     * @var Endpoints
     */
    public $endpoints;

    /**
     * @var Options
     */
    public $options;

    /**
     * @var Actions
     */
    public $actions;

    /**
     * @var OrderMeta
     */
    public $orderMeta;

    /**
     * @var Plugin
     */
    public $plugin;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Template
     */
    public $template;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Session
     */
    public $session;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Country
     */
    public $country;

    /**
     * @var Links
     */
    public $links;

    /**
     * @var Url
     */
    public $url;

    /**
     * @var PaymentMethods
     */
    public $paymentMethods;

    /**
     * @var Store
     */
    public $store;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var Checkout
     */
    public $checkout;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var Nonce
     */
    public $nonce;

    /**
     * @var OrderBilling
     */
    public $orderBilling;

    /**
     * @var OrderShipping
     */
    public $orderShipping;

    /**
     * @var OrderMetadata
     */
    public $orderMetadata;

    /**
     * @var OrderStatus
     */
    public $orderStatus;

    /**
     * @var CurrentUser
     */
    public $currentUser;

    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var Currency
     */
    public $currency;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var Images
     */
    public $images;

    /**
     * @var Metadata
     */
    public $metadataConfig;

    /**
     * @var AdminTranslations
     */
    public $adminTranslations;

    /**
     * @var StoreTranslations
     */
    public $storeTranslations;

    /**
     * @var CreditsEnabled
     */
    public $creditsEnabled;

    /**
     * @var FrontendEndpoints
     */
    public $frontendEndpoints;

    /**
     * Dependencies constructor
     */
    public function __construct()
    {
        global $woocommerce;

        $this->woocommerce       = $woocommerce;
        $this->cache             = new Cache();
        $this->strings           = new Strings();
        $this->admin             = new Admin();
        $this->endpoints         = new Endpoints();
        $this->options           = new Options();
        $this->actions           = new Actions();
        $this->session           = new Session();
        $this->orderMeta         = new OrderMeta();
        $this->product           = new Product();
        $this->template          = new Template();
        $this->plugin            = new Plugin();
        $this->images            = new Images();
        $this->checkout          = new Checkout();
        $this->orderBilling      = new OrderBilling();
        $this->orderShipping     = new OrderShipping();
        $this->orderMetadata     = $this->setOrderMetadata();
        $this->requester         = $this->setRequester();
        $this->store             = $this->setStore();
        $this->logs              = $this->setLogs();
        $this->seller            = $this->setSeller();
        $this->country           = $this->setCountry();
        $this->links             = $this->setLinks();
        $this->url               = $this->setUrl();
        $this->paymentMethods    = $this->setPaymentMethods();
        $this->scripts           = $this->setScripts();
        $this->adminTranslations = $this->setAdminTranslations();
        $this->storeTranslations = $this->setStoreTranslations();
        $this->gateway           = $this->setGateway();
        $this->nonce             = $this->setNonce();
        $this->orderStatus       = $this->setOrderStatus();
        $this->currentUser       = $this->setCurrentUser();
        $this->order             = $this->setOrder();
        $this->notices           = $this->setNotices();
        $this->metadataConfig    = $this->setMetadataConfig();
        $this->currency          = $this->setCurrency();
        $this->settings          = $this->setSettings();
        $this->creditsEnabled    = $this->setCreditsEnabled();
        $this->frontendEndpoints = $this->setFrontendEndpoints();
    }

    /**
     * @return OrderMetadata
     */
    private function setOrderMetadata(): OrderMetadata
    {
        return new OrderMetadata($this->orderMeta);
    }

    /**
     * @return Requester
     */
    private function setRequester(): Requester
    {
        $curlRequester = new CurlRequester();
        $httpClient    = new HttpClient(Requester::BASEURL_MP, $curlRequester);

        return new Requester($httpClient);
    }

    /**
     * @return Seller
     */
    private function setSeller(): Seller
    {
        return new Seller($this->cache, $this->options, $this->requester, $this->store, $this->logs);
    }

    /**
     * @return Country
     */
    private function setCountry(): Country
    {
        return new Country($this->seller);
    }

    /**
     * @return Links
     */
    private function setLinks(): Links
    {
        return new Links($this->country);
    }

    /**
     * @return Url
     */
    private function setUrl(): Url
    {
        return new Url($this->strings);
    }

    /**
     * @return PaymentMethods
     */
    private function setPaymentMethods(): PaymentMethods
    {
        return new PaymentMethods($this->url);
    }

    /**
     * @return Store
     */
    private function setStore(): Store
    {
        return new Store($this->options);
    }

    /**
     * @return Scripts
     */
    private function setScripts(): Scripts
    {
        return new Scripts($this->url, $this->seller);
    }

    /**
     * @return Gateway
     */
    private function setGateway(): Gateway
    {
        return new Gateway(
            $this->options,
            $this->template,
            $this->store,
            $this->checkout,
            $this->storeTranslations,
            $this->url
        );
    }

    /**
     * @return Logs
     */
    private function setLogs(): Logs
    {
        $file   = new File($this->store);
        $remote = new Remote($this->store, $this->requester);

        return new Logs($file, $remote);
    }

    /**
     * @return Nonce
     */
    private function setNonce(): Nonce
    {
        return new Nonce($this->logs, $this->store);
    }

    /**
     * @return OrderStatus
     */
    private function setOrderStatus(): OrderStatus
    {
        return new OrderStatus($this->storeTranslations);
    }

    /**
     * @return CurrentUser
     */
    private function setCurrentUser(): CurrentUser
    {
        return new CurrentUser($this->logs, $this->store);
    }

    /**
     * @return AdminTranslations
     */
    private function setAdminTranslations(): AdminTranslations
    {
        return new AdminTranslations($this->links);
    }

    /**
     * @return StoreTranslations
     */
    private function setStoreTranslations(): StoreTranslations
    {
        return new StoreTranslations($this->links);
    }

    /**
     * @return Order
     */
    private function setOrder(): Order
    {
        return new Order(
            $this->template,
            $this->orderMetadata,
            $this->orderStatus,
            $this->adminTranslations,
            $this->storeTranslations,
            $this->store,
            $this->seller,
            $this->scripts,
            $this->url,
            $this->nonce,
            $this->endpoints,
            $this->currentUser,
            $this->requester,
            $this->logs
        );
    }

    /**
     * @return Notices
     */
    private function setNotices(): Notices
    {
        return new Notices(
            $this->scripts,
            $this->adminTranslations,
            $this->url,
            $this->links,
            $this->currentUser,
            $this->store,
            $this->nonce,
            $this->endpoints
        );
    }

    /**
     * @return Metadata
     */
    private function setMetadataConfig(): Metadata
    {
        return new Metadata($this->options);
    }

    /**
     * @return Currency
     */
    private function setCurrency(): Currency
    {
        return new Currency(
            $this->adminTranslations,
            $this->cache,
            $this->country,
            $this->logs,
            $this->notices,
            $this->requester,
            $this->seller,
            $this->options,
            $this->url
        );
    }

    /**
     * @return Settings
     */
    private function setSettings(): Settings
    {
        return new Settings(
            $this->admin,
            $this->endpoints,
            $this->links,
            $this->plugin,
            $this->scripts,
            $this->seller,
            $this->store,
            $this->adminTranslations,
            $this->url,
            $this->nonce,
            $this->currentUser,
            $this->session,
            $this->logs
        );
    }

    /**
     * @return CreditsEnabled
     */
    private function setCreditsEnabled(): CreditsEnabled
    {
        return new CreditsEnabled(
            $this->admin,
            $this->logs,
            $this->options
        );
    }

    /**
     * @return FrontendEndpoints
     */
    private function setFrontendEndpoints(): FrontendEndpoints
    {
        return new FrontendEndpoints(
            $this->endpoints,
            $this->logs,
            $this->requester,
            $this->session,
            $this->seller,
            $this->storeTranslations
        );
    }
}

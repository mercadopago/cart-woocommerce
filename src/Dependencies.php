<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Checkout;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Gateway;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Hooks\Product;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Hooks\Template;
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Logs\Transports\File;
use MercadoPago\Woocommerce\Logs\Transports\Remote;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\PublicTranslations;

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
     * @var PublicTranslations
     */
    public $publicTranslations;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Requester
     */
    public $requester;

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
     * @var CurrentUser
     */
    public $currentUser;

    /**
     * @var AdminTranslations
     */
    public $adminTranslations;

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
     * Dependencies constructor
     */
    public function __construct()
    {
        global $woocommerce;

        $this->woocommerce        = $woocommerce;
        $this->cache              = new Cache();
        $this->strings            = new Strings();
        $this->admin              = new Admin();
        $this->endpoints          = new Endpoints();
        $this->options            = new Options();
        $this->plugin             = new Plugin();
        $this->product            = new Product();
        $this->template           = new Template();
        $this->publicTranslations = new PublicTranslations();
        $this->order              = $this->setOrder();
        $this->requester          = $this->setRequester();
        $this->store              = $this->setStore();
        $this->seller             = $this->setSeller();
        $this->country            = $this->setCountry();
        $this->links              = $this->setLinks();
        $this->url                = $this->setUrl();
        $this->scripts            = $this->setScripts();
        $this->checkout           = $this->setCheckout();
        $this->gateway            = $this->setGateway();
        $this->logs               = $this->setLogs();
        $this->nonce              = $this->setNonce();
        $this->currentUser        = $this->setCurrentUser();
        $this->adminTranslations  = $this->setTranslations();
        $this->notices            = $this->setNotices();
        $this->currency           = $this->setCurrency();
        $this->settings           = $this->setSettings();
    }

    /**
     * @return Order
     */
    private function setOrder(): Order
    {
        return new Order($this->template);
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
        return new Seller($this->cache, $this->options, $this->requester, $this->store);
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
     * @return Checkout
     */
    private function setCheckout(): Checkout
    {
        return new Checkout($this->scripts);
    }

    /**
     * @return Gateway
     */
    private function setGateway(): Gateway
    {
        return new Gateway($this->options, $this->template, $this->publicTranslations);
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
     * @return CurrentUser
     */
    private function setCurrentUser(): CurrentUser
    {
        return new CurrentUser($this->logs, $this->store);
    }

    /**
     * @return AdminTranslations
     */
    private function setTranslations(): AdminTranslations
    {
        return new AdminTranslations($this->links);
    }

    /**
     * @return Notices
     */
    private function setNotices(): Notices
    {
        return new Notices($this->scripts, $this->adminTranslations, $this->url, $this->links);
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
            $this->seller
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
            $this->currentUser
        );
    }
}

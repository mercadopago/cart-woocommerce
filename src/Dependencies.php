<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\Woocommerce\Admin\Notices;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Admin\Translations;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Links;
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
use MercadoPago\Woocommerce\Logs\Logs;
use MercadoPago\Woocommerce\Logs\Transports\File;
use MercadoPago\Woocommerce\Logs\Transports\Remote;

if (!defined('ABSPATH')) {
    exit;
}

class Dependencies
{
    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Options
     */
    public $options;

    /**
     * @var Store
     */
    public $store;

    /**
     * @var Logs
     */
    public $logs;

    /**
     * @var Seller
     */
    public $seller;

    /**
     * @var Links
     */
    public $links;

    /**
     * @var Translations
     */
    public $translations;

    /**
     * @var Url
     */
    public $url;

    /**
     * @var Scripts
     */
    public $scripts;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var Checkout
     */
    public $checkout;

    /**
     * @var Gateway
     */
    public $gateway;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Admin
     */
    public $admin;

    /**
     * Dependencies constructor
     */
    public function __construct()
    {
        $this->requester    = $this->setRequester();
        $this->options      = $this->setOptions();
        $this->store        = $this->setStore($this->options);
        $this->logs         = $this->setLogs($this->store, $this->requester);
        $this->seller       = $this->setSeller($this->options, $this->requester);
        $this->links        = $this->setLinks($this->seller);
        $this->translations = $this->setTranslations($this->links);
        $this->url          = $this->setUrl();
        $this->scripts      = $this->setScripts($this->url);
        $this->settings     = $this->setSettings($this->links, $this->scripts, $this->seller, $this->store, $this->translations, $this->url);
        $this->notices      = $this->setNotices($this->scripts, $this->translations, $this->url);
        $this->checkout     = $this->setCheckout($this->scripts);
        $this->gateway      = $this->setGateway($this->options);
        $this->order        = $this->setOrder();
        $this->product      = $this->setProduct();
        $this->admin        = $this->setAdmin();
    }

    /**
     * Set requester
     *
     * @return Requester
     */
    public function setRequester(): Requester
    {
        $curlRequester = new CurlRequester();
        $httpClient    = new HttpClient(Requester::BASEURL_MP, $curlRequester);
        return new Requester($httpClient);
    }

    /**
     * Set options
     *
     * @return Options
     */
    public function setOptions(): Options
    {
        return new Options();
    }

    /**
     * Set store
     *
     * @param Options $options
     *
     * @return Store
     */
    public function setStore(Options $options): Store
    {
        return new Store($options);
    }

    /**
     * Set logs
     *
     * @param Store $store
     * @param Requester $requester
     *
     * @return Logs
     */
    public function setLogs(Store $store, Requester $requester): Logs
    {
        $file   = new File($store);
        $remote = new Remote($store, $requester);
        return new Logs($file, $remote);
    }

    /**
     * Set seller
     *
     * @param Options $options
     * @param Requester $requester
     *
     * @return Seller
     */
    public function setSeller(Options $options, Requester $requester): Seller
    {
        $cache = new Cache();
        return new Seller($cache, $options, $requester);
    }

    /**
     * Set links
     *
     * @param Seller $seller
     *
     * @return Links
     */
    public function setLinks(Seller $seller): Links
    {
        $country = new Country($seller);
        return new Links($country);
    }

    /**
     * Set translations
     *
     * @param Links $links
     * @return Translations
     */
    public function setTranslations(Links $links): Translations
    {
        return new Translations($links);
    }

    /**
     * Set url
     *
     * @return Url
     */
    public function setUrl(): Url
    {
        $strings = new Strings();
        return new Url($strings);
    }

    /**
     * Set scripts
     *
     * @param Url $url
     *
     * @return Scripts
     */
    public function setScripts(Url $url): Scripts
    {
        return new Scripts($url);
    }

    /**
     * Set settings
     *
     * @param Links $links
     * @param Scripts $scripts
     * @param Seller $seller
     * @param Store $store
     * @param Translations $translations
     * @param Url $url
     *
     * @return Settings
     */
    public function setSettings(Links $links, Scripts $scripts, Seller $seller, Store $store, Translations $translations, Url $url): Settings
    {
        $admin     = new Admin();
        $endpoints = new Endpoints();
        $plugin    = new Plugin();

        return new Settings($admin, $endpoints, $links, $plugin, $scripts, $seller, $store, $translations, $url);
    }

    /**
     * Set notices
     *
     * @param Scripts $scripts
     * @param Translations $translations
     * @param Url $url
     *
     * @return Notices
     */
    public function setNotices(Scripts $scripts, Translations $translations, Url $url): Notices
    {
        return new Notices($scripts, $translations, $url);
    }

    /**
     * Set checkout
     *
     * @param Scripts $scripts
     *
     * @return Checkout
     */
    public function setCheckout(Scripts $scripts): Checkout
    {
        return new Checkout($scripts);
    }

    /**
     * Set gateway
     *
     * @param Options $options
     *
     * @return Gateway
     */
    public function setGateway(Options $options): Gateway
    {
        return new Gateway($options);
    }

    /**
     * Set order
     *
     * @return Order
     */
    public function setOrder(): Order
    {
        return new Order();
    }

    /**
     * Set product
     *
     * @return Product
     */
    public function setProduct(): Product
    {
        return new Product();
    }

    /**
     * Set admin
     *
     * @return Admin
     */
    public function setAdmin(): Admin
    {
        return new Admin();
    }
}

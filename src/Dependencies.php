<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Configs\Metadata;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\Helpers\Actions;
use MercadoPago\Woocommerce\Helpers\Cart;
use MercadoPago\Woocommerce\Helpers\Images;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Hooks\Blocks;
use MercadoPago\Woocommerce\Order\OrderBilling;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Endpoints\CheckoutCustom;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Cron;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Gateways;
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
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use MercadoPago\Woocommerce\Order\OrderShipping;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\IO\Downloader;
use WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Dependencies
{
    public WooCommerce $woocommerce;

    public Hooks $hooks;

    public Helpers $helpers;

    public Settings $settings;

    public Metadata $metadataConfig;

    public Seller $sellerConfig;

    public Store $storeConfig;

    public CheckoutCustom $checkoutCustomEndpoints;

    public Admin $adminHook;

    public Blocks $blocksHook;

    public Hooks\Cart $cartHook;

    public Checkout $checkoutHook;

    public Endpoints $endpointsHook;

    public Gateway $gatewayHook;

    public Options $optionsHook;

    public Order $orderHook;

    public OrderMeta $orderMetaHook;

    public Plugin $pluginHook;

    public Product $productHook;

    public Scripts $scriptsHook;

    public Template $templateHook;

    public Actions $actionsHelper;

    public Cache $cacheHelper;

    public Cart $cartHelper;

    public Country $countryHelper;

    public CreditsEnabled $creditsEnabledHelper;

    public Cron $cronHelper;

    public Currency $currencyHelper;

    public CurrentUser $currentUserHelper;

    public Gateways $gatewaysHelper;

    public Images $imagesHelper;

    public Links $linksHelper;

    public Nonce $nonceHelper;

    public Notices $noticesHelper;

    public PaymentMethods $paymentMethodsHelper;

    public Requester $requesterHelper;

    public Session $sessionHelper;

    public Strings $stringsHelper;

    public Url $urlHelper;

    public Logs $logs;

    public OrderBilling $orderBilling;

    public OrderMetadata $orderMetadata;

    public OrderShipping $orderShipping;

    public OrderStatus $orderStatus;

    public AdminTranslations $adminTranslations;

    public StoreTranslations $storeTranslations;

    public Downloader $downloader;

    public Funnel $funnel;

    /**
     * Dependencies constructor
     */
    public function __construct()
    {
        global $woocommerce;

        $this->woocommerce             = $woocommerce;
        $this->adminHook               = new Admin();
        $this->cartHook                = new Hooks\Cart();
        $this->blocksHook              = new Blocks();
        $this->endpointsHook           = new Endpoints();
        $this->optionsHook             = new Options();
        $this->orderMetaHook           = new OrderMeta();
        $this->productHook             = new Product();
        $this->templateHook            = new Template();
        $this->pluginHook              = new Plugin();
        $this->checkoutHook            = new Checkout();
        $this->actionsHelper           = new Actions();
        $this->cacheHelper             = new Cache();
        $this->imagesHelper            = new Images();
        $this->sessionHelper           = new Session();
        $this->stringsHelper           = new Strings();
        $this->orderBilling            = new OrderBilling();
        $this->orderShipping           = new OrderShipping();
        $this->orderMetadata           = $this->setOrderMetadata();
        $this->requesterHelper         = $this->setRequester();
        $this->storeConfig             = $this->setStore();
        $this->logs                    = $this->setLogs();
        $this->sellerConfig            = $this->setSeller();
        $this->countryHelper           = $this->setCountry();
        $this->urlHelper               = $this->setUrl();
        $this->linksHelper             = $this->setLinks();
        $this->paymentMethodsHelper    = $this->setPaymentMethods();
        $this->scriptsHook             = $this->setScripts();
        $this->adminTranslations       = $this->setAdminTranslations();
        $this->storeTranslations       = $this->setStoreTranslations();
        $this->gatewaysHelper          = $this->setGatewaysHelper();
        $this->funnel                  = $this->setFunnel();
        $this->gatewayHook             = $this->setGateway();
        $this->nonceHelper             = $this->setNonce();
        $this->orderStatus             = $this->setOrderStatus();
        $this->cronHelper              = $this->setCronHelper();
        $this->currentUserHelper       = $this->setCurrentUser();
        $this->orderHook               = $this->setOrder();
        $this->noticesHelper           = $this->setNotices();
        $this->metadataConfig          = $this->setMetadataConfig();
        $this->currencyHelper          = $this->setCurrency();
        $this->downloader              = $this->setDownloader();
        $this->settings                = $this->setSettings();
        $this->creditsEnabledHelper    = $this->setCreditsEnabled();
        $this->checkoutCustomEndpoints = $this->setCustomCheckoutEndpoints();
        $this->cartHelper              = $this->setCart();
        $this->funnel                  = $this->setFunnel();

        $this->hooks   = $this->setHooks();
        $this->helpers = $this->setHelpers();
    }

    /**
     * @return OrderMetadata
     */
    private function setOrderMetadata(): OrderMetadata
    {
        return new OrderMetadata($this->orderMetaHook);
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
        return new Seller($this->cacheHelper, $this->optionsHook, $this->requesterHelper, $this->storeConfig, $this->logs);
    }

    /**
     * @return Country
     */
    private function setCountry(): Country
    {
        return new Country($this->sellerConfig);
    }

    /**
     * @return Url
     */
    private function setUrl(): Url
    {
        return new Url($this->stringsHelper);
    }

    /**
     * @return Links
     */
    private function setLinks(): Links
    {
        return new Links($this->countryHelper, $this->urlHelper);
    }

    /**
     * @return PaymentMethods
     */
    private function setPaymentMethods(): PaymentMethods
    {
        return new PaymentMethods($this->urlHelper);
    }

    /**
     * @return Store
     */
    private function setStore(): Store
    {
        return new Store($this->optionsHook);
    }

    /**
     * @return Scripts
     */
    private function setScripts(): Scripts
    {
        return new Scripts($this->urlHelper, $this->sellerConfig);
    }

    /**
     * @return Gateway
     */
    private function setGateway(): Gateway
    {
        return new Gateway(
            $this->optionsHook,
            $this->templateHook,
            $this->storeConfig,
            $this->checkoutHook,
            $this->storeTranslations,
            $this->urlHelper,
            $this->funnel
        );
    }

    /**
     * @return Logs
     */
    private function setLogs(): Logs
    {
        $file   = new File($this->storeConfig);
        $remote = new Remote($this->storeConfig, $this->requesterHelper);

        return new Logs($file, $remote);
    }

    /**
     * @return Nonce
     */
    private function setNonce(): Nonce
    {
        return new Nonce($this->logs, $this->storeConfig);
    }

    /**
     * @return OrderStatus
     */
    private function setOrderStatus(): OrderStatus
    {
        return new OrderStatus($this->storeTranslations);
    }

    /**
     * @return Cron
     */
    private function setCronHelper(): Cron
    {
        return new Cron($this->logs);
    }

    /**
     * @return CurrentUser
     */
    private function setCurrentUser(): CurrentUser
    {
        return new CurrentUser($this->logs, $this->storeConfig);
    }

    /**
     * @return Gateways
     */
    private function setGatewaysHelper(): Gateways
    {
        return new Gateways($this->storeConfig);
    }

    /**
     * @return AdminTranslations
     */
    private function setAdminTranslations(): AdminTranslations
    {
        return new AdminTranslations($this->linksHelper);
    }

    /**
     * @return StoreTranslations
     */
    private function setStoreTranslations(): StoreTranslations
    {
        return new StoreTranslations($this->linksHelper);
    }

    /**
     * @return Order
     */
    private function setOrder(): Order
    {
        return new Order(
            $this->templateHook,
            $this->orderMetadata,
            $this->orderStatus,
            $this->adminTranslations,
            $this->storeTranslations,
            $this->storeConfig,
            $this->sellerConfig,
            $this->scriptsHook,
            $this->urlHelper,
            $this->nonceHelper,
            $this->endpointsHook,
            $this->cronHelper,
            $this->currentUserHelper,
            $this->requesterHelper,
            $this->logs
        );
    }

    /**
     * @return Notices
     */
    private function setNotices(): Notices
    {
        return new Notices(
            $this->scriptsHook,
            $this->adminTranslations,
            $this->urlHelper,
            $this->linksHelper,
            $this->currentUserHelper,
            $this->storeConfig,
            $this->nonceHelper,
            $this->endpointsHook,
            $this->sellerConfig
        );
    }

    /**
     * @return Metadata
     */
    private function setMetadataConfig(): Metadata
    {
        return new Metadata($this->optionsHook);
    }

    /**
     * @return Currency
     */
    private function setCurrency(): Currency
    {
        return new Currency(
            $this->adminTranslations,
            $this->cacheHelper,
            $this->countryHelper,
            $this->logs,
            $this->noticesHelper,
            $this->requesterHelper,
            $this->sellerConfig,
            $this->optionsHook,
            $this->urlHelper
        );
    }

    /**
     * @return Settings
     */
    private function setSettings(): Settings
    {
        return new Settings(
            $this->adminHook,
            $this->endpointsHook,
            $this->linksHelper,
            $this->orderHook,
            $this->pluginHook,
            $this->scriptsHook,
            $this->sellerConfig,
            $this->storeConfig,
            $this->adminTranslations,
            $this->urlHelper,
            $this->nonceHelper,
            $this->currentUserHelper,
            $this->sessionHelper,
            $this->logs,
            $this->downloader,
            $this->funnel,
            $this->stringsHelper
        );
    }

    /**
     * @return CreditsEnabled
     */
    private function setCreditsEnabled(): CreditsEnabled
    {
        return new CreditsEnabled(
            $this->adminHook,
            $this->logs,
            $this->optionsHook
        );
    }

    /**
     * @return Funnel
     */
    private function setFunnel(): Funnel
    {
        return new Funnel(
            $this->storeConfig,
            $this->sellerConfig,
            $this->countryHelper,
            $this->gatewaysHelper
        );
    }

    /**
     * @return CheckoutCustom
     */
    private function setCustomCheckoutEndpoints(): CheckoutCustom
    {
        return new CheckoutCustom(
            $this->endpointsHook,
            $this->logs,
            $this->requesterHelper,
            $this->sessionHelper,
            $this->sellerConfig,
            $this->storeTranslations
        );
    }

    /**
     * @return Cart
     */
    private function setCart(): Cart
    {
        return new Cart($this->countryHelper, $this->currencyHelper, $this->sessionHelper, $this->storeTranslations);
    }

    /**
     * @return Hooks
     */
    private function setHooks(): Hooks
    {
        return new Hooks(
            $this->adminHook,
            $this->blocksHook,
            $this->cartHook,
            $this->checkoutHook,
            $this->endpointsHook,
            $this->gatewayHook,
            $this->optionsHook,
            $this->orderHook,
            $this->orderMetaHook,
            $this->pluginHook,
            $this->productHook,
            $this->scriptsHook,
            $this->templateHook
        );
    }

    private function setHelpers(): Helpers
    {
        return new Helpers(
            $this->actionsHelper,
            $this->cacheHelper,
            $this->cartHelper,
            $this->countryHelper,
            $this->creditsEnabledHelper,
            $this->currencyHelper,
            $this->currentUserHelper,
            $this->gatewaysHelper,
            $this->imagesHelper,
            $this->linksHelper,
            $this->nonceHelper,
            $this->noticesHelper,
            $this->paymentMethodsHelper,
            $this->requesterHelper,
            $this->sessionHelper,
            $this->stringsHelper,
            $this->urlHelper
        );
    }

    private function setDownloader(): Downloader
    {
        return new Downloader($this->logs, $this->currentUserHelper);
    }
}

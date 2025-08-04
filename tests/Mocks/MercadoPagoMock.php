<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Configs\Metadata;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\Order\OrderBilling;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderShipping;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Helpers\Actions;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Cart;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\CreditsEnabled;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Helpers\Images;
use MercadoPago\Woocommerce\Helpers\Links;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Helpers\Notices;
use MercadoPago\Woocommerce\Helpers\PaymentMethods;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Helpers\Strings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Hooks;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Blocks;
use MercadoPago\Woocommerce\Hooks\Cart as CartHook;
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
use MercadoPago\Woocommerce\IO\Downloader;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use Mockery;

class MercadoPagoMock
{
    static function getWoocommerceMercadoPagoMock(): WoocommerceMercadoPago
    {
        $mock = Mockery::mock(WoocommerceMercadoPago::class);
        $mock->woocommerce = Mockery::mock('WooCommerce');

        // Hooks mocks
        $mock->hooks = Mockery::mock(Hooks::class);
        $mock->hooks->admin = Mockery::mock(Admin::class);
        $mock->hooks->blocks = Mockery::mock(Blocks::class);
        $mock->hooks->cart = Mockery::mock(CartHook::class);
        $mock->hooks->checkout = Mockery::mock(Checkout::class);
        $mock->hooks->endpoints = Mockery::mock(Endpoints::class);
        $mock->hooks->gateway = Mockery::mock(Gateway::class);
        $mock->hooks->options = Mockery::mock(Options::class);
        $mock->hooks->order = Mockery::mock(Order::class);
        $mock->hooks->orderMeta = Mockery::mock(OrderMeta::class);
        $mock->hooks->plugin = Mockery::mock(Plugin::class);
        $mock->hooks->product = Mockery::mock(Product::class);
        $mock->hooks->scripts = Mockery::mock(Scripts::class);
        $mock->hooks->template = Mockery::mock(Template::class);

        // Helpers mocks
        $mock->helpers = Mockery::mock(Helpers::class);
        $mock->helpers->actions = Mockery::mock(Actions::class);
        $mock->helpers->cache = Mockery::mock(Cache::class);
        $mock->helpers->cart = Mockery::mock(Cart::class);
        $mock->helpers->country = Mockery::mock(Country::class);
        $mock->helpers->creditsEnabled = Mockery::mock(CreditsEnabled::class);
        $mock->helpers->currency = Mockery::mock(Currency::class);
        $mock->helpers->currentUser = Mockery::mock(CurrentUser::class);
        $mock->helpers->gateways = Mockery::mock(Gateways::class);
        $mock->helpers->images = Mockery::mock(Images::class);
        $mock->helpers->links = Mockery::mock(Links::class);
        $mock->helpers->nonce = Mockery::mock(Nonce::class);
        $mock->helpers->notices = Mockery::mock(Notices::class);
        $mock->helpers->paymentMethods = Mockery::mock(PaymentMethods::class);
        $mock->helpers->requester = Mockery::mock(Requester::class);
        $mock->helpers->session = Mockery::mock(Session::class);
        $mock->helpers->strings = Mockery::mock(Strings::class);
        $mock->helpers->url = Mockery::mock(Url::class);
        $mock->helpers->numbers = Mockery::mock(Numbers::class);

        // Settings mocks
        $mock->settings = Mockery::mock(Settings::class);
        $mock->settings->admin = Mockery::mock(Admin::class);
        $mock->settings->endpoints = Mockery::mock(Endpoints::class);
        $mock->settings->links = Mockery::mock(Links::class);
        $mock->settings->order = Mockery::mock(Order::class);
        $mock->settings->plugin = Mockery::mock(Plugin::class);
        $mock->settings->scripts = Mockery::mock(Scripts::class);
        $mock->settings->seller = Mockery::mock(Seller::class);
        $mock->settings->store = Mockery::mock(Store::class);
        $mock->settings->translations = Mockery::mock(AdminTranslations::class);
        $mock->settings->url = Mockery::mock(Url::class);
        $mock->settings->nonce = Mockery::mock(Nonce::class);
        $mock->settings->currentUser = Mockery::mock(CurrentUser::class);
        $mock->settings->session = Mockery::mock(Session::class);
        $mock->settings->logs = Mockery::mock(Logs::class);
        $mock->settings->downloader = Mockery::mock(Downloader::class);
        $mock->settings->funnel = Mockery::mock(Funnel::class);
        $mock->settings->strings = Mockery::mock(Strings::class);

        // Metadata config mocks
        $mock->metadataConfig = Mockery::mock(Metadata::class);
        $mock->metadataConfig->options = Mockery::mock(Options::class);

        // Seller config mocks
        $mock->sellerConfig = Mockery::mock(Seller::class);
        $mock->sellerConfig->cache = Mockery::mock(Cache::class);
        $mock->sellerConfig->options = Mockery::mock(Options::class);
        $mock->sellerConfig->requester = Mockery::mock(Requester::class);
        $mock->sellerConfig->store = Mockery::mock(Store::class);
        $mock->sellerConfig->logs = Mockery::mock(Logs::class);

        // Store config mocks
        $mock->storeConfig = Mockery::mock(Store::class);
        $mock->storeConfig->options = Mockery::mock(Options::class);

        // Logs mocks
        $mock->logs = Mockery::mock(Logs::class);
        $mock->logs->file = Mockery::mock(File::class);
        self::setMocksForLogFunctions($mock->logs->file);
        $mock->logs->remote = Mockery::mock(Remote::class);

        // Order metadata mocks
        $mock->orderMetadata = Mockery::mock(OrderMetadata::class);
        $mock->orderMetadata->orderBilling = Mockery::mock(OrderBilling::class);

        // Country mocks
        $mock->country = Mockery::mock(Country::class);
        $mock->country->seller = Mockery::mock(Seller::class);

        $mock->orderShipping = Mockery::mock(OrderShipping::class);
        $mock->orderBilling = Mockery::mock(OrderBilling::class);
        $mock->orderStatus = Mockery::mock(OrderStatus::class);
        $mock->adminTranslations = Mockery::mock(AdminTranslations::class);
        self::setMocksForAdminTranslations($mock->adminTranslations);
        $mock->storeTranslations = Mockery::mock(StoreTranslations::class);
        self::setMocksForStoreTranslations($mock->storeTranslations);

        return $mock;
    }

    private static function setMocksForLogFunctions($mockFile)
    {
        $mockFile
            ->shouldReceive('info')
            ->andReturn(null);

        $mockFile
            ->shouldReceive('error')
            ->andReturn(null);

        $mockFile
            ->shouldReceive('debug')
            ->andReturn(null);

        $mockFile
            ->shouldReceive('notice')
            ->andReturn(null);

        $mockFile
            ->shouldReceive('warning')
            ->andReturn(null);
    }

    private static function setMocksForAdminTranslations($mockTranslations)
    {
        $mockTranslations->refund = [
            'amount_must_be_positive'   => 'The amount entered for the refund must be greater than zero. Please enter the amount you need to refund.',
            'forbidden'                 => 'Something went wrong. Please contact the Mercado Pago support team and we will help you resolve it.',
            'insufficient_funds'        => 'You do not have sufficient balance in your account. To make the refund, please deposit money in your account.',
            'internal_server_error'     => 'Something went wrong. The refund could not be processed at this time. Please try again later.',
            'invalid_payment_status'    => 'You can only refund a payment that has already been approved. Please wait for approval and try again.',
            'invalid_refund_amount'     => 'The requested refund amount is greater than the total amount of the order. Please check the amount and try again.',
            'invalid_request'           => 'Something went wrong. Please contact the Mercado Pago support team and we will help you resolve it.',
            'no_permission'             => 'You do not have permission to process a refund. Please check your access to the site and try again.',
            'not_found'                 => 'The refund could not be processed. Please try again or contact the Mercado Pago support team.',
            'supertoken_not_supported'  => 'This payment was made using Fast Pay with Mercado Pago and does not yet support refunds through the WooCommerce order page. Please process the refund directly from your Mercado Pago payment details page.',
            'payment_not_found'         => 'The refund could not be processed. Please try again or contact the Mercado Pago support team.',
            'payment_too_old'           => 'This payment is too old to be refunded. If you need help, please contact the Mercado Pago support team.',
            'unauthorized'              => 'Your access credentials are incorrect or have expired. Please renew your credentials in the Mercado Pago settings and try again.',
            'unknown_error'             => 'Something went wrong. Please contact the Mercado Pago support team and we will help you resolve it.',
        ];
    }

    private static function setMocksForStoreTranslations($mockTranslations)
    {
        $mockTranslations->commonMessages = [];
        $mockTranslations->buyerRefusedMessages = [];
        $mockTranslations->commonMessages['invalid_users'] = 'error';
        $mockTranslations->commonMessages['invalid_operators'] = 'error';
        $mockTranslations->buyerRefusedMessages['buyer_default'] = 'error';
    }
}

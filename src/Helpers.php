<?php

namespace MercadoPago\Woocommerce;

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

if (!defined('ABSPATH')) {
    exit;
}

class Helpers
{
    public Actions $actions;

    public Cache $cache;

    public Cart $cart;

    public Country $country;

    public CreditsEnabled $creditsEnabled;

    public Currency $currency;

    public CurrentUser $currentUser;

    public Gateways $gateways;

    public Images $images;

    public Links $links;

    public Nonce $nonce;

    public Notices $notices;

    public PaymentMethods $paymentMethods;

    public Requester $requester;

    public Session $session;

    public Strings $strings;

    public Url $url;

    public function __construct(
        Actions $actions,
        Cache $cache,
        Cart $cart,
        Country $country,
        CreditsEnabled $creditsEnabled,
        Currency $currency,
        CurrentUser $currentUser,
        Gateways $gateways,
        Images $images,
        Links $links,
        Nonce $nonce,
        Notices $notices,
        PaymentMethods $paymentMethods,
        Requester $requester,
        Session $session,
        Strings $strings,
        Url $url
    ) {
        $this->actions        = $actions;
        $this->cache          = $cache;
        $this->cart           = $cart;
        $this->country        = $country;
        $this->creditsEnabled = $creditsEnabled;
        $this->currency       = $currency;
        $this->currentUser    = $currentUser;
        $this->gateways       = $gateways;
        $this->images         = $images;
        $this->links          = $links;
        $this->nonce          = $nonce;
        $this->notices        = $notices;
        $this->paymentMethods = $paymentMethods;
        $this->requester      = $requester;
        $this->session        = $session;
        $this->strings        = $strings;
        $this->url            = $url;
    }
}

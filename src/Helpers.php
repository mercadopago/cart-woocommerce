<?php

namespace MercadoPago\Woocommerce;

use MercadoPago\Woocommerce\Helpers\Actions;
use MercadoPago\Woocommerce\Helpers\Cache;
use MercadoPago\Woocommerce\Helpers\Cart;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\CreditsEnabled;
use MercadoPago\Woocommerce\Helpers\Currency;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
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
    /**
     * @var Actions
     */
    public $actions;

    /**
     * @var Cache
     */
    public $cache;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var Country
     */
    public $country;

    /**
     * @var CreditsEnabled
     */
    public $creditsEnabled;

    /**
     * @var Currency
     */
    public $currency;

    /**
     * @var CurrentUser
     */
    public $currentUser;

    /**
     * @var Images
     */
    public $images;

    /**
     * @var Links
     */
    public $links;

    /**
     * @var Nonce
     */
    public $nonce;

    /**
     * @var Notices
     */
    public $notices;

    /**
     * @var PaymentMethods
     */
    public $paymentMethods;

    /**
     * @var Requester
     */
    public $requester;

    /**
     * @var Session
     */
    public $session;

    /**
     * @var Strings
     */
    public $strings;

    /**
     * @var Url
     */
    public $url;

    public function __construct(
        Actions $actions,
        Cache $cache,
        Cart $cart,
        Country $country,
        CreditsEnabled $creditsEnabled,
        Currency $currency,
        CurrentUser $currentUser,
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

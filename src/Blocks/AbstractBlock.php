<?php

namespace MercadoPago\Woocommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoPaymentBlockInterface;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractBlock extends AbstractPaymentMethodType implements MercadoPagoPaymentBlockInterface
{
    /**
     * @const
     */
    public const ACTION_SESSION_KEY = 'mercadopago_blocks_action';

    /**
     * @const
     */
    public const GATEWAY_SESSION_KEY = 'mercadopago_blocks_gateway';

    /**
     * @const
     */
    public const CHOSEN_PM_SESSION_KEY = 'chosen_payment_method';

    /**
     * @const
     */
    public const UPDATE_CART_NAMESPACE = 'mercadopago_blocks_update_cart';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $scriptName = '';

    /**
     * @var array
     */
    protected $settings = [];

    /**
     * @var WoocommerceMercadoPago
     */
    protected $mercadopago;

    /**
     * @var MercadoPagoGatewayInterface
     */
    protected $gateway;

    /**
     * @var array
     */
    protected $links;

    /**
     * @var object
     */
    protected $storeTranslations;

    /**
     * AbstractBlock constructor
     */
    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;
        $this->gateway     = $this->setGateway();
        $this->links       = $this->mercadopago->helpers->links->getLinks();

        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);
        $this->mercadopago->hooks->blocks->registerBlocksEnqueueCheckoutScriptsBefore([$this, 'resetCheckoutSession']);
        $this->mercadopago->hooks->blocks->registerBlocksUpdated(self::UPDATE_CART_NAMESPACE, [$this, 'updateCartToRegisterDiscountAndCommission']);
    }

    /**
     * Deletes session data
     *
     * @return void
     */
    public function resetCheckoutSession()
    {
        $this->mercadopago->helpers->session->deleteSession(self::ACTION_SESSION_KEY);
        $this->mercadopago->helpers->session->deleteSession(self::GATEWAY_SESSION_KEY);
        $this->mercadopago->helpers->session->deleteSession(self::CHOSEN_PM_SESSION_KEY);
    }

    /**
     * Initializes the payment method type
     *
     * @return void
     */
    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
    }

    /**
     * Returns if this payment method should be active
     *
     * @return boolean
     */
    public function is_active(): bool
    {
        return isset($this->gateway) && $this->gateway->isAvailable();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method
     *
     * @return array
     */
    public function get_payment_method_script_handles(): array
    {
        if (!$this->gateway) {
            return [];
        }

        $scriptName = sprintf('wc_mercadopago_%s_blocks', $this->scriptName);
        $scriptPath = $this->mercadopago->helpers->url->getPluginFileUrl("build/$this->scriptName.block", '.js', true);
        $assetPath  = $this->mercadopago->helpers->url->getPluginFilePath("build/$this->scriptName.block.asset", '.php', true);

        $version = '';
        $deps    = [];

        if (file_exists($assetPath)) {
            $asset   = require $assetPath;
            $version = $asset['version'] ?? '';
            $deps    = $asset['dependencies'] ?? [];
        }

        $this->gateway->registerCheckoutScripts();
        $this->mercadopago->hooks->scripts->registerPaymentBlockScript($scriptName, $scriptPath, $version, $deps);
        $this->gateway->registerCheckoutScripts();
        return [$scriptName];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script
     *
     * @return array
     */
    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->get_supported_features(),
            'params'      => $this->getScriptParams(),
        ];
    }

    /**
     * Returns an array of supported features
     *
     * @return array
     */
    public function get_supported_features(): array
    {
        return isset($this->gateway) ? $this->gateway->supports : [];
    }

    /**
     * Set block payment gateway
     *
     * @return ?AbstractGateway
     */
    public function setGateway(): ?AbstractGateway
    {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways       = $payment_gateways_class->payment_gateways();

        return isset($payment_gateways[$this->name]) ? $payment_gateways[$this->name] : null;
    }

    /**
     * Set payment block script params
     *
     * @return array
     */
    public function getScriptParams(): array
    {
        return [];
    }

    /**
     * Set selected gateway from blocks on session and update WC_Cart
     *
     * @param mixed $data
     *
     * @return void
     */
    public function updateCartToRegisterDiscountAndCommission($data)
    {
        $action  = $data['action'] ?? '';
        $gateway = $data['gateway'] ?? '';

        if (empty($action) || empty($gateway)) {
            return;
        }

        $this->mercadopago->helpers->session->setSession(self::ACTION_SESSION_KEY, $action);
        $this->mercadopago->helpers->session->setSession(self::GATEWAY_SESSION_KEY, $gateway);

        $this->mercadopago->helpers->cart->calculateTotal();
    }

    /**
     * Register plugin and commission to WC_Cart fees
     *
     * @return void
     */
    public function registerDiscountAndCommissionFeesOnCart()
    {
        // Avoid to add fees before WooCommerce Blocks load
        if ($this->mercadopago->hooks->checkout->isCheckout() || $this->mercadopago->hooks->cart->isCart()) {
            return;
        }

        if (isset($this->gateway)) {
            $action  = $this->mercadopago->helpers->session->getSession(self::ACTION_SESSION_KEY);

            if ($action == 'add') {
                $this->mercadopago->helpers->cart->addDiscountAndCommissionOnFeesFromBlocks($this->gateway);
            }

            if ($action == 'remove') {
                $this->mercadopago->helpers->cart->removeDiscountAndCommissionOnFeesFromBlocks($this->gateway);
            }
        }
    }
}

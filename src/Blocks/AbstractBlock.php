<?php

namespace MercadoPago\Woocommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoPaymentBlockInterface;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractBlock extends AbstractPaymentMethodType implements MercadoPagoPaymentBlockInterface {
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
     * @var array
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
        $this->links       = $this->mercadopago->links->getLinks();
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
        return $this->gateway->isAvailable();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method
     *
     * @return array
     */
    public function get_payment_method_script_handles(): array
    {
        $componentsName   = 'wc_mercadopago_checkout_components';
        $componentsStyle  = $this->mercadopago->url->getPluginFileUrl('assets/css/checkouts/mp-plugins-components', '.css');
        $componentsScript = $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/mp-plugins-components', '.js');

        $variables  = $this->getScriptParams();
        $scriptName = sprintf('wc_mercadopago_%s_blocks', $this->scriptName);
        $scriptPath = $this->mercadopago->url->getPluginFileUrl("build/$this->scriptName.block", '.js', true);
        $assetPath  = $this->mercadopago->url->getPluginFilePath("build/$this->scriptName.block.asset", '.php', true);

        $version = '';
        $deps    = [];

        if (file_exists($assetPath)) {
            $asset   = require $assetPath;
            $version = $asset['version'] ?? '';
            $deps    = $asset['dependencies'] ?? [];
        }
        
        $this->mercadopago->scripts->registerCheckoutStyle($componentsName, $componentsStyle);
        $this->mercadopago->scripts->registerCheckoutScript($componentsName, $componentsScript);
        $this->mercadopago->scripts->registerPaymentBlockScript($scriptName, $scriptPath, $version, $deps, $variables);

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
        ];
    }

    /**
     * Returns an array of supported features
     *
     * @return array
     */
    public function get_supported_features(): array
    {
        return $this->gateway->supports;
    }

    /**
     * Set block payment gateway
     *
     * @return MercadoPagoGatewayInterface
     */
    public function setGateway(): MercadoPagoGatewayInterface
    {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways       = $payment_gateways_class->payment_gateways();

        return $payment_gateways[$this->name];
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
}
# Plugins Enablers (Woocommerce)

[![made-with-Php](https://img.shields.io/badge/Made%20with-Php-1f425f.svg)](https://www.php.net/) [![php-version](https://img.shields.io/badge/Php->=7.2-1f425f)]()

The base project for the Plugins Enablers Initiative.

## Install

Open your terminal and execute the script below in your wp-content/plugins to generate a new plugin:

````
plugin_base_dir="woocommerce-plugins-enablers" \
git clone --quiet --recursive -j8 git@github.com:mercadolibre/fury_woocommerce-plugins-enablers.git $plugin_base_dir \
&& cd $plugin_base_dir && composer i && sleep .1 \
&& composer i -d "./packages/sdk/"
````

## Getting started

The purpose of this tutorial is to support the creation and integration of payment gateway plugins to the base plugin.

#### Creating and integrating a payment gateway to the base plugin

To create a payment gateway plugin, you need to create it as an additional plugin that communicates with Woocommerce. As part of the plugin creation process, it is necessary to create a main class that must add the Wordpress loading hook to call the method that allows the plugin to be recognized as a Woocommerce payment gateway plugin.

````
add_action('wp_loaded', initGateway);
````

To create the payment gateway, it is necessary that the gateway class extends the Woocommerce gateway class, allowing the inheritance of gateway methods.

````
class ExampleGateway extends \WC_Payment_Gateway {}
````

*For more information about the WC_Payment_Gateway class, you can access the [documentation](https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html).*

After creating and defining your gateway class, you need to make Woocommerce know about it through the filter of woocommerce_payment_gateways.

````
function initGateway() {
    global $mercadopago;
    $mercadopago->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\ExampleGateway');
}
````

As in the example above, the mercadopago is a global variable that allows integration between the base plugin and the payment gateway plugins. Global variables can be accessed from anywhere in the script, i.e. inside and outside the function.

*To learn more about how to structure your gateway class, access the official [Woocommerce documentation](https://woocommerce.com/document/payment-gateway-api/).*



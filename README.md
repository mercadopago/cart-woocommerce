# Plugins Enablers (Woocommerce)

[![made-with-Php](https://img.shields.io/badge/Made%20with-Php-1f425f.svg)](https://www.php.net/) [![php-version](https://img.shields.io/badge/Php->=7.2-1f425f)]()

The base project for the Plugins Enablers Initiative.

## Install

Open your terminal and run the script below in your ````wp-content/plugins```` to install the base plugin.

````
plugin_base_dir="woocommerce-plugins-enablers" \
&& git clone --quiet --recursive -j8 git@github.com:mercadolibre/fury_woocommerce-plugins-enablers.git $plugin_base_dir \
&& cd $plugin_base_dir && composer i && composer i -d "./packages/sdk/"
````

## Getting started

The purpose of this tutorial is to support the creation and integration of payment gateway plugins to the base plugin.

#### Creating and integrating a payment gateway to the base plugin

It is necessary that the creation of the payment gateway plugin be done along the lines of an additional Woocommerce plugin, and as part of the process, we must create a main class that will add the Wordpress loading hook to call the method that allows the plugin to be recognized as a Woocommerce payment gateway plugin.

````
add_action('wp_loaded', 'initPaymentGateway');
````

In addition, we must create a custom class to extend Woocommerce gateway class, allowing inheritance of gateway methods and the [configs api](https://woocommerce.com/document/settings-api/).

````
class MPGateway extends \WC_Payment_Gateway {}
````

*To learn more about how to structure your gateway class, access the official [Woocommerce documentation](https://woocommerce.com/document/payment-gateway-api/).*

After creating and defining your gateway class, you need to make Woocommerce aware of it through the woocommerce_payment_gateways filter. It is the responsibility of the base plugin to abstract Woocommerce resources, so to add our gateway class to the filter, just reference the registerGateway method.

```
function initPaymentGateway() {
    global $mercadopago;
    $mercadopago->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\MPGateway');
}
````

As you can see in the example above, ````mercadopago```` is a global variable that represents an instance of the base plugin, global variables can be accessed from anywhere, that is, inside and outside the plugin. This variable allows payment gateway plugins to access resources of the base plugin.


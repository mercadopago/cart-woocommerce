<p align="center"><a href="https://www.mercadopago.com/">    <img src="https://http2.mlstatic.com/frontend-assets/ui-navigation/5.18.3/mercadopago/logo__large@2x.png" height="80" width="auto" alt="MercadoPago">
</a></p>

<p align="center">
<img src="https://img.shields.io/wordpress/plugin/v/woocommerce-mercadopago" alt="version">
<img src="https://img.shields.io/wordpress/plugin/dt/woocommerce-mercadopago" alt="download">
<img src="https://img.shields.io/github/license/mercadopago/cart-woocommerce" alt="license">
</p>

The Mercado Pago payments for WooCommerce plugin allows you to expand the functionalities of your online store and offer a unique payment experience for your customers.

<br/>

## Documentation in English

For a better experience, you will be redirected to our site by clicking on the links below:

* [Introduction](https://www.mercadopago.com.ar/developers/en/guides/plugins/woocommerce/introduction/)
* [Installation](https://www.mercadopago.com.ar/developers/en/docs/woocommerce/how-tos/install-module-manually)
* [Integration](https://www.mercadopago.com.ar/developers/en/docs/woocommerce/integration-configuration/plugin-configuration)
* [Payments configuration](https://www.mercadopago.com.ar/developers/en/docs/woocommerce/payments-configuration)
* [Test and receive payments](https://www.mercadopago.com.ar/developers/en/docs/woocommerce/integration-test)

## Documentación en Español

Para una mejor experiencia, será redirigido a nuestro sitio haciendo clic en los links a abajo:

* [Introducción](https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/introduction/)
* [Instalación](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/how-tos/install-module-manually)
* [Integración](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-configuration/plugin-configuration)
* [Preferencias de pago](https://www.mercadopago.com.ar/developers/es/docs/woocommerce/payments-configuration)
* [Prueba y recibe pagos](https://www.mercadopago.com.ar/developers/e/docs/woocommerce/integration-test)

## Documentação em Português

Para uma melhor experiência, você será redirecionado para o nosso site, clicando nos links abaixo:

* [Introdução](https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/introduction/)
* [Instalação](https://www.mercadopago.com.br/developers/pt/docs/woocommerce/how-tos/install-module-manually)
* [Integração](https://www.mercadopago.com.br/developers/pt/docs/woocommerce/integration-configuration/plugin-configuration)
* [Preferências de pagamento](https://www.mercadopago.com.br/developers/pt/docs/woocommerce/payments-configuration)
* [Teste e receba pagamentos](https://www.mercadopago.com.br/developers/pt/docs/woocommerce/integration-test)

*To learn more about how to structure your gateway class, access the official [Woocommerce documentation](https://woocommerce.com/document/payment-gateway-api/).*

After creating and defining your gateway class, you need to make Woocommerce aware of it through the woocommerce_payment_gateways filter. It is the responsibility of the base plugin to abstract Woocommerce resources, so to add our gateway class to the filter, just reference the registerGateway method.

```
function initPaymentGateway() {
    global $mercadopago;
    $mercadopago->hooks->gateway->registerGateway('MercadoPago\Woocommerce\Gateways\MPGateway');
}
````

As you can see in the example above, ````mercadopago```` is a global variable that represents an instance of the base plugin, global variables can be accessed from anywhere, that is, inside and outside the plugin. This variable allows payment gateway plugins to access resources of the base plugin.

Something's wrong? [Get in touch with our support](https://www.mercadopago.com.ar/developers/en/support)

# WooCommerce - Mercado Pago Module (v1.0.0)
---

* [Features](#features)
* [Available versions](#available_versions)
* [Installation](#installation)
* [Configuration](#configuration)

<a name="features"></a>
##Features##
**Standard checkout**

This feature allows merchants to have a standard checkout. It includes features like
customizations of title, description, category, and external reference, integrations via
iframe, modal, and redirection, with configurable auto-returning, max installments and
payment method exclusion setup, and sandbox/debug options.

*Available for Argentina, Brazil, Chile, Colombia, Mexico and Venezuela*

<a name="available_versions"></a>
##Available versions##
<table>
  <thead>
    <tr>
      <th>Plugin Version</th>
      <th>Status</th>
      <th>WooCommerce Compatible Versions</th>
    </tr>
  <thead>
  <tbody>
    <tr>
      <td><a href="https://github.com/marcelohama/cart-woocommerce">v1.0.0</a></td>
      <td>Supported (First Release)</td>
      <td>WooCommerce 2.1.x - 2.5.x</td>
    </tr>
  </tbody>
</table>

<a name="installation"></a>
##Installation##

1. Copy **cart-woocommerce/mercadopago** folder to **[WordPressRootDirectory]/wp-content/plugins/** folder.

2. On your store administration, go to **Plugins** option in sidebar.

3. Search by **WooCommerce Mercado Pago** and click enable. <br />
You will receive the following message: "Plugin enabled." as a notice in your WordPress.

<a name="configuration"></a>
##Configuration##

1. Go to **WooCommerce > Configuration > Checkout Tab > Mercado Pago**. <br />
Fist of all, you need to configure your client credentials. To make it, fill your **Client_id**, **Client_secret** in Mercado Pago Credentials section.
	
	![Installation Instructions](/README.img/wc_setup_credentials.png) <br />
	
	You can obtain your **Client_id** and **Client_secret**, accordingly to your country, in the following links:

	* Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones
	* Brazil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
	* Chile: https://www.mercadopago.com/mlc/herramientas/aplicaciones
	* Colombia: https://www.mercadopago.com/mco/herramientas/aplicaciones
	* Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones
	* Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones

2. Other general configurations. <br />
	* **Instant Payment Notification (IPN) URL**
	![Installation Instructions](/README.img/wc_setup_ipn.png) <br />
	The highlighted URL is where you will get notified about payment updates.<br /><br />
	* **Checkout Options**
	![Installation Instructions](/README.img/wc_setup_checkout.png) <br />
	**Title**: This is the title of the payment option that will be shown to your customers;<br />
	**Description**: This is the description of the payment option that will be shown to your customers;<br />
	**Store Category**: Sets up the category of the store;<br />
	**Store Identificator**: A prefix to identify your store, when you have multiple stores for only one Mercado Pago account;<br />
	**Integration Method**: How your customers will interact with Mercado Pago to pay their orders;<br />
	**iFrame Width**: The width, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**iFrame Height**: The height, in pixels, of the iFrame (used only with iFrame Integration Method);<br />
	**Auto Return**: If set, the platform will return to your store when the payment is approved.<br /><br />
	* **Payment Options**
	![Installation Instructions](/README.img/wc_setup_payment.png) <br />
	**Max Installments**: The maximum installments allowed for your customers;<br />
	**Exclude Payment Methods**: Select the payment methods that you want to not work with Mercado Pago.<br /><br />
	* **Test and Debug Options**
	![Installation Instructions](/README.img/wc_setup_testdebug.png) <br />
	**Mercado Pago Sandboxs**: Test your payments in Mercado Pago sandbox environment;<br />
	**Debug and Log**: Enables/disables system logs.<br />
	
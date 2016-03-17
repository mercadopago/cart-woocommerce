# WooCommerce - MercadoPago Module (v1.0.0 - first release)
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
      <th>Compatible Versions</th>
    </tr>
  <thead>
  <tbody>
    <tr>
      <td><a href="https://github.com/marcelohama/cart-woocommerce">v1.0.0</a></td>
      <td>First Release</td>
      <td>WooCommerce 2.1.x - 2.5.x</td>
    </tr>
  </tbody>
</table>

<a name="installation"></a>
##Installation##

1. Copy **cart-woocommerce/mercadopago** folder to **[WordPressRootDirectory]/wp-content/plugins/** folder.

2. On your store administration, go to **Plugins** item in sidebar.

3. Search by **WooCommerce Mercado Pago Oficial** and click enable. <br />
You will receive the following message: "Plugin enabled." as a notice in your WordPress.

<a name="configuration"></a>
##Configuration##

1. Go to **WooCommerce > Configuration > Checkout Tab > Mercado Pago**. <br />
Set your **Client_id**, **Client_secret** accordingly to your country:

	* Argentina: https://www.mercadopago.com/mla/herramientas/aplicaciones
	* Brazil: https://www.mercadopago.com/mlb/ferramentas/aplicacoes
	* Chile: https://www.mercadopago.com/mlc/herramientas/aplicaciones
	* Colombia: https://www.mercadopago.com/mco/herramientas/aplicaciones
	* Mexico: https://www.mercadopago.com/mlm/herramientas/aplicaciones
	* Venezuela: https://www.mercadopago.com/mlv/herramientas/aplicaciones

2. General configurations. <br />
	* **URL de Notificações Instantâneas de Pagamento (IPN)**: The highlighted URL is where you will get notified about payment updates;
	* **Title**: This is the title of the payment option that will be shown to your customers;
	* **Description**: This is the description of the payment option that will be shown to your customers;
	* **Store Category**: Sets up the category of the store;
	* **Store Identificator**: A prefix to identify your store, when you have multiple stores for only one Mercado Pago account;
	* **Integration Method**: How your customers will interact with Mercado Pago to pay their orders;
	* **iFrame Width**: The width, in pixels, of the iFrame (used only with iFrame Integration Method);
	* **iFrame Height**: The height, in pixels, of the iFrame (used only with iFrame Integration Method);
	* **Auto Return**: If set, the platform will return to your store when the payment is approved;
	* **Max Installments**: The maximum installments allowed for your customers;
	* **Exclude Payment Methods**: Select the payment methods that you want to not work with Mercado Pago;
	* **Mercado Pago Sandboxs**: Test your payments in Mercado Pago sandbox environment;
	* **Debug and Log**: Enables/disables system logs.

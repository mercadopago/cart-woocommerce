=== Woo Mercado Pago Module Oficial ===
Contributors: mercadopago, mercadolivre
Donate link: https://www.mercadopago.com.br/developers/
Tags: ecommerce, mercadopago, woocommerce
Requires at least: WooCommerce 2.1.x
Tested up to: WooCommerce 2.5.x
Stable tag: 2.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This is the oficial module of Mercado Pago for WooCommerce plugin.

== Description ==

This module enables WooCommerce to use Mercado Pago as a payment Gateway for purchases made in your e-commerce store.

= Why chose Mercado Pago =
Mercado Pago owns the highest security standards with PCI certification level 1 and a specialized internal team working on fraud analysis. With Mercado Pago, you will be able to accept payments from the most common brands of credit card, offer purchase installments options and receive your payment with antecipation. You can also enable your customers to pay in the web or in their mobile devices.

= Mercado Pago Main Features =
* Online and real-time processment through IPN mechanism;
* High approval rate with a robust fraud analysis;
* Potential new customers with a base of more than 120 millions of users in Latin America;
* PCI Level 1 Certification;
* Support to major credit card brands;
* Payment installments;
* Anticipation of receivables in D+2 or D+14 (According to Mercado Pago terms and conditions);
* Payment in one click with Mercado Pago basic and custom checkouts;
* Payment via tickets;
* Seller's Protection Program.

== Installation ==

You have two ways to install this module: from your WordPress Store, or by downloading and manually copying the module directory.

= Install from WordPress =
1. On your store administration, go to **Plugins** option in sidebar;
2. Click in **Add New** button and type "Woo Mercado Pago Module" in the **Search Plugins** text field. Press Enter;
3. You should find the module read to be installed. Click install.

= Manual Download =
1. Get the module sources from a repository (<a href="https://github.com/mercadopago/cart-woocommerce/archive/master.zip">Github</a> or <a href="https://downloads.wordpress.org/plugin/woo-mercado-pago-module.2.1.3.zip">WordPress Plugin Directory</a>);
2. Unzip the folder and find "woo-mercado-pago-module" directory;
3. Copy "woo-mercado-pago-module" directory to **[WordPressRootDirectory]/wp-content/plugins/** directory.

To confirm that your module is really installed, you can click in **Plugins** item in the store administration menu, and check your just installed module. Just click **enable** to activate it and you should receive the message "Plugin enabled." as a notice in your WordPress.

= Configuration =
1. On your store administration, go to **WooCommerce > Settings > Checkout** tab. In **Checkout Options**, you can find configurations for **Mercado Pago - Basic Checkout**, **Mercado Pago - Custom Checkout**, and **Mercado Pago - Ticket**.
	* To get your **Client_id** and **Client_secret** for your country, you can go to:
		* Argentina: https://www.mercadopago.com/mla/account/credentials?type=basic
		* Brazil: https://www.mercadopago.com/mlb/account/credentials?type=basic
		* Chile: https://www.mercadopago.com/mlc/account/credentials?type=basic
		* Colombia: https://www.mercadopago.com/mco/account/credentials?type=basic
		* Mexico: https://www.mercadopago.com/mlm/account/credentials?type=basic
		* Peru: https://www.mercadopago.com/mpe/account/credentials?type=basic
		* Venezuela: https://www.mercadopago.com/mlv/account/credentials?type=basic
	* And to get your **Public Key**/**Access Token** you can go to:
		* Argentina: https://www.mercadopago.com/mla/account/credentials?type=custom
		* Brazil: https://www.mercadopago.com/mlb/account/credentials?type=custom
		* Chile: https://www.mercadopago.com/mlc/account/credentials?type=custom
		* Colombia: https://www.mercadopago.com/mco/account/credentials?type=custom
		* Mexico: https://www.mercadopago.com/mlm/account/credentials?type=custom
		* Peru: https://www.mercadopago.com/mpe/account/credentials?type=custom
		* Venezuela: https://www.mercadopago.com/mlv/account/credentials?type=custom
2. For the solutions **Mercado Pago - Basic Checkout**, **Mercado Pago - Custom Checkout**, and **Mercado Pago - Ticket**, you can:
	* Enable/Disable you plugin (for all solutions);
	* Set up your credentials (Client_id/Client_secret for Basic, Public Key/Access Token for Custom and Ticket);
	* Check your IPN URL, where you will get notified about payment updates (for all solutions);
	* Set the title of the payment option that will be shown to your customers (for all solutions);
	* Set the description of the payment option that will be shown to your customers (for all solutions);
	* Set the description that will be shown in your customer's invoice (for Custom and Ticket);
	* Enable coupon of campaigns for discounts (for Custom and Ticket);
	* Set binary mode that when charging a credit card, only [approved] or [reject] status will be taken (only for Custom);
	* Set the category of your store (for all solutions);
	* Set a prefix to identify your store, when you have multiple stores for only one Mercado Pago account (for all solutions);
	* Define how your customers will interact with Mercado Pago to pay their orders (only for Basic);
	* Configure the after-pay return behavior (only for Basic);
	* Configure the maximum installments allowed for your customers (only for Basic);
	* Configure the payment methods that you want to not work with Mercado Pago (only for Basic);
	* Enable currency conversion (for all solutions);
	* Enable/disable sandbox mode, where you can test your payments in Mercado Pago sandbox environment (for Basic and Custom);
	* Enables/disable system logs (for all solutions).

= In this video, we show how you can install and configure from your WordPress store =

[youtube https://www.youtube.com/watch?v=CgV9aVlx5SE]

== Frequently Asked Questions ==

= What is Mercado Pago? =
Please, take a look: https://vimeo.com/125253122

= Any questions? =
Please, check our FAQ at: https://www.mercadopago.com.br/ajuda/

== Screenshots ==

1. `Custom Checkout`

2. `One Click Payment`

3. `Tickets`

4. `Discount Coupon`

5. `Configuration of Basic Checkout`

== Changelog ==

= v2.1.3 (15/09/2016) =
* Improvements
	- A few improvements in performance;
	- Improved translations;
	- Improved security with URL access via SSL in all module flow.

= v2.1.2 (18/08/2016) =
* Improvements
	- Improved performance for both client and server sides.
* Bug fixes
	- Fixed the product list for multiple items in Basic Checkout form.

= v2.1.1 (02/08/2016) =
* Improvements
	- Improved log messages when applying discounts;
	- Added a link to reprint ticket in customer account order page.
* Bug fixes
	- Fixed tax fee for shipments.

= v2.1.0 (25/07/2016) =
* Features
	- Mercado Pago Discount Coupon. This feature lets Mercado Pago and merchants to use campaigns of discount created in their Mercado Pago accounts. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=eQ2YYoWvzKQ">Discount Coupons</a>;
	- Currency Conversion. Added an option to try to use Mercado Pago currency ratio, to automatically convert any currencies to supported/used currency.
* Improvements
	- Improved credentials validation algorithm;
	- Improved checkout data, with more clean and sanitized info for product image and description.

= v2.0.5 (07/07/2016) =
* Improvements
	- Improved IPN behavior to handle consistent messages with absent IDs.
* Bug fixes
	- Fixed the informative URL of ticket IPN in admin page.

= v2.0.4 (29/06/2016) =
* Improvements
	- Added a message in admin view when currency is different from used locally (used in credential's country).
* Bug fixes
	- We have wrote a snippet to handle the absent shipment cost problem;
	- Fixed some URLs of the credentials link for Basic Checkout.

= v2.0.3 (21/06/2016) =
* Bug fixes
	- Basic Checkout for WooCommerce v2.6.x. In WooCommerce v2.6.x, there was a bug related with the ampersand char that was wrongly converted to #38; on URLs and breaking the checkout flow. This update should place a fix to this problem.

= v2.0.2 (13/06/2016) =
* Features
	- Rollout to Peru. This plugin is now supporting Peru, which includes Basic Checkout, Custom Checkout, Tickets, and local language translations.
* Bug fixes
	- Fix a PHP version issue. It was reported to us an issue in a function that uses an assign made directly from an array field. This feature is available in PHP 5.4.x or above and we've made an update to support older versions;
	- Fix a tax issue. It wasn't been correctly added to the total value in Mercado Pago gateway.

= v2.0.1 (09/06/2016) =
* Features
	- Customer Cards (One Click Payment). This feature allows customers to proceed to checkout with only one click. As Mercado Pago owns PCI standards, it can securely store credit card sensitive data and so register the customer card in the first time he uses it. Next time the customer comes back, he can use his card again, only by inserting its CVV code. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=_KB8CtDei_4">Custom Checkout + Customer Cards</a>.
* Improvements
	- SSL verifications for custom checkout and ticket. Custom Checkout and Ticket solutions can only be used with SSL certification. As the module behaves inconsistently if there is no SSL, we've put a watchdog to lock the solution if it is active without SSL;
	- Enabling any type of currency without disabling module (now, error message from API). Now, merchants have the option to use currencies of their choices in WooCommerce. Pay attention that Woo Mercado Pago will always set the currency related to the country of the Mercado Pago credentials.

= v2.0.0 (01/06/2016) =
* Features
	- Custom Checkout for LatAm. Offer a checkout fully customized to your brand experience with our simple-to-use payments API. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=_KB8CtDei_4">Custom Checkout + Customer Cards</a>;
	- Ticket for LatAm. Now, customer can pay orders with bank tickets. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=97VSVx5Uaj0">Tickets</a>.
* Improvements
	- Removed possibility to setting supportable but invalid currency. We've made a fix to prevent users to select a valid currency (such as ARS), but for a different country set by credentials origin (such as MLB - Mercado Pago Brazil).

= v1.0.5 (29/04/2016) =
* Improvements
	- Removal of extra shipment setup in checkout view. We have made a workaround to prevent an extra shipment screen to appear;
	- Translation to es_ES. Users can select Spain as module country, and translation should be ok.
* Bug fixes
	- Some bug fixes to stabilize the module.

= v1.0.4 (15/04/2016) =
* Improvements
	- Added a link to module settings page in plugin page. We've increased the module description informations. Also we've put a link to make a vote on us. Please, VOTE US 5 STARS. Any feedback will be welcome!
	- Fixed status change when processing with two cards. When using payments with two cards in Basic Checkout, the flow of order status wasn't correct in some cases when async IPN events occurs. We've made some adjustments to fix it.

= v1.0.3 (23/03/2016) =
* Improving algorithm when processing IPN
	Async calls and processment were refined.

= v1.0.2 (23/03/2016) =
* Bug fixes
	- IPN URL wasn’t triggered when topic=payment. Fixed a bug for some specific IPN messages of Mercado Pago.

= v1.0.1 (23/03/2016) =
* Improvements
	- Added payment ID in order custom fields information. Added some good informations about the payment in the order view;
	- Removed some unused files/code. We've made some code cleaning;
	- Redesign of the logic of preferences when creating cart, separating items. Itens are now separated in cart description. This increases the readability and consistency of informations in API level;
	- Proper information of shipment cost. Previously, the shipment cost was passed together with the cart total order amount.

= v1.0.0 (16/03/2016) =
* Features
	- LatAm Basic Checkout support. Great for merchants who want to get going quickly and easily. This is the basic payment integration with Mercado Pago. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=DgOsX1eXjBU">Basic Checkout</a>;
	- Set of configurable fields and customizations. Title, description, category, and external reference customizations, integrations via iframe, modal, and redirection, with configurable auto-returning, max installments and payment method exclusion setup;
	- Sandbox and debug options. Basicer can test orders by enabling debug mode or using sandbox environment.

== Upgrade Notice ==

If you're migrating from version 1.x.x to 2.x.x, please be sure to make a backup of your site and database, as there are many additional features and modifications between these versions.

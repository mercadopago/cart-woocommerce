=== WooCommerce MercadoPago ===
Contributors: mercadopago, mercadolivre, claudiosanches, marcelohama
Tags: ecommerce, mercadopago, woocommerce
Requires at least: 4.9.7
Tested up to: 4.9.7
Requires PHP: 5.6
Stable tag: 3.0.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offer to your clients the best experience in e-Commerce by using Mercado Pago as your payment method.

== Description ==

This module enables WooCommerce to use Mercado Pago as a payment method for purchases made in your e-commerce store. By offering a nice set of tools like redirect and custom checkouts, support to several card acquires, payments with tickets, subscriptions, coupon of discounts, and many others e-Commerce features, this plugin wants to bring the best experience for your payment checkout.

= Why chose Mercado Pago =
Mercado Pago owns the highest security standards with PCI certification level 1 and a specialized internal team working on fraud analysis. With Mercado Pago, you will be able to accept payments from the most common brands of credit card, offer purchase installments options and receive your payment with antecipation. You can also enable your customers to pay in the web or in their mobile devices.

= Mercado Pago Main Features =
* Online and real-time processment through IPN/Webhook mechanism;
* High approval rate with a robust fraud analysis;
* Potential new customers with a base of more than 120 millions of users in Latin America;
* PCI Level 1 Certification;
* Support to major credit card brands;
* Payment installments;
* Anticipation of receivables in D+2 or D+14 (According to Mercado Pago terms and conditions);
* Payment in one click with Mercado Pago basic and custom checkouts;
* Payment via tickets;
* Subscriptions;
* Seller's Protection Program.

= Compatibility =

- WooCommerce 3.0 or later.

== Installation ==

You have two ways to install this module: from your WordPress Store, or by downloading and manually copying the module directory.

= Install from WordPress =
1. On your store administration, go to *Plugins* option in sidebar;
2. Click in *Add New* button and type "WooCommerce MercadoPago" in the *Search Plugins* text field. Press Enter;
3. You should find the module ready to be installed. Click install. Its done!

= Manual Download =
1. Get the module sources from a repository (<a href="https://github.com/mercadopago/cart-woocommerce/archive/master.zip">Github</a> or <a href="https://br.wordpress.org/plugins/woocommerce-mercadopago/">WordPress Plugin Directory</a>);
2. Unzip the folder and change its name to "woocommerce-mercadopago";
3. Copy "woocommerce-mercadopago" directory to *[WordPressRootDirectory]/wp-content/plugins/* directory. Its done!

To confirm that your module is really installed, you can click in *Plugins* item in the store administration menu, and check your just installed module. Just click *enable* to activate it and you should receive the message "Plugin enabled." as a notice in your WordPress.

= Configuration =
First of all, make sure that WooCommerce MercadoPago plugin is enabled, by clicking in Plugins item on the WordPress sidebar. Then, in the sidebar of WordPress, click in Settings > Mercado Pago option. You should get the page for the main configuration of Mercado Pago. Over there, you can:

- Plugin Status and Payment Options
Is the upper part of the window. Shows platform statuses and system consistency to use this plugin. Also, there are buttons that serves as shortcuts for the payment gateways that are offered. It is a good idea to have all the field with a green-checked icon.

- Basic Checkout & Subscriptions
Here you should place your *Client Id* and *Client Secret* keys, the credentials that uniquely identifies you in Mercado Pago. *Client Id* and *Client Secret* are used for Basic Checkout and Subscriptions payment methods; 
Also, just bellow, you can enable currency conversion mode for sells with Basic Checkout and Subscriptions. Currency conversion is a feature that enables you to set an unsupported currency in WooCommerce while maintaining Mercado Pago as payment method. It will convert the unsupported currency for the currency used in your country. Pay attention that this service converts values on-the-fly in real-time and can bring some additional delay to your server.

- Custom Checkout & Tickets
Here you should place your *Public Key* and *Access Token* keys, the credentials that uniquely identifies you in Mercado Pago. *Public Key* and *Access Token* are used for Custom Checkout and Tickets payment methods; 
Also, just bellow, you can enable currency conversion mode for sells with Custom Checkout and Tickets. Currency conversion is a feature that enables you to set an unsupported currency in WooCommerce while maintaining Mercado Pago as payment method. It will convert the unsupported currency for the currency used in your country. Pay attention that this service converts values on-the-fly in real-time and can bring some additional delay to your server.

- Status Mapping of Payment x Order
Here you can map each payment state to a given order status. Only make changes over here if you're fully aware of what you're doing.

- Store Settings
These fields are general fields of your store.
*Statement Descriptor*: The description that will be shown in your customer's invoice;
*Store Category*: Sets up the category of the store;
*Store Identificator*: A prefix to identify your store, when you have multiple stores for only one Mercado Pago account.

- Test and Debug Options
Offers logging tools so you can analyze problems that may be occurring. Maintain this disabled if working in production with a stable system.

*Take a look in more detailed information in our WIKI: https://github.com/mercadopago/cart-woocommerce/wiki*

= In this video, we show how you can install and configure from your WordPress store =

[youtube https://www.youtube.com/watch?v=fPbMN03NVoU]

== Frequently Asked Questions ==

= What is Mercado Pago? =
Please, take a look: https://vimeo.com/125253122

= Any questions? =
For further information, you can check our FAQ at: https://www.mercadopago.com.br/ajuda/

= Unification of the projects WooCommerce-MercadoPago and Woo-Mercado-Pago-Module =

* Starting from 25/09/2017, the projects Woo-Mercado-Pago-Module and WooCommerce-MercadoPago have merged. All the oficial sources are now hosted in this project.

* If you're migrating between versions 1.x, 2.x or 3.x, please be sure to make a backup of your site and database, as there are many additional features and modifications between these versions.

* Changelog of Woo Mercado Pago Module:
> v2.2.18 (22/11/2017)
Bug fixes: Fixed a bug in the URL of javascript source for light-box window.
> v2.2.17 (13/11/2017)
Improvements: Improved webhook of ticket printing to a less generic one.
Bug fixes: FIxed a bug related to payment status of tickets.
> v2.2.16 (23/10/2017)
Bug fixes: Fixed the absence of [zip_code] field in registered tickets for Brazil.
> v2.2.15 (22/09/2017)
Bug fixes: Synchronizing Mercado Pago account when WooCommerce back-office cancels an order.
Improvements: Added CNPJ document for brazilian tickets; Optimized error tracking.
> v2.2.14 (14/09/2017)
Bug fixes: Fixed a bug in Ticket form related with inconsistent use of variables of Custom Checkout form; Not showing card issuer field for Chile as it is unnecessary.
> v2.2.13 (28/08/2017)
Bug fixes: Fixing a bug in Custom Checkout, that wasn't showing the form.
> v2.2.12 (14/08/2017)
Improvements: Improved layout alignment for custom checkout and tickets; Added a checklist for platform statuses of cURL, SSL and PHP verification; Added the ticket view after the checkout.
Bug fixes: Fixed a bug that was locking inputs in ticket fields for Brazil.
> v2.2.11 (24/07/2017)
Improvements: Improved credential validation algorithm; Added FEBRABAN rules for brazilian tickets.
Bug fixes: Resolved a bug when converting currency.
> v2.2.10 (04/07/2017)
Bug fixes: Fixed a bug in subscriptions, where a recurrent product wasn't possible to be bought if its end-date is blank.
> v2.2.9 (29/06/2017)
Bug fixes: Fixed a bug in Mercado Envios for WooCommerce 3.x, involving use of undeclared variable.
> v2.2.8 (26/06/2017)
Improvements: Integrated error log API. This can help to debug any cURL requests; Increased stability.
> v2.2.7 (01/06/2017)
Improvements: Optimizations in checkout JavaScript; Additional checking for test users within checkout process.
Bug fixes: Properly changing order status when paying with Basic Checkout using two cards.
> v2.2.6 (18/05/2017)
Improvements: Increased stability for internal payment process.
Bug fixes: Fixed a bug related to shipping value not added to total amount; Not showing ticket button when payment method is not applicable; Removed unused snippet from ticket solution, handling an unexpected warning.
> v2.2.5 (08/05/2017)
Bug fixes: Added support for WooCommerce/WordPress functions to handle warnings; Algorithm of Chile/Colombia when removing decimals.
> v2.2.4 (03/05/2017)
Improvements: Increased support to older versions of PHP; Optimized calls of WordPress/WooCommerce specific functions.
> v2.2.3 (02/05/2017)
Bug fixes: Resolved a bug related to the missing menus in Appearance.
> v2.2.2 (27/04/2017)
Improvements: When using Mercado Envios, the plugin now sends an email with tracking ID to the merchant and customer.
Bug fixes: Resolved a bug related with non-persisted data of Simple Products; Resolved the status update for "in_procerss" in the basic checkout.
> v2.2.1 (13/04/2017)
Features: Discount by payment method. Merchants can give a discount to their customers if the payment is made with a given gateway.
Improvements: Support for WooCommerce 3.0.0.
> v2.2.0 (03/04/2017)
Features: Recurrent Payments. This feature allow merchants to create subscriptions and charge their customers periodically. For now, available only to Argentina, Brazil and Mexico.
> v2.1.9 (23/03/2017)
Features: Mercado Envios for Basic Checkout. Now, merchants can use Mercado Envios services to ship products to their customers. For now, only available to Argentina, Brazil, and Mexico.
> v2.1.8 (13/02/2017)
Features: Rollout to Uruguay. This plugin is now supporting Uruguay for Basic Checkout and its local language translations.
Improvements: Conformity with Argentina's E 51/2017 resolution to show up CFT/TEA amounts; Removed decimals from Chile and Colombia currencies, as they aren't used.
Bug fixes: Fixed and improved the coupon algorithm.
> v2.1.7 (12/12/2016)
Bug fixes: When ticket payment method was enabled, the button for print ticket was appearing for other methods.
> v2.1.6 (09/12/2016)
Features: Cancel/Refund API integration. Now, merchants can cancel and refund orders through store back-office. Options available in order details, order actions; Back url (checkout callback) configurable in back-office for basic checkout solution.
Improvements: Added option to select when (payment approval or order generation) to reduce stocks for tickets solution; Payment with ticket with order description at finish.
> v2.1.5 (16/11/2016)
Improvements: Analytics of module settings.
Bug fixes: Fixed issue in ticket solution that was printing [null] in ticket description.
> v2.1.4 (20/10/2016)
Features: Two Card Payment Configuration. Merchants can configure this feature in back-office through settings page.
Improvements: Removed some redundant notice messages; Improved algorithm to process settings page flow and checkout; Refactored code to meet WordPress coding standards.
Bug fixes: Fixed a SSL issue related to ticket solution (the open locker) in gateway selection.
> v2.1.3 (15/09/2016)
Improvements: A few improvements in performance; Improved translations; Improved security with URL access via SSL in all module flow.
> v2.1.2 (18/08/2016)
Improvements: Improved performance for both client and server sides.
Bug fixes: Fixed the product list for multiple items in Basic Checkout form.
> v2.1.1 (02/08/2016)
Improvements: Improved log messages when applying discounts; Added a link to reprint ticket in customer account order page.
Bug fixes: Fixed tax fee for shipments.
> v2.1.0 (25/07/2016)
Features: Mercado Pago Discount Coupon. This feature lets Mercado Pago and merchants to use campaigns of discount created in their Mercado Pago accounts. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=eQ2YYoWvzKQ">Discount Coupons</a>; Currency Conversion. Added an option to try to use Mercado Pago currency ratio, to automatically convert any currencies to supported/used currency.
Improvements: Improved credentials validation algorithm; Improved checkout data, with more clean and sanitized info for product image and description.
> v2.0.5 (07/07/2016)
Improvements: Improved IPN behavior to handle consistent messages with absent IDs.
Bug fixes: Fixed the informative URL of ticket IPN in admin page.
> v2.0.4 (29/06/2016)
Improvements: Added a message in admin view when currency is different from used locally (used in credential's country).
> Bug fixes: We have wrote a snippet to handle the absent shipment cost problem; Fixed some URLs of the credentials link for Basic Checkout.
> v2.0.3 (21/06/2016)
Bug fixes: Basic Checkout for WooCommerce v2.6.x. In WooCommerce v2.6.x, there was a bug related with the ampersand char that was wrongly converted to #38; on URLs and breaking the checkout flow. This update should place a fix to this problem.
> v2.0.2 (13/06/2016)
Features: Rollout to Peru. This plugin is now supporting Peru, which includes Basic Checkout, Custom Checkout, Tickets, and local language translations.
Bug fixes: Fix a PHP version issue. It was reported to us an issue in a function that uses an assign made directly from an array field. This feature is available in PHP 5.4.x or above and we've made an update to support older versions; Fix a tax issue. It wasn't been correctly added to the total value in Mercado Pago gateway.
> v2.0.1 (09/06/2016)
Features: Customer Cards (One Click Payment). This feature allows customers to proceed to checkout with only one click. As Mercado Pago owns PCI standards, it can securely store credit card sensitive data and so register the customer card in the first time he uses it. Next time the customer comes back, he can use his card again, only by inserting its CVV code. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=_KB8CtDei_4">Custom Checkout + Customer Cards</a>.
Improvements: SSL verifications for custom checkout and ticket. Custom Checkout and Ticket solutions can only be used with SSL certification. As the module behaves inconsistently if there is no SSL, we've put a watchdog to lock the solution if it is active without SSL;  Enabling any type of currency without disabling module (now, error message from API). Now, merchants have the option to use currencies of their choices in WooCommerce. Pay attention that Woo Mercado Pago will always set the currency related to the country of the Mercado Pago credentials.
> v2.0.0 (01/06/2016)
Features: Custom Checkout for LatAm. Offer a checkout fully customized to your brand experience with our simple-to-use payments API. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=_KB8CtDei_4">Custom Checkout + Customer Cards</a>; Ticket for LatAm. Now, customer can pay orders with bank tickets. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=97VSVx5Uaj0">Tickets</a>.
Improvements: Removed possibility to setting supportable but invalid currency. We've made a fix to prevent users to select a valid currency (such as ARS), but for a different country set by credentials origin (such as MLB - Mercado Pago Brazil).
> v1.0.5 (29/04/2016)
Improvements: Removal of extra shipment setup in checkout view. We have made a workaround to prevent an extra shipment screen to appear; Translation to es_ES. Users can select Spain as module country, and translation should be ok.
Bug fixes: Some bug fixes to stabilize the module.
> v1.0.4 (15/04/2016)
Improvements: Added a link to module settings page in plugin page. We've increased the module description informations. Also we've put a link to make a vote on us. Please, VOTE US 5 STARS. Any feedback will be welcome! Fixed status change when processing with two cards. When using payments with two cards in Basic Checkout, the flow of order status wasn't correct in some cases when async IPN events occurs. We've made some adjustments to fix it.
> v1.0.3 (23/03/2016)
Improvements: Improving algorithm when processing IPN; Async calls and processment were refined.
> v1.0.2 (23/03/2016)
Bug fixes: IPN URL wasn’t triggered when topic=payment. Fixed a bug for some specific IPN messages of Mercado Pago.
> v1.0.1 (23/03/2016)
Improvements: Added payment ID in order custom fields information. Added some good informations about the payment in the order view; Removed some unused files/code. We've made some code cleaning; Redesign of the logic of preferences when creating cart, separating items. Itens are now separated in cart description. This increases the readability and consistency of informations in API level; Proper information of shipment cost. Previously, the shipment cost was passed together with the cart total order amount.
> v1.0.0 (16/03/2016)
Features: LatAm Basic Checkout support. Great for merchants who want to get going quickly and easily. This is the basic payment integration with Mercado Pago. Want to see how it works on-the-fly? Please check this video: <a href="https://www.youtube.com/watch?v=DgOsX1eXjBU">Basic Checkout</a>; Set of configurable fields and customizations. Title, description, category, and external reference customizations, integrations via iframe, modal, and redirection, with configurable auto-returning, max installments and payment method exclusion setup; Sandbox and debug options. Basicer can test orders by enabling debug mode or using sandbox environment.

== Screenshots ==

1. `Custom Checkout`

2. `One Click Payment`

3. `Tickets & Discounts`

4. `Plugin Options`

== Changelog ==

= v3.0.17 (07/08/2018) =
* Feature All 
  - Adding X Product ID
  - Migration from v0 (collections) to v1
  
= v3.0.16 (20/07/2018) =
* Feature MCO 
  - Adding PSE gateway for Colombia
* Improvements
  - Some code improvements

= v3.0.15 (15/03/2018) =
* Improvements
	- Allowing customization by merchants, in ticket fields (credits to https://github.com/fernandoacosta)
	- Fixed a bug in Mercado Envios processment.

= v3.0.14 (13/03/2018) =
* Improvements
	- Discount and fee by gateway accepts two leading zeros after decimal point;
	- Customers now have the option to not save their credit cards;
	- Checkout banner is now customizable.

= v3.0.13 (01/03/2018) =
* Bug fixes
	- Fixed a bug in modal window for Basic Checkout.

= v3.0.12 (28/02/2018) =
* Improvements
	- Added date limit for ticket payment;
	- Added option for extra tax by payment gateway;
	- Increased stability.

= v3.0.11 (19/02/2018) =
* Improvements
	- Improved feedback messages when an order fails;
	- Improved credential validation for custom checkout by credit cards.

= v3.0.10 (29/01/2018) =
* Improvements
	- Improved layout in Credit Card and Ticket forms;
	- Improved support to WordPress themes.

= v3.0.9 (16/01/2018) =
* Bug fixes
	- Fixed a bug in the URL of product image;
	- Fix count error in sdk (credits to xchwarze).

= v3.0.8 (05/01/2018) =
* Improvements
	- Increased support and handling to older PHP;
	- IPN/Webhook now customizable.

= v3.0.7 (21/12/2017) =
* Improvements
	- Checking presence of older versions to prevent inconsistences.

= v3.0.6 (13/12/2017) =
* Improvements
	- Added validation for dimensions of products;
	- Added country code for analytics.
* Bug fixes
	- Fixed a problem related to the title of payment method, that were in blank when configuring the module for the first time.

= v3.0.5 (22/11/2017) =
* Bug fixes
	- Fixed a bug in the URL of javascript source for light-box window.

= v3.0.4 (13/11/2017) =
* Improvements
	- Improved webhook of ticket printing to a less generic one.
* Bug fixes
	- FIxed a bug related to payment status of tickets.

= v3.0.3 (25/10/2017) =
* Features
	- Rollout to Uruguay for Custom Checkout and Tickets.
* Bug fixes
	- Not showing ticket form when not needed.

= v3.0.2 (19/10/2017) =
* Bug fixes
	- Fixed the absence of [zip_code] field in registered tickets for Brazil.

= v3.0.1 (04/10/2017) =
* Bug fixes
	- We fixed a Javascript problem that are occurring when payments were retried in custom checkout and tickets;
	- Resolved the size of Mercado Pago icon in checkout form.
* Improvements
	- Allowing absence of SSL if debug mode is enabled;
	- Optmizations in form layout of custom checkout and tickets;
	- Validating currency consistency before trying conversions;
	- References to the new docummentations.

= v3.0.0 (25/09/2017) =
* Features
	- All features already present in <a href="https://br.wordpress.org/plugins/woocommerce-mercadopago/">Woo-Mercado-Pago-Module 2.x</a>;
	- Customization of status mappings between order and payments.
* Improvements
	- Added CNPJ document for brazilian tickets;
	- Optimization in HTTP requests and algorithms;
	- Removal of several redundancies;
	- HTML and Javascript separation;
	- Improvements in the checklist of system status;
	- More intuitive menus and admin navigations.

= 2.0.9 (2017/03/21) =
* Improvements
	- Included sponsor_id to indicate the platform to MercadoPago.

= 2.0.8 (2016/10/24) =
* Features
	- Open MercadoPago Modal when the page load;
* Bug fixes
	- Changed notification_url to avoid payment notification issues.

= 2.0.7 (2016/10/21) =
* Bug fixes
	- Improve MercadoPago Modal z-index to avoid issues with any theme.

= 2.0.6 (2016/07/29) =
* Bug fixes
	- Fixed fatal error on IPN handler while log is disabled.

= 2.0.5 (2016/07/04) =
* Improvements
	- Improved Payment Notification handler;
	- Added full support for Chile in the settings.

= 2.0.4 (2016/06/22) =
* Bug fixes
	- Fixed `back_urls` parameter.

= 2.0.3 (2016/06/21) =
* Improvements
	- Added support for `notification_url`.

= 2.0.2 (2016/06/21) =
* Improvements
	- Fixed support for WooCommerce 2.6.

= 2.0.1 (2015/03/12) =
* Improvements
	- Removed the SSL verification for the new MercadoPago standards.

= 2.0.0 (2014/08/16) =
* Features
	- Adicionado suporte para a moeda `COP`, lembrando que depende da configuração do seu MercadoPago para isso funcionar;
	- Adicionado suporte para traduções no Transifex.
* Bug fixes
	* Corrigido o nome do arquivo principal;
	* Corrigida as strings de tradução;
	* Corrigido o link de cancelamento.

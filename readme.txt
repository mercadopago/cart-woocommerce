=== Mercado Pago payments for WooCommerce ===
Contributors: mercadopago, mercadolivre, claudiosanches, marcelohama
Tags: ecommerce, mercadopago, woocommerce
Requires at least: 4.9.10
Tested up to: 5.7
Requires PHP: 5.6
Stable tag: 5.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offer to your clients the best experience in e-Commerce by using Mercado Pago as your payment method.

== Description ==

The official Mercado Pago plugin allows you to process payments for your online store, allowing users to finalize their purchase with their preferred payment method.

To install it, **you don't need to have technical knowledge:** you can follow the [step by step of how to integrate it](https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/introduction/). from our developer website and start selling today.

### What to do with the Mercado Pago Plugin?
* Activate **Checkout Pro** to offer logged-in payments with money in Mercado Pago account, saved cards and off means.
* Offer payments without the need of having a Mercado Pago account, through the **Custom Checkout** for cards and off means (cash or bank transfer).
* Automatically convert the currency of your products: from Mexican pesos to U.S. dollars and vice versa.
* Sell in **installments** and offer the current promotions in Checkout Pro or apply your own discount coupon in Custom Checkout.
* Test your store before going into production with our Sandbox environment.
* **Receive the money** from your sales on the same day.
* **IMPORTANT:** At the moment the Mercado Envíos service is deactivated.

### Adapted to your business

Prepared for any type of store and category: electronics, clothing, kitchen, bazaar, whatever you want!
Just focus on selling and **we'll take care of the security:** keep your store's payments protected with our fraud prevention and analysis tool.

Boost your sales with Mercado Pago payments for WooCommerce!

== Screenshots ==

1. RECEIVE THE MONEY FROM YOUR SALES ON THE SAME DAY
2. This is what the Checkout Pro looks like in your store. You can choose between a modal experience or a redirect experience.
3. This is what the Customized Checkout looks like in your store. You can activate payments with cards and also cash.
4. Once you install the Mercado Pago Plugin, you will find the 3 checkouts available in the Payment settings in WooCommerce. You can activate them simultaneously or choose one of them. Remember that they must be configured before enabling them.
5. To configure it, follow the step by step indicated in each Checkout. Remember that you can review the official documentation of our plugin on the Mercado Pago developer website.
6. ACCEPT ALL PAYMENT METHODS

== Frequently Asked Questions ==

= I had a question during setup, where can I check the documentation? =

In our developer website you will find the step by step of [how to integrate the Mercado Pago Plugin](https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/introduction/) in your online store.

= What are the requirements for the plugin to work properly? =

You must have an SSL certificate, connecting your website with the HTTPS protocol.

If you need to check the protocol configuration, [test it here](https://www.ssllabs.com/ssltest/).

Finally, remember that the plugin receives IPN (Instant Payment Notification) notifications automatically, you don't need to configure it!

= I already finished the configuration but the Sandbox environment is not working. =

Remember that to test the Checkout Pro you must log out of your Mercado Pago account, as it is not possible to use it to sell and buy at the same time.

Please note that with the Test Environment enabled, the Checkout Pro does not send notifications as does the Custom Checkout.

= How do I configure the sending of emails to my customers? =

The configuration of sending emails must be done from the WooCommerce administrator. The Mercado Pago Plugin only contemplates sending transactions made in the Checkout Pro.

= I reviewed the documentation and these FAQs but still have problems in my store, what can I do? =

If you have already reviewed the documentation and have not found a solution, you can contact our support team through their [contact form](https://www.mercadopago.com.ar/developers/es/support/). Please note that we guarantee a response within {7 days} of your query.

= How do I set up PIX as a payment method? =

To enable PIX as a payment method at your store's Customized Cashier, you need to have your key registered with Mercado Pago. [Here's how to do it](https://www.mercadopago.com.br/stop/pix?url=https%3A%2F%2Fwww.mercadopago.com.br%2Fadmin-pix-keys%2Fmy-keys&authentication_mode=required).

After registering the key, access the WooCommerce administrator and go to the **Payments** section.

Look for the **Pay with PIX option**, make the configuration and activate the PIX.

You can define how long customers can pay after receiving the code, among other settings.

== Installation ==

= Minimum Technical Requirements =
* WordPress version
* Compatibility and dependency of WooCommerce VXX
* LAMP Environment (Linux, Apache, MySQL, PHP)
* SSL Certificate
* Additional configuration: safe_mode off, memory_limit higher than 256MB

Install the module in two different ways: automatically, from the “Plugins” section of WordPress, or manually, downloading and copying the plugin files into your directory.

Automatic Installation by WordPress admin
1. Access "Plugins" from the navigation side menu of your WordPress administrator.
2. Once inside Plugins, click on 'Add New' and search for 'Mercado Pago payments for WooCommerce' in the WordPress Plugin list
3. Click on "Install."

Done! It will be in the "Installed Plugins" section and from there you can activate it.

Manual Installation
1. Download the [zip] (https://github.com/mercadopago/cart-woocommerce/archive/master.zip) now or from the o WordPress Module [Directory] (https://br.wordpress.org/plugins/woocommerce-mercadopago/)
2. Unzip the folder and rename it to ”woocommerce-mercadopago”
3. Copy the "woocommerce-mercadopago" file into your WordPress directory, inside the "Plugins" folder.

Done!

= Installing this plugin does not affect the speed of your store! =

If you installed it correctly, you will see it in your list of "Installed Plugins" on the WordPress work area. Please enable it and proceed to your Mercado Pago account integration and setup.

= Mercado Pago Integration =
1. Create a Mercado Pago [seller account](https://www.mercadopago.com.br/registration-company?confirmation_url=https%3A%2F%2Fwww.mercadopago.com.br%2Fcomo-cobrar) if you don't have one yet. It's free and takes only seconds!
2. Get your [credentials](https://www.mercadopago.com.br/developers/pt/guides/localization/credentials), they are the keys that uniquely identify you within the platform.
3. Set checkout payment preferences and make other advanced settings to change default options.
4. Approve your account to go to [Production](https://www.mercadopago.com.br/developers/pt/guides/payments/api/goto-production/) and receive real payments.

=  Configuration =
Set up both the plugin and the checkouts you want to activate on your payment avenue. Follow these five steps instructions and get everything ready to receive payments:

1. Add your **credentials** to test the store and charge with your Mercado Pago account **according to the country** where you are registered.
2. Approve your account in order to charge.
3. Fill in the basic information of your business in the plugin configuration.
4. Set up **payment preferences** for your customers.
5. Access **advanced** plugin and checkout **settings** only when you want to change the default settings.

Check out our <a href="https://www.mercadopago.com.br/developers/pt/plugins_sdks/plugins/official/woo-commerce/">official documentation</a> for more information on the specific fields to configure.

== Changelog ==
= v5.1.0 (29/03/2021) =
* Features
 - Added new Pix Gateway for Brazil
 - Added Payment type at order panel

* Bug fixes
 - Fixed post in configuration page, removed html

= v5.0.1 (10/03/2021) =
* Features
 - Compatibility with old notification urls

= v5.0.0 (24/02/2021) =
* Features
 - Compatibility with WooCommerce v5.0.0
 - Compatibility with WordPress v5.6.2
 - Added Wordpress Code Standard at plugin

* Bug fixes
 - Fixed round amount

= v4.6.4 (11/02/2021) =
* Bug fixes
 - Removed payments methods in option custom checkout OFF

= v4.6.3 (06/01/2021) =
* Features
 - Compatibility with WooCommerce v4.9.2
 - Compatibility with WordPress v5.6.1
 - Added index to all directories for more security

* Bug fixes
 - Fixed wc-api request check when is ?wc_api or wc-api
 - Fixed close of rating notification

= v4.6.2 (06/01/2021) =
* Bug fixes
 - Changed loading of Mercado Pago SDK at custom checkout

= v4.6.1 (04/01/2021) =
* Features
 - Add support to LearnPress
 - Compatibility with Wordpress v5.6 and WooCommerce v4.8
 - Added version in SDK Mercado Pago
 - Added compatibility with WooCommerce Accepted Payment Methods plugin

* Bug fixes
  - Changed event load of credit-card.js in checkout page
  - Changed API to get payment_methods in Checkout Custo Offline and Checkout pro
  - Changed event load in admin payments config
  - Changed name Checkout Mercado Pago to Checkout Pro

= v4.6.0 (01/12/2020) =
* Features
  - Add review rating banner
  - Improve security on checkouts, xss javascript sanitizer
  - Support section block added in checkout settings

* Bug fixes
  - Fixed error that prevents configuring the Mercado Pago plugin

= v4.5.0 (26/10/2020) =
* Features
  - Compatibility with WooCommerce v4.6.x
  - Improved security (added access token in the header for all calls to Mercado Livre and Mercado Pago endpoints)
  - Add new endpoint to validate Access Token and Public key to substitute old process to validation
  - Improved performance with CSS minification

* Bug fixes
  - Fixed conflict with wc-api webhook and Mercado Pago webhook/IPN.
  - Fixed alert in currency conversion
  - Fixed tranlate in currency conversion
  - Bug fixed when updating orders that have two or more payments associated.

* Bug fixes
  - Fixed conflict with wc-api webhook and Mercado Pago webhook/IPN.

= v4.4.0 (21/09/2020) =
* Features
  - Compatibility with WooCommerce v4.5.x

* Bug fixes
  - Adjusted error when shipping is not used

= v4.3.1 (10/09/2020) =
* Bug fixes
  - Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout

= v4.3.0 (31/08/2020) =
* Features
  - Improve plugin initialization
  - Compatibility with Wordpress v5.5 and WooCommerce v4.4.x

* Bug fixes
  - Fixed currency conversion API - Alert added at checkout when currency conversion fails
  - Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout
  - Adjusted translation in general
  - Adjusted currency translation alert

= v4.2.2 (27/07/2020) =
* Features
  - Added feature: cancelled orders on WooCommerce are automatically cancelled on Mercado Pago
  - Compatibility with Wordpress v5.4 and WooCommerce v4.3.x

* Bug fixes
  - Fixed notification bug - No longer updates completed orders
  - Fixed currency conversion API - No longer allows payments without currency conversion
  - Fixed payment procesisng for virtual products
  - Added ABSPATH in every PHP file
  - Adjusted installments translation
  - Adjusted state names for Transparent Checkout in Brazil
  - Adjusted currency translation translations
  - Removed text in code written in Spanish

== Changelog ==
= v4.2.1 (18/05/2020) =
* Bug fixes
  - Corrected CI document input validation on Uruguay Custom Offline Checkout.

= v4.2.0 (13/05/2020) =
* Features
  - Added compatibility with WooCommerce version 4.1.0
  - Added Integrator ID field on checkouts’ configuration screens
  - Added validation for Public Keys
  - Added alert to activate the WooCommerce plugin whenever it is inactive
  - Added alert to install the WooCommerce plugin whenever it is uninstalled
  - Added assets versioning
  - Added minification of JS files
  - Added debug mode for JS in order to use files without minification
  - Added payment flow for WebPay in Chile for Checkout Custom Offline
  - Updated documentation and regionalized links

* Bug fixes
  - Corrected notification status on charged_back
  - Corrected issue when invalid credentials were switched
  - Corrected checkout options for Store Name, Store Category and Store ID
  - Corrected validation on the cardNumber field whenever card number is removed
  - Corrected input masks on CPNJ and CPF; CNPJ validation and translation in Brazil for Custom Checkout Offline;
  - Corrected mercadopago.js loading
  - Corrected processing of payment status notifications
  - Corrected personalized URLs for successful, refused and pending payments on Checkout Mercado Pago
  - Added success and error messages on received payment notifications
  - Added alphabetical order on offline payment methods for Checkout Custom
  - Added CI document input on Custom Checkout OFF in Uruguay
  - Added compatibility with third-party discount plugins which attribute value on order->fees (computation of fees_cost upon purchase)
  - Added validation, focus and error messages on all JS inputs on Checkout Custom Online and Offline
  - Usability improvements for Checkout Custom - Credit Card on mobile devices
  - Adjusted error messages on online Checkout Custom Online
  - Adjusted status updates on Checkout Custom Offline orders
  - Updated documentation and guide links

= v4.1.1 (10/01/2020) =
* Feature
  - Currency Conversion in Checkout Mercado Pago added

* Bug fixes
  - Currency Conversion for CHO Custom ON and OFF fixed
  - Shipping Cost in the creation of Preferences fixed
  - ME2 shipping mode in the creation of Preferences removed
  - Checkout Mercado Pago class instance fixed when the first configurations are saved

= v4.1.0 (06/01/2020) =
* Feature
  - Updated plugin name from "WooCommerce Mercado Pago" to "Mercado Pago payments for WooCommerce".
  - Feature currency conversion returned.
  - New feature to check if cURL is installed
  - Refactored Javascript code for custom checkout Debit and credit card. Performance improvement, reduced number of SDK calls. Fixed validation errors. Javascript code refactored to the order review page. Removed select from mexico payment method.

* Bug fixes
  - Fixed credential issue when the plugin is upgraded from version 3.x.x to 4xx. Unable to save empty credential.
  - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
  - Fixed error: Undefined index: MLA in WC_WooMercadoPago_Credentials.php on line 163.
  - Fixed error: Call to a member function analytics_save_settings() in WC_WooMercadoPago_Hook_Abstract.php on line 68. Has affected users that cleared the credential and filled new credential production.
  - Fixed load of WC_WooMercadoPago_Module.php file.
  - Fixed error Uncaught Error: Call to a member function homologValidate().
  - Fixed error Undefined index: section in WC_WooMercadoPago_PaymentAbstract.php on line 303. Affected users who did not have homologous accounts
  - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
  - Fixed issue to calculate commission and discount.
  - Fixed Layout of checkout custom input.
  - Fixed translation ES of Modo Producción, Habilitá and definí
  - Fixed Uncaught Error call to a member function update_status() in WC_WooMercadoPago_Notification_Abstract.php. Handle Mercado Pago Notification Failures and Exceptions.
  - Fix PT-BR debit card translation on admin.
  - Fix PT-BR debit card translation on checkout.
  - Remove "One Step Checkout" from CHO Custom Off.
  - Remove Mercado Creditos from Custom CHO OFF.
  - Fixed issue to check if WooCommerce plugin is installed

* Break change
  - Removed feature and support to Mercado Envios shipping. Before install the plugin verify if your store has another method of shipping configured.

= v4.0.8 (13/09/2019) =
* Bug fixes
  - Fixed mercado envios
  - Fexed show fee in checkout
  - Fixed translation file
  - Fixed constant file

= v4.0.7 (12/09/2019) =
* Bug fixes
  - Fixed layout incompatibility
  - Fixed process to validate card at custom checkout
  - Fixed payment due at ticket
  - Fixed spanish translation

= v4.0.6 (09/09/2019) =
* Bug fixes
  - Problem with all translations fixed

= v4.0.5 (04/09/2019) =
* Bug fixes
  - Problem with translations in Portuguese fixed

= v4.0.4 (03/09/2019) =
* Bug fixes
  - Conflict between php5.6 and php7 solved

= v4.0.3 (03/09/2019) =
* Bug fixes
  - Fixed basic checkout layout when theme uses bootstrap
  - Fixed all Custom checkout layout when theme uses bootstrap
  - Fixed input blank in basic checkout config

= v4.0.2 (02/09/2019) =
* Feature All
  - Performance improvement
  - UX and UI improvements
  - Code refactoring
  - Design standards: SOLID, Abstract Factory and Singleton
  - SDK Refactor: Avoid repeated external requests.
  - New Credential Validation Logic
  - Plugin Content Review
  - Adjustment in translations
  - Unification of general plugin settings with payment method setup, simplifying setup steps
  - Logs to assist support and integration
* Bug fixes
  - Added product_id
  - Fixed payment account_money
  - Fixed translation Spanish Neutral and Argentino

= v4.0.2-Beta (13/08/2019) =
* Bug fixes
  - Fixed bug when update plugin from version 3.0.17
  - Fixed bug thats change production mode of basic, custom and ticket checkout when update version.
  - Added statement_descriptor in basic checkout
  - Fixed title space checkout custom

= v4.0.1-Beta (09/08/2019) =
* Bug fixes
  - Fixed notification IPN and Webhook
  - Fixed payment processing
  - Fixed Argentina ticket checkout
  - Fixed rule for custom checkout to generate token
  - Fixed layout checkouts

= v4.0.0-Beta (02/08/2019) =
* Feature All
  - Performance improvement
  - UX and UI improvements
  - Code refactoring
  - Design standards: SOLID, Abstract Factory and Singleton
  - SDK Refactor: Avoid repeated external requests.
  - New Credential Validation Logic
  - Plugin Content Review
  - Adjustment in translations
  - Unification of general plugin settings with payment method setup, simplifying setup steps
  - Logs to assist support and integration

= v3.1.1 (03/05/2019) =
* Feature All
  - Added alert message on all ADMIN pages for setting access_token and public_key credentials, as client_id and client_secret credentials will no longer be used. Basic Checkout will continue to work by setting these new credentials.
  - We have added minor translation enhancements.
  - We add error message when any API error occurs while validating credentials.

= v3.1.0 (17/04/2019) =
* Feature All
  - We are no longer using client_id and client_secret credentials. This will affect the functioning of the basic checkout. You will need to configure access_token and public_key, in the plugin settings have. You can access the link to get the credentials inside of configurations of plugin.
* Improvements
  - Performance enhancements have been made, removing unnecessary requests and adding scope limitation for some functionality.

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

=== WooCommerce Mercado Pago ===
Contributors: mercadopago, mercadolivre, claudiosanches, marcelohama
Tags: ecommerce, mercadopago, woocommerce
Requires at least: 4.9.10
Tested up to: 5.2.2
Requires PHP: 5.6
Stable tag: 4.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offer to your clients the best experience in e-Commerce by using Mercado Pago as your payment method.

== Description ==

Mercado Pago leads the technological transformation of finance in Latin America and develops tools to take its collections to another level. Integrate a payment avenue in your website with our official WooCommerce plugin. With out checkout options you can offer the payment methods that everyone prefers, with the best possible financing: purchases in up to 24 installments, credit and debit cards, in-person payments and money in the Mercado Pago account.

= Why choose Mercado Pago? =

We operate in Argentina, Brazil, Mexico, Peru, Chile, Uruguay and Colombia with businesses of all sizes. They choose us, from entrepreneurs who are just beginning to consolidated big brands in the market.

We process more than 6 million transactions with Visa and Mastercard cards every year and we do so protecting your money, always. Accept payments with the most used cards and offer installments with the best possible financing. With our ready-to-use solutions, going further is in your hands.

You can trust us as you trust a strategic partner. Use the money from your sales once it is available in your Mercado Pago account, with our prepaid card, or withdraw it to your bank at no additional cost. View our fees and have the money from your sales instantly.

= Main Functionalities of Mercado Pago =
* Processing via IPN / Webhook online and in real time;
* High approval rates thanks to a robust fraud analysis;
* Potential clients: more than 120 million users in Latin America trust us;
* Sandbox test environment;
* Log and debug options;
* PCI level 1 Certification;
* Accept the payment methods everyone prefers;
* Installment payments;
* Payments in advance from 2 to 14 business days, according to our Terms and Conditions;
* One-click payments with the basic and personalized Mercado Pago checkouts;
* Payments via tickets (Boletos);
* Subscriptions;
* Seller Protection Program.

= Compatibility =
- WooCommerce 3.0 or higher.

== Screenshots ==

1. `Pay with the payment method you prefer`
2. `Pay with the payment method you prefer`
3. `Pay with debit and credit cards`
4. `Pay with debit and credit cards`
5. `Pay with money and cash`
6. `Pay with ticket and Loterica`

== Frequently Asked Questions ==

= How do we protect the sellers =
We take care of the money with maximum security
We help you in case of problems
We protect your sales against chargebacks

= Where can I find the documentation? =
Check out our official documentation for installing and configuring the Mercado Pago plugin in your store.

= Where and how can I contribute? =
Suggest documentation improvement on [our website](https://www.mercadopago.com.br/developers/es/plugins_sdks/plugins/official/woo-commerce/) for integrators and developers,
Subscribe to the [development log](https://plugins.trac.wordpress.org/log/woocommerce-mercadopago/) by [RSS](https://plugins.trac.wordpress.org/log/woocommerce-mercadopago/?limit=100&mode=stop_on_copy&format=rss),
[Review the code](https://plugins.trac.wordpress.org/browser/woocommerce-mercadopago/) and visit the [SVN repository](https://plugins.svn.wordpress.org/woocommerce-mercadopago/),
Help us translate WordPress: [Translate](https://translate.wordpress.org/projects/wp-plugins/woocommerce-mercadopago/) the plugin for your country.

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
2. Once inside Plugins, click on 'Add New' and search for 'Mercado Pago WooCommerce' in the WordPress Plugin list
3. Click on "Install."

Done! It will be in the "Installed Plugins" section and from there you can activate it. 

Manual Installation
1. Download the zip now or from the o WordPress Module Directory
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

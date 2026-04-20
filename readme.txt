=== Mercado Pago payments for WooCommerce ===
Contributors: mercadopago
Tags: ecommerce, mercadopago, woocommerce
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 8.7.18
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offer to your clients the best experience in e-Commerce by using Mercado Pago as your payment method.

== Description ==

The official Mercado Pago plugin allows you to process payments for your online store, allowing users to finalize their purchase with their preferred payment method.

To install it, **you don't need to have technical knowledge:** you can follow the [step by step of how to integrate it](https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/introduction/). from our developer website and start selling today.

**Warning about v6.0.0:** when updating, if you have made custom layout changes to your checkout, it is possible that some of those customizations become misconfigured. If you have a separate store environment just for testing, please update there first in order to visualize and test the changes.

### What to do with the Mercado Pago Plugin?
* Activate **Checkout Pro** to offer logged-in payments with money in Mercado Pago account, saved cards and off means.
* Offer payments without the need of having a Mercado Pago account, through the **Custom Checkout** for cards and off means, such as cash, bank transfer and PIX (only in Brazil).
* Automatically convert the currency of your products: from Mexican pesos to U.S. dollars and vice versa.
* Sell in **installments** and offer the current promotions in Checkout Pro or apply your own discount coupon in Custom Checkout.
* Test your store before going into production with our Sandbox environment.
* **Receive the money** from your sales on the same day.
* **IMPORTANT:** At the moment the Mercado Envios service is deactivated.
* **Mercado Pago customers can use already stored cards** For your customers who use Mercado Pago to buy without having to fill in card details at the store's checkout.

### Adapted to your business

Prepared for any type of store and category: electronics, clothing, kitchen, bazaar, whatever you want!
Just focus on selling and **we'll take care of the security:** keep your store's payments protected with our fraud prevention and analysis tool.

Boost your sales with Mercado Pago payments for WooCommerce!

### Sell more with the paid market

[Leave your details](https://www.mercadopago.com.br/quero-usar/?utm_campaign=%5BMP%20OP%5D%20Core%20Checkouts%202021&utm_source=plugin-woocommerce&utm_medium=plugin&utm_term=plugin-woocommerce&utm_content=plugin-woocommerce) to talk to our team of experts and understand how to sell more (for now only available for Brazil).

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

= How can I set up PIX as a payment method? =

PIX is a payment method that exists only in Brazil.

To enable PIX as a payment method in the Custom Checkout of your store, you need to have your key registered in Mercado Pago. [See how to do it](https://www.mercadopago.com.br/stop/pix?url=https%3A%2F%2Fwww.mercadopago.com.br%2Fadmin-pix-keys%2Fmy-keys&authentication_mode=required).

After registering the key, log into the WooCommerce administrator and navigate to the **Payments** section.

Look for the option **Pague com PIX**, configure it and activate PIX.

You can set up a time limit for customers to pay after they receive the code, among other settings.

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

= v8.7.18 (16/04/2026) =
* Added
- Add ESC (Encrypted Security Code) flow for credit and debit cards to reduce unnecessary CVV prompts and increase payment approvals
- Add lazy getter pattern (getSuperTokenDeps) in MPEventHandler for Fast Payment dependency injection, replacing direct window.* access
- Add Datadog metrics for currency conversion API error tracking and active conversion monitoring
- Add granular error details to woo_checkout_error Datadog metric
- Add Datadog metric to detect null amount in currency conversion flow
- Add metric alert in MPEventHandler when Fast Payment dependencies are incomplete: MP_SUPER_TOKEN_DEPENDENCIES_NOT_SET
- Add metric alert in card form when Fast Payment trigger handler is missing: mp_cardform_trigger_handler_missing

* Changed
- Pre-download 8 popular WooCommerce themes (astra, kadence, oceanwp, blocksy, generatepress, neve, hestia, storefront)
- Add THEME parameter to select active theme on environment setup (`make up THEME=kadence`)
- Auto-reset when theme changes between runs (tracked in .current-site)
- Document top 5 themes per LATAM country in README
- Refactor E2E test suite with per-country auto-configuration (MLB, MLA, MLM, MCO, MLC, MLU, MPE)
- Add global-setup that auto-resets Docker store when country changes and configures MP plugin
- Add support for both Classic and Blocks checkout modes via `CHECKOUT=classic|blocks` env var
- Add credential validation with clear error messages and admin URL for onboarding
- Add site-guard helper to skip tests when store country doesn't match target
- Add CORS fast-fail detection (~5s) for MP sandbox PCI migration issues
- Add E2E Guardian agent that suggests relevant tests based on git diff on push
- Simplify Checkout Pro and Credits tests to verify redirect only (external checkout is not plugin scope)
- Update all flows to support both Classic and Blocks checkout selectors
- Enable 4 parallel Playwright workers for faster local execution
- Reduce global test timeout from 120s to 60s with early validation for missing card data
- Add per-country npm scripts (`test:mlb:classic`, `test:mlb:blocks`, etc.)
- Remove obsolete login/2FA/credits user configs (no longer needed after redirect-only approach)
- Complete README rewrite with onboarding guide, troubleshooting, and credential setup
- Remove unused `Logs` dependency from `Currency` helper

* Fixed
- Fix `_site_id_v1` not set in Docker causing ticket/boleto address form not to render for MLB
- Fix WC state codes for CL (`CL-RM`), CO (`CO-DC`), UY (`UY-MO`) in billing address data
- Fix hidden billing fields (postcode, state) for countries where WC locale hides them (Chile)
- Fix PSE document field name (`docNumber` → `doc_number`) and flow for Classic checkout
- Fix MCO binary_on card key (`masterMCO` → `master`)
- Fix MLU ticket doc type case sensitivity (`Otro` vs `OTRO`) with uppercase fallback
- Fix MPE ticket spec using invalid spread operator for guest user data
- Fix Yape and PSE flows for Classic checkout (label click + `#place_order`)
- Fix Blocks checkout rendering empty page (self-closing tag → full inner blocks structure)
- Fix `wp-env.js` to work inside Docker container (detect `INSIDE_CONTAINER`)
- Fix MP checkout URL regex to match all country TLDs (`.cl`, `.com.br`, `.com.ar`)
- Fix postcode field cleared by WC AJAX after country change
- Suppress non-fatal wp-cli stderr during global setup
- Add skeleton loader on payment method details while verifying CVV requirement via second API call
- Add generation counter pattern to prevent stale ESC responses on rapid payment method switches
- Add skip metric with categorized reason when ESC payment method fetch is not executed in handleWithEscPaymentMethod
- Fix issuer select (Banco Emisor) losing visual styles (border, border-radius, height, font) in production
- Skip caching error responses from `/currency_conversions/search` API to allow retries and consistent error metric reporting
- Fix display of commission in checkout blocks.
- Fix fast payments flow CSS conflict detection never triggering
- Fix single sessionStorage key (`mp_health_css_conflict_sent`) blocking conflict detection of Wallet Button and fast payments flow in the same session
- Fix `waitForMelidata` throwing `TypeError` when `window.melidataReady` is truthy but not-thenable
- Fix return null on stale ESC generation and guard mountSecurityCodeField call

[See changelog for all versions](https://github.com/mercadopago/cart-woocommerce/blob/main/CHANGELOG.md).

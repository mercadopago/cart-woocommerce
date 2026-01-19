# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [8.7.4] 2026-01-19
### Fixed
- Hide error message when updating cart total on fast payments flow.

## [8.7.3] 2026-01-19
### Changed
- Usability improvements on fast payments flow.

## [8.7.2] - 2025-12-08
### Fixed
- Fix issue with error messages being changed to default one

## [8.7.1] - 2025-11-24
### Changed
- Ensure that fast payment flow header position is initial
### Fixed
- Fix installments display when fast payment flow is supported
- Fix card security code validation when fast payment flow is supported

## [8.7.0] - 2025-11-19
### Added
- Call to mercado pago woocommerce scripts
### Changed
- Ensure that card security code will be validated before form submit on fast payment flow.
- Only send pre-load fast payment metric when flow is supported
- Use new layout to show fast payment flow payment methods
- Improvement error message treatment on checkout custom
### Fixed
- Fix checkout custom layout

## [8.6.1] - 2025-11-06
### Added
- Added card form click metric to Checkout API funnel tracking
### Changed
- Improved trigger mechanism for SDK methods in credit card form fields
- Simplified offline payment flow when only one payment method is available
### Fixed
- Fixed error when switching from credit card to ticket payment method during checkout
- Fixed currency display in installments when currency conversion is active
- Fixed redirect to order confirmation page after payment completion
- Fixed duplicate Pay and Cancel buttons on order received page

## [8.6.0] - 2025-10-22
### Added
- Add loader before custom gateway initialization
- Added currency convertion ratio value to orders 
### Changed
- Improve fast payments experience when wallet button is active
- Changed translations of MLM account balance use cases
### Fixed
- Fix set a semicolon on nbsp call on translations
- Fixed currency conversion in refund
- Fixed display unhandled and untranslated error message


## [8.5.6] - 2025-10-10
### Fixed
- Fix installments field validation during payment processing and enhanced error messaging 
- Fix the behavior of the address data field with ticket payment
- Fix error to pay with Yape payment in Order Pay page
- Fix the Terms and Conditions link text in the test mode message in the Yape payment page
- Fix checkout template styles and structures changed to avoid breakages and inconsistencies in checkout display

## [8.5.5] - 2025-10-03
### Fixed
- Fix issue with checkout session data not being properly merged in blocks and classic checkout

## [8.5.4] - 2025-10-02
### Fixed
- Add status validation to prevent unnecessary metadata updates
- Fixed location id in melidata tracks for admin pages
- Fix order-pay form validation error with Checkout Transparent payment method
### Added
- Improve plugin checkout metrics

## [8.5.3] - 2025-09-29
### Fixed
- Wait for security code to be updated before submitting the form on fast payments flow

## [8.5.2] - 2025-09-19
### Fixed
- Register submitWalletButton function along with the php file on classic checkout

## [8.5.1] - 2025-09-19
### Fixed
- Add new Wallet Button behavior on blocks checkout

## [8.5.0] - 2025-09-17
### Changed
- Wallet Button text update
- Behavior change when clicking on Wallet Button to use fast payments flow when active
### Fixed
- When the data does not come as a notification array, transform it into an array

## [8.4.7] - 2025-09-08
### Fixed
- Adopting code style fixes according to QIT (Quality Insights Toolkit)
- Fixed visual conflict in checkout screen image styles
- Fix to check access to fast payments flow when email is updated
- Optimizing the fast payment flow during trigger

## [8.4.6] - 2025-08-25
### Fixed
- Text and translation corrections in the fast payments flow
- Responsiveness improvements in the fast payments flow
- Fixed payment ID storage errors in custom fields metadata for Ticket and PSE payments
- Fixed sync button functionality for refund scenarios failing to update order information via notification API
### Changed
- Changed installments selector for bank interests hint
- Changed installments options for correctly translated texts on installments without fee

## [8.4.5] - 2025-08-18
### Fixed
- Changed the error message when closing the modal in the fast payments flow
- Obtained logged-in users emails as a fallback in the fast payments flow
- Text and translation corrections in the fast payments flow
- Responsiveness improvements in the fast payments flow
- Check if `downloadSelected` button exists before using it

## [8.4.4] - 2025-08-11
### Fixed
- Does not block payment if email is not filled in

## [8.4.3] - 2025-08-11
### Changed
- Ensure that it uses the same SDK instance in transparent checkout
### Fixed
- Prevents clickable areas from being removed when the fast pay flow is not fully loaded

## [8.4.2] - 2025-08-07
### Fixed
- Sync CardForm ID with checkout context for pay-for-order compatibility
- Fast pay saved card texts responsiveness

## [8.4.1] - 2025-08-04
### Changed
- Changes to support prepaid cards in the flow of cards saved in Mercado Pago
### Fixed
- Validation if app_name exists before using it in the logs
- Fixed logo selector and description field hiding prevention in WooCommerce 9.8.5+

## [8.4.0] - 2025-07-24
### Added
- Refund functionality to orders
- Polling functionality for payments with PIX
- Compatibility to PHP v8.4
### Fixed
- Installments dropdown component

## [8.3.3] - 2025-07-18
### Fixed
- Fix loading issue in custom checkout
- Ensure new card form in saved card flow does not have clickable areas

## [8.3.2] - 2025-07-18
### Added
- Add checkout type to order custom fields

### Changed
- Consolidate additional_info items to single item when is Mercado Pago saved card flow

## [8.3.1] - 2025-07-16
### Changed
- Do not display taxes in Argentina when data is not obtained
- Style adjustments to the saved card flow display

### Fixed
- Fix crash when selecting item in dropdown component on iPhone

## [8.3.0] - 2025-07-16
### Added
- Add Mercado Pago saved card flow
- Show Mercado Pago fees in order metadata

## [8.2.0] - 2025-06-30
### Changed:
- Implement consistent Optional Chaining in setHide function
- Eliminate unnecessary temporary variables and improve consistency
- Maintain 100% functional compatibility with WooCommerce
- Changed the Notification route to accept payload as object or as string.
- Changed titles of the Mercado Pagos`s payment methods in the Woocommerce settings page.
### Fixed:
- Improve WooCommerce 9.8.4+ compatibility in collapsible advanced config.
- Add robust null checks and defensive programming.
- Implement early return validation - Add new admin styling functions.

## [8.1.2] - 2025-06-23
### Added:
- Show update link button when credentials api returns status 401

## [8.1.1] - 2025-06-06
### Changed:
- Changed colors of Pix payment method in checkout, success page and admin.
- Changed cancel order button text.
- Updated the get_settings_url function to target the settings for each payment
### Fixed:
- Fixed error when not using decimal points in currency
- Fixed payment information update on retry after rejected
- Fixed text styles and color in admin
- Fixed document identification fields not being populated for credit card transactions
### Added:
- Added function for directing the admin page in onboarding

## [8.1.0] - 2025-05-19
### Changed
- Updated the Mercado Pago branding across all checkouts, admin panel, and success pages.
### Fixed
- Enhanced translation loading for greater consistency across languages.
- Improved compatibility when updating other plugins with Mercado Pago active.
- Optimized saving of payment settings, including better support for environments with PHP debug enabled.
### Added
- Added Appearance theme data to metadata

## [8.0.1] - 2025-05-08
### Fixed
- Improved the way we handle the credentials validation to avoid unnecessary requests.

## [8.0.0] - 2025-05-06
### Added
- Includes new onboarding functionality, to automate credentials insertion.

## [7.10.3] - 2025-04-15
### Fixed
- Ensure that checkout form element have correct ID.

## [7.10.2] - 2025-03-13
### Fixed:
- Changed misspelled property name in the Settings class to ensure correct declaration.

## [7.10.1] - 2025-03-12
### Fixed:
- Adjusted the way we send metrics to our server ensuring that is not necessary to have Checkout API enabled to do so.
- sendMetric function now is agnostic to checkout type (both classic and blocks).
- Added validation to prevent checkout off from being shown to seller and buyer when there’s no payment methods related to this gateway.

## [7.10.0] - 2025-02-24
### Fixed:
- We have adjusted the translations from Wallet Button to Checkout Custom.
### Added:
- Adds the approved payment note for the buyer.
- Adds validation to load scripts only on checkout-related pages.
### Changed:
- We have changed the way the notification cron works to allow the seller to choose the best time to run.

## [7.9.4] - 2025-02-10
### Fixed
- Resolved an issue where stores with a permanent link configuration different from the default could cause errors on order payment links.

## [7.9.3] - 2025-02-04
### Fixed
- Fixed translations for ticket in ES language.
- Fixed order pay payments error with custom checkout method.
- Order update cron now ignores orders with errors after 2 retries.

## [7.9.2] - 2024-12-26
### Fixed
- Addressed the way we create transactions for PagoEfectivo payments to avoid issues with the payment method.

## [7.9.1] - 2024-12-17
### Fixed
- The number input on the ticket row now takes precedence over the "no number" button.
- On checkout, some users were unable to select the payment method on the ticket row; this issue has been fixed.
- Due to compatibility issues with the latest WooCommerce releases, we've updated the method by which we check and load scripts and styles.

## [7.9.0] - 2024-12-16
### Added
- The plugin now explicitly declares its dependency on WooCommerce.
- The ticket row for Brazil now requires the billing address from your customers due to regulatory reasons.
### Fixed
- Improved the reusability of some classes in the codebase.
- Removed unused code from styles, scripts, and PHP files.
- Removed certain images from the plugin and now access them via MercadoLibre's CDN.
- Eliminated unused files from the MercadoPago SDK in the release build.
- Adjusted default credits row name in MLB.
### Changed
- Added a check to ensure wp_query is present before calling is_checkout.
- A warning is now shown when there is an error with installments on card form.
- Combined Spanish translations into a single file.
- Reduced the package size by 6MB (from 7.2mb to 1.2mb).
- GIFs are now loaded from URLs instead of local files in the admin panel.

## [7.8.2] - 2024-11-07
### Fixed
- **Initializing array for transaction listItems** to fix acessing not initialized property.

## [7.8.1] - 2024-10-07

### Added
- **Silence is golden** directive implemented to enhance security for WordPress sites.
- **Sending new field to get the plugin version** from the stores on funnel to improve onboarding.

### Changed
- **Modified the way asset's are loaded into the store.**

### Fixed
- **Resolved issues with loading minified CSS and JS files** in debugging environments.
- **Refined translations for ES-all** languages.

## [7.8.0] - 2024-09-23

### Changed
- **Rebranded and revamped the CreditsGateway**, improving overall user experience and aligning the visual identity with our updated brand guidelines, making it more intuitive and modern.
- **Compressed several images without losing quality**, which reduces the plugin bundle size and leads to faster download and installation times, improving performance without sacrificing visual fidelity.
- **Removed implicit nullable parameter marking** and replaced it with explicit nullable types, enhancing code clarity and reducing potential bugs related to type handling, thus improving code reliability.

### Fixed
- **Corrected the support component's link URL**, ensuring users are directed to the appropriate help resources without encountering broken or incorrect links, improving support accessibility.


## [7.7.0] - 2024-09-11

### Added
- **New payment method:** Implemented Yape Gateway, now available for transactions in Peru.

### Changed
- **Optimized packaging:** Reduced the number of assets included in the plugin's zip package to improve performance and download time.
- **Code enhancement:** The code has been refactored to follow PHP 7.4 best practices by adopting Typed Properties, improving clarity and safety.

### Fixed
- **cardForm issue resolved:** Fixed the error that appeared in the console during the loading of the `cardForm` script used in the Checkout API for cards. The loading process is now handled more efficiently, eliminating the issue.

## [7.6.4] - 2024-07-31
### Fixed:
- Prevents payment from being created if the currency conversion system fails.

## [7.6.3] - 2024-07-29
### Fixed:
- Fix the size of the card logos at checkout.
- Fix the problem of not showing the disclaimer about card fees for some countries.

## [7.6.2] - 2024-07-16
### Added:
- Added validation on user permissions to download plugin logs.
- Increased plugin wordpress tested version to the latest available.
### Fixed:
- Addressed an issue where discount and commission calculations were not being shown correctly on order details.

## [7.6.1] - 2024-06-26
### Fixed:
- Addressed an funnel metrics issue 

## [7.6.0] - 2024-06-26
### Added:
- Integration with [WooCommerce QIT](https://qit.woo.com/docs/). QIT is a testing platform for WordPress Plugins and Themes developed by WooCommerce, allowing developers to run a series of managed tests out-of-the-box. 
- Implementation of a fallback using WP-cron to resolve the issue of stores' orders remaining in pending status. This feature prevents orders from getting stuck in the pending process by actively updating passive orders, ensuring smoother order management.
### Changed:
- Incorporated code quality enhancements based on QIT recommendations.

## [7.5.1] - 2024-06-05
### Fixed:
- Addressed a problem where one could not change the layout to use woocommerce blocks feature, causing even some pages that use blocks beeing unable to load properly.
- Addressed a vulnerability from prior releases that permitted authenticated attackers to access server configuration details from the seller host, ensuring enhanced security measures in the logs download endpoint.

## [7.5.0] - 2024-05-14
### Added:
- Enhanced visual experience: Based on user feedback, we've refined the Credits checkout experience to make it more visually appealing and user-friendly. The modal now provides clearer information, payment methods are displayed more informatively, and tooltips are less intrusive within the store layout.
### Changed:
- Configurable tooltip text: We've introduced a new setting in the Credits checkout that allows sellers to customize the text displayed in the Credits tooltip. This empowers sellers to tailor the checkout experience to their specific brand and messaging.
- Support Component: we've added a parameter to the support access link via the plugin's admin so that we can have metrics on the source of support access
### Fixed:
- Payment method selection bug: We've addressed a bug in the checkout pro process that prevented the selected payment methods from being respected. This ensures that buyers can consistently use their preferred payment options.

## [7.4.0] - 2024-04-25
### Added:
- A system has been implemented to collect metrics for new sellers, with the aim of facilitating the onboarding of these first-time users. These metrics will allow us to generate ideas for improving the relationship between the plugin and the seller during the onboarding process.
### Other improvements:
- Updated dependencies (PHP SDK).

## [7.3.5] - 2024-04-17
### Fixed:
- Checked field on checkout now validated.
- Rollback SDK dependency.

## [7.3.4] - 2024-04-15
### Fixed:
- Thankyou page redirect for PIX and Ticket Payments working again.
- Prevents type error on checkout screen due to amount values.
- Removed console error message when custom checkout is not enabled.

## [7.3.3] - 2024-04-11
### Changed:
- Our latest plugin update (version 7.3.2) aimed to optimize script loading during checkout using the woocommerce_before_checkout_form and woocommerce_blocks_enqueue_checkout_block_scripts_before hooks. However, to ensure compatibility with a wider range of themes, this functionality has been temporarily disabled. We're actively exploring solutions to achieve optimal script loading across all themes and will implement them in a future update.

## [7.3.2] - 2024-04-11
### Added:
- Added session_id to payment creation request header to improve approval rates.

### Changed:
- Checkout Credits component text has been adjusted.

### Fixed:
- Checkout scripts now load only at checkout time, improving overall store performance.
- Partial refunds made through Mercado Pago are now correctly recognized as partial refunds on the platform.
- The IP address issue for some PSE checkout payments in Colombia has been fixed.
- 3DS flow requests for stores running in a directory now work correctly and stores using the domain root still working.
- The Undefined Array Key Method error that occurred for some sellers when they had not configured the checkout pro method (modal or redirect) has been fixed.
- The pix copy-paste button has been fixed.

### Other improvements:
- General code improvements and optimizations.
- Updated dependencies (PHP SDK).

## [7.3.1] - 2024-03-25
### Changed
- Person type update for PSE
- Improve the layout of the admin buttons
- Adjusting the translation of checkout pro for Spanish-speaking countries

## [7.3.0] - 2024-03-20
### Added
- A warning has been added to inform you that activation was successful and that you now need to enter your credentials when activating plugins.
- We have implemented new display rules and improved the layout of the component that requests ratings for the plugin.
- We've added a link to frequently asked questions at the end of each gateway's configuration.
- We've implemented a new support component at the bottom of the plugin's configuration screen. Here you will find information on how to open a ticket.
### Changed
- We've changed the layout of the notice for filling in credentials.
- We've changed the title of the plugin on the administration screen.
- The payment methods have been renamed and are now sorted according to country.
- We have made adjustments to the hierarchy of titles on the administration screen, as well as to its content, including titles and supplementary texts. 
- For step 1, we have replaced the secondary credential query button with a text-link.
### Fixed
- We've solve the problem when the getPaymentMethodsByGatewayOption function returned an stdClass instead of an array when using json_decode.

## [7.2.1] - 2024-02-15
### Added
- We have added a warning banner for when the language configured in worpress does not have the translation in our plugin.
### Changed
- Improvements have been made to the readme and changelog files so that the markdown makes more sense to our users.
- Now our plugin constructs the URLs for the assets using the absolute path instead of the relative path.
- Prevent block scripts from loading on admin screens and do not load block scripts in checkouts that do not use Checkout Blocks.
- We've reduced the size of the metadata sent in the payment.
### Fixed
- We've fixed the currency conversion calculation.

## [7.2.0] - 2024-02-01
### Added
- Introducing Mercado PSE as a new payment method for our users in Colombia.
- Enhanced user experience with the inclusion of informative error messages in case of payment rejection.
### Changed
- Improved layout of the button for consulting reasons for refusal on the order details screen
### Fixed
- Addressed an issue where error messages were not displaying during the submission process, affecting custom, credit, and ticket transactions. Now, users can expect a smoother and more transparent payment experience.
- Error message did not appear in submit, custom credit and ticket.
- Enables the sending of alphanumeric data to the field, holder's document.

## [7.1.1] - 2024-01-15
### Fixed
- Resolved issue where the cart inaccurately displayed discount and commission information.
- Addressed TypeError occurring when attempting to open orders in the admin panel.

## [7.1.0] - 2024-01-11
### Added
- Added compatibility with Woocommerce Blocks, providing a seamless integration for an enriched user experience. Explore the possibilities with [WooCommerce Blocks Documentation](https://woo.com/document/woocommerce-blocks/) for detailed information and advanced features.
### Fixed:
- Resolved an issue preventing the modification of checkout names.
- Resolved an issue that prevented checkout pro in modal mode from working

## [7.0.6] - 2023-12-11
### Fixed
- Addressed a bug where the store discount code was erroneously applied twice. This fix ensures a smoother checkout experience by resolving the double discount issue.

## [7.0.5] - 2023-12-08
### Fixed
- Resolved issues related to discounts and commission calculations, ensuring accurate and reliable results.
- Resolved implementation of instance logs for Seller and Order classes, providing comprehensive tracking and transparency into their respective functionalities.

### Changed
- Enhanced the return value of Metadata getSettings for improved clarity and usability.
- Improved validation of 3D Secure (3DS) fields to facilitate smoother payment processing.
- Conducted a comprehensive review and refinement of checkout items calculation methods to optimize performance and accuracy.

## [7.0.4] - 2023-12-06
### Changed
- Enhanced the notification_url for improved functionality.
- Improved the sanitization of checkout URLs for a more secure experience.
- Updated the initial hook from wc_loaded to plugins_loaded for better integration.

### Fixed
- Ensured the consistent rendering of Pix QR codes on the thank-you page.
- Removed unnecessary sanitization from get_checkout_order_received_url for smoother processing.

## [7.0.3] - 2023-12-05
### Fixed
- Addressed and resolved the issue with Checkout PRO Modal dependency loading for improved efficiency and smoother functionality.

## [7.0.2] - 2023-12-05
### Fixed
- Credits are now enabled by default, streamlining the search for available countries.
- Resolved status sync rendering issues, ensuring seamless compatibility.

## [7.0.1] - 2023-12-05
### Fixed
- Corrected the issue related to shipping rate values not displaying correctly on transparent checkouts. Now, the accurate shipping rates will be transparently presented for a more seamless checkout experience.

## [7.0.0] - 2023-12-04
### Changed
- Conducted a comprehensive overhaul of the entire plugin, implementing full refactoring for improved code quality and maintainability. Our code has fewer branches, which increases maintainability. This refactoring is designed to improve the quality of feature releases and provide developer users with a greater understanding of the code.
- Elevated the functionality and user experience of the additional information node within the plugin by enriching the payment metadata with additional details, strategically aimed at boosting the payment approval rate.
### Fixed
- Consolidated logging behavior in STEP 2 by ensuring that logs are recorded only when the corresponding toggle in the admin( STEP 2 ) is activated. Previously, the plugin recorded logs irrespective of the toggle's status, but with this fix, the toggle now functions correctly.
### Added
- Integrating 3DS 2.0  (3-D Secure Authentication 2.0) technology. This advancement enables the authentication of transactions involving credit and debit cards in e-commerce scenarios, ensuring that the person making the purchase is genuinely the cardholder or has authorized access to the cardholder's accounts for completing the payment. To learn more about integrating 3DS with Mercado Pago, visit the [Mercado Pago DevSite](https://www.mercadopago.com.br/developers/pt/docs/checkout-api/how-tos/integrate-3ds).

## [6.9.3] - 2023-07-13
### Fixed
- Updated the link to obtain credentials on the developer site
- Resolved warning related to the missing return type of the function AbstractCollection::getIterator()
### Changed
- Improved default activation of Credits Gateway

## [6.9.2] - 2023-06-23
### Fixed
- Fixed bug that was causing the admin panel to become unresponsive or inaccessible

## [6.9.1] - 2023-06-16
fix pix renderization

## [6.9.0] - 2023-06-12
### Added
- Add default activation for Credits Gateway when Basic Gateway is enabled
- Declare this plugin compatible with High-Performance Order Storage (HPOS)
### Changed
- Replace post methods with equivalent methods compatible with HPOS
### Fixed
- devsite link's in readme

## [6.8.1] - 2023-05-22

### Fixed
- Rollback version

## [6.8.0] - 2023-05-22

### Added
- Add default activation for Credits Gateway when Basic Gateway is enabled

## [6.7.5] - 2023-05-17

### Fixed
- Fixed property discount_action_url becoming public instead private

## [6.7.4] - 2023-05-15

### Changed
- Changed function from str_contains to strpos to be compatible for WordPress versions lower than 5.9
- Changed the way to handle custom notification url using or not using Mercado Pago default params

## [6.7.3] - 2023-05-02

### Added
- Test compatibility with WooCommerce v7.6
- Test compatibility with WordPress v6.2

### Changed
- process_nonce_validation was removed from process_payment

### Fixed
- Fix http_user_agent log using wp_is_mobile() instead regex

## [6.7.2] - 2023-03-20

### Added
- Add new fields in update-metadata
    - Cho Pro will add the fields: ```installments, transaction_details, total_paid_amount, transaction_amount, last_four_digits, e o  payment_type```
    - Cho Custom will add the fields: ```payment_type e last_four_digits```
    - In future release, all metadata field prefixed with "mp_" will be removed

- Add security js client and retrive session id from MP_DEVICE_SESSION_ID
    - this improvement will increase the credit card approval rate

### Changed
- Interest attached to the order total

### Fixed
- Fix nonce validation when "allow costumers to create an account during checkout" is enabled

## [6.7.1] - 2023-02-15

### Changed
- Changed Credits Tooltips experience
- Changed generic css classes name

### Fixed
- Fixed PIX QR Code generation

## [6.7.0] - 2023-01-23

### Changed
- Added security improvements

## [6.6.0] - 2023-01-11

### Added
- Added manual notification sync
- Added payment ids to order metadata on callback
- Added select-id and hidden-id attributes to document

### Changed
- Changed user permissions needed for security

### Fixed
- Fixed order amount with gateway discount
- Fixed css class assignment to nonexistent element
- Fixed selection of installments when there is an inversion of checkouts

## [6.5.0] - 2022-12-22

### Added
- Added Mercado Credits payment method
- Added user permissions needed (administrator or editor) for security

### Changed
- Documented use of a 3rd Party or external service
- Improved Checkout Pro layout

### Fixed
- Removed nonce validation from checkouts to use WC nonce validation

## [6.4.1] - 2022-12-14

### Added
- Added bank interest disclaimer

### Changed
- Removed CURLOPT_SSL_VERIFYPEER flag to get server default value

## [6.4.0] - 2022-12-07

### Added
- Added Mercado Pago PHP SDK
- Added min width for checkout select inputs
- Added nonce validation to avoid CSRF Vulnerabilities

### Changed
- Changed notification flow to use PHP SDK
- Changed payments and preferences flow to use PHP SDK

### Fixed
- Fixed plugin translations
- Fixed plugin configuration page links

## [6.3.1] - 2022-10-13

### Changed
- Improved discarded notification response
- Removed loader and timeout on custom checkout

### Fixed
- Fixed the notification rule to allow an approved payment to be updated if the order status is on hold.

## [6.3.0] - 2022-09-27

### Added
- Added interest information on the order confirmation screen for payments with custom checkout

### Fixed
- Fixed timeout and error display in custom checkout
- Removed hyphen from zip code to display correct address for payments with ticket checkout
- Alignment of expiration and security code fields in custom checkout

## [6.2.0] - 2022-09-13

### Added
- Added Mercado Credits tooltip
- Added loader on custom checkout to avoid timeout, handle and show errors on screen
- Added validation on REST Client to avoid return empty array on requests response

### Changed
- Changed Wallet Button layout to encourage more usage

### Fixed
- Fixed email sending method for order placed with PIX

## [6.1.0] - 2022-08-22

### Added
- Added notices scripts on plugin
- Added validation to avoid installments equal to zero
- Added trigger to payment_method_selected event if it not triggered on checkout custom load
- Added rule in notification to allow an approved payment to update if order status is pending, on_hold or failed
- Added client to handle caronte scripts success and error

### Changed
- Removed the test credentials requirement to configure the plugin
- Adjusted credential saving flow to avoid saving two public_key or access_token
- Changed how to load melidata script on window.load
- Send email from Pix and QRCode only for orders with pending status
- Audited npm packages

### Fixed
- Fixed plugin and platform version on melidata client
- Fixed order status when a partial refund is made
- Fixed currency conversion value to display at checkout

## [6.0.2] - 2022-07-13

### Added
- Added preg_replace for notification external params

## [6.0.1] - 2022-06-27

### Added
- Added validation to invalid length on cardNumber to not clear or remove fields

## [6.0.0] - 2022-06-22

### Added
- Added ideal checkout template
- Added secure inputs for Checkout Custom

### Changes
- Updated melidata script to load only on plugin pages

## [5.8.0] - 2022-06-07

### Added
- Added melidata script to collect metrics from plugin

### Changes
- Changed mp logo

## [5.7.6] - 2022-04-19

### Changed Bug fixes

- Adjusted IPN notification to recognize discount coupon
- Added coupon information in order details
- Changed default value of checkout ticket date_expiration

## [5.7.5] - 2022-03-31

### Changed Bug fixes

- Instance a non-static class to call a method (Fatal error on PHP 8)

## [5.7.4] - 2022-02-25

### Changed Bug fixes

- Changed php constant

## [5.7.3] - 2022-02-16

### Changed Bug fixes

- fixed cho pro excluded payments
- fixed cho ticket excluded payments
- validate if has a checkout prod set all to prod
- fixed mp order screen

## [5.7.2] - 2022-02-14

### Changed Bug fixes

- Using Jquery from wp.ajax

## [5.7.1] - 2022-02-14

### Changed Bug fixes

- Adjusted js and css load of mercado pago pool
- Repass all active gateways

## [5.7.0] - 2022-02-14

### Added

- Redesign Admin
- Performance improvements
- Added research in the Mercado Pago plugin configuration pages

### Changed

- Adjusted the css of payment ticket images and text

## [5.6.1] - 2022-01-11

### Changed

- Set important to Mercado Pago inputs, to prevent ghost input type
- Updated Mercado Pago's logo images

## [5.6.0] - 2021-12-01

### Added

- Support to PayCash in Mexico
- Simplified filling for ticket

### Changed

- Adjusted term and conditions CSS
- Admin Order Details validation if is Mercado Pago order
- Updated develop dependencies

## [5.5.0] - 2021-10-19

### Added

- Render pix image from backend for e-mails
- Added link to terms and conditions of Mercado Pago on checkout screen

### Changed

- Fixed retry payment

## [5.4.1] - 2021-09-22

### Changed

- On the order page, the payment was fetched with the wrong token
- When the plugin was updated the checkout mode visually went to test

## [5.4.0] - 2021-09-20

### Added

- Performance improvements
- Improved status of declined payments
- Improvements in store test flow
- Improved text distribution in the Wallet Button alert
- Inclusion of interest-free installment button in payment settings (PSJ)
- Inclusion of Pix code on the customer panel for later consultation
- Inclusion of visual information on the status of the credential
- Adding more QR Code expiration options to the PIX

### Changed

- Fix QR Code breaking email layout

## [5.3.1] - 2021-08-12

### Changed

- Adjusted notification url, checking if it's a friendly url or not

## [5.3.0] - 2021-08-10

### Changed

- Credentials order on painel

### Added

- The seller can change checkout names

## [5.2.1] - 2021-07-28

### Changed

- Return of blank space validation in PHP CodeSniffer
- Adjusting all files that had the wrong spaces

## [5.2.0] - 2021-07-26

### Added

- New payment method Wallet Button (wallet purchase)
- Added support to PHP 8
- Added support to PHPUnit
- Added support to source_news in notification

### Changed

- Changed pix e-mail template
- Removed gulp dependency
- New pre-commit hooks

## [5.1.1] - 2021-04-22

### Added

- Added WooCommerce linter

## [5.1.0] - 2021-03-29

### Added

- Added new Pix Gateway for Brazil
- Added Payment type at order panel

### Changed

- Fixed post in configuration page, removed html

## [5.0.1] - 2021-03-10

### Added

- Compatibility with old notification urls

## [5.0.0] - 2021-02-24

### Added

- Compatibility with WooCommerce v5.0.0
- Compatibility with WordPress v5.6.2
- Added Wordpress Code Standard at plugin

### Changed

- Fixed round amount

## [4.6.4] - 2021-02-11

### Changed

- Removed payments methods in option custom checkout OFF

## [4.6.3] - 2021-02-03

### Added

- Compatibility with WooCommerce v4.9.2
- Compatibility with WordPress v5.6.1
- Added index to all directories for more security

### Changed

- Fixed wc-api request check when is ?wc_api or wc-api
- Fixed close of rating notification

## [4.6.2] - 2021-01-06

### Changed

- Changed loading of Mercado Pago SDK at custom checkout.

## [4.6.1] - 2021-01-04

### Added

- Add support to LearnPress
- Compatibility with Wordpress v5.6 and WooCommerce v4.8
- Added version in SDK Mercado Pago
- Added compatibility with WooCommerce Accepted Payment Methods plugin

### Changed

- Changed event load of credit-card.js in checkout page
- Changed API to get payment_methods in Checkout Custo Offline and Checkout pro
- Changed event load in admin payments config
- Changed name Checkout Mercado Pago to Checkout Pro

## [4.6.0] - 2020-12-01

### Added

- Add review rating banner
- Improve security on checkouts, xss javascript sanitizer
- Support section block added in checkout settings

### Changed

- Fixed error that prevents configuring the Mercado Pago plugin

## [4.5.0] - 2020-10-26

### Added

- Compatibility with WooCommerce v4.6.x
- Improved security (added access token in the header for all calls to Mercado Livre and Mercado Pago endpoints)
- Add new endpoint to validate Access Token and Public key to substitute old process to validation
- Improved performance with CSS minification

### Changed

- Fixed conflict with wc-api webhook and Mercado Pago webhook/IPN.
- Fixed alert in currency conversion
- Fixed tranlate in currency conversion
- Bug fixed when updating orders that have two or more payments associated.

## [4.4.0] - 2020-09-21

### Added

- Compatibility with WooCommerce v4.5.x

### Changed

- Adjusted error when shipping is not used

## [4.3.1] - 2020-09-10

### Changed

- Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout

## [4.3.0] - 2020-08-31

### Added

- Improve plugin initialization
- Compatibility with Wordpress v5.5 and WooCommerce v4.4.x

### Changed

- Fixed currency conversion API - Alert added at checkout when currency conversion fails
- Adjusted inventory (for canceled orders) on payments made at the personalized offline checkout
- Adjusted translation in general
- Adjusted currency translation alert

## [4.2.2] - 2020-07-27

### Added

- Added feature: cancelled orders on WooCommerce are automatically cancelled on Mercado Pago
- Compatibility with Wordpress v5.4 and WooCommerce v4.3.x

### Changed

- Fixed notification bug - No longer updates completed orders
- Fixed currency conversion API - No longer allows payments without currency conversion
- Fixed payment procesisng for virtual products
- Added ABSPATH in every PHP file
- Adjusted installments translation
- Adjusted state names for Transparent Checkout in Brazil
- Adjusted currency translation translations
- Removed text in code written in Spanish

## [4.2.1] - 2020-05-18

### Changed

- Corrected CI document input validation on Uruguay Custom Offline Checkout.

## [4.2.0] - 2020-05-13

### Added

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

### Changed

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

## [4.1.1] - 2020-01-10

### Added

- [PPP-155] Currency Conversion in Checkout Mercado Pago added

### Changed

- [PPP-154] Currency Conversion for CHO Custom ON and OFF fixed
- [PPP-156] Shipping Cost in the creation of Preferences fixed
- [PPP-156] ME2 shipping mode in the creation of Preferences removed
- [PPP-44] Checkout Mercado Pago class instance fixed when the first configurations are saved

## [4.1.0] - 2020-01-06

### Added

- [PLUG-473] CHANGELOG.md added in repository.
- [PLUG-456] Feature currency conversion returned.
- [PLUG-467] New feature to check if cURL is installed

### Changed

- Updated plugin name from "WooCommerce Mercado Pago" to "Mercado Pago payments for WooCommerce".
- [PLUG-459]
    - Fixed credential issue when the plugin is upgraded from version 3.x.x to 4xx. Unable to save empty credential.
    - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
    - Fixed error: Undefined index: MLA in WC_WooMercadoPago_Credentials.php on line 163.
    - Fixed error: Call to a member function analytics_save_settings() in WC_WooMercadoPago_Hook_Abstract.php on line 68. Has affected users that cleared the credential and filled new credential production.
    - Fixed load of WC_WooMercadoPago_Module.php file.
    - Fixed error Uncaught Error: Call to a member function homologValidate().
    - Fixed error Undefined index: section in WC_WooMercadoPago_PaymentAbstract.php on line 303. Affected users who did not have homologous accounts
    - Fixed issue to validate credential when checkout is active. The same problem occurs when removing the enabled checkout credential.
    - Fixed issue to calculate commission and discount.
    - Fixed issue on methadata.
    - Fixed Layout of checkout custom input.
    - Fixed translation of Modo Producción, Habilitá and definí
- [PLUG-459-2] Refactored Javascript code for custom checkout Debit and credit card. Performance improvement, reduced number of SDK calls. Fixed validation errors. Javascript code refactored to the order review page. Removed select from mexico payment method.
- [PLUG-462]
    - Fixed Uncaught Error call to a member function update_status() in WC_WooMercadoPago_Notification_Abstract.php. Handle Mercado Pago Notification Failures and Exceptions.
    - Fixed Uncaught Error call to a member function update_status() in WC_WooMercadoPago_Notification_Abstract.php. Handle Mercado Pago Notification Failures and Exceptions.
- [PLUG-463]
    - Remove Mercado Creditos from Custom CHO OFF.
    - Fix PT-BR debit card translation on admin.
    - Fix PT-BR debit card translation on checkout.
    - Remove "One Step Checkout" from CHO Custom Off.
- [PLUG-466] Removed feature and support to Mercado Envios shipping. Before install the plugin verify if your store has another method of shipping configured.
- [PLUG-470] Fixed issue to check if WooCommerce plugin is installed
- [PLUG-455] Curl Validation.
- [PLUG-474] Removed mercadoenvios/WC_WooMercadoPago_Product_Recurrent.php file.

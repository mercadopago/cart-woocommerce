---
type: Architecture
version: d3bc3f29
validated: 2026-08-12
update_when: when folder layout, layers, or entrypoints change
scope:
  - src/
  - assets/
  - packages/narciso/
  - templates/
  - woocommerce-mercadopago.php
---

# Architecture

## Folder layout

| Path | Role |
|---|---|
| `woocommerce-mercadopago.php` | WordPress plugin bootstrap. Defines `MP_PLUGIN_FILE`, loads `src/Startup.php`. |
| `src/Startup.php` | Availability check (PHP version, WordPress, WooCommerce active). Bootstraps `WoocommerceMercadoPago`. |
| `src/WoocommerceMercadoPago.php` | Plugin root. Instantiates and wires all subsystems. Defines `PLUGIN_VERSION`. |
| `src/Gateways/` | One class per payment method extending `AbstractGateway` → `WC_Payment_Gateway`. Registers WC payment methods, WC API webhook endpoints, and Blocks integration. |
| `src/Transactions/` | One Transaction class per gateway. Builds the Mercado Pago API payload and calls `mp-plugins/php-sdk`. |
| `src/Blocks/` | One Block class per gateway. Registers WooCommerce Gutenberg/Blocks checkout support. |
| `src/Notification/` | IPN, Webhook, and Core notification handlers. `NotificationFactory` dispatches incoming MP callbacks to the correct handler. |
| `src/Endpoints/` | WordPress WC-API and WC-AJAX endpoint registration (checkout validation, 3DS session, integration webhook). |
| `src/Order/` | WooCommerce order lifecycle: billing, shipping, metadata, status transitions. |
| `src/Refund/` | Refund handling: `RefundHandler`. |
| `src/Hooks/` | 13 hook classes wiring WordPress actions and filters (Admin, Blocks, Cart, Checkout, Endpoints, Gateway, Options, Order, OrderMeta, Plugin, Product, Scripts, Template). |
| `src/Admin/` | WordPress admin panel integration (gateway settings pages). |
| `src/Helpers/` | 36+ utility classes: Currency, Nonce, Session, Form, Requester, Notices, CheckoutValidator, etc. |
| `src/Libraries/` | Singleton base, logging (`Logs`), metrics (`Datadog` singleton). |
| `src/Entities/` | Domain entities (Files, Metadata). |
| `src/Interfaces/` | PHP contracts (Gateway, Block, Notification, Log). |
| `src/Configs/` | Plugin configuration (Metadata, Seller, Store) — reads from WordPress options via `wp_option`. |
| `src/Funnel/` | Conversion funnel tracking. |
| `src/Exceptions/` | Custom exceptions (Refund, RejectedPayment, InvalidCheckout). |
| `src/Translations/` | i18n PHP wrappers (wraps WordPress `__()` with the `woocommerce-mercadopago` text domain). |
| `src/Traits/` | Reusable PHP traits (Singleton pattern). |
| `src/IO/` | I/O abstractions (Downloader). |
| `src/HealthMonitor/` | Plugin asset integrity monitoring: `FileIntegrityChecker.php` verifies asset hashes; `ScriptHealthMonitor.php` watches script load health. |
| `assets/js/checkouts/` | Classic checkout: vanilla ES2020 controllers per payment method. |
| `assets/js/checkouts/custom/entities/` | Custom (card) checkout decomposed into entity modules: `card-form.js` (`MPCardForm` — SDK `cardForm()` wrapper), `event-handler.js` (submit/tokenization flow), `mp-card-form-error-codes.js` (`MPCardFormErrorCodes` — centralized SDK error-code strings), `three-ds-handler.js`, `mobile-checkout-classic-observer.js`. Shared UI helpers live in `assets/js/checkouts/custom/mp-custom-page.js` (`CheckoutPage`). |
| `assets/js/checkouts/super-token/` | Super Token CVV tokenization bundle — built locally, **loaded from CDN at runtime** (`http2.mlstatic.com`). |
| `assets/js/blocks/` | React functional components for WooCommerce Blocks checkout (`@wordpress/element`). |
| `assets/js/admin/` | Admin panel JavaScript. |
| `assets/js/caronte/` | Caronte session tracking integration. |
| `assets/js/melidata/` | MeliData client-side analytics. |
| `assets/js/health/` | Client-side health monitoring. |
| `assets/css/` | Stylesheets (admin, checkouts, products, public). |
| `packages/narciso/` | 18 native Web Components using `customElements.define()` and Shadow DOM. |
| `templates/` | PHP view templates for admin settings pages and checkout forms. |
| `tests/` | PHPUnit test suite (mirroring `src/`) and Jest JS unit tests. |
| `e2e/` | Playwright E2E tests organised per country (`mla`, `mlb`, `mlc`, `mlm`, `mco`, `mlu`, `mpe`). |
| `i18n/languages/` | WordPress `.po` / `.mo` translation files for 7 LATAM countries. |
| `scripts/` | Utility scripts including `diff-coverage.js` (pre-push coverage check). |

## Low-signal / generated areas (safe to skip; don't hand-edit)

| Area | Signal | How to regenerate |
|---|---|---|
| `vendor/` | Composer-vendored PHP dependencies | `composer install` |
| `node_modules/` | npm-vendored JS dependencies | `npm install` |
| `build/` | Webpack build output (WooCommerce Blocks) | `npm run build:webpack` |
| `assets/**/*.min.js`, `assets/**/*.min.css` | Minified/bundled asset output | `npm run build` |
| `i18n/languages/*.mo` | Compiled binary translation files | `npm run pot` then WP i18n compile |
| `integrity-manifest.json` | Asset integrity hashes | `npm run build:integrity` |

## Entrypoints / exposed surface

This is a WordPress plugin — there is no standalone binary or HTTP server. WordPress discovers and loads the plugin via `woocommerce-mercadopago.php` at boot. All exposed surfaces are registered as WordPress / WooCommerce hooks:

| Surface type | Example registered name | Registered from |
|---|---|---|
| WC payment gateways (7 methods) | `mercadopago-custom`, `mercadopago-basic`, etc. | Each `Gateway::__construct()` calling `add_filter('woocommerce_payment_gateways', ...)` |
| WC API webhook callbacks (sync from MP backend) | `?wc-api=WC_WooMercadoPago_Custom_Gateway` | Each `Gateway::__construct()` via `Hooks\Endpoints::registerApiEndpoint()` |
| WC-AJAX endpoints (browser ↔ PHP) | `wc_ajax_mp_validate_checkout` | `src/Endpoints/CheckoutValidation.php`, `CheckoutCustom.php` |
| WordPress AJAX endpoints (admin only) | `wp_ajax_mp_integration_login` | `src/Admin/Settings::registerAjaxEndpoints()` |
| WooCommerce Blocks (Gutenberg) | One Block per gateway | `src/Blocks/` via `woocommerce_blocks_payment_method_type_registration` |

See [contracts.md](contracts.md) for the full surface table.

## Request / data flow

**Classic checkout — payment processing:**
1. WordPress loads the plugin at boot: `woocommerce-mercadopago.php` → `Startup::available()` → `WoocommerceMercadoPago::__construct()`.
2. The buyer fills the checkout form. The Classic JS controller (`assets/js/checkouts/{method}.js`) calls the Mercado Pago JS SDK to tokenize the card, then submits the WooCommerce checkout form with the token.
3. WooCommerce calls `{Gateway}::process_payment()` → `proccessPaymentInternal()` (note: intentional double-`c` typo — do not correct it).
4. `proccessPaymentInternal()` instantiates a `{Method}Transaction` which builds the Mercado Pago API payload.
5. The Transaction calls `mp-plugins/php-sdk` (`MercadoPago\PP\Sdk`), which POSTs to `https://api.mercadopago.com`.
6. The SDK response is used by `OrderStatus` to update the WooCommerce order status.

**Async payment notification (IPN / Webhook):**
1. Mercado Pago's backend POSTs to `?wc-api=WC_WooMercadoPago_{Method}_Gateway`.
2. WordPress fires the `woocommerce_api_wc_woomercadopago_*_gateway` action.
3. `AbstractNotification::handleReceivedNotification()` delegates to `NotificationFactory`.
4. `NotificationFactory` routes to `IpnNotification`, `WebhookNotification`, or `CoreNotification` based on the payload shape.
5. The handler fetches payment details via `mp-plugins/php-sdk` and calls `OrderStatus::processStatus()`.
6. `OrderStatus` maps the Mercado Pago payment status to a WooCommerce order status and updates the order.

**PHP-to-JS data bridge:**
PHP passes gateway configuration to JavaScript via `wp_localize_script()`, making parameters available as `window.wc_mercadopago_{method}_params`. JS reads these globals at checkout init — never via a separate AJAX call for initial config.

**Custom checkout JS entities — conventions:**
- **SDK error-code strings are centralized in `MPCardFormErrorCodes`** (`mp-card-form-error-codes.js`, exposed as `window.MPCardFormErrorCodes`). Never hardcode SDK callback error strings (e.g. `'No payment methods found'`, `'timed out'`) inline — reference the constant. The module is registered in `CustomGateway.php` under the handle `wc_mercadopago_custom_card_form_error_codes` and declared as a **script dependency (`deps`) of `card-form`**, so it loads first; a new entity script that uses these codes must declare the same dependency.
- **`CheckoutPage.clearCardState()`** (`mp-custom-page.js`) is the shared card-reset routine: clears cardholder name, the card-number background/detected-brand icon, installments state/component, and — in the Custom flow — the `#paymentMethodId` / `#cardInstallments` hidden submit fields. Call it (not ad-hoc field resets) when a BIN becomes invalid or the card state must be reset, so all consumers stay consistent. **Super Token exception:** when `#mp_checkout_type === 'super_token'` it does **not** clear `#paymentMethodId` / `#cardInstallments` (nor via `removeAdditionFields(clearInstallmentsValue)`), because those shared hidden fields are owned by the Super Token flow — see [traps.md](traps.md#super-token-owns-the-shared-paymentmethodid--cardinstallments-fields).
- **`CheckoutPage.runPreSubmitGates(cardForm)`** (`mp-custom-page.js`) is the single pre-submit gate for the card flow, called from `createToken()` in **both** entrypoints — `event-handler.js` (Classic **and** Order Pay) and `blocks/custom.block.js` (Blocks) — so any gate/sync added here applies to all three checkouts. It short-circuits on card/installments/document errors and, before the installments gate, calls `syncInstallmentsFromSelect()`: it mirrors the current `#form-checkout__installments` value into the hidden `#cardInstallments` whenever the select has a value, so the value posted to the backend always matches what the buyer sees even when no `change` event fired (iOS native picker quirk). The mirror is unconditional (the select is the source of truth): it is an idempotent no-op when both already match, and it also corrects a stale hidden left behind when installments reload on an amount change (shipping/coupon) resets the select. This runs only in the Custom flow — Super Token routes through `handleWithSuperTokenSubmit` and owns the field.
- The `cardBinIsValid` flag on `MPCardForm` governs whether a card-number error may be cleared — see [traps.md](traps.md#card-bin-validity--the-cardbinisvalid-flag-on-mpcardform).

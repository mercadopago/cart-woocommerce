---
type: Contracts
version: d3bc3f29
validated: 2026-07-30
update_when: when the exposed surface, an event, or an outbound dependency changes
scope:
  - src/Endpoints/
  - src/Gateways/
  - src/Hooks/
  - src/Admin/
  - src/Helpers/Requester.php
  - src/Helpers/MetricContext.php
  - src/Libraries/Metrics/Datadog.php
  - src/Transactions/AbstractTransaction.php
---

# Contracts

## Exposed — sync

This plugin is a WordPress plugin, not an HTTP service. It exposes surfaces through the WordPress/WooCommerce hook system. All endpoints below are URL-addressable only within a WordPress installation running this plugin.

### WC-AJAX endpoints (browser ↔ PHP, WooCommerce AJAX channel)

Accessible at `?wc-ajax={endpoint}`. Auth varies per endpoint: `mp_validate_checkout` validates `woocommerce-process-checkout-nonce`; the two 3DS endpoints in `CheckoutCustom` read the WC session; `mp_pix_payment_status` verifies `PixGateway::PIX_STATUS_NONCE` (`mp_pix_polling_nonce`) **and** the order key against the loaded order (time-safe), rejecting with a uniform 403 otherwise.

| Endpoint | Caller intent | Auth | Idempotent | Registered in |
|---|---|---|---|---|
| `mp_validate_checkout` | Validate the checkout form server-side before payment tokenization; returns `{ valid, errors }` | WC nonce | ✅ | `src/Endpoints/CheckoutValidation.php` |
| `mp_get_3ds_from_session` | Retrieve the 3DS challenge result stored in the PHP session after the buyer completes the challenge | WC session | ✅ | `src/Endpoints/CheckoutCustom.php` |
| `mp_redirect_after_3ds_challenge` | Redirect the buyer back to the checkout after a 3DS challenge completes | WC session | ✅ | `src/Endpoints/CheckoutCustom.php` |
| `mp_pix_payment_status` | Poll PIX payment status to detect when the buyer has paid via the PIX QR code | `mp_pix_polling_nonce` + order key | ✅ | `src/Gateways/PixGateway.php` |

### WordPress AJAX endpoints (admin panel, `wp_ajax_` channel)

Accessible at `?action={endpoint}`. Require the user to be logged in as a WordPress admin.

| Endpoint | Caller intent | Auth | Idempotent |
|---|---|---|---|
| `mp_integration_login` | Authenticate with Mercado Pago OAuth to link the store's MP account | Admin login | ❌ |
| `mp_update_test_mode` | Toggle plugin between test mode and production mode | Admin login | ✅ |
| `mp_update_store_information` | Save store configuration (name, description, etc.) | Admin login | ✅ |
| `mp_update_option_credentials` | Save or update Mercado Pago API credentials (public key, access token) | Admin login | ✅ |
| `mp_get_requirements` | Validate that the plugin's environment requirements are met | Admin login | ✅ |
| `mp_get_payment_methods` | Retrieve available Mercado Pago payment methods for the configured credentials | Admin login | ✅ |
| `mp_validate_store_tips` | Validate store-level configuration tips shown in the admin panel | Admin login | ✅ |
| `mp_validate_payment_tips` | Validate payment-level configuration tips shown in the admin panel | Admin login | ✅ |
| `mp_download_log` | Download the plugin's debug log file | Admin login + `mp_download_log_nonce` | ✅ |
| `mp_review_notice_dismiss` | Dismiss the "leave a review" admin notice | Admin login | ✅ |
| `mp_saved_cards_notice_dismiss` | Dismiss the saved cards admin notice | Admin login | ✅ |
| `mp_sync_payment_status` | Manually sync a WooCommerce order's payment status against Mercado Pago | Admin login | ✅ |

## Exposed — async

### WC-API webhook endpoints (Mercado Pago backend → plugin)

Accessible at `?wc-api={endpoint}`. Mercado Pago's platform POSTs payment status notifications here. No authentication beyond URL secrecy; the handler validates the payment ID against the MP API before updating the order.

| Endpoint | What Mercado Pago sends | Handler | Idempotent | DLQ |
|---|---|---|---|---|
| `?wc-api=WC_WooMercadoPago_Custom_Gateway` | Card payment notification (IPN or Webhook) | `CustomGateway::webhook()` → `NotificationFactory` | ✅ (MP payment ID dedup) | — |
| `?wc-api=WC_WooMercadoPago_Basic_Gateway` | Checkout Pro / redirect payment notification | `BasicGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Pix_Gateway` | PIX payment notification | `PixGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Ticket_Gateway` | Ticket / Boleto payment notification | `TicketGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Credits_Gateway` | Mercado Creditos payment notification | `CreditsGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Pse_Gateway` | PSE (Colombia) payment notification | `PseGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Yape_Gateway` | Yape (Peru) payment notification | `YapeGateway::webhook()` → `NotificationFactory` | ✅ | — |
| `?wc-api=WC_WooMercadoPago_Integration_Webhook` | Generic integration webhook (OAuth / config events) | `IntegrationWebhook::webhookHandler()` | ✅ | — |
| `?wc-api=mp_pix_image` | PIX QR code image generation | `PixGateway::generatePixImage()` (endpoint constant: `PixGateway::PIX_IMAGE_ENDPOINT`) | ✅ | — |

## Outbound dependencies

> `Dependency`: `external` = non-Fury third party or cloud SDK. All outbound calls from this plugin go to Mercado Pago's own APIs or to external CDN. This plugin runs inside merchant WordPress installations, not inside the Fury platform — it has no Fury app-to-app calls and does not appear in the Fury dependency graph.

| Dependency | Code host / client | What it's used for | Timeout | Retry | Failure mode | Criticality |
|---|---|---|---|---|---|---|
| `external` | `https://api.mercadopago.com` via `mp-plugins/php-sdk` (PHP SDK `MercadoPago\PP\Sdk`) | All Mercado Pago API calls: payments, orders, OAuth, payment methods | 3 s (Datadog endpoint); SDK default for payment calls | — | API errors are caught, logged via `Datadog::sendEvent()`, and surfaced as WooCommerce payment errors to the buyer | — |
| `external` | `https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog` via `src/Libraries/Metrics/Datadog.php` | Plugin metrics reporting (payment events, errors, performance) | 3 s | — | Exceptions caught and silently discarded (metrics are non-critical) | — |
| `external` | `http2.mlstatic.com` (CDN) | Loads MeliData analytics, Caronte session tracking, and Super Token bundle at runtime in the browser | browser timeout | — | CDN failure degrades analytics and Super Token; checkout may fall back to Classic tokenization path | — |

### Metrics events emitted to the monitor

Sent via `Datadog::sendEvent(event_type, value, message, payment_method, details)` to the monitor endpoint above. `plugin_version` and the platform URL (`platform.url` = site URL) are always included; extra context is built by `MetricContext::buildBaseMetricDetails()` (`team`, `api_route`, `site_id`, `environment`, `cust_id`). Emission is fire-and-forget (`blocking => false`) and any failure is swallowed — a metric never affects the payment flow.

| Event type | When | `value` | Notable `details` |
|---|---|---|---|
| `mp_payment_create_result` | Every **checkout** payment-creation attempt (success and error) that goes through the transaction layer — `AbstractPaymentTransaction`/`SupertokenTransaction` via `AbstractTransaction::sendPaymentCreateResultMetric()`. Subscription CIT/MIT charges (Automatic Payments) do **not** pass through this path and are out of scope — see note below. | HTTP status class `2xx`/`4xx`/`5xx` (from `ApiException::getApiStatus()` on error; `2xx` when the SDK returns data) | `alert_type` = `success`/`error`; `payment_status` = the payment's business status (`approved`/`rejected`/`pending`/`in_process`) on 2xx, `null` on API error — a rejected card is a 2xx, not an error; `sdk_instance_id` |
| `mp_api_error` | A payment API call throws | `$e->getCode()` | `payment_method` = `checkout_type`; base fields via `buildBaseMetricDetails()` |

> **Out of scope for `mp_payment_create_result`:** the WooCommerce Subscriptions charges created outside `createPayment()` — the initial CIT via `CustomGateway::cit()` (`AutomaticPaymentsClient::cit()`) and renewal MITs via `Hooks/Subscriptions` (`AutomaticPaymentsClient::mit()`). These go through Automatic Payments, not the transaction layer, so they are not (yet) instrumented by this event.

## Consumers (who calls this repo — snapshot)

> This is a WordPress plugin installed inside merchant stores. Its callers are store browsers and Mercado Pago's backend (via webhook), not Fury applications. A Fury consumer graph does not apply: this plugin runs in the WordPress environment, outside the Fury service mesh.

N/A — consumers are WordPress store owners (admin) and their buyers (browser), not Fury services.

## External Config Service dependency

> Snapshot: 2026-06-29 (f2d57be7) · source: config-derived. Verify against the WordPress database before changing config-driven behavior.

This plugin does not use Fury Config or any external config service. All configuration is stored in WordPress's `wp_options` database table, read at runtime by `src/Configs/` classes.

| Config store | Keys that drive behavior | Where the code reads them |
|---|---|---|
| WordPress `wp_options` | Seller credentials (public key, access token), test/production mode, store configuration, gateway settings per payment method | `src/Configs/Seller.php`, `src/Configs/Store.php`, `src/Configs/Metadata.php`, each `Gateway::init_settings()` |

## Specs

No OpenAPI or AsyncAPI spec exists in the repo. The plugin's surfaces are WordPress/WooCommerce hooks, not REST APIs in the conventional sense. The webhook payload schemas are defined by Mercado Pago's platform documentation (external). Functional specs for feature work live in `.claude/agents/spec-writer.md` and tickets in the PSW Jira project.

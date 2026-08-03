---
type: Overview
version: f2d57be7
validated: 2026-06-29
update_when: when the repo's purpose, scopes, or capabilities change
scope:
  - woocommerce-mercadopago.php
  - src/
  - composer.json
  - package.json
  - .fury
---

# Overview

## What this repo is

The official Mercado Pago payment gateway plugin for WooCommerce/WordPress. It enables WooCommerce store owners across Latin America (Argentina, Brazil, Chile, Mexico, Colombia, Uruguay, Peru) to accept payments via Mercado Pago — credit cards, PIX, boleto/ticket, PSE, Yape, and Mercado Creditos. The plugin handles payment tokenization, order processing, IPN/Webhook payment notifications, and admin configuration. It runs entirely inside a merchant's WordPress installation as a self-contained PHP + JavaScript plugin; it has no Fury service infrastructure of its own.

## Archetype

WordPress payment gateway plugin. Distributed through the WordPress Plugin Directory, WooCommerce marketplace, and directly to Mercado Pago partners. Supports two frontend checkout modes:

- **Classic** (vanilla ES2020 + jQuery events): `assets/js/checkouts/`
- **Blocks** (React via `@wordpress/element`): `assets/js/blocks/`

## Identifiers

- `application_name`: `woocommerce-plugins-enablers` (from `.fury`)
- PHP package: `mp-plugins/woocommerce-plugins-enablers` (from `composer.json`)
- JS package: `woocommerce-mercadopago` v8.8.1 (from `package.json`)
- Runtime: PHP 7.4+ / Node 20 (build tooling only)
- Owner / team: mp-pp-bigsellers — @albalmeida_meli @anacarolferr_meli @cleitoandrad_meli @ddlorenzetti_meli @ext-anlferna_meli @flira_meli @julidsilva_meli @klucena_meli @skhalil_meli (from `.github/CODEOWNERS`)

## Capabilities (and where they live)

| Capability | Lives in (code) |
|---|---|
| Payment gateway registration (Classic checkout) | `src/Gateways/` — AbstractGateway, BasicGateway, CustomGateway, PixGateway, TicketGateway, CreditsGateway, PseGateway, YapeGateway |
| Payment processing and API payload construction | `src/Transactions/` — one Transaction class per gateway |
| WooCommerce Blocks (Gutenberg) checkout | `src/Blocks/` + `assets/js/blocks/` |
| IPN / Webhook payment notification handling | `src/Notification/` — IpnNotification, WebhookNotification, CoreNotification via NotificationFactory |
| WordPress WC-API and WC-AJAX endpoint registration | `src/Endpoints/` — CheckoutCustom, CheckoutValidation, IntegrationWebhook |
| WooCommerce order lifecycle management | `src/Order/` |
| Refund processing | `src/Refund/RefundHandler` |
| Admin settings panel | `src/Admin/` + `templates/` |
| WordPress action/filter registrations (13 hook classes) | `src/Hooks/` |
| Utility helpers (currency, nonce, session, form, requester) | `src/Helpers/` (36+ classes) |
| Plugin configuration (seller credentials, store, metadata) | `src/Configs/` |
| Classic checkout JS controllers (per payment method) | `assets/js/checkouts/` |
| Super Token CVV tokenization bundle | `assets/js/checkouts/super-token/` (built locally, served from CDN at runtime) |
| WooCommerce Blocks React components | `assets/js/blocks/` |
| Narciso design system (18 native Web Components) | `packages/narciso/` |
| PHP metrics reporting to Mercado Pago Datadog endpoint | `src/Libraries/Metrics/Datadog.php` |
| Client-side analytics and metrics | `assets/js/melidata/`, `assets/js/health/`, `sendMetric()` in checkout JS |
| Internationalisation (LATAM, 7 countries) | `i18n/languages/` + `src/Translations/` |
| Conversion funnel tracking | `src/Funnel/` |
| Caronte session tracking | `assets/js/caronte/` |

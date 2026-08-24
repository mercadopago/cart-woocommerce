---
type: Overview
version: 71b31053
validated: 2026-08-18
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
| Per-site document masks/validation (checkout doc field) | `packages/narciso/components/input-document/` — `DocumentHandlerFactory` + one handler per type (`mask()` + interim `validate()`) |
| PHP metrics reporting to Mercado Pago Datadog endpoint | `src/Libraries/Metrics/Datadog.php` |
| Client-side analytics and metrics | `assets/js/melidata/`, `assets/js/health/`, `sendMetric()` in checkout JS |
| Internationalisation (LATAM, 7 countries) | `i18n/languages/` + `src/Translations/` |
| Conversion funnel tracking | `src/Funnel/` |
| Caronte session tracking | `assets/js/caronte/` |

## Team hub (P&P)

This repo is part of the **Plugins & Payments (P&P)** ecosystem. Two hubs orient work across the team's repositories:

- **Domain hub — source of truth for specs / SDDs:** https://github.com/melisource/fury_mp-op-pp-sdd — the central Spec-Driven Development index and per-platform specs (WooCommerce lives under `sdd/woocommerce/`). Start here to discover which domain features this plugin participates in and which other repos they touch.
- **Process hub — DoR/DoD, code review, standards:** https://github.com/melisource/fury_mp-op-pp-development-cycle

## Related spec (P&P domain hub)

`fury_woocommerce-plugins-enablers` is referenced as an "App envolvido" for several WooCommerce-domain features in the hub's SDD index — [`tech/architecture/sdd-index.md`](https://github.com/melisource/fury_mp-op-pp-sdd/blob/master/tech/architecture/sdd-index.md):

- **WooCommerce — SDD concluído** (`sdd/woocommerce/meli/specs/`)
- **WooCommerce SuperToken — A/B Test** (`sdd/woocommerce/meli/features/supertoken/20260519-ab-test/`)
- **WooCommerce SuperToken — Experiência v2.1** (`sdd/woocommerce/meli/features/supertoken/experienciav2.1/`)
- **WooCommerce — CNPJ alfanumérico** (`sdd/woocommerce/meli/features/20260429-alphanumeric-cnpj/`)
- **Hub — CNPJ alfanumérico (hub scope)** (`sdd/core/meli/features-cross-platform/20260429-alphanumeric-cnpj/`)

Full domain spec: https://github.com/melisource/fury_mp-op-pp-sdd

### Local BDD scenarios (`features_locais`)

There are **no Gherkin `.feature` files** and **no BDD runner** (Behat / Cucumber / Codeception) wired in this repo. Adding `.feature` files would be inert — nothing would execute them — so none are created. The executable acceptance coverage here is instead:

- the **Playwright E2E suite** under [`e2e/`](../../e2e/README.md) — per-country Classic and Blocks checkout plus the Super Token flows (this repo also hosts the Super Token E2E suite and CDN bundle build);
- the **PHPUnit** (`tests/`) and **Jest** (`tests/JS/`) unit suites.

If the team later wires a BDD runner, the domain features listed above are the natural candidates to encode as `.feature` scenarios.

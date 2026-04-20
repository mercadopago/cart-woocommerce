# E2E Tests

## Quick Start

```bash
# 1. Install dependencies
cd e2e
npm install && npx playwright install

# 2. Copy and configure .env
cp .env.sample .env
# Fill in MP test credentials (see "Credentials" section below)

# 3. Start Docker store (if not running)
cd ../docker-flexible-environment && make up

# 4. Run tests
cd ../e2e
npm run test:mlb:classic   # Brazil — Classic checkout
npm run test:mlb:blocks    # Brazil — Blocks checkout
```

## Prerequisites

| Tool | Install | Why |
|------|---------|-----|
| Docker Desktop | [docker.com](https://www.docker.com/products/docker-desktop/) | Runs the WordPress + WooCommerce store |
| Node.js 20+ | `nvm install 20` | Playwright and test runner |
| MP Test Credentials | [developers/panel](https://www.mercadopago.com/developers/panel/app) | Payment API authentication |
| MP Test User | [developers/panel](https://www.mercadopago.com/developers/panel/app) → Test Users | Buyer email for sandbox payments |

## Credentials Setup

Each country needs its own MP seller credentials and a test user email.

### Step 1: Get test credentials

1. Go to https://www.mercadopago.com/developers/panel/app
2. Select or create a test application for the country you want to test
3. Copy the **test** Access Token and Public Key

### Step 2: Get a test user email

1. In the same panel, go to **Test Users**
2. Create a buyer test user for the country you want to test
3. Copy the email (format: `test_user_XXXXXXXX@testuser.com`)

**Important:** The seller (credentials) and buyer (test user email) must be different accounts from the same country. Using the same account or mixing countries causes `Invalid users involved` errors.

### Step 3: Add to e2e/.env

```env
# Buyer email (must be a MP test user, NOT a real email)
GUEST_EMAIL=test_user_XXXXXXXX@testuser.com

# Per-country credentials (recommended)
MP_ACCESS_TOKEN_TEST_MLB=TEST-...
MP_PUBLIC_KEY_TEST_MLB=TEST-...
MP_ACCESS_TOKEN_TEST_MLA=TEST-...
MP_PUBLIC_KEY_TEST_MLA=TEST-...

# Or generic (used as fallback when per-country is not set)
MP_ACCESS_TOKEN_TEST=TEST-...
MP_PUBLIC_KEY_TEST=TEST-...
```

If credentials are missing, tests fail immediately with instructions:
```
[E2E] Credenciais MP nao configuradas para MLA (faltando: access_token, public_key).

Opcoes para resolver:
  1. Adicionar no e2e/.env: MP_ACCESS_TOKEN_TEST_MLA=TEST-...
  2. Ou configurar via admin: http://localhost:8080/wp-admin/admin.php?page=mercadopago-settings
```

### Common credential errors

| Error | Cause | Fix |
|-------|-------|-----|
| `Invalid users involved` | GUEST_EMAIL is not a test user, or belongs to a different country | Use a `test_user_*@testuser.com` email from the same country as the credentials |
| `Las credenciales no coinciden con el entorno` | Mixing test/prod credentials or test user from wrong environment | Ensure both access_token and public_key start with `TEST-` |
| `UNAUTHORIZED` | Access token from country A used in country B store | Use per-country credentials: `MP_ACCESS_TOKEN_TEST_MLA` for MLA tests |
| `bin_not_found` | Card number not accepted by the seller account | Use test card numbers from [MP docs](https://www.mercadopago.com/developers/en/docs/woocommerce/additional-content/your-integrations/test/cards) for the specific country |
| `notification_url is non-nullable` | Plugin can't send notification URL | Handled automatically by global-setup (sets a placeholder domain) |

## How It Works

Each country has its own Docker store (currency, locale, shipping, products). The test infrastructure handles everything automatically:

```
npm run test:mla:classic
         |
         v
  [global-setup.js]
         |
         |-- Docker store is MLB? Auto-reset to MLA (~30s)
         |-- Set checkout mode: classic or blocks
         |-- Configure MP plugin: credentials, site_id, payment methods
         |-- Enable gateways, set binary_mode=no
         |
         v
  [tests run against http://localhost:8080]
```

### Checkout modes

The plugin supports two WooCommerce checkout implementations:

| Mode | Page content | How it works |
|------|-------------|--------------|
| **Classic** | `[woocommerce_checkout]` shortcode | Traditional form, jQuery-based, `#billing_*` fields, `#place_order` button |
| **Blocks** | `<!-- wp:woocommerce/checkout -->` with inner blocks | React-based, `#email` / `#shipping-*` fields, `.wc-block-components-checkout-place-order-button` |

All test flows support both modes automatically via `isVisible` checks. The global-setup switches the checkout page based on the `CHECKOUT` env var.

**Note on Blocks:** Blocks checkout requires the full inner block structure (contact-information, shipping-address, payment, etc.). A self-closing `<!-- wp:woocommerce/checkout /-->` renders empty.

### One country per run

`npm run test:mlb:classic` configures the store for Brazil with Classic checkout. Running `npm test` (all countries) only runs the current country — others skip automatically via `skipIfNotSite`.

To run all countries sequentially (with auto-reset between each):
```bash
cd ../docker-flexible-environment && make e2e-all
```

## Commands

### Classic checkout (shortcode)

| Command | Description |
|---------|-------------|
| `npm run test:mlb:classic` | Brazil — Classic |
| `npm run test:mla:classic` | Argentina — Classic |
| `npm run test:mlm:classic` | Mexico — Classic |
| `npm run test:mco:classic` | Colombia — Classic |
| `npm run test:mlc:classic` | Chile — Classic |
| `npm run test:mlu:classic` | Uruguay — Classic |
| `npm run test:mpe:classic` | Peru — Classic |

### Blocks checkout (React)

| Command | Description |
|---------|-------------|
| `npm run test:mlb:blocks` | Brazil — Blocks |
| `npm run test:mla:blocks` | Argentina — Blocks |
| `npm run test:mlm:blocks` | Mexico — Blocks |
| `npm run test:mco:blocks` | Colombia — Blocks |
| `npm run test:mlc:blocks` | Chile — Blocks |
| `npm run test:mlu:blocks` | Uruguay — Blocks |
| `npm run test:mpe:blocks` | Peru — Blocks |

### Other commands

| Command | Description |
|---------|-------------|
| `npm run test:health` | Health monitor tests (no checkout needed) |
| `npm test` | Run all tests (only current country passes, rest skip) |
| `npm run report` | Open HTML test report |
| `npm run test-ui` | Open Playwright UI |

### Extra arguments via `--`

```bash
npm run test:mlb:classic -- --headed           # Watch in browser
npm run test:mlb:classic -- --debug            # Step-through debugger
npm run test:mlb:classic -- -g "pix"           # Filter by test name
npm run test:mlb:classic -- --reporter=list    # Show progress in terminal
```

### Docker Makefile commands

```bash
cd docker-flexible-environment
make e2e SITE=mlb           # Run E2E from host for specific country
make e2e-docker TESTS=tests/mlb   # Run inside container (full Chromium for Blocks)
make e2e-reset SITE=mla     # Reset store + run E2E
make e2e-all                # Run all countries sequentially
make checkout-classic       # Switch to classic checkout
make checkout-blocks        # Switch to blocks checkout (with inner blocks)
```

## Test Structure

```
e2e/
  tests/
    mlb/                    # Brazil
      chocustom/            #   Credit/debit card (Custom Checkout)
      chopro/               #   Checkout Pro (redirect to mercadopago.com)
      chocredits/           #   Mercado Credits (redirect)
      pix/                  #   PIX payment
      ticket/               #   Boleto payment
    mla/                    # Argentina
    mlm/                    # Mexico
    mco/                    # Colombia (includes PSE)
    mlc/                    # Chile
    mlu/                    # Uruguay
    mpe/                    # Peru (includes Yape)
    health/                 # Health monitor JS component tests
  flows/                    # Reusable test flows (shared by all countries)
    fill_steps_to_checkout.js   # Shop -> Cart -> Checkout -> Fill billing
    chocustom.js                # Card payment flow (with token injection)
    chopro.js                   # Checkout Pro redirect verification
    credits.js                  # Credits redirect verification
    pix.js                      # PIX payment flow
    ticket.js                   # Ticket/invoice payment flow
    pse.js                      # PSE payment flow (Colombia)
    yape.js                     # Yape payment flow (Peru)
  helpers/
    wp-env.js               # Docker/container wp-cli helper
    site-guard.js            # Skip tests when store country doesn't match
  data/
    buyer_data.js            # Guest user data per country (address, doc, email)
    meli_sites.js            # Country configs exported for specs
    credit_card_scenarios.js # Card numbers and test scenarios
    debit_card_scenarios.js  # Debit card numbers
  global-setup.js            # Auto-configures store before all tests
  playwright.config.js       # Playwright config (4 workers, retries=0, 60s timeout)
```

## Payment Methods by Country

| Country | Credit Card | Debit Card | Checkout Pro | Credits | PIX | Ticket | PSE | Yape |
|---------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| MLB (Brazil) | x | x (elo) | x | x | x | x | | |
| MLA (Argentina) | x | x | x | x | | x | | |
| MLM (Mexico) | | | x | x | | x | | |
| MCO (Colombia) | x | x | x | | | x | x | |
| MLC (Chile) | x | x | x | | | | | |
| MLU (Uruguay) | x | x | x | | | x | | |
| MPE (Peru) | x | x | x | | | x | | x |

## Writing New Tests

### Basic pattern

```js
import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/chocustom";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

test.beforeEach(() => {
  skipIfNotSite(test, 'MLB');  // Skip if store is not Brazil
});

test('payment approved with master', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});
```

### Rules

- Always add `skipIfNotSite` — tests must only run against their country's store
- Use flows from `flows/` for common actions — don't duplicate checkout logic
- All flows support both Classic and Blocks automatically (via `isVisible` checks)
- For `binary_on` tests, toggle via `setGatewaySetting` in `beforeAll`/`afterAll`
- Chopro/Credits tests verify only the redirect to mercadopago.com (external checkout is not our scope)
- Card test names: `APRO` = approved, `CONT` = pending, `OTHE` = rejected

## Known Issues

**CORS on card_tokens (PCI Sandbox migration):** Some card tests may fail with a CORS error when the MP sandbox blocks requests from `secure-fields.mercadopago.com`. Tests detect this in ~5s and show a clear diagnostic message. This is an MP infrastructure issue, not a plugin bug.

**Debit card installments:** The checkout shows an installments dropdown for debit cards, but debit only supports 1 installment. Documented in debit specs as a known bug.

**MP Sandbox instability:** The MP sandbox API may return `500 internal_error` intermittently. When this happens, all payment-dependent tests fail (card, PIX, ticket). Redirect-based tests (chopro, credits) may still pass since they use the Preferences API. Retry when the API stabilizes.

**Doc type case sensitivity:** The `#form-checkout__identificationType` (custom checkout) and `#doc_type` (ticket) selects may have different casing for the same type (e.g., `Otro` vs `OTRO`). The ticket flow handles this with an uppercase fallback.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| All tests skip | Store country doesn't match | Use `npm run test:<country>:classic` instead of `npm test` |
| `container not running` | Docker not started | `cd ../docker-flexible-environment && make up` |
| `ajax_add_to_cart` timeout | Shop page not rendering | Check `woocommerce_coming_soon` option (global-setup disables it) |
| `billing_number required` | Extra Checkout Fields for Brazil plugin | Handled by `fillClassicBilling` — check `user.address.number` |
| `billing_postcode` not visible | Country hides postcode (e.g., Chile) | Handled by conditional fill in `fillClassicBilling` |
| `order-received` timeout | Payment failed | Check credentials match the store country; check MP API status |
| `bin_not_found` | Card number not valid for seller | Use country-specific test card from MP docs |
| `No shipping method` | Billing address doesn't match shipping zone | Wait for AJAX after country selection (handled by flow) |
| `500 internal_error` from MP | MP sandbox is down | Wait and retry — not a code issue |
| Tests take >5 min | CORS timeout on card tests | Known issue, tests fast-fail in ~5s with diagnostic |
| Blocks checkout empty | Missing inner blocks in page content | Use `make checkout-blocks` which inserts full block structure |
| `crypto.randomUUID` error in Blocks | Chromium headless_shell in non-secure context | Use `make e2e-docker` which uses full Chromium with secure context flag |
| `erro critico` during setup | MP API unstable during payment method sync | Non-fatal — stderr is suppressed, setup continues |

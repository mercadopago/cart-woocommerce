---
type: Runbook
version: 2a3e4556
validated: 2026-09-17
update_when: when build/test/CI or the Definition of Done changes
scope:
  - Makefile
  - package.json
  - composer.json
  - .github/workflows/
  - phpunit.xml
  - jest.config.js
  - e2e/
  - docker-flexible-environment/
  - scripts/
  - bin/
  - assets/css/checkouts/super-token/
  - e2e/
  - docker-flexible-environment/
---

# Runbook + Definition of Done

## Build / run

This is a WordPress plugin — it has no standalone server. To run it locally, install it inside a WordPress + WooCommerce installation (see the `docker-flexible-environment/` setup or the `/woo-homolog` skill for AWS homologation).

**Interactive local store setup:**
```
cd docker-flexible-environment
make interactive
```
The menu selects country, WordPress/PHP versions, theme, Mercado Pago mode,
checkout pages and optional Order Pay test data. It is an additive wrapper over
the existing Docker targets: direct `make up`/`make reset` and E2E targets keep
their prior defaults and lifecycle behavior. Country-specific credentials use
the `_<SITE>` value first and fall back to the generic variable; secret values
are read from `.env` and are never printed.

While it runs, the menu shows a spinner with a generic phase inferred from
fixed lifecycle markers. It never streams Docker logs, which keeps credentials
and other local configuration out of the terminal output.

`make up` and `make reset` also accept `WORDPRESS=latest`, `WORDPRESS=X.Y` or
`WORDPRESS=X.Y.Z`. The WordPress version is structural store state, so changing
it recreates local volumes. E2E reset targets explicitly set test mode, the
selected checkout and no Order Pay fixture, without inheriting menu choices.
When reusing a store, the interactive menu preserves the version selector in
its marker (`latest`, `X.Y` or `X.Y.Z`) rather than substituting the concrete
core version reported by WordPress; this avoids a false destructive reset after
`latest` resolves to a release.
The Order Pay URL is intentionally retrieved only through `make order-pay-url`:
the interactive summary does not print its order key.

**Install PHP dependencies:**
```
composer install
```

**Install JS dependencies:**
```
npm install
```

**Full build (all assets — run before testing or distributing):**
```
npm run build
```

This runs in sequence: build Narciso Web Components → minify JS → minify CSS → build the Super Token webpack entries + stage their three JS/CSS pairs → build Webpack (WooCommerce Blocks) → generate integrity manifest.

**Build targets (individual):**

| Target | Command |
|---|---|
| All assets | `npm run build` |
| Narciso design system (Web Components) | `npm run build:narciso` |
| WooCommerce Blocks (Webpack) | `npm run build:webpack` |
| JS (non-blocks) | `npm run build:js` |
| CSS only | `npm run build:css` |
| Super Token CDN bundle | `npm run build:super-token:bundle` |
| Asset integrity manifest | `npm run build:integrity` |
| Verify asset integrity (release gate) | `npm run verify:integrity` |
| i18n POT files | `npm run pot` |

**Local dev watch mode:**
```
npm run watch:build
```

## Test

**PHP tests (PHPUnit 9.6):**
```
vendor/bin/phpunit
```

**JS tests (Jest 30):**
```
npm test
```

**JS tests with watch:**
```
npm run test:watch
```

**JS tests with coverage:**
```
npm run test:coverage
```

**JS mutation testing (StrykerJS — local/manual, NOT in CI):**
```
npm run test:mutation
```
Pilot from PSW-4301: scoped via `stryker.config.json` to a single pure-logic module
(`adapters/view/shared/paymentMethodPresentation.ts`) to evaluate whether mutation testing
adds value here. Reuses `jest.config.js`. Runs are slow (~3 min) and non-deterministic, so
it is intentionally kept out of the blocking CI gate. Requires **Node 20** (`nvm use 20`).

StrykerJS is **not** a committed devDependency — the `test:mutation` script fetches it on demand via
`npx` (pinned to `@stryker-mutator/*@^9.6`), so its ~1150 transitive packages never enter
`package-lock.json` nor the CI `npm ci` install. The pilot's result is captured in the PSW-4301 PR/SDD
(mutation score **88.79% → 98.28%** after closing real assertion gaps).

**Diff coverage check (changed JS files, threshold 75%):**
```
node scripts/diff-coverage.js
```

This also runs automatically as a pre-push git hook.

**PHP static analysis (PHPStan 2.1 — level 5):**
```
vendor/bin/phpstan analyse
```

**PHP lint check:**
```
vendor/bin/php-cs-fixer fix --dry-run
```

**PHP lint auto-fix:**
```
vendor/bin/php-cs-fixer fix
```

**JS lint check:**
```
npm run lint
```

**JS lint auto-fix:**
```
npm run lint:fix
```

**SCSS lint (Super Token styles only):**
```
npm run lint:css
```
The Super Token styles are authored in SCSS under `assets/css/checkouts/super-token/`
(`_root.scss` mixin + `_list.scss` + `payment-methods/*` + `variants/*`, composed by the
single `super-token.scss` entry). `lint:css` (stylelint) and
`npm run verify:super-token:scss` require **Node 20** — run under `nvm use 20`.

**Super Token CDN hand-off — three artifact pairs, copied manually (PSW-4417).** `build:super-token:bundle`
(`compileSuperTokenCss` + `stageSuperTokenJsBundle` in `main.js`, after `build:super-token:webpack`
runs three webpack builds) produces **three JS+CSS pairs** that are the manual hand-off to
`fury_mp-op-pp-woocommerce-scripts`:

| Source artifact (gitignored) | CDN path | Plugin that loads it |
| --- | --- | --- |
| `super-token.bundle.js` + `super-token.bundle.min.css` | `mp-super-token/` | Deferred TASK-013 cutover (not loaded by 8.9.3; variant resolved at runtime) |
| `super-token-v2.bundle.js` + `super-token-v2.bundle.min.css` | `v1/` | 8.9.3 and earlier (A/B loader, variant v2) |
| `super-token-v2.1.bundle.js` + `super-token-v2.1.bundle.min.css` | `v2.1/` | 8.9.3 and earlier (A/B loader, variant v2.1) |

The two per-variant pairs are the same refactored runtime with the A/B variant frozen at build time
(`ST_FIXED_VARIANT=v2` / `v2.1`, webpack `DefinePlugin`), so an older plugin's loader gets a bundle
matching the folder it fetched. **Copy all three pairs** into the scripts repo: 8.9.3 still consumes the
two per-variant paths, while the unified pair remains staged for the deferred TASK-013 cutover. Omitting
the per-variant pairs breaks Super Token for stores on plugin 8.9.3 or earlier (see [traps.md](traps.md)
for the frozen folder↔variant invariant).

The per-variant CSS hand-offs carry `--mp-super-token-loader-version`, generated from
`SUPER_TOKEN_LOADER_VERSION` in `main.js`. The JS runtime exposes a separately maintained
`SUPER_TOKEN_JS_VERSION` in `constants.ts`. Bump both plugin sources together before `npm run build`;
the plugin build is the only source of truth for the hand-off. Copy its three JS and two per-variant
CSS outputs into the scripts repo without editing their versions downstream. After hand-off,
`tests/scripts/super-token/build-output-integration.test.js` in the scripts repo extracts both
stamps from homologation and production artifacts and fails if they diverge.

**Verify SCSS ≡ legacy CSS (RN-1 equivalence gate for the SCSS refactor):**
```
npm run verify:super-token:scss
```
Compiles each variant and diffs the normalized rule set (selectors, declarations,
specificity, cascade order) against the legacy CSS. TASK-013 must re-run this before
swapping the shipped bundle to the compiled SCSS and removing the legacy `.css`.

**E2E tests (Playwright, per country):**
```
# From e2e/ — see e2e/README for environment setup

# Super Token golden-device suite (MLB/MLA/MLM)
cd e2e/super-token
make list SITE=mlb
make test SITE=mlb GROUP=resilience
```
E2E tests require a live WooCommerce store with the plugin installed. Use the `/woo-homolog` skill or `docker-flexible-environment/`. The Super Token suite additionally requires the gitignored `config/countries.json` plus the Play-certified golden Android device documented in `e2e/super-token/README.md`; `make list` is the offline discovery check once that local config exists.

The MLB alphanumeric-CNPJ payload flows share `e2e/flows/checkout_request.helper.js` for
Classic `wc-ajax=checkout` and Blocks `POST /wc/store/v1/checkout` interception. The Blocks
matcher excludes `__experimental_calc_totals`, and gateway fields are read from the Store
API JSON `payment_data` array. Keep card and ticket flows on this helper so their transport
handling does not diverge.

Super Token scenarios that assert an initialization metric must install `recordMetricPayloads(page)`
before `startCustomCheckout`: module-level deduplication may emit the only request during the initial
render. Synthetic saved-method assertions must also scope `[data-checkout="installments"]` to the
row under test, because the base Custom Checkout keeps its own installments select in the document.
When asserting the exact number of metrics caused by synthetic renders, install a separate collector
after the initial checkout render so bootstrap traffic is not included in that count.

**iOS Simulator / Safari (MLB Custom Checkout):**
```
cd e2e/ios
make install
make doctor
make test
```

The iOS suite runs Classic installments regression + approved/rejected cards and Blocks installments regression + approved card serially. Its test titles follow the `Given ..., When ..., Then ...` convention. `make install` pins and registers XCUITest 12.2.1 in the same project-local `APPIUM_HOME` used by the runner, and `make doctor` fails if that exact driver is unavailable. The suite creates a dedicated Simulator, starts Appium/XCUITest locally, and gives the Simulator a loopback-only HTTPS entrypoint at `https://localhost:8443` using the `ios` Caddy profile. The pre-existing HTTP development port remains available to the host according to the Docker environment configuration. A single Safari session alternates between web and native contexts to automate the checkout and its cross-origin PCI fields. Clean Simulator boots allow five minutes for WebDriverAgent setup and 90 seconds for Safari to expose its first debuggable web context. No public tunnel or physical iPhone is required. Evidence is written by checkout section to `e2e/ios/evidence/<execution-id>/`; synthetic buyer and test-card data remain visible, while credentials, tokens and internal responses are excluded. Use `make stability` for the three-clean-run acceptance gate. Run `make help` for the complete command list, including focused Classic, Blocks and installments targets.

For each release, its author actively runs `bash e2e/run-all-report.sh --release` on macOS. The
manual regression command clears diagnostic title filters, includes the complete iOS suite once and
propagates `make`'s exit status even though its output is also written with `tee`. The iOS release check is part of the human-initiated regression
process; it is not triggered automatically by CI.

**Shared seven-country comparison environments (PSW-4320):**

```bash
# Local/tooling validation; does not publish or execute payments
make e2e-shared-validate

# Owner bootstrap in a clean remote checkout, before starting Compose
# Host prerequisites: Docker/Compose, flock, Node.js >=20.19, npm and PHP CLI.
make e2e-shared-infra-seed RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
make e2e-shared-infra-up
make e2e-shared-infra-live-check

# Canonical release operation: build/upload RC, pin production, compare Classic + Blocks
SMOOTH_USER=<network-user> \
SMOOTH_KNOWN_HOSTS_PATH=/path/to/owner-verified-known-hosts \
make e2e-shared-release \
  SITE=MLB RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3

# After publishing all countries: four countries in parallel, both checkouts, one retry
SMOOTH_USER=<network-user> make e2e-shared-test-matrix \
  RC_VERSION=8.9.4 PRODUCTION_VERSION=8.9.3
```

The release target reads the default `RC_VERSION` from `package.json`, creates the RC ZIP with the
root `build` target, downloads the explicit production version from the fixed WordPress.org host,
validates both archives and confirms their versions after installation. The environment mapping is
fixed: RC → staging and production → homol. If candidate publication fails after production was
installed, the same verified production artifact is installed in staging as a compensating
transaction and the comparison aborts; a rollback failure is reported as recovery-required rather
than allowing a mismatched pair to run. One per-country lease remains held from publication
until Classic and Blocks (including their child processes) finish, even if Classic fails. A
60-second heartbeat renews ownership throughout the operation. Each renewal tolerates up to three
transient SSH attempts in the same heartbeat; an ownership mismatch fails immediately, and failure
of all attempts terminates the lanes and produces an invalid `LEASE_LOST` report rather than
accepting potentially contaminated results. Both versions are added to the live monitor and paired
report.

Start any shared release with `make e2e-shared-preflight`. It is read-only, takes no lease, checks
that all 14 staging/homol domains resolve only to the current instance IPv4 returned by
`smooth get-instances`, and answers whether a publish can succeed — mount layout, lane pointer,
lane permissions, installed version across the seven stores of each lane, and active leases. It
exits non-zero with explicit `smooth add-domain` remediation for DNS drift or the exact local/host
command for an infrastructure blocker. The same DNS gate runs automatically before canonical
release and regression targets. Lanes created before the lane-root mount are migrated once on the
host with `make e2e-shared-infra-migrate`.

Real runs require provisioned hosts from `e2e/config/environments.json`, individual `smooth`/SSH
access, `SMOOTH_USER` and a protected `SMOOTH_KNOWN_HOSTS_PATH` whose host key was verified with the
owner through an independent channel. TOFU and an unverified `ssh-keyscan` are not accepted. All
granular comparison targets require both `RC_VERSION` and `PRODUCTION_VERSION`. They acquire a
per-country remote lease and write isolated artifacts
under `e2e/results/dual/<run_id>/`; publication targets instead take a lane-wide writer lock,
because one artifacts lane backs all seven countries of an environment and a publish swaps the
release for every one of them; a matrix summary is written under
`e2e/results/matrix/<matrix_id>/`. The matrix keeps at most four countries active, runs staging and
homol together, and serializes Classic/Blocks for the same country. Remote global setup is
preflight-only and must never call local WP-CLI. Before starting Playwright, every granular or
matrix run reads the installed plugin version from the exact staging/homol containers as `www-data`
and fails closed when either value differs from the declared RC/production version. Shared runs
intentionally exclude `@serial-store` until snapshot restore exists.

Equal versions are accepted only by the granular/matrix targets with `PARITY_VALIDATION=1`. This
mode exists to validate infrastructure and execution flow when no RC is available: reports use
`comparisonMode=PARITY_VALIDATION`, set `releaseEligible=false` and emit only `PARITY_*`
classifications for lane differences. It cannot satisfy the canonical release gate, which still
requires distinct RC and production versions. `SITE` is normalized case-insensitively at the root
Makefile boundary before it is passed to the runners.

On shared stores, Caddy blocks `/wp-content/debug.log` and its `PATH_INFO` variants, while every
container boot forces `WP_DEBUG`, `WP_DEBUG_LOG` and `WP_DEBUG_DISPLAY` off and removes an existing
log. `make e2e-shared-infra-up` force-recreates Caddy so a synchronized bind-mounted configuration
is actually loaded before the live probes. Setup, plugin publication and installed-version
verification run WP-CLI as `www-data`; the
root container entrypoint is reserved for operating-system/database setup. Legacy persistent
`wp-config.php` ownership is migrated transiently for the unprivileged debug update and restored to
`root:www-data` with mode `0640` before Apache starts. Duplicate legacy debug definitions are
deleted before the canonical `false` constants are recreated. If `SMOOTH_PK_PATH` is provided, all
SSH/SCP calls also set `IdentitiesOnly=yes`.

The single owner/developer/agent runbook is
`docker-flexible-environment/deploy/README.md#ambientes-compartilhados-staging--homol-psw-4320`.
It covers access, infrastructure, secret creation/rotation, granular Make targets, monitor,
classification and recovery. Other docs should link to it instead of copying operational steps.

Only shared stores synchronize credentials/runtime options and the WooCommerce language catalog
after every container start; local and personal stores preserve wp-admin configuration and do not
fail boot on remote locale availability. The
country locale remains `es_UY`/`es_PE` for Uruguay/Peru, while their WooCommerce strings use the
explicit `es_ES` fallback because WordPress.org does not publish those two country catalogs. Shared
language artifacts are owned by `www-data`, and payment-method cache refresh retries transient API
failure up to three times before failing closed. Run
`bash docker-flexible-environment/test-install-woocommerce-locale.sh` when changing this mapping.

The shared Make targets default to `TIMEOUT_PROFILE=standard` plus one Playwright retry. This avoids
turning the measured ~30-second shared checkout latency into a release false negative. `fast`
halves test, expect, action, navigation and explicit wait budgets and is diagnostic-only. CORS
failures remain detected during the approved-payment polling loop. Missing, corrupt or empty lane
JSON always produces an invalid paired report naming the affected lane.
Expected capability skips are surfaced as coverage limitations; direct checkout routes do not
exercise the cart-to-checkout transition, and `@serial-store` remains excluded even on failed-test
reruns.

The 2026-08-28 capacity benchmark reduced the complete 14-combination matrix from `04:34:18` to
`01:03:36` (about 77%) with four concurrent countries and the fast profile. Both lanes were on
8.9.3, so this benchmark supports only performance/stability conclusions, not a release verdict.
Longest-country-first scheduling added after that run is expected to keep the equivalent `fast`
diagnostic near 55–60 minutes. Rebenchmark the canonical `standard` + retry matrix before recording
an operational release estimate.

## Test harness

To add a passing test on the first try, copy an existing sibling in the matching `tests/` subdirectory and reuse the shared scaffolding below — don't re-stub the runtime or hand-roll doubles.

### Bootstrap
- **PHP:** [`tests/bootstrap.php`](../../tests/bootstrap.php) — wired via `bootstrap="tests/bootstrap.php"` in [`phpunit.xml`](../../phpunit.xml); sets up the WordPress/WooCommerce stubs and derives `PLUGIN_SUPER_TOKEN_VERSION`, so individual tests inherit the runtime instead of rebuilding it.
- **JS:** [`jest.setup.js`](../../jest.setup.js) — loaded via `setupFilesAfterEnv` in [`jest.config.js`](../../jest.config.js).

### Centralized mocks / stubs
- **PHP:** [`tests/Mocks/`](../../tests/Mocks/) — `SdkMock`, `MercadoPagoMock`, `ArrayMock`; plus reusable mock traits in [`tests/Traits/`](../../tests/Traits/) — `WoocommerceMock`, `GatewayMock`, `TransactionMock`, `FormMock`. Reuse these rather than creating per-test doubles.
- **JS:** suite mocks live alongside the specs under `tests/JS/`, mirroring the source tree (`tests/JS/checkouts/`, `tests/JS/blocks/`, `tests/JS/narciso/`, ...).

### Coverage configuration
- **PHP (PHPUnit):** the `<coverage>` block in [`phpunit.xml`](../../phpunit.xml) whitelists only `src/Gateways`, `src/Transactions`, `src/Exceptions`, `src/Helpers`, `src/Order`, `src/Notification`. Other `src/` directories (e.g. `Blocks`, `Endpoints`, `Hooks`, `Admin`, `Configs`, `Refund`, `Libraries`) are exercised by tests but **not** in the coverage include set, so they don't appear in the PHP coverage report. Widening the whitelist is a follow-up for the team — intentionally left out of this docs-only change so coverage gates don't move.
- **JS (Jest):** `collectCoverageFrom` in [`jest.config.js`](../../jest.config.js), `coverageProvider: 'v8'`, reporters `text`/`lcov`/`html`/`json`, output under `coverage/`. The changed-files gate is `node scripts/diff-coverage.js` (75% threshold, also a pre-push hook — see the Test section above).

## CI

| Workflow | File | What it checks | Blocking |
|---|---|---|---|
| Code quality | `.github/workflows/code-quality.yml` | Asset integrity (`bin/verify-integrity.js`) + PHP coding standards (`vendor/bin/phpcs`). ⚠️ Triggers `on: [push]`, which **startup-fails in this org** (see note below) — effectively inert today. | Push (inert) |
| JS quality | `.github/workflows/js-quality.yml` | `npm ci` (validates `package-lock.json`) + `npm run type-check` + `npm run lint` + `npm test`. Critical since `tsc --noEmit` is the only type check for the Super Token TS tree (webpack/Jest strip types without checking). Triggers `on: pull_request` **on purpose** so it actually runs (PSW-4301). | Yes (PRs) |
| Code coverage | `.github/workflows/code-coverage.yml` | PHPUnit coverage on PR vs base branch; comments coverage diff and fails if coverage decreases | Yes |
| Publish release | `.github/workflows/publish-release.yml` | Runs the blocking asset integrity gate (`node bin/verify-integrity.js`), then publishes the plugin to the distribution repo | Release only |
| Metrics gate | `.github/workflows/metrics-gate.yml` | On PRs to `master`: posts a sticky comment with the Datadog dashboard links + checklist, and holds the `metrics-review` check red until the `metrics-ok` label is applied by an authorized reviewer | PRs to `master` (only blocking once marked required — see below) |

> ⚠️ **`push`-triggered workflows startup-fail in this org.** Empirically, every `on: [push]` workflow run
> (Code Quality, and `publish-release` on `master`) completes in ~0s with `startup_failure` — repo-wide,
> on every branch, regardless of file content. Only `pull_request`-triggered workflows actually run
> (code-coverage, doc-freshness, metrics-gate, and now js-quality). So the pre-existing `code-quality.yml`
> (`phpcs`) has effectively never run, and **any new gate must trigger `on: pull_request`** to execute
> (PSW-4301). Root cause is an org/repo Actions policy, not the workflow files.
>
> ⚠️ **CI runners have no Fury npm auth.** GitHub-hosted runners can't authenticate to the internal
> `npm.artifacts.furycloud.io` registry (`npm ci` → E401). Every JS dependency here is public, so
> `js-quality.yml` rewrites the furycloud `resolved` URLs (stripping the Nexus `/repository/all` path)
> to `registry.npmjs.org` in the runner checkout only — never committed — then `npm ci`. The lockfile
> `integrity` hashes still validate the tarballs, so the lock-drift gate is preserved.

### Metrics review gate (`metrics-review`, PRs to `master`)

`.github/workflows/metrics-gate.yml` forces an active, auditable look at the Datadog metrics before merging into `master` (PSW-4334, after an incident where a release regressed metrics unnoticed). It runs `on: pull_request` (base `master`; types `opened, reopened, synchronize, labeled, unlabeled`) with `permissions: pull-requests: write` + `issues: write` + `contents: read`. The `metrics-review` check is green **only** while the `metrics-ok` label is present:

- **Label mechanism:** adding `metrics-ok` turns the check green; removing it turns it red — no new push needed (the workflow re-runs on `labeled`/`unlabeled`).
- **Authorized reviewers only:** on a `labeled` event, the actor is validated against an allowlist hardcoded in the workflow (kept in sync with `.github/CODEOWNERS`). An unauthorized actor's label is removed and the run fails. The **PR author cannot approve their own PR** (rejected even if on the allowlist).
- **New commits invalidate approval:** on `synchronize`, an existing `metrics-ok` is removed and the run fails, so metrics must be re-reviewed against the code actually being merged.
- **The `metrics-ok` label must exist in the repo** (it is a prerequisite — GitHub rejects applying a non-existent label).
- **Enforcement is a manual, one-time step per repo:** mark `metrics-review` as a **required** status check in `master` branch protection (Settings → Branches). Without it the workflow runs but does not block the merge.
- No Datadog secrets in this version (deep-link only). Mirrored identically in `fury_mp-op-pp-woocommerce-scripts`.

### Release integrity gate (blocking)

`bin/verify-integrity.js` (npm target `verify:integrity`) compares every asset listed in `integrity-manifest.json` against what is actually present and exits non-zero if any asset is **missing**, has a **divergent SHA-256**, or is an **orphan** (a `.min.{js,css}` under `assets/` that is absent from the manifest — i.e. the manifest was not regenerated). It runs at three points:

- `.github/workflows/code-quality.yml` — on every push/PR, as an **early warning** so a stale manifest fails in review, not only at release.
- `.github/workflows/publish-release.yml` — step "Validate asset integrity", before "Setup release": the **blocking release gate** (uses the runner's Node, no `npm ci`).
- `bin/create-release-zip.sh` — before the `zip`, validating the staged package against the repo-root manifest.

The two per-variant Super Token CSS hand-offs (`super-token-v2.bundle.min.css` and
`super-token-v2.1.bundle.min.css`) remain build/release artifacts but are deliberately absent from
the manifest and skipped by orphan validation. `main.js` and `bin/verify-integrity.js` consume the
same literal allowlist from `bin/integrity-assets.js`; every other minified asset, including the
plugin-served `super-token.bundle.min.css`, remains covered by the blocking gate (PSW-4437).

Depends only on the Node stdlib. Follow-up of the v8.7.23 postmortem (PPSP-1484), where the checkout card form shipped blank because `mp-plugins-components.min.{js,css}` were absent from the package.

## Definition of Done (ready for PR)

- [ ] PHP tests pass: `vendor/bin/phpunit`
- [ ] JS tests pass: `npm test`
- [ ] Diff coverage passes (75% threshold): pre-push hook runs `node scripts/diff-coverage.js` automatically
- [ ] PHP lint clean: `vendor/bin/php-cs-fixer fix --dry-run`
- [ ] PHP static analysis passes: `vendor/bin/phpstan analyse`
- [ ] JS lint clean: `npm run lint`
- [ ] Full build succeeds if JS/CSS/Narciso changed: `npm run build`
- [ ] iOS Simulator suite passes three consecutive clean runs if `e2e/ios/` or the Custom installments submit flow changed
- [ ] Version bump applied to all 4 files in sync if releasing (see [traps.md](traps.md) — version sync section)
- [ ] CHANGELOG.md updated under `[Unreleased]` with the change category (Added / Changed / Fixed)
- [ ] Update the relevant `docs/agent/` guide in the same PR; bump its `version` to the current HEAD SHA and update `scope` if code directories changed (doc-freshness soft norm)

## DoR/DoD — P&P hub reference

The Definition of Done above derives from the team's centralized standard:
https://github.com/melisource/fury_mp-op-pp-development-cycle

Full operational checklist (feature flag, rollout, approvals):
https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/checklists/FEATURE_CHECKLIST.md

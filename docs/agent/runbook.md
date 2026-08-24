---
type: Runbook
version: 47aa4915
validated: 2026-08-20
update_when: when build/test/CI or the Definition of Done changes
scope:
  - Makefile
  - package.json
  - composer.json
  - .github/workflows/
  - phpunit.xml
  - jest.config.js
  - scripts/
  - bin/
  - assets/css/checkouts/super-token/
---

# Runbook + Definition of Done

## Build / run

This is a WordPress plugin — it has no standalone server. To run it locally, install it inside a WordPress + WooCommerce installation (see the `docker-flexible-environment/` setup or the `/woo-homolog` skill for AWS homologation).

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
`SUPER_TOKEN_JS_VERSION` in `constants.ts`. Preserve both values when copying `v1/` and `v2.1/`;
after hand-off, `tests/scripts/super-token/build-output-integration.test.js` in the scripts repo
extracts both stamps from homologation and production artifacts and fails if they diverge.

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
```
E2E tests require a live WooCommerce store with the plugin installed. Use the `/woo-homolog` skill or `docker-flexible-environment/`.

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

Depends only on the Node stdlib. Follow-up of the v8.7.23 postmortem (PPSP-1484), where the checkout card form shipped blank because `mp-plugins-components.min.{js,css}` were absent from the package.

## Definition of Done (ready for PR)

- [ ] PHP tests pass: `vendor/bin/phpunit`
- [ ] JS tests pass: `npm test`
- [ ] Diff coverage passes (75% threshold): pre-push hook runs `node scripts/diff-coverage.js` automatically
- [ ] PHP lint clean: `vendor/bin/php-cs-fixer fix --dry-run`
- [ ] PHP static analysis passes: `vendor/bin/phpstan analyse`
- [ ] JS lint clean: `npm run lint`
- [ ] Full build succeeds if JS/CSS/Narciso changed: `npm run build`
- [ ] Version bump applied to all 4 files in sync if releasing (see [traps.md](traps.md) — version sync section)
- [ ] CHANGELOG.md updated under `[Unreleased]` with the change category (Added / Changed / Fixed)
- [ ] Update the relevant `docs/agent/` guide in the same PR; bump its `version` to the current HEAD SHA and update `scope` if code directories changed (doc-freshness soft norm)

## DoR/DoD — P&P hub reference

The Definition of Done above derives from the team's centralized standard:
https://github.com/melisource/fury_mp-op-pp-development-cycle

Full operational checklist (feature flag, rollout, approvals):
https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/checklists/FEATURE_CHECKLIST.md

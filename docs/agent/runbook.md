---
type: Runbook
version: eee2ba57
validated: 2026-07-23
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

This runs in sequence: build Narciso Web Components → minify JS → minify CSS → bundle Super Token variants → build Webpack (WooCommerce Blocks) → generate integrity manifest.

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

**E2E tests (Playwright, per country):**
```
# From e2e/ — see e2e/README for environment setup
```
E2E tests require a live WooCommerce store with the plugin installed. Use the `/woo-homolog` skill or `docker-flexible-environment/`.

## CI

| Workflow | File | What it checks | Blocking |
|---|---|---|---|
| Code quality | `.github/workflows/code-quality.yml` | SuperToken CSS reset check (`bin/check-css-reset.js --ci`) + asset integrity (`bin/verify-integrity.js`) + PHP coding standards (`vendor/bin/phpcs`) | Yes |
| Code coverage | `.github/workflows/code-coverage.yml` | PHPUnit coverage on PR vs base branch; comments coverage diff and fails if coverage decreases | Yes |
| Publish release | `.github/workflows/publish-release.yml` | Runs the blocking asset integrity gate (`node bin/verify-integrity.js`), then publishes the plugin to the distribution repo | Release only |
| Metrics gate | `.github/workflows/metrics-gate.yml` | On PRs to `master`: posts a sticky comment with the Datadog dashboard links + checklist, and holds the `metrics-review` check red until the `metrics-ok` label is applied by an authorized reviewer | PRs to `master` (only blocking once marked required — see below) |

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

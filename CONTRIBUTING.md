
# Contributing Guidelines

Thank you for  contributing to our project! Here are some guidelines to help streamline the process and ensure that your contributions are effective.

## Team standards (P&P hub)

This repository follows the centralized standards of the **Plugins & Payments (P&P)** team:

- **Domain hub — specs / SDD index:** https://github.com/melisource/fury_mp-op-pp-sdd
- **Process hub — code review, coding & logging standards, DoR/DoD:** https://github.com/melisource/fury_mp-op-pp-development-cycle
  - Code review guide: [`docs/CODE_REVIEW_GUIDE.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/CODE_REVIEW_GUIDE.md)
  - Coding standards: [`docs/CODING_STANDARDS.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/CODING_STANDARDS.md)
  - Definition of Ready / Done: [`docs/DEFINITION_OF_READY.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/DEFINITION_OF_READY.md) · [`docs/DEFINITION_OF_DONE.md`](https://github.com/melisource/fury_mp-op-pp-development-cycle/blob/master/docs/DEFINITION_OF_DONE.md) — see also the repo-local DoD in [`docs/agent/runbook.md`](docs/agent/runbook.md) and [`AGENTS.md`](AGENTS.md).

The sections below cover the local build/test/PR flow specific to this plugin.

## Requirements

- php: See the `composer.json` file to know the required php version
- node: See the `package.json` file to know the required node version
- pre-commit: Git hooks tool, see the [install docs](https://pre-commit.com/#install)

For a better development experience you should set the `WP_ENVIRONMENT_TYPE` constant to `local` or `development`, one thing this do is prevent browser asset caching.

## Docker environment

The docker environment counts with:
- npm
- composer 
- xdebug 
- latest wordpress and wp-cli 
- supervisor to copy logs and auto build assets on change
- adminer to access the database

The .env file counts with:
- APP_PORT: port to access app
- ADMINER_PORT: port to access adminer
- DB_USER: database user used by app
- DB_PASSWORD: database password used by app
- DB_NAME: database name used by app
- DB_PORT: port to access database
- ADMIN_USER: admin user name to access app
- ADMIN_PASSWORD: admin user password to access app
- ADMIN_EMAIL: admin user email
- WORDPRESS_DEBUG: value used to set WP_DEBUG constant
- WORDPRESS_CONFIG_EXTRA: extra wordpress config that will be placed on wp-config.php
- XDEBUG_MODE: valid xdebug mode https://xdebug.org/docs/all_settings#mode

## Commands

### Make commands

- `make install`: Setup the docker environment, setup wordpress, install npm and composer dependencies, install woocommerce and generate store products.
- `make build`: Creates the plugin zip
- `make watch`: Alias to `npm run watch:release`
- `make release`: Setup the release
- `make update-po`: Update `.pot` and `.po` files using the docker environment, see https://developer.wordpress.org/plugins/internationalization/localization
- `make make-mo`: Update `.mo` files using the docker environment, see https://developer.wordpress.org/plugins/internationalization/localization
- `make switch-language lang={locale_code}`: Change wordpress language on docker environment

See `Makefile` for more make commands info.

### Npm commands
- `npm run pot`: For [i18n](https://codex.wordpress.org/I18n_for_WordPress_Developers) WordPress uses pot files, this command generate our pot file from the tranlation files
- `npm run watch:build`: Run `npm run build` on asset change
- `npm run watch:release`: Run `make build` on any change
- `npm run watch:logs`: Copy php, wordpress and woocommerce logs to `logs` folder on change
- `npm run watch:make-mo`: Run wp-cli `make-mo` command on `.po` files change
- `npm run build`: Build assets
- `npm run build:{command}`: Asset related commands
- `npm run setup:st`: Configure the local Super Token dev environment — active variant, `USE_BUNDLE` toggle and SDK env (see [Super Token](#super-token-ab-variants--local-development))

See `package.json` for more npm commands info.

### Composer commands

- `composer run phpcs`: Run [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer)
- `composer run phpcbf`: Apply [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer) fixes
- `composer run metrics` and `composer run metrics:path`: Run [PhpMetrics](https://github.com/phpmetrics/PhpMetrics)
- `composer run qit:{command}`: A series of [qit](https://qit.woo.com/docs) commands
- `composer run phpunit`: Run the unit tests

See `composer.json` for more composer commands info.

When adding a new command, add its description here please.

## Git Workflow

We follow the Gitflow workflow for managing our branches. Please familiarize yourself with the Gitflow model if you are not already familiar. Here's a brief overview:
- `master`: Represents the production-ready code.
- `develop`: Represents the latest development code.
- `feature/*` : Used for developing new features.
- `bugfix/*`: Used for fixing bugs.
- `release/*`: Used for preparing releases.
- `hotfix/*`: Used for fixing critical issues in the production code.

## Conventional Commits

We encourage the use of Conventional Commits in your commit messages. This helps in generating meaningful changelogs and tracking project history effectively. If you're using VSCode, we recommend installing the [Conventional Commits for VSCode extension](https://marketplace.visualstudio.com/items?itemName=vivaxy.vscode-conventional-commits) for better integration.

## Pull Requests

When submitting a pull request, please ensure the following:
- Your commit message provides a clear and concise description of the changes made.
- Include a changelog in the following files: `readme.txt`, `changelog.txt`, and `changelog.md`. The changelog should summarize the changes made in this pull request.
- If applicable, ensure that any changes related to Mercado Pago, WooCommerce, or WordPress are accompanied by relevant documentation updates.

## Changelog Guidelines

- Changelog entries should be clear and objective, providing a brief summary of the changes made.
- It's important to include only relevant changes in the changelog.
- Ensure consistency in formatting and language across different changelog files.

We appreciate your contributions and adherence to these guidelines. They help maintain the quality and consistency of our project. Happy coding!

## Technical Implementation Notes

### Order Sync Button and Metabox

**Internal Implementation:**
- **Location**: `src/Hooks/Order.php::syncOrderStatus()` - Syncs the order in woocommerce to mercadopago
- **Location**: `src/Hooks/Order.php::getMetaboxData()` - Get the data to be renreded on the Status Sync Metabox

**Technical Details - Dev Internal Notes:**
- The sync button queries the **Notification API** and works with all Mercado Pago payment flows, but is particularly useful for payments made with **Checkout API or Checkout PRO - credit and debit cards**, where approved payment triggers the notification flow
- The **Notification API** brings information from the last notification saved in **KVS (fury)**
- The **Metabox displays payment information** through the **Payments API**
- **Edge case**: Some internally approved payments (Ticket and PIX) may not trigger the notification flow, so the sync button may bring the last notification with pending status to the WooCommerce Order Notes, bringing a different status than the payment that was previously approved.

### Super Token (A/B variants — local development)

Super Token runs as an A/B experiment with two variants — `v2` (control) and `v2.1` (treatment). In production the loader is served from the CDN bundle; for local development the plugin can load the individual variant files instead.

**Active variant — single source of truth:**
- The active variant lives in **one** place: the `PLUGIN_SUPER_TOKEN_VERSION` constant in `src/WoocommerceMercadoPago.php`.
- The build script (`main.js` via `getActiveSuperTokenVersion()`), the Jest config (`jest.config.js`) and the PHPUnit bootstrap (`tests/bootstrap.php`) all derive from it — there is no value to keep in sync by hand.

**Configuring the dev environment — `npm run setup:st`:**

Use the interactive helper instead of editing constants manually. It prompts for, and writes to the PHP source of truth:

| Prompt | Constant | Effect |
|---|---|---|
| Variant (`v2` / `v2.1`) | `PLUGIN_SUPER_TOKEN_VERSION` | Which variant the plugin (and the test suite) targets |
| Use the CDN bundle? (`s`/`n`) | `PLUGIN_SUPER_TOKEN_USE_BUNDLE` | `n` = dev mode: load the individual variant files; `s` = load the CDN bundle |
| JS SDK env (`prod`/`beta`/`gama`) | `PLUGIN_SDK_ENV` | Mercado Pago JS SDK environment |

- `setup:st` only configures the dev runtime — it does **not** set the loader version. Bumping the loader version is a release task (edit the `SUPER_TOKEN_LOADER_VERSION` map in `main.js` and rebuild the bundle).
- The runtime always loads the minified files (`*.min.js`). If you edit a Super Token source file, run `npm run build:js` (or `make build` in the docker dev environment) so the change is reflected. Just switching variant/mode via `setup:st` needs no rebuild — the committed `.min.js` already match.
- **Testing the A/B variant cookie locally:** the `mp_st_variant` cookie is set with the `Secure` flag, so browsers do **not** persist it over plain HTTP (e.g. `http://localhost`). In dev mode (`setup:st` → `USE_BUNDLE=false`) this is a non-issue — the loader/cookie A/B logic isn't exercised (the variant files load directly). To test the cookie/variant-assignment logic directly, serve the store over **HTTPS** (e.g. the docker dev environment's tunnel).

**Bundle destination — `npm run build:super-token:bundle`:**

Concatenates the active variant's files and copies the bundle into the `woocommerce-scripts` repo. The destination is resolved in this order:

1. `SUPER_TOKEN_SCRIPTS_REPO_PATH` environment variable (explicit override), if set;
2. a sibling directory — `fury_mp-op-pp-woocommerce-scripts` (preferred) or `mp-op-pp-woocommerce-scripts`.

If none is found, the build fails with a clear error instead of copying to a stale folder.

```bash
# Example: point the bundle copy at a specific scripts repo checkout
SUPER_TOKEN_SCRIPTS_REPO_PATH=/path/to/fury_mp-op-pp-woocommerce-scripts npm run build:super-token:bundle
```

## Tranlating using make commands

After adding the new texts in the code:

1. Run `make update-po` to update the `.pot` and `.po` files with the new texts.
2. Edit the `.pot` files adding the translations.
3. Run `make make-mo` to update the `.mo` files.


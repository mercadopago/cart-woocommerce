
# Contributing Guidelines

Thank you for  contributing to our project! Here are some guidelines to help streamline the process and ensure that your contributions are effective.

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

## Tranlating using make commands

After adding the new texts in the code:

1. Run `make update-po` to update the `.pot` and `.po` files with the new texts.
2. Edit the `.pot` files adding the translations.
3. Run `make make-mo` to update the `.mo` files.

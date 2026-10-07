'use strict';

/**
 * Build hand-off assets that are shipped with the release but intentionally
 * stay outside integrity-manifest.json because the plugin does not serve them.
 * Keep this list literal: broad patterns could hide future orphaned assets.
 */
const INTEGRITY_IGNORED_ASSETS = Object.freeze([
  'assets/css/checkouts/super-token/super-token-v2.bundle.min.css',
  'assets/css/checkouts/super-token/super-token-v2.1.bundle.min.css',
]);

module.exports = { INTEGRITY_IGNORED_ASSETS };

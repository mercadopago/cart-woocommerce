'use strict';

const fs = require('fs');
const path = require('path');

/**
 * Personal-review assets are generated inside assets/ for the isolated homolg
 * store, but they must never be shipped in the distributable plugin. The local
 * Smart Pix bundle is copied by prepare-smart-pix-real.js and is git-ignored,
 * so the release payload (git ls-files) never contains it.
 */
const RELEASE_EXCLUDED_ASSETS = Object.freeze([
  'assets/js/checkouts/smart-pix/smart-pix.mock.min.js',
  'assets/js/checkouts/smart-pix/smart-pix.bundle.min.js',
  'assets/css/checkouts/smart-pix/smart-pix.bundle.min.css',
  'assets/js/melidata/woocommerce.smart-pix-mock.min.js',
]);

function removeReleaseExcludedAssets (root) {
  const absoluteRoot = path.resolve(root);

  RELEASE_EXCLUDED_ASSETS.forEach((relativePath) => {
    const absolutePath = path.resolve(absoluteRoot, relativePath);
    const expectedPrefix = `${absoluteRoot}${path.sep}`;

    if (!absolutePath.startsWith(expectedPrefix)) {
      throw new Error(`Release asset outside package root: ${relativePath}`);
    }

    fs.rmSync(absolutePath, { force: true });
  });
}

function parseRoot (argv) {
  const rootIndex = argv.indexOf('--root');
  return rootIndex >= 0 ? argv[rootIndex + 1] : undefined;
}

function main () {
  const root = parseRoot(process.argv.slice(2));

  if (!root) {
    console.error('Usage: release-assets.js --root <package-root>');
    process.exit(1);
  }

  removeReleaseExcludedAssets(root);
}

if (require.main === module) {
  main();
}

module.exports = {
  RELEASE_EXCLUDED_ASSETS,
  removeReleaseExcludedAssets,
  main,
};

const path = require( 'path' );
const webpack = require( 'webpack' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

// The Super Token build hands its output to the CDN scripts repo, which owns
// minification and obfuscation (babel→ES5 + terser + obfuscator), same as the v1/v2.1
// bundles. Ship it unminified so that pipeline transforms readable source once; the
// Blocks build (build:webpack) does not set this and stays minified.
const superTokenHandOff = !! process.env.ST_UNMINIFIED;

// Build-time A/B variant pin for the CDN retrocompat paths (PSW-4417). Empty (default) builds the
// unified runtime staged at mp-super-token/ for the deferred TASK-013 cutover. Setting
// ST_FIXED_VARIANT=v2 / v2.1 freezes the same refactored source for the per-path bundles that the
// 8.9.3 A/B loader fetches from v1/ (v2) and v2.1/. resolveSuperTokenVariant
// reads the injected __ST_FIXED_VARIANT__ constant.
//
// Invariant: the value per path must mirror SUPER_TOKEN_VARIANT_FOLDER in the legacy loader
// (v2 -> v1/, v2.1 -> v2.1/). That loader is embedded in already-shipped plugin versions and cannot
// change, so v1/ must stay pinned to v2 and v2.1/ to v2.1 — see the build:super-token:webpack script.
const fixedVariant = process.env.ST_FIXED_VARIANT || '';

module.exports = {
  ...defaultConfig,
  ...( superTokenHandOff && { devtool: false } ),
  optimization: {
    ...defaultConfig.optimization,
    ...( superTokenHandOff && { minimize: false } ),
  },
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...( defaultConfig.resolve && defaultConfig.resolve.alias ),
      '@super-token': path.resolve( __dirname, 'assets/js/checkouts/super-token' ),
    },
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
    ),
    new DependencyExtractionWebpackPlugin(),
    new webpack.DefinePlugin({
      __ST_FIXED_VARIANT__: JSON.stringify( fixedVariant ),
    }),
  ],
};

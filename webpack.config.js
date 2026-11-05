const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

// The Super Token build hands its output to the CDN scripts repo, which owns
// minification and obfuscation (babel→ES5 + terser + obfuscator), same as the v1/v2.1
// bundles. Ship it unminified so that pipeline transforms readable source once; the
// Blocks build (build:webpack) does not set this and stays minified.
const superTokenHandOff = !! process.env.ST_UNMINIFIED;

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
  ],
};

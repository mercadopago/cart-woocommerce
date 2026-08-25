const fs = require('fs');
const readline = require('readline');
const path = require('path');
const minify = require('minify');
const wpPot = require('wp-pot');
const { sync: globSync } = require('glob');

/**
 * Minify JS and CSS files
 *
 * @param extension
 */
function minifyFiles (extension) {
  const assetsFiles = findFilesInDir(`./assets/${extension}`, `.${extension}`, '/blocks');
  // Super Token assets are produced by dedicated steps (webpack for JS, sass for CSS); its JS
  // hand-off is committed unminified for the CDN pipeline, so keep the generic minifier off them.
  const isSuperTokenAsset = (filePath) => path.normalize(filePath).includes(path.normalize('checkouts/super-token/'));
  const isNotMinifiedAndHasSelectedExtension = (filePath) => {
    const normalizedPath = path.normalize(filePath);
    return normalizedPath.includes(`.${extension}`)
      && !normalizedPath.includes('.min');
  };
  const filteredFiles = assetsFiles.filter(
    (filePath) => isNotMinifiedAndHasSelectedExtension(filePath) && !isSuperTokenAsset(filePath)
  );

  filteredFiles.forEach((file) => {
    const filePath = path.resolve(`${file}`);

    minify(filePath, { js: { ecma: 6 }, css: { compatibility: '*' } })
      .then((minifiedContent) => {
        const newFilePathName = filePath
          .split(`.${extension}`)[0]
          .concat(`.min.${extension}`);
        fs.writeFileSync(newFilePathName, minifiedContent);
      })
      .catch(console.error);
  });
}

/**
 * Reads the active super-token variant from the PHP source of truth
 * (PLUGIN_SUPER_TOKEN_VERSION in src/WoocommerceMercadoPago.php), so there is no
 * duplicated/"must match" constant to keep in sync.
 *
 * Read lazily as a function (not as a module-level constant) so unit tests that mock `fs`
 * can configure the mock before calling it. Note: jest.config.js calls this eagerly at Jest
 * startup — in a non-standard environment where the PHP file is absent/malformed, Jest fails
 * at config-load time.
 *
 * @returns {string} The active variant (e.g. 'v2.1').
 */
function getActiveSuperTokenVersion () {
  const phpPath = path.resolve(__dirname, 'src/WoocommerceMercadoPago.php');
  const phpContent = fs.readFileSync(phpPath, 'utf8');
  const match = phpContent.match(/private const PLUGIN_SUPER_TOKEN_VERSION\s*=\s*'([^']+)'/);
  if (!match) {
    throw new Error('PLUGIN_SUPER_TOKEN_VERSION not found in src/WoocommerceMercadoPago.php — cannot resolve the active super-token variant.');
  }
  return match[1];
}

/**
 * Super Token A/B variant list (single source of truth). Each key is a product
 * variant; the value is its loader version. Consumed by the interactive
 * setupSuperToken prompt to offer the dev variant choice. The CDN folder mapping
 * and the per-variant bundle publish live in the scripts repo now (TASK-013).
 */
const SUPER_TOKEN_LOADER_VERSION = { 'v2': '1.2.5', 'v2.1': '1.2.5' };

/**
 * Compile one Super Token SCSS entry into a compressed CSS file (both under
 * `assets/css/checkouts/super-token`). Shared by the unified and per-variant builds.
 */
function compileScss (entryName, outputName, loaderVersion = null) {
  const stDir = './assets/css/checkouts/super-token';
  const entry = path.resolve(`${stDir}/${entryName}`);
  if (!fs.existsSync(entry)) {
    throw new Error(`compileScss: SCSS entry not found: ${entry}`);
  }

  const compressed = require('sass').compile(entry, { style: 'compressed' }).css;
  const versionStamp = loaderVersion
    ? `:root{--mp-super-token-loader-version:${loaderVersion}}`
    : '';
  const outputFilePath = path.resolve(`${stDir}/${outputName}`);
  fs.writeFileSync(outputFilePath, `${versionStamp}${compressed}`);
  return outputFilePath;
}

/**
 * Compile the Super Token stylesheets.
 *
 * - `super-token.bundle.min.css` — the unified hand-off for the future mp-super-token/ cutover:
 *   the refactored runtime stamps `data-variant` on the root and both variants' rules coexist here,
 *   scoped to it. Dev/self-construct mode serves this one file regardless of the active variant.
 * - `super-token-v2.bundle.min.css` / `super-token-v2.1.bundle.min.css` — per-variant, from the same
 *   SCSS sources, for the legacy CDN retrocompat bundles an older plugin's loader fetches from v1/
 *   (v2) and v2.1/. Hand-off only (gitignored, copied into the scripts repo); not served locally (PSW-4417).
 */
function compileSuperTokenCss () {
  compileScss('super-token.scss', 'super-token.bundle.min.css');
  compileScss('super-token-v2.scss', 'super-token-v2.bundle.min.css', SUPER_TOKEN_LOADER_VERSION.v2);
  compileScss('super-token-v2.1.scss', 'super-token-v2.1.bundle.min.css', SUPER_TOKEN_LOADER_VERSION['v2.1']);
}

/**
 * Stage one webpack build (`build/<buildDir>/bootstrap.ts.js`) as an unminified,
 * gitignored hand-off bundle under `assets/js/checkouts/super-token/<targetName>`.
 */
function stageOneJsBundle (buildDir, targetName) {
  const webpackOutput = path.resolve(`./build/${buildDir}/bootstrap.ts.js`);
  if (!fs.existsSync(webpackOutput)) {
    throw new Error(`stageOneJsBundle: webpack output not found: ${webpackOutput}. Run build:super-token:webpack first.`);
  }

  const targetPath = path.resolve(`./assets/js/checkouts/super-token/${targetName}`);
  fs.copyFileSync(webpackOutput, targetPath);
  return targetPath;
}

/**
 * Stage the Super Token JS bundles — a local hand-off for the manual copy into the
 * woocommerce-scripts repo (the tracked CDN source), which owns minification and
 * obfuscation, so these stay readable. Built unminified (ST_UNMINIFIED). Nothing enqueues
 * them locally: dev/self-construct mode serves `build/super-token/bootstrap.ts.js`, bundle
 * mode loads from the CDN.
 *
 * - `super-token.bundle.js` — the runtime bundle for the future mp-super-token/ cutover, with the
 *   variant resolved at runtime. The 8.9.3 production loader still uses the per-variant paths.
 * - `super-token-v2.bundle.js` / `super-token-v2.1.bundle.js` — the same source built with the
 *   variant frozen (ST_FIXED_VARIANT), for the legacy CDN retrocompat paths v1/ (v2) and v2.1/,
 *   which an older plugin's A/B loader fetches (PSW-4417).
 *
 * Must run after `build:super-token:webpack`, which produces the three webpack outputs.
 */
function stageSuperTokenJsBundle () {
  stageOneJsBundle('super-token', 'super-token.bundle.js');
  stageOneJsBundle('super-token-v2', 'super-token-v2.bundle.js');
  stageOneJsBundle('super-token-v2.1', 'super-token-v2.1.bundle.js');
}

/**
 * Build the Super Token assets: the unified and per-variant CSS outputs plus their staged JS
 * bundles. Single entrypoint for `build:super-token:bundle`.
 */
function buildSuperTokenAssets () {
  compileSuperTokenCss();
  stageSuperTokenJsBundle();
}

/**
 * Generate .pot files
 */
function generatePotFiles () {
  wpPot({
    domain: 'woocommerce-mercadopago',
    destFile: './i18n/languages/woocommerce-mercadopago.pot',
    lastTranslator: 'Mercado Pago Developers <woocommerce.dev@mercadopago.com>',
    src: ['src/**/*.php', 'templates/**/*.php']
  });
}

/**
 * Find a file by extension
 *
 * @param startPath
 * @param filter
 *
 * @returns {*[]}
 */
function findFilesInDir (startPath, filter, excludes = '') {
  let results = [];

  if (!fs.existsSync(startPath)) {
    console.error('no dir ', startPath);
    return [];
  }

  const files = fs.readdirSync(startPath);

  for (let i = 0; i < files.length; i++) {
    const filename = path.join(startPath, files[i]);

    if (filename.includes(excludes)) {
      continue;
    }

    const stat = fs.lstatSync(filename);

    if (stat.isDirectory()) {
      results = results.concat(findFilesInDir(filename, filter, excludes));
    } else if (filename.indexOf(filter) >= 0) {
      results.push(filename);
    }
  }

  return results;
}

async function setupSuperToken() {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });
  const ask = (question) => new Promise((resolve) => rl.question(question, resolve));

  const variants = Object.keys(SUPER_TOKEN_LOADER_VERSION);
  const variant = (await ask(`Qual variante do Super Token? (${variants.join(' = controle, ')} = tratamento): `)).trim();
  if (!variants.includes(variant)) {
    console.error(`Variante inválida. Use uma das: ${variants.join(', ')}`);
    process.exit(1);
  }

  const bundleAnswer = (await ask('Usar o bundle da CDN? (s/n — n = modo dev, carrega arquivos individuais): ')).trim().toLowerCase();
  if (!['s', 'n'].includes(bundleAnswer)) {
    console.error("Resposta inválida. Use 's' ou 'n'.");
    process.exit(1);
  }
  const useBundle = bundleAnswer === 's';

  const env = await ask('Qual ambiente do JS SDK? (prod, beta, gama): ');
  if (!['prod', 'beta', 'gama'].includes(env)) {
    console.error('Ambiente inválido. Use: prod, beta ou gama');
    process.exit(1);
  }

  rl.close();

  // Configure only the dev runtime constants in the PHP source of truth: active variant,
  // bundle toggle and SDK env. The loader version is a release concern (bump the
  // SUPER_TOKEN_LOADER_VERSION map + rebuild the bundle), intentionally not handled here.
  const phpPath = 'src/WoocommerceMercadoPago.php';
  let phpContent = fs.readFileSync(phpPath, 'utf8');

  phpContent = phpContent.replace(
    /private const PLUGIN_SUPER_TOKEN_VERSION = '[^']*';/,
    `private const PLUGIN_SUPER_TOKEN_VERSION = '${variant}';`
  );

  phpContent = phpContent.replace(
    /private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = \w+;/,
    `private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = ${useBundle};`
  );

  phpContent = phpContent.replace(
    /private const PLUGIN_SDK_ENV = '[^']+';/,
    `private const PLUGIN_SDK_ENV = '${env}';`
  );

  fs.writeFileSync(phpPath, phpContent, 'utf8');
  console.log(`✔ ${phpPath}: PLUGIN_SUPER_TOKEN_VERSION = '${variant}'`);
  console.log(`✔ ${phpPath}: PLUGIN_SUPER_TOKEN_USE_BUNDLE = ${useBundle}`);
  console.log(`✔ ${phpPath}: PLUGIN_SDK_ENV = '${env}'`);

  console.log('\nConfiguração concluída!');
}

/**
 * Generate integrity-manifest.json with SHA-256 hashes of critical JS/CSS files.
 * Run as the last build step so hashes reflect final (minified) assets.
 */
function generateIntegrityManifest () {
  const crypto = require('crypto');

  // The per-variant Super Token stylesheets (super-token-v{2,2.1}.bundle.min.css) are a gitignored
  // CDN hand-off for the legacy v1//v2.1/ paths, not served by the plugin, so they must not enter the
  // integrity manifest — unlike the served single super-token.bundle.min.css (PSW-4417). The JS
  // hand-off already escapes this glob: it is staged unminified (.bundle.js, no .min).
  const criticalFiles = globSync('assets/**/*.min.{js,css}', {
    ignore: ['assets/css/checkouts/super-token/super-token-v*.bundle.min.css'],
  });

  const manifest = {};

  criticalFiles.forEach((file) => {
    const absolutePath = path.resolve(file);

    if (!fs.existsSync(absolutePath)) {
      console.warn(`[integrity] Arquivo não encontrado, ignorando: ${file}`);
      return;
    }

    const content = fs.readFileSync(absolutePath);
    manifest[file] = crypto.createHash('sha256').update(content).digest('hex');
  });

  fs.writeFileSync('integrity-manifest.json', JSON.stringify(manifest, null, 2));
  console.log('[integrity] integrity-manifest.json gerado com', Object.keys(manifest).length, 'entradas.');
}

module.exports = {
  SUPER_TOKEN_LOADER_VERSION,
  getActiveSuperTokenVersion,
  minifyFiles,
  compileSuperTokenCss,
  stageSuperTokenJsBundle,
  buildSuperTokenAssets,
  generatePotFiles,
  generateIntegrityManifest,
  setupSuperToken
};

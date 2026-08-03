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
  const isNotMinifiedAndHasSelectedExtension = (filePath) => {
    const normalizedPath = path.normalize(filePath);
    return normalizedPath.includes(`.${extension}`)
      && !normalizedPath.includes('.min');
  };
  const filteredFiles = assetsFiles.filter((filePath) => isNotMinifiedAndHasSelectedExtension(filePath));

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
 * Super Token A/B variants → CDN folder mapping (single source of truth).
 *
 * Each KEY is both the source folder under `assets/.../super-token/` and the
 * product variant name; each VALUE is the destination folder in the scripts
 * project / CDN. Variant 'v2' is served from the legacy 'v1/' folder for
 * retro-compatibility (see DD-3).
 *
 * Add a new A/B variant here and `build:super-token:bundle` will bundle and
 * copy it automatically — no other change needed.
 */
const SUPER_TOKEN_FOLDER_MAP = { 'v2': 'v1', 'v2.1': 'v2.1' };

/**
 * Super Token loader version per variant (single source of truth for the bundle).
 *
 * Injected at build time into BOTH the CSS bundle (as the `--mp-super-token-loader-version`
 * custom property) and the JS bundle (the `SUPER_TOKEN_JS_VERSION` constant), so a
 * variant's CSS and JS always carry the same version (no drift). Bump only the variant
 * that changed; variants evolve independently.
 *
 * In dev mode (MP_SUPER_TOKEN_USE_BUNDLE=false) the bundle is not built, so the JS keeps
 * the default value declared in its source file.
 */
const SUPER_TOKEN_LOADER_VERSION = { 'v2': '1.2.4', 'v2.1': '1.2.4' };

/**
 * Resolves the loader version for a variant.
 *
 * Throws if the variant has no entry in SUPER_TOKEN_LOADER_VERSION — fail-loud,
 * consistent with the bundleSuperTokenJs guard, so adding a variant to
 * SUPER_TOKEN_FOLDER_MAP without a matching version is caught at build time
 * instead of silently stamping another variant's version.
 *
 * @param {string} version Variant being bundled ('v2' or 'v2.1').
 * @returns {string} The loader version to stamp into the bundle.
 */
function resolveSuperTokenLoaderVersion (version) {
  const loaderVersion = SUPER_TOKEN_LOADER_VERSION[version];
  if (!loaderVersion) {
    throw new Error(`No loader version mapped for Super Token variant "${version}". Add it to SUPER_TOKEN_LOADER_VERSION.`);
  }
  return loaderVersion;
}

/**
 * Bundle JS files for super-token checkout without minification
 *
 * @param {string} version Source variant folder to bundle (e.g. 'v2', 'v2.1'). Defaults to the active variant (from PHP).
 */
function bundleSuperTokenJs (version = getActiveSuperTokenVersion()) {
  const jsBaseDir = `./assets/js/checkouts/super-token/${version}`;
  // Version-agnostic files shared by every variant (e.g. the checkout validation resolver):
  // bundled into each variant from a single source, so they are not coupled to a variant folder.
  const sharedBaseDir = `./assets/js/checkouts/super-token/shared`;
  const jsOutputFilePath = path.resolve(`./assets/js/checkouts/super-token/super-token.bundle.js`);
  const variantFiles = findFilesInDir(jsBaseDir, '.js', '/blocks');
  const sharedFiles = fs.existsSync(sharedBaseDir) ? findFilesInDir(sharedBaseDir, '.js', '/blocks') : [];
  const jsFilesToBundle = [...variantFiles, ...sharedFiles]
    .filter((filePath) => {
      const normalizedPath = path.normalize(filePath);
      return normalizedPath.includes('.js')
        && !normalizedPath.includes('.min')
        && path.resolve(filePath) !== jsOutputFilePath;
    })
    .sort();

  if (jsFilesToBundle.length === 0) {
    return null;
  }

  const jsConcatenatedContent = jsFilesToBundle
    .map((filePath) => fs.readFileSync(path.resolve(filePath), 'utf8'))
    .join('\n');

  // Inject the loader version (single source of truth) into the bundle, overriding
  // the dev-mode default declared in the source file.
  // Anchor on the `const` declarator so the target is deterministic: the class field
  // default (SUPER_TOKEN_JS_VERSION = null, no const) and usages never match, even if
  // that field's default is later changed to a string. Replace stays first-match-only.
  const versionDeclarationPattern = /\bconst\s+SUPER_TOKEN_JS_VERSION\s*=\s*(?:'[^']*'|"[^"]*")/;
  if (!versionDeclarationPattern.test(jsConcatenatedContent)) {
    // Fail loud: if the declaration is renamed/removed, the bundle would ship unstamped silently.
    throw new Error(`bundleSuperTokenJs: const SUPER_TOKEN_JS_VERSION declaration not found for variant "${version}" — cannot inject loader version.`);
  }
  const stampedJsContent = jsConcatenatedContent.replace(
    versionDeclarationPattern,
    `const SUPER_TOKEN_JS_VERSION = '${resolveSuperTokenLoaderVersion(version)}'`
  );

  fs.writeFileSync(jsOutputFilePath, stampedJsContent);
  return jsOutputFilePath;
}

/**
 * Bundle CSS files for super-token checkout without minification
 *
 * @param {string} version Source variant folder to bundle (e.g. 'v2', 'v2.1'). Defaults to the active variant (from PHP).
 */
function bundleSuperTokenCss (version = getActiveSuperTokenVersion()) {
  const cssBaseDir = `./assets/css/checkouts/super-token/${version}`;
  const cssOutputFilePath = path.resolve(`./assets/css/checkouts/super-token/super-token.bundle.css`);
  const cssFiles = findFilesInDir(cssBaseDir, '.css', '/blocks');
  const cssFilesToBundle = cssFiles
    .filter((filePath) => {
      const normalizedPath = path.normalize(filePath);
      return normalizedPath.includes('.css')
        && !normalizedPath.includes('.min')
        && path.resolve(filePath) !== cssOutputFilePath;
    })
    .sort();

  if (cssFilesToBundle.length === 0) {
    return null;
  }

  const cssConcatenatedContent = cssFilesToBundle
    .map((filePath) => fs.readFileSync(path.resolve(filePath), 'utf8'))
    .join('\n');

  // Prepend the loader version stamp (single source of truth) at the top of the bundle.
  const versionStamp = `.root {\n  --mp-super-token-loader-version: ${resolveSuperTokenLoaderVersion(version)};\n}\n`;

  fs.writeFileSync(cssOutputFilePath, versionStamp + cssConcatenatedContent);
  return cssOutputFilePath;
}

/**
 * Sibling directory names accepted for the scripts repo, in priority order.
 * The Fury clone (with the `fury_` prefix) is preferred over the legacy name.
 */
const SUPER_TOKEN_SCRIPTS_REPO_DIRS = ['fury_mp-op-pp-woocommerce-scripts', 'mp-op-pp-woocommerce-scripts'];

/**
 * Resolves the scripts repo directory where the bundles are copied.
 *
 * Order: SUPER_TOKEN_SCRIPTS_REPO_PATH env override → a sibling matching one of
 * SUPER_TOKEN_SCRIPTS_REPO_DIRS. Throws with a clear message if none exists, so
 * the build never copies to a wrong/stale folder silently.
 *
 * @returns {string} Absolute path to the scripts repo root.
 */
function resolveSuperTokenScriptsRepoDir () {
  if (process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH) {
    const overridePath = path.resolve(process.env.SUPER_TOKEN_SCRIPTS_REPO_PATH);
    if (!fs.existsSync(overridePath) || !fs.statSync(overridePath).isDirectory()) {
      throw new Error(`SUPER_TOKEN_SCRIPTS_REPO_PATH points to a non-existent directory: ${overridePath}`);
    }
    return overridePath;
  }

  for (const dirName of SUPER_TOKEN_SCRIPTS_REPO_DIRS) {
    const candidatePath = path.resolve(__dirname, `../${dirName}`);
    if (fs.existsSync(candidatePath)) {
      return candidatePath;
    }
  }

  throw new Error(
    `Scripts repo not found. Expected one of [${SUPER_TOKEN_SCRIPTS_REPO_DIRS.join(', ')}] as a sibling of `
    + `${__dirname}, or set SUPER_TOKEN_SCRIPTS_REPO_PATH.`
  );
}

/**
 * Copy super-token bundles to external scripts project
 *
 * Maps the product variant to its CDN folder: 'v2' is served from the legacy
 * 'v1/' folder (retro-compatibility, see DD-3), 'v2.1' from 'v2.1/'.
 *
 * @param {string} version Variant being copied ('v2' or 'v2.1'). Defaults to the active variant (from PHP).
 */
function copySuperTokenBundlesToScriptsProject (version = getActiveSuperTokenVersion()) {
  const folder = SUPER_TOKEN_FOLDER_MAP[version] || version;
  const targetDir = path.join(resolveSuperTokenScriptsRepoDir(), `src/scripts/super-token/${folder}`);
  const sourceCssPath = path.resolve('./assets/css/checkouts/super-token/super-token.bundle.css');
  const sourceJsPath = path.resolve('./assets/js/checkouts/super-token/super-token.bundle.js');

  fs.mkdirSync(targetDir, { recursive: true });
  fs.copyFileSync(sourceCssPath, path.join(targetDir, 'super-token.bundle.css'));
  fs.copyFileSync(sourceJsPath, path.join(targetDir, 'super-token.bundle.js'));
  fs.rmSync(sourceCssPath);
  fs.rmSync(sourceJsPath);
}

/**
 * Bundle super-token CSS/JS and copy output to scripts project
 *
 * @param {string} version Variant to bundle and copy (e.g. 'v2', 'v2.1'). Defaults to the active variant (from PHP).
 */
function bundleAndCopySuperTokenAssets (version = getActiveSuperTokenVersion()) {
  bundleSuperTokenCss(version);
  bundleSuperTokenJs(version);
  copySuperTokenBundlesToScriptsProject(version);
}

/**
 * Bundle and copy every Super Token A/B variant declared in SUPER_TOKEN_FOLDER_MAP.
 *
 * Single entrypoint for `build:super-token:bundle` so that a new variant is
 * never forgotten: it is generated automatically as soon as it is added to the
 * map. Variants are processed sequentially because each one reuses the same
 * intermediate bundle path before copying.
 */
function bundleAllSuperTokenVariants () {
  // WARNING: intermediate bundle paths are shared between variants — do NOT
  // parallelize this loop (e.g. Promise.all). A parallel run would overwrite
  // one variant's intermediate bundle before it is copied, corrupting output.
  Object.keys(SUPER_TOKEN_FOLDER_MAP).forEach((version) => bundleAndCopySuperTokenAssets(version));
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

  const criticalFiles = globSync('assets/**/*.min.{js,css}');

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
  SUPER_TOKEN_FOLDER_MAP,
  SUPER_TOKEN_LOADER_VERSION,
  getActiveSuperTokenVersion,
  resolveSuperTokenLoaderVersion,
  resolveSuperTokenScriptsRepoDir,
  minifyFiles,
  bundleSuperTokenCss,
  bundleSuperTokenJs,
  copySuperTokenBundlesToScriptsProject,
  bundleAndCopySuperTokenAssets,
  bundleAllSuperTokenVariants,
  generatePotFiles,
  generateIntegrityManifest,
  setupSuperToken
};

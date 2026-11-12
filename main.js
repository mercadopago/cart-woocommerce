const fs = require('fs');
const readline = require('readline');
const path = require('path');
const minify = require('minify');
const wpPot = require('wp-pot');
const { sync: globSync } = require('glob');
const { INTEGRITY_IGNORED_ASSETS } = require('./scripts/public/verify-integrity');

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

/** Canonical experience and JS/CSS telemetry stamp; not a runtime selector. */
const SUPER_TOKEN_LOADER_VERSION = { 'v2.1': '1.2.13' };

/**
 * Compile one Super Token SCSS entry into a compressed CSS file (both under
 * `assets/css/checkouts/super-token`). Used only by the canonical build.
 */
function compileScss (entryName, outputName, loaderVersion = null) {
  const stDir = './assets/css/checkouts/super-token';
  const entry = path.resolve(`${stDir}/${entryName}`);
  if (!fs.existsSync(entry)) {
    throw new Error(`compileScss: SCSS entry not found: ${entry}`);
  }

  const compressed = require('sass').compile(entry, { style: 'compressed' }).css;
  // A string token keeps multi-dot versions stable across browser CSSOM serializers.
  const versionStamp = loaderVersion
    ? `:root{--mp-super-token-loader-version:${JSON.stringify(loaderVersion)}}`
    : '';
  const outputFilePath = path.resolve(`${stDir}/${outputName}`);
  fs.writeFileSync(outputFilePath, `${versionStamp}${compressed}`);
  return outputFilePath;
}

/** Compile one transitional stylesheet for canonical v2.1 and independently cached older JS. */
function compileSuperTokenCss () {
  // TODO(PSW-4853): remove v2 selectors in the next Scripts release once
  // js_version:1_2_11 stays near zero for several days after CDN publication.
  // This is independent of retiring v1/, which serves the current v2.1 JS.
  compileScss('super-token.scss', 'super-token.bundle.min.css', SUPER_TOKEN_LOADER_VERSION['v2.1']);
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

/** Stage the single readable webpack output; the Scripts repo owns CDN optimization and aliases. */
function stageSuperTokenJsBundle () {
  stageOneJsBundle('super-token', 'super-token.bundle.js');
}

/**
 * Build the canonical Super Token CSS and staged JS handoff. Single entrypoint for `build:super-token:bundle`.
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

const LANGUAGES_DIR = './i18n/languages';
// Copy shown in the card checkout (Super Token block and new card form) shares one Spanish.
const NEUTRAL_SPANISH_CONTEXT_PREFIXES = ['super token', 'card checkout'];
const NEUTRAL_SPANISH_CREDITS_OVERRIDES = new Map([
  [JSON.stringify({ context: 'credits buyer banner', msgid: 'Buy now, pay later' }), 'Compra ahora, paga después'],
  [JSON.stringify({ context: null, msgid: 'Buy in up to 12 installments without credit cards' }), 'Compra ahora y paga después en hasta 12 cuotas'],
]);

function poEntryKey (entry) {
  const lines = entry.split('\n');
  const field = (name) => {
    const values = [];
    let current = null;
    for (const line of lines) {
      const keyword = line.match(/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?) "(.*)"$/);
      if (keyword) {
        current = keyword[1];
        if (current === name) values.push(keyword[2]);
        continue;
      }
      const continuation = line.match(/^"(.*)"$/);
      if (continuation && current === name) values.push(continuation[1]);
    }
    return values.length ? values.join('') : null;
  };
  return { context: field('msgctxt'), msgid: field('msgid') };
}

function readPoEntries (locale) {
  const content = fs.readFileSync(`${LANGUAGES_DIR}/woocommerce-mercadopago-${locale}.po`, 'utf8');
  return content.replace(/\n+$/, '').split(/\n\s*\n/);
}

function usesNeutralSpanish (entry) {
  const { context } = poEntryKey(entry);
  return context !== null && NEUTRAL_SPANISH_CONTEXT_PREFIXES.some((prefix) => context.startsWith(prefix));
}

/**
 * Generate the generic es catalog: the Argentine catalog for the whole plugin, with the
 * card checkout copy (Super Token and new card form) taken from es_MX — the closest the plugin has
 * to neutral Spanish. Two exact Credits entries use the neutral copy from the handoff.
 */
function generateGenericSpanishCatalog () {
  const mexicanNeutralEntries = new Map(
    readPoEntries('es_MX')
      .filter(usesNeutralSpanish)
      .map((entry) => [JSON.stringify(poEntryKey(entry)), entry])
  );

  const entries = readPoEntries('es_AR').map((entry, index) => {
    if (index === 0) {
      return entry.replace(/^"Language: es_AR\\n"$/m, '"Language: es\\n"');
    }
    const creditsCopy = NEUTRAL_SPANISH_CREDITS_OVERRIDES.get(JSON.stringify(poEntryKey(entry)));
    if (creditsCopy !== undefined) {
      return entry.replace(/^msgstr ".*"(?:\n".*")*/m, () => `msgstr ${JSON.stringify(creditsCopy)}`);
    }
    if (!usesNeutralSpanish(entry)) {
      return entry;
    }
    const mexicanEntry = mexicanNeutralEntries.get(JSON.stringify(poEntryKey(entry)));
    if (!mexicanEntry) {
      throw new Error(`es_MX is missing the neutral Spanish entry: ${poEntryKey(entry).msgid}`);
    }
    return mexicanEntry;
  });

  fs.writeFileSync(`${LANGUAGES_DIR}/woocommerce-mercadopago-es.po`, `${entries.join('\n\n')}\n`);
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

  // Configure the dev runtime constants in the PHP source of truth:
  // bundle toggle and SDK env. The loader version is a release concern (bump the
  // SUPER_TOKEN_LOADER_VERSION map + rebuild the bundle), intentionally not handled here.
  const phpPath = 'src/WoocommerceMercadoPago.php';
  let phpContent = fs.readFileSync(phpPath, 'utf8');

  phpContent = phpContent.replace(
    /private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = \w+;/,
    `private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = ${useBundle};`
  );

  phpContent = phpContent.replace(
    /private const PLUGIN_SDK_ENV = '[^']+';/,
    `private const PLUGIN_SDK_ENV = '${env}';`
  );

  fs.writeFileSync(phpPath, phpContent, 'utf8');
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

  // Every served minified asset is covered, including the canonical Super Token CSS. Local
  // Smart Pix review assets are git-ignored and never shipped, so generator and verifier share
  // the same literal allowlist and cannot disagree about them.
  const criticalFiles = globSync('assets/**/*.min.{js,css}', {
    ignore: INTEGRITY_IGNORED_ASSETS,
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
  minifyFiles,
  compileSuperTokenCss,
  stageSuperTokenJsBundle,
  buildSuperTokenAssets,
  generatePotFiles,
  generateGenericSpanishCatalog,
  generateIntegrityManifest,
  setupSuperToken
};

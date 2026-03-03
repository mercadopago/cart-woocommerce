const fs = require('fs');
const readline = require('readline');
const path = require('path');
const minify = require('minify');
const wpPot = require('wp-pot');

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
 * Bundle JS files for super-token checkout without minification
 */
function bundleSuperTokenJs () {
  const jsBaseDir = './assets/js/checkouts/super-token';
  const jsOutputFilePath = path.resolve(`${jsBaseDir}/super-token.bundle.js`);
  const jsFiles = findFilesInDir(jsBaseDir, '.js', '/blocks');
  const jsFilesToBundle = jsFiles
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

  fs.writeFileSync(jsOutputFilePath, jsConcatenatedContent);
  return jsOutputFilePath;
}

/**
 * Bundle CSS files for super-token checkout without minification
 */
function bundleSuperTokenCss () {
  const cssBaseDir = './assets/css/checkouts/super-token';
  const cssOutputFilePath = path.resolve(`${cssBaseDir}/super-token.bundle.css`);
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

  fs.writeFileSync(cssOutputFilePath, cssConcatenatedContent);
  return cssOutputFilePath;
}

/**
 * Copy super-token bundles to external scripts project
 */
function copySuperTokenBundlesToScriptsProject () {
  const targetDir = path.resolve(__dirname, '../mp-op-pp-woocommerce-scripts/src/scripts/super-token/v1');
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
 */
function bundleAndCopySuperTokenAssets () {
  bundleSuperTokenCss();
  bundleSuperTokenJs();
  copySuperTokenBundlesToScriptsProject();
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
  const version = await ask('Qual será a versão do Super Token? (ex: 1.0.0): ');
  if (!/^\d+\.\d+\.\d+/.test(version)) {
    console.error('Versão inválida. Use o formato semver (ex: 1.2.3)');
    process.exit(1);
  }

  const env = await ask('Qual ambiente do JS SDK? (prod, beta, gama): ');
  if (!['prod', 'beta', 'gama'].includes(env)) {
    console.error('Ambiente inválido. Use: prod, beta ou gama');
    process.exit(1);
  }

  rl.close();

  // 1. Set PLUGIN_SUPER_TOKEN_USE_BUNDLE = false and PLUGIN_SDK_ENV
  const phpPath = 'src/WoocommerceMercadoPago.php';
  let phpContent = fs.readFileSync(phpPath, 'utf8');

  phpContent = phpContent.replace(
    /private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = \w+;/,
    'private const PLUGIN_SUPER_TOKEN_USE_BUNDLE = false;'
  );

  phpContent = phpContent.replace(
    /private const PLUGIN_SDK_ENV = '[^']+';/,
    `private const PLUGIN_SDK_ENV = '${env}';`
  );

  fs.writeFileSync(phpPath, phpContent, 'utf8');
  console.log(`✔ ${phpPath}: PLUGIN_SUPER_TOKEN_USE_BUNDLE = false`);
  console.log(`✔ ${phpPath}: PLUGIN_SDK_ENV = '${env}'`);

  // 2. Update SUPER_TOKEN_JS_VERSION in JS file
  const jsPath = 'assets/js/checkouts/super-token/mp-super-token.js';
  let jsContent = fs.readFileSync(jsPath, 'utf8');

  jsContent = jsContent.replace(
    /const SUPER_TOKEN_JS_VERSION = '[^']+';/,
    `const SUPER_TOKEN_JS_VERSION = '${version}';`
  );

  fs.writeFileSync(jsPath, jsContent, 'utf8');
  console.log(`✔ ${jsPath}: SUPER_TOKEN_JS_VERSION = '${version}'`);

  // 3. Update --mp-super-token-loader-version in CSS file
  const cssPath = 'assets/css/checkouts/super-token/mp-super-token.css';
  let cssContent = fs.readFileSync(cssPath, 'utf8');

  cssContent = cssContent.replace(
    /--mp-super-token-loader-version: [^;]+;/,
    `--mp-super-token-loader-version: ${version};`
  );

  fs.writeFileSync(cssPath, cssContent, 'utf8');
  console.log(`✔ ${cssPath}: --mp-super-token-loader-version: ${version}`);

  console.log('\nConfiguração concluída!');
}

module.exports = {
  minifyFiles,
  bundleSuperTokenCss,
  bundleSuperTokenJs,
  copySuperTokenBundlesToScriptsProject,
  bundleAndCopySuperTokenAssets,
  generatePotFiles,
  setupSuperToken
};

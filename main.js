const fs = require('fs');
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

module.exports = {
  minifyFiles,
  bundleSuperTokenCss,
  bundleSuperTokenJs,
  copySuperTokenBundlesToScriptsProject,
  bundleAndCopySuperTokenAssets,
  generatePotFiles
};

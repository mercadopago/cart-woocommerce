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
  const isNotMinifiedAndHasSelectedExtension = (filePath) => filePath.includes(`.${extension}`) && !filePath.includes('.min');
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

module.exports = { minifyFiles, generatePotFiles };

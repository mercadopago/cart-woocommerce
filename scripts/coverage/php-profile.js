'use strict';

const fs = require('fs');
const path = require('path');

function phpProfile(root, requested, recipe, inventory) {
  if (!['woo', 'generic'].includes(requested)) throw new Error('Invalid PHP coverage profile');
  const manifestPath = path.join(root, 'composer.json');
  const composer = fs.existsSync(manifestPath) ? JSON.parse(fs.readFileSync(manifestPath, 'utf8')) : {};
  const mappings = composer.autoload?.['psr-4'] || {};
  const development = composer['autoload-dev']?.['psr-4'] || {};
  const signals = composer.name === recipe.composerName
    || Object.keys({ ...mappings, ...development }).some(key => key.toLowerCase().startsWith(recipe.namespace.toLowerCase()))
    || fs.existsSync(path.join(root, 'woocommerce-mercadopago.php'))
    || recipe.dependencies.some(item => fs.existsSync(path.join(root, item.path)))
    || inventory.some(file => /namespace\s+MercadoPago\\Woocommerce(?:\\|\s*;)/i.test(fs.readFileSync(path.join(root, file), 'utf8')));
  if (requested === 'generic') {
    if (signals) throw new Error('Generic PHP coverage profile rejects WooCommerce identity or partial sources');
    return { profile: requested, dependencies: [] };
  }
  const target = mappings[recipe.namespace];
  const paths = (Array.isArray(target) ? target : [target]).filter(Boolean).map(value => value.replace(/\/$/, ''));
  if (composer.name !== recipe.composerName || paths.length !== 1 || paths[0] !== 'src') throw new Error('Woo PHP coverage profile requires the original Composer identity and PSR-4 mapping');
  for (const item of [...recipe.dependencies, ...recipe.closure, ...recipe.interfaces, ...recipe.parents.filter(item => !item.internal)]) {
    const file = path.join(root, item.path);
    if (!fs.existsSync(file) || path.relative(root, fs.realpathSync(file)).startsWith('..')) throw new Error(`Woo PHP coverage bootstrap identity missing or outside snapshot: ${item.path}`);
  }
  const declarationPaths = [...recipe.dependencies, ...recipe.closure].map(item => item.path);
  return { profile: requested, dependencies: recipe.dependencies, captureWhitelist: inventory.filter(file => declarationPaths.includes(file)), sourceInventory: inventory };
}

module.exports = { phpProfile };

const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '../../../');

const aliases = {
  'assets/js': path.join(ROOT_DIR, 'assets/js'),
};

function resolveAlias(aliasPath) {
  for (const [alias, realPath] of Object.entries(aliases)) {
    if (aliasPath.startsWith(alias)) {
      return aliasPath.replace(alias, realPath);
    }
  }
  return path.join(ROOT_DIR, aliasPath);
}

module.exports = { resolveAlias };
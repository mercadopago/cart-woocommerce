#!/usr/bin/env node
const fs = require('node:fs');
const path = require('node:path');

const PUBLIC_EXPORT_COMMIT = 'de8a3afcec0f74c8085632c0ce5585355eb2ef24';
function present(filename) {
  try { return fs.lstatSync(filename); } catch (error) {
    if (error.code === 'ENOENT') return null;
    throw error;
  }
}
async function main() {
  const root = path.resolve(__dirname, '..');
  const corePath = path.join(root, 'scripts/quality/test-core.js');
  const core = present(corePath);
  if (core) {
    if (!core.isFile()) throw new Error('Private quality-core entry must be a regular file.');
    const entry = require(corePath);
    if (typeof entry.main !== 'function') throw new Error('Private quality-core main export is absent.');
    const { withSignalCancellation } = require(path.join(root, 'scripts/quality/process.js'));
    return withSignalCancellation(entry.main);
  }
  if (present(path.join(root, '.fury')) || !/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/.test(PUBLIC_EXPORT_COMMIT)) {
    throw new Error('Quality core is absent outside an identified public archive.');
  }
  process.stdout.write('Quality core: omitted from public archive; skipped (no validation performed).\n');
  return undefined;
}
if (require.main === module) {
  main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = error.exitCode || 1;
  });
}
module.exports = { main };

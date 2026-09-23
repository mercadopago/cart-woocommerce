const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');
const { e2eTimeout, getTimeoutProfile } = require('../helpers/runtime-timeouts');

const E2E_DIR = path.resolve(__dirname, '..');
const REGRESSION_ROOTS = [
  path.join(E2E_DIR, 'flows'),
  ...['mla', 'mlb', 'mlc', 'mlm', 'mco', 'mlu', 'mpe'].map((site) => (
    path.join(E2E_DIR, 'tests', site)
  )),
];

function javascriptFiles(root) {
  return fs.readdirSync(root, { withFileTypes: true }).flatMap((entry) => {
    const target = path.join(root, entry.name);
    if (entry.isDirectory()) return javascriptFiles(target);
    return entry.isFile() && entry.name.endsWith('.js') ? [target] : [];
  });
}

describe('runtime timeout profiles', () => {
  it('keeps the existing budgets in the standard profile', () => {
    assert.equal(e2eTimeout(60000, { E2E_TIMEOUT_PROFILE: 'standard' }), 60000);
    assert.equal(getTimeoutProfile({}), 'standard');
  });

  it('cuts every configured budget in half in the fast profile', () => {
    assert.equal(e2eTimeout(60000, { E2E_TIMEOUT_PROFILE: 'fast' }), 30000);
    assert.equal(e2eTimeout(150000, { E2E_TIMEOUT_PROFILE: 'fast' }), 75000);
    assert.equal(e2eTimeout(500, { E2E_TIMEOUT_PROFILE: 'fast' }), 500);
  });

  it('rejects invalid profiles and timeout values before a run starts', () => {
    assert.throws(() => e2eTimeout(60000, { E2E_TIMEOUT_PROFILE: 'turbo' }), /Perfil de timeout invalido/);
    assert.throws(() => e2eTimeout(0, {}), /numero positivo/);
  });

  it('keeps every Playwright timeout in the shared web regression profile-aware', () => {
    const violations = REGRESSION_ROOTS.flatMap(javascriptFiles).filter((filePath) => {
      const source = fs.readFileSync(filePath, 'utf8');
      return /timeout:\s*\d+|test\.setTimeout\(\d+/.test(source);
    }).map((filePath) => path.relative(E2E_DIR, filePath));

    assert.deepEqual(violations, []);
  });
});

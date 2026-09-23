const assert = require('node:assert/strict');
const path = require('node:path');
const { describe, it } = require('node:test');
const {
  parseArgs,
  productionArtifactPath,
  productionArtifactUrl,
} = require('../fetch-production-artifact');

describe('production artifact fetcher', () => {
  it('builds a fixed WordPress.org URL from a validated version', () => {
    const url = productionArtifactUrl('v8.9.3');

    assert.equal(url.origin, 'https://downloads.wordpress.org');
    assert.equal(url.pathname, '/plugin/woocommerce-mercadopago.8.9.3.zip');
  });

  it('stores the artifact under the ignored E2E results directory', () => {
    const artifactPath = productionArtifactPath('8.9.3');

    assert.equal(path.basename(artifactPath), 'woocommerce-mercadopago.8.9.3.zip');
    assert.match(artifactPath, /e2e\/results\/artifacts/);
  });

  it('supports dry-run and rejects version injection', () => {
    assert.deepEqual(parseArgs(['--version', 'v8.9.3', '--dry-run']), {
      version: '8.9.3',
      dryRun: true,
    });
    assert.throws(() => parseArgs(['--version', '8.9.3?host=evil']), /invalida/);
  });
});
